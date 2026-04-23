<?php

namespace App\Controllers;

use App\controllers\Controller;

class UserController extends Controller
{
    public function create()
    {

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $username = $this->sanitaze($_POST['username'] ?? null);
        $password = $this->sanitaze($_POST['password'] ?? null);
        $fullname = $this->sanitaze($_POST['fullname'] ?? null);
        $role     = $this->sanitaze($_POST['role'] ?? 'vendeur');
        $actif    = $this->sanitaze($_POST['actif'] ?? 1);

        if (!$username || !$password || !$fullname) {
            $this->status(400)->json(['error' => 'Champs obligatoires manquants']);
            return;
        }

        $userModel = new \App\Models\User();
        $userModel->create($username, $password, $fullname, $role, $actif);

        $this->json(['success' => true, 'message' => 'user create !']);
    }

    public function update()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $id = $this->sanitaze($_POST['id'] ?? null);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = [];
        if (isset($_POST['nom_utilisateur'])) {
            $data['nom_utilisateur'] = $this->sanitaze($_POST['nom_utilisateur']);
        }
        if (isset($_POST['mot_de_passe'])) {
            $data['mot_de_passe']    = $this->sanitaze($_POST['mot_de_passe']);
        }
        if (isset($_POST['nom_complet'])) {
            $data['nom_complet']     = $this->sanitaze($_POST['nom_complet']);
        }
        if (isset($_POST['role'])) {
            $data['role']            = $this->sanitaze($_POST['role']);
        }
        if (isset($_POST['actif'])) {
            $data['actif']           = $this->sanitaze($_POST['actif']);
        }

        $userModel = new \App\Models\User();
        $success = $userModel->update($id, $data);

        $this->json(['success' => $success]);
    }

    public function all()
    {
        $userModel = new \App\Models\User();
        $users = $userModel->all();
        $this->json($users);
    }

    public function delete()
    {
        $id = $this->sanitaze($_POST['id'] ?? null);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $userModel = new \App\Models\User();
        if ($userModel->exist($id)) {
            $userModel->delete($id);
            $this->json(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
        } else {
            $this->status(404)->json(['error' => 'Utilisateur inexistant']);
        }
    }
}
