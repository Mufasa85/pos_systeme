<?php
/**
 * MESURE DE BOUT EN BOUT : le gain réel de la recherche en deux temps
 * sur la VRAIE page /produits, à 50 000 produits.
 *
 * Compare :
 *   - l'ANCIEN chemin (`OR code_barres` dans la même requête) ;
 *   - le NOUVEAU chemin (préfixe indexé, repli infixe, code-barres à part).
 *
 * Base : pos_perf_final2 — créée puis supprimée.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';
const DB   = 'pos_perf_final2';

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

echo "=== Préparation : {$N} produits ===\n";
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

$p->exec('SET SESSION cte_max_recursion_depth = 100000');
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
$p->exec('CREATE TEMPORARY TABLE tmp_agg (product_id INT PRIMARY KEY, total_stock FLOAT, avail_stock FLOAT, near_exp DATE)');
$p->exec("INSERT INTO tmp_agg SELECT product_id, SUM(stock), SUM(CASE WHEN date_expiration IS NULL OR date_expiration >= CURDATE() THEN stock ELSE 0 END), MIN(CASE WHEN date_expiration IS NOT NULL AND stock > 0 AND date_expiration >= CURDATE() THEN date_expiration END) FROM product_batches GROUP BY product_id");
$p->exec('UPDATE produits p LEFT JOIN tmp_agg a ON a.product_id = p.id SET p.stock = COALESCE(a.total_stock,0), p.available_stock = COALESCE(a.avail_stock,0), p.nearest_expiration_date = a.near_exp');
$p->exec('DROP TEMPORARY TABLE tmp_agg');
$p->query('ANALYZE TABLE produits, product_batches')->fetchAll();
printf("    %d produits, %d lots, prêts\n\n", $N, $batchCount);

$select = "SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
                   p.stock AS total_stock, p.available_stock, p.nearest_expiration_date
            FROM produits p
            INNER JOIN categories c ON p.category_id = c.id
            LEFT JOIN taxes t ON p.taxe_id = t.id
            LEFT JOIN shops s ON p.shop_id = s.id
            WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1) AND %s
            ORDER BY p.nom ASC LIMIT 50";

$count = "SELECT COUNT(*) AS c FROM produits p
          INNER JOIN categories c ON p.category_id = c.id
          LEFT JOIN shops s ON p.shop_id = s.id
          WHERE p.shop_id = 1 AND (s.id IS NULL OR s.actif = 1) AND %s";

function timed(PDO $p, string $label, string $sql, array $params, int $runs = 5): float
{
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $s = $p->prepare($sql);
        $t0 = microtime(true);
        $s->execute($params);
        $s->fetchAll();
        $times[] = (microtime(true) - $t0) * 1000;
    }
    sort($times);
    printf("  %-56s %8.1f ms\n", $label, $times[(int) (count($times) / 2)]);
    return $times[(int) (count($times) / 2)];
}

echo "=== 1. ANCIEN : nom OR code-barres dans la MÊME requête ===\n";
$ancienCond = "(p.nom LIKE ? OR p.code_barres LIKE ?)";
$tAncienPage = timed($p, "page : LIKE '%terme%'", sprintf($select, $ancienCond), ['%Marque 07%', '%Marque 07%']);
$tAncienCnt  = timed($p, "count : LIKE '%terme%'", sprintf($count, $ancienCond), ['%Marque 07%', '%Marque 07%']);

echo "\n=== 2. NOUVEAU : préfixe indexé ===\n";
$prefixCond = "(p.nom LIKE ?)";
$tNewPage = timed($p, "page : LIKE 'terme%' (indexable)", sprintf($select, $prefixCond), ['Marque 07%']);
$tNewCnt  = timed($p, "count : LIKE 'terme%' (indexable)", sprintf($count, $prefixCond), ['Marque 07%']);

echo "\n=== 3. Le chemin code-barres (index UNIQUE) ===\n";
$bcCond = "(p.code_barres = ?)";
timed($p, "page : code_barres = 'terme' (exact)", sprintf($select, $bcCond), ['Marque 07']);

echo "\n=== 4. Le repli infixe (le pire cas) ===\n";
$tFallback = timed($p, "page : LIKE '%terme%' (repli, scan)", sprintf($select, "(p.nom LIKE ?)"), ['%Marque 07%']);

echo "\n=== SYNTHÈSE ===\n";
printf("  Recherche par frappe (cas courant, préfixe) :\n");
printf("    ANCIEN  %7.1f ms (page) + %6.1f ms (count) = %7.1f ms\n",
    $tAncienPage, $tAncienCnt, $tAncienPage + $tAncienCnt);
printf("    NOUVEAU %7.1f ms (page) + %6.1f ms (count) = %7.1f ms\n",
    $tNewPage, $tNewCnt, $tNewPage + $tNewCnt);
printf("    → GAIN : %.0f×\n\n",
    ($tAncienPage + $tAncienCnt) / max(0.1, $tNewPage + $tNewCnt));

printf("  Pire cas (terme au milieu, repli infixe) : %.1f ms (inchangé : c'est un scan)\n", $tFallback);

echo "\n=== Nettoyage ===\n";
$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
echo "    base jetable " . DB . " supprimée.\n";
