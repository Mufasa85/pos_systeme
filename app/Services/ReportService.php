<?php

namespace App\Services;

use App\Models\DgiZReport;
use App\Models\Report;

class ReportService
{
    private $reportModel;
    private $dgiZReportModel;

    public function __construct()
    {
        $this->reportModel = new Report();
        $this->dgiZReportModel = new DgiZReport();
    }

    /**
     * Résout le NIF à afficher dans l'en-tête du rapport.
     *
     * OBJECTIF : afficher EXACTEMENT le même NIF que l'en-tête de la facture
     * finale, sans plus.
     *
     * SOURCE DE VÉRITÉ : le NIF de l'en-tête de la facture vient de
     * `STORE_INFO.isf`, lui-même alimenté par `GET /api/settings` →
     * `store_isf` (cf. `SettingsController::index()`). On reproduit cette
     * résolution À L'IDENTIQUE, dans le même ordre :
     *
     *   1. `shops.isf` de la boutique de la session (`$_SESSION['shop_id']`) ;
     *   2. pour un SUPER_ADMIN, `company_info.isf` (l'entreprise) REMPLACEE
     *      la valeur ci-dessus si elle est non vide (cf. le `foreach` du
     *      fallback dans `SettingsController::index()`) ;
     *   3. sinon `settings.store_isf` (ancien réglage par boutique) ;
     *   4. sinon `company_info.isf` (repli final).
     *
     * Concrètement : un vendeur voit le NIF de sa boutique, un super_admin
     * voit le NIF de l'entreprise — exactement comme sur ses factures.
     *
     * Repli final : le NIF de la boutique/société lu par
     * `Report::getShopInfo()` (utile quand aucun utilisateur n'est
     * authentifié, ex. génération depuis un script CLI).
     *
     * @param array|false $shopInfo Infos boutique (résultat de Report::getShopInfo).
     *                                      `false` si aucune boutique ne correspond.
     * @return string
     */
    private function resolveNif($shopInfo): string
    {
        $shopId = !empty($_SESSION['shop_id']) ? (int)$_SESSION['shop_id'] : null;
        $role   = (string)($_SESSION['role'] ?? '');

        // 1. NIF de la boutique de la session.
        $nif = '';
        if ($shopId) {
            try {
                $shop = (new \App\Models\Shop())->findById($shopId);
                $nif  = trim((string)($shop['isf'] ?? ''));
            } catch (\Exception $e) {
                error_log('[ReportService] Lecture de la boutique impossible: ' . $e->getMessage());
            }
        }

        // 2. Super_admin → NIF de l'ENTREPRISE (prioritaire, comme dans
        //    `/api/settings` où le fallback company_info écrase la boutique).
        $companyIsf = '';
        try {
            $company = (new \App\Models\CompanyInfo())->get();
            $companyIsf = trim((string)($company['isf'] ?? ''));
        } catch (\Exception $e) {
            error_log('[ReportService] Lecture de company_info impossible: ' . $e->getMessage());
        }

        if ($role === 'super_admin' && $companyIsf !== '') {
            return $companyIsf;
        }

        if ($nif !== '') {
            return $nif;
        }

        // 3. Ancien réglage par boutique (`settings.store_isf`).
        try {
            $settingsIsf = trim((string)((new \App\Models\Settings())->get('store_isf', $shopId) ?? ''));
            if ($settingsIsf !== '') {
                return $settingsIsf;
            }
        } catch (\Exception $e) {
            error_log('[ReportService] Lecture des settings impossible: ' . $e->getMessage());
        }

        // 4. Repli final : NIF de l'entreprise, puis de la boutique/société.
        if ($companyIsf !== '') {
            return $companyIsf;
        }

        $fallback = is_array($shopInfo) ? trim((string)($shopInfo['isf'] ?? '')) : '';
        return $fallback !== '' ? $fallback : 'N/A';
    }

    // ── Génération générique (Z et X) ───────────────────────────

    public function generate($shopId, $type, $startDate, $endDate)
    {
        $shopInfo = $this->reportModel->getShopInfo($shopId);
        // `getShopInfo()` renvoie `false` si aucune boutique ne correspond
        // (ex. super_admin sans filtre) : on normalise en tableau vide.
        $shopInfo = is_array($shopInfo) ? $shopInfo : [];

        $denomination = $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A';
        // NIF de l'utilisateur connecté (cf. resolveNif()), et non celui de
        // la boutique : le rapport doit porter le même identifiant que la
        // facture finale.
        $isf = $this->resolveNif($shopInfo);
        $adresse = $shopInfo['adresse'] ?? '';
        $telephone = $shopInfo['telephone'] ?? '';

        $salesByInvoiceType = $this->reportModel->getSalesByInvoiceType($shopId, $startDate, $endDate);
        $salesByTaxGroup = $this->reportModel->getSalesByTaxGroup($shopId, $startDate, $endDate);
        $discounts = $this->reportModel->getTotalDiscounts($shopId, $startDate, $endDate);
        $creditNotes = $this->reportModel->getCreditNotes($shopId, $startDate, $endDate);
        $incompleteSales = $this->reportModel->getIncompleteSalesCount($shopId, $startDate, $endDate);

        // ── Blocs HÔTEL ───────────────────────────────────────────────
        // Le CA hôtelier est désormais intégré aux rapports : les factures
        // hôtel portent leur vrai type fiscal (`ventes.invoice_type`) et
        // leurs nuitées apparaissent dans le groupe de taxation de la chambre
        // (snapshot `details_vente.tax_rate`).
        //
        // ⚠️ Règle de non-double-compte : les EXTRA hôtel (`invoice_type='EX'`)
        // sont EXCLUS des totaux consolidés (types de facture, groupes de
        // taxation, paiements) car ils sont déjà inclus dans le total des
        // factures hôtel. Ils restent visibles dans le bloc dédié « Extras ».
        $hotelBreakdown = $this->reportModel->getHotelBreakdown($shopId, $startDate, $endDate);
        $hotelByRoom = $this->reportModel->getHotelReportByRoom($shopId, $startDate, $endDate);
        $hotelExtras = $this->reportModel->getHotelExtrasReport($shopId, $startDate, $endDate);

        // Paiements : parsing JSON en PHP
        $rawPayments = $this->reportModel->getPaymentsParsed($shopId, $startDate, $endDate);
        $paymentsByMethod = [];
        $paymentLabels = [
            'ESPECES' => 'Espèces', 'MOBILEMONEY' => 'Mobile Money',
            'CARTEBANCAIRE' => 'Carte Bancaire', 'VIREMENT' => 'Virement',
            'CREDIT' => 'Crédit', 'CHEQUES' => 'Chèques', 'AUTRE' => 'Autres',
        ];
        foreach ($rawPayments as $row) {
            $payments = json_decode($row['payments'] ?? '[]', true);
            if (!is_array($payments)) {
                continue;
            }
            foreach ($payments as $p) {
                $typeP = $p['type'] ?? ($p['mode'] ?? 'ESPECES');
                $amount = floatval($p['amount'] ?? 0);
                $paymentsByMethod[$typeP] = ($paymentsByMethod[$typeP] ?? 0) + $amount;
            }
        }

        return [
            'header' => [
                'type' => $type,
                'denomination' => $denomination,
                // NIF de l'UTILISATEUR CONNECTÉ (et non celui de la
                // boutique) : c'est l'identifiant porté par la facture
                // finale et par la payload DGI (`store_isf`). Le rapport
                // fiscal doit afficher exactement le même NIF.
                'nif' => $isf,
                'isf' => $isf,
                'adresse' => $adresse,
                'telephone' => $telephone,
                'generated_at' => date('Y-m-d H:i:s'),
                'period_start' => $startDate,
                'period_end' => $endDate,
            ],
            'totals' => [
                'total_ht' => array_sum(array_column($salesByInvoiceType, 'total_ht')),
                'total_tax' => array_sum(array_column($salesByInvoiceType, 'total_tax')),
                'total_ttc' => array_sum(array_column($salesByInvoiceType, 'total_ttc')),
                'total_invoices' => array_sum(array_column($salesByInvoiceType, 'invoice_count')),
                // Taxe spécifique (TS) totale du CA : c'est une taxe
                // additionnelle à la TVA, collectée sur le prix (hôtellerie,
                // produits à taxe spécifique). Elle n'est pas incluse dans
                // `ventes.tva` → on l'agrège depuis les lignes de détail.
                'total_ts' => array_sum(array_column($salesByTaxGroup, 'total_ts')),
            ],
            'by_invoice_type' => $salesByInvoiceType,
            'by_tax_group' => $salesByTaxGroup,
            // ── Blocs hôtel ───────────────────────────────────────────
            // `hotel_breakdown` : ventilation nuitées / extras / total
            // `hotel_by_room`   : CA hôtelier par chambre
            // `hotel_extras`    : détail des extras (déjà inclus dans le
            //                      total des factures hôtel → affiché pour
            //                      information, jamais additionné)
            'hotel_breakdown' => $hotelBreakdown,
            'hotel_by_room' => $hotelByRoom,
            'hotel_extras' => $hotelExtras,
            'by_payment_method' => array_map(function ($k, $v) use ($paymentLabels) {
                return [
                    'method' => $k,
                    'label' => $paymentLabels[$k] ?? $k,
                    'total' => $v,
                ];
            }, array_keys($paymentsByMethod), $paymentsByMethod),
            'discounts' => $discounts,
            'credit_notes' => $creditNotes,
            'incomplete_sales' => $incompleteSales,
        ];
    }


    /**
     * Début de la période ouverte (non encore clôturée).
     *
     * Source unique de vérité : la dernière clôture DGI. Sans clôture DGI,
     * on prend le début de la journée (et non un an en arrière : les
     * rapportX ne couvrent que la journée en cours).
     */
    public function resolveOpenPeriodStart($shopId): string
    {
        $lastClosure = $this->dgiZReportModel->getLastWithInvoices(
            $shopId !== null ? (int)$shopId : null
        );
        if ($lastClosure && !empty($lastClosure['period_end'])) {
            return (string)$lastClosure['period_end'];
        }

        return date('Y-m-d 00:00:00');
    }

    /**
     * Point de départ de la PREMIÈRE clôture DGI (aucune clôture antérieure).
     *
     * On ancre la période sur la première vente réelle de la boutique plutôt
     * que sur « un an en arrière » : la première clôture ne doit englober que
     * l'exercice de la boutique, pas un an d'historique.
     *
     * À défaut de vente connue (boutique sans vente, ou données indisponibles)
     * on conserve le repli « un an en arrière », plus sûr qu'une date vide.
     */
    public function resolveFirstClosureStart($shopId): string
    {
        try {
            $firstSale = $this->reportModel->getFirstSaleDate(
                $shopId !== null ? (int)$shopId : null
            );
            if (!empty($firstSale)) {
                return (string)$firstSale;
            }
        } catch (\Exception $e) {
            error_log('[ReportService] Date de première vente introuvable: ' . $e->getMessage());
        }

        return date('Y-m-d H:i:s', strtotime('-1 year'));
    }

    // ── A-rapport : détail par article (clôture la période A) ──

    public function generateAReport($shopId)
    {
        $last = $this->reportModel->getLastAReport($shopId);
        $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00', strtotime('-1 year'));
        $periodEnd = date('Y-m-d H:i:s');

        $shopInfo = $this->reportModel->getShopInfo($shopId);
        // `getShopInfo()` renvoie `false` si aucune boutique ne correspond
        // (ex. super_admin sans filtre) : on normalise en tableau vide pour
        // éviter tout accès à un index sur un booléen.
        $shopInfo = is_array($shopInfo) ? $shopInfo : [];
        $articles = $this->reportModel->getArticlesReport($shopId, $periodStart, $periodEnd);
        // Même NIF que les autres rapports : celui de l'utilisateur connecté
        // (cf. resolveNif()), aligné sur la facture finale.
        $nif = $this->resolveNif($shopInfo);

        $totalQtySold = array_sum(array_column($articles, 'quantite_vendue'));
        $totalQtyReturned = array_sum(array_column($articles, 'quantite_retournee'));
        $totalCollected = array_sum(array_column($articles, 'montant_collecte'));

        $this->reportModel->createAReport($shopId, $periodStart, $periodEnd, [
            'articles_count' => count($articles),
            'total_quantity_sold' => $totalQtySold,
            'total_quantity_returned' => $totalQtyReturned,
            'total_amount_collected' => $totalCollected,
        ]);

        return [
            'header' => [
                'type' => 'A',
                'denomination' => $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A',
                'nif' => $nif,
                'isf' => $nif,
                'adresse' => $shopInfo['adresse'] ?? '',
                'telephone' => $shopInfo['telephone'] ?? '',
                'generated_at' => $periodEnd,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            'articles' => $articles,
            'totals' => [
                'total_quantity_sold' => $totalQtySold,
                'total_quantity_returned' => $totalQtyReturned,
                'total_amount_collected' => $totalCollected,
                'articles_count' => count($articles),
            ],
        ];
    }
}
