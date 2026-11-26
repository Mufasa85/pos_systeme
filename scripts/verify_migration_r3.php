<?php
/**
 * Vérifie la migration R3 sur une base JETABLE construite depuis le
 * schéma RÉEL de production.
 *
 * Le schéma est pris depuis `pos_system` (SHOW CREATE TABLE) — pas
 * recopié à la main — pour que la migration soit testée contre les
 * vraies définitions (index, FK, collations).
 *
 * La migration est appliquée DEUX FOIS pour prouver qu'elle est
 * rejouable (le backfill est un UPDATE, pas un INSERT).
 *
 * Ne touche JAMAIS `pos_system` ni `pos_systeme_test`.
 */

const HOST = '127.0.0.1';
const USER = 'root';
const PASS = 'root';
const SRC  = 'pos_system';
const DB   = 'pos_perf_r3_test';

function pdo(?string $db = null): PDO
{
    $dsn = 'mysql:host=' . HOST . ';port=3306;charset=utf8mb4' . ($db ? ';dbname=' . $db : '');
    return new PDO($dsn, USER, PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
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

echo "=== 1. Construction de la base jetable depuis le schéma RÉEL ===\n";

$p = pdo();
$p->exec('DROP DATABASE IF EXISTS ' . DB);
$p->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

// On copie la structure ET les données de 3 tables.
$pdo = pdo(DB);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$tables = ['shops', 'categories', 'taxes', 'produits', 'product_batches'];
foreach ($tables as $t) {
    $row = pdo(SRC)->query("SHOW CREATE TABLE `$t`")->fetch();
    $ddl = $row['Create Table'];
    // On retire le AUTO_INCREMENT pour repartir de 1 proprement.
    $ddl = preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl);
    $pdo->exec($ddl);
    echo "    table copiée : $t\n";
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Un peu de données réalistes : 2 boutiques, des produits, des lots.
$pdo->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique A','BA',1),(2,'Boutique B','BB',1)");
$pdo->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Boissons',1),(2,'Alimentaire',2),(3,'Mixte',NULL)");
$pdo->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A')");

$pdo->exec("INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
             VALUES ('R3-P1','Produit boutique A',1,1,100,0,5,NULL,1),
                    ('R3-P2','Produit boutique B',2,2,200,0,5,NULL,1),
                    ('R3-P3','Produit sans boutique',3,NULL,300,0,5,NULL,1)");
$pdo->exec("INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
             VALUES (1,'LOT-A1',10,DATE_ADD(CURDATE(), INTERVAL 10 DAY),CURDATE()),
                    (1,'LOT-A2',20,DATE_ADD(CURDATE(), INTERVAL 5 DAY),CURDATE()),
                    (2,'LOT-B1',30,DATE_ADD(CURDATE(), INTERVAL 3 DAY),CURDATE()),
                    (3,'LOT-C1',40,NULL,CURDATE())");

$lotsAvant = (int) $pdo->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
echo "    $lotsAvant lots insérés\n";

echo "\n=== 2. La colonne n'existe pas encore ===\n";
$colAvant = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'product_batches' AND column_name = 'shop_id'"
)->fetchColumn();
check('shop_id absent avant migration', $colAvant === 0);

$idxAvant = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'product_batches'
       AND index_name = 'idx_batches_shop_exp'"
)->fetchColumn();
check('idx_batches_shop_exp absent avant migration', $idxAvant === 0);

echo "\n=== 3. Application de la migration (1re fois) ===\n";
$migration = __DIR__ . '/../migrations/2026_11_25_product_batches_shop_id_denormalise.sql';
$sql = file_get_contents($migration);

// Le runner du projet découpe les instructions ; on le reproduit ici.
$runner = __DIR__ . '/../scripts/apply_migration.php';
$out = [];
$code = 0;
// DB_NAME pointe la base jetable : apply_migration.php lit config/config.php,
// qui lit getenv('DB_NAME').
// ⚠️ `DB_NAME=x cmd` est la syntaxe POSIX (sh) : sous Windows/cmd.exe
// elle est refusée (« 'DB_NAME' is not recognized »), et sous sh le
// quoting imbriqué casse (« pos_perf_r3_test: command not found »).
//
// La solution portable : `putenv()` dans le processus parent — le
// processus enfant hérite de l'environnement, donc `php
// scripts/apply_migration.php` lit le bon DB_NAME via config/config.php.
putenv('DB_NAME=' . DB);
exec(sprintf(
    'php %s %s 2>&1',
    escapeshellarg($runner),
    escapeshellarg($migration)
), $out, $code);
foreach ($out as $line) {
    if (trim($line) !== '') {
        echo '    ' . $line . "\n";
    }
}
check('la migration s\'exécute sans erreur', $code === 0, 'code=' . $code);

echo "\n=== 4. Contrôle après la 1re application ===\n";
$colApres = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'product_batches' AND column_name = 'shop_id'"
)->fetchColumn();
check('shop_id existe', $colApres === 1);

$idxApres = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'product_batches'
       AND index_name = 'idx_batches_shop_exp'"
)->fetchColumn();
check('idx_batches_shop_exp existe', $idxApres > 0);

$rows = $pdo->query(
    "SELECT b.batch_number, b.shop_id AS lot_shop, p.shop_id AS produit_shop
     FROM product_batches b JOIN produits p ON p.id = b.product_id
     ORDER BY b.id"
)->fetchAll();

foreach ($rows as $r) {
    $attendu = $r['produit_shop'];
    $ok = ($r['lot_shop'] === null && $attendu === null)
        || ((int)$r['lot_shop'] === (int)$attendu);
    check(
        'le lot ' . $r['batch_number'] . ' porte la boutique de son produit',
        $ok,
        'lot=' . var_export($r['lot_shop'], true) . ', produit=' . var_export($r['produit_shop'], true)
    );
}

check(
    'le lot d\'un produit SANS boutique a shop_id NULL',
    $rows[3]['lot_shop'] === null,
    var_export($rows[3]['lot_shop'], true)
);

$mismatch = (int) $pdo->query(
    "SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id = b.product_id
     WHERE NOT (b.shop_id <=> p.shop_id)"
)->fetchColumn();
check('aucun lot ne diverge de son produit', $mismatch === 0, $mismatch . ' divergences');

$orphelins = (int) $pdo->query(
    "SELECT COUNT(*) FROM product_batches b LEFT JOIN produits p ON p.id = b.product_id WHERE p.id IS NULL"
)->fetchColumn();
check('aucun lot orphelin', $orphelins === 0);

echo "\n=== 5. Application de la migration (2e fois — rejouabilité) ===\n";
$out2 = [];
exec(sprintf(
    'php %s %s 2>&1',
    escapeshellarg($runner),
    escapeshellarg($migration)
), $out2, $code2);
check('la 2e application ne plante pas', $code2 === 0, 'code=' . $code2);

$lotsApres = (int) $pdo->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
check('aucun lot dupliqué par le rejeu', $lotsApres === $lotsAvant, "$lotsApres (attendu $lotsAvant)");

$mismatch2 = (int) $pdo->query(
    "SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id = b.product_id
     WHERE NOT (b.shop_id <=> p.shop_id)"
)->fetchColumn();
check('toujours aucune divergence après rejeu', $mismatch2 === 0);

echo "\n=== 6. Un lot créé APRÈS la migration porte la bonne boutique ===\n";
// C'est le comportement de ProductBatch::create() : shop_id lu depuis
// le produit. On le simule en SQL pour valider la contrainte, puis on
// vérifiera le vrai code dans les tests PHPUnit.
$pdo->exec("INSERT INTO product_batches (product_id, shop_id, batch_number, stock, date_expiration, date_reception)
             VALUES (1, (SELECT shop_id FROM produits WHERE id = 1), 'LOT-A3', 5, NULL, CURDATE())");
$row = $pdo->query("SELECT shop_id FROM product_batches WHERE batch_number = 'LOT-A3'")->fetch();
check('le nouveau lot porte shop_id = 1', (int)$row['shop_id'] === 1, var_export($row['shop_id'], true));

echo "\n=== 7. Nettoyage ===\n";
pdo()->exec('DROP DATABASE IF EXISTS ' . DB);
echo "    base jetable " . DB . " supprimée.\n";
echo "    (pos_system et pos_systeme_test n'ont pas été touchées)\n";

echo "\n" . ($failures === 0
    ? "=== TOUS LES CONTROLES PASSENT ===\n"
    : "=== $failures ECHEC(S) ===\n");

exit($failures === 0 ? 0 : 1);
