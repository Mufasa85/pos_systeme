<?php

namespace App\Models;

class PressingTarif
{
    private $db;
    private $table = 'pressing_tarifs';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null, $serviceId = null)
    {
        $where = [];
        $params = [];
        if ($shopId) {
            $where[] = 't.shop_id = ?';
            $params[] = $shopId;
        }
        if ($serviceId) {
            $where[] = 't.service_id = ?';
            $params[] = $serviceId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $this->db->fetchAll(
            "SELECT t.*, s.nom as service_nom
             FROM {$this->table} t
             LEFT JOIN pressing_services s ON t.service_id = s.id
             $whereSql
             ORDER BY s.nom, t.article_type ASC",
            $params
        );
    }

    public function findById($id, $shopId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $params = [$id];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function findByServiceAndType($serviceId, $articleType, $shopId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE service_id = ? AND article_type = ?";
        $params = [$serviceId, $articleType];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (shop_id, service_id, article_type, prix_unitaire)
                VALUES (:shop_id, :service_id, :article_type, :prix_unitaire)";
        $this->db->execute($sql, [
            ':shop_id'       => $data['shop_id'],
            ':service_id'    => $data['service_id'],
            ':article_type'  => $data['article_type'],
            ':prix_unitaire' => $data['prix_unitaire'],
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET service_id = :service_id, article_type = :article_type, prix_unitaire = :prix_unitaire
                WHERE id = :id";
        return $this->db->execute($sql, [
            ':id'            => $id,
            ':service_id'    => $data['service_id'],
            ':article_type'  => $data['article_type'],
            ':prix_unitaire' => $data['prix_unitaire'],
        ]);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
