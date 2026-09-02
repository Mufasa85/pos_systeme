<?php

namespace App\Models;

class CompanyInfo
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function get()
    {
        return $this->db->fetch('SELECT * FROM company_info WHERE id = 1');
    }

    public function update($data)
    {
        $sql = 'UPDATE company_info SET 
                name = :name,
                address = :address,
                email = :email,
                pdv = :pdv,
                phone = :phone,
                ice = :ice,
                rccm = :rccm,
                isf = :isf,
                nid = :nid,
                token = :token,
                port = :port
                WHERE id = 1';

        return $this->db->execute($sql, [
            ':name' => $data['name'] ?? null,
            ':address' => $data['address'] ?? null,
            ':email' => $data['email'] ?? null,
            ':pdv' => $data['pdv'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':ice' => $data['ice'] ?? null,
            ':rccm' => $data['rccm'] ?? null,
            ':isf' => $data['isf'] ?? null,
            ':nid' => $data['nid'] ?? null,
            ':token' => $data['token'] ?? null,
            ':port' => $data['port'] ?? null,
        ]);
    }
}
