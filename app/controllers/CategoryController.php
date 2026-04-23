<?php

namespace App\Controllers;

use App\Models\Category;
use App\controllers\Controller;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = new Category();
        $this->json($categories->all());
    }

    public function delete()
    {
        $category = new \App\Models\Category();
        $id = $this->sanitaze($_POST['id']);
        if ($category->exist($id)) {
            $category->deleteCategory($id);
            echo"categorie  supprimer avec success";
        } else {
            echo "categorie inexistant error 404";
        }
    }


    public function create()
    {

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $name = $this->sanitaze($_POST['category'] ?? null);
        if (!$name) {
            $this->status(400)->json(['error' => 'Nom de catégorie manquant']);
            return;
        }

        $categoryModel = new Category();
        $id = $categoryModel->add($name);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
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
