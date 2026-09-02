<?php

namespace App\Models;

class ProductBatch
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($data)
    {
        $sql = 'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
                VALUES (:product_id, :batch_number, :stock, :date_expiration, :date_reception)';
        $this->db->query($sql, [
            ':product_id' => $data['product_id'],
            ':batch_number' => $data['batch_number'] ?? null,
            ':stock' => (float)$data['stock'],
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null,
            ':date_reception' => !empty($data['date_reception']) ? $data['date_reception'] : date('Y-m-d'),
        ]);
        $batchId = $this->db->getConnection()->lastInsertId();
        $this->recalculateProductStock($data['product_id']);
        return $batchId;
    }

    public function findByProductId($productId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_batches WHERE product_id = ? ORDER BY date_expiration ASC, date_reception ASC, id ASC',
            [$productId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM product_batches WHERE id = ?', [$id]);
    }

    public function update($id, $data)
    {
        $batch = $this->findById($id);
        if (!$batch) {
            return false;
        }

        $sql = 'UPDATE product_batches SET
                    batch_number = :batch_number,
                    stock = :stock,
                    date_expiration = :date_expiration,
                    date_reception = :date_reception
                WHERE id = :id';
        $this->db->query($sql, [
            ':id' => $id,
            ':batch_number' => $data['batch_number'] ?? $batch['batch_number'],
            ':stock' => isset($data['stock']) ? (float)$data['stock'] : $batch['stock'],
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : $batch['date_expiration'],
            ':date_reception' => !empty($data['date_reception']) ? $data['date_reception'] : $batch['date_reception'],
        ]);
        $this->recalculateProductStock($batch['product_id']);
        return true;
    }

    public function delete($id)
    {
        $batch = $this->findById($id);
        if (!$batch) {
            return false;
        }
        $this->db->query('DELETE FROM product_batches WHERE id = ?', [$id]);
        $this->recalculateProductStock($batch['product_id']);
        return true;
    }

    public function getTotalStock($productId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(stock), 0) as total FROM product_batches WHERE product_id = ?',
            [$productId]
        );
        return (float)($row['total'] ?? 0);
    }

    public function getAvailableStock($productId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(stock), 0) as total FROM product_batches WHERE product_id = ? AND (date_expiration IS NULL OR date_expiration >= CURDATE())',
            [$productId]
        );
        return (float)($row['total'] ?? 0);
    }

    public function hasEnoughStock($productId, $quantity)
    {
        return $this->getAvailableStock($productId) >= (float)$quantity;
    }

    /**
     * Deduit le stock d'un produit en suivant la regle FIFO sur les lots non perimes.
     * Doit etre appele a l'interieur d'une transaction pour garantir la coherence.
     *
     * @param int $productId
     * @param float $quantity
     * @return bool true si la deduction a reussi, false si stock insuffisant
     */
    public function deductStock($productId, $quantity)
    {
        $quantity = (float)$quantity;
        if ($quantity <= 0) {
            return true;
        }

        if (!$this->hasEnoughStock($productId, $quantity)) {
            return false;
        }

        $batches = $this->getAvailableBatches($productId);
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $batchStock = (float)$batch['stock'];
            $deduct = min($batchStock, $remaining);
            $newStock = $batchStock - $deduct;

            $this->db->query(
                'UPDATE product_batches SET stock = :stock WHERE id = :id',
                [':stock' => $newStock, ':id' => $batch['id']]
            );

            $remaining -= $deduct;
        }

        $this->recalculateProductStock($productId);
        return true;
    }

    /**
     * Restaure du stock lors d'une annulation de vente.
     * Le stock est ajoute au lot non perime le plus ancien, ou cree un nouveau lot si aucun lot valide n'existe.
     */
    public function restoreStock($productId, $quantity)
    {
        $quantity = (float)$quantity;
        if ($quantity <= 0) {
            return true;
        }

        $batches = $this->getAvailableBatches($productId);
        if (!empty($batches)) {
            $oldest = $batches[0];
            $newStock = (float)$oldest['stock'] + $quantity;
            $this->db->query(
                'UPDATE product_batches SET stock = :stock WHERE id = :id',
                [':stock' => $newStock, ':id' => $oldest['id']]
            );
        } else {
            $this->create([
                'product_id' => $productId,
                'batch_number' => 'RESTORE-' . date('YmdHis'),
                'stock' => $quantity,
                'date_expiration' => null,
                'date_reception' => date('Y-m-d'),
            ]);
            return true;
        }

        $this->recalculateProductStock($productId);
        return true;
    }

    public function getAvailableBatches($productId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_batches
             WHERE product_id = ? AND stock > 0 AND (date_expiration IS NULL OR date_expiration >= CURDATE())
             ORDER BY CASE WHEN date_expiration IS NULL THEN 1 ELSE 0 END ASC, date_expiration ASC, date_reception ASC, id ASC',
            [$productId]
        );
    }

    public function getExpiringSoon($days = 7, $shopId = null)
    {
        $where = 'WHERE b.date_expiration IS NOT NULL AND b.date_expiration >= CURDATE() AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL :days DAY) AND b.stock > 0';
        $params = [':days' => (int)$days];

        if ($shopId) {
            $where .= ' AND p.shop_id = :shop_id';
            $params[':shop_id'] = $shopId;
        }

        $sql = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where
                ORDER BY b.date_expiration ASC, p.nom ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getExpired($shopId = null)
    {
        $where = 'WHERE b.date_expiration IS NOT NULL AND b.date_expiration < CURDATE() AND b.stock > 0';
        $params = [];

        if ($shopId) {
            $where .= ' AND p.shop_id = :shop_id';
            $params[':shop_id'] = $shopId;
        }

        $sql = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where
                ORDER BY b.date_expiration ASC, p.nom ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getNearestExpirationDate($productId)
    {
        $row = $this->db->fetch(
            'SELECT MIN(date_expiration) as nearest FROM product_batches
             WHERE product_id = ? AND stock > 0 AND date_expiration >= CURDATE()',
            [$productId]
        );
        return $row['nearest'] ?? null;
    }

    private function recalculateProductStock($productId)
    {
        $total = $this->getTotalStock($productId);
        $this->db->query(
            'UPDATE produits SET stock = :stock WHERE id = :id',
            [':stock' => $total, ':id' => $productId]
        );
    }
}
