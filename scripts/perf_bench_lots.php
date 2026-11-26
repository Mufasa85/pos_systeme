<?php
/**
 * Benchmark ciblé pour la PAGE LOTS (P6) et les ALERTES DASHBOARD.
 *
 * La page /produits/lots doit lister les ~85 000 lots triés par péremption.
 * La requête naïve (ORDER BY date_expiration + LIMIT 50) coûte ~334 ms.
 * Ce script teste les variantes d'index qui pourraient corriger ça.
 *
 * Base : pos_perf_dec (50 000 produits / 85 000 lots) — réutilisée.
 */

const DSN   = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
const USER = 'root';
const PASS = 'root';

$p = new PDO(DSN . ';dbname=pos_perf_dec', USER, PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

function bench(PDO $p, string $label, string $sql, array $params = [], int $runs = 5): void
{
    $times = [];
    $rows  = 0;
    for ($i = 0; $i < $runs; $i++) {
        $s  = $p->prepare($sql);
        $t0 = microtime(true);
        $s->execute($params);
        $res = $s->fetchAll();
        $times[] = (microtime(true) - $t0) * 1000;
        $rows = count($res);
    }
    sort($times);
    printf("  %-56s %8.1f ms  (%d)\n", $label, $times[(int) (count($times) / 2)], $rows);
}

function addIndex(PDO $p, string $table, string $kind, string $index, string $cols): void
{
    $st = $p->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $st->execute([$table, $index]);
    if ((int) $st->fetchColumn() > 0) {
        printf("  (index %s deja present)\n", $index);
        return;
    }
    $sql = ($kind === 'FULLTEXT')
        ? "CREATE FULLTEXT INDEX {$index} ON {$table} ({$cols})"
        : "CREATE INDEX {$index} ON {$table} ({$cols})";
    $p->exec($sql);
    $p->query("ANALYZE TABLE {$table}")->fetchAll();
    printf("  (index %s cree)\n", $index);
}

echo "=== 1. PAGE LOTS — la requete actuelle ===\n";
$lots = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, b.date_reception,
                p.nom AS product_name, p.code_barres
         FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
         WHERE p.shop_id = 1 AND b.stock > 0
         ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50";
bench($p, "actuel : ORDER BY date_expiration + JOIN produits", $lots);

echo "\n=== 2. Avec un index sur (date_expiration, id) ===\n";
addIndex($p, 'product_batches', 'INDEX', 'idx_batches_exp', 'date_expiration, id');
bench($p, "ORDER BY date_expiration (index)", $lots);

echo "\n=== 3. Avec un index sur (stock, date_expiration) ===\n";
addIndex($p, 'product_batches', 'INDEX', 'idx_batches_stock_exp', 'stock, date_expiration');
bench($p, "ORDER BY date_expiration (index stock,exp)", $lots);

echo "\n=== 4. Variante : dénormaliser shop_id dans product_batches ===\n";
echo "  (permet de joindre SANS passer par produits)\n";
$p->exec("ALTER TABLE product_batches ADD COLUMN shop_id int NULL");
$p->exec("UPDATE product_batches b JOIN produits p ON p.id = b.product_id SET b.shop_id = p.shop_id");
$p->exec("CREATE INDEX idx_batches_shop_exp ON product_batches (shop_id, date_expiration, id)");
$p->query("ANALYZE TABLE product_batches")->fetchAll();

$lotsDirect = "SELECT b.id, b.product_id, b.batch_number, b.stock, b.date_expiration, b.date_reception,
                      p.nom AS product_name, p.code_barres
               FROM product_batches b INNER JOIN produits p ON p.id = b.product_id
               WHERE b.shop_id = 1 AND b.stock > 0
               ORDER BY b.date_expiration ASC, b.id ASC LIMIT 50";
bench($p, "shop_id dénormalisé + index (shop_id, exp, id)", $lotsDirect);

echo "\n=== 5. Pagination profonde (page 1000) ===\n";
$lotsP1000 = str_replace('LIMIT 50', 'LIMIT 50 OFFSET 49950', $lotsDirect);
bench($p, "page 1000 (avec index)", $lotsP1000);

echo "\n=== 6. ALERTES DASHBOARD — les requêtes NON bornées ===\n";
$soon = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
         FROM product_batches b INNER JOIN produits p ON b.product_id = p.id
         WHERE b.date_expiration IS NOT NULL AND b.date_expiration >= CURDATE()
           AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND b.stock > 0
           AND p.shop_id = 1
         ORDER BY b.date_expiration ASC, p.nom ASC";
bench($p, "getExpiringSoon(7) — AUCUNE limite", $soon);

$expired = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
            FROM product_batches b INNER JOIN produits p ON b.product_id = p.id
            WHERE b.date_expiration IS NOT NULL AND b.date_expiration < CURDATE() AND b.stock > 0
              AND p.shop_id = 1
            ORDER BY b.date_expiration ASC";
bench($p, "getExpired() — AUCUNE limite", $expired);

$nSoon = (int) $p->query("SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id=b.product_id
    WHERE b.date_expiration >= CURDATE() AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND b.stock > 0 AND p.shop_id = 1")->fetchColumn();
$nExp  = (int) $p->query("SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id=b.product_id
    WHERE b.date_expiration < CURDATE() AND b.stock > 0 AND p.shop_id = 1")->fetchColumn();
printf("\n  → le dashboard charge %d lots (sous 7 j) + %d lots (périmés) = %d lignes\n",
    $nSoon, $nExp, $nSoon + $nExp);

echo "\n=== 7. Combien de lignes pour 30 jours ? (l'hypothèse 'pire cas') ===\n";
foreach ([7, 30, 90] as $d) {
    $n = (int) $p->query("SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id=b.product_id
        WHERE b.date_expiration >= CURDATE() AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL $d DAY)
          AND b.stock > 0 AND p.shop_id = 1")->fetchColumn();
    printf("    sous %3d jours : %6d lots\n", $d, $n);
}

echo "\n=== Nettoyage (on retire la colonne d'essai) ===\n";
$p->exec("DROP INDEX idx_batches_shop_exp ON product_batches");
$p->exec("ALTER TABLE product_batches DROP COLUMN shop_id");
$p->query("ANALYZE TABLE product_batches")->fetchAll();
echo "  ok\n";
