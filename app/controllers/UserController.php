<?php

namespace App\Controllers;

use App\controllers\Controller;

class UserController extends Controller
{
    public function create()
    {
        if (!$this->requireAdmin()) return;

        $username  = $this->sanitaze($_POST['username'] ?? null);
        $password  = $this->sanitaze($_POST['password'] ?? null);
        $fullname  = $this->sanitaze($_POST['fullname'] ?? null);
        $role      = $this->sanitaze($_POST['role'] ?? 'vendeur');
        $actif     = $this->sanitaze($_POST['actif'] ?? 1);
        $agentCode = $this->sanitaze($_POST['agent_code'] ?? null);
        $email     = $this->sanitaze($_POST['email'] ?? null);
        $telephone = $this->sanitaze($_POST['telephone'] ?? null);

        // Seul super_admin peut créer des admins
        if ($role === 'admin' && !$this->isSuperAdmin()) {
            $this->status(403)->json(['error' => 'Seul le super_admin peut créer des administrateurs']);
            return;
        }
        // Personne ne peut créer un super_admin via l'API
        if ($role === 'super_admin') {
            $this->status(403)->json(['error' => 'Impossible de créer un super_admin']);
            return;
        }

        if (!$username || !$password || !$fullname) {
            $this->status(400)->json(['error' => 'Champs obligatoires manquants']);
            return;
        }

        // Affecter à la boutique courante (admin crée pour sa boutique)
        $shopId = $this->isSuperAdmin() ? ($this->sanitaze($_POST['shop_id'] ?? null)) : $this->getShopId();

        $userModel = new \App\Models\User();
        $userModel->create($username, $password, $fullname, $role, $actif, $agentCode, $shopId, $email, $telephone);

        $this->logAudit('create', 'utilisateur', null, ['username' => $username, 'role' => $role]);
        $this->json(['success' => true, 'message' => 'Utilisateur créé !']);
    }

    public function update()
    {
        if (!$this->requireAdmin()) return;

        $id = $this->sanitaze($_POST['id'] ?? null);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        // Debug: log received data
        error_log("User update - ID: $id, POST data: " . print_r($_POST, true));

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
        if (isset($_POST['agent_code'])) {
            $data['agent_code']      = $this->sanitaze($_POST['agent_code']);
        }
        if (isset($_POST['email'])) {
            $data['email']           = $this->sanitaze($_POST['email']);
        }
        if (isset($_POST['telephone'])) {
            $data['telephone']       = $this->sanitaze($_POST['telephone']);
        }
        if (isset($_POST['two_factor_enabled'])) {
            $data['two_factor_enabled'] = (int)$_POST['two_factor_enabled'];
        }
        if (isset($_POST['shop_id']) && $this->isSuperAdmin()) {
            $data['shop_id']         = (int)$_POST['shop_id'];
        }

        error_log("User update - data to update: " . print_r($data, true));

        if (empty($data)) {
            $this->json(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
            return;
        }

        $userModel = new \App\Models\User();
        $success = $userModel->update($id, $data);

        error_log("User update - success: " . ($success ? 'true' : 'false'));
        error_log("User update - role in data: " . ($data['role'] ?? 'NOT SET'));

        $this->logAudit('update', 'utilisateur', $id);
        $this->json(['success' => (bool)$success]);
    }

    public function all()
    {
        if (!$this->requireAuth()) return;

        $userModel = new \App\Models\User();
        $callerRole = $_SESSION['role'] ?? 'vendeur';
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $users = $userModel->all($shopId, $callerRole);
        $this->json($users);
    }

    public function delete()
    {
        if (!$this->requireAdmin()) return;

        $id = $this->sanitaze($_POST['id'] ?? 0);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        // Ne pas pouvoir supprimer un super_admin
        $userModel = new \App\Models\User();
        $target = $userModel->exist($id);
        if (!$target) {
            $this->status(404)->json(['error' => 'Utilisateur inexistant']);
            return;
        }
        if ($target['role'] === 'super_admin') {
            $this->status(403)->json(['error' => 'Impossible de supprimer un super_admin']);
            return;
        }
        // Admin ne peut supprimer que les vendeurs de sa boutique
        if (!$this->isSuperAdmin() && $target['shop_id'] != $this->getShopId()) {
            $this->status(403)->json(['error' => 'Cet utilisateur ne fait pas partie de votre boutique']);
            return;
        }

        $userModel->delete($id);
        $this->logAudit('delete', 'utilisateur', $id, ['username' => $target['nom_utilisateur']]);
        $this->json(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
    }

    public function updateProfile()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];

        $data = [];
        if (isset($input['nom_complet']) && trim($input['nom_complet']) !== '') {
            $data['nom_complet'] = $this->sanitaze(trim($input['nom_complet']));
        }
        if (isset($input['email'])) {
            $data['email'] = $this->sanitaze(trim($input['email']));
        }
        if (isset($input['telephone'])) {
            $data['telephone'] = $this->sanitaze(trim($input['telephone']));
        }
        if (isset($input['agent_code'])) {
            $data['agent_code'] = $this->sanitaze(trim($input['agent_code']));
        }

        if (empty($data)) {
            $this->status(400)->json(['success' => false, 'message' => 'Aucune donnée à mettre à jour']);
            return;
        }

        $userModel = new \App\Models\User();
        $userModel->update($userId, $data);

        // Mettre à jour la session
        if (isset($data['nom_complet'])) {
            $_SESSION['full_name'] = $data['nom_complet'];
            $_SESSION['nom_complet'] = $data['nom_complet'];
        }
        if (isset($data['email'])) {
            $_SESSION['email'] = $data['email'];
        }
        if (isset($data['telephone'])) {
            $_SESSION['telephone'] = $data['telephone'];
        }
        if (isset($data['agent_code'])) {
            $_SESSION['agent_code'] = $data['agent_code'];
        }

        $this->logAudit('update_profile', 'utilisateur', $userId);
        $this->json(['success' => true, 'message' => 'Profil mis à jour avec succès']);
    }

    public function changePassword()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (!$currentPassword || !$newPassword) {
            $this->status(400)->json(['success' => false, 'message' => 'Tous les champs sont requis']);
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

        $userModel = new \App\Models\User();
        $user = $userModel->exist($_SESSION['user_id']);

        if (!$user || !password_verify($currentPassword, $user['mot_de_passe'])) {
            $this->status(400)->json(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
            return;
        }

        $userModel->updatePassword($_SESSION['user_id'], $newPassword);
        $this->logAudit('change_password', 'utilisateur', $_SESSION['user_id']);
        $this->json(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);
    }
}
