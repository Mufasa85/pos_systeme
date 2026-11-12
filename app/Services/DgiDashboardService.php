<?php

namespace App\Services;

use App\Exceptions\DgiReportException;

/**
 * Agrégats DGI/OSAT pour les indicateurs du tableau de bord.
 *
 * Remplace les calculs locaux (somme SQL des ventes) par les montants
 * officiels fournis par la DGI pour trois périodes : jour, semaine, mois.
 *
 * ⚠️ Trois périodes = trois appels provider (~1,7 s chacun mesurés). Le
 * rendu du tableau de bord ne doit donc PAS attendre ces appels : la page
 * s'affiche immédiatement et les indicateurs sont injectés par le frontend
 * (`GET /api/dashboard/dgi-stats`).
 *
 * Un cache court (60 s, en session) évite de rejouer les 3 appels à chaque
 * rechargement de page.
 */
class DgiDashboardService
{
    /** Durée de vie du cache, en secondes. */
    private const CACHE_TTL = 60;

    /** Nombre maximal d'appels provider simultanés (see fetchInBatches). */
    private const BATCH_SIZE = 4;

    /** Timeout maximal d'une requête provider, en secondes. */
    private const REQUEST_TIMEOUT = 20;

    /** Clé de cache en session (suffixée par boutique). */
    private const SESSION_PREFIX = 'dgi_dashboard_stats_';

    private DgiReportService $reportService;

    public function __construct(?DgiReportService $reportService = null)
    {
        $this->reportService = $reportService ?? new DgiReportService();
    }

    /**
     * Agrégats jour / semaine / mois pour une boutique.
     *
     * Une période en échec est signalée individuellement (`error`), sans faire
     * échouer les autres : une coupure réseau ne doit pas vider tout le
     * tableau de bord.
     *
     * @param int|null $shopId Boutique concernée (null = super_admin sans filtre)
     * @param string   $nif    ISF de la boutique, résolu par l'appelant
     */
    public function getStats(?int $shopId, string $nif): array
    {
        $nif = trim($nif);
        if ($nif === '') {
            throw new DgiReportException(
                'dgi_report_missing_isf',
                "L'ISF de la boutique n'est pas configuré.",
                422
            );
        }

        return $this->getStatsForShops([$shopId => $nif]);
    }

    /**
     * Agrégats jour / semaine / mois pour PLUSIEURS boutiques.
     *
     * La DGI ne sait pas agréger : chaque boutique impose son propre appel
     * provider. Les appels sont donc lancés EN PARALLÈLE (cURL multi) —
     * sinon 3 périodes × N boutiques s'additionneraient (~1,7 s par appel).
     *
     * @param array<int|string, string> $shops ISF par identifiant de boutique
     */
    public function getStatsForShops(array $shops): array
    {
        if ($shops === []) {
            throw new DgiReportException(
                'dgi_report_shop_required',
                'Veuillez sélectionner une boutique pour le rapport DGI.',
                400
            );
        }

        $cacheKey = self::SESSION_PREFIX . $this->buildCacheKey($shops);
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            $cached['meta']['cached'] = true;
            return $cached;
        }

        $periods = $this->buildPeriods();
        $results = $this->fetchAllPeriods($shops, $periods);

        $stats = [
            'success' => true,
            'periods' => $results,
            'meta' => [
                'cached' => false,
                'fetched_at' => date('Y-m-d H:i:s'),
                'periods' => $periods,
                'shops_count' => count($shops),
                // Fuseau des bornes de période : horloge du serveur (+1).
                'timezone' => $this->reportService->getRequestTimezone()->getName(),
                'utc_offset_seconds' => $this->reportService
                    ->getRequestTimezone()->getOffset(new \DateTimeImmutable('now')),
                // Fuseau de référence des factures POS (Kinshasa).
                'shop_timezone' => $this->reportService->getShopTimezone()->getName(),
            ],
        ];

        $this->writeCache($cacheKey, $stats);

        return $stats;
    }

    /**
     * Exécute les requêtes par lots de taille bornée.
     *
     * ⚠️ Le provider OSAT est mutualisé : au-delà de quelques appels
     * simultanés, il commence à répondre en erreur ou à faire expirer des
     * requêtes (mesuré : 9 appels simultanés → saturation). On limite donc
     * la concurrence à 4 requêtes par lot, avec un timeout borné : le
     * tableau de bord reste rapide et fiable.
     *
     * @param array<int, array{nif: string, start: string, end: string}> $requests
     */
    private function fetchInBatches(array $requests): array
    {
        $results = [];
        $chunks = array_chunk($requests, self::BATCH_SIZE, true);

        foreach ($chunks as $chunk) {
            $batch = $this->reportService->getReportsBatch($chunk, self::REQUEST_TIMEOUT);
            foreach ($batch as $index => $item) {
                $results[$index] = $item;
            }
        }

        return $results;
    }

    /**
     * Clé de cache : elle dépend des boutiques concernées (une autre
     * sélection ne doit pas servir un cache trompeur).
     *
     * @param array<int|string, string> $shops
     */
    private function buildCacheKey(array $shops): string
    {
        $ids = array_keys($shops);
        sort($ids);

        return implode('_', array_map('strval', $ids));
    }

    /**
     * Interroge le provider pour toutes les boutiques × toutes les périodes,
     * en parallèle, puis somme les résultats par période.
     *
     * @param array<int|string, string>                                $shops
     * @param array<string, array{start: string, end: string, label: string}> $periods
     */
    private function fetchAllPeriods(array $shops, array $periods): array
    {
        $requests = [];
        $requestMeta = [];

        foreach ($shops as $shopId => $nif) {
            $nif = trim((string)$nif);
            if ($nif === '') {
                continue;
            }

            // Token résolu UNE fois par boutique (et non par période) :
            // évite N × 3 lectures SQL sur les tables shops/company_info.
            $token = $this->reportService->resolveToken($nif);
            $timezone = $this->reportService->getRequestTimezone();

            foreach ($periods as $periodKey => $period) {
                $requests[] = [
                    'nif' => $nif,
                    'start' => $period['start'],
                    'end' => $period['end'],
                    'token' => $token,
                    // Fuseau source des bornes : le provider reçoit de l'UTC.
                    'timezone' => $timezone,
                ];
                $requestMeta[] = ['shop' => $shopId, 'period' => $periodKey];
            }
        }

        $results = [];
        foreach ($periods as $periodKey => $period) {
            $results[$periodKey] = [
                'ht' => 0.0,
                'ttc' => 0.0,
                'invoice_count' => 0,
                'error' => null,
            ];
        }

        if ($requests === []) {
            $errors = [];
            foreach ($periods as $periodKey => $period) {
                $results[$periodKey]['error'] = "L'ISF de la boutique n'est pas configuré.";
                $errors[$periodKey] = $periodKey;
            }
            return $results;
        }

        $batch = $this->fetchInBatches($requests);

        foreach ($batch as $index => $item) {
            $meta = $requestMeta[$index] ?? null;
            if ($meta === null) {
                continue;
            }
            $periodKey = $meta['period'];

            if ($item['error'] !== null || !is_array($item['report'])) {
                $message = (string)($item['error'] ?? 'Le service DGI est momentanément indisponible.');
                $results[$periodKey]['error'] = $results[$periodKey]['error'] ?? $message;
                continue;
            }

            $totals = $item['report']['totals'] ?? [];
            $results[$periodKey]['ht'] += (float)($totals['ht'] ?? 0);
            $results[$periodKey]['ttc'] += (float)($totals['ttc'] ?? 0);
            $results[$periodKey]['invoice_count'] += (int)($totals['invoice_count'] ?? 0);
        }

        return $results;
    }

    /**
     * Bornes des trois périodes, ancrées sur l'HORLOGE DU SERVEUR.
     *
     * Le serveur PHP tourne en `Europe/Berlin` (UTC+1) : les périodes du
     * tableau de bord sont donc calculées sur cette horloge (et non sur
     * l'heure de Kinshasa).
     *
     * - jour    : aujourd'hui de 00:00:00 à 23:59:59 ;
     * - semaine : les 7 derniers jours (glissante, comme le dashboard) ;
     * - mois    : du 1er au dernier jour du mois en cours.
     *
     * ⚠️ Ces bornes sont converties en UTC avant l'appel provider : le
     * provider indexe en UTC et ne comprend pas les offsets explicites
     * (cf. `DgiReportService::getRequestTimezone()`).
     *
     * @return array<string, array{start: string, end: string, label: string}>
     */
    private function buildPeriods(): array
    {
        $timezone = $this->reportService->getRequestTimezone();

        $now = new \DateTimeImmutable('now', $timezone);
        $today = $now->setTime(0, 0, 0);

        $dayEnd = $today->modify('+1 day')->modify('-1 second');
        $weekStart = $today->modify('-6 days');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month')->setTime(23, 59, 59);

        return [
            'day' => [
                'start' => $today->format('Y-m-d H:i:s'),
                'end' => $dayEnd->format('Y-m-d H:i:s'),
                'label' => 'Ventes du jour',
            ],
            'week' => [
                'start' => $weekStart->format('Y-m-d H:i:s'),
                'end' => $dayEnd->format('Y-m-d H:i:s'),
                'label' => 'Cette semaine',
            ],
            'month' => [
                'start' => $monthStart->format('Y-m-d H:i:s'),
                'end' => $monthEnd->format('Y-m-d H:i:s'),
                'label' => 'Ce mois',
            ],
        ];
    }

    /**
     * Lit une entrée de cache en session.
     */
    private function readCache(string $key): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $cached = $_SESSION[$key] ?? null;
        if (!is_array($cached) || !isset($cached['expires_at']) || $cached['expires_at'] <= time()) {
            return null;
        }

        return $cached['stats'] ?? null;
    }

    /**
     * Écrit une entrée de cache en session.
     */
    private function writeCache(string $key, array $stats): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[$key] = [
            'stats' => $stats,
            'expires_at' => time() + self::CACHE_TTL,
        ];
    }
}
