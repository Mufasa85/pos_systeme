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

    public function getOtpCodes()
    {
        if (!$this->requireSuperAdmin()) return;

        $otpModel = new OtpCode();
        $codes = $otpModel->getAllWithUsers();
        $this->json(['success' => true, 'data' => $codes]);
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
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfToken)) {
            $this->status(403)->json(['success' => false, 'message' => 'Session invalide, veuillez rafraîchir la page']);
            return;
        }
        // Token à usage unique : on le régénère pour empêcher le rejeu
        unset($_SESSION['csrf']);

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

            // Générer et envoyer OTP par SMS et/ou email
            $otpModel = new OtpCode();
            $userEmail = $user['email'] ?? null;
            $userPhone = $user['telephone'] ?? null;
            $channels = [];

            if ($userPhone) {
                $channels[] = 'sms';
            }
            if ($userEmail) {
                $channels[] = 'email';
            }

            // Toujours générer un code (canal principal = sms si dispo, sinon email)
            $primaryChannel = $channels[0] ?? 'sms';
            $code = $otpModel->generate($user['id'], 'login', $primaryChannel);

            // Envoyer sur tous les canaux disponibles
            if ($userPhone) {
                $this->sendOtpBySms($userPhone, $code, $userEmail);
            }
            // if ($userEmail) {
            //     $this->sendOtpByEmail($userEmail, $code, $user['nom_complet'] ?? $user['nom_utilisateur'] ?? '');
            // }

            $msg = 'Code OTP envoyé par SMS.';

            $this->json([
                'success' => true,
                'requires_otp' => true,
                'message' => $msg,
                'channels' => $channels,
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
        $code = $this->sanitaze(trim($_GET['code'] ?? ''));
        $contact = $this->sanitaze(trim($_GET['contact'] ?? ''));

        if (!$code) {
            $input = json_decode(file_get_contents('php://input'), true);
            $code = $this->sanitaze(trim($input['code'] ?? ''));
        }

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

        error_log("[OTP-VERIFY] userId=$userId code=$code");

        if ($otpModel->verify($userId, $code, 'login')) {
            $userData = $_SESSION['otp_user_data'];
            unset($_SESSION['otp_user_id'], $_SESSION['otp_user_data']);
            $this->completeLogin($userData);
            $this->json(['success' => true]);
        } else {
            // Debug : lister les codes en base pour cet utilisateur
            $db = \App\Core\Database::getInstance();
            $rows = $db->fetchAll("SELECT id, code, type, used, expires_at, NOW() as now_mysql FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 3", [$userId]);
            error_log("[OTP-VERIFY-FAIL] userId=$userId code=$code rows=" . json_encode($rows));
            $this->json(['success' => false, 'message' => 'Code OTP invalide ou expiré']);
        }
    }

    // ── Renvoi OTP ──────────────────────────────────────────────

    public function resendOtp()
    {
        $contact = $this->sanitaze(trim($_GET['contact'] ?? ''));
        $channel = $this->sanitaze(trim($_GET['channel'] ?? ''));
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

        $userEmail = $userData['email'] ?? null;
        $userPhone = $userData['telephone'] ?? null;
        $userName = $userData['nom_complet'] ?? $userData['nom_utilisateur'] ?? '';

        // Déterminer les canaux à utiliser
        $sendSms = in_array($channel, ['', 'sms', 'all']) && $userPhone;
        // $sendEmail = in_array($channel, ['', 'email', 'all']) && $userEmail;

        // Si aucun canal valide demandé, fallback sur ce qui est dispo
        if (!$sendSms) {
            $sendSms = (bool)$userPhone;
        }

        $primaryChannel = 'sms';
        $code = $otpModel->generate($userId, 'login', $primaryChannel);

        $sentChannels = [];
        if ($sendSms) {
            $this->sendOtpBySms($userPhone, $code, $userEmail);
            $sentChannels[] = 'SMS';
        }
        // if ($sendEmail) {
        //     $this->sendOtpByEmail($userEmail, $code, $userName);
        //     $sentChannels[] = 'email';
        // }

        $msg = count($sentChannels) > 0
            ? 'Nouveau code OTP envoyé par ' . implode(' et ', $sentChannels) . '.'
            : 'Aucun canal d\'envoi disponible pour cet utilisateur.';

        $this->json(['success' => true, 'message' => $msg]);
    }

    // ── Mot de passe oublié ─────────────────────────────────────

    public function forgotPassword()
    {
        $contact = $this->sanitaze(trim($_GET['contact'] ?? ''));
        if (!$contact) {
            $input = json_decode(file_get_contents('php://input'), true);
            $contact = $this->sanitaze(trim($input['contact'] ?? ''));
        }

        if (!$contact) {
            $this->status(400)->json(['success' => false, 'message' => 'Email ou numéro de téléphone requis']);
            return;
        }

        $userModel = new User();

        // Chercher par téléphone en priorité, puis par email si nécessaire
        $user = $userModel->findByPhone($contact);
        if (!$user) {
            $user = $userModel->findByEmail($contact);
        }

        if (!$user) {
            // Ne pas révéler si l'utilisateur existe ou non
            $this->json(['success' => true, 'message' => 'Si un compte correspond, un code de vérification a été envoyé.']);
            return;
        }

        $phone = $user['telephone'] ?? null;
        $email = $user['email'] ?? null;

        if (empty($phone) && empty($email)) {
            $this->json(['success' => false, 'message' => 'Aucun numéro de téléphone ni email associé à ce compte.']);
            return;
        }

        $otpModel = new OtpCode();
        $resetModel = new PasswordReset();
        $channel = $email ? 'email' : 'sms';

        $code = $otpModel->generate($user['id'], 'password_reset', $channel);
        $token = $resetModel->generate($user['id'], $channel);

        // Stocker le token en session pour la vérification
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_user_id'] = $user['id'];

        if ($phone) {
            $this->sendOtpBySms($phone, $code, $email);
        }
        // if ($email) {
        //     $this->sendOtpByEmail($email, $code, $user['nom_complet'] ?? $user['nom_utilisateur'] ?? '', true);
        // }

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
        $_SESSION['full_name'] = $user['nom_complet'];
        $_SESSION['nom_complet'] = $user['nom_complet'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['shop_id'] = $user['shop_id'] ?? null;
        $_SESSION['agent_code'] = $user['agent_code'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['telephone'] = $user['telephone'] ?? '';
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

    private function sendOtpBySms($phone, $code, $email = null)
    {
        if (!$phone) return;

        try {
            // Formater le numéro : 0xxx → 243xxx
            $phone = trim($phone);
            if (strpos($phone, '0') === 0) {
                $phone = '243' . substr($phone, 1);
            }

            $settingsModel = new \App\Models\Settings();
            $token = trim((string)$settingsModel->get('token'));

            $message = $code;
            $smsUrl = 'https://osat-energie.com/dgi/sms_otp/index.php?numero=' . urlencode($phone)
                . '&msg=' . urlencode($message)
                . '&token=' . urlencode($token)
                . ($email ? '&email=' . urlencode($email) : '');

            $ch = curl_init($smsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("[OTP-SMS] Sent to: $phone | HTTP: $httpCode");
        } catch (\Exception $e) {
            error_log("OTP SMS error: " . $e->getMessage());
        }
    }
}
