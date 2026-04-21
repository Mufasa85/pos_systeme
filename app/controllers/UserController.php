<?php

namespace App\Controllers;

class UserController
{
    public function create()
    {
        header('Content-Type: application/json');

        // Vérification des droits (admin uniquement)
        // if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        //   http_response_code(403);
        // echo json_encode(['error' => 'Accès refusé']);
        //return;
        # }

        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        $fullname = $_POST['fullname'] ?? null;
        $role     = $_POST['role'] ?? 'vendeur';
        $actif    = $_POST['actif'] ?? 1;

        if (!$username || !$password || !$fullname) {
            http_response_code(400);
            echo json_encode(['error' => 'Champs obligatoires manquants']);
            return;
        }

        $userModel = new \App\Models\User();
        $userModel->create($username, $password, $fullname, $role, $actif);

        echo json_encode(['success' => true, 'message' => 'user create !']);
    }

    public function update()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID manquant']);
            return;
        }

        $data = [];
        if (isset($_POST['nom_utilisateur'])) {
            $data['nom_utilisateur'] = $_POST['nom_utilisateur'];
        }
        if (isset($_POST['mot_de_passe'])) {
            $data['mot_de_passe']    = $_POST['mot_de_passe'];
        }
        if (isset($_POST['nom_complet'])) {
            $data['nom_complet']     = $_POST['nom_complet'];
        }
        if (isset($_POST['role'])) {
            $data['role']            = $_POST['role'];
        }
        if (isset($_POST['actif'])) {
            $data['actif']           = $_POST['actif'];
        }

        $userModel = new \App\Models\User();
        $success = $userModel->update($id, $data);

        echo json_encode(['success' => $success]);
    }

    public function all()
    {
        header('Content-Type: application/json');

        $userModel = new \App\Models\User();
        $users = $userModel->all();

        echo json_encode($users);
    }

    public function delete()
    {
        header('Content-Type: application/json');

        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID manquant']);
            return;
        }

        $userModel = new \App\Models\User();
        if ($userModel->exist($id)) {
            $userModel->delete($id);
            echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur inexistant']);
        }
    }
}
