<?php
/**
 * MESURE DE LA MIGRATION R3 : dé-normaliser `shop_id` dans product_batches.
 *
 * Question : quel index faut-il, et quel est le coût réel de la page
 * /produits/lots (liste des 85 000 lots triés par péremption) ?
 *
 * Comparaison mesurée :
 *   A. JOINTURE (actuel)      : WHERE p.shop_id = 1, ORDER BY b.date_expiration
 *   B. shop_id dé-normalisé   : WHERE b.shop_id = 1, ORDER BY b.date_expiration
 *
 * Et pour chaque variante, le plan d'exécution (type / key / rows / Extra)
 * — car un « 1,5 ms » obtenu par un scan complet n'est pas un index.
 *
 * Base : pos_perf_r3 — créée puis supprimée.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';
const DB   = 'pos_perf_r3';

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
  PRIMARY KEY (id), KEY product_id (product_id), KEY date_expiration (date_expiration),
  CONSTRAINT fk_pb_product FOREIGN KEY (product_id) REFERENCES produits (id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL);

$p->exec('SET SESSION cte_max_recursion_depth = 100000');
$p->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Boissons',1),(2,'Alimentaire',1),(3,'Hygiène',1),(4,'Divers',1),(5,'Frais',1),(6,'Snacks',1),(7,'Épicerie',1),(8,'Boissons gazeuses',1),(9,'Boulangerie',1)");
$p->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A'),(2,10,'B','Groupe B'),(3,18,'C','Groupe C')");
$p->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique Test','BT',1),(2,'Boutique 2','B2',1),(3,'Boutique 3','B3',1),(4,'Boutique 4','B4',1),(5,'Boutique 5','B5',1)");
$p->exec(<<<SQL
INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < {$N})
SELECT CONCAT('BC-', LPAD(n, 8, '0')),
       CONCAT('Marque ', LPAD(((n - 1) % 49) + 1, 2, '0'),
              ' Format ', LPAD(((n - 1) % 12) + 1, 2, '0'),
              ' Lot ', LPAD(((n - 1) % 1000) + 1, 4, '0')),
       ((n - 1) % 9) + 1, ((n - 1) % 5) + 1, ROUND(500 + (n % 100000) / 100, 2), 50, 5, NULL, ((n - 1) % 3) + 1
FROM seq
SQL);

$batchCount = (int) round($N * 1.7);
$p->exec(<<<SQL
INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < {$batchCount})
SELECT ((n - 1) % {$N}) + 1, CONCAT('LOT-', n), 10 + (n % 40),
       DATE_ADD(CURDATE(), INTERVAL (n % 400) DAY), CURDATE() FROM seq
SQL);

// ⚠️ Un produit SANS boutique (shop_id NULL) — le cas réel (1 produit
// dans pos_system). Sa colonne shop_id dé-normalisée doit rester NULLABLE.
$p->exec("INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
          VALUES ('BC-SANS-BOUTIQUE', 'Produit sans boutique', 1, NULL, 500, 10, 2, NULL, 1)");

$p->query('ANALYZE TABLE produits, product_batches')->fetchAll();
printf("    %d produits, %d lots, prêts\n\n", $N, $batchCount);

function bench(PDO $p, string $label, string $sql, array $params = [], int $runs = 5): float
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
    printf("  %-52s %8.1f ms  (%d lignes)\n", $label, $times[(int) (count($times) / 2)], $rows);
    return $times[(int) (count($times) / 2)];
}

function explain(PDO $p, string $label, string $sql, array $params = []): void
{
    $s = $p->prepare('EXPLAIN ' . $sql);
    $s->execute($params);
    $r = $s->fetch();
    printf("    %-30s type=%-8s key=%-22s rows=%-7s Extra=%s\n",
        $label, $r['type'] ?? '?', $r['key'] ?? '?', $r['rows'] ?? '?', $r['Extra'] ?? '');
}

/**
 * Ajoute une variante de l'EXPLAIN : quand la requete a un JOIN sur
 * produits, on veut aussi le plan de la table product_batches seule.
 */
function explainBatches(PDO $p, string $label, string $sql, array $params = []): void
{
    $s = $p->prepare('EXPLAIN ' . $sql);
    $s->execute($params);
    $rows = $s->fetchAll();
    foreach ($rows as $r) {
        if (($r['table'] ?? '') === 'b') {
            printf("    %-30s type=%-8s key=%-22s rows=%-7s Extra=%s\n",
                $label, $r['type'] ?? '?', $r['key'] ?? '?', $r['rows'] ?? '?', $r['Extra'] ?? '');
            return;
        }
    }
}

$select = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, b.date_reception,
                   p.nom AS product_name, p.code_barres
            FROM product_batches b
            INNER JOIN produits p ON p.id = b.product_id
            WHERE %s
            ORDER BY b.date_expiration ASC, b.id ASC
            LIMIT 50";

echo "=== A. AVANT : la jointure actuelle ===\n\n";
$avant = "(p.shop_id = 1 AND b.stock > 0)";
$tAvant = bench($p, 'page 1 : JOIN produits, ORDER BY date_expiration', sprintf($select, $avant));
explain($p, 'plan', sprintf($select, $avant));

echo "\n  Pagination profonde (OFFSET 49950) :\n";
$avantOffset = sprintf(str_replace('LIMIT 50', 'LIMIT 50 OFFSET 49950', $select), $avant);
bench($p, 'page 1000 : OFFSET 49950', $avantOffset);

echo "\n\n=== B. La MIGRATION : ajouter shop_id + l'index ===\n\n";
$t0 = microtime(true);
$p->exec('ALTER TABLE product_batches ADD COLUMN shop_id int NULL DEFAULT NULL');
$tAlter = (microtime(true) - $t0) * 1000;
printf("  ALTER TABLE ADD COLUMN : %.1f ms\n", $tAlter);

$t0 = microtime(true);
$p->exec('UPDATE product_batches b JOIN produits p ON p.id = b.product_id SET b.shop_id = p.shop_id');
$tBackfill = (microtime(true) - $t0) * 1000;
printf("  Backfill (UPDATE ... JOIN) : %.1f ms\n", $tBackfill);

$t0 = microtime(true);
$p->exec('CREATE INDEX idx_batches_shop_exp ON product_batches (shop_id, stock, date_expiration, id)');
$tIndex = (microtime(true) - $t0) * 1000;
printf("  CREATE INDEX : %.1f ms\n", $tIndex);
$p->query('ANALYZE TABLE product_batches')->fetchAll();

echo "\n  Contrôle du backfill :\n";
$nulls = (int) $p->query('SELECT COUNT(*) FROM product_batches WHERE shop_id IS NULL')->fetchColumn();
$total = (int) $p->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
$orphelins = (int) $p->query(
    'SELECT COUNT(*) FROM product_batches b LEFT JOIN produits p ON p.id = b.product_id WHERE p.id IS NULL'
)->fetchColumn();
printf("    lots totaux           : %d\n", $total);
printf("    lots shop_id NULL     : %d  (attendu : 0, sauf produits sans boutique)\n", $nulls);
printf("    lots orphelins        : %d  (attendu : 0)\n", $orphelins);

echo "\n\n=== C. APRÈS : shop_id dé-normalisé ===\n\n";
$apres = "(b.shop_id = 1 AND b.stock > 0)";
$tApres = bench($p, 'page 1 : shop_id dé-normalisé', sprintf($select, $apres));
explain($p, 'plan', sprintf($select, $apres));

echo "\n  Pagination profonde (OFFSET 49950) :\n";
$apresOffset = sprintf(str_replace('LIMIT 50', 'LIMIT 50 OFFSET 49950', $select), $apres);
bench($p, 'page 1000 : OFFSET 49950', $apresOffset);

echo "\n\n=== D. Quel index est optimal ? ===\n\n";

$sansStockTmp = '(b.shop_id = 1)';
$variantes = [
    'idx A : (shop_id, stock, date_exp, id)'   => ['idx_batches_a', 'shop_id, stock, date_expiration, id'],
    'idx B : (shop_id, date_exp, id)'          => ['idx_batches_b', 'shop_id, date_expiration, id'],
    'idx C : (shop_id, date_exp, id, stock)'   => ['idx_batches_c', 'shop_id, date_expiration, id, stock'],
    'idx D : (date_exp, shop_id, id)'          => ['idx_batches_d', 'date_expiration, shop_id, id'],
    'idx E : (shop_id, date_exp) seul'         => ['idx_batches_e', 'shop_id, date_expiration'],
];

// Le KEYSET (R4) : on memorise le dernier (date_expiration, id) et on
// demande la suite. C'est ce qui doit rester CONSTANT quelle que soit
// la page.
$keysetSql = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                     p.nom AS product_name, p.code_barres
              FROM product_batches b
              INNER JOIN produits p ON p.id = b.product_id
              WHERE b.shop_id = 1 AND b.stock > 0
                AND (b.date_expiration > :exp1 OR (b.date_expiration = :exp2 AND b.id > :id))
              ORDER BY b.date_expiration ASC, b.id ASC
              LIMIT 50";

$borne = $p->query("SELECT date_expiration, id FROM product_batches
                    WHERE shop_id = 1 AND stock > 0
                    ORDER BY date_expiration ASC, id ASC
                    LIMIT 1 OFFSET 10000")->fetch();

foreach ($variantes as $nom => [$nomIdx, $cols]) {
    echo "  --- {$nom} ---\n";
    // On repart d'un etat propre : on supprime les index de test.
    foreach (['idx_batches_a', 'idx_batches_b', 'idx_batches_c', 'idx_batches_d', 'idx_batches_e'] as $oldIdx) {
        try {
            $p->exec("DROP INDEX {$oldIdx} ON product_batches");
        } catch (\Throwable $e) {
            // L'index n'existe pas : c'est normal au premier passage.
        }
    }
    $p->exec("CREATE INDEX {$nomIdx} ON product_batches ({$cols})");
    $p->query('ANALYZE TABLE product_batches')->fetchAll();
    bench($p, '    page 1 (avec filtre stock)', sprintf($select, $apres));
    explain($p, '    plan', sprintf($select, $apres));
    if ($borne) {
        bench($p, '    KEYSET page 200 (R4)',
            $keysetSql,
            [':exp1' => $borne['date_expiration'], ':exp2' => $borne['date_expiration'], ':id' => $borne['id']]);
        explainBatches($p, '    plan (table b)', $keysetSql,
            [':exp1' => $borne['date_expiration'], ':exp2' => $borne['date_expiration'], ':id' => $borne['id']]);
    }
}

echo "\n\n=== E. La requete SANS le filtre stock (l'index peut alors servir) ===\n\n";
$sansStock = '(b.shop_id = 1)';
bench($p, 'page 1 sans filtre stock', sprintf($select, $sansStock));
explain($p, 'plan', sprintf($select, $sansStock));

echo "\n  (les plans ci-dessus couvrent deja les variantes avec et sans filtre stock)\n";

echo "\n\n=== F. La requête RÉELLE de la page des lots ===\n\n";
echo "  (les 8 colonnes affichées + la jointure pour le nom du produit)\n\n";

$pageReelle = "SELECT b.id, b.product_id, b.batch_number, b.stock,
                       b.date_expiration, b.date_reception,
                       p.nom AS product_name, p.code_barres
                FROM product_batches b
                INNER JOIN produits p ON p.id = b.product_id
                WHERE %s
                ORDER BY b.date_expiration ASC, b.id ASC
                LIMIT 50";

// On repart de l'index choisi : (shop_id, date_expiration, id).
$p->exec('DROP INDEX idx_batches_shop_exp ON product_batches');
$p->exec('CREATE INDEX idx_batches_shop_exp ON product_batches (shop_id, date_expiration, id)');
$p->query('ANALYZE TABLE product_batches')->fetchAll();

$tReelleApres = bench($p, 'page 1 (shop_id dé-normalisé)', sprintf($pageReelle, $apres));
explainBatches($p, 'plan (table b)', sprintf($pageReelle, $apres));

echo "\n  Et la version AVANT (avec la jointure qui fait le filtre) :\n";
$tReelleAvant = bench($p, 'page 1 (jointure, ancien chemin)', sprintf($pageReelle, $avant));

printf("\n  → GAIN BOUT EN BOUT : %.1f ms → %.1f ms  (×%.0f)\n",
    $tReelleAvant, $tReelleApres, $tReelleAvant / max(0.1, $tReelleApres));

echo "\n\n=== SYNTHÈSE ===\n";
printf("  Page 1        : %7.1f ms → %6.1f ms   (×%.0f)\n", $tAvant, $tApres, $tAvant / max(0.1, $tApres));
printf("  Migration     : %7.1f ms (ALTER + backfill + index)\n", $tAlter + $tBackfill + $tIndex);

echo "\n\n=== Nettoyage ===\n";
$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
echo "    base jetable " . DB . " supprimée.\n";
