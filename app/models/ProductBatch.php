<?php

namespace App\Models;

class ProductBatch
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Crée un lot.
     *
     * ⚠️ `shop_id` est dé-normalisé (colonne ajoutée par
     *    migrations/2026_11_25_product_batches_shop_id_denormalise.sql).
     *    Il est lu depuis le PRODUIT, jamais depuis l'appelant : c'est le
     *    seul moyen de garantir que le lot porte toujours la bonne
     *    boutique, y compris quand l'appelant ne la connaît pas (import
     *    de masse, restauration de stock, création initiale).
     *
     *    Si le lot porte une boutique différente de son produit, il
     *    apparaît dans les lots de l'une mais pas de l'autre — une fuite
     *    de données inter-boutiques, silencieuse.
     */
    public function create($data)
    {
        $productId = (int)$data['product_id'];

        $sql = 'INSERT INTO product_batches (product_id, shop_id, batch_number, stock, date_expiration, date_reception)
                VALUES (:product_id, :shop_id, :batch_number, :stock, :date_expiration, :date_reception)';
        $this->db->query($sql, [
            ':product_id' => $productId,
            ':shop_id' => $this->shopIdOfProduct($productId),
            ':batch_number' => $data['batch_number'] ?? null,
            ':stock' => (float)$data['stock'],
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null,
            ':date_reception' => !empty($data['date_reception']) ? $data['date_reception'] : date('Y-m-d'),
        ]);
        $batchId = $this->db->getConnection()->lastInsertId();
        $this->recalculateProductStock($productId);
        return $batchId;
    }

    /**
     * La boutique d'un produit (null si le produit n'en a pas).
     *
     * Utilisé pour dé-normaliser `product_batches.shop_id`.
     */
    private function shopIdOfProduct(int $productId): ?int
    {
        $row = $this->db->fetch('SELECT shop_id FROM produits WHERE id = ? LIMIT 1', [$productId]);
        if (!$row || $row['shop_id'] === null) {
            return null;
        }
        return (int)$row['shop_id'];
    }

    /**
     * Resynchronise `shop_id` sur tous les lots d'un produit.
     *
     * ⚠️ À appeler quand le `shop_id` d'un PRODUIT change : les lots
     *    doivent suivre, sinon ils restent rattachés à l'ancienne boutique
     *    (fuite de données inter-boutiques).
     *
     * @return bool true si la synchronisation a été exécutée.
     */
    public function syncShopIdForProduct(int $productId): bool
    {
        $shopId = $this->shopIdOfProduct($productId);
        $this->db->query(
            'UPDATE product_batches SET shop_id = :shop_id
             WHERE product_id = :product_id AND NOT (shop_id <=> :shop_id2)',
            [':shop_id' => $shopId, ':shop_id2' => $shopId, ':product_id' => $productId]
        );
        return true;
    }

    public function findByProductId($productId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_batches WHERE product_id = ? ORDER BY date_expiration ASC, date_reception ASC, id ASC',
            [$productId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM product_batches WHERE id = ?', [$id]);
    }

    public function update($id, $data)
    {
        $batch = $this->findById($id);
        if (!$batch) {
            return false;
        }

        $sql = 'UPDATE product_batches SET
                    batch_number = :batch_number,
                    stock = :stock,
                    date_expiration = :date_expiration,
                    date_reception = :date_reception
                WHERE id = :id';
        $this->db->query($sql, [
            ':id' => $id,
            ':batch_number' => $data['batch_number'] ?? $batch['batch_number'],
            ':stock' => isset($data['stock']) ? (float)$data['stock'] : $batch['stock'],
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : $batch['date_expiration'],
            ':date_reception' => !empty($data['date_reception']) ? $data['date_reception'] : $batch['date_reception'],
        ]);
        $this->recalculateProductStock($batch['product_id']);
        return true;
    }

    public function delete($id)
    {
        $batch = $this->findById($id);
        if (!$batch) {
            return false;
        }
        $this->db->query('DELETE FROM product_batches WHERE id = ?', [$id]);
        $this->recalculateProductStock($batch['product_id']);
        return true;
    }

    public function getTotalStock($productId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(stock), 0) as total FROM product_batches WHERE product_id = ?',
            [$productId]
        );
        return (float)($row['total'] ?? 0);
    }

    public function getAvailableStock($productId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(stock), 0) as total FROM product_batches WHERE product_id = ? AND (date_expiration IS NULL OR date_expiration >= CURDATE())',
            [$productId]
        );
        return (float)($row['total'] ?? 0);
    }

    public function hasEnoughStock($productId, $quantity)
    {
        return $this->getAvailableStock($productId) >= (float)$quantity;
    }

    /**
     * Deduit le stock d'un produit en suivant la regle FIFO sur les lots non perimes.
     * Doit etre appele a l'interieur d'une transaction pour garantir la coherence.
     *
     * @param int $productId
     * @param float $quantity
     * @return bool true si la deduction a reussi, false si stock insuffisant
     */
    public function deductStock($productId, $quantity)
    {
        $quantity = (float)$quantity;
        if ($quantity <= 0) {
            return true;
        }

        if (!$this->hasEnoughStock($productId, $quantity)) {
            return false;
        }

        $batches = $this->getAvailableBatches($productId);
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $batchStock = (float)$batch['stock'];
            $deduct = min($batchStock, $remaining);
            $newStock = $batchStock - $deduct;

            $this->db->query(
                'UPDATE product_batches SET stock = :stock WHERE id = :id',
                [':stock' => $newStock, ':id' => $batch['id']]
            );

            $remaining -= $deduct;
        }

        $this->recalculateProductStock($productId);
        return true;
    }

    /**
     * Restaure du stock lors d'une annulation de vente.
     * Le stock est ajoute au lot non perime le plus ancien, ou cree un nouveau lot si aucun lot valide n'existe.
     */
    public function restoreStock($productId, $quantity)
    {
        $quantity = (float)$quantity;
        if ($quantity <= 0) {
            return true;
        }

        $batches = $this->getAvailableBatches($productId);
        if (!empty($batches)) {
            $oldest = $batches[0];
            $newStock = (float)$oldest['stock'] + $quantity;
            $this->db->query(
                'UPDATE product_batches SET stock = :stock WHERE id = :id',
                [':stock' => $newStock, ':id' => $oldest['id']]
            );
        } else {
            $this->create([
                'product_id' => $productId,
                'batch_number' => 'RESTORE-' . date('YmdHis'),
                'stock' => $quantity,
                'date_expiration' => null,
                'date_reception' => date('Y-m-d'),
            ]);
            return true;
        }

        $this->recalculateProductStock($productId);
        return true;
    }

    public function getAvailableBatches($productId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_batches
             WHERE product_id = ? AND stock > 0 AND (date_expiration IS NULL OR date_expiration >= CURDATE())
             ORDER BY CASE WHEN date_expiration IS NULL THEN 1 ELSE 0 END ASC, date_expiration ASC, date_reception ASC, id ASC',
            [$productId]
        );
    }

    /**
     * PAGE DES LOTS : liste paginée des lots, triés par péremption.
     *
     * ⚠️ PERF — mesuré à 50 000 produits / 85 000 lots, 5 boutiques :
     *
     *   jointure (b.shop_id via produits) → 187,1 ms  (Using temporary;
     *                                               Using filesort)
     *   dé-normalisé (b.shop_id)         →   1,7 ms
     *
     *   Le filtre ET le tri portent sur la même table, donc un seul index
     *   `(shop_id, date_expiration, id)` suffit.
     *
     * ⚠️ PAGINATION — deux modes, parce qu'ils n'ont pas les mêmes
     *    propriétés :
     *
     *   'keyset' (défaut) : on repart du dernier couple (date_expiration,
     *       id) et on demande la suite. Coût CONSTANT quelle que soit la
     *       page (~2 ms mesurés à la page 200). En revanche, on ne peut pas
     *       sauter directement à la page 500.
     *
     *   'offset' : permet l'accès direct à une page, mais MySQL doit
     *       PRODUIRE puis JETER les lignesprevious (mesuré : 177 ms à la
     *       page 1000).
     *
     * Le `id` est le tie-breaker : sans lui, deux lots expirant le même
     * jour ont le même couple de tri et MySQL les ordonne
     * arbitrairement — la pagination saute alors des lignes et en répète
     * d'autres.
     *
     * @param string $mode      'keyset' ou 'offset'.
     * @param string|null $lastExpiration  Borne inférieure (mode keyset).
     * @param int|null    $lastId          Borne inférieure (mode keyset).
     * @return array{rows: array, total: int, limit: int, offset: int}
     */
    public function getPage(
        ?int $shopId = null,
        int $limit = 50,
        int $offset = 0,
        string $mode = 'keyset',
        ?string $lastExpiration = null,
        ?int $lastId = null,
        bool $onlyWithStock = true
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $where = [];
        $params = [];

        if ($shopId !== null) {
            $where[] = 'b.shop_id = :shop_id';
            $params[':shop_id'] = $shopId;
        }
        if ($onlyWithStock) {
            $where[] = 'b.stock > 0';
        }

        // ── Mode keyset : la suite de (date_expiration, id) ──────────
        if ($mode === 'keyset' && $lastExpiration !== null && $lastId !== null) {
            $where[] = '(b.date_expiration > :exp1
                        OR (b.date_expiration = :exp2 AND b.id > :last_id))';
            $params[':exp1'] = $lastExpiration;
            $params[':exp2'] = $lastExpiration;
            $params[':last_id'] = (int)$lastId;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Le total : compté séparément (le COUNT avec jointure serait
        // inutilement lent, cf. Product::getPage()).
        $countSql = "SELECT COUNT(*) AS c FROM product_batches b $whereSql";
        $total = (int)($this->db->fetch($countSql, $params)['c'] ?? 0);

        $sql = "SELECT b.id, b.product_id, b.batch_number, b.stock,
                       b.date_expiration, b.date_reception,
                       p.nom AS product_name, p.code_barres
                FROM product_batches b
                INNER JOIN produits p ON p.id = b.product_id
                $whereSql
                ORDER BY b.date_expiration ASC, b.id ASC
                LIMIT $limit";

        if ($mode !== 'keyset') {
            $sql .= " OFFSET $offset";
        }

        return [
            'rows'   => $this->db->fetchAll($sql, $params),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Construit le WHERE + les params des requêtes "lots qui périment".
     *
     * ⚠️ L'ORDER BY de ces requêtes se termine par `b.id ASC` : sans ce
     * tie-breaker, deux lots du MÊME produit expirant le MÊME jour ont le
     * même (date_expiration, nom) et MySQL les rend dans un ordre
     * ARBITRAIRE. La pagination OFFSET saute alors des lignes et en
     * répète d'autres d'une page à l'autre — ce qui a été constaté et
     * corrigé par tests/Integration/ExpirationAlertsBoundsTest.php.
     *
     * Factorisé parce que `getExpiringSoon`, `getExpired` et leurs
     * compteurs doivent partager EXACTEMENT le même filtre — sinon le
     * compteur affiché et la liste affichée divergent, ce qui est
     * impossible à diagnostiquer pour l'utilisateur.
     */
    private function buildExpirationWhere($days, $shopId, array &$params, string $operator): string
    {
        if ($operator === '<') {
            $where = 'WHERE b.date_expiration IS NOT NULL AND b.date_expiration < CURDATE() AND b.stock > 0';
        } else {
            $where = 'WHERE b.date_expiration IS NOT NULL AND b.date_expiration >= CURDATE()
                      AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL :days DAY) AND b.stock > 0';
            $params[':days'] = (int)$days;
        }

        if ($shopId) {
            $where .= ' AND p.shop_id = :shop_id';
            $params[':shop_id'] = $shopId;
        }

        return $where;
    }

    /**
     * Lots qui périment dans les $days prochains.
     *
     * ⚠️ $limit borne le nombre de lignes renvoyées. SANS cette borne,
     * une boutique avec beaucoup de lots en fin de série charge TOUS
     * ces lots en mémoire puis les rend en HTML : mesuré à 50 000
     * produits / 85 000 lots, la fenêtre de 90 jours renvoyait 19 382
     * lignes (400 ms de SQL + 102 ms de rendu = 502 ms, et 19 382
     * nœuds DOM).
     *
     * Le nombre total est disponible via countExpiringSoon().
     */
    public function getExpiringSoon($days = 7, $shopId = null, $limit = 20, $offset = 0)
    {
        $params = [];
        $where = $this->buildExpirationWhere($days, $shopId, $params, '<=');

        $sql = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where
                ORDER BY b.date_expiration ASC, p.nom ASC, b.id ASC";

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)$offset);
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Lots DÉJÀ périmés.
     *
     * ⚠️ Même borne que getExpiringSoon() — voir le commentaire là-bas.
     */
    public function getExpired($shopId = null, $limit = 20, $offset = 0)
    {
        $params = [];
        $where = $this->buildExpirationWhere(null, $shopId, $params, '<');

        $sql = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where
                ORDER BY b.date_expiration ASC, p.nom ASC, b.id ASC";

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)$offset);
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Nombre TOTAL de lots qui périment — pour afficher « N alertes »
     * sans charger les N lignes.
     */
    public function countExpiringSoon($days = 7, $shopId = null): int
    {
        $params = [];
        $where = $this->buildExpirationWhere($days, $shopId, $params, '<=');

        $sql = "SELECT COUNT(*) as c
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where";

        return (int) ($this->db->fetch($sql, $params)['c'] ?? 0);
    }

    /**
     * Nombre TOTAL de lots déjà périmés.
     */
    public function countExpired($shopId = null): int
    {
        $params = [];
        $where = $this->buildExpirationWhere(null, $shopId, $params, '<');

        $sql = "SELECT COUNT(*) as c
                FROM product_batches b
                INNER JOIN produits p ON b.product_id = p.id
                $where";

        return (int) ($this->db->fetch($sql, $params)['c'] ?? 0);
    }

    public function getNearestExpirationDate($productId)
    {
        $row = $this->db->fetch(
            'SELECT MIN(date_expiration) as nearest FROM product_batches
             WHERE product_id = ? AND stock > 0 AND date_expiration >= CURDATE()',
            [$productId]
        );
        return $row['nearest'] ?? null;
    }

    /**
     * Recalcule les colonnes de stock dénormalisées de `produits`.
     *
     * ⚠️ C'est le POINT D'ÉCRITURE UNIQUE de la dénormalisation : toute
     * mutation de `product_batches` passe par ici (create, update, delete,
     * deductStock, restoreStock — voir les appels dans cette classe).
     *
     * ⚠️ Une écriture directe en SQL dans `product_batches` (script,
     * phpMyAdmin, migration) laisserait ces colonnes DÉSYNCHRONISÉES, et
     * le stock affiché serait faux sans aucun message d'erreur.
     *
     * Les trois colonnes sont recalculées en UNE SEULE requête (et non 4
     * requêtes) : à chaque vente, ce chemin est sur le chemin critique.
     *
     * @param int $productId
     */
    public function recalculateProductStock($productId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(stock), 0) AS total,
                    COALESCE(SUM(CASE WHEN date_expiration IS NULL OR date_expiration >= CURDATE()
                                      THEN stock ELSE 0 END), 0) AS available,
                    MIN(CASE WHEN date_expiration IS NOT NULL AND stock > 0
                              AND date_expiration >= CURDATE()
                             THEN date_expiration END) AS nearest
             FROM product_batches WHERE product_id = ?',
            [$productId]
        );

        $this->db->query(
            'UPDATE produits
                SET stock = :stock,
                    available_stock = :available_stock,
                    nearest_expiration_date = :nearest
              WHERE id = :id',
            [
                ':stock'            => (float)($row['total'] ?? 0),
                ':available_stock'  => (float)($row['available'] ?? 0),
                ':nearest'          => $row['nearest'] ?? null,
                ':id'               => $productId,
            ]
        );
    }
}
