<?php
/**
 * Mesure l'impact du DASHBOARD quand il rend TOUTES les alertes de lots.
 *
 * Constat : dashboard.php fait un foreach sur $expired_alerts et
 * $expiration_alerts sans limite. Avec 50 000 produits / 85 000 lots,
 * getExpiringSoon(7) renvoie 1 703 lignes -> 1 703 <div> dans le HTML.
 *
 * Ce script mesure le coût réel du rendu, et compare avec une version
 * bornée (les N plus urgente) + un compteur.
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
    printf("  %-52s %8.1f ms  (%d lignes)\n", $label, $times[(int) (count($times) / 2)], count($rows));
    return [$rows, $times[(int) (count($times) / 2)]];
}

$soonSql = "SELECT b.*, p.nom as product_name, p.code_barres, p.shop_id
            FROM product_batches b
            INNER JOIN produits p ON b.product_id = p.id
            WHERE b.date_expiration IS NOT NULL
              AND b.date_expiration >= CURDATE()
              AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND b.stock > 0
              AND p.shop_id = :shop
            ORDER BY b.date_expiration ASC, p.nom ASC";

echo "=== 1. La requête du dashboard (telle quelle) ===\n";
[$alerts, $tSql] = bench($p, 'getExpiringSoon(7) — sans limite', $soonSql, [':days' => 7, ':shop' => 1]);
[$alerts30, $tSql30] = bench($p, 'getExpiringSoon(30) — sans limite', $soonSql, [':days' => 30, ':shop' => 1]);

echo "\n=== 2. Le RENDU HTML du dashboard ===\n";

function renderAlerts(array $alerts): string
{
    $html = '';
    foreach ($alerts as $ea) {
        $html .= '<div class="alert-item">'
            . '<span>' . htmlspecialchars($ea['product_name'])
            . ' ' . (!empty($ea['batch_number']) ? '(' . htmlspecialchars($ea['batch_number']) . ')' : '') . '</span>'
            . '<span><strong>' . floor((strtotime($ea['date_expiration']) - strtotime(date('Y-m/Y-m-d'))) / 86400) . 'j</strong> '
            . date('d/m/Y', strtotime($ea['date_expiration'])) . '</span>'
            . '</div>';
    }
    return $html;
}

$t0 = microtime(true);
$htmlAll  = renderAlerts($alerts);
$tRenderAll = (microtime(true) - $t0) * 1000;

$t0 = microtime(true);
$htmlTop  = renderAlerts(array_slice($alerts, 0, 20));
$tRenderTop = (microtime(true) - $t0) * 1000;

printf("  rendu de %d alertes : %8.1f ms  →  %6.2f Mo de HTML\n",
    count($alerts), $tRenderAll, strlen($htmlAll) / 1048576);
printf("  rendu des 20 plus urgentes : %6.1f ms  →  %6.2f Mo de HTML\n",
    $tRenderTop, strlen($htmlTop) / 1048576);
printf("  gain : %.0f× sur le temps, %.0f× sur la taille\n\n",
    $tRenderAll / max(0.001, $tRenderTop), strlen($htmlAll) / max(1, strlen($htmlTop)));

echo "=== 3. Le dashboard complet (SQL + rendu) ===\n";
printf("  ACTUEL (1 703 alertes rendues) : %8.1f ms\n", $tSql + $tRenderAll);
printf("  BORNÉ  (20 alertes + compteur)  : %8.1f ms\n", $tSql + $tRenderTop);
printf("  → gain : %.0f×\n\n", ($tSql + $tRenderAll) / max(0.1, $tSql + $tRenderTop));

echo "=== 4. Le compteur (pour dire « 1 703 alertes, voir plus ») ===\n";
[$cnt, $tCnt] = bench($p, 'COUNT(*) des alertes', "SELECT COUNT(*) FROM product_batches b
    INNER JOIN produits p ON b.product_id = p.id
    WHERE b.date_expiration >= CURDATE()
      AND b.date_expiration <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
      AND b.stock > 0 AND p.shop_id = :shop", [':days' => 7, ':shop' => 1]);

echo "\n=== 5. Le pire cas réel : 90 jours ===\n";
[$alerts90, ] = bench($p, 'getExpiringSoon(90) — sans limite', $soonSql, [':days' => 90, ':shop' => 1]);
$t0 = microtime(true);
$html90 = renderAlerts($alerts90);
$t90 = (microtime(true) - $t0) * 1000;
printf("  rendu de %d alertes : %.1f ms → %.2f Mo\n", count($alerts90), $t90, strlen($html90) / 1048576);
