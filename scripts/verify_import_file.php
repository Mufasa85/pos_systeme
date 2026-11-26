<?php
/**
 * Vérifie que le fichier Excel généré est RÉELLEMENT importable.
 *
 * ⚠️ On ne se contente pas de vérifier qu'il s'ouvre : on le passe
 *    dans le VRAI service d'import (`ProductImportService`), sur une base
 *    jetable, et on vérifie le nombre de lignes réellement importées.
 *
 * C'est la seule preuve que le fichier est conforme : un fichier qui
 * "s'ouvre" peut très bien rejeté 100 % des lignes à l'import.
 *
 * Base : pos_import_test — créée puis supprimée.
 * Ne touche JAMAIS `pos_system` ni `pos_systeme_test`.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = $argv[1] ?? (dirname(__DIR__) . '/docs/produits_50k_import.xlsx');
// Le 2e argument est le nombre de lignes ATTENDU (pour le controle) —
// il ne limite PAS l'import.
$expectedRows = (int) ($argv[2] ?? 0);   // 0 = toutes les lignes du fichier

if (!is_file($file)) {
    fwrite(STDERR, "Fichier introuvable : {$file}\n");
    exit(1);
}

echo "=== 1. Lecture du fichier par PhpSpreadsheet ===\n";
$t0 = microtime(true);
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$tRead = (microtime(true) - $t0) * 1000;

$highestRow = $sheet->getHighestRow();
$highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
printf("    feuille « %s » : %d lignes × %d colonnes\n", $sheet->getTitle(), $highestRow, $highestCol);
printf("    lecture en %.0f ms\n", $tRead);

echo "\n=== 2. L'en-tête correspond-il au template de l'import ? ===\n";
$attendu = [
    'code_barres', 'nom', 'categorie', 'prix', 'stock', 'stock_minimum',
    'taxe', 'product_type', 'prod_service', 'remise_type', 'remise_value',
    'taxe_specifique_type', 'taxe_specifique_value', 'date_expiration',
];
$lu = [];
for ($c = 1; $c <= count($attendu); $c++) {
    $lu[] = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
}
$ok = ($lu === $attendu);
printf("  [%s] les 14 en-têtes canoniques sont dans le bon ordre\n", $ok ? 'OK' : 'KO');
if (!$ok) {
    echo "    attendu : " . implode(', ', $attendu) . "\n";
    echo "    lu      : " . implode(', ', $lu) . "\n";
}

echo "\n=== 3. Le format TEXTE de la colonne code_barres ===\n";
$format = $sheet->getStyle('A:A')->getNumberFormat()->getFormatCode();
printf("  [%s] le format de la colonne A est « %s » (attendu « @ »)\n",
    ($format === '@' ? 'OK' : 'KO'), $format);

echo "\n=== 4. Un échantillon de lignes ===\n";
printf("  %-16s %-44s %-16s %10s %8s %-12s\n", 'code_barres', 'nom', 'categorie', 'prix', 'stock', 'date_exp');
for ($r = 2; $r <= min(6, $highestRow); $r++) {
    printf("  %-16s %-44s %-16s %10s %8s %-12s\n",
        substr((string)$sheet->getCell("A{$r}")->getValue(), 0, 16),
        substr((string)$sheet->getCell("B{$r}")->getValue(), 0, 44),
        (string)$sheet->getCell("C{$r}")->getValue(),
        (string)$sheet->getCell("D{$r}")->getValue(),
        (string)$sheet->getCell("E{$r}")->getValue(),
        (string)$sheet->getCell("N{$r}")->getValue()
    );
}

echo "\n=== 5. Les DONNÉES sont-elles cohérentes ? ===\n";
// Les codes-barres doivent être uniques (l'import ignore les doublons).
$codes = [];
$manquants = 0;
$accents = 0;
$apostrophes = 0;
$perimes = 0;
$sansStock = 0;
$avecExp = 0;
$prixInvalides = 0;
$cats = [];

for ($r = 2; $r <= $highestRow; $r++) {
    $code = (string)$sheet->getCell("A{$r}")->getValue();
    $nom  = (string)$sheet->getCell("B{$r}")->getValue();
    $cat  = (string)$sheet->getCell("C{$r}")->getValue();
    $prix = (string)$sheet->getCell("D{$r}")->getValue();
    $stock = (string)$sheet->getCell("E{$r}")->getValue();
    $exp  = (string)$sheet->getCell("N{$r}")->getValue();

    if ($code === '') {
        $manquants++;
    } else {
        $codes[$code] = true;
    }
    if (preg_match('/[éèêàçùôûîï]/u', $nom)) {
        $accents++;
    }
    if (strpos($nom, "'") !== false) {
        $apostrophes++;
    }
    if ($exp !== '') {
        $avecExp++;
        if (strtotime($exp) < strtotime(date('Y-m-d'))) {
            $perimes++;
        }
    }
    if ((float)$stock <= 0) {
        $sansStock++;
    }
    if (!is_numeric($prix) || (float)$prix <= 0) {
        $prixInvalides++;
    }
    $cats[$cat] = true;
}

$total = $highestRow - 1;
printf("  lignes de données            : %d\n", $total);
printf("  [%s] code-barres uniques      : %d / %d\n",
    count($codes) === $total ? 'OK' : 'KO', count($codes), $total);
printf("  [%s] aucun code-barres vide   : %d vide(s)\n", $manquants === 0 ? 'OK' : 'KO', $manquants);
printf("  [%s] aucun prix invalide      : %d\n", $prixInvalides === 0 ? 'OK' : 'KO', $prixInvalides);
printf("  noms avec accents             : %d (%.1f %%)\n", $accents, $accents * 100 / max(1, $total));
printf("  noms avec apostrophe          : %d (%.1f %%)\n", $apostrophes, $apostrophes * 100 / max(1, $total));
printf("  lots déjà périmés             : %d (%.1f %%)\n", $perimes, $perimes * 100 / max(1, $total));
printf("  produits avec péremption      : %d (%.1f %%)\n", $avecExp, $avecExp * 100 / max(1, $total));
printf("  produits en rupture (stock 0) : %d (%.1f %%)\n", $sansStock, $sansStock * 100 / max(1, $total));
printf("  catégories utilisées           : %s\n", implode(', ', array_keys($cats)));

echo "\n=== 7. IMPORT RÉEL dans une base jetable ===\n";
/*
 * On importe dans une base jetable pour prouver que le fichier passe
 * le VRAI service d'import (et pas seulement qu'il s'ouvre).
 */
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('DROP DATABASE IF EXISTS pos_import_test');
$pdo->exec('CREATE DATABASE pos_import_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

// Le schéma minimal nécessaire à l'import.
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pos_import_test;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('CREATE TABLE shops (id int NOT NULL AUTO_INCREMENT, nom varchar(100) NOT NULL, code varchar(20) NOT NULL DEFAULT "X", actif tinyint(1) DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE categories (id int NOT NULL AUTO_INCREMENT, category varchar(120) NOT NULL, shop_id int DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE taxes (id int NOT NULL AUTO_INCREMENT, taux decimal(5,2) NOT NULL, etiquette varchar(20) DEFAULT NULL, groupe_taxe varchar(50) DEFAULT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE produits (id int NOT NULL AUTO_INCREMENT, code_barres varchar(50) NOT NULL, nom varchar(100) NOT NULL, category_id int NOT NULL, shop_id int DEFAULT NULL, prix decimal(10,2) NOT NULL, stock float NOT NULL DEFAULT 0, stock_minimum float NOT NULL DEFAULT 0, image varchar(255) DEFAULT NULL, taxe_id int DEFAULT 1, product_type varchar(20) DEFAULT "unite", prod_service enum("BIE","SER","TAX") DEFAULT NULL, remise_type varchar(10) DEFAULT "percent", remise_value decimal(10,2) DEFAULT 0.00, taxe_specifique_type varchar(10) DEFAULT "%", taxe_specifique_value decimal(10,2) DEFAULT 0.00, date_expiration date DEFAULT NULL, available_stock float NOT NULL DEFAULT 0, nearest_expiration_date date DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY code_barres (code_barres), KEY category_id (category_id), KEY idx_prod_shop (shop_id), KEY idx_prod_shop_nom (shop_id, nom)) ENGINE=InnoDB');
/*
 * ⚠️ `shop_id` est présent : c'est la colonne dé-normalisée de la
 * migration R3 (2026_11_25_product_batches_shop_id_denormalise.sql), et
 * le code de `ProductBatch::create()` l'écrit explicitement. Sans elle,
 * l'import échoue sur « Unknown column 'shop_id' » — ce que ce script
 * a effectivement constaté.
 */
$pdo->exec('CREATE TABLE product_batches (id int NOT NULL AUTO_INCREMENT, product_id int NOT NULL, shop_id int NULL DEFAULT NULL, batch_number varchar(50) DEFAULT NULL, stock float NOT NULL DEFAULT 0, date_expiration date DEFAULT NULL, date_reception date DEFAULT NULL, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY product_id (product_id), KEY date_expiration (date_expiration), KEY idx_batches_shop_exp (shop_id, date_expiration, id)) ENGINE=InnoDB');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$pdo->exec("INSERT INTO shops (id, nom, code, actif) VALUES (1,'Boutique Test','BT',1),(2,'Boutique 2','B2',1)");
$pdo->exec("INSERT INTO categories (id, category, shop_id) VALUES (1,'Comestible',1),(3,'Service',1),(4,'Non comestible',1),(6,'Boissons',2),(16,'Boissons',1),(31,'animal',1),(32,'animal',2),(33,'Comestible',2),(34,'Non comestible',2)");
$pdo->exec("INSERT INTO taxes (id, taux, etiquette, groupe_taxe) VALUES (1,0,'A','Groupe A'),(2,16,'B','Groupe B'),(3,5,'C','Groupe C'),(4,0,'D','Groupe D'),(5,0,'E','Groupe E'),(6,16,'F','Groupe F'),(7,5,'G','Groupe G'),(8,0,'H','Groupe H'),(9,0,'I','Groupe I'),(10,0,'J','Groupe J'),(11,0,'K','Groupe K'),(12,0,'L','Groupe L'),(13,0,'M','Groupe M'),(14,0,'N','Groupe N'),(15,1,'O','Groupe O'),(16,1,'P','Groupe P'),(17,50,'Q','GROUPE Q'),(18,20,'R','GROUPE R')");

// On force la config vers la base jetable.
putenv('DB_NAME=pos_import_test');

// Le service d'import, tel que l'API l'appelle.
$service = new \App\Services\ProductImportService();

$t0 = microtime(true);
// La signature réelle est importFile($shopId, $filePath, $options).
$memoryBefore = memory_get_peak_usage(true);
$result = $service->importFile(1, $file, [
    'mode'              => 'create',
    'create_categories' => false,
]);
$tImport = (microtime(true) - $t0) * 1000;

printf("  import terminé en %.0f ms\n", $tImport);
printf("  pic mémoire  : %.0f Mo (memory_limit : %s)\n",
    memory_get_peak_usage(true) / 1048576, ini_get('memory_limit'));
printf("  résultat : %s\n", json_encode(array_diff_key($result, ['report' => 1]), JSON_UNESCAPED_UNICODE));

$importes = (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
$lots = (int)$pdo->query('SELECT COUNT(*) FROM product_batches')->fetchColumn();
$avecStock = (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE stock > 0')->fetchColumn();

printf("  produits en base   : %d\n", $importes);
printf("  lots en base       : %d\n", $lots);
printf("  produits avec stock: %d\n", $avecStock);

echo "\n=== 6. Les catégories existent-elles en base ? ===\n";
// La vérification se fait sur la base JETABLE (c'est elle qui sera
// importée) — les catégories réelles y ont été recopiées.
foreach (array_keys($cats) as $c) {
    $st = $pdo->prepare('SELECT id FROM categories WHERE category = ? LIMIT 1');
    $st->execute([$c]);
    $row = $st->fetch();
    printf("  [%s] « %s »\n", $row ? 'OK' : 'KO', $c);
}

$attendu = $expectedRows > 0 ? min($expectedRows, $total) : $total;
printf("\n  [%s] %d produits importés sur %d attendus\n",
    ($importes === $attendu ? 'OK' : 'KO'), $importes, $attendu);

// Un échantillon importé.
echo "\n  Échantillon importé :\n";
foreach ($pdo->query('SELECT code_barres, nom, category_id, prix, stock FROM produits ORDER BY id LIMIT 3')->fetchAll() as $r) {
    printf("    %-16s %-44s cat=%d prix=%-10s stock=%s\n",
        $r['code_barres'], $r['nom'], $r['category_id'], $r['prix'], $r['stock']);
}

// Le detail de l'import : combien de lots crees, combien de produits
// avec une date de peremption (ils alimentent la page /produits/lots).
$avecExp = (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE date_expiration IS NOT NULL')->fetchColumn();
$perimes = (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE date_expiration < CURDATE()')->fetchColumn();
printf("\n    produits avec date de peremption : %d\n", $avecExp);
printf("    lots deja perimes (date_expiration) : %d\n", $perimes);

// Nettoyage.
$pdo = null;
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('DROP DATABASE IF EXISTS pos_import_test');
echo "\n=== base jetable pos_import_test supprimée ===\n";
