<?php

namespace App\Models;

class Shop
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll('SELECT * FROM shops ORDER BY nom ASC');
    }

    public function getActive()
    {
        return $this->db->fetchAll('SELECT * FROM shops WHERE actif = 1 ORDER BY nom ASC');
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE id = ?', [$id]);
    }

    public function findByCode($code)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE code = ?', [$code]);
    }

    public function findByIsf($isf)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE isf = ?', [$isf]);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO shops (nom, code, adresse, telephone, email, ice, rccm, isf, pdv, nid, token, port, service_type_id, actif)
                VALUES (:nom, :code, :adresse, :telephone, :email, :ice, :rccm, :isf, :pdv, :nid, :token, :port, :service_type_id, :actif)';
        $this->db->query($sql, [
            ':nom'             => $data['nom'],
            ':code'            => $data['code'],
            ':adresse'         => $data['adresse'] ?? null,
            ':telephone'       => $data['telephone'] ?? null,
            ':email'           => $data['email'] ?? null,
            ':ice'             => $data['ice'] ?? null,
            ':rccm'            => $data['rccm'] ?? null,
            ':isf'             => $data['isf'] ?? null,
            ':pdv'             => $data['pdv'] ?? null,
            ':nid'             => $data['nid'] ?? null,
            ':token'           => $data['token'] ?? null,
            ':port'            => $data['port'] ?? null,
            ':service_type_id' => $data['service_type_id'] ?? null,
            ':actif'           => $data['actif'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['nom', 'code', 'adresse', 'telephone', 'email', 'ice', 'rccm', 'isf', 'pdv', 'nid', 'token', 'port', 'service_type_id', 'actif'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE shops SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM shops WHERE id = ?', [$id]);
    }

    public function getStats($shopId)
    {
        $params = $shopId ? [$shopId] : [];
        $where = $shopId ? 'WHERE shop_id = ?' : '';

        $products = $this->db->fetch("SELECT COUNT(*) as total FROM produits $where", $params);
        $users = $this->db->fetch("SELECT COUNT(*) as total FROM utilisateurs $where", $params);
        $sales = $this->db->fetch("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as revenue FROM ventes $where", $params);

        return [
            'products' => $products['total'] ?? 0,
            'users'    => $users['total'] ?? 0,
            'sales'    => $sales['total'] ?? 0,
            'revenue'  => $sales['revenue'] ?? 0,
        ];
    }
}
