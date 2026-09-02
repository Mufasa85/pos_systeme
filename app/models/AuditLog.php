<?php

namespace App\Models;

class AuditLog
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function log($userId, $shopId, $action, $entity, $entityId = null, $details = null)
    {
        $sql = 'INSERT INTO audit_logs (user_id, shop_id, action, entity, entity_id, details, ip_address)
                VALUES (:user_id, :shop_id, :action, :entity, :entity_id, :details, :ip_address)';
        return $this->db->query($sql, [
            ':user_id'   => $userId,
            ':shop_id'   => $shopId,
            ':action'    => $action,
            ':entity'    => $entity,
            ':entity_id' => $entityId,
            ':details'   => $details ? json_encode($details) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function getAll($shopId = null, $limit = 100, $offset = 0)
    {
        $where = '';
        $params = [];

        if ($shopId) {
            $where = 'WHERE a.shop_id = ?';
            $params[] = $shopId;
        }

        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $sql = "SELECT a.*, u.nom_complet as user_name 
                FROM audit_logs a
                LEFT JOIN utilisateurs u ON a.user_id = u.id
                $where
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, $params);
    }

    public function getByEntity($entity, $entityId)
    {
        $sql = 'SELECT a.*, u.nom_complet as user_name 
                FROM audit_logs a
                LEFT JOIN utilisateurs u ON a.user_id = u.id
                WHERE a.entity = ? AND a.entity_id = ?
                ORDER BY a.created_at DESC';
        return $this->db->fetchAll($sql, [$entity, $entityId]);
    }

    public function purgeOlderThan($months = 6)
    {
        $sql = 'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)';
        return $this->db->execute($sql, [$months]);
    }
}
