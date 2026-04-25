<?php

namespace App\Models;

class Settings
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function get($key)
    {
        $sql = "SELECT value FROM settings WHERE setting_key = ?";
        $result = $this->db->fetch($sql, [$key]);
        return $result ? $result['value'] : null;
    }

    public function getAll()
    {
        $sql = "SELECT setting_key, value FROM settings";
        $rows = $this->db->fetchAll($sql);
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['value'];
        }
        return $settings;
    }

    public function set($key, $value)
    {
        $sql = "INSERT INTO settings (setting_key, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        $this->db->query($sql, [$key, $value]);
    }

    public function setMultiple($data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}
