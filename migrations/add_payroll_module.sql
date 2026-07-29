-- ============================================================================
-- MIGRATION : Module de paie (payroll) intégré au POS multi-boutique
-- Auteur     : Cascade / projet POS
-- Objectif   : Créer les tables paie dans la base du POS avec scoping shop_id
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Tables de référence (globales ou surchargeables par boutique)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payroll_departments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_departments_name_shop_idx` (`name`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_job_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_job_cat_name_shop_idx` (`name`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_payment_methods` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_pay_methods_code_shop_idx` (`code`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_allowances` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `calculation_type` ENUM('fixed', 'percent_base') NOT NULL DEFAULT 'fixed',
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_allowances_code_shop_idx` (`code`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_deductions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `calculation_type` ENUM('fixed', 'percent_gross') NOT NULL DEFAULT 'fixed',
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_deductions_code_shop_idx` (`code`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_contribution_rates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `employee_rate` DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'Taux salarié %',
  `employer_rate` DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'Taux employeur %',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_contrib_code_shop_idx` (`code`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_seniority_bands` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT DEFAULT NULL COMMENT 'NULL = valeur globale',
  `min_years` DECIMAL(5,2) NOT NULL,
  `max_years` DECIMAL(5,2) DEFAULT NULL COMMENT 'NULL = pas de plafond',
  `percent` DECIMAL(8,4) NOT NULL,
  `label` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 2. Employés liés aux vendeurs du POS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payroll_employees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL COMMENT 'FK vers utilisateurs.id',
  `shop_id` INT NOT NULL COMMENT 'Boutique de rattachement',
  `matricule` VARCHAR(50) NOT NULL,
  `device_user_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID pointeuse biométrique',
  `hire_date` DATE NOT NULL,
  `iban` VARCHAR(50) DEFAULT NULL,
  `cnss_number` VARCHAR(50) DEFAULT NULL,
  `direction` VARCHAR(120) DEFAULT NULL,
  `job_title` VARCHAR(120) DEFAULT NULL,
  `sitaf` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tax_dependents` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `cat_leg` VARCHAR(50) DEFAULT NULL,
  `cat_prof` VARCHAR(50) DEFAULT NULL,
  `ier_rate` DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'Taux IER employeur %',
  `department_id` INT DEFAULT NULL,
  `job_category_id` INT DEFAULT NULL,
  `status` ENUM('active', 'suspended', 'left') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_employees_matricule_shop_idx` (`matricule`, `shop_id`),
  UNIQUE KEY `payroll_employees_user_idx` (`user_id`),
  UNIQUE KEY `payroll_employees_device_shop_idx` (`device_user_id`, `shop_id`),
  KEY `payroll_employees_status_hire_idx` (`status`, `hire_date`),
  CONSTRAINT `payroll_employees_user_fk` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_employees_shop_fk` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_employees_department_fk` FOREIGN KEY (`department_id`) REFERENCES `payroll_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_employees_job_category_fk` FOREIGN KEY (`job_category_id`) REFERENCES `payroll_job_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_contracts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'CDI',
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `base_salary` DECIMAL(15,2) NOT NULL,
  `sursalary` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `pay_type` ENUM('monthly', 'daily', 'hourly') NOT NULL DEFAULT 'monthly',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'XOF',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_contracts_employee_idx` (`employee_id`, `is_active`),
  CONSTRAINT `payroll_contracts_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 3. Périodes et présences
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `working_days` DECIMAL(5,2) NOT NULL DEFAULT 22.00,
  `status` ENUM('draft', 'calculated', 'validated', 'paid', 'closed') NOT NULL DEFAULT 'draft',
  `calculated_at` DATETIME DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_periods_month_year_shop_idx` (`year`, `month`, `shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_attendance` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `payroll_period_id` INT NOT NULL,
  `worked_days` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `worked_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `expected_working_days` DECIMAL(5,2) NOT NULL DEFAULT 22.00,
  `paid_days` DECIMAL(5,2) DEFAULT NULL COMMENT 'Jours rémunérés calculés',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_attendance_employee_period_idx` (`employee_id`, `payroll_period_id`),
  CONSTRAINT `payroll_attendance_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_attendance_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_absences` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `payroll_period_id` INT DEFAULT NULL,
  `type` ENUM('paid_leave', 'sick', 'unpaid', 'unjustified', 'other') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `days` DECIMAL(5,2) NOT NULL,
  `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_absences_employee_dates_idx` (`employee_id`, `start_date`),
  CONSTRAINT `payroll_absences_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_absences_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_overtimes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `payroll_period_id` INT NOT NULL,
  `work_date` DATE NOT NULL,
  `hours` DECIMAL(6,2) NOT NULL,
  `rate_type` ENUM('normal_25', 'night_50', 'holiday_100', 'custom') NOT NULL DEFAULT 'normal_25',
  `multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.25,
  `amount` DECIMAL(15,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_overtimes_employee_period_idx` (`employee_id`, `payroll_period_id`),
  CONSTRAINT `payroll_overtimes_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_overtimes_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 4. Pointage / pointeuse
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payroll_time_clock_imports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `imported_by` INT DEFAULT NULL,
  `rows_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_ok` INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_error` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `payroll_time_clock_imports_user_fk` FOREIGN KEY (`imported_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_time_clock_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `event_type` ENUM('IN', 'OUT', 'BREAK_START', 'BREAK_END', 'UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `event_at` DATETIME NOT NULL,
  `source` ENUM('manual', 'device_usb', 'device_network', 'web', 'import_csv') NOT NULL DEFAULT 'manual',
  `verify_mode` ENUM('FP', 'CARD', 'PIN', 'OTHER') DEFAULT NULL,
  `device_sn` VARCHAR(100) DEFAULT NULL,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `in_zone` TINYINT(1) DEFAULT NULL,
  `identity_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `validated` TINYINT(1) NOT NULL DEFAULT 1,
  `import_batch_id` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_time_clock_employee_event_idx` (`employee_id`, `event_at`),
  UNIQUE KEY `payroll_time_clock_dedup_unique` (`employee_id`, `event_at`, `event_type`),
  CONSTRAINT `payroll_time_clock_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_time_clock_import_fk` FOREIGN KEY (`import_batch_id`) REFERENCES `payroll_time_clock_imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5. Bulletins et paiements
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payroll_payslips` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `payroll_period_id` INT NOT NULL,
  `gross_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `taxable_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cnss_base` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `employer_charges` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `employer_cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `net_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `status` ENUM('draft', 'calculated', 'validated', 'paid') NOT NULL DEFAULT 'draft',
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_payslips_employee_period_idx` (`employee_id`, `payroll_period_id`),
  CONSTRAINT `payroll_payslips_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_payslips_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_payslip_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `payslip_id` INT NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `type` ENUM('earning', 'deduction', 'employer') NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT NULL,
  `rate` DECIMAL(15,4) DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `payroll_payslip_lines_payslip_idx` (`payslip_id`),
  CONSTRAINT `payroll_payslip_lines_payslip_fk` FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `shop_id` INT NOT NULL,
  `payslip_id` INT NOT NULL,
  `payment_method_id` INT DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `paid_at` DATE NOT NULL,
  `reference` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_payments_payslip_idx` (`payslip_id`),
  CONSTRAINT `payroll_payments_payslip_fk` FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_payments_method_fk` FOREIGN KEY (`payment_method_id`) REFERENCES `payroll_payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_payments_user_fk` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
