<?php

namespace App\Models;

class RestaurantMenuComposition
{
    private $db;
    private $table = 'restaurant_menu_compositions';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getByMenu($menuId)
    {
        return $this->db->fetchAll(
            "SELECT c.*, i.nom as item_nom, i.prix as item_prix\n             FROM {$this->table} c\n             LEFT JOIN restaurant_menu_items i ON c.menu_item_id = i.id\n             WHERE c.menu_id = ?\n             ORDER BY c.ordre ASC",
            [$menuId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (menu_id, menu_item_id, quantite, ordre)
                VALUES (:menu_id, :menu_item_id, :quantite, :ordre)";
        $this->db->execute($sql, [
            ':menu_id'     => $data['menu_id'],
            ':menu_item_id' => $data['menu_item_id'],
            ':quantite'    => $data['quantite'] ?? 1,
            ':ordre'       => $data['ordre'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['menu_item_id', 'quantite', 'ordre'];
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

    public function deleteByMenu($menuId)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE menu_id = ?", [$menuId]);
    }
}
