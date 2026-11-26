<?php
/**
 * Vérifie la page /alertes-lots : route, rendu, bornes, pagination.
 *
 * S'appuie sur une VRAIE session (session_start) — sans elle,
 * RechargeAccessService::ensureSessionStarted() remplace $_SESSION
 * par une session vide et le contrôle d'accès renvoie 403.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

// Session réelle (obligatoire pour que requireActiveShop() fonctionne).
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$shopId = (int)($_GET['shop_id'] ?? 1);
$userId = 1;

$_SESSION['user_id']         = $userId;
$_SESSION['shop_id']         = $shopId;
$_SESSION['role']            = 'admin';
$_SESSION['active_shop_id']  = $shopId;
$_SESSION['acces_produits']  = 1;

$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    printf("  [%s] %s%s\n", $ok ? 'OK' : 'KO', $label, $detail !== '' ? " — $detail" : '');
}

// ── 1. La route résout-elle ? ─────────────────────────────────────────────
echo "=== 1. Résolution de la route ===\n";
// En CLI, SCRIPT_NAME pointe sur le binaire -> le base path du routeur
// est faux et AUCUNE route ne matche. `setRouterBasePath('')` le remet
// à zéro (méthode prévue pour les tests, cf. app/App.php).
\App\App::setRouterBasePath('');

// Charger les fichiers de routes comme le fait public/index.php —
// sans ça, AUCUNE route n'est enregistrée et tout renvoie 404.
require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../routes/api.php';

/**
 * Déclenche une route comme le fait le vrai serveur (cf.
 * tests/Integration/UserPageAccessRoutesTest.php) et renvoie le corps.
 */
function routeBody(string $route): array
{
    $_SERVER['REQUEST_URI']    = $route;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    try {
        \App\Core\Router::matcher();
    } catch (\Throwable $e) {
        ob_end_clean();
        return ['status' => 500, 'body' => $e->getMessage()];
    }
    $body = (string)ob_get_clean();
    $status = http_response_code();
    http_response_code(200);

    return ['status' => $status === false ? 200 : (int)$status, 'body' => $body];
}

$r = routeBody('/alertes-lots');
check('/alertes-lots rend la page (status 200)', $r['status'] === 200, 'status=' . $r['status']);
check('la page contient le titre attendu', str_contains($r['body'], 'Alertes de péremption'));
check('la page ne contient pas d erreur fatale', !str_contains($r['body'], 'Fatal error'));

$rProd = routeBody('/produits');
check('/produits reste rendu (status 200)', $rProd['status'] === 200, 'status=' . $rProd['status']);

// ── 2. Le modèle borne-t-il les alertes ? ────────────────────────────────
echo "\n=== 2. Les bornes du modèle ===\n";
$batchModel = new \App\Models\ProductBatch();

$all = $batchModel->getExpiringSoon(90, $shopId, null);
check('getExpiringSoon(limit=null) charge tout (comportement historique)', is_array($all));

$limited = $batchModel->getExpiringSoon(90, $shopId, 20);
check('getExpiringSoon(limit=20) renvoie <= 20 lignes', count($limited) <= 20, count($limited) . ' lignes');

$expired = $batchModel->getExpired($shopId, 20);
check('getExpired(limit=20) renvoie <= 20 lignes', count($expired) <= 20, count($expired) . ' lignes');

$total = $batchModel->countExpiringSoon(90, $shopId);
check('countExpiringSoon renvoie un entier', is_int($total), "total=$total");
check('le compteur >= le nombre renvoye', $total >= count($limited), "total=$total, renvoye=" . count($limited));

$page2 = $batchModel->getExpiringSoon(90, $shopId, 20, 20);
check('offset=20 decale bien la page', count($page2) === count($limited) || count($page2) <= 20,
    'page1=' . count($limited) . ', page2=' . count($page2));
if (count($limited) > 0 && count($page2) > 0) {
    $ids1 = array_column($limited, 'id');
    $ids2 = array_column($page2, 'id');
    check('aucun chevauchement entre page 1 et page 2', count(array_intersect($ids1, $ids2)) === 0);
}

// ── 3. Le filtre 'expired' et 'soon' ne se mélangent pas ─────────────────
echo "\n=== 3. Separation des filtres ===\n";
$exp = $batchModel->getExpired($shopId, 20);
$expDates = array_map(fn($l) => $l['date_expiration'], $exp);
$badExpired = array_filter($expDates, fn($d) => $d !== null && strtotime($d) >= strtotime(date('Y-m-d')));
check('getExpired ne renvoie QUE des lots perimes', count($badExpired) === 0, count($badExpired) . ' mauvais');

$soon = $batchModel->getExpiringSoon(7, $shopId, 20);
$soonDates = array_map(fn($l) => $l['date_expiration'], $soon);
$badSoon = array_filter($soonDates, fn($d) => $d === null || strtotime($d) > strtotime(date('Y-m-d') . ' +7 days'));
check('getExpiringSoon(7) ne renvoie QUE des lots sous 7 jours', count($badSoon) === 0, count($badSoon) . ' mauvais');

// ── 4. Le rendu de la vue ─────────────────────────────────────────────────
echo "\n=== 4. Rendu de la vue ===\n";
$lots = $batchModel->getExpired($shopId, 20);
$lots = array_merge($lots, $batchModel->getExpiringSoon(30, $shopId, 20));

ob_start();
$page    = 'alertes-lots';   // injecte par render()
$pageNum = 1;
$filtre  = 'all';
$days    = 30;
$perPage = 50;
$total   = count($lots);
$pages   = 1;
include __DIR__ . '/../app/views/alertes-lots.php';
$html = ob_get_clean();

check('la vue rend du HTML', strlen($html) > 500, strlen($html) . ' octets');
check('la vue contient la table', str_contains($html, 'Alertes de péremption'));
check('la vue contient le code-barres', str_contains($html, 'code-barres') || str_contains($html, 'Code-barres'));
check('pas d\'erreur PHP dans la sortie', !str_contains($html, 'Warning:') && !str_contains($html, 'Fatal error') && !str_contains($html, 'Undefined'));

// ── 5. La page de rendu du contrôleur ─────────────────────────────────────
echo "\n=== 5. PageController::alertesLots() ===\n";
$rc = new \App\Controllers\PageController();
ob_start();
$rc->alertesLots();
$out = ob_get_clean();
check('la methode rend sans erreur fatale', !str_contains($out, 'Fatal error'), strlen($out) . ' octets');

echo "\n" . ($failures === 0
    ? "=== TOUS LES CONTROLES PASSENT ===\n"
    : "=== $failures ECHEC(S) ===\n");

exit($failures === 0 ? 0 : 1);
