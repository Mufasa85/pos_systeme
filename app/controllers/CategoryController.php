<?php

namespace App\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = new Category();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $this->json($categories->all($shopId));
    }

    public function delete()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $category = new \App\Models\Category();
        $id = $this->sanitaze($_POST['id']);
        if ($category->exist($id)) {
            $category->deleteCategory($id);
            $this->logAudit('delete', 'categorie', $id);
            $this->json(['success' => true, 'message' => 'Catégorie supprimée avec succès']);
        } else {
            $this->status(404)->json(['error' => 'Catégorie inexistante']);
        }
    }


    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $name = $this->sanitaze($_POST['category'] ?? null);
        if (!$name) {
            $this->status(400)->json(['error' => 'Nom de catégorie manquant']);
            return;
        }

        $categoryModel = new Category();
        $id = $categoryModel->add($name, $this->getShopId());
        $this->logAudit('create', 'categorie', null, ['nom' => $name]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = $this->sanitaze($_POST['id'] ?? null);
        $name = $this->sanitaze($_POST['category'] ?? null);

        if (!$id || !$name) {
            $this->status(400)->json(['error' => 'ID ou nom manquant']);
            return;
        }

        $categoryModel = new Category();
        $success = $categoryModel->update($id, $name);

        $this->json(['success' => $success]);
    }

}
