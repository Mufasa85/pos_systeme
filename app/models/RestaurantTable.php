<?php

namespace App\Models;

class RestaurantTable
{
    private $db;
    private $table = 'restaurant_tables';
    private $validStates = ['libre', 'occupee', 'reservee', 'nettoyage'];

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getValidStates()
    {
        return $this->validStates;
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE shop_id = ? ORDER BY numero ASC", [$shopId]);
        }
        return $this->db->fetchAll("SELECT t.*, s.nom as shop_name FROM {$this->table} t LEFT JOIN shops s ON t.shop_id = s.id ORDER BY t.shop_id ASC, t.numero ASC");
    }

    public function findById($id, $shopId = null)
    {
        if ($shopId) {
            return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ? AND shop_id = ?", [$id, $shopId]);
        }
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function existsNumero($numero, $shopId, $excludeId = null)
    {
        if ($excludeId) {
            return $this->db->fetch(
                "SELECT id FROM {$this->table} WHERE numero = ? AND shop_id = ? AND id != ?",
                [$numero, $shopId, $excludeId]
            );
        }
        return $this->db->fetch(
            "SELECT id FROM {$this->table} WHERE numero = ? AND shop_id = ?",
            [$numero, $shopId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} (shop_id, numero, nom, capacite, etat)
                VALUES (:shop_id, :numero, :nom, :capacite, :etat)";
        $this->db->execute($sql, [
            ':shop_id'  => $data['shop_id'],
            ':numero'   => $data['numero'],
            ':nom'      => $data['nom'] ?? null,
            ':capacite' => $data['capacite'] ?? 4,
            ':etat'     => $data['etat'] ?? 'libre',
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['numero', 'nom', 'capacite', 'etat', 'qr_code', 'qr_token'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function updateState($id, $etat)
    {
        if (!in_array($etat, $this->validStates)) {
            return false;
        }
        return $this->db->execute(
            "UPDATE {$this->table} SET etat = ? WHERE id = ?",
            [$etat, $id]
        );
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
