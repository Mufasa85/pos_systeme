<?php

namespace App\Models;

class RestaurantMenuItemOption
{
    private $db;
    private $table = 'restaurant_menu_item_options';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($menuItemId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE menu_item_id = ? ORDER BY actif DESC, id ASC",
            [$menuItemId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (menu_item_id, nom, prix_supp, obligatoire, actif)
                VALUES (:menu_item_id, :nom, :prix_supp, :obligatoire, :actif)";
        $this->db->execute($sql, [
            ':menu_item_id' => $data['menu_item_id'],
            ':nom'          => $data['nom'],
            ':prix_supp'    => $data['prix_supp'] ?? 0,
            ':obligatoire'  => $data['obligatoire'] ?? 0,
            ':actif'        => $data['actif'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['nom', 'prix_supp', 'obligatoire', 'actif'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
