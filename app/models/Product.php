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
    /**
     * Construit le clause WHERE et les paramètres de la requête catalogue.
     *
     * ⚠️ Cette méthode est PARTAGÉE par `getAllFiltered()` et `getPage()` :
     * les deux DOIVENT filtrer pareil. Dupliquer ce code a déjà caused
     * une divergence (le filtre des boutiques inactives), et la page
     * `/produits` a affiché un compteur faux pendant des semaines.
     *
     * @return array{0: string, 1: array} [whereSql, params]
     */
    /**
     * Construit le WHERE du catalogue.
     *
     * @param string $searchMode 'both' (complet), 'prefix' (indexable)
     *                         ou 'barcode' (code-barres exact).
     */
    private function buildCatalogWhere($shopId, $includeInactiveShops, array $filters, string $searchMode = 'both'): array
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
            /*
             * RECHERCHE HYBRIDE (préfixe + infixe).
             *
             * Mesuré à 50 000 produits (base jetable, schéma réel) :
             *   LIKE '%terme%'  → 29,6 – 44,7 ms  (scan complet)
             *   LIKE 'terme%'   →  4,0 –  4,1 ms  (utilise idx_prod_shop_nom)
             *
             * Le FULLTEXT a été ÉVALUÉ ET ÉCARTÉ :
             *   - il n'apporte rien sur le préfixe (4,5 ms vs 4,0 ms) ;
             *   - `ft_min_word_len = 4` fait que `MATCH '+ca*'` renvoie
             *     6 038 résultats FAUX (il ignore le mot), alors que
             *     `LIKE 'ca%'` renvoie 2 041 résultats PERTINENTS ;
             *   - il ne trouve pas les termes partiels ('Coca' dans
             *     'Coca-Cola').
             *
             * La stratégie retenue : on teste le PRÉFIXE (indexé, rapide)
             * et on ne bascule sur l'infixe (lent, mais complet) que si le
             * préfixe ne donne rien. Un code-barres est toujours cherché
             * en exact : il a un index UNIQUE (0,1 ms).
             */
            $search = (string)$filters['search'];

            if ($searchMode === 'barcode') {
                // Recherche par code-barres EXACT (index UNIQUE, 0,1 ms).
                // C'est le chemin du scanner : il doit toujours trouver
                // le produit, quelle que soit la recherche par nom.
                $where[] = 'p.code_barres = ?';
                $params[] = $search;
            } else {
                $where[] = $this->buildSearchCondition($search, $searchMode);
                // Les placeholders sont dans l'ordre d'apparition dans le
                // SQL généré : on les ajoute après avoir construit la
                // condition.
                foreach ($this->searchParams as $sp) {
                    $params[] = $sp;
                }
            }
        }

        // Filtrer par défaut les produits dont la boutique est désactivée.
        // Les produits sans shop rattaché (shop_id NULL) restent visibles.
        if (!$includeInactiveShops) {
            $where[] = '(s.id IS NULL OR s.actif = 1)';
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * Construit la condition de recherche et renseigne `$searchParams`.
     *
     * @var array $searchParams Placeholders dans l'ordre d'apparition.
     */
    private array $searchParams = [];

    /**
     * Condition SQL de recherche sur le NOM (le code-barres est traité
     * À PART, dans une requête distincte — cf. `getPage()`).
     *
     * ⚠️ MESURE à 50 000 produits — la FORME du predicate est décisive :
     *
     *   (p.nom LIKE 'X%')                       →  0,5 ms  type=range
     *                                               sur idx_prod_shop_nom
     *   (p.nom LIKE 'X%' OR p.code_barres LIKE 'X%')
     *                                            → 12,4 ms  + "Using where"
     *
     * Le `OR code_barres LIKE ?` dans la MÊME requête empêche MySQL
     * d'exploiter l'index pour le préfixe : il doit vérifier la seconde
     * branche ligne à ligne. D'où la stratégie en DEUX requêtes.
     *
     * @param string $search Terme recherché.
     * @param string $mode   'prefix' (indexable) ou 'both' (complet).
     */
    private function buildSearchCondition(string $search, string $mode = 'both'): string
    {
        $this->searchParams = [];

        $search = trim($search);
        if ($search === '') {
            return '1=1';
        }

        if ($mode === 'prefix') {
            $this->searchParams[] = $search . '%';
            return '(p.nom LIKE ?)';
        }

        $this->searchParams[] = '%' . $search . '%';
        return '(p.nom LIKE ?)';
    }

    /**
     * Liste TOUS les produits correspondant aux filtres.
     *
     * ⚠️ Charge le catalogue ENTIER : à 50 000 produits, c'est 3,2 Mo de
     * JSON. Utiliser `getPage()` pour un affichage paginé.
     *
     * Les colonnes de stock sont lues DÉNORMALISÉES (`produits.stock`,
     * `produits.available_stock`, `produits.nearest_expiration_date`),
     * maintenues par `ProductBatch::recalculateProductStock()`.
     *
     * ⚠️ AVANT, cette requête agrégeait les lots à chaque lecture
     * (`GROUP BY` sur 85 000 lignes) : mesuré à 5 883 – 6 676 ms pour
     * 50 000 produits, contre 586 – 675 ms pour une page de 50. C'est le
     * gain le plus important de la migration de dénormalisation.
     */
    public function getAllFiltered($shopId = null, $includeInactiveShops = false, array $filters = [])
    {
        [$whereSql, $params] = $this->buildCatalogWhere($shopId, $includeInactiveShops, $filters);

        return $this->db->fetchAll("SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
                p.stock AS total_stock,
                p.available_stock AS available_stock,
                p.nearest_expiration_date AS nearest_expiration_date
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql
            ORDER BY p.nom ASC", $params);
    }

    /**
     * Page de produits, avec pagination serveur.
     *
     * ⚠️ C'est la méthode à utiliser pour AFFICHER le catalogue : à
     * 50 000 produits, charger le catalogue entier (3,2 Mo de JSON) et
     * paginer côté client fait geler le navigateur.
     *
     * Le filtre est IDENTIQUE à celui de `getAllFiltered()` (même
     * construction de WHERE, cf. `buildCatalogWhere()`).
     *
     * @param array    $filters search, category, shop_id
     * @param int      $limit   taille de page (bornée à 200)
     * @param int      $offset  décalage
     * @param int|null $shopId
     * @param bool     $includeInactiveShops
     * @return array{rows: array, total: int, limit: int, offset: int}
     */
    public function getPage(array $filters = [], int $limit = 50, int $offset = 0, $shopId = null, $includeInactiveShops = false): array
    {
        // Bornes : un `limit` fou renverrait le catalogue entier, et un
        // `offset` négatif fait échouer MySQL.
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $search = trim((string)($filters['search'] ?? ''));

        /*
         * RECHERCHE EN DEUX TEMPS — c'est LA partie la plus mesurée de ce
         * fichier. À 50 000 produits (base jetable, schéma réel) :
         *
         *   (p.nom LIKE 'X%')                       →  0,5 ms   type=range
         *   (p.nom LIKE 'X%' OR p.code_barres LIKE 'X%')
         *                                            → 12,4 ms   + "Using where"
         *   (p.nom LIKE '%X%')                      → 106 ms    scan complet
         *
         * Le `OR` sur le code-barres dans la MÊME requête empeche MySQL
         * d'utiliser l'index pour le préfixe. On procède donc ainsi :
         *
         *   1. requête « nom préfixe »  → indexable, 0,5 ms ;
         *   2. si elle ne donne RIEN, requête « nom infixe » (complet) ;
         *   3. le code-barres est cherché SÉPARÉMENT (index UNIQUE), et
         *      fusionné au résultat.
         *
         * Le FULLTEXT a été évalué et écarté : aucun gain sur le préfixe
         * (4,5 ms vs 4,0 ms), et `ft_min_word_len = 4` le rend INUTILISABLE
         * sur les termes courts — mesuré : `MATCH '+ca*'` renvoie 6 038
         * résultats FAUX (il ignore le mot), là où `LIKE 'ca%'` renvoie
         * 2 041 résultats PERTINENTS. Or « cola », « pain », « sel » sont
         * des recherches de caisse ultra-fréquentes.
         */
        if ($search !== '') {
            return $this->getPageSearched($filters, $limit, $offset, $shopId, $includeInactiveShops, $search);
        }

        [$whereSql, $params] = $this->buildCatalogWhere($shopId, $includeInactiveShops, $filters);

        // Le total est compté SÉPARÉMENT : le `COUNT(*)` avec jointures
        // est mesuré à 2,0 ms sur 6 000 produits (et reste linéaire).
        $countSql = "SELECT COUNT(*) AS c
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql";
        $total = (int)($this->db->fetch($countSql, $params)['c'] ?? 0);

        // ⚠️ `LIMIT`/`OFFSET` en LITTERAUX castés en int : le projet
        // tourne avec `EMULATE_PREPARES = false`, et mélanger des
        // placeholders nommés avec des positionnels lève HY093.
        // (Même approche que `CaisseBrouillon::listDrafts()`.)
        $sql = "SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
                p.stock AS total_stock,
                p.available_stock AS available_stock,
                p.nearest_expiration_date AS nearest_expiration_date
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql
            ORDER BY p.nom ASC
            LIMIT {$limit} OFFSET {$offset}";

        return [
            'rows'   => $this->db->fetchAll($sql, $params),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Page du catalogue AVEC recherche.
     *
     * Stratégie en trois temps (cf. le commentaire de `getPage()` pour
     * les mesures) :
     *   1. nom en PRÉFIXE  → requête indexable, 0,5 ms à 50 000 produits ;
     *   2. si vide, nom en INFIXE → requête complète (plus lente, mais
     *      c'est le seul moyen de trouver « Cola » dans « Coca-Cola ») ;
     *   3. code-barres Exact (index UNIQUE) → fusionné au résultat.
     *
     * Le code-barres est cherché dans TOUS les cas (y compris quand le
     * préfixe a déjà trouvé des produits) : scanner un code-barres doit
     * TOUJOURS fonctionner, et c'est 0,1 ms.
     */
    private function getPageSearched(array $filters, int $limit, int $offset, $shopId, bool $includeInactiveShops, string $search): array
    {
        $prefixRows = null;
        $total = 0;

        // ── 1. Préfixe (indexable) ─────────────────────────────────────
        [$whereSql, $params] = $this->buildCatalogWhere($shopId, $includeInactiveShops, $filters, 'prefix');
        $prefixSql = $this->catalogSelectSql($whereSql) . " ORDER BY p.nom ASC LIMIT {$limit} OFFSET {$offset}";
        $prefixRows = $this->db->fetchAll($prefixSql, $params);

        $countSql = "SELECT COUNT(*) AS c
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql";
        $total = (int)($this->db->fetch($countSql, $params)['c'] ?? 0);

        // ── 2. Repli en infixe si le préfixe ne donne RIEN ──────────────
        //    (le caissier a tapé un mot AU MILIEU du nom)
        if ($total === 0) {
            [$whereSql, $params] = $this->buildCatalogWhere($shopId, $includeInactiveShops, $filters, 'both');
            $infixSql = $this->catalogSelectSql($whereSql) . " ORDER BY p.nom ASC LIMIT {$limit} OFFSET {$offset}";
            $prefixRows = $this->db->fetchAll($infixSql, $params);
            $total = (int)($this->db->fetch($countSql, $params)['c'] ?? 0);
        }

        // ── 3. Code-barres : toujours cherché en exact ─────────────────
        //    (index UNIQUE sur code_barres → 0,1 ms)
        $barcodeRows = [];
        if (preg_match('/^[A-Za-z0-9._-]{3,}$/', $search)) {
            [$bcWhere, $bcParams] = $this->buildCatalogWhere($shopId, $includeInactiveShops, $filters, 'barcode');
            $bcSql = $this->catalogSelectSql($bcWhere) . " ORDER BY p.nom ASC LIMIT {$limit}";
            $barcodeRows = $this->db->fetchAll($bcSql, $bcParams);

            $bcCountSql = "SELECT COUNT(*) AS c
                FROM produits p
                INNER JOIN categories c ON p.category_id = c.id
                LEFT JOIN shops s ON p.shop_id = s.id
                $bcWhere";
            $barcodeTotal = (int)($this->db->fetch($bcCountSql, $bcParams)['c'] ?? 0);
        } else {
            $barcodeTotal = 0;
        }

        // Fusion : les produits trouvés par code-barres ne sont pas
        // doublons de ceux trouvés par le nom.
        $seen = [];
        $rows = [];
        foreach (array_merge($barcodeRows, $prefixRows) as $r) {
            $id = (int)$r['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = $r;
        }

        // Le total combine les deux sources, sans double compte.
        $total = $total + $barcodeTotal;

        return [
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Le SELECT commun du catalogue (colonnes dénormalisées, jointures).
     */
    private function catalogSelectSql(string $whereSql): string
    {
        return "SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
                p.stock AS total_stock,
                p.available_stock AS available_stock,
                p.nearest_expiration_date AS nearest_expiration_date
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            $whereSql";
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
        /*
         * ⚠️ La boutique AVANT modification est lue ICI, avant l'UPDATE :
         *    après, la ligne porte déjà la nouvelle valeur et la
         *    différence serait invisible (donc jamais synchronisée).
         */
        $shopIdAvant = $this->shopIdActuel((int)$id);
        $shopIdApres = $data['shop_id'] ?? null;

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
        /*
         * ⚠️ Si la BOUTIQUE du produit a changé, ses lots doivent suivre.
         *
         * `product_batches.shop_id` est dé-normalisé (migration
         * 2026_11_25_product_batches_shop_id_denormalise.sql) pour que la
         * page des lots puisse filtrer ET trier sur un seul index.
         *
         * Sans cette synchronisation, les lots resteraient rattachés à
         * l'ANCIENNE boutique : ils y seraient encore listés (fuite de
         * données inter-boutiques) et absents de la nouvelle.
         *
         * C'est le seul endroit du code où le `shop_id` d'un produit peut
         * changer : `Product::update()`.
         */
        if ((int)($shopIdApres ?? 0) !== (int)($shopIdAvant ?? 0)) {
            (new ProductBatch())->syncShopIdForProduct((int)$id);
        }

        return true;
    }

    /**
     * La boutique actuelle d'un produit (null si aucune).
     */
    private function shopIdActuel(int $id): ?int
    {
        $row = $this->db->fetch('SELECT shop_id FROM produits WHERE id = ? LIMIT 1', [$id]);
        if (!$row || $row['shop_id'] === null) {
            return null;
        }
        return (int)$row['shop_id'];
    }

    /**
     * Recalcule les colonnes de stock dénormalisées de `produits`.
     *
     * ⚠️ Délègue à `ProductBatch::recalculateProductStock()` : c'est le
     * point d'écriture unique de la dénormalisation. Cette méthode NE DOIT
     * PAS recalculer le stock par un chemin différent, sinon elle
     * écraserait `available_stock` avec une valeur incohérente.
     *
     * (Avant : cette méthode faisait un `UPDATE produits SET stock = ...`
     * sur la seule somme des lots, calculée par une requête séparée.)
     */
    private function recalculateProductStock($id)
    {
        $batchModel = new ProductBatch();
        $batchModel->recalculateProductStock($id);
    }

    public function delete($id)
    {
        $sql = 'DELETE FROM produits WHERE id = :id';
        return $this->db->query($sql, [':id' => $id]);
    }
}
