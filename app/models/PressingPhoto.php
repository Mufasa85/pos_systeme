<?php

namespace App\Models;

class PressingPhoto
{
    private $db;
    private $table = 'pressing_photos';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getByDepot($depotId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE depot_id = ? ORDER BY created_at ASC",
            [$depotId]
        );
    }

    public function getByArticle($articleId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE article_id = ? ORDER BY created_at ASC",
            [$articleId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (depot_id, article_id, chemin, type)
                VALUES (:depot_id, :article_id, :chemin, :type)";
        $this->db->execute($sql, [
            ':depot_id'  => $data['depot_id'],
            ':article_id' => $data['article_id'] ?? null,
            ':chemin'    => $data['chemin'],
            ':type'      => $data['type'] ?? 'etat_initial',
        ]);
        return $this->db->lastInsertId();
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
