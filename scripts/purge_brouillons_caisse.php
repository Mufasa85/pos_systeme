<?php

/**
 * Purge des brouillons de panier abandonnés.
 *
 * Supprime les brouillons de caisse 'OUVERT' (jamais facturés) de plus de
 * N jours (30 par défaut, cf. CaisseBrouillon::PURGE_JOURS).
 *
 * ⚠️ Règles :
 *   - Seuls les brouillons 'ouvert' sont purgés. Un brouillon 'converti'
 *     est une trace métier liée à une facture : il n'est JAMAIS supprimé.
 *   - Aucune facture n'est touchée par cette opération.
 *   - Le seuil est configurable : `--jours=7` ou la variable d'env
 *     `BROUILLONS_PURGE_JOURS`.
 *
 * Usage :
 *   php scripts/purge_brouillons_caisse.php
 *   php scripts/purge_brouillons_caisse.php --jours=7
 *
 * Cron (quotidien, à 3h du matin) :
 *   0 3 * * * php /chemin/vers/scripts/purge_brouillons_caisse.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\CaisseBrouillon;

// ── Seuil de purge (option CLI, variable d'env, défaut 30 jours) ──
$jours = (int) (getenv('BROUILLONS_PURGE_JOURS') ?: CaisseBrouillon::PURGE_JOURS);

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--jours=(\d+)$/', $arg, $m)) {
        $jours = (int) $m[1];
    }
}

if ($jours < 1) {
    $jours = CaisseBrouillon::PURGE_JOURS;
}

echo '[' . date('Y-m-d H:i:s') . "] Purge des brouillons de caisse\n";
echo "  → Seuil : {$jours} jours\n";
echo "  → Statut purgé : '" . CaisseBrouillon::STATUS_OUVERT . "'\n";
echo "  → Statut CONSERVÉ : '" . CaisseBrouillon::STATUS_CONVERTI . "' (liés à une facture)\n\n";

try {
    $model = new CaisseBrouillon();
    $supprimes = $model->purgeExpired($jours);

    echo "  Brouillons purgés : {$supprimes}\n";

    if ($supprimes > 0) {
        echo "  ✅ Purge terminée.\n";
    } else {
        echo "  ✅ Aucun brouillon à purger.\n";
    }
} catch (\Throwable $e) {
    echo "  ❌ Erreur pendant la purge : " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
