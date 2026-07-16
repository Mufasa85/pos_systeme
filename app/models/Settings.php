<?php

namespace App\Models;

class Settings
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function get($key, $shopId = null)
    {
        if ($shopId) {
            $sql = "SELECT value FROM settings WHERE setting_key = ? AND shop_id = ?";
            $result = $this->db->fetch($sql, [$key, $shopId]);
            if ($result) return $result['value'];
        }
        $sql = "SELECT value FROM settings WHERE setting_key = ? AND shop_id IS NULL";
        $result = $this->db->fetch($sql, [$key]);
        if ($result) return $result['value'];
        $sql = "SELECT value FROM settings WHERE setting_key = ? ORDER BY id LIMIT 1";
        $result = $this->db->fetch($sql, [$key]);
        return $result ? $result['value'] : null;
    }

    public function getAll($shopId = null)
    {
        if ($shopId) {
            $sql = "SELECT setting_key, value FROM settings WHERE shop_id = ? OR shop_id IS NULL";
            $rows = $this->db->fetchAll($sql, [$shopId]);
        } else {
            $sql = "SELECT setting_key, value FROM settings";
            $rows = $this->db->fetchAll($sql);
        }
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['value'];
        }
        return $settings;
    }

    public function set($key, $value, $shopId = null)
    {
        $sql = "INSERT INTO settings (setting_key, shop_id, value) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        $this->db->query($sql, [$key, $shopId, $value]);
    }

    public function setMultiple($data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}
