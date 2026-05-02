<?php

namespace App\Models;

class Product
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll("SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette 
            FROM produits p 
            INNER JOIN categories c ON p.category_id = c.id 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            ORDER BY p.nom ASC");
    }

    public function findByBarcode($barcode)
    {
        return $this->db->fetch("SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette 
            FROM produits p 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            WHERE p.code_barres = :code_barres", [':code_barres' => $barcode]);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette 
            FROM produits p 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            WHERE p.id = :id", [':id' => $id]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO produits (code_barres, nom, category_id, prix, stock, stock_minimum, image, taxe_id)
                VALUES (:code_barres, :nom, :category_id, :prix, :stock, :stock_minimum, :image, :taxe_id)";
        $this->db->query($sql, [
            ':code_barres'   => $data['code_barres'],
            ':nom'           => $data['nom'],
            ':category_id'   => $data['category_id'],
            ':prix'          => $data['prix'],
            ':stock'         => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image'         => $data['image'] ?? '',
            ':taxe_id'       => $data['taxe_id'] ?? 1
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function updateStock($id, $quantity)
    {
        $sql = "UPDATE produits SET stock = stock - :quantite WHERE id = :id";
        return $this->db->query($sql, [':quantite' => $quantity, ':id' => $id]);
    }

    public function update($id, $data)
    {
        $sql = "UPDATE produits SET 
                code_barres = :code_barres,
                nom = :nom, 
                category_id = :category_id,
                prix = :prix,
                stock = :stock,
                stock_minimum = :stock_minimum,
                image = :image,
                taxe_id = :taxe_id
                WHERE id = :id";
        return $this->db->query($sql, [
            ':id' => $id,
            ':code_barres' => $data['code_barres'],
            ':nom' => $data['nom'],
            ':category_id' => $data['category_id'],
            ':prix' => $data['prix'],
            ':stock' => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image' => $data['image'] ?? '',
            ':taxe_id' => $data['taxe_id'] ?? 1
        ]);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM produits WHERE id = :id";
        return $this->db->query($sql, [':id' => $id]);
    }
}
