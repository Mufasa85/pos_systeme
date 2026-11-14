<?php

namespace App\Models;

/**
 * Paramètres de calcul du module Paie (table clé/valeur `payroll_settings`).
 *
 * Ces paramètres permettent d'appliquer le modèle de paie réel documenté
 * dans `Recap paie.xlsx` (classeur du comptable) et dans la base `paie_db`
 * (ancien projet paie autonome) : barème IRT, taux de change, logement 30 %,
 * transport 21,15 % du LOG, ancienneté 3 % par année, réduction d'IRT par
 * personne à charge.
 *
 * ⚠️ Rétrocompatibilité : `calculation_mode = 'standard'` (défaut) conserve
 * exactement le modèle générique historique. Le mode `'excel_rdc'` active le
 * modèle du classeur.
 *
 * La résolution suit le même schéma que les autres modèles paramétrables du
 * module : la ligne de la boutique (`shop_id = X`) prime sur la ligne globale
 * (`shop_id IS NULL`).
 */
class PayrollSettings
{
    /** Mode de calcul historique (base + primes − retenues − cotisations). */
    public const MODE_STANDARD = 'standard';

    /** Mode « modèle Excel / RDC » (barème IRT, LOG/TRANS, ancienneté 3 %/an). */
    public const MODE_EXCEL_RDC = 'excel_rdc';

    /** Valeurs par défaut (alignées sur la migration 2026_11_13). */
    public const DEFAULTS = [
        'calculation_mode'         => self::MODE_STANDARD,
        'country'                  => 'RDC',
        'currency'                 => 'Fc',
        'default_working_days'     => '26',
        'exchange_rate'            => '2658.7148',
        'exchange_rate_source'     => 'api',
        'seniority_rate_per_year'  => '3',
        'seniority_max_years'      => '0',
        'housing_rate'             => '30',
        'transport_rate'           => '21.15',
        'tax_dependents_allowance' => '2',
        'cnss_employee_rate'       => '5',
        'cnss_employer_rate'       => '6.5',
        'inpp_employer_rate'       => '3',
        'onem_employer_rate'       => '0.2',
        'ier_employer_rate'        => '25',
        'irt_enabled'              => '1',
        'prorate_base'             => '0',
    ];

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Toutes les lignes de paramètres (globales + boutique).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all($shopId = null): array
    {
        if (!$shopId) {
            return $this->db->fetchAll(
                'SELECT * FROM payroll_settings WHERE shop_id IS NULL ORDER BY id ASC'
            );
        }

        return $this->db->fetchAll(
            'SELECT a.* FROM payroll_settings a
             WHERE a.shop_id = ? OR (a.shop_id IS NULL AND NOT EXISTS (
                 SELECT 1 FROM payroll_settings b WHERE b.setting_key = a.setting_key AND b.shop_id = ?
             ))
             ORDER BY a.id ASC',
            [$shopId, $shopId]
        );
    }

    /**
     * Paramètres résolus (boutique prioritaire, sinon global, sinon défaut).
     *
     * @return array<string, string>
     */
    public function resolved($shopId = null): array
    {
        $values = self::DEFAULTS;

        foreach ($this->all($shopId) as $row) {
            $key = $row['setting_key'] ?? null;
            if ($key === null) {
                continue;
            }
            $value = $row['setting_value'];
            $values[$key] = ($value === null || $value === '')
                ? (self::DEFAULTS[$key] ?? '')
                : (string)$value;
        }

        return $values;
    }

    /**
     * Lit un paramètre unique, résolu pour la boutique donnée.
     */
    public function get(string $key, $shopId = null, $default = null): string
    {
        $values = $this->resolved($shopId);
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default !== null ? (string)$default : '';
    }

    public function getFloat(string $key, $shopId = null): float
    {
        return (float)$this->get($key, $shopId);
    }

    public function getInt(string $key, $shopId = null): int
    {
        return (int)$this->get($key, $shopId);
    }

    public function getBool(string $key, $shopId = null): bool
    {
        $value = strtolower(trim($this->get($key, $shopId)));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Vrai si le modèle « Excel / RDC » est actif pour cette boutique.
     */
    public function isExcelMode($shopId = null): bool
    {
        return $this->get('calculation_mode', $shopId) === self::MODE_EXCEL_RDC;
    }

    /**
     * Enregistre (ou met à jour) un paramètre.
     *
     * ⚠️ `shop_id` valant NULL pour les paramètres globaux, un
     * `INSERT ... ON DUPLICATE KEY UPDATE` ne fonctionne PAS : en SQL, NULL
     * n'est jamais égal à NULL, donc MySQL n'y voit pas de doublon et
     * l'index UNIQUE ne se déclenche pas. On passe donc par un SELECT
     * suivi d'un UPDATE ou d'un INSERT explicites.
     */
    public function save(string $key, $value, $shopId = null): bool
    {
        $existing = $this->db->fetch(
            'SELECT id FROM payroll_settings
             WHERE setting_key = ? AND (shop_id = ? OR (shop_id IS NULL AND ? IS NULL))
             LIMIT 1',
            [$key, $shopId, $shopId]
        );

        if ($existing) {
            return (bool)$this->db->execute(
                'UPDATE payroll_settings SET setting_value = ? WHERE id = ?',
                [$value, $existing['id']]
            );
        }

        return (bool)$this->db->execute(
            'INSERT INTO payroll_settings (shop_id, setting_key, setting_value)
             VALUES (?, ?, ?)',
            [$shopId, $key, $value]
        );
    }
}
