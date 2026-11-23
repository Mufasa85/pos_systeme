<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Services\ImageProcessor;

class UserController extends Controller
{
    /**
     * POST /api/create/user — création d'un utilisateur.
     *
     * ⚠️ Régression 2026-11-23 : le super_admin créait un utilisateur avec
     * `sanitaze($_POST['shop_id'] ?? null)`. La méthode était typée `string` :
     * un champ optionnel ABSENT (le sélecteur de boutique n'est pas rendu,
     * ou le navigateur envoie un formulaire sans `shop_id`) suffisait à
     * lever un TypeError et à afficher une page blanche :
     * « sanitaze(): Argument #1 ($input) must be of type string, null given ».
     *
     * La boutique est désormais RÉSOLUE (jamais `null` en base) et les
     * entrées sont validées : une erreur métier renvoie un JSON 4xx lisible
     * au lieu d'une erreur PHP 500.
     */
    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        // Formulaire (POST) ou corps JSON : les deux sont acceptés.
        $input = $this->requestInput();

        $username  = trim($this->sanitaze($input['username'] ?? ''));
        $password  = $this->sanitizePassword($input['password'] ?? '');
        $fullname  = trim($this->sanitaze($input['fullname'] ?? ''));
        $role      = trim($this->sanitaze($input['role'] ?? '')) ?: 'vendeur';
        $actif     = (int)$this->sanitaze($input['actif'] ?? 1) === 0 ? 0 : 1;
        $agentCode = trim($this->sanitaze($input['agent_code'] ?? ''));
        $email     = trim($this->sanitaze($input['email'] ?? ''));
        $telephone = trim($this->sanitaze($input['telephone'] ?? ''));

        // La 2FA est un choix explicite du formulaire : `null` = non fourni
        // (la colonne garde son défaut SQL).
        $twoFactor = null;
        if (array_key_exists('two_factor_enabled', $input)) {
            $twoFactor = (int)$this->sanitaze($input['two_factor_enabled']) === 1 ? 1 : 0;
        }

        // Seul super_admin peut créer des admins
        if ($role === 'admin' && !$this->isSuperAdmin()) {
            $this->status(403)->json([
                'success' => false,
                'error'   => 'Seul le super_admin peut créer des administrateurs',
            ]);
            return;
        }
        // Personne ne peut créer un super_admin via l'API
        if ($role === 'super_admin') {
            $this->status(403)->json([
                'success' => false,
                'error'   => 'Impossible de créer un super_admin',
            ]);
            return;
        }
        // Liste blanche : `utilisateurs.role` est un ENUM. Une valeur hors
        // liste provoquerait une erreur SQL (et une page blanche).
        if (!in_array($role, ['admin', 'vendeur'], true)) {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'Rôle invalide',
            ]);
            return;
        }

        if (!$username || !$password || !$fullname) {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'Champs obligatoires manquants',
            ]);
            return;
        }

        if (strlen($password) < 6) {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'Le mot de passe doit contenir au moins 6 caractères',
            ]);
            return;
        }

        // La 2FA est uniquement livrée par SMS (cf. AuthController::login()) :
        // l'activer sans numéro de téléphone rendrait le compte INUTILISABLE
        // (aucun code ne peut être reçu à la connexion).
        if ($twoFactor === 1 && $telephone === '') {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'Téléphone requis',
                'message' => 'La vérification en deux étapes (2FA) nécessite un numéro de téléphone '
                    . '(le code OTP est envoyé par SMS).',
            ]);
            return;
        }

        // Boutique de rattachement : OBLIGATOIRE et VALIDÉE (jamais `null`).
        // Un utilisateur sans boutique ne peut pas travailler : on refuse
        // la création plutôt que d'enregistrer un compte orphelin.
        $shopId = $this->resolveCreationShopId($input);
        if ($shopId === null) {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'Boutique non sélectionnée',
                'code'    => 'shop_required',
                'message' => $this->isSuperAdmin()
                    ? 'Sélectionnez la boutique de rattachement du compte avant de l\'enregistrer. '
                        . 'Un utilisateur appartient toujours à une boutique.'
                    : 'Votre compte n\'est rattaché à aucune boutique. '
                        . 'Contactez le super_admin pour qu\'il vous affecte à une boutique.',
            ]);
            return;
        }

        $shopModel = new \App\Models\Shop();
        if (!$shopModel->findById($shopId)) {
            $this->status(404)->json([
                'success' => false,
                'error'   => 'Boutique introuvable',
            ]);
            return;
        }

        $userModel = new \App\Models\User();

        // `utilisateurs.nom_utilisateur` porte une contrainte UNIQUE : on
        // refuse explicitement plutôt que de laisser remonter une
        // PDOException (page blanche).
        if ($userModel->usernameExists($username)) {
            $this->status(409)->json([
                'success' => false,
                'error'   => 'Ce nom d\'utilisateur existe déjà',
            ]);
            return;
        }

        // Code agent : le front génère un code AFxxxx aléatoire, mais le
        // serveur reste l'autorité : `utilisateurs.agent_code` est UNIQUE.
        // Un code vide ou déjà attribué est donc remplacé par un code libre.
        $agentCode = $this->resolveAgentCode($userModel, $agentCode);

        try {
            $userModel->create(
                $username,
                $password,
                $fullname,
                $role,
                $actif,
                $agentCode,
                $shopId,
                $email !== '' ? $email : null,
                $telephone !== '' ? $telephone : null,
                $twoFactor
            );
        } catch (\PDOException $e) {
            error_log('UserController::create - erreur SQL : ' . $e->getMessage());
            $this->status(409)->json([
                'success' => false,
                'error'   => 'Utilisateur déjà existant (nom d\'utilisateur ou code agent)',
            ]);
            return;
        }

        $userId = (int)\App\Core\Database::getInstance()->lastInsertId();

        if ($role === 'vendeur' && $shopId && $userId) {
            (new \App\Models\PayrollEmployee())->createForVendor($userId, $shopId, $agentCode);
        }

        $this->logAudit('create', 'utilisateur', $userId, [
            'username' => $username,
            'role'     => $role,
            'shop_id'  => $shopId,
        ]);
        $this->json(['success' => true, 'message' => 'Utilisateur créé !', 'id' => $userId]);
    }

    /**
     * Résout la boutique de rattachement d'un utilisateur à créer.
     *
     * ⚠️ Règle métier : un utilisateur APPARTIENT TOUJOURS à une boutique.
     * Si aucune boutique n'est sélectionnée, la création est REFUSÉE
     * (jamais de repli automatique) : un utilisateur sans boutique ne peut
     * ni se connecter à la caisse, ni figurer dans un périmètre de paie.
     *
     * Règles (multi-tenant) :
     *   - `super_admin` : la boutique EXPLICITEMENT choisie dans le
     *     formulaire. Aucune boutique en session n'est utilisée : le
     *     super_admin administera plusieurs boutiques, il doit donc
     *     designate celle qui porte le compte.
     *   - `admin` : TOUJOURS sa propre boutique. Le `shop_id` reçu est
     *     ignoré (anti-évasion multi-tenant, cf.
     *     `PageController::resolveTargetShopId()`).
     *
     * @param array<string, mixed> $input
     * @return int|null L'identifiant de la boutique, ou null si aucune
     *                  boutique n'a été sélectionnée.
     */
    private function resolveCreationShopId(array $input): ?int
    {
        if (!$this->isSuperAdmin()) {
            $sessionShopId = (int)($this->getShopId() ?? 0);

            return $sessionShopId > 0 ? $sessionShopId : null;
        }

        $requested = $input['shop_id'] ?? null;
        if ($requested !== null && $requested !== '' && (int)$requested > 0) {
            return (int)$requested;
        }

        // Aucun `shop_id` reçu : on ne devine PAS la boutique.
        return null;
    }

    /**
     * Détermine le code agent final à enregistrer (format AFxxxx).
     *
     * Le modal de création propose un code aléatoire AFxxxx généré côté
     * navigateur. Cette méthode reste la source de vérité côté serveur :
     *
     *   - code vide → un code libre est généré ;
     *   - code déjà attribué à un AUTRE utilisateur → un code libre est
     *     généré (la colonne `utilisateurs.agent_code` est UNIQUE, l'INSERT
     *     échouerait sinon avec une erreur SQL illisible côté interface) ;
     *   - code libre → il est conservé tel quel (l'utilisateur peut le
     *     saisir manuellement s'il le souhaite).
     *
     * @param string|null $code          Code soumis par le formulaire.
     * @param int|null    $exceptUserId  Utilisateur à ignorer dans la
     *                                    recherche de conflit (édition).
     * @return string|null Le code à enregistrer, ou null si aucun code
     *                     libre n'a pu être trouvé.
     */
    private function resolveAgentCode(\App\Models\User $userModel, ?string $code, ?int $exceptUserId = null): ?string
    {
        $code = trim((string) $code);

        if ($code !== '' && !$userModel->agentCodeExists($code, $exceptUserId)) {
            return $code;
        }

        $generated = $userModel->generateAgentCode();
        if ($generated === null) {
            error_log('UserController::resolveAgentCode - impossible de générer un code agent libre');
        }

        return $generated;
    }

    public function update()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $input = $this->requestInput();

        $id = (int)$this->sanitaze($input['id'] ?? '');
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        // Debug: log received data
        error_log("User update - ID: $id, POST data: " . print_r($input, true));
        error_log('User update - FILES: ' . print_r($_FILES, true));

        $data = [];
        if (isset($input['nom_utilisateur'])) {
            $data['nom_utilisateur'] = trim($this->sanitaze($input['nom_utilisateur']));
        }
        if (isset($input['mot_de_passe'])) {
            // Mot de passe : `trim()` uniquement (jamais d'assainissement
            // HTML) — cf. Controller::sanitizePassword().
            $data['mot_de_passe'] = $this->sanitizePassword($input['mot_de_passe']);
        }
        if (isset($input['nom_complet'])) {
            $data['nom_complet']     = trim($this->sanitaze($input['nom_complet']));
        }
        if (isset($input['role'])) {
            $data['role']            = trim($this->sanitaze($input['role']));
        }
        if (isset($input['actif'])) {
            $data['actif']           = (int)$this->sanitaze($input['actif']) === 0 ? 0 : 1;
        }
        if (isset($input['agent_code'])) {
            $data['agent_code']      = trim($this->sanitaze($input['agent_code']));
        }
        if (isset($input['email'])) {
            $data['email']           = trim($this->sanitaze($input['email']));
        }
        if (isset($input['telephone'])) {
            $data['telephone']       = trim($this->sanitaze($input['telephone']));
        }
        if (isset($input['two_factor_enabled'])) {
            $data['two_factor_enabled'] = (int)$this->sanitaze($input['two_factor_enabled']) === 1 ? 1 : 0;
        }
        if (isset($input['shop_id']) && $this->isSuperAdmin()) {
            $data['shop_id']         = (int)$input['shop_id'];
        }
        if (isset($input['profile_image'])) {
            $data['profile_image']   = $this->sanitaze($input['profile_image']);
        }

        // Handle profile image file upload
        $processedImage = $this->processProfileImage();
        if ($processedImage) {
            $data['profile_image'] = $processedImage;

            $userModelForCleanup = new \App\Models\User();
            $oldUser = $userModelForCleanup->exist($id);
            $oldImage = $oldUser['profile_image'] ?? '';
            if ($oldImage && strpos($oldImage, '/media/profile/') === 0) {
                $oldPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . basename($oldImage);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        error_log('User update - data to update: ' . print_r($data, true));

        if (empty($data)) {
            $this->json(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
            return;
        }

        $userModel = new \App\Models\User();

        // Code agent : si l'utilisateur en saisit un déjà attribué à un
        // autre compte, on lui en attribue un nouveau plutôt que de faire
        // échouer la mise à jour (contrainte UNIQUE sur agent_code).
        // Le code courant de l'utilisateur est ignoré dans la recherche.
        if (array_key_exists('agent_code', $data)) {
            $data['agent_code'] = $this->resolveAgentCode($userModel, $data['agent_code'], (int)$id);
        }

        $success = $userModel->update($id, $data);

        error_log('User update - success: ' . ($success ? 'true' : 'false'));
        error_log('User update - role in data: ' . ($data['role'] ?? 'NOT SET'));

        $this->logAudit('update', 'utilisateur', $id);
        $this->json(['success' => (bool)$success]);
    }

    public function all()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $userModel = new \App\Models\User();
        $callerRole = $_SESSION['role'] ?? 'vendeur';
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $users = $userModel->all($shopId, $callerRole);
        $this->json($users);
    }

    public function delete()
    {
        if (!$this->requireAdmin()) {
            return;
        }

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

        // Impossible de supprimer l'utilisateur connecté soi-même
        if ((int)$target['id'] === (int)($_SESSION['user_id'] ?? 0)) {
            $this->status(400)->json(['error' => 'Impossible de supprimer votre propre compte']);
            return;
        }

        // La suppression détachera l'auteur des lignes liées (ventes, paie,
        // hôtel) sans jamais supprimer l'historique — cf. migration
        // 2026_10_25_user_delete_fk_set_null.sql. Le modèle renvoie un
        // message explicite en cas d'échec (contrainte FK non migrée).
        $result = $userModel->delete($id);

        if (empty($result['success'])) {
            $this->status(409)->json([
                'error'   => $result['message'] ?? 'Erreur lors de la suppression de l\'utilisateur',
                'success' => false,
            ]);
            return;
        }

        $this->logAudit('delete', 'utilisateur', $id, ['username' => $target['nom_utilisateur']]);
        $this->json(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
    }

    public function updateProfile()
    {
        if (!$this->requireAuth()) {
            return;
        }

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

        // Code agent : un code déjà attribué à un autre compte est remplacé
        // par un code libre (contrainte UNIQUE sur `utilisateurs.agent_code`).
        // Le code de l'utilisateur connecté n'est pas un conflit.
        if (array_key_exists('agent_code', $data)) {
            $data['agent_code'] = $this->resolveAgentCode($userModel, $data['agent_code'], (int)$userId);
        }

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

    /**
     * Contexte complet de l'utilisateur connecté, en JSON.
     *
     * Route : GET /api/me
     *
     * Retourne en un seul appel :
     *  - la session PHP (rôle, boutique, agent_code, expiration)
     *  - la fiche utilisateur complète relue en BDD
     *  - la boutique d'affectation
     *  - les identifiants légaux (NIF/ISF, ICE, RCCM, NID, PDV) résolus
     *    automatiquement selon le rôle (company_info pour le super_admin,
     *    shops pour un utilisateur de boutique).
     *
     * Les colonnes sensibles (mot de passe, token API) ne sont jamais exposées.
     */
    public function me()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $context = (new \App\Services\CurrentUserService())->getContext();

        $this->json([
            'success' => true,
            'data'    => $context,
        ]);
    }

    public function changePassword()
    {
        if (!$this->requireAuth()) {
            return;
        }

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

    public function uploadProfileImage()
    {
        if (!$this->requireAuth()) {
            return;
        }

        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json(['success' => false, 'message' => 'Erreur lors du téléchargement de l\'image']);
            return;
        }

        $file = $_FILES['profile_image'];
        $maxSize = 5 * 1024 * 1024; // 5MB avant compression

        if ($file['size'] > $maxSize) {
            $this->status(400)->json(['success' => false, 'message' => 'L\'image ne doit pas dépasser 5MB']);
            return;
        }

        if (!ImageProcessor::validate($file['tmp_name'])) {
            $this->status(400)->json(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou GIF']);
            return;
        }

        $imageUrl = $this->processProfileImage();
        if (!$imageUrl) {
            $this->status(500)->json(['success' => false, 'message' => 'Erreur lors de la sauvegarde de l\'image']);
            return;
        }

        // Supprimer l'ancienne image (nouveau format uniquement, hors public/)
        $userId = $_SESSION['user_id'];
        $userModel = new \App\Models\User();
        $oldUser = $userModel->exist($userId);
        $oldImage = $oldUser['profile_image'] ?? '';
        if ($oldImage && strpos($oldImage, '/media/profile/') === 0) {
            $oldPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . basename($oldImage);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $userModel->update($userId, ['profile_image' => $imageUrl]);

        $this->logAudit('upload_profile_image', 'utilisateur', $userId, ['image' => basename($imageUrl)]);
        $this->json(['success' => true, 'image_url' => $imageUrl, 'message' => 'Image de profil mise à jour']);
    }

    /**
     * Valide, redimensionne/compresse et stocke la photo de profil hors de public/.
     * Retourne la référence à stocker en base ('/media/profile/xxx.jpg') ou null.
     */
    private function processProfileImage(): ?string
    {
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['profile_image'];
        $maxSize = 5 * 1024 * 1024; // 5MB avant compression
        if ($file['size'] > $maxSize) {
            return null;
        }

        if (!ImageProcessor::validate($file['tmp_name'])) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('profile_', true) . '.jpg';
        $filepath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!ImageProcessor::resizeAndSave($file['tmp_name'], $filepath, 500, 85)) {
            return null;
        }

        return '/media/profile/' . $filename;
    }
}
