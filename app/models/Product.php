<?php

namespace App\Models;

class Product
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll($shopId = null)
    {
        $where = '';
        $params = [];
        if ($shopId) {
            $where = 'WHERE p.shop_id = ?';
            $params[] = $shopId;
        }
        return $this->db->fetchAll("SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name,
                COALESCE(SUM(pb.stock), 0) AS total_stock,
                MIN(CASE WHEN pb.date_expiration IS NOT NULL AND pb.stock > 0 AND pb.date_expiration >= CURDATE() THEN pb.date_expiration END) AS nearest_expiration_date
            FROM produits p 
            INNER JOIN categories c ON p.category_id = c.id 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            LEFT JOIN shops s ON p.shop_id = s.id 
            LEFT JOIN product_batches pb ON pb.product_id = p.id
            $where
            GROUP BY p.id
            ORDER BY p.nom ASC", $params);
    }

    public function findByBarcode($barcode)
    {
        $product = $this->db->fetch("SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette 
            FROM produits p 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            WHERE p.code_barres = :code_barres", [':code_barres' => $barcode]);
        if ($product) {
            $product = $this->enrichWithBatchInfo($product);
        }
        return $product;
    }

    public function findById($id)
    {
        $product = $this->db->fetch("SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette 
            FROM produits p 
            LEFT JOIN taxes t ON p.taxe_id = t.id 
            WHERE p.id = :id", [':id' => $id]);
        if ($product) {
            $product = $this->enrichWithBatchInfo($product);
        }
        return $product;
    }

    private function enrichWithBatchInfo($product)
    {
        $batchModel = new ProductBatch();
        $product['total_stock'] = $batchModel->getTotalStock($product['id']);
        $product['available_stock'] = $batchModel->getAvailableStock($product['id']);
        $product['nearest_expiration_date'] = $batchModel->getNearestExpirationDate($product['id']);
        $product['batches'] = $batchModel->findByProductId($product['id']);
        return $product;
    }

    public function getStock($id)
    {
        $batchModel = new ProductBatch();
        return $batchModel->getAvailableStock($id);
    }

    public function hasEnoughStock($id, $quantity)
    {
        $batchModel = new ProductBatch();
        return $batchModel->hasEnoughStock($id, $quantity);
    }

    public function create($data)
    {
        $sql = "INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type, prod_service, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value, date_expiration)
                VALUES (:code_barres, :nom, :category_id, :shop_id, :prix, :stock, :stock_minimum, :image, :taxe_id, :product_type, :prod_service, :remise_type, :remise_value, :taxe_specifique_type, :taxe_specifique_value, :date_expiration)";
        $this->db->query($sql, [
            ':code_barres'   => $data['code_barres'],
            ':nom'           => $data['nom'],
            ':category_id'   => $data['category_id'],
            ':shop_id'       => $data['shop_id'] ?? null,
            ':prix'          => $data['prix'],
            ':stock'         => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image'         => $data['image'] ?? '',
            ':taxe_id'       => $data['taxe_id'] ?? 1,
            ':product_type'  => $data['product_type'] ?? 'unite',
            ':prod_service'  => $data['prod_service'] ?? null,
            ':remise_type'   => $data['remise_type'] ?? '%',
            ':remise_value'  => $data['remise_value'] ?? 0,
            ':taxe_specifique_type' => $data['taxe_specifique_type'] ?? '%',
            ':taxe_specifique_value' => $data['taxe_specifique_value'] ?? 0,
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null
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
                shop_id = :shop_id,
                prix = :prix,
                stock_minimum = :stock_minimum,
                image = :image,
                taxe_id = :taxe_id,
                product_type = :product_type,
                prod_service = :prod_service,
                remise_type = :remise_type,
                remise_value = :remise_value,
                taxe_specifique_type = :taxe_specifique_type,
                taxe_specifique_value = :taxe_specifique_value,
                date_expiration = :date_expiration
                WHERE id = :id";
        $this->db->query($sql, [
            ':id' => $id,
            ':code_barres' => $data['code_barres'],
            ':nom' => $data['nom'],
            ':category_id' => $data['category_id'],
            ':shop_id' => $data['shop_id'] ?? null,
            ':prix' => $data['prix'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image' => $data['image'] ?? '',
            ':taxe_id' => $data['taxe_id'] ?? 1,
            ':product_type' => $data['product_type'] ?? 'unite',
            ':prod_service' => $data['prod_service'] ?? null,
            ':remise_type' => $data['remise_type'] ?? '%',
            ':remise_value' => $data['remise_value'] ?? 0,
            ':taxe_specifique_type' => $data['taxe_specifique_type'] ?? '%',
            ':taxe_specifique_value' => $data['taxe_specifique_value'] ?? 0,
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null
        ]);
        $this->recalculateProductStock($id);
        return true;
    }

    private function recalculateProductStock($id)
    {
        $batchModel = new ProductBatch();
        $total = $batchModel->getTotalStock($id);
        $this->db->query("UPDATE produits SET stock = :stock WHERE id = :id", [':stock' => $total, ':id' => $id]);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM produits WHERE id = :id";
        return $this->db->query($sql, [':id' => $id]);
    }
}
