<?php

namespace App\Controllers;

use App\Models\User;

class AuthController
{
    public function showLogin()
    {
        if (isset($_SESSION['user_id'])) {
            #header('Location: ' . APP_URL . '/dashboard');
            exit;
        }
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR. 'views/login.php';
    }

    public function login()
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $userModel = new User();
        $user = $userModel->login($username, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom_utilisateur'] = $user['nom_utilisateur'];
            $_SESSION['nom_complet'] = $user['nom_complet'];
            $_SESSION['role'] = $user['role'];

            // Repondre en JSON pour l'AJAX
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
        }
    }

    public function logout()
    {
        session_destroy();
        // header('Location: /');
    }
}
