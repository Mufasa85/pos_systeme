<?php
/**
 * Applique une migration SQL sur la base courante (celle du `phpunit.xml`
 * ou de `config/config.php` selon les variables d'environnement).
 *
 * Usage :
 *   php scripts/apply_migration.php migrations/2026_11_05_hotel_facture_details_link.sql
 *
 * Les migrations contiennent des blocs PREPARE/EXECUTE (idempotence via
 * INFORMATION_SCHEMA) : on exécute le fichier statement par statement en
 * ignorant les séparateurs `;` présents dans les chaînes SQL.
 */

require_once __DIR__ . '/../config/config.php';

$file = $argv[1] ?? null;
if (!$file || !is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/apply_migration.php <fichier.sql>\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Les migrations utilisent PREPARE/EXECUTE/DEALLOCATE : sans buffering,
        // PDO refuse d'enchaîner les statements (« unbuffered queries are
        // active », erreur 2014).
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(1);
}

$sql = (string)file_get_contents($file);

// Découpe naïve en statements : on ignore les `;` situés dans une chaîne
// littérale (les migrations contiennent des COMMENT '...;...').
//
// ⚠️ Les commentaires de LIGNE (`-- ...`) sont gérés ICI (et pas après le
// découpage) : un commentaire contient souvent une apostrophe (« l'hôtel »)
// qui ferait basculer le parseur en mode "chaîne" et ferait avaler tout le
// fichier dans un seul statement.
$statements = [];
$current    = '';
$inString   = false;
$quoteChar  = '';
$len        = strlen($sql);

for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];

    // Commentaire de ligne : on saute jusqu'au retour à la ligne.
    // (`--` doit être suivi d'un espace ou d'un EOL, sinon c'est un opérateur.)
    if (!$inString && $ch === '-' && ($sql[$i + 1] ?? '') === '-'
        && in_array($sql[$i + 2] ?? "\n", [' ', "\n", "\r", "\t"], true)) {
        while ($i < $len && $sql[$i] !== "\n") {
            $i++;
        }
        $current .= "\n";
        continue;
    }

    if ($inString) {
        if ($ch === '\\') {           // caractère d'échappement MySQL
            $current .= $ch . ($sql[$i + 1] ?? '');
            $i++;
            continue;
        }
        if ($ch === $quoteChar) {
            // Apostrophe doublée ('') = apostrophe échappée : on reste dedans.
            if (($sql[$i + 1] ?? '') === $quoteChar) {
                $current .= $ch . $quoteChar;
                $i++;
                continue;
            }
            $inString = false;
        }
        $current .= $ch;
        continue;
    }

    if ($ch === "'" || $ch === '"' || $ch === '`') {
        $inString  = true;
        $quoteChar = $ch;
        $current  .= $ch;
        continue;
    }

    if ($ch === ';') {
        if (trim($current) !== '') {
            $statements[] = trim($current);
        }
        $current = '';
        continue;
    }

    $current .= $ch;
}
if (trim($current) !== '') {
    $statements[] = trim($current);
}

// Nettoyage final : on retire les lignes vides résiduelles.
$statements = array_values(array_filter(array_map('trim', $statements), static function (string $s): bool {
    return $s !== '';
}));

$ok = 0;
foreach ($statements as $stmt) {
    try {
        // `query()` + `fetchAll()` : certains statements des migrations
        // (`SET @x := (SELECT ...)`, `PREPARE ...`) laissent un jeu de
        // résultats ouvert. Sans le drained, le statement suivant échoue
        // avec « Cannot execute queries while other unbuffered queries are
        // active » (erreur 2014).
        $result = $pdo->query($stmt);
        if ($result !== false) {
            $result->fetchAll();
            $result->closeCursor();
        }
        $ok++;
    } catch (PDOException $e) {
        fwrite(STDERR, "ECHEC sur le statement :\n" . substr($stmt, 0, 300) . "\n"
            . '-> ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Migration appliquée sur " . DB_NAME . " : {$ok} statement(s) exécuté(s).\n";