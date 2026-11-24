<?php

namespace App\Models;

class Product
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll($shopId = null, $includeInactiveShops = false)
    {
        return $this->getAllFiltered($shopId, $includeInactiveShops);
    }

    /**
     * Compte les produits correspondant aux mêmes filtres que
     * `getAllFiltered()`.
     *
     * ⚠️ Sert au tableau de bord, qui affichait le nombre de produits en
     * chargeant TOUT le catalogue (`getAll()` + `count()`) : 6 000 lignes
     * chargées pour afficher un simple nombre (400 ms mesurés).
     * Un `COUNT(*)` fait le même travail en 2,6 ms.
     *
     * Le filtre des boutiques INACTIVES est reproduit à l'identique
     * (`s.id IS NULL OR s.actif = 1`) : les produits sans boutique
     * rattachée restent comptés, ceux d'une boutique désactivée non.
     *
     * @param int|string|null $shopId
     * @param bool $includeInactiveShops
     * @param array $filters
     * @return int
     */
    public function countFiltered($shopId = null, $includeInactiveShops = false, array $filters = [])
    {
        $where = [];
        $params = [];

        if ($shopId) {
            $where[] = 'p.shop_id = ?';
            $params[] = $shopId;
        }

        if (!empty($filters['shop_id'])) {
            $where[] = 'p.shop_id = ?';
            $params[] = (int)$filters['shop_id'];
        }

        if (!$includeInactiveShops) {
            $where[] = '(s.id IS NULL OR s.actif = 1)';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $row = $this->db->fetch("SELECT COUNT(*) AS c
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql", $params);

        return (int)($row['c'] ?? 0);
    }

    /**
     * Liste les produits dont le stock est sous le seuil d'alerte.
     *
     * ⚠️ Remplace le `array_filter()` du tableau de bord, qui parcourait
     * 6 000 produits en PHP pour en garder quelques dizaines.
     *
     * Le stock retenu est la SOMME des lots (`total_stock`), comme le
     * faisait l'ancien `getAll()` : on ne se fie donc pas à la colonne
     * dénormalisée `produits.stock`, mais à la source de vérité
     * (`product_batches`).
     *
     * @param int|string|null $shopId
     * @param bool $includeInactiveShops
     * @return array
     */
    public function getLowStock($shopId = null, $includeInactiveShops = false)
    {
        $where = [];
        $params = [];

        if ($shopId) {
            $where[] = 'p.shop_id = ?';
            $params[] = $shopId;
        }

        if (!$includeInactiveShops) {
            $where[] = '(s.id IS NULL OR s.actif = 1)';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->fetchAll("SELECT p.id, p.nom, p.code_barres, p.prix, p.stock_minimum,
                COALESCE(SUM(pb.stock), 0) AS total_stock
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN shops s ON p.shop_id = s.id
            LEFT JOIN product_batches pb ON pb.product_id = p.id
            $whereSql
            GROUP BY p.id, p.nom, p.code_barres, p.prix, p.stock_minimum
            HAVING COALESCE(SUM(pb.stock), 0) <= p.stock_minimum
            ORDER BY p.nom ASC", $params);
    }

    /**
     * Liste les produits avec des filtres optionnels.
     *
     * Filtres acceptés :
     *   - search    : recherche sur le nom ou le code-barres (LIKE)
     *   - category  : nom exact de la catégorie
     *   - shop_id   : ID de la boutique (super_admin)
     *
     * @param int|string|null $shopId
     * @param bool $includeInactiveShops
     * @param array $filters
     */
    public function getAllFiltered($shopId = null, $includeInactiveShops = false, array $filters = [])
    {
        $where = [];
        $params = [];

        if ($shopId) {
            $where[] = 'p.shop_id = ?';
            $params[] = $shopId;
        }

        // Filtre boutique explicite (super_admin) : prime sur $shopId.
        if (!empty($filters['shop_id'])) {
            $where[] = 'p.shop_id = ?';
            $params[] = (int)$filters['shop_id'];
        }

        // Filtre catégorie (le <select> de la page stocke le NOM).
        // ⚠️ Quand une boutique est ciblée, on filtre aussi sur le shop_id
        // de la catégorie : sinon deux boutiques portant la même
        // catégorie seraient mélangées.
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $where[] = 'c.category = ?';
            $params[] = $filters['category'];

            $targetShop = !empty($filters['shop_id']) ? (int)$filters['shop_id'] : $shopId;
            if ($targetShop) {
                $where[] = 'c.shop_id = ?';
                $params[] = $targetShop;
            }
        }

        // Recherche texte sur le nom ou le code-barres.
        if (!empty($filters['search'])) {
            $where[] = '(p.nom LIKE ? OR p.code_barres LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Filtrer par défaut les produits dont la boutique est désactivée.
        // Les produits sans shop rattaché (shop_id NULL) restent visibles.
        if (!$includeInactiveShops) {
            $where[] = '(s.id IS NULL OR s.actif = 1)';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->fetchAll("SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
                COALESCE(SUM(pb.stock), 0) AS total_stock,
                COALESCE(SUM(CASE WHEN pb.date_expiration IS NULL OR pb.date_expiration >= CURDATE() THEN pb.stock ELSE 0 END), 0) AS available_stock,
                MIN(CASE WHEN pb.date_expiration IS NOT NULL AND pb.stock > 0 AND pb.date_expiration >= CURDATE() THEN pb.date_expiration END) AS nearest_expiration_date
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            LEFT JOIN product_batches pb ON pb.product_id = p.id
            $whereSql
            GROUP BY p.id
            ORDER BY p.nom ASC", $params);
    }

    public function findByBarcode($barcode)
    {
        $product = $this->db->fetch('SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette
            FROM produits p
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.code_barres = :code_barres
              AND (s.id IS NULL OR s.actif = 1)', [':code_barres' => $barcode]);
        if ($product) {
            $product = $this->enrichWithBatchInfo($product);
        }
        return $product;
    }

    /**
     * Recherche un produit par code-barres et renvoie SON ID (ou null).
     *
     * ⚠️ Ne fait PAS appel à `enrichWithBatchInfo()` : ce dernier ajoute
     * 4 requêtes (stock total, stock disponible, péremption, lots) qui sont
     * inutiles quand on ne cherche que l'identifiant — c'est le cas de
     * l'import produits, qui interproge le catalogue ligne par ligne.
     *
     * La SÉMANTIQUE est volontairement IDENTIQUE à `findByBarcode()` :
     *   - comparaison du code-barres faite par MySQL (collation
     *     `utf8mb4_0900_ai_ci` → insensible à la casse ET aux accents) ;
     *   - les produits d'une boutique INACTIVE ne sont PAS trouvés
     *     (`s.id IS NULL OR s.actif = 1`).
     */
    public function findIdByBarcode($barcode)
    {
        $row = $this->db->fetch('SELECT p.id
            FROM produits p
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.code_barres = :code_barres
              AND (s.id IS NULL OR s.actif = 1)', [':code_barres' => $barcode]);
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Recherche par lot les produits correspondant à une liste de code-barres.
     *
     * ⚠️ Pourquoi une requête batchée et pas une boucle de `findIdByBarcode()` ?
     * L'import de 5 000 produits déclenchait 25 000 requêtes (5 par ligne,
     * dont les 4 d'enrichissement inutiles) → 7,5 s. Ici : 1 requête par lot
     * de 500 code-barres → ~50 ms pour 5 000 produits.
     *
     * La comparaison reste FAITE PAR MySQL : le code-barres du fichier et
     * celui stocké en base peuvent différer par la casse ou les accents
     * (`utf8mb4_0900_ai_ci`), et c'est MySQL qui décide de l'égalité.
     * Un tableau PHP ne reproduirait pas cette sémantique.
     *
     * @param array $barcodes Liste de code-barres (tels que saisis dans le fichier)
     * @return array<string,int|null> Map code-barres du fichier → id du produit (null = absent)
     */
    public function findIdsByBarcodes(array $barcodes): array
    {
        $result = [];
        if (empty($barcodes)) {
            return $result;
        }

        // On déduplique en conservant l'ordre du premier passage : le map
        // retourné doit contenir une entrée par code-barres SAISI.
        $unique = [];
        foreach ($barcodes as $bc) {
            $bc = (string)$bc;
            if ($bc !== '' && !array_key_exists($bc, $unique)) {
                $unique[$bc] = true;
            }
        }
        $unique = array_keys($unique);

        // MySQL a une limite pratique de placeholders : on découpe par lots.
        $chunkSize = 500;

        foreach (array_chunk($unique, $chunkSize) as $chunk) {
            // Un `SELECT ? AS cb` par code-barres : les placeholders sont
            // positionnels, tous distincts, donc pas de HY093.
            $selects = [];
            foreach ($chunk as $i) {
                $selects[] = 'SELECT ? AS cb';
            }

            // ⚠️ La jointure sur `shops` est SANS filtre, et la règle
            // « boutique inactive = produit introuvable » est appliquée en
            // PHP (cf. plus bas). Mettre le filtre dans le WHERE ferait
            // DISPARAÎTRE la ligne du résultat : le map n'aurait pas
            // d'entrée pour ce code-barres, et l'import tenterait de le
            // CRÉER (échec sur l'UNIQUE global de `code_barres`).
            $sql = 'SELECT q.cb AS queried, p.id AS id, p.shop_id AS shop_id, s.actif AS shop_actif
                    FROM (' . implode(' UNION ALL ', $selects) . ') q
                    LEFT JOIN produits p ON p.code_barres = q.cb
                    LEFT JOIN shops s ON p.shop_id = s.id';

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute(array_values($chunk));

            foreach ($stmt->fetchAll() as $row) {
                $isFound = $row['id'] !== null
                    && ($row['shop_id'] === null || (int)$row['shop_actif'] === 1);
                // La clé du map est le code-barres TEL QUE SAISI (colonne
                // `queried`), pas celui stocké : ils peuvent différer.
                $result[$row['queried']] = $isFound ? (int)$row['id'] : null;
            }
        }

        // Tout code-barres non trouvé dans la base doit avoir une entrée
        // explicite (null) : l'appelant distingue « absent » de « inconnu ».
        foreach ($unique as $bc) {
            if (!array_key_exists($bc, $result)) {
                $result[$bc] = null;
            }
        }

        return $result;
    }

    public function findById($id)
    {
        // `t.groupe_taxe AS tax_groupe` : le groupe complet ("Groupe B") est
        // nécessaire pour FIGER le groupe de taxation sur la ligne de vente
        // (cf. SaleController::create() et la migration 2026_11_06).
        // Sans lui, la ligne de détail n'avait que l'étiquette ("B").
        $product = $this->db->fetch('SELECT p.*, t.taux AS tax_rate, t.etiquette AS tax_etiquette, t.groupe_taxe AS tax_groupe
            FROM produits p
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.id = :id
              AND (s.id IS NULL OR s.actif = 1)', [':id' => $id]);
        if ($product) {
            $product = $this->enrichWithBatchInfo($product);
        }
        return $product;
    }

    public function findByImage($image)
    {
        return $this->db->fetch('SELECT id, shop_id, image FROM produits WHERE image = :image', [':image' => $image]);
    }

    private function enrichWithBatchInfo($product)
    {
        $batchModel = new ProductBatch();
        $product['total_stock'] = $batchModel->getTotalStock($product['id']);
        $product['available_stock'] = $batchModel->getAvailableStock($product['id']);
        $product['nearest_expiration_date'] = $batchModel->getNearestExpirationDate($product['id']);
        $product['batches'] = $batchModel->findByProductId($product['id']);
        return $product;
    }

    public function getStock($id)
    {
        $batchModel = new ProductBatch();
        return $batchModel->getAvailableStock($id);
    }

    public function hasEnoughStock($id, $quantity)
    {
        $batchModel = new ProductBatch();
        return $batchModel->hasEnoughStock($id, $quantity);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type, prod_service, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value, date_expiration)
                VALUES (:code_barres, :nom, :category_id, :shop_id, :prix, :stock, :stock_minimum, :image, :taxe_id, :product_type, :prod_service, :remise_type, :remise_value, :taxe_specifique_type, :taxe_specifique_value, :date_expiration)';
        $this->db->query($sql, [
            ':code_barres'   => $data['code_barres'],
            ':nom'           => $data['nom'],
            ':category_id'   => $data['category_id'],
            ':shop_id'       => $data['shop_id'] ?? null,
            ':prix'          => $data['prix'],
            ':stock'         => $data['stock'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image'         => $data['image'] ?? '',
            ':taxe_id'       => $data['taxe_id'] ?? 1,
            ':product_type'  => $data['product_type'] ?? 'unite',
            ':prod_service'  => $data['prod_service'] ?? null,
            ':remise_type'   => $data['remise_type'] ?? '%',
            ':remise_value'  => $data['remise_value'] ?? 0,
            ':taxe_specifique_type' => $data['taxe_specifique_type'] ?? '%',
            ':taxe_specifique_value' => $data['taxe_specifique_value'] ?? 0,
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null,
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function updateStock($id, $quantity)
    {
        $sql = 'UPDATE produits SET stock = stock - :quantite WHERE id = :id';
        return $this->db->query($sql, [':quantite' => $quantity, ':id' => $id]);
    }

    public function update($id, $data)
    {
        $sql = 'UPDATE produits SET
                code_barres = :code_barres,
                nom = :nom,
                category_id = :category_id,
                shop_id = :shop_id,
                prix = :prix,
                stock_minimum = :stock_minimum,
                image = :image,
                taxe_id = :taxe_id,
                product_type = :product_type,
                prod_service = :prod_service,
                remise_type = :remise_type,
                remise_value = :remise_value,
                taxe_specifique_type = :taxe_specifique_type,
                taxe_specifique_value = :taxe_specifique_value,
                date_expiration = :date_expiration
                WHERE id = :id';
        $this->db->query($sql, [
            ':id' => $id,
            ':code_barres' => $data['code_barres'],
            ':nom' => $data['nom'],
            ':category_id' => $data['category_id'],
            ':shop_id' => $data['shop_id'] ?? null,
            ':prix' => $data['prix'],
            ':stock_minimum' => $data['stock_minimum'],
            ':image' => $data['image'] ?? '',
            ':taxe_id' => $data['taxe_id'] ?? 1,
            ':product_type' => $data['product_type'] ?? 'unite',
            ':prod_service' => $data['prod_service'] ?? null,
            ':remise_type' => $data['remise_type'] ?? '%',
            ':remise_value' => $data['remise_value'] ?? 0,
            ':taxe_specifique_type' => $data['taxe_specifique_type'] ?? '%',
            ':taxe_specifique_value' => $data['taxe_specifique_value'] ?? 0,
            ':date_expiration' => !empty($data['date_expiration']) ? $data['date_expiration'] : null,
        ]);
        $this->recalculateProductStock($id);
        return true;
    }

    private function recalculateProductStock($id)
    {
        $batchModel = new ProductBatch();
        $total = $batchModel->getTotalStock($id);
        $this->db->query('UPDATE produits SET stock = :stock WHERE id = :id', [':stock' => $total, ':id' => $id]);
    }

    public function delete($id)
    {
        $sql = 'DELETE FROM produits WHERE id = :id';
        return $this->db->query($sql, [':id' => $id]);
    }
}
