<?php

/**
 * Script de resynchronisation des factures DGI en attente
 *
 * FILET DE SÉCURITÉ du webhook OSAT (POST /api/dgi/update).
 *
 * Quand la DGI est indisponible, l'API OSAT renvoie un fallback et met la
 * demande en attente de son côté (cas "update", store_homologation = 2).
 * OSAT revient ensuite vers nous pour livrer la résolution. Ce script
 * couvre les cas où cette livraison n'a pas abouti :
 *   - l'app était hors ligne au moment du callback ;
 *   - le réseau a coupé la requête ;
 *   - OSAT n'a pas (encore) notre URL, ou n'a pas pu l'appeler.
 *
 * Dans ces cas, on relit nous-mêmes la facture auprès d'OSAT
 * (GET /dgi/facture/) et on écrit les éléments de sécurité en base.
 *
 * Usage : php scripts/sync_dgi_pending.php [limite]
 * Cron  : toutes les 10 minutes — ex. "0,10,20,30,40,50 * * * *" suivi de
 *         "php /chemin/vers/scripts/sync_dgi_pending.php"
 *
 * Le script tourne en CLI : il n'y a pas de session, il accède donc
 * directement à toutes les boutiques (comme archive_data.php).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$limit = isset($argv[1]) ? max(1, (int)$argv[1]) : 50;

echo '[' . date('Y-m-d H:i:s') . "] Resynchronisation DGI (limite : {$limit})\n";

try {
    $service = new \App\Services\DgiSyncService();

    $pending = $service->findPendingSales($limit);

    if (count($pending) === 0) {
        echo "  ✓ Aucune facture en attente\n";
        exit(0);
    }

    echo '  → ' . count($pending) . " facture(s) en attente\n\n";

    $synced = 0;
    $failed = 0;

    foreach ($pending as $sale) {
        $saleId = (int)$sale['id'];
        $numero = $sale['numero_facture'] ?? ('#' . $saleId);

        $result = $service->syncSale($saleId);

        if ($result['success'] && $result['status'] === 'synced') {
            $synced++;
            echo "  ✓ {$numero} synchronisée\n";
        } else {
            $failed++;
            echo "  … {$numero} : {$result['status']} — {$result['message']}\n";
        }
    }

    echo "\n  Résultat : {$synced} synchronisée(s), {$failed} en attente\n";
    echo '[' . date('Y-m-d H:i:s') . "] Fin\n";
} catch (\Throwable $e) {
    echo '[ERREUR] ' . $e->getMessage() . "\n";
    error_log('[DGI SYNC] Exception cron : ' . $e->getMessage());
    exit(1);
}
