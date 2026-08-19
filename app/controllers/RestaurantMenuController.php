<?php

namespace App\Controllers;

use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\controllers\Controller;

class RestaurantMenuController extends Controller
{
    // ── Vue combinée (catégories + plats) ───────────────────────

    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $categoryModel = new RestaurantCategory();
        $itemModel = new RestaurantMenuItem();

        $this->json([
            'categories' => $categoryModel->all($shopId),
            'items'      => $itemModel->all($shopId),
        ]);
    }

    // ── Catégories ───────────────────────────────────────────────

    public function createCategory()
    {
        if (!$this->requireAdmin()) return;

        $nom = $this->sanitaze($_POST['nom'] ?? '');
        $description = isset($_POST['description']) ? $this->sanitaze($_POST['description']) : null;
        $shopId = $this->isSuperAdmin() ? ($_POST['shop_id'] ?? null) : $this->getShopId();

        if (!$nom) {
            $this->status(400)->json(['error' => 'Nom de catégorie requis']);
            return;
        }
        if (!$shopId) {
            $this->status(400)->json(['error' => 'Boutique requise']);
            return;
        }

        $categoryModel = new RestaurantCategory();
        if ($categoryModel->existsNom($nom, $shopId)) {
            $this->status(409)->json(['error' => 'Cette catégorie existe déjà pour cette boutique']);
            return;
        }

        $id = $categoryModel->create(['shop_id' => $shopId, 'nom' => $nom, 'description' => $description]);
        $this->logAudit('create', 'restaurant_category', $id, ['nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateCategory($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID catégorie manquant']);
            return;
        }

        $categoryModel = new RestaurantCategory();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $categoryModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Catégorie introuvable']);
            return;
        }

        $data = [];
        if (isset($_POST['nom'])) {
            $nom = $this->sanitaze($_POST['nom']);
            if ($categoryModel->existsNom($nom, $existing['shop_id'], $id)) {
                $this->status(409)->json(['error' => 'Cette catégorie existe déjà pour cette boutique']);
                return;
            }
            $data['nom'] = $nom;
        }
        if (isset($_POST['description'])) $data['description'] = $this->sanitaze($_POST['description']);

        $categoryModel->update($id, $data);
        $this->logAudit('update', 'restaurant_category', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteCategory($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID catégorie manquant']);
            return;
        }

        $categoryModel = new RestaurantCategory();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $categoryModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Catégorie introuvable']);
            return;
        }

        $categoryModel->delete($id);
        $this->logAudit('delete', 'restaurant_category', $id, ['nom' => $existing['nom']]);
        $this->json(['success' => true]);
    }

    // ── Plats ────────────────────────────────────────────────────

    private function handleImageUpload($nom)
    {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/assets/img/restaurant/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($nom));
        $safeName = trim(preg_replace('/_+/', '_', $safeName), '_');

        $fileName = $safeName . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        $counter = 1;
        while (file_exists($filePath)) {
            $fileName = $safeName . '_' . time() . '_' . $counter . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            $counter++;
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
            return 'assets/img/restaurant/' . $fileName;
        }
        return null;
    }

    public function createItem()
    {
        if (!$this->requireAdmin()) return;

        $nom = $this->sanitaze($_POST['nom'] ?? '');
        $categorieId = (int)($_POST['categorie_id'] ?? 0);
        $prix = (float)($_POST['prix'] ?? 0);
        $description = isset($_POST['description']) ? $this->sanitaze($_POST['description']) : null;
        $tempsPreparation = (int)($_POST['temps_preparation'] ?? 0);
        $disponible = isset($_POST['disponible']) ? (int)$_POST['disponible'] : 1;
        $shopId = $this->isSuperAdmin() ? ($_POST['shop_id'] ?? null) : $this->getShopId();

        if (!$nom || !$categorieId || $prix <= 0) {
            $this->status(400)->json(['error' => 'Nom, catégorie et prix (> 0) requis']);
            return;
        }
        if (!$shopId) {
            $this->status(400)->json(['error' => 'Boutique requise']);
            return;
        }

        $categoryModel = new RestaurantCategory();
        if (!$categoryModel->findById($categorieId, $this->isSuperAdmin() ? null : $shopId)) {
            $this->status(404)->json(['error' => 'Catégorie introuvable']);
            return;
        }

        $image = $this->handleImageUpload($nom);

        $itemModel = new RestaurantMenuItem();
        $id = $itemModel->create([
            'shop_id'           => $shopId,
            'categorie_id'      => $categorieId,
            'nom'               => $nom,
            'description'       => $description,
            'image'             => $image,
            'prix'              => $prix,
            'temps_preparation' => $tempsPreparation,
            'disponible'        => $disponible,
        ]);

        $this->logAudit('create', 'restaurant_menu_item', $id, ['nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateItem($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID plat manquant']);
            return;
        }

        $itemModel = new RestaurantMenuItem();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $itemModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }

        $data = [];
        if (isset($_POST['nom'])) $data['nom'] = $this->sanitaze($_POST['nom']);
        if (isset($_POST['categorie_id'])) $data['categorie_id'] = (int)$_POST['categorie_id'];
        if (isset($_POST['description'])) $data['description'] = $this->sanitaze($_POST['description']);
        if (isset($_POST['prix'])) $data['prix'] = (float)$_POST['prix'];
        if (isset($_POST['temps_preparation'])) $data['temps_preparation'] = (int)$_POST['temps_preparation'];
        if (isset($_POST['disponible'])) $data['disponible'] = (int)$_POST['disponible'];

        $newImage = $this->handleImageUpload($data['nom'] ?? $existing['nom']);
        if ($newImage) {
            $data['image'] = $newImage;
        }

        $itemModel->update($id, $data);
        $this->logAudit('update', 'restaurant_menu_item', $id, $data);
        $this->json(['success' => true]);
    }

    public function toggleItem($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID plat manquant']);
            return;
        }

        $itemModel = new RestaurantMenuItem();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $itemModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $disponible = !empty($input['disponible']);

        $itemModel->toggleDisponible($id, $disponible);
        $this->logAudit('update', 'restaurant_menu_item', $id, ['disponible' => $disponible]);
        $this->json(['success' => true]);
    }

    public function deleteItem($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID plat manquant']);
            return;
        }

        $itemModel = new RestaurantMenuItem();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $itemModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }

        $itemModel->delete($id);
        $this->logAudit('delete', 'restaurant_menu_item', $id, ['nom' => $existing['nom']]);
        $this->json(['success' => true]);
    }
}
