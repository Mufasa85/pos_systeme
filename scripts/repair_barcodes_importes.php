<?php

/**
 * Répare les codes-barres corrompus par l'import Excel.
 *
 * CONTEXTE
 *   L'import de produits lisait la valeur AFFICHÉE de la cellule
 *   « code_barres » au lieu de sa valeur RÉELLE. Or Excel formate
 *   automatiquement en date (m/d/yyyy) les codes-barres numériques à
 *   13 chiffres : « 8426423308202 » s'affichait « 5/17/23070765319 » et
 *   c'est cette chaîne qui était enregistrée en base.
 *
 *   Ce script détecte ces codes-barres « en forme de date » et les
 *   reconvertit en leur valeur numérique d'origine.
 *
 * ⚠️ La reconversion est EXACTE (et non une approximation) : la valeur
 *    affichée « m/d/yyyy » est le sérial Excel du code-barres. On vérifie
 *    systématiquement l'aller-retour (sérial → affichage → sérial) avant
 *    d'écrire quoi que ce soit.
 *
 * USAGE
 *   php scripts/repair_barcodes_importes.php                 # rapport seul (dry-run)
 *   php scripts/repair_barcodes_importes.php --yes           # applique la réparation
 *   php scripts/repair_barcodes_importes.php --yes --backup=backup_barcodes.sql
 *
 *   --resolve-collision            autorise la résolution des collisions
 *   --delete-id=294,295            produits à supprimer pour libérer le
 *                                  code-barres (avec --resolve-collision)
 *
 * Le code est corrigé côté application (ProductImportService::readBarcode
 * et ::readString) : ce script ne sert qu'à réparer les données DÉJÀ
 * importées.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

// ---------------------------------------------------------------------------
// Options CLI
// ---------------------------------------------------------------------------

$execute = false;
$backupFile = null;
$resolveCollision = false;
$deleteIds = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--yes') {
        $execute = true;
    } elseif ($arg === '--dry-run') {
        $execute = false;
    } elseif ($arg === '--resolve-collision') {
        $resolveCollision = true;
    } elseif (preg_match('/^--delete-id=(\d+(?:,\d+)*)$/', $arg, $m)) {
        $deleteIds = array_map('intval', explode(',', $m[1]));
    } elseif (preg_match('/^--backup=(.+)$/', $arg, $m)) {
        $backupFile = $m[1];
    } else {
        fwrite(STDERR, "Option inconnue : {$arg}\n");
        exit(2);
    }
}

// ---------------------------------------------------------------------------
// Détection
// ---------------------------------------------------------------------------

$service = new App\Services\BarcodeRepairService();
$result = $service->buildPlan(null, $resolveCollision, $deleteIds);

$plan = $result['plan'];
$duplicates = $result['duplicates'];
$skipped = $result['skipped'];

echo "\n🔎 Code-barres corrompus détectés sur « " . DB_NAME . ' » : '
    . $result['detected'] . "\n";

if ($result['detected'] === 0) {
    echo "   ✅ Rien à réparer.\n\n";
    exit(0);
}

foreach ($plan as $item) {
    echo sprintf(
        "   ✓ #%-4d [%s] → %-14s (%s)\n",
        $item['id'],
        $item['old'],
        $item['new'],
        $item['nom']
    );
}

foreach ($duplicates as $duplicate) {
    echo sprintf(
        "   ♻️  #%-4d [%s] — doublon à supprimer (%s)\n",
        $duplicate['id'],
        $duplicate['code_barres'],
        $duplicate['nom']
    );
}

foreach ($skipped as $item) {
    echo sprintf(
        "   ⚠️  #%-4d [%s] — %s\n",
        $item['id'],
        $item['code_barres'],
        $item['reason']
    );
}

echo "\n  " . count($plan) . ' code-barres à réparer, ' . count($skipped) . ' ignoré(s).';

if ($duplicates !== []) {
    echo "\n  " . count($duplicates) . ' doublon(s) à supprimer : '
        . implode(', ', array_map(static fn (array $d): string => '#' . $d['id'], $duplicates));
}

echo "\n";

if ($plan === [] && $duplicates === []) {
    echo "\n✅ Aucune correction à appliquer.\n\n";
    exit(0);
}

if (!$execute) {
    echo "\n🔎 MODE RAPPORT (aucune modification).\n";
    echo "   Relancez avec --yes pour appliquer la réparation.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Sauvegarde optionnelle
// ---------------------------------------------------------------------------

if ($backupFile !== null) {
    $backupPath = $backupFile;
    if (!is_dir(dirname($backupPath))) {
        $backupPath = dirname(__DIR__) . '/' . ltrim($backupPath, '/\\');
    }

    $content = "-- Sauvegarde des codes-barres AVANT réparation\n"
        . '-- Généré le ' . date('Y-m-d H:i:s') . "\n"
        . "SET NAMES utf8mb4;\n\n"
        . $service->exportBackup($plan, $duplicates);

    if (file_put_contents($backupPath, $content) === false) {
        fwrite(STDERR, "Impossible d'écrire le fichier de sauvegarde : {$backupPath}\n");
        exit(1);
    }

    echo "\n💾 Sauvegarde vers {$backupPath}\n";
}

// ---------------------------------------------------------------------------
// Réparation transactionnelle
// ---------------------------------------------------------------------------

echo "\n🔧 Réparation en cours...\n";

try {
    foreach ($duplicates as $duplicate) {
        echo "  ♻️  Suppression du doublon #{$duplicate['id']} ({$duplicate['nom']})\n";
    }

    $applied = $service->applyPlan($plan, $duplicates);

    foreach ($plan as $item) {
        echo "  ✓ #{$item['id']} : {$item['old']} → {$item['new']}\n";
    }

    echo "\n[" . date('Y-m-d H:i:s') . '] ✅ Réparation terminée ('
        . $applied['repaired'] . ' code-barres, '
        . $applied['deleted'] . " doublon(s) supprimé(s)).\n";
} catch (Throwable $e) {
    echo "\n[" . date('Y-m-d H:i:s') . '] ❌ ERREUR : ' . $e->getMessage() . "\n";
    echo "   Transaction annulée : aucune modification conservée.\n";
    error_log('[REPAIR_BARCODES] Error: ' . $e->getMessage());
    exit(1);
}

exit(0);
