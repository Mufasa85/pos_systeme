<?php

namespace App\Services;

use App\Exceptions\DgiReportException;
use Closure;
use DateTimeImmutable;

/**
 * Client des agrégats de rapports DGI fournis par OSAT.
 *
 * Le provider est appelé uniquement depuis le backend. Le transport HTTP est
 * injectable pour permettre des tests déterministes sans accès au réseau.
 */
class DgiReportService
{
    /**
     * Taxonomie DGI A-P. Elle reprend les libellés déjà utilisés dans les
     * tickets de facture du POS.
     */
    private const TAX_CATEGORIES = [
        'A' => ['label' => 'Exonéré et hors champ', 'rate' => 0.0],
        'B' => ['label' => 'Taxable', 'rate' => 16.0],
        'C' => ['label' => 'Taxable', 'rate' => 5.0],
        'D' => ['label' => 'Régimes dérogatoires TVA', 'rate' => 0.0],
        'E' => ['label' => 'Exportation et opérations assimilées', 'rate' => 0.0],
        'F' => ['label' => 'TVA marché public à financement extérieur', 'rate' => 16.0],
        'G' => ['label' => 'TVA marché public à financement extérieur', 'rate' => 5.0],
        'H' => ['label' => 'Consignation/déconsignation emballage', 'rate' => 0.0],
        'I' => ['label' => 'Garantie et caution', 'rate' => 0.0],
        'J' => ['label' => 'Débours', 'rate' => 0.0],
        'K' => ['label' => 'Opérations réalisées par les non-assujettis', 'rate' => 0.0],
        'L' => ['label' => 'Prélèvements sur les ventes', 'rate' => 0.0],
        'M' => ['label' => 'Ventes réglementées TVA spécifique', 'rate' => 0.0],
        'N' => ['label' => 'TVA spécifique', 'rate' => 0.0],
        'O' => ['label' => 'Taxable', 'rate' => 1.0],
        'P' => ['label' => 'TVA marché public à financement extérieur', 'rate' => 1.0],
    ];

    /**
     * Types de facture DGI, avec leur libellé.
     *
     * Exposée publiquement : la vue et le frontend s'en servent pour
     * construire le filtre par type de facture sans dupliquer la liste.
     */
    public const INVOICE_TYPES = [
        'FV' => 'Facture de Vente',
        'EV' => "Fac de Vente à l'exportation",
        'FT' => "Facture d'acompte",
        'FA' => "Facture d'avoir",
        'EA' => "Fac d'avoir à l'exportation",
        'ET' => "Fac d'acompte à l'exportation",
    ];

    /**
     * Types de rapport acceptés par le provider OSAT.
     *
     * Le provider distingue le X (période personnalisée, en lecture seule) du
     * Z (période de clôture) via le paramètre `rapport=` de l'URL.
     */
    public const REPORT_TYPE_X = 'x';
    public const REPORT_TYPE_Z = 'z';

    /**
     * Modes de paiement DGI, avec leur libellé lisible.
     *
     * Le provider renvoie les modes sous forme de clés techniques
     * (`CARTEBANCAIRE`, `ESPECES`, ...) : on les traduit pour l'affichage.
     */
    private const PAYMENT_MODES = [
        'ESPECES' => 'Espèces',
        'CARTEBANCAIRE' => 'Carte bancaire',
        'MOBILEMONEY' => 'Mobile Money',
        'CREDIT' => 'Crédit',
        'CHEQUES' => 'Chèques',
        'VIREMENT' => 'Virement',
        'CHEQUE' => 'Chèque',
        'ESPECE' => 'Espèces',
        'CARTE' => 'Carte bancaire',
    ];

    private Closure $transport;

    /** Le transport est-il celui par défaut (cURL) ? */
    private bool $usesDefaultTransport;

    public function __construct(?callable $transport = null)
    {
        $this->usesDefaultTransport = $transport === null;
        $this->transport = $transport !== null
            ? Closure::fromCallable($transport)
            : Closure::fromCallable([$this, 'request']);
    }

    /**
     * Récupère et normalise un rapport DGI pour une boutique et une période.
     *
     * @param string[] $types Types de facture à conserver (vide = tous).
     * @param string|null $token Token d'accès de la boutique (null = résolu
     *                          depuis `shops.token` / `company_info.token`).
     * @param \DateTimeZone|null $sourceTimezone Fuseau des bornes de période
     *                          (null = fuseau de la boutique).
     * @param string $reportType Type de rapport provider (`x` ou `z`).
     * @param bool $superAdmin Le provider doit-il agréger les boutiques
     *                         gérées par le super_admin ?
     *
     * @return array{totals: array, tax_groups: array, by_invoice_type: array, warnings: array}
     */
    public function getReport(
        string $nif,
        string $startDate,
        string $endDate,
        array $types = [],
        ?string $token = null,
        ?\DateTimeZone $sourceTimezone = null,
        string $reportType = self::REPORT_TYPE_X,
        bool $superAdmin = false
    ): array {
        if (!$this->isEnabled()) {
            throw new DgiReportException(
                'dgi_report_disabled',
                'Les rapports DGI ne sont pas activés sur cette installation.',
                503
            );
        }

        $nif = trim($nif);
        if ($nif === '') {
            throw new DgiReportException(
                'dgi_report_missing_isf',
                "L'ISF de la boutique n'est pas configuré.",
                422
            );
        }

        $url = $this->buildProviderUrl(
            $nif,
            $startDate,
            $endDate,
            $token,
            $sourceTimezone,
            $reportType,
            $superAdmin
        );

        $startedAt = microtime(true);
        try {
            $response = ($this->transport)($url);
        } catch (DgiReportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[DgiReportService] Transport DGI indisponible: ' . $e->getMessage());
            throw new DgiReportException(
                'dgi_provider_unavailable',
                'Le service DGI est momentanément indisponible.',
                502,
                $e
            );
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return $this->buildReportFromResponse($response, $startDate, $endDate, $durationMs, $types);
    }

    /**
     * Valide une réponse provider et la normalise en rapport DGI.
     *
     * Factorisé hors de `getReport()` pour être réutilisé par les appels
     * parallèles (`getReportsBatch`).
     *
     * @param array{status?: int, body?: string} $response
     * @param string[] $types Types de facture à conserver (vide = tous).
     */
    private function buildReportFromResponse(
        array $response,
        string $startDate,
        string $endDate,
        int $durationMs,
        array $types = []
    ): array {
        if (!array_key_exists('body', $response)) {
            throw new DgiReportException(
                'dgi_provider_invalid_response',
                'La réponse du service DGI est invalide.',
                502
            );
        }

        $httpStatus = (int)($response['status'] ?? 0);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new DgiReportException(
                'dgi_provider_http_error',
                'Le service DGI a renvoyé une erreur.',
                502
            );
        }

        $payload = json_decode((string)$response['body'], true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new DgiReportException(
                'dgi_provider_invalid_json',
                'La réponse du service DGI contient un JSON invalide.',
                502
            );
        }

        if (array_key_exists('success', $payload) && empty($payload['success'])) {
            throw new DgiReportException(
                'dgi_provider_rejected',
                'Le service DGI a refusé la demande de rapport.',
                502
            );
        }

        $report = $this->normalizePayload($payload, $types);
        [$utcStart, $offset] = $this->toProviderUtc($startDate);
        [$utcEnd] = $this->toProviderUtc($endDate);
        $report['meta'] = [
            'duration_ms' => $durationMs,
            'fetched_at' => date('Y-m-d H:i:s'),
            'timezone' => $this->getShopTimezone()->getName(),
            'utc_offset_seconds' => $offset,
            'period_start' => $utcStart,
            'period_end' => $utcEnd,
        ];

        return $report;
    }

    /**
     * Récupère plusieurs rapports DGI EN PARALLÈLE.
     *
     * Indispensable pour le tableau de bord en mode multi-boutique : sans
     * cela, 3 périodes × N boutiques seAdditionnent (~1,7 s par appel).
     * En parallèle, le délai total reste celui de l'appel le plus lent.
     *
     * ⚠️ Le provider est mutualisé : sous forte concurrence (9 appels
     * simultanés), il peut renvoyer une erreur transitoire ou faire expirer
     * une requête. Les échecs transitoires sont donc REJOUÉS
     * séquentiellement (cf. retryTransient), avec un léger délai.
     *
     * @param array<int, array{nif: string, start: string, end: string}> $requests
     * @param int|null $timeoutSeconds Timeout global par requête (null = illimité)
     *
     * @return array<int, array{report: ?array, error: ?string}> Un résultat par
     *         requête, dans le même ordre. Un échec isolé n'annule pas les
     *         autres.
     */
    public function getReportsBatch(array $requests, ?int $timeoutSeconds = null): array
    {
        if ($requests === []) {
            return [];
        }

        if (!$this->isEnabled()) {
            throw new DgiReportException(
                'dgi_report_disabled',
                'Les rapports DGI ne sont pas activés sur cette installation.',
                503
            );
        }

        // Un transport injecté (tests) ne peut pas être parallélisé : on
        // tombe sur des appels séquentiels.
        if ($this->isInjectedTransport()) {
            return $this->getReportsBatchSequential($requests);
        }

        if (!function_exists('curl_multi_init')) {
            return $this->getReportsBatchSequential($requests);
        }

        return $this->getReportsBatchParallel($requests, $timeoutSeconds);
    }

    /**
     * Le transport est-il injecté (donc non-parallélisable) ?
     *
     * ⚠️ On compare un drapeau, pas les Closures : deux Closures ne sont
     * jamais égales en PHP, un test d'égalité serait toujours faux.
     */
    private function isInjectedTransport(): bool
    {
        return !$this->usesDefaultTransport;
    }

    /**
     * Type de rapport provider porté par une requête de lot.
     *
     * @param array<int, array<string, mixed>> $request
     */
    private function readRequestReportType(array $request): string
    {
        $type = isset($request['report_type']) ? (string)$request['report_type'] : '';

        return $type === '' ? self::REPORT_TYPE_X : $type;
    }

    /**
     * Le lot doit-il être agrégé par le provider (super_admin) ?
     *
     * @param array<int, array<string, mixed>> $request
     */
    private function readRequestSuperAdmin(array $request): bool
    {
        return !empty($request['super_admin']);
    }

    /**
     * Appels séquentiels (transport injecté ou cURL multi indisponible).
     *
     * @param array<int, array{nif: string, start: string, end: string}> $requests
     */
    private function getReportsBatchSequential(array $requests): array
    {
        $results = [];
        foreach ($requests as $index => $request) {
            try {
                $report = $this->getReport(
                    $request['nif'],
                    $request['start'],
                    $request['end'],
                    [],
                    isset($request['token']) ? (string)$request['token'] : null,
                    isset($request['timezone']) && $request['timezone'] instanceof \DateTimeZone
                        ? $request['timezone']
                        : null,
                    $this->readRequestReportType($request),
                    $this->readRequestSuperAdmin($request)
                );
                $results[$index] = ['report' => $report, 'error' => null];
            } catch (DgiReportException $e) {
                $results[$index] = ['report' => null, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Appels HTTP simultanés via cURL multi.
     *
     * @param array<int, array{nif: string, start: string, end: string}> $requests
     * @param int|null $timeoutSeconds Timeout global par requête (null = illimité)
     */
    private function getReportsBatchParallel(array $requests, ?int $timeoutSeconds = null): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $startedAt = microtime(true);

        foreach ($requests as $index => $request) {
            $nif = trim((string)$request['nif']);
            if ($nif === '') {
                curl_multi_remove_handle($multi, $handles[$index] ?? null);
                unset($handles[$index]);
                continue;
            }

            $url = $this->buildProviderUrl(
                $nif,
                $request['start'],
                $request['end'],
                isset($request['token']) ? (string)$request['token'] : null,
                isset($request['timezone']) && $request['timezone'] instanceof \DateTimeZone
                    ? $request['timezone']
                    : null,
                $this->readRequestReportType($request),
                $this->readRequestSuperAdmin($request)
            );
            $ch = curl_init($url);
            curl_setopt_array($ch, $this->buildCurlOptions($timeoutSeconds));
            curl_multi_add_handle($multi, $ch);
            $handles[$index] = $ch;
        }

        // Exécution simultanée : on boucle jusqu'à ce que tous les appels
        // soient terminés.
        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $results = [];

        foreach ($requests as $index => $request) {
            $ch = $handles[$index] ?? null;
            if ($ch === null) {
                $results[$index] = [
                    'report' => null,
                    'error' => "L'ISF de la boutique n'est pas configuré.",
                ];
                continue;
            }

            $body = curl_multi_getcontent($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            try {
                if ($body === false || $body === '') {
                    $this->throwTransportError($curlErrno, $curlError);
                }
                $results[$index] = [
                    'report' => $this->buildReportFromResponse(
                        ['status' => $httpStatus, 'body' => (string)$body],
                        $request['start'],
                        $request['end'],
                        $durationMs
                    ),
                    'error' => null,
                ];
            } catch (DgiReportException $e) {
                // Erreur transitoire (provider saturé) : on rejoue en
                // séquentiel, ce qui est plus doux pour le provider.
                $results[$index] = $this->retryTransient($request, $e);
            }
        }

        curl_multi_close($multi);

        return $results;
    }

    /**
     * Rejoue une requête échouée de façon transitoire.
     *
     * Le provider OSAT est mutualisé : sous concurrence, une requête peut
     * échouer alors que la même requête aboutit quelques secondes plus tard.
     * On retente donc 2 fois, avec un délai croissant, avant d'abandonner.
     *
     * @param array{nif: string, start: string, end: string} $request
     *
     * @return array{report: ?array, error: ?string}
     */
    private function retryTransient(array $request, DgiReportException $error): array
    {
        $code = $error->getErrorCode();
        $isTransient = in_array($code, [
            'dgi_provider_unavailable',
            'dgi_provider_timeout',
            'dgi_provider_http_error',
            'dgi_provider_invalid_response',
        ], true);

        if (!$isTransient) {
            return ['report' => null, 'error' => $error->getMessage()];
        }

        $attempts = 2;
        $lastError = $error->getMessage();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            // Attente croissante : laisse le provider respirer.
            sleep($attempt);

            try {
                $report = $this->getReport(
                    $request['nif'],
                    $request['start'],
                    $request['end'],
                    [],
                    isset($request['token']) ? (string)$request['token'] : null,
                    isset($request['timezone']) && $request['timezone'] instanceof \DateTimeZone
                        ? $request['timezone']
                        : null,
                    $this->readRequestReportType($request),
                    $this->readRequestSuperAdmin($request)
                );
                return ['report' => $report, 'error' => null];
            } catch (DgiReportException $e) {
                $lastError = $e->getMessage();
            } catch (\Throwable $e) {
                $lastError = 'Le service DGI est momentanément indisponible.';
            }
        }

        error_log('[DgiReportService] Echec apres retries: ' . $lastError);

        return ['report' => null, 'error' => $lastError];
    }

    /**
     * Indique si une période contient au moins une facture DGI.
     *
     * Une clôture sur une période vide est refusée : elle figerait une
     * période sans aucune vente et empêcherait toute clôture ultérieure
     * sur les ventes suivantes.
     */
    public function hasInvoices(array $report): bool
    {
        return (int)($report['totals']['invoice_count'] ?? 0) > 0;
    }

    public function isEnabled(): bool
    {
        return !defined('DGI_REPORT_ENABLED') || (bool)constant('DGI_REPORT_ENABLED');
    }

    /**
     * Construit l'URL provider avec les dates au format attendu par OSAT.
     *
     * Le token d'accès de la boutique (paramètre `token=`) est ajouté s'il est
     * connu — même convention que la facturation OSAT
     * (`DgiSyncService::fetchFacture`).
     *
     * @param string $reportType Type de rapport provider (`x` ou `z`).
     * @param bool $superAdmin Ajouter `super_admin=true` : le provider renvoie
     *                         alors les données de TOUTES les boutiques gérées
     *                         par le super_admin (agrégation côté provider,
     *                         sans boucle HTTP côté POS).
     */
    public function buildProviderUrl(
        string $nif,
        string $startDate,
        string $endDate,
        ?string $token = null,
        ?\DateTimeZone $sourceTimezone = null,
        string $reportType = self::REPORT_TYPE_X,
        bool $superAdmin = false
    ): string {
        $baseUrl = defined('DGI_REPORT_API_URL')
            ? (string)constant('DGI_REPORT_API_URL')
            : 'https://osat-energie.com/dgi/rapport/index.php';

        $url = $baseUrl
            . (str_contains($baseUrl, '?') ? '&' : '?')
            . 'nif=' . rawurlencode(trim($nif))
            . '&debut=' . $this->encodeProviderValue($this->formatProviderDate($startDate, $sourceTimezone))
            . '&fin=' . $this->encodeProviderValue($this->formatProviderDate($endDate, $sourceTimezone))
            . '&rapport=' . rawurlencode($this->normalizeProviderReportType($reportType));

        if ($superAdmin) {
            $url .= '&super_admin=true';
        }

        $token = $token ?? $this->resolveToken($nif);
        if ($token !== '') {
            $url .= '&token=' . rawurlencode($token);
        }

        return $url;
    }

    /**
     * Normalise le type de rapport provider.
     *
     * Seuls `x` et `z` sont acceptés : toute autre valeur retombe sur `x`
     * (le rapport en lecture seule, le plus courant).
     */
    private function normalizeProviderReportType(string $reportType): string
    {
        $type = strtolower(trim($reportType));

        return $type === self::REPORT_TYPE_Z ? self::REPORT_TYPE_Z : self::REPORT_TYPE_X;
    }

    /**
     * Résout le token d'accès DGI d'une boutique.
     *
     * ⚠️ Le token doit appartenir au même émetteur que l'ISF : on le résout
     * donc à partir de l'ISF interrogé, pas de la session (sinon un
     * super_admin, sans boutique, ne pourrait pas interroger les autres
     * boutiques).
     *
     * Cascade (identique à `DgiSyncService::resolveToken()`) :
     *   1. `company_info.token` si l'ISF de l'entreprise correspond ;
     *   2. `shops.token` de la boutique portant cet ISF ;
     *   3. `company_info.token` (repli).
     *
     * Le token n'est jamais transmis au navigateur : il n'apparaît que dans
     * l'appel backend au provider.
     */
    public function resolveToken(string $nif): string
    {
        $nif = trim($nif);
        if ($nif === '') {
            return '';
        }

        try {
            $companyInfo = (new \App\Models\CompanyInfo())->get();
        } catch (\Throwable $e) {
            error_log('[DgiReportService] Lecture company_info impossible: ' . $e->getMessage());
            $companyInfo = [];
        }

        if (trim((string)($companyInfo['isf'] ?? '')) === $nif) {
            $token = trim((string)($companyInfo['token'] ?? ''));
            if ($token !== '') {
                return $token;
            }
        }

        try {
            $shop = (new \App\Models\Shop())->findByIsf($nif);
            if ($shop && !empty($shop['token'])) {
                return trim((string)$shop['token']);
            }
        } catch (\Throwable $e) {
            error_log('[DgiReportService] Lecture boutique impossible: ' . $e->getMessage());
        }

        return trim((string)($companyInfo['token'] ?? ''));
    }

    /**
     * Encode une valeur de query string en conservant `/` et `:` lisibles,
     * comme dans les URL communiquées par le provider.
     */
    private function encodeProviderValue(string $value): string
    {
        return str_replace(
            ['%2F', '%3A'],
            ['/', ':'],
            rawurlencode($value)
        );
    }

    /**
     * Convertit une date SQL vers le format provider `d/m/Y H:i:s`, en UTC.
     *
     * ⚠️ La DGI horodate ses factures en UTC, alors que le POS enregistre en
     * heure locale. Mesuré sur la boutique SuperMarché (ISF A1720894F) le
     * 10/11/2026, les 6 factures du jour étaient classées par la DGI avec un
     * décalage constant de −59/−60 minutes par rapport à leur heure d'émission
     * POS. Sans cette conversion, une période demandée en heure locale ne
     * contient jamais les factures attendues (et une clôture peut figer une
     * période vide).
     *
     * @param string      $date     Date SQL à convertir.
     * @param \DateTimeZone|null $sourceTimezone Fuseau de la date source
     *        (null = fuseau boutique). Le provider, lui, reçoit TOUJOURS de
     *        l'UTC : il ne comprend pas les offsets explicites.
     */
    public function formatProviderDate(string $date, ?\DateTimeZone $sourceTimezone = null): string
    {
        $parsed = $this->parseDate($date, $sourceTimezone);

        return $parsed->setTimezone(new \DateTimeZone('UTC'))->format('d/m/Y H:i:s');
    }

    /**
     * Convertit une date SQL en UTC, en exposant le décalage.
     *
     * @return array{0: string, 1: int} [date UTC formatée, décalage en secondes]
     */
    public function toProviderUtc(string $date, ?\DateTimeZone $sourceTimezone = null): array
    {
        $parsed = $this->parseDate($date, $sourceTimezone);
        $utc = $parsed->setTimezone(new \DateTimeZone('UTC'));

        return [$utc->format('d/m/Y H:i:s'), $parsed->getOffset() - $utc->getOffset()];
    }

    /**
     * Analyse une date SQL, en tenant compte du fuseau source.
     *
     * @param \DateTimeZone|null $sourceTimezone Fuseau de la date source
     *        (null = fuseau de la boutique).
     */
    private function parseDate(string $date, ?\DateTimeZone $sourceTimezone = null): DateTimeImmutable
    {
        $value = trim($date);
        $timezone = $sourceTimezone ?? $this->getShopTimezone();

        // ⚠️ Le fuseau est passé AU PARSEUR : sans cela, `createFromFormat()`
        // interpréterait l'heure dans le fuseau du serveur (Europe/Berlin),
        // ce qui cumulerait deux décalages.
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        if ($parsed !== false && $parsed->format('Y-m-d H:i:s') !== $value) {
            $parsed = false;
        }
        if ($parsed === false) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
            if ($parsed !== false && $parsed->format('Y-m-d') !== $value) {
                $parsed = false;
            }
        }
        if ($parsed === false) {
            throw new DgiReportException(
                'dgi_report_invalid_date',
                'La période du rapport DGI est invalide.',
                400
            );
        }

        return $parsed;
    }

    /**
     * Fuseau horaire de la boutique (heure de référence des factures POS).
     */
    public function getShopTimezone(): \DateTimeZone
    {
        $timezone = defined('DGI_REPORT_TIMEZONE')
            ? trim((string)constant('DGI_REPORT_TIMEZONE'))
            : '';
        if ($timezone === '') {
            $timezone = 'Africa/Kinshasa';
        }

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            error_log('[DgiReportService] Fuseau DGI invalide, repli sur Africa/Kinshasa: ' . $timezone);
            return new \DateTimeZone('Africa/Kinshasa');
        }
    }

    /**
     * Fuseau horaire des PÉRIODES demandées.
     *
     * Deux notions distinctes :
     *
     * - `getShopTimezone()` : l'heure de référence des factures POS
     *   (`Africa/Kinshasa`), utilisée par les rapports de clôture ;
     * - `getRequestTimezone()` : le fuseau des bornes de période construites
     *   par l'appelant. Le tableau de bord l'ancre sur l'horloge du SERVEUR
     *   (`Europe/Berlin`, soit UTC+1), qui est le fuseau PHP par défaut.
     *
     * ⚠️ Quel que soit le fuseau source, les dates envoyées au provider
     * restent en UTC : le provider indexe ses factures en UTC et ne
     * comprend pas les offsets explicites (`+01:00` → réponse illisible,
     * mesuré). Convertir est donc obligatoire, sinon la période demandée
     * serait décalée d'une heure et la première heure de chaque journée
     * serait perdue.
     */
    public function getRequestTimezone(): \DateTimeZone
    {
        $timezone = defined('DGI_REPORT_REQUEST_TIMEZONE')
            ? trim((string)constant('DGI_REPORT_REQUEST_TIMEZONE'))
            : '';
        if ($timezone === '') {
            // Fuseau par défaut du serveur PHP (Europe/Berlin => UTC+1).
            $timezone = date_default_timezone_get();
        }

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            error_log('[DgiReportService] Fuseau de requete invalide, repli serveur: ' . $timezone);
            return new \DateTimeZone(date_default_timezone_get());
        }
    }

    /**
     * Valide et normalise le payload brut du provider.
     *
     * @param string[] $types Types de facture à conserver (vide = tous).
     */
    public function normalizePayload(array $payload, array $types = []): array
    {
        $final = $payload['data_final'] ?? null;
        $byType = $payload['data_par_type'] ?? null;
        if (!is_array($final) || !is_array($byType)) {
            throw new DgiReportException(
                'dgi_provider_invalid_schema',
                'Le format du rapport DGI est inattendu.',
                502
            );
        }

        $selectedTypes = $this->normalizeTypeFilter($types);

        $globalTotals = $this->normalizeTotals($final);
        $globalTaxGroups = $this->normalizeTaxGroups($final);

        $allInvoiceTypes = [];
        foreach ($byType as $type => $typeData) {
            if (!is_array($typeData)) {
                continue;
            }
            $allInvoiceTypes[] = $this->normalizeInvoiceType((string)$type, $typeData);
        }

        $invoiceTypes = $allInvoiceTypes;
        if ($selectedTypes !== []) {
            $invoiceTypes = [];
            foreach ($allInvoiceTypes as $invoiceType) {
                if (in_array($invoiceType['type'], $selectedTypes, true)) {
                    $invoiceTypes[] = $invoiceType;
                }
            }
        }

        // Quand un filtre par type est appliqué, les compteurs affichés
        // doivent correspondre aux types affichés : on les recalcule donc à
        // partir des types retenus (le provider ne propose pas ce filtre).
        $totals = $this->totalsForTypes($invoiceTypes, $globalTotals);
        $taxGroups = $selectedTypes === []
            ? $globalTaxGroups
            : $this->taxGroupsForTypes($invoiceTypes, $globalTaxGroups);

        // Idem pour les modes de paiement : recalculés sur les types retenus
        // quand un filtre est appliqué.
        $globalPayments = $this->normalizePayments($final['paiements'] ?? []);
        $payments = $selectedTypes === []
            ? $globalPayments
            : $this->paymentsForTypes($invoiceTypes);

        // La cohérence se vérifie toujours sur l'ENSEMBLE des types : sinon
        // un filtre partiel produirait de faux avertissements (les sommes
        // partielles ne peuvent pas égaler les totaux globaux).
        $warnings = $this->buildConsistencyWarnings($globalTotals, $globalTaxGroups, $allInvoiceTypes);
        $warnings = array_merge(
            $warnings,
            $this->buildPaymentWarnings($globalPayments, $allInvoiceTypes)
        );
        $warnings = array_merge(
            $warnings,
            $this->buildAmountWarnings($globalTotals, $globalTaxGroups)
        );

        return [
            'totals' => $totals,
            'tax_groups' => $taxGroups,
            'payments' => $payments,
            'by_invoice_type' => $invoiceTypes,
            // Liste COMPLETE des types : le frontend s'en sert pour alimenter
            // le filtre (HT, TTC, TVA, nb de factures, remise) même quand un
            // filtre restreint l'affichage.
            'all_by_invoice_type' => $allInvoiceTypes,
            'warnings' => $warnings,
            'filter' => [
                'types' => $selectedTypes,
                'filtered' => $selectedTypes !== [],
            ],
        ];
    }

    /**
     * Contrôle les relations entre les montants du provider.
     *
     * Relations stables sur toutes les périodes observées :
     *   - somme(ha) = TTC
     *   - somme(ts) = TS_TOTAL
     *
     * ⚠️ La somme des TVA **par catégorie** ne correspond PAS au total TVA
     * (écart mesuré de 6 800 sur le payload de référence) : on ne contrôle
     * donc pas cette relation, qui produirait un faux positif systématique.
     *
     * ⚠️ La définition de `HT1` côté provider **a changé** : selon la
     * période, il vaut `TTC − TVA` ou `somme(ha) − catégorie A`. On ne
     * tranche pas : le montant est affiché tel que le provider le fournit.
     */
    private function buildAmountWarnings(array $totals, array $taxGroups): array
    {
        $warnings = [];

        $sumHa = 0.0;
        $sumTs = 0.0;
        foreach ($taxGroups as $group) {
            $sumHa += (float)$group['amount'];
            $sumTs += (float)$group['ts'];
        }

        if (abs($sumHa - (float)$totals['ttc']) > 0.01) {
            $warnings[] = sprintf(
                'La somme des HT par catégorie (%.2f) diffère du TTC (%.2f).',
                $sumHa,
                (float)$totals['ttc']
            );
        }
        if (abs($sumTs - (float)$totals['ts_total']) > 0.01) {
            $warnings[] = sprintf(
                'La somme des TS par catégorie (%.2f) diffère du TS total (%.2f).',
                $sumTs,
                (float)$totals['ts_total']
            );
        }

        return $warnings;
    }

    /**
     * Recalcule les modes de paiement à partir des seuls types retenus.
     */
    private function paymentsForTypes(array $invoiceTypes): array
    {
        $byMode = [];
        foreach ($invoiceTypes as $invoiceType) {
            foreach ($invoiceType['payments'] as $payment) {
                $code = $payment['code'];
                if (!isset($byMode[$code])) {
                    $byMode[$code] = [
                        'code' => $code,
                        'label' => $payment['label'],
                        'total' => 0.0,
                    ];
                }
                $byMode[$code]['total'] += (float)$payment['total'];
            }
        }

        $result = array_values($byMode);
        usort($result, static function (array $a, array $b): int {
            return $b['total'] <=> $a['total'];
        });

        return $result;
    }

    /**
     * Vérifie que la somme des paiements par type correspond au total global.
     */
    private function buildPaymentWarnings(array $globalPayments, array $invoiceTypes): array
    {
        if ($globalPayments === [] || $invoiceTypes === []) {
            return [];
        }

        $sums = [];
        foreach ($invoiceTypes as $invoiceType) {
            foreach ($invoiceType['payments'] as $payment) {
                $code = $payment['code'];
                $sums[$code] = ($sums[$code] ?? 0.0) + (float)$payment['total'];
            }
        }

        $warnings = [];
        foreach ($globalPayments as $payment) {
            $code = $payment['code'];
            $sum = (float)($sums[$code] ?? 0.0);
            if (abs($sum - (float)$payment['total']) > 0.01) {
                $warnings[] = sprintf(
                    'La somme des paiements %s par type (%.2f) diffère du total global (%.2f).',
                    $payment['label'],
                    $sum,
                    (float)$payment['total']
                );
            }
        }

        return $warnings;
    }

    /**
     * Recalcule les catégories fiscales à partir des seuls types retenus.
     */
    private function taxGroupsForTypes(array $invoiceTypes, array $globalGroups): array
    {
        $byCode = [];
        foreach ($globalGroups as $group) {
            $byCode[$group['code']] = $group;
        }

        $result = [];
        foreach ($byCode as $code => $group) {
            $amount = 0.0;
            $tva = 0.0;
            $ts = 0.0;
            foreach ($invoiceTypes as $invoiceType) {
                foreach ($invoiceType['tax_groups'] as $typeGroup) {
                    if ($typeGroup['code'] !== $code) {
                        continue;
                    }
                    $amount += (float)$typeGroup['amount'];
                    $tva += (float)$typeGroup['tva'];
                    $ts += (float)$typeGroup['ts'];
                }
            }

            $result[] = [
                'code' => $code,
                'label' => $group['label'],
                'rate' => $group['rate'],
                'amount' => $amount,
                'tva' => $tva,
                'ts' => $ts,
            ];
        }

        return $result;
    }

    /**
     * Normalise le filtre de types : on ne conserve que les types DGI connus,
     * en majuscules et sans doublon.
     *
     * @param string[] $types
     *
     * @return string[]
     */
    private function normalizeTypeFilter(array $types): array
    {
        $filtered = [];
        foreach ($types as $type) {
            $code = strtoupper(trim((string)$type));
            if ($code === '' || !isset(self::INVOICE_TYPES[$code])) {
                continue;
            }
            if (!in_array($code, $filtered, true)) {
                $filtered[] = $code;
            }
        }

        return $filtered;
    }

    /**
     * Normalise les compteurs globaux.
     *
     * `HT1` est le total hors taxes (HT) du provider.
     */
    private function normalizeTotals(array $final): array
    {
        return [
            'ht' => $this->number($final['HT1'] ?? 0),
            'tva' => $this->number($final['TVA'] ?? 0),
            'ts_total' => $this->number($final['TS_TOTAL'] ?? 0),
            'ttc' => $this->number($final['TTC'] ?? 0),
            'invoice_count' => (int)($final['nombre_factures_traitees'] ?? 0),
            'discount_total' => $this->number($final['remise'] ?? 0),
        ];
    }

    /**
     * Recalcule les totaux globaux à partir des seuls types retenus.
     *
     * Le provider ne propose pas de filtre par type : les totaux globaux
     * `data_final` couvrent TOUS les types. Quand l'utilisateur filtre,
     * les compteurs affichés doivent correspondre aux types affichés.
     */
    private function totalsForTypes(array $invoiceTypes, array $globalTotals): array
    {
        if ($invoiceTypes === []) {
            return $globalTotals;
        }

        $sums = $this->sumByType($invoiceTypes);
        $filtered = $globalTotals;
        foreach ($sums as $key => $value) {
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * Normalise les catégories A-P du tableau global.
     */
    private function normalizeTaxGroups(array $final): array
    {
        $ha = is_array($final['ha'] ?? null) ? $final['ha'] : [];
        $va = is_array($final['va'] ?? null) ? $final['va'] : [];
        $ts = is_array($final['ts'] ?? null) ? $final['ts'] : [];

        $groups = [];
        foreach (self::TAX_CATEGORIES as $code => $definition) {
            $amount = $this->number($ha['ha' . strtolower($code)] ?? 0);
            $tva = $this->number($va['va' . strtolower($code)] ?? 0);
            $tsAmount = $this->number($ts['ts' . strtolower($code)] ?? 0);

            $groups[] = [
                'code' => $code,
                'label' => $definition['label'],
                'rate' => $definition['rate'],
                'amount' => $amount,
                'tva' => $tva,
                'ts' => $tsAmount,
            ];
        }

        return $groups;
    }

    /**
     * Normalise un agrégat par type de facture.
     */
    private function normalizeInvoiceType(string $type, array $data): array
    {
        return [
            'type' => $type,
            'label' => self::INVOICE_TYPES[$type] ?? $type,
            'totals' => [
                'ht' => $this->number($data['HT1'] ?? 0),
                'tva' => $this->number($data['TVA'] ?? 0),
                'ts_total' => $this->number($data['TS_TOTAL'] ?? 0),
                'ttc' => $this->number($data['TTC'] ?? 0),
                'invoice_count' => (int)($data['nombre_factures_traitees'] ?? 0),
                'discount_total' => $this->number($data['remise'] ?? 0),
            ],
            'payments' => $this->normalizePayments($data['paiements'] ?? []),
            'tax_groups' => $this->normalizeTaxGroups($data),
        ];
    }

    /**
     * Normalise les modes de paiement.
     *
     * Le provider renvoie un objet `{ mode: montant }`. On en fait une liste
     * triée par montant décroissant, avec un libellé lisible.
     */
    private function normalizePayments($payments): array
    {
        if (!is_array($payments)) {
            return [];
        }

        $normalized = [];
        foreach ($payments as $mode => $amount) {
            $code = strtoupper(trim((string)$mode));
            if ($code === '') {
                continue;
            }
            $normalized[] = [
                'code' => $code,
                'label' => self::PAYMENT_MODES[$code] ?? $code,
                'total' => $this->number($amount),
            ];
        }

        // Montant décroissant : le mode principal apparaît en premier.
        usort($normalized, static function (array $a, array $b): int {
            return $b['total'] <=> $a['total'];
        });

        return $normalized;
    }

    /**
     * Somme les compteurs d'une liste d'agrégats par type de facture.
     */
    private function sumByType(array $invoiceTypes): array
    {
        $sums = [
            'ht' => 0.0,
            'tva' => 0.0,
            'ts_total' => 0.0,
            'ttc' => 0.0,
            'invoice_count' => 0,
            'discount_total' => 0.0,
        ];

        foreach ($invoiceTypes as $invoiceType) {
            foreach ($sums as $key => $_) {
                $sums[$key] += $invoiceType['totals'][$key];
            }
        }

        return $sums;
    }

    /**
     * Compare les sommes par type et par catégorie avec les totaux globaux.
     * Les écarts remontent comme avertissements, sans bloquer le rapport.
     */
    private function buildConsistencyWarnings(array $totals, array $taxGroups, array $invoiceTypes): array
    {
        $warnings = [];
        $sums = $this->sumByType($invoiceTypes);

        $labels = [
            'ht' => 'HT',
            'tva' => 'TVA',
            'ts_total' => 'TS',
            'ttc' => 'TTC',
            'invoice_count' => 'nombre de factures',
            'discount_total' => 'remises',
        ];
        foreach ($sums as $key => $sum) {
            if (abs((float)$sum - (float)$totals[$key]) > 0.01) {
                $warnings[] = sprintf(
                    'La somme des %s par type (%.2f) diffère du total global (%.2f).',
                    $labels[$key],
                    (float)$sum,
                    (float)$totals[$key]
                );
            }
        }

        $categoryTs = 0.0;
        foreach ($taxGroups as $group) {
            $categoryTs += (float)$group['ts'];
        }
        if (abs($categoryTs - (float)$totals['ts_total']) > 0.01) {
            $warnings[] = sprintf(
                'La somme des TS par catégorie (%.2f) diffère du TS total (%.2f).',
                $categoryTs,
                (float)$totals['ts_total']
            );
        }

        return $warnings;
    }

    /**
     * Accepte les nombres numériques et leurs chaînes décimales.
     */
    private function number($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return (float)trim($value);
        }

        return 0.0;
    }

    /**
     * Effectue l'appel HTTP réel vers OSAT.
     *
     * @return array{status: int, body: string}
     */
    private function request(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new DgiReportException(
                'dgi_provider_transport_unavailable',
                'Le module HTTP du serveur ne permet pas de joindre la DGI.',
                500
            );
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->buildCurlOptions());

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($body === false) {
            $this->throwTransportError($curlErrno, $curlError);
        }

        return [
            'status' => $status,
            'body' => (string)$body,
        ];
    }

    /**
     * Options cURL communes à tous les appels provider.
     *
     * ⚠️ PAS de CURLOPT_TIMEOUT : la DGI peut mettre du temps à répondre
     * sur une période large. La requête attend le provider aussi
     * longtemps qu'il faut (le délai de connexion reste borné).
     */
    private function buildCurlOptions(?int $timeoutSeconds = null): array
    {
        $connectTimeout = defined('DGI_REPORT_CONNECT_TIMEOUT')
            ? max(1, (int)constant('DGI_REPORT_CONNECT_TIMEOUT'))
            : 5;

        $headers = ['Accept: application/json'];
        $token = defined('DGI_REPORT_API_TOKEN')
            ? trim((string)constant('DGI_REPORT_API_TOKEN'))
            : '';
        if ($token !== '') {
            $headers[] = 'X-DGI-Token: ' . $token;
        }

        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];

        // Timeout global : borné uniquement pour les appels PARALLÈLES du
        // tableau de bord. La clôture Z, elle, doit pouvoir attendre la DGI
        // aussi longtemps que nécessaire (défaut : pas de timeout).
        if ($timeoutSeconds !== null && $timeoutSeconds > 0) {
            $options[CURLOPT_TIMEOUT] = $timeoutSeconds;
        }

        return $options;
    }

    /**
     * Traduit une erreur cURL en exception applicative.
     */
    private function throwTransportError(int $errno, string $error): void
    {
        if ($errno === CURLE_OPERATION_TIMEDOUT || str_contains(strtolower($error), 'timed out')) {
            throw new DgiReportException(
                'dgi_provider_timeout',
                'Le service DGI met trop de temps à répondre.',
                504
            );
        }

        throw new DgiReportException(
            'dgi_provider_unavailable',
            'Le service DGI est momentanément indisponible.',
            502
        );
    }
}
