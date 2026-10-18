<?php

namespace App\Models;

/**
 * Gestion des tâches housekeeping (`hotel_housekeeping`).
 *
 * Trois types de tâches :
 *   - nettoyage   : créée automatiquement au check-out d'un séjour
 *   - inspection  : contrôle qualité post-nettoyage
 *   - maintenance : signalée par la gouvernante ou le manager
 *
 * Quatre statuts possibles :
 *   - a_faire  : ouverte, en attente de prise en charge
 *   - en_cours : une gouvernante l'a commencée (ou réclamée)
 *   - fait     : terminée → libère la chambre (etat = 'disponible')
 *   - bloque   : un problème a été signalé → attend le manager
 *
 * Règle métier importante :
 *   Quand une tâche passe à 'fait', la chambre passe à 'disponible'
 *   (sauf si elle est encore occupée par un séjour en cours, ce qui ne
 *   devrait pas arriver car les tâches ménage sont créées au check-out).
 */
class HotelHousekeeping
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Liste des tâches visibles par la gouvernante sur /hotel/etages.
     *
     * Règle de visibilité (Sprint 3) :
     *   - tâches qui lui sont assignées (tous statuts)
     *   - tâches non assignées ET de type 'nettoyage' ou 'inspection'
     *     → elle peut les "réclamer"
     *   - tâches bloquées (type maintenance) toujours visibles,
     *     même déjà assignées à quelqu'un d'autre
     *
     * Si une chambre est en `etat = 'a_nettoyer'` mais n'a pas (encore)
     * de tâche housekeeping active (cas legacy, ou tâche perdue), on
     * crée une ligne "virtuelle" type=nettoyage, statut=a_faire, non
     * assignée — comme ça la gouvernante la voit et peut la faire.
     * C'est essentiel pour rester cohérent avec la grille Réception
     * (qui se base sur hotel_chambres.etat, pas sur la table housekeeping).
     *
     * Tri : a_faire d'abord, puis en_cours, puis bloquées, puis faites du jour.
     */
    public function tachesPourGouvernante(int $userId): array
    {
        $sql = "
            SELECT
                h.id,
                h.chambre_id,
                c.numero        AS chambre_numero,
                c.type          AS chambre_type,
                h.type          AS type_tache,
                h.statut,
                h.assigne_a,
                h.date_creation,
                h.date_fait,
                h.notes
            FROM hotel_housekeeping h
            INNER JOIN hotel_chambres c ON c.id = h.chambre_id
            WHERE
                h.assigne_a = :uid
                OR (h.assigne_a IS NULL AND h.type IN ('nettoyage','inspection'))
                OR h.statut = 'bloque'
            UNION ALL
            -- Chambres 'a_nettoyer' SANS tâche active (sécurise les cas legacy / désynchro)
            SELECT
                NULL            AS id,
                c.id            AS chambre_id,
                c.numero        AS chambre_numero,
                c.type          AS chambre_type,
                'nettoyage'     AS type_tache,
                'a_faire'       AS statut,
                NULL            AS assigne_a,
                NULL            AS date_creation,
                NULL            AS date_fait,
                NULL            AS notes
            FROM hotel_chambres c
            WHERE c.etat = 'a_nettoyer'
              AND NOT EXISTS (
                SELECT 1 FROM hotel_housekeeping h2
                WHERE h2.chambre_id = c.id
                  AND h2.statut IN ('a_faire','en_cours')
              )
            ORDER BY
                FIELD(statut, 'a_faire', 'en_cours', 'bloque', 'fait'),
                chambre_numero ASC
        ";
        return $this->db->fetchAll($sql, [':uid' => $userId]);
    }

    /**
     * Trouve une tâche par son id, retourne null si introuvable.
     */
    public function findById(int $id)
    {
        return $this->db->fetch('SELECT * FROM hotel_housekeeping WHERE id = :id', [':id' => $id]);
    }

    /**
     * La gouvernante "réclame" une tâche non assignée :
     * on lui met assigne_a = uid et statut = en_cours.
     * Refuse si la tâche est déjà assignée à quelqu'un d'autre
     * ou si elle est déjà terminée.
     */
    public function reclamer(int $taskId, int $userId): bool
    {
        $t = $this->findById($taskId);
        if (!$t) return false;
        if ($t['statut'] === 'fait') return false;
        if (!is_null($t['assigne_a']) && (int)$t['assigne_a'] !== $userId) return false;

        $this->db->query(
            "UPDATE hotel_housekeeping
             SET assigne_a = :uid,
                 statut = 'en_cours'
             WHERE id = :id",
            [':uid' => $userId, ':id' => $taskId]
        );
        return true;
    }

    /**
     * Variante "réclamer" pour une chambre orpheline (en a_nettoyer, sans tâche).
     * Crée directement une tâche en_cours assignée à la gouvernante, sans
     * toucher à l'état de la chambre. Refuse si la chambre n'est pas éligible.
     */
    public function reclamerOrphelin(int $chambreId, int $userId): bool
    {
        $chambre = $this->db->fetch(
            "SELECT id FROM hotel_chambres
             WHERE id = :id AND etat = 'a_nettoyer'",
            [':id' => $chambreId]
        );
        if (!$chambre) return false;

        // Ne pas créer de doublon si une tâche active existe déjà entre-temps
        $existante = $this->db->fetch(
            "SELECT id FROM hotel_housekeeping
             WHERE chambre_id = :cid AND statut IN ('a_faire','en_cours')
             LIMIT 1",
            [':cid' => $chambreId]
        );
        if ($existante) {
            // Tenter de lier la tâche existante à la gouvernante
            $this->db->query(
                "UPDATE hotel_housekeeping
                 SET assigne_a = :uid, statut = 'en_cours'
                 WHERE id = :id",
                [':uid' => $userId, ':id' => $existante['id']]
            );
            return true;
        }

        $this->db->query(
            "INSERT INTO hotel_housekeeping (chambre_id, type, statut, assigne_a)
             VALUES (:cid, 'nettoyage', 'en_cours', :uid)",
            [':cid' => $chambreId, ':uid' => $userId]
        );
        return true;
    }

    /**
     * Marque une tâche comme terminée :
     *   - statut = 'fait', date_fait = NOW()
     *   - la chambre repasse à 'disponible' (sauf 'hors_service')
     *
     * Règle : seul l'assigné peut la fermer. Si la tâche n'a personne,
     * on l'assigne automatiquement à l'appelant (cas d'usage : la
     * gouvernante commence sans réclamer formellement).
     */
    public function marquerFait(int $taskId, int $userId): array
    {
        $t = $this->findById($taskId);
        if (!$t) {
            return ['ok' => false, 'error' => 'Tâche introuvable'];
        }
        if ($t['statut'] === 'fait') {
            return ['ok' => false, 'error' => 'Tâche déjà terminée'];
        }
        // Si quelqu'un d'autre l'a et qu'elle n'est pas terminée → refus
        if (!is_null($t['assigne_a']) && (int)$t['assigne_a'] !== $userId) {
            return ['ok' => false, 'error' => 'Tâche assignée à une autre gouvernante'];
        }

        // 1) Fermer la tâche
        $this->db->query(
            "UPDATE hotel_housekeeping
             SET statut = 'fait',
                 date_fait = NOW(),
                 assigne_a = :uid
             WHERE id = :id",
            [':uid' => $userId, ':id' => $taskId]
        );

        // 2) Libérer la chambre (si elle n'est pas hors service)
        $this->db->query(
            "UPDATE hotel_chambres
             SET etat = 'disponible'
             WHERE id = :cid AND etat <> 'hors_service'",
            [':cid' => $t['chambre_id']]
        );

        return ['ok' => true];
    }

    /**
     * Variante pour les chambres "orphelines" : la chambre est en `a_nettoyer`
     * mais aucune tâche housekeeping active n'existe pour elle.
     *
     * On crée la tâche (assigne_a = uid, type = nettoyage) puis on la ferme
     * immédiatement, et on libère la chambre. Le tout dans une transaction
     * pour ne jamais laisser un état intermédiaire sale.
     *
     * Refuse si la chambre n'est pas en `a_nettoyer` (sécurité).
     */
    public function marquerFaitOrpheline(int $chambreId, int $userId): array
    {
        $chambre = $this->db->fetch('SELECT id, etat FROM hotel_chambres WHERE id = :id', [':id' => $chambreId]);
        if (!$chambre) {
            return ['ok' => false, 'error' => 'Chambre introuvable'];
        }
        if ($chambre['etat'] !== 'a_nettoyer') {
            return ['ok' => false, 'error' => "La chambre n'est pas en état 'à_nettoyer' (état actuel : {$chambre['etat']})"];
        }

        try {
            $this->db->beginTransaction();

            // 1) Créer et fermer la tâche
            $this->db->query(
                "INSERT INTO hotel_housekeeping (chambre_id, type, statut, assigne_a, date_fait)
                 VALUES (:cid, 'nettoyage', 'fait', :uid, NOW())",
                [':cid' => $chambreId, ':uid' => $userId]
            );

            // 2) Libérer la chambre (sauf si hors_service entre-temps)
            $this->db->query(
                "UPDATE hotel_chambres
                 SET etat = 'disponible'
                 WHERE id = :cid AND etat = 'a_nettoyer'",
                [':cid' => $chambreId]
            );

            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db) {
                try { $this->db->rollBack(); } catch (\Throwable $_) {}
            }
            error_log('[HotelHousekeeping::marquerFaitOrpheline] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Erreur technique, réessayez'];
        }
    }

    /**
     * Variante "signaler un problème" sur une chambre orpheline :
     * crée une tâche maintenance bloquée, n'altère pas l'état de la chambre
     * (elle reste a_nettoyer jusqu'à ce que le manager la libère).
     */
    public function signalerProblemeOrphelin(int $chambreId, int $userId, string $notes): array
    {
        $notes = trim($notes);
        if ($notes === '') {
            return ['ok' => false, 'error' => 'Le message est vide'];
        }
        $chambre = $this->db->fetch('SELECT id, etat FROM hotel_chambres WHERE id = :id', [':id' => $chambreId]);
        if (!$chambre) {
            return ['ok' => false, 'error' => 'Chambre introuvable'];
        }
        if ($chambre['etat'] !== 'a_nettoyer') {
            return ['ok' => false, 'error' => "La chambre n'est pas en état 'à_nettoyer'"];
        }

        $this->db->query(
            "INSERT INTO hotel_housekeeping (chambre_id, type, statut, assigne_a, notes)
             VALUES (:cid, 'maintenance', 'bloque', :uid, :notes)",
            [':cid' => $chambreId, ':uid' => $userId, ':notes' => $notes]
        );
        return ['ok' => true];
    }

    /**
     * La gouvernante signale un problème sur une tâche.
     *   - statut = 'bloque'
     *   - notes = le message texte
     *   - assigne_a = elle (si pas déjà quelqu'un)
     *
     * Utilisé pour les 3 types (nettoyage / inspection / maintenance).
     */
    public function signalerProbleme(int $taskId, int $userId, string $notes): array
    {
        $notes = trim($notes);
        if ($notes === '') {
            return ['ok' => false, 'error' => 'Le message est vide'];
        }

        $t = $this->findById($taskId);
        if (!$t) {
            return ['ok' => false, 'error' => 'Tâche introuvable'];
        }
        if ($t['statut'] === 'fait') {
            return ['ok' => false, 'error' => 'Tâche déjà terminée'];
        }

        // Si quelqu'un d'autre l'a → on n'écrase pas l'assignation
        $newAssigne = is_null($t['assigne_a']) ? $userId : $t['assigne_a'];

        $this->db->query(
            "UPDATE hotel_housekeeping
             SET statut = 'bloque',
                 notes = :notes,
                 assigne_a = :uid
             WHERE id = :id",
            [
                ':notes' => $notes,
                ':uid'   => $newAssigne,
                ':id'    => $taskId,
            ]
        );

        return ['ok' => true];
    }

    /**
     * Petites stats affichées en haut de la page gouvernante :
     * compteurs par état, pour la journée.
     */
    public function statsDuJour(): array
    {
        $row = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN statut = 'a_faire' THEN 1 ELSE 0 END) AS a_faire,
                SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN statut = 'bloque' THEN 1 ELSE 0 END) AS bloque,
                SUM(CASE WHEN statut = 'fait' AND DATE(date_fait) = CURDATE() THEN 1 ELSE 0 END) AS faites_jour
             FROM hotel_housekeeping
             WHERE DATE(date_creation) = CURDATE()
                OR DATE(date_fait) = CURDATE()"
        );
        return [
            'a_faire'     => (int)($row['a_faire'] ?? 0),
            'en_cours'    => (int)($row['en_cours'] ?? 0),
            'bloque'      => (int)($row['bloque'] ?? 0),
            'faites_jour' => (int)($row['faites_jour'] ?? 0),
        ];
    }
}