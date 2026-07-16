<?php

namespace App\Models;

class PasswordReset
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function generate($userId, $channel = 'email', $expiresMinutes = 15)
    {
        // Invalider les anciens tokens
        $this->invalidateAll($userId);

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresMinutes} minutes"));

        $sql = "INSERT INTO password_resets (user_id, token, channel, expires_at)
                VALUES (:user_id, :token, :channel, :expires_at)";
        $this->db->query($sql, [
            ':user_id'    => $userId,
            ':token'      => $token,
            ':channel'    => $channel,
            ':expires_at' => $expiresAt
        ]);

        return $token;
    }

    public function verify($token)
    {
        $sql = "SELECT * FROM password_resets 
                WHERE token = ? AND used = 0 AND expires_at > NOW()
                LIMIT 1";
        return $this->db->fetch($sql, [$token]);
    }

    public function markUsed($token)
    {
        return $this->db->execute("UPDATE password_resets SET used = 1 WHERE token = ?", [$token]);
    }

    public function invalidateAll($userId)
    {
        return $this->db->execute("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0", [$userId]);
    }

    public function cleanExpired()
    {
        return $this->db->execute("DELETE FROM password_resets WHERE expires_at < NOW() AND used = 1");
    }
}
