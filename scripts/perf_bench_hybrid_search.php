<?php
/**
 * Mesure de la RECHERCHE HYBRIDE (préfixe + infixe dans une MÊME requête).
 *
 * Question : `WHERE nom LIKE 'c%' OR nom LIKE '%c%'` est-il aussi lent
 * qu'un simple `LIKE '%c%'` (scan complet), ou MySQL exploit-t-il
 * l'index pour la partie préfixe ?
 *
 * C'est LA question qui décide de l'implémentation. On compare :
 *   A. LIKE '%terme%'            (infixe seul)
 *   B. LIKE 'terme%'             (préfixe seul)
 *   C. hybride dans une requête  (préfixe OR infixe)
 *   D. deux requêtes séparées    (préfixe, puis infixe si vide)
 *
 * Base : pos_perf_hyb — créée puis supprimée.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';
const DB   = 'pos_perf_hyb';

function pdo(?string $db = null): PDO
{
    $dsn = $db ? DSN . ';dbname=' . $db : DSN;
    return new PDO($dsn, USER, PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

$N = (int) ($argv[1] ?? 50000);

echo "=== Préparation : {$N} produits / lots ===\n";
$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
$p->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$p = pdo(DB);

$p->exec('CREATE TABLE categories (id int NOT NULL AUTO_INCREMENT, category varchar(120) NOT NULL, shop_id int DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec('CREATE TABLE taxes (id int NOT NULL AUTO_INCREMENT, taux decimal(5,2) NOT NULL, etiquette varchar(20) DEFAULT NULL, groupe_taxe varchar(50) DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec('CREATE TABLE shops (id int NOT NULL AUTO_INCREMENT, nom varchar(100) NOT NULL, code varchar(20) NOT NULL DEFAULT "X", actif tinyint(1) DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB');
$p->exec(<<<SQL
CREATE TABLE produits (
  id int NOT NULL AUTO_INCREMENT, code_barres varchar(50) NOT NULL, nom varchar(100) NOT NULL,
  category_id int NOT NULL, shop_id int DEFAULT NULL, prix decimal(10,2) NOT NULL,
  stock float NOT NULL DEFAULT 0, stock_minimum float NOT NULL DEFAULT 0, image varchar(255) DEFAULT NULL,
  taxe_id int DEFAULT 1, product_type varchar(20) DEFAULT 'unite', prod_service enum('BIE','SER','TAX') DEFAULT NULL,
  remise_type varchar(10) DEFAULT 'percent', remise_value decimal(10,2) DEFAULT 0.00,
  taxe_specifique_type varchar(10) DEFAULT '%', taxe_specifique_value decimal(10,2) DEFAULT 0.00,
  date_expiration date DEFAULT NULL,
  available_stock float NOT NULL DEFAULT 0, nearest_expiration_date date DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY code_barres (code_barres), KEY category_id (category_id), KEY idx_prod_shop (shop_id)
) ENGINE=InnoDB
SQL);
$p->exec(<<<SQL
CREATE TABLE product_batches (
  id int NOT NULL AUTO_INCREMENT, product_id int NOT NULL, batch_number varchar(50) DEFAULT NULL,
  stock float NOT NULL DEFAULT 0, date_expiration date DEFAULT NULL, date_reception date DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY product_id (product_id), KEY date_expiration (date_expiration)
) ENGINE=InnoDB
SQL);

$marques = ['Coca','Pepsi','Sprite','Fanta','Orangina','Biere','Eau','Lait','Danone','Nestle','Yoplait','President','Camembert','Emmental','Baguette','Pain','Farine','Sucre','Sel','Huile','Riz','Pates','Tomate','Pesto','Sauce','Ketchup','Mayonnaise','Moutarde','Chocolat','Biscuit','Cereale','Yaourt','Fromage','Jambon','Saumon','Thon','Crevette','Poulet','Boeuf','Veau','Agneau','Porc','Semoule','Haricot','Lentille','Vanille','Caramel','Miel','Confiture'];
$unites  = ['33cl','1L','500g','250g','Pack','Dose','Bouteille','Canette','Brique','Sachet','Barquette','Piece'];
$mList = "'" . "','".join($marques) . "'";
$uList = "'" . "','".join($unites) . "'";

$p->exec("SET SESSION cte_max_recursion_depth = 100000");
$p->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Boissons',1),(2,'Alimentaire',1),(3,'Hygiène',1),(4,'Divers',1),(5,'Frais',1),(6,'Snacks',1),(7,'Épicerie',1),(8,'Boissons gazeuses',1),(9,'Boulangerie',1)");
$p->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A'),(2,10,'B','Groupe B'),(3,18,'C','Groupe C')");
$p->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique Test','BT',1)");
$p->exec(<<<SQL
INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < {$N})
SELECT CONCAT('BC-', LPAD(n, 8, '0')),
       CONCAT('Marque ', LPAD(((n - 1) % 49) + 1, 2, '0'),
              ' Format ', LPAD(((n - 1) % 12) + 1, 2, '0'),
              ' Lot ', LPAD(((n - 1) % 1000) + 1, 4, '0')),
       ((n - 1) % 9) + 1, 1, ROUND(500 + (n % 100000) / 100, 2), 50, 5, NULL, ((n - 1) % 3) + 1
FROM seq
SQL);

$batchCount = (int) round($N * 1.7);
$p->exec(<<<SQL
INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < {$batchCount})
SELECT ((n - 1) % {$N}) + 1, CONCAT('LOT-', n), 10 + (n % 40),
       DATE_ADD(CURDATE(), INTERVAL (n % 400) DAY), CURDATE() FROM seq
SQL);

$p->exec('CREATE INDEX idx_prod_shop_nom ON produits (shop_id, nom)');

// Backfill set-based (une seule lecture par lot) — cf. le piège mesuré.
$p->exec('CREATE TEMPORARY TABLE tmp_agg (product_id INT PRIMARY KEY, total_stock FLOAT, avail_stock FLOAT, near_exp DATE)');
$p->exec("INSERT INTO tmp_agg SELECT product_id, SUM(stock), SUM(CASE WHEN date_expiration IS NULL OR date_expiration >= CURDATE() THEN stock ELSE 0 END), MIN(CASE WHEN date_expiration IS NOT NULL AND stock > 0 AND date_expiration >= CURDATE() THEN date_expiration END) FROM product_batches GROUP BY product_id");
$p->exec('UPDATE produits p LEFT JOIN tmp_agg a ON a.product_id = p.id SET p.stock = COALESCE(a.total_stock,0), p.available_stock = COALESCE(a.avail_stock,0), p.nearest_expiration_date = a.near_exp');
$p->exec('DROP TEMPORARY TABLE tmp_agg');
$p->query('ANALYZE TABLE produits, product_batches')->fetchAll();
printf("    %d produits, %d lots, prêts\n\n", $N, $batchCount);

function bench(PDO $p, string $label, string $sql, array $params = [], int $runs = 5): array
{
    $times = [];
    $rows = 0;
    for ($i = 0; $i < $runs; $i++) {
        $s = $p->prepare($sql);
        $t0 = microtime(true);
        $s->execute($params);
        $res = $s->fetchAll();
        $times[] = (microtime(true) - $t0) * 1000;
        $rows = count($res);
    }
    sort($times);
    printf("  %-54s %8.1f ms  (%d)\n", $label, $times[(int) (count($times) / 2)], $rows);
    return [$rows, $times[(int) (count($times) / 2)]];
}

// La requête réelle de la page (jointures comprises).
$pageSql = "SELECT p.*, c.category AS categorie, t.taux AS tax_rate, s.nom AS shop_name
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1)
              AND %s
            ORDER BY p.nom ASC LIMIT 50 OFFSET 0";

$countSql = "SELECT COUNT(*) AS c FROM produits p
             INNER JOIN categories c ON p.category_id = c.id
             LEFT JOIN shops s ON p.shop_id = s.id
             WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1) AND %s";

echo "=== A. La PAGE (LIMIT 50) ===\n";
foreach (['Marque 07', 'Marque 07 Format 03', 'Format 03'] as $t) {
    echo "\n  terme « {$t} »\n";
    bench($p, "A. infixe seul   LIKE '%{$t}%'", sprintf($pageSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["%{$t}%", "%{$t}%"]);
    bench($p, "B. préfixe seul  LIKE '{$t}%'", sprintf($pageSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["{$t}%", "{$t}%"]);
    bench($p, "C. HYBRIDE       préfixe OR infixe", sprintf($pageSql, "(p.nom LIKE ? OR p.code_barres LIKE ? OR p.nom LIKE ? OR p.code_barres LIKE ?)"), ["{$t}%", "{$t}%", "%{$t}%", "%{$t}%"]);
}

echo "\n=== B. Le COUNT (pour le « X sur Y ») ===\n";
foreach (['Marque 07', 'Format 03'] as $t) {
    echo "\n  terme « {$t} »\n";
    bench($p, "infixe seul", sprintf($countSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["%{$t}%", "%{$t}%"]);
    bench($p, "préfixe seul", sprintf($countSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["{$t}%", "{$t}%"]);
    bench($p, "HYBRIDE", sprintf($countSql, "(p.nom LIKE ? OR p.code_barres LIKE ? OR p.nom LIKE ? OR p.code_barres LIKE ?)"), ["{$t}%", "{$t}%", "%{$t}%", "%{$t}%"]);
}

echo "\n=== C. Le CAS RÉEL : stratégie en DEUX requêtes ===\n";
echo "  (préfixe d'abord ; infixe seulement si le préfixe ne donne rien)\n\n";
foreach (['Marque 07', 'Format 03', 'zzzintrouvable', 'Marque 07 Format 03'] as $t) {
    [$nPrefix, $tPrefix] = bench($p, "  préfixe '{$t}%'", sprintf($countSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["{$t}%", "{$t}%"]);
    if ($nPrefix === 0) {
        [$nInfix, $tInfix] = bench($p, "  → repli infixe '%{$t}%'", sprintf($countSql, "(p.nom LIKE ? OR p.code_barres LIKE ?)"), ["%{$t}%", "%{$t}%"]);
        printf("  terme « %s » : préfixe VIDE → repli infixe\n\n", $t);
    } else {
        printf("  terme « %s » : préfixe OK (%d résultats) → pas de repli\n\n", $t, $nPrefix);
    }
}

echo "\n=== D. Le plan d'exécution : FILESORT ou pas ? ===\n";
echo "  (c'est là que se joue le gain du préfixe : si MySQL peut se\n";
echo "   servir de l'index (shop_id, nom) pour le FILTRE *et* le TRI,\n";
echo "   il n'a pas de fichiersort.)\n\n";

$plans = [
    'préfixe seul'  => "(p.nom LIKE ? OR p.code_barres LIKE ?)",
    'HYBRIDE'       => "(p.nom LIKE ? OR p.code_barres LIKE ? OR p.nom LIKE ? OR p.code_barres LIKE ?)",
];
foreach (['Marque 07', 'Format 03'] as $t) {
    echo "  terme « {$t} »\n";
    foreach ($plans as $nom => $cond) {
        $n = substr_count($cond, '?');
        $params = [];
        for ($i = 0; $i < $n; $i++) {
            $params[] = ($i % 2 === 0) ? $t . '%' : '%' . $t . '%';
        }
        $sql = "EXPLAIN SELECT p.id FROM produits p WHERE p.shop_id = 1 AND {$cond} ORDER BY p.nom LIMIT 50";
        $st = $p->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        $extra = $row['Extra'] ?? '';
        $fs = str_contains($extra, 'filesort') ? 'FILESORT' : 'PAS de filesort';
        printf("    %-14s : key=%-18s rows=%-7s %s  (%s)\n",
            $nom, $row['key'] ?? '?', $row['rows'] ?? '?', $fs, $extra);
    }
}

/*
 * ===================================================================
 * E. A/B : l'index de PREFIXE (nom(20)) change-t-il tout ?
 *
 * ⚠️ Mesure à 50 000 : `EXPLAIN` montre `rows=24875` pour le préfixe,
 * c'est-à-dire un SCAN COMPLET de l'index (shop_id, nom) et non une
 * recherche par intervalle. Donc le préfixe ne profitait PAS de
 * l'index...
 *
 * ... sauf si un index dédié sur nom(20) existe. C'est ce que mesure
 * ce bloc, sur la requête RÉELLE de la page (avec jointures).
 * ===================================================================
 */
echo "\n=== E. A/B : index de préfixe nom(20) ===\n\n";

$requetePage = "SELECT p.*, c.category AS categorie, t.taux AS tax_rate, s.nom AS shop_name
                FROM produits p
                INNER JOIN categories c ON p.category_id = c.id
                LEFT JOIN taxes t ON p.taxe_id = t.id
                LEFT JOIN shops s ON p.shop_id = s.id
                WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1)
                  AND (p.nom LIKE ? OR p.code_barres LIKE ?)
                ORDER BY p.nom ASC LIMIT 50";

$requeteCompte = "SELECT COUNT(*) AS c FROM produits p
                  INNER JOIN categories c ON p.category_id = c.id
                  LEFT JOIN shops s ON p.shop_id = s.id
                  WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1)
                    AND (p.nom LIKE ? OR p.code_barres LIKE ?)";

echo "  SANS index de préfixe\n";
bench($p, "    page  LIKE 'Marque 07%'", $requetePage, ['Marque 07%', 'Marque 07%']);
bench($p, "    count LIKE 'Marque 07%'", $requeteCompte, ['Marque 07%', 'Marque 07%']);
bench($p, "    page  LIKE '%Marque 07%'", $requetePage, ['%Marque 07%', '%Marque 07%']);

echo "\n  Ajout de l'index nom(20)...\n";
$p->exec('CREATE INDEX idx_prod_nom_prefix ON produits (nom(20))');
$p->query('ANALYZE TABLE produits')->fetchAll();

echo "  AVEC index de préfixe\n";
bench($p, "    page  LIKE 'Marque 07%'", $requetePage, ['Marque 07%', 'Marque 07%']);
bench($p, "    count LIKE 'Marque 07%'", $requeteCompte, ['Marque 07%', 'Marque 07%']);
bench($p, "    page  LIKE '%Marque 07%'", $requetePage, ['%Marque 07%', '%Marque 07%']);

echo "\n  Le plan d'exécution AVEC l'index :\n";
foreach (['Marque 07%', '%Marque 07%'] as $pat) {
    $sql = "EXPLAIN SELECT p.id FROM produits p
            WHERE p.shop_id = 1 AND (p.nom LIKE ? OR p.code_barres LIKE ?)
            ORDER BY p.nom LIMIT 50";
    $st = $p->prepare($sql);
    $st->execute([$pat, $pat]);
    $row = $st->fetch();
    printf("    %-14s : type=%-8s key=%-20s rows=%-7s Extra=%s\n",
        $pat, $row['type'] ?? '?', $row['key'] ?? '?', $row['rows'] ?? '?', $row['Extra'] ?? '');
}

echo "\n  === E2. Quelle clause casse l'index ? ===\n\n";

$jointures = "FROM produits p
              INNER JOIN categories c ON p.category_id = c.id
              LEFT JOIN taxes t ON p.taxe_id = t.id
              LEFT JOIN shops s ON p.shop_id = s.id
              WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1)";

$variantes = [
    "nom LIKE 'X%' SEUL"                 => ["(p.nom LIKE ?)",                    ['Marque 07%']],
    "nom LIKE 'X%' OR code LIKE 'X%'"    => ["(p.nom LIKE ? OR p.code_barres LIKE ?)", ['Marque 07%', 'Marque 07%']],
    "code_barres = 'X' (exact)"          => ["(p.code_barres = ?)",               ['Marque 07']],
    "nom LIKE 'X%' OR code = 'X'"        => ["(p.nom LIKE ? OR p.code_barres = ?)", ['Marque 07%', 'Marque 07']],
];

foreach ($variantes as $nom => [$cond, $params]) {
    $sql = "SELECT p.id {$jointures} AND {$cond} ORDER BY p.nom LIMIT 50";
    $st = $p->prepare($sql);
    $t0 = microtime(true);
    $st->execute($params);
    $rows = $st->fetchAll();
    $t = (microtime(true) - $t0) * 1000;

    $exSt = $p->prepare("EXPLAIN " . $sql);
    $exSt->execute($params);
    $ex = $exSt->fetch();
    printf("    %-30s %7.1f ms  rows=%-6s type=%-8s key=%-20s %s\n",
        $nom, $t, count($rows), $ex['type'] ?? '?', $ex['key'] ?? '?', $ex['Extra'] ?? '');
}

echo "\n  === E3. La stratégie EN DEUX REQUÊTES (recommandée) ===\n\n";
echo "  Requête 1 : nom LIKE 'X%'  (indexable, rapide)\n";
echo "  Requête 2 : code_barres LIKE 'X%'  (seulement si besoin)\n\n";

$debut = microtime(true);
$r1 = $p->query("SELECT p.id {$jointures} AND (p.nom LIKE 'Marque 07%') ORDER BY p.nom LIMIT 50")->fetchAll();
$t1 = (microtime(true) - $debut) * 1000;
printf("    req 1 (nom préfixe)        : %7.1f ms  (%d lignes)\n", $t1, count($r1));

$debut = microtime(true);
$r2 = $p->query("SELECT p.id {$jointures} AND (p.code_barres LIKE 'Marque 07%') ORDER BY p.nom LIMIT 50")->fetchAll();
$t2 = (microtime(true) - $debut) * 1000;
printf("    req 2 (code-barres)        : %7.1f ms  (%d lignes)\n", $t2, count($r2));

$debut = microtime(true);
$r3 = $p->query("SELECT p.id {$jointures} AND (p.code_barres LIKE '%Marque 07%') ORDER BY p.nom LIMIT 50")->fetchAll();
$t3 = (microtime(true) - $debut) * 1000;
printf("    req 2' (code-barres infixe): %7.1f ms  (%d lignes)\n", $t3, count($r3));

echo "\n  Nettoyage ===\n";
$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
echo "    base jetable " . DB . " supprimée.\n";
