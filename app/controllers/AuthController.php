<?php

namespace App\Controllers;

require_once dirname(__DIR__, 2) . '/config/config.php';

use App\Models\User;
use App\Models\OtpCode;
use App\Models\PasswordReset;
use App\controllers\Controller;

class AuthController extends Controller
{
    private $maxLoginAttempts = 5;
    private $lockoutMinutes = 15;

    public function showLogin()
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard');
            exit;
        }
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/login.php';
    }

    public function showVerifyOtp()
    {
        if (!isset($_SESSION['otp_user_id'])) {
            header('Location: /');
            exit;
        }
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/otp-verify.php';
    }

    public function showForgotPassword()
    {
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/forgot-password.php';
    }

    public function showResetPassword()
    {
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/reset-password.php';
    }

    // ── Rate limiting ───────────────────────────────────────────

    private function isRateLimited($username)
    {
        $db = \App\Core\Database::getInstance();
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $result = $db->fetch($sql, [$username, $this->lockoutMinutes]);
        return ($result['attempts'] ?? 0) >= $this->maxLoginAttempts;
    }

    private function recordLoginAttempt($username)
    {
        $db = \App\Core\Database::getInstance();
        $db->query("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)", [
            $username, $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }

    private function clearLoginAttempts($username)
    {
        $db = \App\Core\Database::getInstance();
        $db->execute("DELETE FROM login_attempts WHERE username = ?", [$username]);
    }

    // ── Login (étape 1) ─────────────────────────────────────────

    public function login()
    {
        $username = $this->sanitaze(trim($_POST['username'] ?? ''));
        $password = $this->sanitaze(trim($_POST['password'] ?? ''));

        // Rate limiting
        if ($this->isRateLimited($username)) {
            $this->json(['success' => false, 'message' => "Trop de tentatives. Réessayez dans {$this->lockoutMinutes} minutes."]);
            return;
        }

        $userModel = new User();
        $user = $userModel->login($username, $password);

        if (!$user) {
            $this->recordLoginAttempt($username);
            
            // Vérifier si on atteint le seuil pour notification
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                    WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            $result = $db->fetch($sql, [$username, $this->lockoutMinutes]);
            if (($result['attempts'] ?? 0) >= $this->maxLoginAttempts) {
                $this->notifySuperAdmins('suspicious_action', 
                    'Tentatives de connexion suspectes',
                    "L'utilisateur '$username' a atteint {$this->maxLoginAttempts} tentatives échouées depuis l'IP " . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue')
                );
            }
            
            $this->json(['success' => false, 'message' => 'Identifiants incorrects']);
            return;
        }

        // Check if user is active
        if (isset($user['actif']) && (int)$user['actif'] === 0) {
            $this->json(['success' => false, 'message' => 'Votre compte présente un souci, veuillez contacter votre administrateur']);
            return;
        }

        // Effacer les tentatives de login après succès
        $this->clearLoginAttempts($username);

        // Vérifier si la 2FA est activée
        if (!empty($user['two_factor_enabled'])) {
            // Stocker les infos en session temporaire (pas encore authentifié)
            $_SESSION['otp_user_id'] = $user['id'];
            $_SESSION['otp_user_data'] = [
                'id'              => $user['id'],
                'nom_utilisateur' => $user['nom_utilisateur'],
                'nom_complet'     => $user['nom_complet'],
                'role'            => $user['role'],
                'shop_id'         => $user['shop_id'] ?? null,
                'agent_code'      => $user['agent_code'] ?? '',
                'email'           => $user['email'] ?? null,
                'telephone'       => $user['telephone'] ?? null
            ];

            // Générer et envoyer OTP
            $otpModel = new OtpCode();
            $code = $otpModel->generate($user['id'], 'login', 'email');

            // Envoyer par email (si configuré)
            $this->sendOtpByEmail($user['email'] ?? null, $code, $user['nom_complet']);
            // Envoyer par SMS (si configuré)
            $this->sendOtpBySms($user['telephone'] ?? null, $code);

            $this->json([
                'success' => true,
                'requires_otp' => true,
                'message' => 'Code OTP envoyé. Vérifiez votre email ou téléphone.'
            ]);
            return;
        }

        // Pas de 2FA : connexion directe
        $this->completeLogin($user);
        $this->json(['success' => true]);
    }

    // ── Vérification OTP (étape 2) ──────────────────────────────

    public function verifyOtp()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $code = $this->sanitaze(trim($input['code'] ?? ''));
        $userId = $_SESSION['otp_user_id'] ?? null;

        if (!$userId || !$code) {
            $this->status(400)->json(['success' => false, 'message' => 'Code OTP requis']);
            return;
        }

        $otpModel = new OtpCode();

        // Vérifier le nombre de tentatives récentes
        if ($otpModel->countRecentAttempts($userId, 'login', 15) > 10) {
            unset($_SESSION['otp_user_id'], $_SESSION['otp_user_data']);
            $this->status(429)->json(['success' => false, 'message' => 'Trop de tentatives. Reconnectez-vous.']);
            return;
        }

        if ($otpModel->verify($userId, $code, 'login')) {
            $userData = $_SESSION['otp_user_data'];
            unset($_SESSION['otp_user_id'], $_SESSION['otp_user_data']);
            $this->completeLogin($userData);
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Code OTP invalide ou expiré']);
        }
    }

    // ── Renvoi OTP ──────────────────────────────────────────────

    public function resendOtp()
    {
        $userId = $_SESSION['otp_user_id'] ?? null;
        $userData = $_SESSION['otp_user_data'] ?? null;

        if (!$userId || !$userData) {
            $this->status(400)->json(['success' => false, 'message' => 'Session expirée']);
            return;
        }

        $otpModel = new OtpCode();

        // Limiter les renvois
        if ($otpModel->countRecentAttempts($userId, 'login', 5) > 5) {
            $this->status(429)->json(['success' => false, 'message' => 'Trop de renvois. Attendez quelques minutes.']);
            return;
        }

        $code = $otpModel->generate($userId, 'login', 'email');
        $this->sendOtpByEmail($userData['email'] ?? null, $code, $userData['nom_complet'] ?? '');
        $this->sendOtpBySms($userData['telephone'] ?? null, $code);

        $this->json(['success' => true, 'message' => 'Nouveau code OTP envoyé']);
    }

    // ── Mot de passe oublié ─────────────────────────────────────

    public function forgotPassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $contact = $this->sanitaze(trim($input['contact'] ?? ''));

        if (!$contact) {
            $this->status(400)->json(['success' => false, 'message' => 'Email ou numéro de téléphone requis']);
            return;
        }

        $userModel = new User();
        
        // Chercher par email ou téléphone
        $user = $userModel->findByEmail($contact);
        $channel = 'email';
        if (!$user) {
            $user = $userModel->findByPhone($contact);
            $channel = 'sms';
        }

        if (!$user) {
            // Ne pas révéler si l'utilisateur existe ou non
            $this->json(['success' => true, 'message' => 'Si un compte correspond, un code de vérification a été envoyé.']);
            return;
        }

        $otpModel = new OtpCode();
        $resetModel = new PasswordReset();

        $code = $otpModel->generate($user['id'], 'password_reset', $channel);
        $token = $resetModel->generate($user['id'], $channel);

        // Stocker le token en session pour la vérification
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_user_id'] = $user['id'];

        if ($channel === 'email') {
            $this->sendOtpByEmail($user['email'], $code, $user['nom_complet'], true);
        } else {
            $this->sendOtpBySms($user['telephone'], $code);
        }

        $this->json(['success' => true, 'message' => 'Si un compte correspond, un code de vérification a été envoyé.']);
    }

    public function verifyResetCode()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $code = $this->sanitaze(trim($input['code'] ?? ''));
        $userId = $_SESSION['reset_user_id'] ?? null;

        if (!$userId || !$code) {
            $this->status(400)->json(['success' => false, 'message' => 'Code requis']);
            return;
        }

        $otpModel = new OtpCode();
        if ($otpModel->verify($userId, $code, 'password_reset')) {
            $_SESSION['reset_verified'] = true;
            $this->json(['success' => true, 'message' => 'Code vérifié. Vous pouvez changer votre mot de passe.']);
        } else {
            $this->json(['success' => false, 'message' => 'Code invalide ou expiré']);
        }
    }

    public function resetPassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $newPassword = $input['password'] ?? '';
        $confirmPassword = $input['password_confirm'] ?? '';
        $userId = $_SESSION['reset_user_id'] ?? null;

        if (!$userId || empty($_SESSION['reset_verified'])) {
            $this->status(400)->json(['success' => false, 'message' => 'Session de récupération invalide']);
            return;
        }

        if (strlen($newPassword) < 6) {
            $this->status(400)->json(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->status(400)->json(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
            return;
        }

        $userModel = new User();
        $userModel->updatePassword($userId, $newPassword);

        // Marquer le token comme utilisé
        $token = $_SESSION['reset_token'] ?? null;
        if ($token) {
            $resetModel = new PasswordReset();
            $resetModel->markUsed($token);
        }

        // Nettoyer la session de récupération
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_verified']);

        $this->logAudit('password_reset', 'utilisateur', $userId);
        $this->json(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);
    }

    // ── Compléter la connexion ──────────────────────────────────

    private function completeLogin($user)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nom_utilisateur'] = $user['nom_utilisateur'];
        $_SESSION['nom_complet'] = $user['nom_complet'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['shop_id'] = $user['shop_id'] ?? null;
        $_SESSION['agent_code'] = $user['agent_code'] ?? '';
        $_SESSION['last_activity'] = time();

        $this->logAudit('login', 'utilisateur', $user['id']);
    }

    // ── Logout ──────────────────────────────────────────────────

    public function logout()
    {
        $this->logAudit('logout', 'utilisateur', $_SESSION['user_id'] ?? null);
        session_destroy();
        header('Location: /');
        exit;
    }

    // ── Envoi OTP par email ─────────────────────────────────────

    private function sendOtpByEmail($email, $code, $name = '', $isReset = false)
    {
        if (!$email) return;

        try {
            $config = require dirname(__DIR__) . '/config/mail.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = $config['encryption'] ?: false;
            $mail->Port       = $config['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($email, $name);

            $mail->isHTML(true);

            if ($isReset) {
                $mail->Subject = 'Réinitialisation de mot de passe';
                $mail->Body = "
                    <div style='font-family:Inter,Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f8f9fa;border-radius:12px'>
                        <h2 style='color:#0D0552;margin-bottom:10px'>Réinitialisation de mot de passe</h2>
                        <p>Bonjour <strong>{$name}</strong>,</p>
                        <p>Voici votre code de vérification :</p>
                        <div style='background:linear-gradient(135deg,#0D0552,#30E9FE);color:#fff;font-size:32px;letter-spacing:8px;text-align:center;padding:20px;border-radius:8px;margin:20px 0'>
                            {$code}
                        </div>
                        <p style='color:#666;font-size:14px'>Ce code expire dans <strong>15 minutes</strong>.</p>
                        <p style='color:#999;font-size:12px'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                    </div>";
            } else {
                $mail->Subject = 'Code de vérification OTP';
                $mail->Body = "
                    <div style='font-family:Inter,Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f8f9fa;border-radius:12px'>
                        <h2 style='color:#0D0552;margin-bottom:10px'>Connexion sécurisée</h2>
                        <p>Bonjour <strong>{$name}</strong>,</p>
                        <p>Voici votre code de vérification pour vous connecter :</p>
                        <div style='background:linear-gradient(135deg,#0D0552,#30E9FE);color:#fff;font-size:32px;letter-spacing:8px;text-align:center;padding:20px;border-radius:8px;margin:20px 0'>
                            {$code}
                        </div>
                        <p style='color:#666;font-size:14px'>Ce code expire dans <strong>5 minutes</strong>.</p>
                        <p style='color:#999;font-size:12px'>Si ce n'est pas vous, changez votre mot de passe immédiatement.</p>
                    </div>";
            }

            $mail->AltBody = "Votre code de vérification : $code";
            $mail->send();

            $type = $isReset ? 'PASSWORD_RESET' : 'LOGIN';
            error_log("[OTP-EMAIL] [$type] Sent to: $email");
        } catch (\Exception $e) {
            error_log("OTP email error: " . $e->getMessage());
        }
    }

    // ── Envoi OTP par SMS ───────────────────────────────────────

    private function sendOtpBySms($phone, $code)
    {
        if (!$phone) return;

        try {
            // TODO: Configurer API SMS (OSAT-Energie)
            // Pour le développement, on log le code
            error_log("[OTP-SMS] To: $phone | Code: $code");
        } catch (\Exception $e) {
            error_log("OTP SMS error: " . $e->getMessage());
        }
    }
}
