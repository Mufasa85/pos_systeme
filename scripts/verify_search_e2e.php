<?php
/**
 * Vérifie l'END-TO-END de la recherche et de la page /alertes-lots.
 *
 * ⚠️ Le serveur PHP est lancé EN ARRIÈRE-PLAN par l'appelant :
 *
 *     php -S 127.0.0.1:<port> -t public
 *
 * (terminal background=true). Le lancer depuis PHP ne fonctionne pas sur
 * ce Windows/MSYS : `proc_open` échoue silencieusement et le `&` du shell
 * ne survit pas à la fin du script.
 *
 * Le port est passé via la variable d'environnement POS_VERIFY_PORT.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

$port = (int) (getenv('POS_VERIFY_PORT') ?: 0);
if ($port <= 0) {
    fwrite(STDERR, "POS_VERIFY_PORT non defini : lancez le serveur d'abord.\n");
    exit(2);
}

// ── Session réelle (obligatoire pour que requireActiveShop() passe) ──────
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/*
 * ⚠️ Le serveur (public/index.php) utilise le chemin de session PAR
 * DÉFAUT de PHP. Ce script doit utiliser le MÊME, sinon le cookie est
 * envoyé mais le serveur ne trouve pas la session et redirige vers le
 * login (302).
 */
$sessionId = 'posverify' . getmypid();
session_id($sessionId);

$_SESSION['user_id']        = 1;
$_SESSION['shop_id']        = 1;
$_SESSION['role']           = 'admin';
$_SESSION['active_shop_id'] = 1;
$_SESSION['acces_produits'] = 1;

/*
 * ⚠️ `session_write_close()` écrit le fichier et le SUPPRIME au下次
 * `session_start()`. Le serveur (public/index.php) fait son propre
 * `session_start()` : si la session est déjà « ouverte » côté serveur,
 * il la réutilise, sinon il repart d'un fichier vide.
 *
 * On écrit donc le fichier de session MANUELLEMENT, au format natif PHP,
 * pour être sûr que le serveur le lira tel quel.
 */
session_write_close();

$sessionPath = session_save_path();
$sessionPath = ($sessionPath !== '' && is_dir($sessionPath))
    ? $sessionPath
    : sys_get_temp_dir();

$sessionFile = rtrim(str_replace('\\', '/', $sessionPath), '/') . '/sess_' . $sessionId;
file_put_contents($sessionFile, 'user_id|i:1;shop_id|i:1;role|s:5:"admin";active_shop_id|i:1;acces_produits|i:1;');
@chmod($sessionFile, 0666);

// Le serveur doit pouvoir lire le fichier de session : on le rend lisible.
$sessionPath = session_save_path();
$sessionFile = ($sessionPath !== '' ? $sessionPath : sys_get_temp_dir())
    . '/sess_' . $sessionId;
if (is_file($sessionFile)) {
    @chmod($sessionFile, 0666);
}

$base = "http://127.0.0.1:{$port}";

/*
 * Données de test : sans produits ni lots, la page est vide et les
 * contrôles ne prouvent rien. On crée un jeu minimal et on le SUPPRIME
 * à la fin.
 */
$pdo = \App\Core\Database::getInstance()->getConnection();

$cleanup = [];

try {
    $pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
        ->execute(['Catégorie E2E', 1]);
    $categoryId = (int) $pdo->lastInsertId();
    $cleanup[] = ['DELETE FROM categories WHERE id = ?', [$categoryId]];

    $pdo->prepare(
        'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
         VALUES (?, ?, ?, 1, 1500, 10, 2, NULL, 1)'
    )->execute(['E2E-COLA-ORIGINAL', 'Coca-Cola Original 33cl', $categoryId]);
    $productId = (int) $pdo->lastInsertId();
    $cleanup[] = ['DELETE FROM produits WHERE id = ?', [$productId]];

    $pdo->prepare(
        'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
         VALUES (?, ?, ?, 1, 1500, 10, 2, NULL, 1)'
    )->execute(['E2E-SPRITE-1L', 'Sprite 1L', $categoryId]);
    $productId2 = (int) $pdo->lastInsertId();
    $cleanup[] = ['DELETE FROM produits WHERE id = ?', [$productId2]];

    // 25 lots sous 7 jours : le dashboard doit en montrer 20 et dire
    // qu'il y en a 5 de plus.
    //
    // ⚠️ `shop_id` est ÉCRIT explicitement : c'est la colonne dé-normalisée
    // (migration R3). Un lot inséré sans elle a shop_id = NULL et
    // n'apparaît dans AUCUNE boutique — la page /produits/lots serait
    // vide.
    for ($i = 0; $i < 25; $i++) {
        $pdo->prepare(
            'INSERT INTO product_batches (product_id, shop_id, batch_number, stock, date_expiration, date_reception)
             VALUES (?, 1, ?, 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), CURDATE())'
        )->execute([$productId, 'E2E-LOT-' . $i]);
        $batchId = (int) $pdo->lastInsertId();
        $cleanup[] = ['DELETE FROM product_batches WHERE id = ?', [$batchId]];
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Preparation des donnees de test impossible : " . $e->getMessage() . "\n");
    exit(1);
}

$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    printf("  [%s] %s%s\n", $ok ? 'OK' : 'KO', $label, $detail !== '' ? " — $detail" : '');
}

/**
 * Requête HTTP avec le cookie de session.
 */
function httpGet(string $url, string $sessionId): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Cookie: PHPSESSID=' . $sessionId],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => (string)$body];
}

// ── Attendre que le serveur réponde ──────────────────────────────────────
$ready = false;
for ($i = 0; $i < 60; $i++) {
    usleep(250000);
    $c = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
    if ($c) {
        fclose($c);
        $ready = true;
        break;
    }
}
if (!$ready) {
    fwrite(STDERR, "Le serveur sur le port {$port} ne repond pas\n");
    exit(1);
}
echo "=== Serveur pret sur le port {$port} ===\n\n";

echo "=== 1. La page /produits ===\n";
$r = httpGet($base . '/produits', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
check('la page contient le tableau des produits',
    str_contains($r['body'], 'data-table') || str_contains($r['body'], 'produits-table'));
check('pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));

echo "\n=== 2. L'API /api/produits/page ===\n";
foreach (['', 'Coca', 'zzzzintrouvable'] as $terme) {
    $url = $base . '/api/produits/page?limit=50&offset=0'
        . ($terme !== '' ? '&search=' . urlencode($terme) : '');
    $r = httpGet($url, $sessionId);

    $libelle = $terme !== '' ? " (search=$terme)" : '';
    check('HTTP 200' . $libelle, $r['code'] === 200, 'code=' . $r['code']);

    $json = json_decode($r['body'], true);
    check('  la reponse est du JSON valide', is_array($json), substr(strip_tags($r['body']), 0, 90));
    if (is_array($json)) {
        check('  il y a une cle rows', array_key_exists('rows', $json));
        check('  il y a un total', array_key_exists('total', $json));
        check('  rows <= limit', count($json['rows'] ?? []) <= 50, count($json['rows'] ?? []) . ' lignes');
    }
}

echo "\n=== 3. La page /alertes-lots ===\n";
$r = httpGet($base . '/alertes-lots', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
check('la page contient le titre', str_contains($r['body'], 'Alertes de péremption'));
check('pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));

echo "\n=== 4. Les filtres de /alertes-lots ===\n";
foreach (['expired', 'soon', 'all'] as $filtre) {
    $r = httpGet($base . '/alertes-lots?filtre=' . $filtre . '&days=30', $sessionId);
    check('filtre=' . $filtre . ' : HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
    check('  pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));
}

echo "\n=== 5. La pagination de /alertes-lots ===\n";
$r = httpGet($base . '/alertes-lots?page=2', $sessionId);
check('page=2 : HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
check('pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));

echo "\n=== 6. La page /produits/lots (R3) ===\n";
$r = httpGet($base . '/produits/lots', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
check('la page contient le titre', str_contains($r['body'], 'Lots'));
check('la page contient le tableau', str_contains($r['body'], 'lots-body'));
check('pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));

echo "\n=== 6b. L'API /api/product-batches/page (R3/R4) ===\n";
$r = httpGet($base . '/api/product-batches/page?limit=50&offset=0', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
$json = json_decode($r['body'], true);
check('la reponse est du JSON valide', is_array($json), substr(strip_tags($r['body']), 0, 90));
if (is_array($json)) {
    check('  il y a une cle rows', array_key_exists('rows', $json));
    check('  il y a un total', array_key_exists('total', $json));
    check('  il y a une borne next', array_key_exists('next', $json));
    check('  il y a has_more', array_key_exists('has_more', $json));
    check('  mode = keyset', ($json['mode'] ?? '') === 'keyset', $json['mode'] ?? '(absent)');

    // Le keyset : on utilise la borne 'next' pour la page suivante.
    if (!empty($json['rows']) && !empty($json['next'])) {
        $next = $json['next'];
        $r2 = httpGet(
            $base . '/api/product-batches/page?limit=50&offset=0&exp='
            . urlencode((string)$next['exp']) . '&id=' . (int)$next['id'],
            $sessionId
        );
        check('  la page suivante (keyset) repond 200', $r2['code'] === 200, 'code=' . $r2['code']);
        $j2 = json_decode($r2['body'], true);
        $ids1 = array_map('intval', array_column($json['rows'], 'id'));
        $ids2 = array_map('intval', array_column($j2['rows'] ?? [], 'id'));
        check('  la page suivante ne repete aucun lot',
            count(array_intersect($ids1, $ids2)) === 0,
            'page1=' . count($ids1) . ', page2=' . count($ids2));
    }
}

echo "\n=== 6c. Le mode offset de l'API ===\n";
$r = httpGet($base . '/api/product-batches/page?limit=50&offset=0&mode=offset', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
$json = json_decode($r['body'], true);
check('mode = offset', ($json['mode'] ?? '') === 'offset', $json['mode'] ?? '(absent)');

echo "\n=== 7. Le dashboard et son lien vers /alertes-lots ===\n";
$r = httpGet($base . '/dashboard', $sessionId);
check('HTTP 200', $r['code'] === 200, 'code=' . $r['code']);
check('le dashboard mentionne les alertes', str_contains($r['body'], 'Alertes de péremption'));
check('le lien /alertes-lots est présent', str_contains($r['body'], '/alertes-lots'),
    'le lien ne s affiche que s il reste des alertes non montrees');
check('pas d erreur PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'));

echo "\n=== 8. Le dashboard borne les alertes ===\n";
$nb = substr_count($r['body'], 'class="alert-item');
check('au plus 41 alertes rendues (20 périmés + 20 proches + le lien)',
    $nb <= 41, "$nb alertes");
check('le dashboard montre des alertes', $nb > 0, "$nb alertes");
check('le compteur signale les alertes non montrees',
    str_contains($r['body'], 'non affichée') || str_contains($r['body'], 'non affichee'),
    'le message « N autre(s) alerte(s) non affichée(s) » doit etre present');

@unlink($sessionFile);

// Suppression des donnees de test (dans l'ordre inverse : lots, produits,
// categorie).
foreach (array_reverse($cleanup) as [$sql, $args]) {
    $pdo->prepare($sql)->execute($args);
}

echo "\n" . ($failures === 0
    ? "=== TOUS LES CONTROLES PASSENT ===\n"
    : "=== $failures ECHEC(S) ===\n");

exit($failures === 0 ? 0 : 1);
