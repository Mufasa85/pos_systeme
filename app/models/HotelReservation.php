<?php

namespace App\Models;

/**
 * Gestion des réservations / séjours hôtel (`hotel_sejours`).
 *
 * Règles :
 *  - Une chambre ne peut avoir qu'un séjour actif (`reserve`, `en_cours`)
 *    sur une période donnée (pas de chevauchement).
 *  - Le check-in passe le séjour à `en_cours` et la chambre à `occupée`
 *    (le front-office mettra l'état côté chambres — ici on n'agit pas
 *    sur hotel_chambres.etat, séparé pour éviter couplage fort).
 *  - L'annulation passe le séjour à `annule`. La chambre reste inchangée.
 *  - Le check-out passe à `termine` + chambre à `a_nettoyer` (idem,
 *    l'état côté chambres sera ajusté par le page Réception / Chambres).
 */
class HotelReservation
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Liste des réservations filtrables par statut + plage de dates.
     * Filtre par défaut : séjours à venir et en cours (les plus utiles
     * à la réceptionniste).
     */
    public function list(?string $statut = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT s.*,
                       g.nom AS guest_nom, g.prenom AS guest_prenom, g.telephone AS guest_tel,
                       c.numero AS chambre_numero, c.type AS chambre_type, c.prix_base AS chambre_prix_base,
                       u.nom_complet AS user_nom
                FROM hotel_sejours s
                INNER JOIN hotel_guests g ON g.id = s.guest_id
                INNER JOIN hotel_chambres c ON c.id = s.chambre_id
                LEFT JOIN utilisateurs u ON u.id = s.user_id
                WHERE 1=1";
        $params = [];
        if ($statut) {
            $sql .= ' AND s.statut = :statut';
            $params[':statut'] = $statut;
        }
        if ($dateFrom) {
            $sql .= ' AND s.date_depart_prevue >= :df';
            $params[':df'] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND s.date_arrivee <= :dt';
            $params[':dt'] = $dateTo;
        }
        $sql .= ' ORDER BY s.date_arrivee DESC LIMIT 200';
        return $this->db->fetchAll($sql, $params);
    }

    public function findById(int $id)
    {
        return $this->db->fetch(
            "SELECT s.*, g.nom AS guest_nom, g.prenom AS guest_prenom, g.telephone AS guest_tel,
                    g.piece_identite AS guest_piece, g.nationalite AS guest_nat,
                    c.numero AS chambre_numero, c.type AS chambre_type
             FROM hotel_sejours s
             INNER JOIN hotel_guests g ON g.id = s.guest_id
             INNER JOIN hotel_chambres c ON c.id = s.chambre_id
             WHERE s.id = :id",
            [':id' => $id]
        );
    }

    /**
     * Détecte un conflit : un autre séjour actif (reserve ou en_cours)
     * qui chevauche la période [arrivee, depart] sur la même chambre.
     * Convention : date_arrivee = DATETIME (heure d'arrivée),
     *              date_depart_prevue = DATE (jour de départ).
     * Chevauchement = le séjour existant a une arrivée <= nouvelle départ
     *                 ET une départ_prevue >= nouvelle arrivée.
     */
    public function hasConflict(int $chambreId, string $dateArrivee, string $dateDepartPrevue, ?int $excludeId = null): ?array
    {
        $sql = "SELECT s.id, s.date_arrivee, s.date_depart_prevue, s.statut,
                       g.nom AS guest_nom, g.prenom AS guest_prenom
                FROM hotel_sejours s
                INNER JOIN hotel_guests g ON g.id = s.guest_id
                WHERE s.chambre_id = :cid
                  AND s.statut IN ('reserve','en_cours')
                  AND s.date_arrivee < :new_depart_dt
                  AND s.date_depart_prevue >= :new_arrivee_date";
        $params = [
            ':cid' => $chambreId,
            ':new_depart_dt' => $dateDepartPrevue . ' 00:00:00',
            ':new_arrivee_date' => date('Y-m-d', strtotime($dateArrivee)),
        ];
        if ($excludeId) {
            $sql .= ' AND s.id != :ex';
            $params[':ex'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $row = $this->db->fetch($sql, $params);
        return $row ?: null;
    }

    /**
     * Crée une réservation. Renvoie l'id.
     * Lève une RuntimeException en cas de conflit (la vue affichera
     * la chambre conflictuelle).
     */
    public function create(
        int $chambreId,
        int $guestId,
        string $dateArrivee,    // YYYY-MM-DD HH:MM:SS
        string $dateDepartPrevue, // YYYY-MM-DD
        float $prixParNuit,
        float $remisePct,
        ?string $notes,
        int $userId
    ): int {
        $conflict = $this->hasConflict($chambreId, $dateArrivee, $dateDepartPrevue);
        if ($conflict) {
            throw new \RuntimeException(
                "Conflit : chambre déjà réservée pour cette période (séjour #{$conflict['id']}, " .
                "du {$conflict['date_arrivee']} au {$conflict['date_depart_prevue']})"
            );
        }
        $this->db->query(
            "INSERT INTO hotel_sejours
             (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit, remise_pct, notes, user_id, version)
             VALUES (:cid, :gid, :arrivee, :depart, 'reserve', :prix, :remise, :notes, :uid, 1)",
            [
                ':cid'    => $chambreId,
                ':gid'    => $guestId,
                ':arrivee'=> $dateArrivee,
                ':depart' => $dateDepartPrevue,
                ':prix'   => $prixParNuit,
                ':remise' => $remisePct,
                ':notes'  => $notes,
                ':uid'    => $userId,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        // ... chambre_id / dates / prix peuvent changer
        foreach (['chambre_id','date_arrivee','date_depart_prevue','prix_par_nuit','remise_pct','notes'] as $k) {
            if (isset($data[$k])) {
                $fields[] = "$k = :$k";
                $params[":$k"] = $data[$k];
            }
        }
        if (empty($fields)) return false;
        // test de conflit si chambre/dates ont changé
        if (isset($data['chambre_id']) || isset($data['date_arrivee']) || isset($data['date_depart_prevue'])) {
            $cur = $this->findById($id);
            if (!$cur) return false;
            $ch = $data['chambre_id'] ?? $cur['chambre_id'];
            $ar = $data['date_arrivee']  ?? $cur['date_arrivee'];
            $dp = $data['date_depart_prevue'] ?? $cur['date_depart_prevue'];
            $conflict = $this->hasConflict($ch, $ar, $dp, $id);
            if ($conflict) {
                throw new \RuntimeException(
                    "Conflit : chambre déjà réservée pour cette période (séjour #{$conflict['id']})"
                );
            }
        }
        // verrou optimiste
        $fields[] = 'version = version + 1';
        $sql = 'UPDATE hotel_sejours SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->query($sql, $params);
        return true;
    }

    /**
     * Statut = annule. Ne supprime pas — on garde l'historique.
     */
    public function cancel(int $id): bool
    {
        $this->db->query(
            "UPDATE hotel_sejours SET statut = 'annule', version = version + 1 WHERE id = :id",
            [':id' => $id]
        );
        return true;
    }

    /**
     * Chambres disponibles sur la période = chambres dont :
     *   - etat IN ('disponible', 'a_nettoyer') (jamais hors_service)
     *   - pas de séjour actif qui chevauche la période
     */
    public function availableRooms(string $dateArrivee, string $dateDepartPrevue, ?int $excludeReservationId = null): array
    {
        $sql = "SELECT c.id, c.numero, c.type, c.prix_base, c.etat
                FROM hotel_chambres c
                WHERE c.etat != 'hors_service'
                  AND NOT EXISTS (
                    SELECT 1 FROM hotel_sejours s
                    WHERE s.chambre_id = c.id
                      AND s.statut IN ('reserve','en_cours')
                      AND s.date_arrivee < :new_depart_dt
                      AND s.date_depart_prevue >= :new_arrivee_date";
        $params = [
            ':new_depart_dt' => $dateDepartPrevue . ' 00:00:00',
            ':new_arrivee_date' => date('Y-m-d', strtotime($dateArrivee)),
        ];
        if ($excludeReservationId) {
            $sql .= ' AND s.id != :ex';
            $params[':ex'] = $excludeReservationId;
        }
        $sql .= ")\n                ORDER BY c.numero ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function allRooms(): array
    {
        return $this->db->fetchAll('SELECT id, numero, type, prix_base, etat FROM hotel_chambres ORDER BY numero ASC');
    }
}
