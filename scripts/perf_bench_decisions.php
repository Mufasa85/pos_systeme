<?php
/**
 * Benchmark DÉCISIONNEL pour les étapes restantes (P4 et P6).
 *
 * Mesure, sur 50 000 produits / 85 000 lots :
 *   A. RECHERCHE : LIKE '%terme%' vs LIKE 'terme%' vs FULLTEXT, et l'effet
 *      de la longueur du terme (ft_min_word_len = 4 par défaut).
 *   B. TRI : ORDER BY nom (indexé) vs prix vs stock, avec LIMIT.
 *   C. PAGE LOTS : listage des 85 000 lots par péremption, paginé.
 *
 * Base jetable `pos_perf_dec` : créée puis supprimée.
 * Ne touche JAMAIS `pos_system` ni `pos_systeme_test`.
 *
 * Usage : php scripts/perf_bench_decisions.php [N]
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';
const DB   = 'pos_perf_dec';

function pdo(?string $db = null): PDO
{
    $dsn = $db ? DSN . ';dbname=' . $db : DSN;
    return new PDO($dsn, USER, PASS, [
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES         => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
}

$N = (int) ($argv[1] ?? 50000);
// Si PERF_OUT est defini, toute la sortie y est ecrite (le shell
// redirige mal vers un chemin contenant des espaces).
$OUT = (string) (getenv('PERF_OUT') ?: '');
if ($OUT !== '') {
    @unlink($OUT);
    ob_start(function ($b) use ($OUT) { if ($b !== '') { @file_put_contents($OUT, $b, FILE_APPEND); } return ''; });
}

echo "=== Préparation : {$N} produits, lots, index + dé-normalisation ===\n";
$REUSE = (string) (getenv('PERF_REUSE') ?: '');
$p = pdo();
if ($REUSE !== '') {
    echo "    base existante réutilisée : {$REUSE}\n";
    $p = pdo($REUSE);
} else {
    $p->exec('DROP DATABASE IF EXISTS ' . DB);
    $p->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    $p = pdo(DB);
}

if ($REUSE === '') {
$p->exec('CREATE TABLE categories (id int NOT NULL AUTO_INCREMENT, category varchar(120) NOT NULL, shop_id int DEFAULT NULL, PRIMARY KEY (id), KEY idx_cat_shop (shop_id)) ENGINE=InnoDB');
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

$p->exec('SET SESSION cte_max_recursion_depth = 100000');
$p->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Boissons',1),(2,'Alimentaire',1),(3,'Hygiène',1),(4,'Divers',1),(5,'Frais',1),(6,'Snacks',1),(7,'Épicerie',1),(8,'Boissons gazeuses',1),(9,'Boulangerie',1)");
$p->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A'),(2,10,'B','Groupe B'),(3,18,'C','Groupe C')");
$p->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique Test','BT',1)");

// Noms réalistes : marque + désignation. C'est ce que le caissier tape.
$marques = ['Coca', 'Pepsi', 'Sprite', 'Fanta', 'Orangina', 'Biere', 'Eau', 'Lait', 'Danone', 'Nestle', 'Yoplait', 'Président', 'Camembert', 'Emmental', 'Baguette', 'Pain', 'Farine', 'Sucre', 'Sel', 'Huile', 'Riz', 'Pates', 'Tomate', 'Pesto', 'Sauce', 'Ketchup', 'Mayonnaise', 'Moutarde', 'Chocolat', 'Biscuit', 'Cereale', 'Yaourt', 'Fromage', 'Jambon', 'Saumon', 'Thon', 'Crevette', 'Poulet', 'Boeuf', 'Veau', 'Agneau', 'Porc', 'Riz', 'Semoule', 'Haricot', 'Lentille', 'Chocolat', 'Vanille', 'Caramel', 'Miel', 'Confiture'];
$p->exec(<<<SQL
INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < {$N})
SELECT CONCAT('BC-', LPAD(n, 8, '0')),
       CONCAT(ELT(((n - 1) % 49) + 1, 'Coca','Pepsi','Sprite','Fanta','Orangina','Biere','Eau','Lait','Danone','Nestle','Yoplait','President','Camembert','Emmental','Baguette','Pain','Farine','Sucre','Sel','Huile','Riz','Pates','Tomate','Pesto','Sauce','Ketchup','Mayonnaise','Moutarde','Chocolat','Biscuit','Cereale','Yaourt','Fromage','Jambon','Saumon','Thon','Crevette','Poulet','Boeuf','Veau','Agneau','Porc','Semoule','Haricot','Lentille','Vanille','Caramel','Miel','Confiture'),
              ' ', ELT(((n - 1) % 12) + 1, '33cl','1L','500g','250g','Pack','Dose','Bouteille','Canette','Brique','Sachet','Barquette','Piece'),
              ' - Lot ', LPAD(((n - 1) % 1000) + 1, 4, '0')),
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

/*
 * ⚠️ Backfill en SQL "set-based" et NON en sous-requetes correlees.
 *
 * La forme correlée (UPDATE ... SET x = (SELECT SUM(...) FROM ...))
 * est QUADRATIQUE : MySQL ré-exécute la sous-requête pour chaque ligne,
 * soit 50 000 × 85 000 lectures. Sur cette machine, un backfill
 * correlé a mis plus de 3 HEURES sans finir et a bloqué toutes les
 * sessions suivantes sur un verrou de métadonnées.
 *
 * On passe donc par une table temporaire pré-agrégée (GROUP BY) puis
 * un simple UPDATE ... JOIN : chaque lot est lu UNE seule fois.
 */
$p->exec(<<<SQL
CREATE TEMPORARY TABLE tmp_agg (
  product_id INT PRIMARY KEY, total_stock FLOAT, avail_stock FLOAT, near_exp DATE
)
SQL);
$p->exec(<<<SQL
INSERT INTO tmp_agg
SELECT product_id,
       SUM(stock),
       SUM(CASE WHEN date_expiration IS NULL OR date_expiration >= CURDATE() THEN stock ELSE 0 END),
       MIN(CASE WHEN date_expiration IS NOT NULL AND stock > 0 AND date_expiration >= CURDATE() THEN date_expiration END)
FROM product_batches
GROUP BY product_id
SQL);
$p->exec(<<<SQL
UPDATE produits p
LEFT JOIN tmp_agg a ON a.product_id = p.id
SET p.stock         = COALESCE(a.total_stock, 0),
    p.available_stock = COALESCE(a.avail_stock, 0),
    p.nearest_expiration_date = a.near_exp
SQL);
$p->exec('DROP TEMPORARY TABLE tmp_agg');
$p->query('ANALYZE TABLE produits, product_batches')->fetchAll();
}
if (!isset($batchCount)) {
    $batchCount = (int) $p->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
}
printf("    %d produits, %d lots, prêts\n\n", $N, $batchCount);


/**
 * Cree un index s'il n'existe pas deja (idempotent).
 * Indispensable en mode REUSE : le script peut etre rejoue sur la meme base.
 */
function addIndex(PDO $p, string $table, string $kind, string $index, string $cols): void
{
    $st = $p->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $st->execute([$table, $index]);
    if ((int) $st->fetchColumn() > 0) {
        printf("    (index %s deja present)\n", $index);
        return;
    }
    // MySQL : "CREATE FULLTEXT INDEX nom ON table (cols)"
    //         "CREATE INDEX nom ON table (cols)"
    $sql = ($kind === 'FULLTEXT')
        ? "CREATE FULLTEXT INDEX {$index} ON {$table} ({$cols})"
        : "CREATE INDEX {$index} ON {$table} ({$cols})";
    $p->exec($sql);
    $p->query("ANALYZE TABLE {$table}")->fetchAll();
    printf("    (index %s cree sur %s)\n", $index, $table);
}

function bench(PDO $p, string $label, string $sql, array $params = [], int $runs = 3): void
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
    printf("    %-52s %8.1f ms   (%d résultats)\n", $label, $times[(int) (count($times) / 2)], $rows);
}

// ══ A. RECHERCHE ═══════════════════════════════════════════════════════════
echo "=== A. RECHERCHE — quel mécanisme à 50 000 produits ? ===\n";

$terms = ['coca', 'cola', 'chocolat', 'camem'];

echo "\n  A1. LIKE '%terme%' (scan complet, actuel)\n";
foreach ($terms as $t) {
    bench($p, "LIKE '%$t%'", "SELECT id FROM produits WHERE nom LIKE ? OR code_barres LIKE ?", ['%' . $t . '%', '%' . $t . '%']);
}

echo "\n  A2. LIKE 'terme%' (préfixe — ce que le caissier tape le plus souvent)\n";
foreach ($terms as $t) {
    bench($p, "LIKE '$t%'", "SELECT id FROM produits WHERE nom LIKE ?", [$t . '%']);
}

echo "\n  A3. Index de PRÉFIXE sur nom(20) — rend le LIKE préfixe indexable\n";
addIndex($p, 'produits', 'INDEX', 'idx_prod_nom_prefix', 'nom(20)');
foreach ($terms as $t) {
    bench($p, "LIKE '$t%' (avec index préfixe)", "SELECT id FROM produits WHERE nom LIKE ?", [$t . '%']);
}
bench($p, "LIKE '%cola%' (conserve le scan)", "SELECT id FROM produits WHERE nom LIKE ?", ['%cola%']);

echo "\n  A4. FULLTEXT (MATCH ... AGAINST en mode booléen)\n";
addIndex($p, 'produits', 'FULLTEXT', 'ft_produits_nom', 'nom, code_barres');
foreach ($terms as $t) {
    bench($p, "MATCH '+$t*' (booléen)", "SELECT id FROM produits WHERE MATCH(nom, code_barres) AGAINST(? IN BOOLEAN MODE)", ['+' . $t . '*']);
}
bench($p, "MATCH 'chocolat' (mode naturel)", "SELECT id FROM produits WHERE MATCH(nom, code_barres) AGAINST(?)", ['chocolat']);

echo "\n  A5. FULLTEXT sur un terme COURT (< 4 caractères → ignoré par MySQL)\n";
bench($p, "MATCH '+biere*' (5 car.)", "SELECT id FROM produits WHERE MATCH(nom, code_barres) AGAINST(? IN BOOLEAN MODE)", ['+biere*']);
bench($p, "MATCH '+bier*' (4 car.)", "SELECT id FROM produits WHERE MATCH(nom, code_barres) AGAINST(? IN BOOLEAN MODE)", ['+bier*']);
bench($p, "MATCH '+ca*' (2 car.) → AUCUN résultat", "SELECT id FROM produits WHERE MATCH(nom, code_barres) AGAINST(? IN BOOLEAN MODE)", ['+ca*']);
bench($p, "LIKE 'ca%' (2 car.) → des résultats", "SELECT id FROM produits WHERE nom LIKE ?", ['ca%']);

echo "\n  A6. Code-barres exact (index UNIQUE) — le chemin du scanner\n";
bench($p, "code_barres = 'BC-00012345'", "SELECT id FROM produits WHERE code_barres = ?", ['BC-00012345']);

echo "\n  A7. La requête COMPLÈTE (ce que fait vraiment la page)\n";
$searchSql = "SELECT p.*, c.category AS categorie FROM produits p INNER JOIN categories c ON p.category_id = c.id LEFT JOIN shops s ON p.shop_id = s.id WHERE (p.shop_id = 1) AND (s.id IS NULL OR s.actif = 1) AND (p.nom LIKE ? OR p.code_barres LIKE ?) ORDER BY p.nom ASC LIMIT 50";
bench($p, "LIKE '%coca%' + LIMIT 50", $searchSql, ['%coca%', '%coca%']);
$searchPrefix = "SELECT p.*, c.category AS categorie FROM produits p INNER JOIN categories c ON p.category_id = c.id LEFT JOIN shops s ON p.shop_id = s.id WHERE (p.shop_id = 1) AND (s.id IS NULL OR s.actif = 1) AND (p.nom LIKE ? OR p.code_barres LIKE ?) ORDER BY p.nom ASC LIMIT 50";
bench($p, "LIKE 'coca%' + LIMIT 50", $searchPrefix, ['coca%', 'coca%']);

// ══ B. TRI ═════════════════════════════════════════════════════════════════
echo "\n\n=== B. TRI — quelle colonne, quel coût ? ===\n";
$sortBase = "SELECT p.id, p.nom, p.prix, p.stock FROM produits p WHERE p.shop_id = 1";
bench($p, "ORDER BY nom (indexé) LIMIT 50", $sortBase . " ORDER BY p.nom ASC LIMIT 50");
bench($p, "ORDER BY nom DESC LIMIT 50", $sortBase . " ORDER BY p.nom DESC LIMIT 50");
bench($p, "ORDER BY prix ASC LIMIT 50", $sortBase . " ORDER BY p.prix ASC LIMIT 50");
bench($p, "ORDER BY prix DESC LIMIT 50", $sortBase . " ORDER BY p.prix DESC LIMIT 50");
bench($p, "ORDER BY stock ASC LIMIT 50", $sortBase . " ORDER BY p.stock ASC LIMIT 50");
bench($p, "ORDER BY stock DESC LIMIT 50", $sortBase . " ORDER BY p.stock DESC LIMIT 50");

echo "\n  B2. Avec un filtre catégorie (la liste est plus petite)\n";
$sortCat = "SELECT p.id, p.nom, p.prix, p.stock FROM produits p INNER JOIN categories c ON p.category_id = c.id WHERE p.shop_id = 1 AND c.category = ?";
bench($p, "cat=Boissons ORDER BY nom LIMIT 50", $sortCat . " ORDER BY p.nom ASC LIMIT 50", ['Boissons']);
bench($p, "cat=Boissons ORDER BY prix LIMIT 50", $sortCat . " ORDER BY p.prix ASC LIMIT 50", ['Boissons']);

echo "\n  B3. Index (shop_id, prix) pour le tri par prix\n";
addIndex($p, 'produits', 'INDEX', 'idx_prod_shop_prix', 'shop_id, prix');
bench($p, "ORDER BY prix ASC (avec index)", $sortBase . " ORDER BY p.prix ASC LIMIT 50");

echo "\n  B4. Pagination profonde avec tri par prix\n";
bench($p, "ORDER BY prix, page 1", $sortBase . " ORDER BY p.prix ASC LIMIT 50 OFFSET 0");
bench($p, "ORDER BY prix, page 1000", $sortBase . " ORDER BY p.prix ASC LIMIT 50 OFFSET 49950");

// ══ C. PAGE LOTS ═══════════════════════════════════════════════════════════
echo "\n\n=== C. PAGE LOTS — lister les 85 000 lots par péremption ===\n";
$lotsSql = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, b.date_reception, p.nom AS product_name, p.code_barres
            FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
            WHERE p.shop_id = 1 AND b.stock > 0
            ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50 OFFSET 0";
bench($p, "lots par péremption, LIMIT 50", $lotsSql);

$lotsSqlPage2 = str_replace('OFFSET 0', 'OFFSET 49950', $lotsSql);
bench($p, "lots par péremption, page 1000", $lotsSqlPage2);

$lotsExpSoon = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, p.nom AS product_name
            FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
            WHERE p.shop_id = 1 AND b.stock > 0 AND b.date_expiration IS NOT NULL
              AND b.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY b.date_expiration ASC LIMIT 50";
bench($p, "lots qui périment sous 30 jours", $lotsExpSoon);

$lotsExpired = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, p.nom AS product_name
            FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
            WHERE p.shop_id = 1 AND b.stock > 0 AND b.date_expiration < CURDATE()
            ORDER BY b.date_expiration ASC LIMIT 50";
bench($p, "lots DÉJÀ périmés", $lotsExpired);

echo "\n  C2. Avec index sur (product_id, date_expiration)\n";
addIndex($p, 'product_batches', 'INDEX', 'idx_batches_prod_exp', 'product_id, date_expiration');
bench($p, "lots par péremption (avec index)", $lotsSql);

echo "\n\n=== Nettoyage ===\n";
if ($REUSE === '') {
    $p = pdo();
    $p->exec('DROP DATABASE IF EXISTS ' . DB);
    echo "    base jetable " . DB . " supprimée.\n";
} else {
    echo "    base réutilisée conservée : {$REUSE}\n";
}
