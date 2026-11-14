<?php

namespace App\Services;

use App\Models\PayrollContract;
use App\Models\PayrollEmployee;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import du récapitulatif de paie Excel (cf. `Recap paie.xlsx`).
 *
 * Le classeur du comptable est la source de vérité historique : il contient
 * pour chaque agent son matricule, sa direction, sa fonction, sa date
 * d'embauche, son salaire de base, son sursalaire, son taux d'IER, etc.
 *
 * ⚠️ Le classeur contient 37 colonnes, dont beaucoup sont des FORMULES
 * (PLAF CNSS, IMPOSABLE, MASSE SALARIALE, CNSS, IMPOTS, NET A PAYER,
 * BRUT EMPLOYEUR…). Ces colonnes sont IGNORÉES à l'import : elles sont
 * recalculées par le module Paie (`PayrollExcelCalculator`), qui reproduit
 * le même modèle. On importe donc uniquement les données SAISIES.
 *
 * Le mapping se fait par EN-TÊTE (et non par index de colonne) : le fichier
 * peut évoluer (colonnes insérées) sans casser l'import.
 *
 * Colonnes importées et leur cible :
 *
 *   MATR.              → payroll_employees.matricule
 *   NOM & POSTNOM      → agent apparié par nom (utilisateurs.nom_complet)
 *   DIRECTION          → payroll_employees.direction
 *   DEPART.            → (informatif, non importé : pas de table dédiée)
 *   FONCTION           → payroll_employees.job_title
 *   SITAF              → payroll_employees.sitaf
 *   CHARGE FISCALE     → payroll_employees.tax_dependents
 *   CATLEG             → payroll_employees.cat_leg
 *   CATPROF            → payroll_employees.cat_prof
 *   NUM CNSS           → payroll_employees.cnss_number
 *   MOIS / ANNEE       → période visée (contrôle)
 *   DATE ENG           → payroll_employees.hire_date
 *   Jour presté        → jours travaillés de la période
 *   SALAIRE            → payroll_contracts.base_salary
 *   SURSALAIRE         → payroll_contracts.sursalary
 *   IK / Mobilité / Mesure d'accompagnement → payroll_allowances (primes)
 *   IER                → payroll_employees.ier_rate
 */
class PayrollRecapImportService
{
    /** En-têtes du classeur → colonne cible. */
    private const COLUMN_MAP = [
        'MATR.'                  => 'matricule',
        'NOM & POSTNOM'          => 'nom',
        'DIRECTION'              => 'direction',
        'DEPART.'                => 'departement',
        'FONCTION'               => 'fonction',
        'SITAF'                  => 'sitaf',
        'CHARGE FISCALE'         => 'charge_fiscale',
        'CATLEG'                 => 'cat_leg',
        'CATPROF'                => 'cat_prof',
        'NUM CNSS'               => 'cnss_number',
        'MOIS'                   => 'mois',
        'ANNEE'                  => 'annee',
        'DATE ENG'               => 'date_engagement',
        'Jour presté'            => 'jours_prestes',
        'SALAIRE'                => 'base_salary',
        'SURSALAIRE'             => 'sursalary',
        'IK'                     => 'ik',
        'Mobilité'               => 'mobilite',
        "Mesure d'accompagnement" => 'mesure_accompagnement',
        'IER'                    => 'ier_rate',
    ];

    /** En-têtes tolérés en variante (accents / casse / ponctuation). */
    private const HEADER_ALIASES = [
        'MATR'                 => 'MATR.',
        'MATRICULE'            => 'MATR.',
        'NOM & POSTNOM'        => 'NOM & POSTNOM',
        'NOM ET POSTNOM'       => 'NOM & POSTNOM',
        'NOM POSTNOM'          => 'NOM & POSTNOM',
        'DATE ENGAGEMENT'      => 'DATE ENG',
        "DATE D'ENGAGEMENT"    => 'DATE ENG',
        'JOUR PRESTE'          => 'Jour presté',
        'JOURS PRESTES'        => 'Jour presté',
        'CHARGE FISCALE'       => 'CHARGE FISCALE',
        'MESURE D’ACCOMPAGNEMENT' => "Mesure d'accompagnement",
        "MESURE D'ACCOMPAGNEMENT" => "Mesure d'accompagnement",
        'MOBILITE'             => 'Mobilité',
    ];

    /** Noms de primes repris du classeur (code interne → libellé). */
    private const ALLOWANCE_MAP = [
        'ik'                    => ['IK', 'Indemnité de kilométrage'],
        'mobilite'              => ['MOB', 'Indemnité de mobilité'],
        'mesure_accompagnement' => ['ACC', 'Mesure d’accompagnement'],
    ];

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Lit le fichier et retourne les lignes normalisées.
     *
     * @param string $filePath  Chemin du .xlsx
     * @param int    $headerRow Ligne des en-têtes (1 par défaut)
     *
     * @return array{rows: array<int, array<string, mixed>>, headers: array<string, string>, errors: array<int, string>}
     */
    public function read(string $filePath, int $headerRow = 1): array
    {
        if (!is_file($filePath)) {
            throw new \Exception('Fichier introuvable : ' . basename($filePath));
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);

        $headers = $this->readHeaders($sheet, $headerRow);
        if (empty($headers)) {
            throw new \Exception(
                'Aucun en-tête reconnu. Colonnes attendues : MATR., NOM & POSTNOM, SALAIRE…'
            );
        }

        $rows = [];
        $errors = [];
        $lastRow = $sheet->getHighestRow();

        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $row = $this->readRow($sheet, $headers, $r);

            // Ligne vide : on l'ignore silencieusement.
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            // Une ligne sans matricule ET sans nom n'est pas exploitable.
            if (empty($row['matricule']) && empty($row['nom'])) {
                $errors[] = 'Ligne ' . $r . ' : matricule et nom vides, ligne ignorée.';
                continue;
            }

            $row['_line'] = $r;
            $rows[] = $row;
        }

        return ['rows' => $rows, 'headers' => $headers, 'errors' => $errors];
    }

    /**
     * Associe chaque colonne de la feuille à une clé métier, via son en-tête.
     *
     * @return array<string, string> en-tête normalisé => colonne (lettre)
     */
    private function readHeaders(Worksheet $sheet, int $headerRow): array
    {
        $headers = [];
        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($c = 1; $c <= $lastCol; $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $raw = $sheet->getCell($col . $headerRow)->getValue();
            if ($raw === null || trim((string)$raw) === '') {
                continue;
            }

            $normalized = $this->normalizeHeader((string)$raw);
            $key = $this->resolveHeaderKey($normalized);

            if ($key !== null && isset(self::COLUMN_MAP[$key])) {
                $headers[$key] = $col;
            }
        }

        return $headers;
    }

    /**
     * Retrouve la clé métier d'un en-tête normalisé.
     *
     * Les alias sont eux-mêmes normalisés à la volée : `HEADER_ALIASES` est
     * écrit en clair (« DATE ENGAGEMENT », « MOBILITE »…) pour rester lisible,
     * et converti ici dans la même forme que l'en-tête de la feuille.
     */
    private function resolveHeaderKey(string $normalized): ?string
    {
        foreach (self::HEADER_ALIASES as $alias => $target) {
            if ($this->normalizeHeader($alias) === $normalized) {
                return $target;
            }
        }

        foreach (self::COLUMN_MAP as $header => $target) {
            if ($this->normalizeHeader($header) === $normalized) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Normalise un en-tête : minuscules, sans accents, sans ponctuation.
     */
    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        // Remplace les apostrophes typographiques par des droites.
        $value = str_replace(['’', '‘'], "'", $value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'û', 'ü', 'ç'], 'e', $value);

        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    /**
     * Extrait et convertit les valeurs d'une ligne.
     *
     * ⚠️ `$headers` est indexé par EN-TÊTE (ex. « MATR. ») ; on lit la valeur
     * dans cette colonne, puis on la range sous la CLÉ MÉTIER associée
     * (ex. « matricule ») — c'est cette clé qui est utilisée ensuite.
     */
    private function readRow(Worksheet $sheet, array $headers, int $rowNumber): array
    {
        $row = [];

        foreach ($headers as $header => $col) {
            $target = self::COLUMN_MAP[$header] ?? $header;

            $cell = $sheet->getCell($col . $rowNumber);
            $value = $cell->getValue();

            // Les formules ne sont pas importées : l'app recalcule.
            if ($cell->getDataType() === 'f') {
                $value = $cell->getOldCalculatedValue();
            }

            $row[$target] = $this->castValue($target, $value, $cell);
        }

        return $row;
    }

    /**
     * Convertit une valeur brute vers le type attendu par la colonne métier.
     */
    private function castValue(string $key, $value, $cell)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Mois et année : ce sont des compteurs entiers.
        if ($key === 'mois' || $key === 'annee') {
            return (int)$value;
        }

        // Le matricule est une CHAÎNE : « 1100011 » ne doit pas devenir un
        // entier (sinon il perd ses zéros initiaux et l'appariement échoue).
        if ($key === 'matricule') {
            return (string)$value;
        }

        // Date d'embauche : sérial Excel ou date déjà typée.
        if ($key === 'date_engagement') {
            if (is_numeric($value)) {
                return SharedDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
            return (string)$value;
        }

        // Les champs numériques (montants, taux, compteurs).
        $numericKeys = [
            'sitaf', 'charge_fiscale', 'mois', 'annee', 'jours_prestes',
            'base_salary', 'sursalary', 'ik', 'mobilite', 'mesure_accompagnement',
            'ier_rate',
        ];
        if (in_array($key, $numericKeys, true)) {
            // Certaines cellules utilisent un format à séparateur de milliers.
            if (is_string($value)) {
                $value = str_replace([' ', ','], ['', '.'], $value);
            }
            return (float)$value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Importe les lignes lues dans la base.
     *
     * Chaque ligne est appariée à un agent du POS, dans cet ordre :
     *   1. par MATRICULE (colonne MATR. du classeur) ;
     *   2. par NOM & POSTNOM (comparaison insensible à la casse et aux espaces).
     *
     * Un agent apparié reçoit :
     *   - la mise à jour de sa fiche Paie (direction, fonction, SITAF, charge
     *     fiscale, cat_leg, cat_prof, n° CNSS, date d'embauche, taux IER) ;
     *   - la création / mise à jour de son CONTRAT (salaire, sursalaire) ;
     *   - les PRIMES du récap (IK, Mobilité, Mesure d'accompagnement) ;
     *   - sa PRÉSENCE pour la période (jours prestés).
     *
     * @param array  $rows    Lignes normalisées (cf. read())
     * @param int    $shopId  Boutique cible
     * @param bool   $dryRun  true = simulation (aucune écriture)
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>, details: array<int, array>}
     */
    public function import(array $rows, int $shopId, bool $dryRun = false): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $details = [];

        $employeeModel = new PayrollEmployee();
        $contractModel = new PayrollContract();

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $match = $this->matchEmployee($row, $shopId);

            if (!$match) {
                $skipped++;
                $errors[] = 'Ligne ' . $line . ' : aucun agent ne correspond à '
                    . (($row['matricule'] ?? '') ?: ($row['nom'] ?? '?'))
                    . ' — ligne ignorée.';
                continue;
            }

            $employee = $match;
            $details[] = [
                'line'       => $line,
                'nom'        => $employee['nom_complet'],
                'matricule'  => $row['matricule'] ?? $employee['matricule'],
                'salaire'    => $row['base_salary'] ?? null,
                'sursalaire' => $row['sursalary'] ?? null,
            ];

            if ($dryRun) {
                $imported++;
                continue;
            }

            $this->applyEmployee($employeeModel, $employee['id'], $row);
            $this->applyContract($contractModel, $employee['id'], $shopId, $row);
            $this->applyAllowances($shopId, $row);
            $this->applyAttendance($shopId, $employee['id'], $row);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'details'  => $details,
        ];
    }

    /**
     * Retrouve la fiche Paie correspondant à une ligne du récap.
     *
     * @return array|false
     */
    private function matchEmployee(array $row, int $shopId)
    {
        $employees = (new PayrollEmployee())->all($shopId, false);

        // 1. Par matricule.
        $matricule = trim((string)($row['matricule'] ?? ''));
        if ($matricule !== '') {
            foreach ($employees as $e) {
                if (strcasecmp((string)$e['matricule'], $matricule) === 0) {
                    return $e;
                }
            }
        }

        // 2. Par nom (comparaison normalisée).
        $nom = trim((string)($row['nom'] ?? ''));
        if ($nom !== '') {
            $needle = $this->normalizeName($nom);
            foreach ($employees as $e) {
                if ($this->normalizeName((string)$e['nom_complet']) === $needle) {
                    return $e;
                }
            }
        }

        return false;
    }

    /**
     * Normalise un nom pour la comparaison (casse, accents, espaces).
     */
    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'û', 'ü', 'ç'], 'e', $name);
        $name = preg_replace('/[^a-z0-9]+/', '', $name);

        return $name;
    }

    /**
     * Met à jour la fiche Paie de l'agent avec les données du récap.
     */
    private function applyEmployee(PayrollEmployee $model, int $employeeId, array $row): void
    {
        $data = [];

        $map = [
            'direction'      => 'direction',
            'fonction'       => 'job_title',
            'sitaf'          => 'sitaf',
            'charge_fiscale' => 'tax_dependents',
            'cat_leg'        => 'cat_leg',
            'cat_prof'       => 'cat_prof',
            'cnss_number'    => 'cnss_number',
            'date_engagement' => 'hire_date',
        ];

        foreach ($map as $key => $field) {
            if (!empty($row[$key])) {
                $data[$field] = $row[$key];
            }
        }

        // L'IER est un pourcentage : la colonne du classeur contient 0, 25, …
        if (isset($row['ier_rate']) && $row['ier_rate'] !== null) {
            $data['ier_rate'] = $this->normalizeIerRate($row);
        }

        if (!empty($data)) {
            $model->update($employeeId, $data);
        }
    }

    /**
     * Normalise le taux d'IER.
     *
     * ⚠️ La colonne IER du classeur n'est PAS homogène :
     *   - sur les lignes où l'IER est une formule (`=P×25 %`), la cellule
     *     contient le MONTANT calculé (ex. 2 443,61) et non le taux ;
     *   - sur les lignes où l'IER est saisi à la main, la cellule contient le
     *     TAUX (0, 25, …).
     *
     * On distingue les deux : un taux d'IER est toujours <= 100. Au-delà, c'est
     * un montant → on le ramène au taux correspondant en le divisant par le
     * plafond CNSS (salaire + sursalaire + ancienneté), reconstitué ici.
     */
    private function normalizeIerRate(array $row): float
    {
        $value = (float)($row['ier_rate'] ?? 0);

        if ($value <= 0) {
            return 0.0;
        }

        // Déjà un taux (0..100 %).
        if ($value <= 100.0) {
            return $value;
        }

        // C'est un montant : on retrouve le taux sur le plafond CNSS.
        $base = (float)($row['base_salary'] ?? 0) + (float)($row['sursalary'] ?? 0);
        if ($base <= 0) {
            return 0.0;
        }

        // L'ancienneté (3 % par an) entre dans le plafond CNSS.
        $years = $this->yearsSince($row['date_engagement'] ?? null);
        $cnssBase = $base + ($base * 0.03 * $years);

        if ($cnssBase <= 0) {
            return 0.0;
        }

        return round(($value / $cnssBase) * 100, 4);
    }

    /**
     * Ancienneté en années (fractionnaire) à la date du jour.
     */
    private function yearsSince(?string $date): float
    {
        if (empty($date)) {
            return 0.0;
        }

        try {
            $from = new \DateTime($date);
        } catch (\Exception $e) {
            return 0.0;
        }

        $diff = $from->diff(new \DateTime(date('Y-m-d')));

        return $diff->y + ($diff->m / 12) + ($diff->d / 365);
    }

    /**
     * Crée ou met à jour le contrat de l'agent (salaire, sursalaire).
     */
    private function applyContract(PayrollContract $model, int $employeeId, int $shopId, array $row): void
    {
        if (!isset($row['base_salary']) || $row['base_salary'] === null) {
            return;
        }

        $data = [
            'base_salary' => (float)$row['base_salary'],
            'sursalary'  => (float)($row['sursalary'] ?? 0),
            'pay_type'   => 'monthly',
            'type'       => 'CDI',
            'currency'   => 'Fc',
            'is_active'  => 1,
            'is_paid'    => 1,
        ];

        // Date de début : celle de l'engagement, sinon aujourd'hui.
        $data['start_date'] = $row['date_engagement'] ?? date('Y-m-d');

        $existing = $model->findActiveByEmployee($employeeId);
        if ($existing) {
            $model->update($existing['id'], $data);
            return;
        }

        $data['employee_id'] = $employeeId;
        $model->create($data);
    }

    /**
     * Reprend les primes du récap (IK, Mobilité, Mesure d'accompagnement).
     *
     * Elles sont stockées comme avantages fixes (`payroll_allowances`) : le
     * modèle « Excel » les ajoute au brut via les avantages actifs.
     */
    private function applyAllowances(int $shopId, array $row): void
    {
        $allowanceModel = new \App\Models\PayrollAllowance();

        foreach (self::ALLOWANCE_MAP as $key => $meta) {
            [$code, $label] = $meta;
            $amount = $row[$key] ?? null;
            if ($amount === null || (float)$amount === 0.0) {
                continue;
            }

            $existing = $allowanceModel->findByCode($code, $shopId);
            $data = [
                'shop_id'         => $shopId,
                'code'            => $code,
                'label'           => $label,
                'calculation_type' => 'fixed',
                'amount'          => (float)$amount,
                'is_active'       => 1,
            ];

            if ($existing) {
                $allowanceModel->update($existing['id'], $data);
            } else {
                $allowanceModel->create($data);
            }
        }
    }

    /**
     * Reprend les jours prestés comme présences de la période.
     */
    private function applyAttendance(int $shopId, int $employeeId, array $row): void
    {
        $periodId = $this->resolvePeriod($row, $shopId);
        if (!$periodId) {
            return;
        }

        $workedDays = $row['jours_prestes'] ?? null;
        if ($workedDays === null || (float)$workedDays <= 0) {
            return;
        }

        $attendance = new \App\Models\PayrollAttendance();
        $attendance->createOrUpdate([
            'shop_id'              => $shopId,
            'employee_id'          => $employeeId,
            'payroll_period_id'    => $periodId,
            'worked_days'          => (float)$workedDays,
            'worked_hours'         => (float)$workedDays * 8,
            'expected_working_days' => (float)$workedDays,
            'paid_days'            => (float)$workedDays,
        ]);
    }

    /**
     * Retrouve la période (mois/année) visée par le récap.
     */
    private function resolvePeriod(array $row, int $shopId)
    {
        $month = (int)($row['mois'] ?? 0);
        $year = (int)($row['annee'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 2000) {
            return null;
        }

        $period = (new \App\Models\PayrollPeriod())->findByShopMonthYear($shopId, $month, $year);

        return $period ? (int)$period['id'] : null;
    }
}
