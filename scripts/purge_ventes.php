<?php

/**
 * Purge de la table `ventes` (ventes POS + factures hôtel).
 *
 * OBJECTIF
 *   Supprimer des ventes sans violer les clés étrangères (erreur MySQL 1451).
 *
 * CONTRAINTES CONNUES SUR CE PROJET (MySQL/InnoDB)
 *   - `hotel_factures.vente_id`   : FK RESTRICT  -> bloque la suppression
 *   - `hotel_extras.vente_id`     : FK SET NULL  -> détaché automatiquement
 *   - `pressing_commandes.vente_id`: FK SET NULL  -> détaché automatiquement
 *   - `caisse_brouillons.vente_id` : FK SET NULL  -> détaché automatiquement
 *   - `details_vente.vente_id`    : index simple -> lignes orphelines à purger
 *
 * Le script REDÉCOUVRE les contraintes réelles via `information_schema` :
 * il ne dépend donc pas du dump ni d'une migration précise.
 *
 * ⚠️ La purge est IRRÉVERSIBLE (sauf option --backup).
 *    Elle ne remet pas le stock et ne supprime pas les factures déjà
 *    déclarées à la DGI : sur une base de production, préférer
 *    l'archivage (scripts/archive_data.php).
 *
 * USAGE
 *   php scripts/purge_ventes.php                      # rapport seul (dry-run)
 *   php scripts/purge_ventes.php --yes                # purge de toutes les ventes
 *   php scripts/purge_ventes.php --yes --shop=2       # purge d'une boutique
 *   php scripts/purge_ventes.php --yes --backup=backup_ventes.sql
 *   php scripts/purge_ventes.php --yes --reset-auto-increment
 */

require_once dirname(__DIR__) . '/config/config.php';

// ---------------------------------------------------------------------------
// Options CLI
// ---------------------------------------------------------------------------

$execute = false;
$shopId = null;
$backupFile = null;
$resetAutoIncrement = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--yes') {
        $execute = true;
    } elseif ($arg === '--dry-run') {
        $execute = false;
    } elseif (preg_match('/^--shop=(\d+)$/', $arg, $m)) {
        $shopId = (int) $m[1];
    } elseif (preg_match('/^--backup=(.+)$/', $arg, $m)) {
        $backupFile = $m[1];
    } elseif ($arg === '--reset-auto-increment') {
        $resetAutoIncrement = true;
    } else {
        fwrite(STDERR, "Option inconnue : {$arg}\n");
        exit(2);
    }
}

// ---------------------------------------------------------------------------
// Découverte des clés étrangères
// ---------------------------------------------------------------------------

/**
 * Charge les contraintes FK de la base, regroupées par table parente.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function loadForeignKeys(PDO $db): array
{
    $sql = 'SELECT kcu.CONSTRAINT_NAME AS constraint_name,
                   kcu.TABLE_NAME AS child_table,
                   kcu.COLUMN_NAME AS child_column,
                   kcu.REFERENCED_TABLE_NAME AS parent_table,
                   kcu.REFERENCED_COLUMN_NAME AS parent_column,
                   rc.DELETE_RULE AS delete_rule
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION';

    $constraints = [];
    foreach ($db->query($sql)->fetchAll() as $row) {
        $key = $row['child_table'] . '::' . $row['constraint_name'];
        if (!isset($constraints[$key])) {
            $constraints[$key] = [
                'constraint_name' => $row['constraint_name'],
                'child_table' => $row['child_table'],
                'parent_table' => $row['parent_table'],
                'delete_rule' => strtoupper($row['delete_rule']),
                'child_columns' => [],
                'parent_columns' => [],
            ];
        }
        $constraints[$key]['child_columns'][] = $row['child_column'];
        $constraints[$key]['parent_columns'][] = $row['parent_column'];
    }

    $byParent = [];
    foreach ($constraints as $constraint) {
        $byParent[$constraint['parent_table']][] = $constraint;
    }

    return $byParent;
}

/**
 * Table existe-t-elle ?
 */
function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Valeur SQL échappée pour une sauvegarde `.sql`.
 */
function sqlValue(PDO $db, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $db->quote((string) $value);
}

/**
 * Écrit les lignes d'une requête dans un fichier SQL rejouable.
 *
 * @param array<int, array{table: string, sql: string, params: array}> $steps
 */
function writeBackup(PDO $db, string $file, array $steps): int
{
    $handle = fopen($file, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Impossible d'ouvrir le fichier de sauvegarde : {$file}");
    }

    fwrite($handle, "-- Sauvegarde avant purge de `ventes`\n");
    fwrite($handle, '-- Généré le ' . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Pour restaurer : exécuter ce fichier sur la base concernée.\n");
    fwrite($handle, "SET NAMES utf8mb4;\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

    $total = 0;
    foreach ($steps as $step) {
        $stmt = $db->prepare($step['sql']);
        $stmt->execute($step['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $columns
        ));

        fwrite($handle, "-- {$step['table']} : " . count($rows) . " ligne(s)\n");
        foreach ($rows as $row) {
            $values = array_map(
                static fn (string $column): string => sqlValue($db, $row[$column]),
                $columns
            );
            fwrite(
                $handle,
                "INSERT INTO `{$step['table']}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n"
            );
            $total++;
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($handle);

    return $total;
}

// ---------------------------------------------------------------------------
// Exécution
// ---------------------------------------------------------------------------

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, '❌ Connexion à la base impossible : ' . $e->getMessage() . "\n");
    fwrite(STDERR, '   Base : ' . DB_NAME . ' @ ' . DB_HOST . "\n");
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . "] Analyse des contraintes...\n";

$fkByParent = loadForeignKeys($db);
$ventesChildren = $fkByParent['ventes'] ?? [];

// Tables enfants connues : ligne de détail + factures hôtel + liens optionnels.
$knownChildren = [
    'details_vente' => 'Lignes de détail des ventes',
    'hotel_factures' => 'Factures hôtel officielles',
    'hotel_extras' => 'Extras hôtel (lien SET NULL)',
    'pressing_commandes' => 'Dossiers pressing (lien SET NULL)',
    'caisse_brouillons' => 'Brouillons de caisse (lien SET NULL)',
];

// Toutes les tables qui référencent `ventes` avec une règle bloquante
// (RESTRICT / NO ACTION) doivent être purgées AVANT la table parente.
$blockingChildren = [];
foreach ($ventesChildren as $constraint) {
    $rule = $constraint['delete_rule'];
    if (in_array($rule, ['RESTRICT', 'NO ACTION'], true)) {
        $blockingChildren[$constraint['child_table']][] = $constraint;
    }
}

$blockingChildNames = array_keys($blockingChildren);

echo "  → Contraintes FK pointant vers `ventes` :\n";
if ($ventesChildren === []) {
    echo "      (aucune)\n";
}
foreach ($ventesChildren as $constraint) {
    echo sprintf(
        "      - %s.%s (%s) → ON DELETE %s\n",
        $constraint['child_table'],
        implode(', ', $constraint['child_columns']),
        $constraint['constraint_name'],
        $constraint['delete_rule']
    );
}

if ($blockingChildNames === []) {
    echo "  → Aucune contrainte bloquante détectée.\n";
} else {
    echo '  → Tables à purger AVANT `ventes` (contrainte bloquante) : '
        . implode(', ', $blockingChildNames) . "\n";
}

// ---------------------------------------------------------------------------
// Construction du plan de purge
// ---------------------------------------------------------------------------

/**
 * @var array<int, array{table: string, label: string, where: string, params: array}>
 */
$plan = [];

// 1. Purge des tables enfants bloquantes (obligatoire avant la table parente).
foreach ($blockingChildren as $childTable => $constraints) {
    $childColumn = $constraints[0]['child_columns'][0];
    $label = $knownChildren[$childTable] ?? ("Table enfant de `ventes` ({$childTable})");

    $where = "{$childColumn} IN (SELECT id FROM ventes" . ($shopId !== null ? ' WHERE shop_id = ?' : '') . ')';
    $params = $shopId !== null ? [$shopId] : [];

    $plan[] = [
        'table' => $childTable,
        'label' => $label,
        'where' => $where,
        'params' => $params,
    ];
}

// 2. Purge des lignes de détail (FK ou simple index : toujours à purger).
if (tableExists($db, 'details_vente')) {
    $where = 'vente_id IN (SELECT id FROM ventes' . ($shopId !== null ? ' WHERE shop_id = ?' : '') . ')';
    $params = $shopId !== null ? [$shopId] : [];

    $plan[] = [
        'table' => 'details_vente',
        'label' => 'Lignes de détail des ventes',
        'where' => $where,
        'params' => $params,
    ];

    // 2b. Lignes de détail déjà orphelines (vente_id sans vente correspondante) :
    //     elles sont nées d'une suppression antérieure et faussent les rapports.
    //     Sans `shop_id` sur `details_vente`, elles ne sont nettoyées que
    //     lors d'une purge globale (une purge par boutique les laisserait).
    if ($shopId === null) {
        $plan[] = [
            'table' => 'details_vente',
            'label' => 'Lignes de détail orphelines (déjà sans vente)',
            'where' => 'vente_id NOT IN (SELECT id FROM ventes)',
            'params' => [],
        ];
    }
}

// 3. Suppression des ventes (table parente).
$plan[] = [
    'table' => 'ventes',
    'label' => 'Ventes (table parente)',
    'where' => $shopId !== null ? 'shop_id = ?' : '1 = 1',
    'params' => $shopId !== null ? [$shopId] : [],
];

// ---------------------------------------------------------------------------
// Rapport (dry-run)
// ---------------------------------------------------------------------------

$counts = [];
foreach ($plan as $step) {
    $sql = "SELECT COUNT(*) FROM `{$step['table']}` WHERE {$step['where']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($step['params']);
    $counts[] = (int) $stmt->fetchColumn();
}

echo "\n";
echo 'Plan de purge' . ($shopId !== null ? " (boutique #{$shopId})" : ' (toutes les ventes)') . " :\n";
foreach ($plan as $index => $step) {
    echo sprintf(
        "  %d. %-20s %-38s %s ligne(s)\n",
        $index + 1,
        $step['table'],
        $step['label'],
        number_format($counts[$index], 0, ',', ' ')
    );
}

$totalSales = (int) $db->query('SELECT COUNT(*) FROM ventes')->fetchColumn();
echo "\n  Ventes actuellement dans `ventes` : " . number_format($totalSales, 0, ',', ' ') . "\n";

if (!$execute) {
    echo "\n🔎 MODE RAPPORT (aucune suppression).\n";
    echo "   Relancez avec --yes pour exécuter la purge.\n";
    echo "   Conseil : utilisez --backup=nom_fichier.sql pour garder une copie.\n";
    echo "   ⚠️ Sur une base de production, préférer `php scripts/archive_data.php`\n";
    echo "      (archivage : conserve l'historique fiscal et purge les anciennes ventes).\n";
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

    echo "\n💾 Sauvegarde vers {$backupPath} ...\n";

    $backupSteps = [];
    foreach ($plan as $step) {
        $backupSteps[] = [
            'table' => $step['table'],
            'sql' => "SELECT * FROM `{$step['table']}` WHERE {$step['where']}",
            'params' => $step['params'],
        ];
    }

    $saved = writeBackup($db, $backupPath, $backupSteps);
    echo "  ✓ {$saved} ligne(s) sauvegardée(s)\n";
}

// ---------------------------------------------------------------------------
// Purge transactionnelle
// ---------------------------------------------------------------------------

echo "\n🗑️  Purge en cours...\n";

try {
    $db->beginTransaction();

    foreach ($plan as $step) {
        $sql = "DELETE FROM `{$step['table']}` WHERE {$step['where']}";
        $stmt = $db->prepare($sql);
        $stmt->execute($step['params']);
        $deleted = $stmt->rowCount();

        echo sprintf(
            "  ✓ %-20s %s ligne(s) supprimée(s)\n",
            $step['table'],
            number_format($deleted, 0, ',', ' ')
        );
    }

    $db->commit();

    // ⚠️ `ALTER TABLE` provoque un COMMIT implicite en MySQL :
    // on le fait APRÈS le commit pour ne pas casser l'atomicité du DELETE.
    if ($resetAutoIncrement) {
        $db->exec('ALTER TABLE ventes AUTO_INCREMENT = 1');
        echo "  ✓ Compteur AUTO_INCREMENT de `ventes` remis à 1\n";
    }

    echo "\n[" . date('Y-m-d H:i:s') . "] ✅ Purge terminée.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n[" . date('Y-m-d H:i:s') . '] ❌ ERREUR : ' . $e->getMessage() . "\n";
    echo "   Transaction annulée : aucune suppression conservée.\n";
    error_log('[PURGE_VENTES] Error: ' . $e->getMessage());
    exit(1);
}

exit(0);
