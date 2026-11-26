<?php
/**
 * AUDIT : requêtes potentiellement non bornées sur les tables qui grossissent.
 *
 * À 50 000 produits / 85 000 lots, une requête sans LIMIT ne coûte pas
 * "un peu plus" — elle charge TOUT et le PHP construit un tableau géant
 * avant de l'envoyer.
 *
 * Ce script liste les requêtes SELECT/UPDATE/DELETE des modèles qui
 * n'ont ni LIMIT ni borne logique, pour les tables à forte croissance.
 *
 * Ne modifie rien : c'est un audit en lecture seule.
 */

$root = dirname(__DIR__);

// Tous les modèles du dossier : l'audit ne doit pasdependre d'une
// liste maintenue a la main (c'est elle qui laisse passer les oublis).
$targets = [];
foreach (glob($root . '/app/models/*.php') as $f) {
    $targets[] = str_replace($root . '/', '', $f);
}

echo "=== 1. Requêtes SANS LIMIT dans les modèles ===\n\n";

foreach ($targets as $file) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    // On découpe en méthodes pour attribuer le finding à la bonne fonction.
    $methods = preg_split('/(?=\n    (?:public|private|protected) function )/', $src);

    foreach ($methods as $chunk) {
        if (!preg_match('/public function (\w+)/', $chunk, $m)) {
            continue;
        }
        $fn = $m[1];

        // On cherche les SELECT/DELETE/UPDATE exécutés par fetchAll/execute.
        $hasSelect = preg_match('/\b(SELECT|UPDATE|DELETE)\b/i', $chunk);
        if (!$hasSelect) {
            continue;
        }

        $hasLimit = preg_match('/\bLIMIT\b/i', $chunk);
        $isCount   = preg_match('/COUNT\s*\(/i', $chunk) && !preg_match('/fetchAll|->all\(/', $chunk);
        $isSingle  = preg_match('/fetch(One|Column|Row)\s*\(/', $chunk) && !preg_match('/fetchAll/', $chunk);
        $isAggregate = preg_match('/\b(SUM|AVG|MIN|MAX|GROUP_CONCAT)\s*\(/i', $chunk);

        if ($hasLimit || $isCount || $isSingle || $isAggregate) {
            continue;
        }

        // Requête multi-lignes susceptible de renvoyer beaucoup de lignes.
        if (!preg_match('/fetchAll|->all\(|getResult|->fetch\(/', $chunk)) {
            continue;
        }

        /*
         * Faux positif : la requête filtre sur une CLÉ UNIQUE (id,
         * numero, code...), donc elle renvoie au plus une ligne. Le
         * "LIMIT" n'y changerait rien.
         */
        if (preg_match('/WHERE\s+\w*\.?\w*\s*(=|IN)\s*[:?]/i', $chunk)
            && !preg_match('/WHERE[^;]*\b(shop_id|categorie|category_id|statut|etat|date|type|role)\b/i', $chunk)) {
            continue;
        }

        // On extrait le SELECT pour comprendre ce qui est chargé.
        $sel = '';
        if (preg_match('/(SELECT\b.*?)(?:\"|\'|;|\n\s*\n)/is', $chunk, $sm)) {
            $sel = preg_replace('/\s+/', ' ', trim($sm[1]));
        }
        $sel = mb_substr($sel, 0, 150);

        /*
         * Périmètre : le module POS (catalogue, ventes, stock, lots).
         * L'hôtel et la paie sont un chantier distinct — les signaler
         * ici noierait le rapport sous des items hors sujet.
         */
        if (!preg_match('/^(Product|ProductBatch|Sale|Stock|Payment|Dgi|Vente)/', basename($file))) {
            continue;
        }

        printf("  ⚠️  %s::%s()\n", basename($file), $fn);
        printf("      %s\n\n", $sel !== '' ? $sel : '(SQL non extractible)');
    }
}

echo "=== 2. Endpoints qui exposent ces listes au navigateur ===\n\n";
$apiFiles = glob($root . '/app/controllers/*.php');
foreach ($apiFiles as $f) {
    $src = file_get_contents($f);
    $methods = preg_split('/(?=\n    (?:public|private|protected) function )/', $src);
    foreach ($methods as $chunk) {
        if (!preg_match('/public function (\w+)/', $chunk, $m)) {
            continue;
        }
        $fn = $m[1];
        if (!preg_match('/->json\s*\(/', $chunk)) {
            continue;
        }
        // On cherche les appels à des méthodes potentiellement non bornées.
        if (!preg_match('/(getAll|getExpiringSoon|getExpired|getAllFiltered|findByShop|getProducts)\s*\(/', $chunk, $mm)) {
            continue;
        }
        printf("  %s::%s() → appelle %s()\n", basename($f), $fn, $mm[1]);
    }
}

echo "\n=== 3. Les vues qui font un foreach sans limite ===\n\n";
$views = glob($root . '/app/views/*.php');
foreach ($views as $v) {
    $src = file_get_contents($v);
    if (preg_match('/foreach\s*\(\s*\$(expiration_alerts|expired_alerts)/', $src, $m)) {
        printf("  app/views/%s : foreach(\$%s) sans limite\n", basename($v), $m[1]);
    }
}
