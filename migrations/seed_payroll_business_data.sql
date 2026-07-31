-- Paramétrage Paie de test pour RDC / Fc
-- Toutes les cotisations, barèmes, avantages et retenues sont à 0.
-- L'administrateur pourra les mettre à jour via Paramètres > Paie.

INSERT INTO payroll_contribution_rates
    (shop_id, code, label, employee_rate, employer_rate, is_active)
VALUES
    (NULL, 'CNSS', 'Caisse nationale de sécurité sociale', 0.0000, 0.0000, 1),
    (NULL, 'INPP', 'Institut national de préparation professionnelle', 0.0000, 0.0000, 1),
    (NULL, 'ONEM', 'Office national de l''emploi', 0.0000, 0.0000, 1),
    (NULL, 'INSS', 'Institut national de sécurité sociale', 0.0000, 0.0000, 1)
ON DUPLICATE KEY UPDATE
    employee_rate = VALUES(employee_rate),
    employer_rate = VALUES(employer_rate),
    label = VALUES(label),
    is_active = VALUES(is_active);

INSERT INTO payroll_seniority_bands
    (shop_id, min_years, max_years, percent, label)
SELECT v.shop_id, v.min_years, v.max_years, v.percent, v.label
FROM (
    SELECT NULL AS shop_id, 0.00 AS min_years, 2.00 AS max_years, 0.0000 AS percent, '0 à 2 ans' AS label
    UNION ALL
    SELECT NULL, 2.00, 5.00, 0.0000, '2 à 5 ans'
    UNION ALL
    SELECT NULL, 5.00, 10.00, 0.0000, '5 à 10 ans'
    UNION ALL
    SELECT NULL, 10.00, 15.00, 0.0000, '10 à 15 ans'
    UNION ALL
    SELECT NULL, 15.00, NULL, 0.0000, '15 ans et +'
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM payroll_seniority_bands b
    WHERE b.shop_id IS NULL AND b.min_years = v.min_years
);

INSERT INTO payroll_allowances
    (shop_id, code, label, calculation_type, amount, is_active)
VALUES
    (NULL, 'TRANSPORT', 'Prime de transport', 'fixed', 0.00, 1),
    (NULL, 'LOGEMENT', 'Allocation logement', 'fixed', 0.00, 1),
    (NULL, 'RISQUE', 'Prime de risque', 'fixed', 0.00, 1),
    (NULL, 'REPAS', 'Prime de repas', 'fixed', 0.00, 1),
    (NULL, 'OUTILLAGE', 'Prime d''outillage', 'fixed', 0.00, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    calculation_type = VALUES(calculation_type),
    amount = VALUES(amount),
    is_active = VALUES(is_active);

INSERT INTO payroll_deductions
    (shop_id, code, label, calculation_type, amount, is_active)
VALUES
    (NULL, 'AVANCE', 'Avance sur salaire', 'fixed', 0.00, 1),
    (NULL, 'PRET', 'Prêt entreprise', 'fixed', 0.00, 1),
    (NULL, 'ABSENCE', 'Absence non payée', 'fixed', 0.00, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    calculation_type = VALUES(calculation_type),
    amount = VALUES(amount),
    is_active = VALUES(is_active);
