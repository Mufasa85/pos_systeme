<?php
// app/models/Product.php

class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM produits ORDER BY nom ASC");
    }

    public function findByBarcode($barcode) {
        return $this->db->fetch("SELECT * FROM produits WHERE code_barres = :code_barres", [':code_barres' => $barcode]);
    }

    public function findById($id) {
        return $this->db->fetch("SELECT * FROM produits WHERE id = :id", [':id' => $id]);
    }

    public function create($data) {
        $sql = "INSERT INTO produits (code_barres, nom, categorie, prix, stock, stock_minimum, image)
                VALUES (:code_barres, :nom, :categorie, :prix, :stock, :stock_minimum, :image)";
        $this->db->query($sql, [
            ':code_barres'   => $data['code_barres'],
            ':nom'           => $data['nom'],
            ':categorie'     => $data['categorie'],
            ':prix'          => $data['prix'],
            ':stock'         => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image'         => $data['image'] ?? ''
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function updateStock($id, $quantity) {
        // Decrease stock
        $sql = "UPDATE produits SET stock = stock - :quantite WHERE id = :id";
        return $this->db->query($sql, [':quantite' => $quantity, ':id' => $id]);
    }

    public function update($id, $data) {
        $sql = "UPDATE produits SET 
                code_barres = :code_barres,
                nom = :nom, 
                categorie = :categorie,
                prix = :prix,
                stock = :stock,
                stock_minimum = :stock_minimum,
                image = :image
                WHERE id = :id";
        return $this->db->query($sql, [
            ':id' => $id,
            ':code_barres' => $data['code_barres'],
            ':nom' => $data['nom'],
            ':categorie' => $data['categorie'],
            ':prix' => $data['prix'],
            ':stock' => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image' => $data['image'] ?? ''
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM produits WHERE id = :id";
        return $this->db->query($sql, [':id' => $id]);
    }
}

