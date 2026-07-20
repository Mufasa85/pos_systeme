<?php

namespace App\Models;

class Notification
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($userId, $shopId, $type, $title, $message, $link = null)
    {
        $sql = "INSERT INTO notifications (user_id, shop_id, type, title, message, link)
                VALUES (:user_id, :shop_id, :type, :title, :message, :link)";
        $this->db->query($sql, [
            ':user_id' => $userId,
            ':shop_id' => $shopId,
            ':type'    => $type,
            ':title'   => $title,
            ':message' => $message,
            ':link'    => $link
        ]);
        return $this->db->lastInsertId();
    }

    public function getForUser($userId, $limit = 20, $offset = 0)
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$userId, (int)$limit, (int)$offset]);
    }

    public function getUnreadCount($userId)
    {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result['count'] ?? 0;
    }

    public function markAsRead($id, $userId)
    {
        return $this->db->execute(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    public function markAllAsRead($userId)
    {
        return $this->db->execute(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    public function notifyShopAdmins($shopId, $type, $title, $message, $link = null)
    {
        $sql = "SELECT id FROM utilisateurs WHERE shop_id = ? AND role IN ('admin','super_admin')";
        $admins = $this->db->fetchAll($sql, [$shopId]);
        foreach ($admins as $admin) {
            $this->create($admin['id'], $shopId, $type, $title, $message, $link);
        }
    }

    public function notifySuperAdmins($type, $title, $message, $link = null)
    {
        $sql = "SELECT id FROM utilisateurs WHERE role = 'super_admin'";
        $superAdmins = $this->db->fetchAll($sql);
        foreach ($superAdmins as $sa) {
            $this->create($sa['id'], null, $type, $title, $message, $link);
        }
    }

    public function delete($id, $userId)
    {
        return $this->db->execute(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
}
