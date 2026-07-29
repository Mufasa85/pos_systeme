-- Données de test pour valider tout le flux Paie (Phases 1 à 5)
-- Nécessite : au moins une boutique et au moins un vendeur transformé en employé.

SET @shop_id = (SELECT id FROM shops ORDER BY id LIMIT 1);
SET @employee_id = (
    SELECT pe.id
    FROM payroll_employees pe
    JOIN utilisateurs u ON pe.user_id = u.id
    WHERE u.role = 'vendeur'
      AND pe.shop_id = @shop_id
    ORDER BY pe.id
    LIMIT 1
);
SET @year = YEAR(CURDATE());
SET @month = MONTH(CURDATE());
SET @period_day = CONCAT(@year, '-', LPAD(@month, 2, '0'), '-15');

-- Évite de planter si aucune boutique/employé n'existe
SET @shop_id = COALESCE(@shop_id, 1);
SET @employee_id = COALESCE(@employee_id, 1);

-- Période paie courante (draft)
INSERT INTO payroll_periods (shop_id, month, year, working_days, status)
VALUES (@shop_id, @month, @year, 22.00, 'draft')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @period_id = LAST_INSERT_ID();

-- Contrat de test s'il n'existe pas déjà un contrat actif
INSERT INTO payroll_contracts (employee_id, type, start_date, base_salary, sursalary, pay_type, currency, is_active)
SELECT @employee_id, 'CDI', CONCAT(@year, '-', LPAD(@month, 2, '0'), '-01'),
       500000.00, 0.00, 'monthly', 'Fc', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM payroll_contracts c
    WHERE c.employee_id = @employee_id AND c.is_active = 1
);

-- Présence du mois (22 jours / 176 heures)
INSERT INTO payroll_attendance (shop_id, employee_id, payroll_period_id, worked_days, worked_hours, expected_working_days, paid_days)
VALUES (@shop_id, @employee_id, @period_id, 22.00, 176.00, 22.00, 22.00)
ON DUPLICATE KEY UPDATE
    worked_days = VALUES(worked_days),
    worked_hours = VALUES(worked_hours),
    expected_working_days = VALUES(expected_working_days),
    paid_days = VALUES(paid_days);

-- 2 heures supplémentaires le 15 du mois
INSERT INTO payroll_overtimes (shop_id, employee_id, payroll_period_id, work_date, hours, rate_type, multiplier)
SELECT @shop_id, @employee_id, @period_id, @period_day, 2.00, 'normal_25', 1.25
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM payroll_overtimes o
    WHERE o.employee_id = @employee_id
      AND o.payroll_period_id = @period_id
      AND o.work_date = @period_day
);

-- Méthode de paiement par défaut
INSERT INTO payroll_payment_methods (shop_id, code, label, is_active)
VALUES (@shop_id, 'CASH', 'Espèces', 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    is_active = VALUES(is_active);
