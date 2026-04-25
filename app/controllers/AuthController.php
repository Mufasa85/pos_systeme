<?php

namespace App\Controllers;

require_once dirname(__DIR__, 2) . '/config/config.php';

use App\Models\User;
use App\controllers\Controller;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/login.php';
    }

    public function login()
    {
        $username = $this->sanitaze(trim($_POST['username'] ?? ''));
        $password = $this->sanitaze(trim($_POST['password'] ?? ''));

        $userModel = new User();
        $user = $userModel->login($username, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom_utilisateur'] = $user['nom_utilisateur'];
            $_SESSION['nom_complet'] = $user['nom_complet'];
            $_SESSION['role'] = $user['role'];

            // Repondre en JSON pour l'AJAX
            $this->json(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
        }
    }

    public function logout()
    {
        session_destroy();
        header('Location: /');
        exit;
    }
}
