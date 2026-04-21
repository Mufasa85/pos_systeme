<?php

namespace App\Controllers;

use App\Models\Category;

class CategoryController
{
    public function index()
    {
        $categories = new Category();
        echo json_encode($categories->all());
    }

    public function delete()
    {
        $category = new \App\Models\Category();
        $id = $_POST['id'];
        if ($category->exist($id)) {
            $category->deleteCategory($id);
            echo"categorie  supprimer avec success";
        } else {
            echo "categorie inexistant error 404";
        }
    }


    public function create()
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $name = $_POST['category'] ?? null;
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Nom de catégorie manquant']);
            return;
        }

        $categoryModel = new Category();
        $id = $categoryModel->add($name);
        echo json_encode(['success' => true, 'id' => $id]);
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
        $name = $_POST['category'] ?? null;

        if (!$id || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'ID ou nom manquant']);
            return;
        }

        $categoryModel = new Category();
        $success = $categoryModel->update($id, $name);

        echo json_encode(['success' => $success]);
    }

}
