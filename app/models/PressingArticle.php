<?php

namespace App\Models;

class PressingArticle
{
    private $db;
    private $table = 'pressing_articles';
    private $validServices = [
        'lavage', 'repassage', 'lavage_repassage', 'nettoyage_sec',
        'detachage', 'desinfection', 'blanchiment', 'anti_odeur',
        'express', 'pliage', 'emballage_cintre'
    ];

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getValidServices()
    {
        return $this->validServices;
    }

    public function getByDepot($depotId)
    {
        return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE depot_id = ? ORDER BY id ASC", [$depotId]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (depot_id, nom_article, quantite, etat_initial, commentaire, service, service_id, prix_unitaire, prix_total)
                VALUES (:depot_id, :nom_article, :quantite, :etat_initial, :commentaire, :service, :service_id, :prix_unitaire, :prix_total)";
        $this->db->execute($sql, [
            ':depot_id'      => $data['depot_id'],
            ':nom_article'   => $data['nom_article'],
            ':quantite'      => $data['quantite'],
            ':etat_initial'  => $data['etat_initial'] ?? null,
            ':commentaire'   => $data['commentaire'] ?? null,
            ':service'       => $data['service'],
            ':service_id'    => $data['service_id'] ?? null,
            ':prix_unitaire' => $data['prix_unitaire'],
            ':prix_total'    => $data['prix_total'],
        ]);
        return $this->db->lastInsertId();
    }
}
