<?php

namespace App\Models;

/**
 * Gestion des guests (clients ayant séjourné à l'hôtel).
 * Réutilise les infos de tel/pièce d'identité pour retrouver un guest existant.
 */
class HotelGuest
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Recherche par mot-clé (nom, prénom, téléphone, pièce d'identité).
     * Retourne max 20 résultats.
     */
    public function search(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        return $this->db->fetchAll(
            "SELECT id, nom, prenom, telephone, piece_identite, nationalite
             FROM hotel_guests
             WHERE nom LIKE :k OR prenom LIKE :k OR telephone LIKE :k OR piece_identite LIKE :k
             ORDER BY nom ASC, prenom ASC
             LIMIT 20",
            [':k' => $like]
        );
    }

    public function findById(int $id)
    {
        return $this->db->fetch('SELECT * FROM hotel_guests WHERE id = :id', [':id' => $id]);
    }

    /**
     * Création d'un guest. Renvoie l'id créé.
     */
    public function create(string $nom, ?string $prenom, ?string $telephone, ?string $pieceIdentite, ?string $nationalite): int
    {
        $this->db->query(
            'INSERT INTO hotel_guests (nom, prenom, telephone, piece_identite, nationalite)
             VALUES (:nom, :prenom, :tel, :piece, :nat)',
            [
                ':nom'   => $nom,
                ':prenom'=> $prenom,
                ':tel'   => $telephone,
                ':piece' => $pieceIdentite,
                ':nat'   => $nationalite,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Recherche de doublon probable (même nom + téléphone OU même pièce d'identité).
     * Pour éviter de créer 2 guests pour la même personne.
     */
    public function findPossibleDuplicate(string $nom, ?string $telephone, ?string $pieceIdentite): ?array
    {
        if ($pieceIdentite) {
            $row = $this->db->fetch('SELECT * FROM hotel_guests WHERE piece_identite = :p LIMIT 1', [':p' => $pieceIdentite]);
            if ($row) return $row;
        }
        if ($telephone) {
            $row = $this->db->fetch(
                'SELECT * FROM hotel_guests WHERE nom = :n AND telephone = :t LIMIT 1',
                [':n' => $nom, ':t' => $telephone]
            );
            if ($row) return $row;
        }
        return null;
    }
}
