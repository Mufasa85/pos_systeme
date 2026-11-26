<?php
/**
 * Mesure l'import CSV vs l'import XLSX à 50 000 produits.
 *
 * ⚠️ Constat : `ProductImportService::readRows()` fait
 *    `IOFactory::load()` pour un .xlsx → TOUT le fichier en RAM.
 *    La branche CSV (`readCsvRows()`) lit ligne par ligne → mémoire
 *    constante.
 *
 * Ce script mesure les DEUX, sur une base jetable, et rapporte :
 *   - le temps ;
 *   - la PIC de mémoire (memory_get_peak_usage) ;
 *   - le nombre de lignes importées.
 *
 * Base : pos_import_csv_test — créée puis supprimée.
 */

require __DIR__ . '/../vendor/autoload.php';

$csv = $argv[1] ?? (dirname(__DIR__) . '/docs/produits_50k_import.csv');
$maxRows = (int) ($argv[2] ?? 0);

if (!is_file($csv)) {
    fwrite(STDERR, "Fichier introuvable : {$csv}\n");
    exit(1);
}

const DB = 'pos_import_csv_test';

/**
 * Prépare la base jetable (schéma minimal + données de référence).
 */
/**
 * Prend un verrou SUR LE NOM DE LA BASE JETABLE.
 *
 * ⚠️ `flock()` NE MARCHE PAS entre deux processus PHP distincts sur ce
 *    setup (Windows/MSYS) : les deux obtiennent le verrou — vérifié.
 *    Le verrou par EXISTENCE DU FICHIER, lui, fonctionne partout.
 *
 *    Le fichier contient le PID du processus détenteur : si le processus
 *    est mort sans avoir nettoyé, on peut le dire (« processus 1234
 *    disparu »), et on libère le verrou.
 */
function preparer(): PDO
{
    $root = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $root->exec('DROP DATABASE IF EXISTS ' . DB);
    $root->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . DB . ';charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('CREATE TABLE shops (id int NOT NULL AUTO_INCREMENT, nom varchar(100) NOT NULL, code varchar(20) NOT NULL DEFAULT "X", actif tinyint(1) DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE categories (id int NOT NULL AUTO_INCREMENT, category varchar(120) NOT NULL, shop_id int DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE taxes (id int NOT NULL AUTO_INCREMENT, taux decimal(5,2) NOT NULL, etiquette varchar(20) DEFAULT NULL, groupe_taxe varchar(50) DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE produits (id int NOT NULL AUTO_INCREMENT, code_barres varchar(50) NOT NULL, nom varchar(100) NOT NULL, category_id int NOT NULL, shop_id int DEFAULT NULL, prix decimal(10,2) NOT NULL, stock float NOT NULL DEFAULT 0, stock_minimum float NOT NULL DEFAULT 0, image varchar(255) DEFAULT NULL, taxe_id int DEFAULT 1, product_type varchar(20) DEFAULT "unite", prod_service enum("BIE","SER","TAX") DEFAULT NULL, remise_type varchar(10) DEFAULT "percent", remise_value decimal(10,2) DEFAULT 0.00, taxe_specifique_type varchar(10) DEFAULT "%", taxe_specifique_value decimal(10,2) DEFAULT 0.00, date_expiration date DEFAULT NULL, available_stock float NOT NULL DEFAULT 0, nearest_expiration_date date DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY code_barres (code_barres), KEY category_id (category_id), KEY idx_prod_shop (shop_id), KEY idx_prod_shop_nom (shop_id, nom)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE product_batches (id int NOT NULL AUTO_INCREMENT, product_id int NOT NULL, shop_id int NULL DEFAULT NULL, batch_number varchar(50) DEFAULT NULL, stock float NOT NULL DEFAULT 0, date_expiration date DEFAULT NULL, date_reception date DEFAULT NULL, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY product_id (product_id), KEY date_expiration (date_expiration), KEY idx_batches_shop_exp (shop_id, date_expiration, id)) ENGINE=InnoDB');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique Test','BT',1),(2,'Boutique 2','B2',1)");
    $pdo->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Comestible',1),(3,'Service',1),(4,'Non comestible',1),(6,'Boissons',2),(16,'Boissons',1),(31,'animal',1),(32,'animal',2),(33,'Comestible',2),(34,'Non comestible',2)");
    $pdo->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A'),(2,16,'B','Groupe B'),(3,5,'C','Groupe C'),(4,0,'D','Groupe D'),(5,0,'E','Groupe E'),(6,16,'F','Groupe F'),(7,5,'G','Groupe G'),(8,0,'H','Groupe H'),(9,0,'I','Groupe I'),(10,0,'J','Groupe J'),(11,0,'K','Groupe K'),(12,0,'L','Groupe L'),(13,0,'M','Groupe M'),(14,0,'N','Groupe N'),(15,1,'O','Groupe O'),(16,1,'P','Groupe P'),(17,50,'Q','GROUPE Q'),(18,20,'R','GROUPE R')");

    return $pdo;
}

function nettoyer(): void
{
    $root = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $root->exec('DROP DATABASE IF EXISTS ' . DB);
}

/**
 * Mesure UN import et renvoie le résultat.
 */
function mesurer(string $fichier, string $label): array
{
    global $DB;

    echo "=== Import {$label} : " . basename($fichier) . " ===\n";
    printf("    taille du fichier : %.2f Mo\n", filesize($fichier) / 1048576);

    $pdo = preparer();
    putenv('DB_NAME=' . DB);

    $service = new \App\Services\ProductImportService();

    $memoryBefore = memory_get_peak_usage(true);

    $t0 = microtime(true);
    $result = $service->importFile(1, $fichier, [
        'mode'              => 'create',
        'create_categories' => false,
    ]);
    $tImport = (microtime(true) - $t0) * 1000;
    $memoryPeak = memory_get_peak_usage(true);

    $summary = $result['summary'] ?? [];
    printf("    temps        : %.0f ms (%.0f s)\n", $tImport, $tImport / 1000);
    printf("    pic mémoire  : %.0f Mo\n", $memoryPeak / 1048576);
    printf("    résultat     : %d créés, %d ignorés, %d erreurs\n",
        $summary['created'] ?? 0, $summary['skipped'] ?? 0, $summary['errors'] ?? 0);

    // Les messages d'erreur : c'est eux qui disent ce qui cloche.
    $msgs = [];
    foreach (($result['rows'] ?? []) as $r) {
        if (($r['status'] ?? '') === 'error') {
            $m = (string)($r['message'] ?? '');
            $msgs[$m] = ($msgs[$m] ?? 0) + 1;
        }
    }
    if (!empty($msgs)) {
        echo "    messages d'erreur :\n";
        foreach ($msgs as $m => $n) {
            printf("      %5d × %s\n", $n, mb_substr($m, 0, 110));
        }
    }

    $produits = (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
    $lots = (int)$pdo->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
    $sansShop = (int)$pdo->query('SELECT COUNT(*) FROM product_batches WHERE shop_id IS NULL')->fetchColumn();
    $divergence = (int)$pdo->query(
        'SELECT COUNT(*) FROM product_batches b JOIN produits p ON p.id = b.product_id
         WHERE NOT (b.shop_id <=> p.shop_id)'
    )->fetchColumn();

    printf("    produits en base : %d\n", $produits);
    printf("    lots en base     : %d\n", $lots);
    printf("    lots sans shop_id: %d (attendu 0)\n", $sansShop);
    printf("    divergence       : %d (attendu 0)\n", $divergence);

    echo "\n    Échantillon :\n";
    foreach ($pdo->query('SELECT code_barres, nom, prix, stock FROM produits ORDER BY id DESC LIMIT 3')->fetchAll() as $r) {
        printf("      %-16s %-44s %-10s %s\n", $r['code_barres'], $r['nom'], $r['prix'], $r['stock']);
    }

    nettoyer();
    echo "    base jetable " . $DB . " supprimée\n\n";

    return [
        'fichier'    => basename($fichier),
        'ms'         => $tImport,
        'memoire'    => $memoryPeak,
        'crees'      => $summary['created'] ?? 0,
        'erreurs'    => $summary['errors'] ?? 0,
        'produits'   => $produits,
        'lots'       => $lots,
        'divergence' => $divergence,
    ];
}

/*
 * ⚠️ Le verrou est pris AVANT toute écriture (avant le DROP DATABASE
 *    de `preparer()`). Sinon deux mesures se marchent dessus : l'une
 *    vide la base sous les pieds de l'autre, et la survivante rapporte
 *    des milliers d'erreurs FANTÔMES (« Duplicate entry »).
 */
require_once __DIR__ . '/import_lock.php';

if (!import_lock_acquire(DB)) {
    exit(1);
}
register_shutdown_function('import_lock_release');

$mesures = [];

// Le CSV d'abord : il n'a pas la limite de mémoire.
$mesures['CSV'] = mesurer($csv, 'CSV');

// Le XLSX : il lui faut une mémoire plus large (le .xlsx est décompressé
// en RAM par PhpSpreadsheet).
$xlsx = str_replace('.csv', '.xlsx', $csv);
if (is_file($xlsx)) {
    ini_set('memory_limit', '2G');
    $mesures['XLSX'] = mesurer($xlsx, 'XLSX');
}

echo "=== COMPARAISON ===\n\n";
printf("  %-10s %12s %12s %10s %8s %8s\n", 'Format', 'Temps', 'Pic mémoire', 'Créés', 'Erreurs', 'Divergence');
foreach ($mesures as $label => $m) {
    printf("  %-10s %9.0f s %9.0f Mo %10d %8d %8d\n",
        $label, $m['ms'] / 1000, $m['memoire'] / 1048576, $m['crees'], $m['erreurs'], $m['divergence']);
}

echo "\n=== CONCLUSIONS ===\n";
if (isset($mesures['CSV'], $mesures['XLSX'])) {
    printf("  Le CSV consomme %.1f× moins de mémoire que le XLSX.\n",
        $mesures['XLSX']['memoire'] / max(1, $mesures['CSV']['memoire']));
    if ($mesures['CSV']['memoire'] < 400 * 1048576) {
        echo "  → Le CSV passe avec le memory_limit par DÉFAUT (512 Mo).\n";
    }
    if ($mesures['XLSX']['memoire'] > 512 * 1048576) {
        echo "  → Le XLSX DÉPASSE le memory_limit par défaut (512 Mo) :\n";
        echo "    il faut `memory_limit=2G` pour l'import du .xlsx.\n";
    }
}
