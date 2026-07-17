<?php

namespace App\Models;

class OtpCode
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function generate($userId, $type = 'login', $channel = 'email', $expiresMinutes = 5)
    {
        // Invalider les anciens codes non utilisés pour cet utilisateur
        $this->invalidateAll($userId, $type);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresMinutes = (int)$expiresMinutes;

        // Utiliser NOW() de MySQL pour éviter un décalage de timezone PHP vs MySQL
        $sql = "INSERT INTO otp_codes (user_id, code, type, channel, expires_at)
                VALUES (:user_id, :code, :type, :channel, DATE_ADD(NOW(), INTERVAL {$expiresMinutes} MINUTE))";
        $this->db->query($sql, [
            ':user_id'  => $userId,
            ':code'     => $code,
            ':type'     => $type,
            ':channel'  => $channel
        ]);

        return $code;
    }

    public function verify($userId, $code, $type = 'login')
    {
        $sql = "SELECT * FROM otp_codes 
                WHERE user_id = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        $otp = $this->db->fetch($sql, [$userId, $code, $type]);

        if ($otp) {
            // Marquer comme utilisé
            $this->db->execute("UPDATE otp_codes SET used = 1 WHERE id = ?", [$otp['id']]);
            return true;
        }

        return false;
    }

    public function invalidateAll($userId, $type = 'login')
    {
        $sql = "UPDATE otp_codes SET used = 1 WHERE user_id = ? AND type = ? AND used = 0";
        return $this->db->execute($sql, [$userId, $type]);
    }

    public function countRecentAttempts($userId, $type = 'login', $minutes = 15)
    {
        $sql = "SELECT COUNT(*) as attempts FROM otp_codes 
                WHERE user_id = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $result = $this->db->fetch($sql, [$userId, $type, $minutes]);
        return $result['attempts'] ?? 0;
    }

    public function cleanExpired()
    {
        return $this->db->execute("DELETE FROM otp_codes WHERE expires_at < NOW() AND used = 1");
    }
}
