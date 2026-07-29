-- Seed : crée automatiquement un employé Paie pour chaque vendeur existant
-- qui n'a pas encore de fiche dans payroll_employees.
-- Le matricule est repris du agent_code de l'utilisateur ;
-- s'il n'est pas renseigné, on génère 'EMP-{id_utilisateur}'.
-- La date d'embauche est aujourd'hui.

INSERT INTO payroll_employees (
    user_id,
    shop_id,
    matricule,
    device_user_id,
    hire_date,
    iban,
    cnss_number,
    direction,
    job_title,
    sitaf,
    tax_dependents,
    cat_leg,
    cat_prof,
    ier_rate,
    department_id,
    job_category_id,
    status
)
SELECT
    u.id,
    u.shop_id,
    COALESCE(NULLIF(u.agent_code, ''), CONCAT('EMP-', u.id)),
    NULL,
    CURDATE(),
    NULL,
    NULL,
    NULL,
    NULL,
    0,
    0,
    NULL,
    NULL,
    0,
    NULL,
    NULL,
    'active'
FROM utilisateurs u
WHERE u.role = 'vendeur'
  AND u.shop_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM payroll_employees pe WHERE pe.user_id = u.id
  );
