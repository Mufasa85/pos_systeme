<?php
/**
 * Reproduction du bug : Duplicate entry ... for key 'produits.code_barres'
 * lors d'un import en mode « mise à jour ».
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

echo "=== 1. Comportement de array_merge sur des cles numériques ===\n";

$block1 = ['3017620000010' => 356, '3017620000028' => 357];
$block2 = ['3017620000036' => 358];

$merged = array_merge($block1, $block2);
var_dump($merged);

echo "Lookup '3017620000010' dans le merge : ";
var_dump($merged['3017620000010'] ?? 'ABSENT');

echo "\nLookup '3017620000036' dans le merge : ";
var_dump($merged['3017620000036'] ?? 'ABSENT');

echo "\n=== 2. array_replace (correctif proposé) ===\n";
$fixed = array_replace($block1, $block2);
var_dump($fixed);
echo "Lookup '3017620000010' : ";
var_dump($fixed['3017620000010'] ?? 'ABSENT');
echo "Lookup '3017620000036' : ";
var_dump($fixed['3017620000036'] ?? 'ABSENT');

echo "\n=== 3. Lookup réel via Product::findIdsByBarcodes ===\n";
$product = new App\Models\Product();
$ids = $product->findIdsByBarcodes(array_keys($block1 + $block2));
var_dump($ids);

echo "\n=== 4. Simulation de la fusion dans l'import ===\n";
$existingIds = [];
$existingIds = array_merge($existingIds, $ids);   // ce que fait le service
echo "Après array_merge, existe-t-il '3017620000010' ? ";
var_dump(isset($existingIds['3017620000010']));
echo "→ l'import va tenter un INSERT (doublon SQL) : ";
var_dump((int)$product->findIdByBarcode('3017620000010'));
