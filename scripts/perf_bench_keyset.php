<?php
/**
 * KEYSET PAGINATION pour la page /produits/lots.
 *
 * Problème : OFFSET 49950 sur 85 000 lots oblige MySQL à produire puis
 * JETER 49 950 lignes (mesuré : 184 ms à la page 1000).
 *
 * Alternative "keyset" : on memorise le dernier couple
 * (date_expiration, id) vu et on demande la suite :
 *     WHERE (date_expiration, id) > (:last_exp, :last_id)
 * Le coût reste CONSTANT quelle que soit la page.
 *
 * Base : pos_perf_dec — réutilisée.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';

$p = new PDO(DSN . ';dbname=pos_perf_dec', USER, PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

function bench(PDO $p, string $label, string $sql, array $params = [], int $runs = 5): array
{
    $times = [];
    $rows  = [];
    for ($i = 0; $i < $runs; $i++) {
        $s  = $p->prepare($sql);
        $t0 = microtime(true);
        $s->execute($params);
        $res = $s->fetchAll();
        $times[] = (microtime(true) - $t0) * 1000;
        $rows = $res;
    }
    sort($times);
    printf("  %-50s %8.1f ms  (%d)\n", $label, $times[(int) (count($times) / 2)], count($rows));
    return [$rows, $times[(int) (count($times) / 2)]];
}

echo "=== 1. Recherche des bornes de la page 1000 ===\n";
// On prend le couple (date_expiration, id) de la 50 000e ligne.
$bounds = $p->query("SELECT date_expiration, id FROM product_batches
                      WHERE stock > 0 ORDER BY date_expiration ASC, id ASC
                      LIMIT 1 OFFSET 49950")->fetch();
printf("  borne page 1000 : date_expiration=%s id=%d\n", $bounds['date_expiration'], $bounds['id']);

echo "\n=== 2. OFFSET vs KEYSET ===\n";
$withJoin = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                    p.nom AS product_name, p.code_barres
             FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
             WHERE p.shop_id = 1 AND b.stock > 0
             ORDER BY b.date_expiration ASC, b.id ASC";

bench($p, 'OFFSET 49950 (page 1000) — JOIN produits', $withJoin . ' LIMIT 50 OFFSET 49950');

$keyset = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                  p.nom AS product_name, p.code_barres
           FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
           WHERE p.shop_id = 1 AND b.stock > 0
             AND (b.date_expiration > :exp1 OR (b.date_expiration = :exp2 AND b.id > :id))
           ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50";
bench($p, 'KEYSET (page 1000) — JOIN produits', $keyset,
    [':exp1' => $bounds['date_expiration'], ':exp2' => $bounds['date_expiration'], ':id' => $bounds['id']]);

echo "\n=== 3. Le KEYSET avec shop_id dénormalisé (le VRAI candidat) ===\n";
$p->exec("ALTER TABLE product_batches ADD COLUMN shop_id int NULL");
$p->exec("UPDATE product_batches b JOIN produits p ON p.id = b.product_id SET b.shop_id = p.shop_id");
$p->exec("CREATE INDEX idx_batches_shop_exp ON product_batches (shop_id, stock, date_expiration, id)");
$p->query("ANALYZE TABLE product_batches")->fetchAll();

$keysetDirect = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                        p.nom AS product_name, p.code_barres
                 FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
                 WHERE b.shop_id = 1 AND b.stock > 0
                   AND (b.date_expiration > :exp1 OR (b.date_expiration = :exp2 AND b.id > :id))
                 ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50";
bench($p, 'KEYSET + shop_id dénormalisé (page 1000)', $keysetDirect,
    [':exp1' => $bounds['date_expiration'], ':exp2' => $bounds['date_expiration'], ':id' => $bounds['id']]);

$offsetDirect = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                         p.nom AS product_name, p.code_barres
                  FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
                  WHERE b.shop_id = 1 AND b.stock > 0
                  ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50 OFFSET 49950";
bench($p, 'OFFSET + shop_id dénormalisé (page 1000)', $offsetDirect);

echo "\n=== 4. La PREMIERE page (pas de borne) ===\n";
$first = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration,
                 p.nom AS product_name, p.code_barres
          FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
          WHERE b.shop_id = 1 AND b.stock > 0
          ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50";
bench($p, 'première page (shop_id dénormalisé)', $first);

echo "\n=== 5. Le COST CONSTANT du keyset ===\n";
foreach ([0, 100, 500, 1000, 1500] as $page) {
    $off = $page * 50;
    $b   = $p->query("SELECT date_expiration, id FROM product_batches
                      WHERE stock > 0 ORDER BY date_expiration ASC, id ASC
                      LIMIT 1 OFFSET $off")->fetch();
    if (!$b) {
        continue;
    }
    $s = $p->prepare($keysetDirect);
    $t0 = microtime(true);
    $s->execute([':exp1' => $b['date_expiration'], ':exp2' => $b['date_expiration'], ':id' => $b['id']]);
    $s->fetchAll();
    $t = (microtime(true) - $t0) * 1000;

    $s2 = $p->prepare($offsetDirect);
    $t0 = microtime(true);
    $s2->execute();
    $s2->fetchAll();
    $t2 = (microtime(true) - $t0) * 1000;

    printf("  page %5d : KEYSET %6.1f ms  |  OFFSET %7.1f ms  →  %.0f×\n",
        $page + 1, $t, $t2, $t2 / max(0.01, $t));
}

echo "\n=== 6. Un filtre par produit (le cas d'usage réel : lots d'UN produit) ===\n";
bench($p, 'lots d\'UN produit (index product_id)', "SELECT b.*, p.nom AS product_name
    FROM product_batches b JOIN produits p ON p.id = b.product_id
    WHERE b.product_id = 12345 ORDER BY b.date_expiration ASC LIMIT 50");

echo "\n=== Nettoyage ===\n";
$p->exec("DROP INDEX idx_batches_shop_exp ON product_batches");
$p->exec("ALTER TABLE product_batches DROP COLUMN shop_id");
$p->query("ANALYZE TABLE product_batches")->fetchAll();
echo "  ok\n";
