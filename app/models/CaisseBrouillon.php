<?php

namespace App\Models;

/**
 * Brouillons de panier de la caisse.
 *
 * Un brouillon est un SNAPSHOT du panier (`posCart.items` côté JS) persisté
 * côté serveur pour survivre à un refresh, un crash navigateur, une coupure
 * réseau ou un changement de poste de travail.
 *
 * ⚠️ Règles invariantes (cf. docs/PLAN_BROUILLONS_CAISSE.md) :
 *   - Un brouillon ne DÉDUIT JAMAIS le stock. La déduction se fait à la
 *     facturation, dans `SaleController::create` (règle Q1).
 *   - Un brouillon ne NUMÉROTE JAMAIS une facture. Le numéro est demandé à
 *     la DGI au moment de la validation, comme pour une vente normale.
 *   - Un brouillon n'est PAS une vente : il vit dans sa propre table, pour
 *     ne jamais polluer les rapports DGI (A/X/Z) ni la clôture de caisse.
 *   - Le payload ne contient que des ARTICLES et des identifiants : ni NIF,
 *     ni adresse, ni coordonnées bancaires du client (cf. §4.7 du plan).
 *
 * @see \App\Controllers\CaisseBrouillonController
 */
class CaisseBrouillon
{
    /** Seuil de purge (Q3) : un brouillon 'ouvert' de plus de X jours est supprimé. */
    public const PURGE_JOURS = 30;

    /** Statuts possibles d'un brouillon. */
    public const STATUS_OUVERT   = 'ouvert';
    public const STATUS_CONVERTI = 'converti';

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Crée un brouillon.
     *
     * @param array $data Voir CaisseBrouillonController::store() pour le détail.
     * @return int ID du brouillon créé.
     */
    public function create($data)
    {
        $sql = 'INSERT INTO caisse_brouillons
                    (shop_id, user_id, libelle, client_id, sejour_id, invoice_type,
                     payload, total, nb_articles, status, vente_id)
                VALUES
                    (:shop_id, :user_id, :libelle, :client_id, :sejour_id, :invoice_type,
                     :payload, :total, :nb_articles, :status, :vente_id)';

        $this->db->query($sql, [
            ':shop_id'      => $data['shop_id'],
            ':user_id'      => $data['user_id'],
            ':libelle'      => $data['libelle'],
            ':client_id'    => $data['client_id'] ?? null,
            ':sejour_id'    => $data['sejour_id'] ?? null,
            ':invoice_type' => $data['invoice_type'] ?? 'FV',
            ':payload'      => is_string($data['payload'])
                ? $data['payload']
                : json_encode($data['payload'], JSON_UNESCAPED_UNICODE),
            ':total'        => $data['total'] ?? 0,
            ':nb_articles'  => $data['nb_articles'] ?? 0,
            ':status'       => $data['status'] ?? self::STATUS_OUVERT,
            ':vente_id'     => $data['vente_id'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Met à jour le contenu d'un brouillon (nouveau panier sauvegardé).
     *
     * Le `status` repasse à 'ouvert' : ré-enregistrer un brouillon déjà
     * facturé le rouvre (le caissier a modifié son panier avant de facturer).
     *
     * @return bool true si la ligne a été mise à jour.
     */
    public function update($id, $data)
    {
        $sql = 'UPDATE caisse_brouillons SET
                    libelle      = :libelle,
                    client_id    = :client_id,
                    sejour_id    = :sejour_id,
                    invoice_type = :invoice_type,
                    payload      = :payload,
                    total        = :total,
                    nb_articles  = :nb_articles,
                    status       = :status,
                    vente_id     = :vente_id
                WHERE id = :id';

        return $this->db->execute($sql, [
            ':id'           => $id,
            ':libelle'      => $data['libelle'],
            ':client_id'    => $data['client_id'] ?? null,
            ':sejour_id'    => $data['sejour_id'] ?? null,
            ':invoice_type' => $data['invoice_type'] ?? 'FV',
            ':payload'      => is_string($data['payload'])
                ? $data['payload']
                : json_encode($data['payload'], JSON_UNESCAPED_UNICODE),
            ':total'        => $data['total'] ?? 0,
            ':nb_articles'  => $data['nb_articles'] ?? 0,
            ':status'       => $data['status'] ?? self::STATUS_OUVERT,
            ':vente_id'     => $data['vente_id'] ?? null,
        ]);
    }

    /**
     * Récupère un brouillon par son ID.
     *
     * @param int      $id
     * @param int|null $shopId Restreint la lecture à une boutique (scoping).
     * @return array|null
     */
    public function findById($id, $shopId = null)
    {
        $sql = "SELECT b.*,
                       COALESCE(u.nom_complet, '" . User::DELETED_LABEL . "') AS auteur,
                       c.nom_client, c.code_client, c.numero AS client_numero
                FROM caisse_brouillons b
                LEFT JOIN utilisateurs u ON b.user_id = u.id
                LEFT JOIN clients c ON b.client_id = c.id
                WHERE b.id = ?";
        $params = [$id];

        if ($shopId !== null) {
            $sql .= ' AND b.shop_id = ?';
            $params[] = $shopId;
        }

        return $this->db->fetch($sql, $params);
    }

    /**
     * Liste les brouillons d'une boutique, du plus récemment modifié au plus ancien.
     *
     * @param int|null $shopId
     * @param int|null $userId Si null, on liste ceux de TOUS les utilisateurs
     *                        de la boutique (Q2 : lecture croisée autorisée).
     * @param string   $status  'ouvert', 'converti' ou '' (tous).
     * @param int      $limit
     * @return array
     */
    public function listForShop($shopId = null, $userId = null, $status = '', $limit = 100)
    {
        $where = [];
        $params = [];

        if ($shopId !== null) {
            $where[] = 'b.shop_id = ?';
            $params[] = $shopId;
        }
        if ($userId !== null) {
            $where[] = 'b.user_id = ?';
            $params[] = $userId;
        }
        if ($status !== '') {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }

        $sql = "SELECT b.id, b.libelle, b.client_id, b.sejour_id, b.invoice_type,
                       b.total, b.nb_articles, b.status, b.vente_id,
                       b.created_at, b.updated_at, b.user_id,
                       COALESCE(u.nom_complet, '" . User::DELETED_LABEL . "') AS auteur,
                       c.nom_client, c.code_client
                FROM caisse_brouillons b
                LEFT JOIN utilisateurs u ON b.user_id = u.id
                LEFT JOIN clients c ON b.client_id = c.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY b.updated_at DESC LIMIT ' . (int)$limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Marque un brouillon comme converti en facture.
     *
     * @param int $id
     * @param int $venteId ID de la vente créée.
     * @return bool
     */
    public function markConverted($id, $venteId)
    {
        $sql = 'UPDATE caisse_brouillons
                SET status = :status, vente_id = :vente_id
                WHERE id = :id';

        return $this->db->execute($sql, [
            ':status'   => self::STATUS_CONVERTI,
            ':vente_id' => $venteId,
            ':id'       => $id,
        ]);
    }

    /**
     * Supprime un brouillon.
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->db->execute('DELETE FROM caisse_brouillons WHERE id = ?', [$id]);
    }

    /**
     * Purge les brouillons 'ouvert' trop anciens (Q3).
     *
     * ⚠️ Seuls les brouillons NON facturés sont purgés : un brouillon
     * 'converti' est une trace métier liée à une facture et n'est JAMAIS
     * supprimé. Aucune facture n'est touchée par cette opération.
     *
     * @param int $jours Seuil en jours (défaut : self::PURGE_JOURS).
     * @return int Nombre de brouillons supprimés.
     */
    public function purgeExpired($jours = self::PURGE_JOURS)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int)$jours . ' days'));

        $sql = 'DELETE FROM caisse_brouillons
                WHERE status = :status
                  AND updated_at < :cutoff';

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':status' => self::STATUS_OUVERT,
            ':cutoff' => $cutoff,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Compte les brouillons 'ouvert' d'un utilisateur (badge du menu).
     *
     * @param int|null $shopId
     * @param int|null $userId
     * @return int
     */
    public function countOpen($shopId = null, $userId = null)
    {
        $where = ['status = ?'];
        $params = [self::STATUS_OUVERT];

        if ($shopId !== null) {
            $where[] = 'shop_id = ?';
            $params[] = $shopId;
        }
        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }

        $sql = 'SELECT COUNT(*) AS total FROM caisse_brouillons WHERE ' . implode(' AND ', $where);

        $row = $this->db->fetch($sql, $params);

        return (int)($row['total'] ?? 0);
    }
}
