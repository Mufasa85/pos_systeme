<?php

namespace App\Services;

/**
 * Calcul de paie « modèle Excel / RDC ».
 *
 * Reproduit fidèlement le modèle documenté dans `Recap paie.xlsx` (classeur du
 * comptable) et confirmé par la base `paie_db` (ancien projet paie autonome).
 *
 * Colonnes du classeur → étapes du calcul :
 *
 *   R  SALAIRE            → salaire de base du contrat
 *   S  SURSALAIRE        → sursalaire du contrat
 *   N  ANCIENNETE        → DATEDIF(date_engagement, aujourd'hui, "y")
 *   T  SALAIRE ANCIENNETE = (R × 3 %) × N
 *   X  LOG               = (R + S + T) × 30 %
 *   Y  TRANS             = X × 21,15 %
 *   Z  MASSE SALARIALE   = R + S + T + X + Y
 *   P  PLAF CNSS         = R + S + T
 *   Q  IMPOSABLE         = P − CNSS (5 %)
 *   AA CNSS              = P × 5 %
 *   AB IMPOTS            = barème IRT (5 tranches) × (1 − 2 % × enfants) ÷ taux
 *   AC TOTAL RETENU      = AA + AB
 *   AD NET A PAYER       = Z − AC
 *   AE IER               = P × 25 %
 *   AF/AG CNSS patron.   = P × 6,5 %
 *   AI INPP              = P × 3 %
 *   AJ ONEM              = P × 0,2 %
 *   AK BRUT EMPLOYEUR    = Z + AE + AF + AG + AI + AJ
 *
 * ⚠️ Les taux sont lus dans `payroll_settings` (boutique prioritaire, sinon
 * global) : rien n'est figé en dur.
 *
 * Le barème IRT est exprimé dans la devise forte (USD) puis converti au taux
 * de change `exchange_rate` — c'est le fonctionnement exact du classeur.
 */
class PayrollExcelCalculator
{
    /** Barème IRT : [seuil haut USD, part constante USD, taux % de la tranche]. */
    private const IRT_BRACKETS = [
        ['max' => 162000.0,   'base' => 0.0,        'rate' => 0.03],
        ['max' => 1800000.0,  'base' => 4860.0,     'rate' => 0.15],
        ['max' => 3600000.0,  'base' => 250560.0,   'rate' => 0.30],
        ['max' => 6543000.0,  'base' => 790560.0,   'rate' => 0.40],
        ['max' => null,       'base' => 0.0,        'rate' => 0.30],
    ];

    private $settings;

    public function __construct(?\App\Models\PayrollSettings $settings = null)
    {
        $this->settings = $settings ?? new \App\Models\PayrollSettings();
    }

    /**
     * Barème de l'impôt sur le revenu, exprimé dans la devise forte.
     *
     * Reproduit la formule du classeur :
     *   IF(USDC <= 162000,   USDC × 3 %,
     *   IF(USDC <= 1800000,  4860 + (USDC − 162000) × 15 %,
     *   IF(USDC <= 3600000,  250560 + (USDC − 1800000) × 30 %,
     *   IF(USDC < 6543000,   790560 + ((USDC − 3600000) × 40 %),
     *                       USDC × 30 %))))
     *
     * @param float $usdc         Montant imposable dans la devise forte
     * @param float $allowancePct Réduction en % (2 % par personne à charge)
     * @return float Montant de l'impôt dans la devise forte
     */
    public function computeIrt(float $usdc, float $allowancePct = 0.0): float
    {
        if ($usdc <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        foreach (self::IRT_BRACKETS as $bracket) {
            $max = $bracket['max'];
            if ($max === null || $usdc <= $max) {
                $tax = $bracket['base'] + ($usdc - $this->bracketBase($bracket)) * $bracket['rate'];
                break;
            }
        }

        // Réduction pour personnes à charge, jamais négative.
        $factor = max(0.0, 1.0 - ($allowancePct / 100));

        return $tax * $factor;
    }

    /**
     * Montant de départ de la tranche (pour les tranches à « base + % »).
     */
    private function bracketBase(array $bracket): float
    {
        switch ($bracket['base']) {
            case 4860.0:
                return 162000.0;
            case 250560.0:
                return 1800000.0;
            case 790560.0:
                return 3600000.0;
            default:
                return 0.0;
        }
    }

    /**
     * Calcule l'intégralité du bulletin selon le modèle Excel.
     *
     * @param array    $contract   Contrat (base_salary, sursalary…)
     * @param array    $employee   Fiche Paie (hire_date, tax_dependents…)
     * @param array    $attendance ['paid_days' => float, 'worked_hours' => float]
     * @param int|null $shopId     Boutique (pour résoudre les paramètres)
     *
     * @return array{payslip: array, lines: array, detail: array}
     */
    public function calculate(array $contract, array $employee, array $attendance, $shopId = null): array
    {
        $p = $this->settings->resolved($shopId);

        // ── Date de référence ────────────────────────────────────────────────
        // Par défaut la fin de la période de paie (mois/year), ce qui rend le
        // calcul REPRODUCTIBLE : recalculer un bulletin d'octobre en
        // décembre ou en mars donne toujours le même montant.
        // `attendance['reference_date']` permet de forcer une autre date.
        $referenceDate = $attendance['reference_date'] ?? $this->periodEndDate(
            $attendance['month'] ?? null,
            $attendance['year'] ?? null
        );
        if (empty($referenceDate)) {
            $referenceDate = date('Y-m-d');
        }

        // ── Taux de change ──────────────────────────────────────────────────
        // Le classeur fige un taux (2 658,7148 à sa date d'édition). On peut
        // le remplacer par le taux réel publié par l'API OSAT (`/dgi/currency/`,
        // déjà proxifiée pour la facture DGI) : le barème IRT étant libellé en
        // USD, un taux périmé fausse l'impôt.
        //   - `exchange_rate_source = 'api'`  → taux du jour (repli : paramètre)
        //   - `exchange_rate_source = 'fixed'` → taux paramétré (classeur)
        $exchangeRate = (float)$p['exchange_rate'];
        $rateSource = strtolower((string)($p['exchange_rate_source'] ?? 'api'));

        if ($rateSource === 'api') {
            $live = (new PayrollExchangeRateService())->getRate($exchangeRate);
            if ($live > 0) {
                $exchangeRate = $live;
            }
        }

        if ($exchangeRate <= 0) {
            $exchangeRate = 1.0;
        }

        // ── R / S : salaire et sursalaire ────────────────────────────────────
        // Le classeur ne proratise PAS le salaire de base : R est le salaire
        // mensuel plein. L'option `prorate_base` permet de conserver le
        // prorata du modèle standard (base / jours_ouvrables × jours_payés).
        $baseSalary = (float)$contract['base_salary'];
        $prorateBase = in_array(strtolower((string)$p['prorate_base']), ['1', 'true', 'yes', 'on'], true);

        if ($prorateBase) {
            $workingDays = (float)($attendance['working_days'] ?? 26);
            $paidDays = (float)($attendance['paid_days'] ?? $workingDays);
            if ($workingDays > 0) {
                $baseSalary = ($baseSalary / $workingDays) * $paidDays;
            }
        }

        $sursalary = (float)($contract['sursalary'] ?? 0);

        // ── N / T : ancienneté (3 % du salaire de base PAR ANNÉE) ───────────
        // La date de référence est la date de la PERIODE (fin de mois) et non
        // la date du jour : recalculer un bulletin l'an dernier doit donner
        // la même ancienneté qu'à l'époque. Sans cela, un bulletin calculé
        // en décembre et recalculé en janvier changerait de montant.
        $years = $this->yearsBetween($employee['hire_date'], $referenceDate);
        $seniorityRate = (float)$p['seniority_rate_per_year'];
        $seniorityMax = (int)$p['seniority_max_years'];
        $cappedYears = ($seniorityMax > 0) ? min($years, $seniorityMax) : $years;
        $seniorityAmount = $baseSalary * ($seniorityRate / 100) * $cappedYears;

        // ── X / Y : logement et transport ───────────────────────────────────
        $housingRate = (float)$p['housing_rate'];
        $transportRate = (float)$p['transport_rate'];
        $housingAmount = ($baseSalary + $sursalary + $seniorityAmount) * ($housingRate / 100);
        $transportAmount = $housingAmount * ($transportRate / 100);

        // ── Z : masse salariale ─────────────────────────────────────────────
        $grossAmount = $baseSalary + $sursalary + $seniorityAmount + $housingAmount + $transportAmount;

        // ── P : plafond CNSS ────────────────────────────────────────────────
        $cnssBase = $baseSalary + $sursalary + $seniorityAmount;

        // ── AA : CNSS salarié ──────────────────────────────────────────────
        $cnssEmployeeRate = (float)$p['cnss_employee_rate'];
        $cnssEmployee = $cnssBase * ($cnssEmployeeRate / 100);

        // ── Q : imposable ───────────────────────────────────────────────────
        $taxableAmount = $cnssBase - $cnssEmployee;

        // ── AB : IRT ────────────────────────────────────────────────────────
        $taxDependents = (int)($employee['tax_dependents'] ?? 0);
        $allowancePct = $taxDependents * (float)$p['tax_dependents_allowance'];

        $irtEnabled = in_array(strtolower((string)$p['irt_enabled']), ['1', 'true', 'yes', 'on'], true);
        $irtAmount = 0.0;
        $irtUsd = 0.0;
        $taxableUsd = $taxableAmount * $exchangeRate;

        if ($irtEnabled && $exchangeRate > 0) {
            $irtUsd = $this->computeIrt($taxableUsd, $allowancePct);
            $irtAmount = $irtUsd / $exchangeRate;
        }

        // ── AC / AD : total retenu et net à payer ───────────────────────────
        $totalDeductions = $cnssEmployee + $irtAmount;
        $netAmount = $grossAmount - $totalDeductions;

        // ── AE / AF / AG / AI / AJ : charges patronales ─────────────────────
        // ⚠️ L'IER est une taxe AGENT PAR AGENT : elle n'est due que par les
        // agents effectivement soumis. Le classeur en donne la preuve :
        //   - ligne 2 → AE = 0 (valeur saisie, pas de formule) ;
        //   - ligne 5 → AE = "=P5*25%" (formule).
        // La base `paie_db` va dans le même sens : 3 agents à `ier_rate = 0`,
        // 1 seul agent à 25 %.
        // → On applique donc le taux porté par l'agent (0 si non renseigné).
        // Le paramètre global `ier_employer_rate` reste la valeur de référence
        // à saisir dans la fiche de l'agent, pas un taux automatique.
        $ierRate = isset($employee['ier_rate']) ? (float)$employee['ier_rate'] : 0.0;

        $ierAmount = $cnssBase * ($ierRate / 100);

        // ⚠️ Le classeur compte DEUX lignes de CNSS patronale :
        //   AF = "CNSS 6,5 %"          = P × 6,5 %
        //   AG = "CNSS 5 % ET CNSS 1,5 %" = P × 6,5 %
        // et le BRUT EMPLOYEUR (AK) les additionne TOUTES LES DEUX :
        //   AK = Z + AE + AF + AG + AI + AJ
        // C'est également le cas dans la base `paie_db` (CNSS_PAT et CNSS_PAT2).
        $cnssEmployer = $cnssBase * ((float)$p['cnss_employer_rate'] / 100);
        $cnssEmployer2 = $cnssBase * ((float)$p['cnss_employer_rate'] / 100);
        $inppEmployer = $cnssBase * ((float)$p['inpp_employer_rate'] / 100);
        $onemEmployer = $cnssBase * ((float)$p['onem_employer_rate'] / 100);

        $employerCharges = $ierAmount + $cnssEmployer + $cnssEmployer2 + $inppEmployer + $onemEmployer;
        $employerCost = $grossAmount + $employerCharges;

        $payslip = [
            'shop_id'           => $shopId,
            'employee_id'       => $employee['id'] ?? null,
            'gross_amount'      => round($grossAmount, 2),
            'taxable_amount'    => round($taxableAmount, 2),
            'cnss_base'         => round($cnssBase, 2),
            'total_deductions'  => round($totalDeductions, 2),
            'employer_charges'  => round($employerCharges, 2),
            'employer_cost'     => round($employerCost, 2),
            'net_amount'        => round($netAmount, 2),
            'status'            => 'calculated',
        ];

        $lines = $this->buildLines([
            'baseSalary'       => $baseSalary,
            'sursalary'        => $sursalary,
            'years'            => $cappedYears,
            'seniorityRate'    => $seniorityRate,
            'seniorityAmount'  => $seniorityAmount,
            'housingAmount'    => $housingAmount,
            'housingRate'      => $housingRate,
            'transportAmount'  => $transportAmount,
            'transportRate'    => $transportRate,
            'grossAmount'      => $grossAmount,
            'cnssBase'         => $cnssBase,
            'cnssEmployee'     => $cnssEmployee,
            'cnssEmployeeRate' => $cnssEmployeeRate,
            'irtAmount'        => $irtAmount,
            'irtUsd'           => $irtUsd,
            'taxableUsd'       => $taxableUsd,
            'exchangeRate'     => $exchangeRate,
            'allowancePct'     => $allowancePct,
            'taxDependents'    => $taxDependents,
            'ierAmount'        => $ierAmount,
            'ierRate'          => $ierRate,
            'cnssEmployer'     => $cnssEmployer,
            'cnssEmployer2'    => $cnssEmployer2,
            'cnssEmployerRate' => (float)$p['cnss_employer_rate'],
            'inppEmployer'     => $inppEmployer,
            'inppRate'         => (float)$p['inpp_employer_rate'],
            'onemEmployer'     => $onemEmployer,
            'onemRate'         => (float)$p['onem_employer_rate'],
        ]);

        $detail = [
            'mode'          => \App\Models\PayrollSettings::MODE_EXCEL_RDC,
            'exchange_rate' => $exchangeRate,
            'irt_usd'       => round($irtUsd, 2),
            'taxable_usd'   => round($taxableUsd, 2),
        ];

        return ['payslip' => $payslip, 'lines' => $lines, 'detail' => $detail];
    }

    /**
     * Dernier jour d'un mois (mois/année) — sert de date de référence.
     *
     * @param int|null $month 1..12
     * @param int|null $year
     * @return string|null date 'Y-m-d', ou null si les données sont absentes
     */
    private function periodEndDate($month, $year): ?string
    {
        $month = (int)$month;
        $year = (int)$year;

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return null;
        }

        // strtotime('last day of <mois> <année>') gère les mois de 28/29/30/31 jours.
        $timestamp = strtotime('last day of ' . $month . ' ' . $year);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * Construit les lignes de bulletin (ordre d'affichage du classeur).
     */
    private function buildLines(array $d): array
    {
        $lines = [
            [
                'code'       => 'BASE',
                'label'      => 'Salaire de base',
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => null,
                'amount'     => round($d['baseSalary'], 2),
                'sort_order' => 0,
            ],
            [
                'code'       => 'SURSAL',
                'label'      => 'Sursalaire',
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => null,
                'amount'     => round($d['sursalary'], 2),
                'sort_order' => 5,
            ],
            [
                'code'       => 'ANCIEN',
                'label'      => 'Salaire ancienneté (' . $d['years'] . ' ans)',
                'type'       => 'earning',
                'quantity'   => round($d['years'], 2),
                'rate'       => $d['seniorityRate'],
                'amount'     => round($d['seniorityAmount'], 2),
                'sort_order' => 10,
            ],
            [
                'code'       => 'LOG',
                'label'      => 'Logement (' . $d['housingRate'] . ' %)',
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => $d['housingRate'],
                'amount'     => round($d['housingAmount'], 2),
                'sort_order' => 20,
            ],
            [
                'code'       => 'TRANS',
                'label'      => 'Transport (' . $d['transportRate'] . ' % du LOG)',
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => $d['transportRate'],
                'amount'     => round($d['transportAmount'], 2),
                'sort_order' => 25,
            ],
            [
                'code'       => 'CNSS_SAL',
                'label'      => 'CNSS (salarié)',
                'type'       => 'deduction',
                'quantity'   => null,
                'rate'       => $d['cnssEmployeeRate'],
                'amount'     => round($d['cnssEmployee'], 2),
                'sort_order' => 40,
            ],
        ];

        $irtLabel = 'Impôt sur le revenu (IRT)';
        if ($d['taxDependents'] > 0) {
            $irtLabel .= ' — réduction ' . $d['allowancePct'] . ' % ('
                . $d['taxDependents'] . ' personne(s) à charge)';
        }
        $lines[] = [
            'code'       => 'IRT',
            'label'      => $irtLabel,
            'type'       => 'deduction',
            'quantity'   => null,
            'rate'       => null,
            'amount'     => round($d['irtAmount'], 2),
            'sort_order' => 45,
        ];

        $lines[] = [
            'code'       => 'IER',
            'label'      => 'IER employeur',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $d['ierRate'],
            'amount'     => round($d['ierAmount'], 2),
            'sort_order' => 60,
        ];
        $lines[] = [
            'code'       => 'CNSS_PAT',
            'label'      => 'CNSS (employeur)',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $d['cnssEmployerRate'],
            'amount'     => round($d['cnssEmployer'], 2),
            'sort_order' => 65,
        ];
        $lines[] = [
            'code'       => 'CNSS_PAT2',
            'label'      => 'CNSS 5 % + CNSS 1,5 % (employeur)',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $d['cnssEmployerRate'],
            'amount'     => round($d['cnssEmployer2'], 2),
            'sort_order' => 67,
        ];
        $lines[] = [
            'code'       => 'INPP_PAT',
            'label'      => 'INPP (employeur)',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $d['inppRate'],
            'amount'     => round($d['inppEmployer'], 2),
            'sort_order' => 70,
        ];
        $lines[] = [
            'code'       => 'ONEM_PAT',
            'label'      => 'ONEM (employeur)',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $d['onemRate'],
            'amount'     => round($d['onemEmployer'], 2),
            'sort_order' => 75,
        ];

        foreach ($lines as $i => $line) {
            $lines[$i]['sort_order'] = ($line['sort_order'] ?? $i) * 10;
        }

        return $lines;
    }

    /**
     * Ancienneté en années ENTIÈRES, comme `DATEDIF(…, "y")` du classeur.
     *
     * Le classeur calcule la colonne ANCIENNETE avec
     *   `=DATEDIF(M2, TODAY(), "y")`
     * c'est-à-dire un nombre d'ANNÉES ENTIÈRES (12 ans), et non une valeur
     * fractionnaire. On reproduit ce comportement à l'identique : c'est lui
     * qui conditionne le montant du salaire d'ancienneté (colonne T).
     *
     * Conséquence : l'ancienneté ne change qu'au jour anniversaire de
     * l'agent, pas tous les jours — comportement attendu et stable.
     */
    private function yearsBetween(string $from, string $to): float
    {
        $fromDate = new \DateTime($from);
        $toDate = new \DateTime($to);

        return (float)$fromDate->diff($toDate)->y;
    }
}
