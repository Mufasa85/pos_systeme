<?php

namespace App\Models;

class RestaurantReservation
{
    private $db;
    private $table = 'restaurant_reservations';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null, $filters = [])
    {
        $sql = "SELECT r.*, t.numero as table_numero, t.nom as table_nom, c.nom as client_nom_complet, c.numero as client_telephone\n                FROM {$this->table} r\n                LEFT JOIN restaurant_tables t ON r.table_id = t.id\n                LEFT JOIN clients c ON r.client_id = c.id\n                WHERE 1=1";
        $params = [];

        if ($shopId) {
            $sql .= " AND r.shop_id = ?";
            $params[] = $shopId;
        }
        if (!empty($filters['date'])) {
            $sql .= " AND DATE(r.date_heure) = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND r.statut = ?";
            $params[] = $filters['statut'];
        }

        $sql .= " ORDER BY r.date_heure ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id, $shopId = null)
    {
        $sql = "SELECT r.*, t.numero as table_numero, t.nom as table_nom\n                FROM {$this->table} r\n                LEFT JOIN restaurant_tables t ON r.table_id = t.id\n                WHERE r.id = ?";
        $params = [$id];
        if ($shopId) {
            $sql .= " AND r.shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (shop_id, table_id, client_nom, client_telephone, client_id, date_heure, nb_personnes, statut, commentaire, created_by)
                VALUES (:shop_id, :table_id, :client_nom, :client_telephone, :client_id, :date_heure, :nb_personnes, :statut, :commentaire, :created_by)";
        $this->db->execute($sql, [
            ':shop_id'         => $data['shop_id'],
            ':table_id'        => $data['table_id'] ?? null,
            ':client_nom'      => $data['client_nom'] ?? null,
            ':client_telephone' => $data['client_telephone'] ?? null,
            ':client_id'       => $data['client_id'] ?? null,
            ':date_heure'      => $data['date_heure'],
            ':nb_personnes'    => $data['nb_personnes'] ?? 1,
            ':statut'          => $data['statut'] ?? 'confirmee',
            ':commentaire'     => $data['commentaire'] ?? null,
            ':created_by'      => $data['created_by'],
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['table_id', 'client_nom', 'client_telephone', 'client_id', 'date_heure', 'nb_personnes', 'statut', 'commentaire'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id, $shopId = null)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $params = [$id];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->execute($sql, $params);
    }

    public function getByDate($shopId, $date)
    {
        return $this->all($shopId, ['date' => $date]);
    }
}
