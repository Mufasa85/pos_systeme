<?php

namespace App\Models;

/**
 * Gestion des guests (clients ayant séjourné à l'hôtel).
 *
 *  - Recherche texte (utilisée par les formulaires Réservations)
 *  - Pagination + stats par guest (utilisée par /hotel/guests)
 *  - Création / mise à jour
 *  - Détection de doublon par téléphone ou pièce d'identité
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
     * Retourne max 20 résultats — sert à l'autocomplete dans le formulaire de réservation.
     */
    public function search(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        // PDO natif (EMULATE_PREPARES=false) : un placeholder nommé ne peut
        // pas être réutilisé — on passe la même valeur sous N clés distinctes.
        return $this->db->fetchAll(
            "SELECT id, nom, prenom, telephone, piece_identite, nationalite
             FROM hotel_guests
             WHERE nom LIKE :k1 OR prenom LIKE :k2 OR telephone LIKE :k3 OR piece_identite LIKE :k4
             ORDER BY nom ASC, prenom ASC
             LIMIT 20",
            [':k1' => $like, ':k2' => $like, ':k3' => $like, ':k4' => $like]
        );
    }

    public function findById(int $id)
    {
        return $this->db->fetch(
            'SELECT g.*, c.code_client AS client_code, c.nom_client AS client_nom, c.numero AS client_numero
             FROM hotel_guests g
             LEFT JOIN clients c ON c.id = g.client_id
             WHERE g.id = :id',
            [':id' => $id]
        );
    }

    /**
     * Création d'un guest. Renvoie l'id créé.
     * `$clientId` (optionnel) : rattache le guest à un client POS existant
     * pour permettre aux factures POS/recharges d'être liées au même client.
     */
    public function create(
        string $nom,
        ?string $prenom,
        ?string $telephone,
        ?string $pieceIdentite,
        ?string $nationalite,
        ?int $clientId = null
    ): int {
        $this->db->query(
            'INSERT INTO hotel_guests (nom, prenom, telephone, piece_identite, nationalite, client_id)
             VALUES (:nom, :prenom, :tel, :piece, :nat, :cid)',
            [
                ':nom'   => $nom,
                ':prenom'=> $prenom,
                ':tel'   => $telephone,
                ':piece' => $pieceIdentite,
                ':nat'   => $nationalite,
                ':cid'   => $clientId,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Recherche d'un guest déjà rattaché à un client POS donné.
     * Sert à éviter de créer deux guests pour le même client.
     * Renvoie null si aucun.
     */
    public function findByClientId(int $clientId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM hotel_guests WHERE client_id = :cid ORDER BY id ASC LIMIT 1',
            [':cid' => $clientId]
        );
        return $row ?: null;
    }

    /**
     * Détection de doublon probable : même pièce d'identité ou (nom + téléphone).
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

    /**
     * Mise à jour des infos d'un guest (manager uniquement via controller).
     */
    public function updateGuest(int $id, array $data): bool
    {
        // Champs modifiables (pas d'id ni created_at). client_id se gère via linkClient().
        $fields = [];
        $params = [':id' => $id];
        foreach (['nom', 'prenom', 'telephone', 'piece_identite', 'nationalite'] as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "$k = :$k";
                $params[":$k"] = $data[$k];
            }
        }
        if (empty($fields)) return false;
        $sql = 'UPDATE hotel_guests SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->query($sql, $params);
        return true;
    }

    /**
     * Lie (ou délie) un guest à un client POS.
     * `$clientId === null` → on retire le lien (soft).
     * Retourne true si le guest existe (sans se soucier du nombre
     * exact de colonnes modifiées — Database::execute renvoie toujours true).
     */
    public function linkClient(int $guestId, ?int $clientId): bool
    {
        $this->db->execute(
            'UPDATE hotel_guests SET client_id = :cid WHERE id = :id',
            [':cid' => $clientId, ':id' => $guestId]
        );
        return true;
    }

    /**
     * Liste paginée avec stats (nb séjours + dernier séjour).
     */
    public function listWithStats(int $limit, int $offset, ?string $search = null): array
    {
        $where = '';
        $params = [];
        if ($search !== null && $search !== '') {
            $where = ' WHERE (g.nom LIKE :k1 OR g.prenom LIKE :k2 OR g.telephone LIKE :k3 OR g.piece_identite LIKE :k4
                          OR c.code_client LIKE :k5 OR c.nom_client LIKE :k6)';
            $params = [':k1' => '%' . $search . '%', ':k2' => '%' . $search . '%', ':k3' => '%' . $search . '%',
                       ':k4' => '%' . $search . '%', ':k5' => '%' . $search . '%', ':k6' => '%' . $search . '%'];
        }
        $sql = "SELECT g.id, g.nom, g.prenom, g.telephone, g.piece_identite, g.nationalite,
                       g.client_id, g.created_at,
                       c.code_client AS client_code, c.nom_client AS client_nom,
                       (SELECT COUNT(*) FROM hotel_sejours s WHERE s.guest_id = g.id) AS nb_sejours,
                       (SELECT MAX(s.date_depart_prevue) FROM hotel_sejours s
                          WHERE s.guest_id = g.id AND s.statut IN ('termine','en_cours')) AS dernier_sejour
                FROM hotel_guests g
                LEFT JOIN clients c ON c.id = g.client_id" . $where . "
                ORDER BY g.nom ASC, g.prenom ASC
                LIMIT $limit OFFSET $offset";
        return $this->db->fetchAll($sql, $params);
    }

    public function countGuests(?string $search = null): int
    {
        $where = '';
        $params = [];
        if ($search !== null && $search !== '') {
            $where = ' WHERE (nom LIKE :k1 OR prenom LIKE :k2 OR telephone LIKE :k3 OR piece_identite LIKE :k4)';
            $params = [':k1' => '%' . $search . '%', ':k2' => '%' . $search . '%',
                       ':k3' => '%' . $search . '%', ':k4' => '%' . $search . '%'];
        }
        $row = $this->db->fetch('SELECT COUNT(*) AS c FROM hotel_guests' . $where, $params);
        return (int)($row['c'] ?? 0);
    }

    /**
     * Séjours d'un guest (avec chambre jointe) — pour la fiche détail.
     */
    public function sejoursPourGuest(int $guestId): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, c.numero AS chambre_numero, c.type AS chambre_type
             FROM hotel_sejours s
             INNER JOIN hotel_chambres c ON c.id = s.chambre_id
             WHERE s.guest_id = :gid
             ORDER BY s.date_arrivee DESC",
            [':gid' => $guestId]
        );
    }
}
