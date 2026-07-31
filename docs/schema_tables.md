# Schéma des tables - pos_system

Ce document décrit les 39 tables de la base `pos_system` : rôle, colonnes, types, clés et clés étrangères.

---

## audit_logs

- **Rôle** : Journal d'audit des actions utilisateur
- **Lignes estimées** : 155

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| user_id | int | NULL | NULL | MUL | - |
| shop_id | int | NULL | NULL | MUL | - |
| action | varchar(50) | NOT NULL | NULL | MUL | - |
| entity | varchar(50) | NOT NULL | NULL | - | - |
| entity_id | int | NULL | NULL | - | - |
| details | json | NULL | NULL | - | - |
| ip_address | varchar(45) | NULL | NULL | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | MUL DEFAULT_GENERATED | - |

## categories

- **Rôle** : Catégories de produits
- **Lignes estimées** : 4

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| category | varchar(120) | NOT NULL | NULL | - | - |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## clients

- **Rôle** : Fiches clients
- **Lignes estimées** : 8

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| numero | varchar(30) | NOT NULL | NULL | - | - |
| nom_client | varchar(50) | NOT NULL | NULL | - | - |
| code_client | varchar(20) | NOT NULL | NULL | UNI | - |
| type_client_id | int | NOT NULL | NULL | MUL | -> type_client.id |
| nif | text | NULL | NULL | - | - |
| adresse | varchar(255) | NULL | NULL | - | - |
| shop_id | int | NULL | NULL | MUL | -> shops.id |

## company_info

- **Rôle** : Informations de l'entreprise
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| name | varchar(100) | NULL | NULL | - | - |
| address | varchar(255) | NULL | NULL | - | - |
| email | varchar(100) | NULL | NULL | - | - |
| pdv | varchar(50) | NULL | NULL | - | - |
| phone | varchar(30) | NULL | NULL | - | - |
| ice | varchar(50) | NULL | NULL | - | - |
| rccm | varchar(50) | NULL | NULL | - | - |
| isf | varchar(50) | NULL | NULL | - | - |
| nid | varchar(100) | NULL | NULL | - | - |
| token | varchar(255) | NULL | NULL | - | - |
| port | varchar(10) | NULL | NULL | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## details_vente

- **Rôle** : Lignes de produits pour chaque vente
- **Lignes estimées** : 547

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| vente_id | int | NOT NULL | NULL | MUL | - |
| produit_id | int | NOT NULL | NULL | MUL | - |
| quantite | int | NOT NULL | NULL | - | - |
| prix | decimal(10,2) | NOT NULL | NULL | - | - |
| remise_type | varchar(10) | NULL | percent | - | - |
| remise_value | decimal(10,2) | NULL | 0.00 | - | - |
| taxe_specifique_type | varchar(10) | NULL | % | - | - |
| taxe_specifique_value | decimal(10,2) | NULL | 0.00 | - | - |

## details_vente_archive

- **Rôle** : Lignes archivées des anciennes ventes
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI | - |
| vente_id | int | NOT NULL | NULL | MUL | - |
| produit_id | int | NOT NULL | NULL | - | - |
| quantite | int | NOT NULL | NULL | - | - |
| prix | decimal(10,2) | NOT NULL | NULL | - | - |
| remise_type | varchar(10) | NULL | percent | - | - |
| remise_value | decimal(10,2) | NULL | 0.00 | - | - |
| taxe_specifique_type | varchar(10) | NULL | % | - | - |
| taxe_specifique_value | decimal(10,2) | NULL | 0.00 | - | - |

## login_attempts

- **Rôle** : Tentatives de connexion (sécurité)
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| username | varchar(50) | NOT NULL | NULL | MUL | - |
| ip_address | varchar(45) | NOT NULL | NULL | MUL | - |
| attempted_at | datetime | NULL | CURRENT_TIMESTAMP | MUL DEFAULT_GENERATED | - |

## notifications

- **Rôle** : Notifications utilisateurs
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| user_id | int | NULL | NULL | MUL | -> utilisateurs.id |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| type | enum('stock_low','sale_target','suspicious_action','system') | NOT NULL | NULL | MUL | - |
| title | varchar(150) | NOT NULL | NULL | - | - |
| message | text | NOT NULL | NULL | - | - |
| link | varchar(255) | NULL | NULL | - | - |
| is_read | tinyint(1) | NOT NULL | 0 | MUL | - |
| sent_email | tinyint(1) | NOT NULL | 0 | - | - |
| sent_sms | tinyint(1) | NOT NULL | 0 | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | MUL DEFAULT_GENERATED | - |

## otp_codes

- **Rôle** : Codes OTP pour la 2FA
- **Lignes estimées** : 68

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| user_id | int | NOT NULL | NULL | MUL | -> utilisateurs.id |
| code | varchar(6) | NOT NULL | NULL | MUL | - |
| type | enum('login','password_reset') | NOT NULL | login | - | - |
| channel | enum('email','sms') | NOT NULL | email | - | - |
| expires_at | datetime | NOT NULL | NULL | MUL | - |
| used | tinyint(1) | NOT NULL | 0 | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## password_resets

- **Rôle** : Tokens de réinitialisation de mot de passe
- **Lignes estimées** : 18

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| user_id | int | NOT NULL | NULL | MUL | -> utilisateurs.id |
| token | varchar(64) | NOT NULL | NULL | UNI | - |
| channel | enum('email','sms') | NOT NULL | NULL | - | - |
| expires_at | datetime | NOT NULL | NULL | MUL | - |
| used | tinyint(1) | NOT NULL | 0 | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_absences

- **Rôle** : Absences enregistrées en paie
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| payroll_period_id | int | NULL | NULL | MUL | -> payroll_periods.id |
| type | enum('paid_leave','sick','unpaid','unjustified','other') | NOT NULL | NULL | - | - |
| start_date | date | NOT NULL | NULL | - | - |
| end_date | date | NOT NULL | NULL | - | - |
| days | decimal(5,2) | NOT NULL | NULL | - | - |
| is_paid | tinyint(1) | NOT NULL | 0 | - | - |
| notes | varchar(255) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_allowances

- **Rôle** : Primes et avantages salariaux
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| code | varchar(30) | NOT NULL | NULL | MUL | - |
| label | varchar(120) | NOT NULL | NULL | - | - |
| calculation_type | enum('fixed','percent_base') | NOT NULL | fixed | - | - |
| amount | decimal(15,2) | NOT NULL | 0.00 | - | - |
| is_active | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_attendance

- **Rôle** : Présences / jours travaillés
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| payroll_period_id | int | NOT NULL | NULL | MUL | -> payroll_periods.id |
| worked_days | decimal(5,2) | NOT NULL | 0.00 | - | - |
| worked_hours | decimal(8,2) | NOT NULL | 0.00 | - | - |
| expected_working_days | decimal(5,2) | NOT NULL | 22.00 | - | - |
| paid_days | decimal(5,2) | NULL | NULL | - | - |
| notes | varchar(255) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_contracts

- **Rôle** : Contrats de travail
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| type | varchar(50) | NOT NULL | CDI | - | - |
| start_date | date | NOT NULL | NULL | - | - |
| end_date | date | NULL | NULL | - | - |
| base_salary | decimal(15,2) | NOT NULL | NULL | - | - |
| sursalary | decimal(15,2) | NOT NULL | 0.00 | - | - |
| pay_type | enum('monthly','daily','hourly') | NOT NULL | monthly | - | - |
| currency | varchar(10) | NOT NULL | XOF | - | - |
| is_active | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_contribution_rates

- **Rôle** : Taux de cotisations sociales
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| code | varchar(30) | NOT NULL | NULL | MUL | - |
| label | varchar(120) | NOT NULL | NULL | - | - |
| employee_rate | decimal(8,4) | NOT NULL | 0.0000 | - | - |
| employer_rate | decimal(8,4) | NOT NULL | 0.0000 | - | - |
| is_active | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_deductions

- **Rôle** : Retenues et déductions salariales
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| code | varchar(30) | NOT NULL | NULL | MUL | - |
| label | varchar(120) | NOT NULL | NULL | - | - |
| calculation_type | enum('fixed','percent_gross') | NOT NULL | fixed | - | - |
| amount | decimal(15,2) | NOT NULL | 0.00 | - | - |
| is_active | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_departments

- **Rôle** : Départements de l'entreprise
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| name | varchar(120) | NOT NULL | NULL | MUL | - |
| code | varchar(30) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_employees

- **Rôle** : Employés du module paie liés aux utilisateurs
- **Lignes estimées** : 1

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| user_id | int | NOT NULL | NULL | UNI | -> utilisateurs.id |
| shop_id | int | NOT NULL | NULL | MUL | -> shops.id |
| matricule | varchar(50) | NOT NULL | NULL | MUL | - |
| device_user_id | varchar(50) | NULL | NULL | MUL | - |
| hire_date | date | NOT NULL | NULL | - | - |
| iban | varchar(50) | NULL | NULL | - | - |
| cnss_number | varchar(50) | NULL | NULL | - | - |
| direction | varchar(120) | NULL | NULL | - | - |
| job_title | varchar(120) | NULL | NULL | - | - |
| sitaf | tinyint unsigned | NOT NULL | 0 | - | - |
| tax_dependents | tinyint unsigned | NOT NULL | 0 | - | - |
| cat_leg | varchar(50) | NULL | NULL | - | - |
| cat_prof | varchar(50) | NULL | NULL | - | - |
| ier_rate | decimal(8,4) | NOT NULL | 0.0000 | - | - |
| department_id | int | NULL | NULL | MUL | -> payroll_departments.id |
| job_category_id | int | NULL | NULL | MUL | -> payroll_job_categories.id |
| status | enum('active','suspended','left') | NOT NULL | active | MUL | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_job_categories

- **Rôle** : Catégories de métiers
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| name | varchar(120) | NOT NULL | NULL | MUL | - |
| code | varchar(30) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_overtimes

- **Rôle** : Heures supplémentaires
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| payroll_period_id | int | NOT NULL | NULL | MUL | -> payroll_periods.id |
| work_date | date | NOT NULL | NULL | - | - |
| hours | decimal(6,2) | NOT NULL | NULL | - | - |
| rate_type | enum('normal_25','night_50','holiday_100','custom') | NOT NULL | normal_25 | - | - |
| multiplier | decimal(5,2) | NOT NULL | 1.25 | - | - |
| amount | decimal(15,2) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_payment_methods

- **Rôle** : Modes de paiement du salaire
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| code | varchar(30) | NOT NULL | NULL | MUL | - |
| label | varchar(100) | NOT NULL | NULL | - | - |
| is_active | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_payments

- **Rôle** : Paiements de paie effectués
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| payslip_id | int | NOT NULL | NULL | MUL | -> payroll_payslips.id |
| payment_method_id | int | NULL | NULL | MUL | -> payroll_payment_methods.id |
| amount | decimal(15,2) | NOT NULL | NULL | - | - |
| paid_at | date | NOT NULL | NULL | - | - |
| reference | varchar(120) | NULL | NULL | - | - |
| status | enum('pending','paid','cancelled') | NOT NULL | pending | - | - |
| created_by | int | NULL | NULL | MUL | -> utilisateurs.id |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_payslip_lines

- **Rôle** : Lignes détaillées des bulletins de paie
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| payslip_id | int | NOT NULL | NULL | MUL | -> payroll_payslips.id |
| code | varchar(30) | NOT NULL | NULL | - | - |
| label | varchar(190) | NOT NULL | NULL | - | - |
| type | enum('earning','deduction','employer') | NOT NULL | NULL | - | - |
| quantity | decimal(10,2) | NULL | NULL | - | - |
| rate | decimal(15,4) | NULL | NULL | - | - |
| amount | decimal(15,2) | NOT NULL | NULL | - | - |
| sort_order | int | NOT NULL | 0 | - | - |

## payroll_payslips

- **Rôle** : Bulletins de paie générés
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| payroll_period_id | int | NOT NULL | NULL | MUL | -> payroll_periods.id |
| gross_amount | decimal(15,2) | NOT NULL | 0.00 | - | - |
| taxable_amount | decimal(15,2) | NOT NULL | 0.00 | - | - |
| cnss_base | decimal(15,2) | NOT NULL | 0.00 | - | - |
| total_deductions | decimal(15,2) | NOT NULL | 0.00 | - | - |
| employer_charges | decimal(15,2) | NOT NULL | 0.00 | - | - |
| employer_cost | decimal(15,2) | NOT NULL | 0.00 | - | - |
| net_amount | decimal(15,2) | NOT NULL | 0.00 | - | - |
| status | enum('draft','calculated','validated','paid') | NOT NULL | draft | - | - |
| pdf_path | varchar(255) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## payroll_periods

- **Rôle** : Périodes de paie
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| month | tinyint unsigned | NOT NULL | NULL | - | - |
| year | smallint unsigned | NOT NULL | NULL | MUL | - |
| working_days | decimal(5,2) | NOT NULL | 22.00 | - | - |
| status | enum('draft','calculated','validated','paid','closed') | NOT NULL | draft | - | - |
| calculated_at | datetime | NULL | NULL | - | - |
| validated_at | datetime | NULL | NULL | - | - |
| closed_at | datetime | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_seniority_bands

- **Rôle** : Barêmes d'ancienneté
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NULL | NULL | - | - |
| min_years | decimal(5,2) | NOT NULL | NULL | - | - |
| max_years | decimal(5,2) | NULL | NULL | - | - |
| percent | decimal(8,4) | NOT NULL | NULL | - | - |
| label | varchar(120) | NULL | NULL | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_time_clock_events

- **Rôle** : Événements de pointage
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | bigint | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| employee_id | int | NOT NULL | NULL | MUL | -> payroll_employees.id |
| event_type | enum('IN','OUT','BREAK_START','BREAK_END','UNKNOWN') | NOT NULL | UNKNOWN | - | - |
| event_at | datetime | NOT NULL | NULL | - | - |
| source | enum('manual','device_usb','device_network','web','import_csv') | NOT NULL | manual | - | - |
| verify_mode | enum('FP','CARD','PIN','OTHER') | NULL | NULL | - | - |
| device_sn | varchar(100) | NULL | NULL | - | - |
| latitude | decimal(10,7) | NULL | NULL | - | - |
| longitude | decimal(10,7) | NULL | NULL | - | - |
| in_zone | tinyint(1) | NULL | NULL | - | - |
| identity_verified | tinyint(1) | NOT NULL | 0 | - | - |
| validated | tinyint(1) | NOT NULL | 1 | - | - |
| import_batch_id | int | NULL | NULL | MUL | -> payroll_time_clock_imports.id |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## payroll_time_clock_imports

- **Rôle** : Imports de pointage
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| shop_id | int | NOT NULL | NULL | - | - |
| filename | varchar(255) | NOT NULL | NULL | - | - |
| imported_by | int | NULL | NULL | MUL | -> utilisateurs.id |
| rows_total | int unsigned | NOT NULL | 0 | - | - |
| rows_ok | int unsigned | NOT NULL | 0 | - | - |
| rows_skipped | int unsigned | NOT NULL | 0 | - | - |
| rows_error | int unsigned | NOT NULL | 0 | - | - |
| created_at | datetime | NOT NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## product_batches

- **Rôle** : Lots de stock produits
- **Lignes estimées** : 23

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| product_id | int | NOT NULL | NULL | MUL | -> produits.id |
| batch_number | varchar(50) | NULL | NULL | - | - |
| stock | float | NOT NULL | 0 | - | - |
| date_expiration | date | NULL | NULL | MUL | - |
| date_reception | date | NULL | NULL | - | - |
| created_at | timestamp | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## produits

- **Rôle** : Catalogue de produits
- **Lignes estimées** : 22

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| code_barres | varchar(50) | NOT NULL | NULL | UNI | - |
| nom | varchar(100) | NOT NULL | NULL | - | - |
| category_id | int | NOT NULL | NULL | MUL | -> categories.id |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| prix | decimal(10,2) | NOT NULL | NULL | - | - |
| stock | float | NOT NULL | 0 | - | - |
| stock_minimum | float | NOT NULL | 0 | - | - |
| image | varchar(255) | NULL | NULL | - | - |
| taxe_id | int | NULL | 1 | - | - |
| product_type | varchar(20) | NULL | unite | MUL | - |
| prod_service | enum('BIE','SER','TAX') | NULL | NULL | - | - |
| remise_type | varchar(10) | NULL | percent | - | - |
| remise_value | decimal(10,2) | NULL | 0.00 | - | - |
| taxe_specifique_type | varchar(10) | NULL | % | - | - |
| taxe_specifique_value | decimal(10,2) | NULL | 0.00 | - | - |
| date_expiration | date | NULL | NULL | - | - |

## service_providers

- **Rôle** : Fournisseurs de services
- **Lignes estimées** : 2

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| code | varchar(20) | NOT NULL | NULL | UNI | - |
| nom | varchar(100) | NOT NULL | NULL | - | - |
| type_service | enum('electricity','water') | NOT NULL | NULL | - | - |
| api_endpoint | varchar(255) | NULL | NULL | - | - |
| api_key | varchar(255) | NULL | NULL | - | - |
| actif | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |

## service_types

- **Rôle** : Types de service / prestations
- **Lignes estimées** : 7

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| name | varchar(50) | NOT NULL | NULL | UNI | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## settings

- **Rôle** : Paramètres de l'application par boutique
- **Lignes estimées** : 31

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| setting_key | varchar(100) | NOT NULL | NULL | MUL | - |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| value | text | NULL | NULL | - | - |
| created_at | timestamp | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | timestamp | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## shops

- **Rôle** : Boutiques (multi-shop)
- **Lignes estimées** : 2

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| nom | varchar(100) | NOT NULL | NULL | - | - |
| code | varchar(20) | NOT NULL | NULL | UNI | - |
| adresse | varchar(255) | NULL | NULL | - | - |
| telephone | varchar(30) | NULL | NULL | - | - |
| email | varchar(100) | NULL | NULL | - | - |
| ice | varchar(50) | NULL | NULL | - | - |
| rccm | varchar(50) | NULL | NULL | - | - |
| isf | varchar(50) | NULL | NULL | - | - |
| pdv | varchar(100) | NULL | NULL | - | - |
| nid | varchar(100) | NULL | NULL | - | - |
| token | varchar(255) | NULL | NULL | - | - |
| port | varchar(255) | NULL | NULL | - | - |
| service_type_id | int | NULL | NULL | MUL | -> service_types.id |
| actif | tinyint(1) | NOT NULL | 1 | - | - |
| created_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| updated_at | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP | - |

## taxes

- **Rôle** : Taux de taxe
- **Lignes estimées** : 18

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| groupe_taxe | varchar(50) | NOT NULL | NULL | - | - |
| etiquette | varchar(100) | NOT NULL | NULL | - | - |
| description | text | NULL | NULL | - | - |
| taux | decimal(5,2) | NOT NULL | 0.00 | - | - |
| couleur | varchar(7) | NULL | #64748B | - | - |

## type_client

- **Rôle** : Catégories de clients
- **Lignes estimées** : 5

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| code | varchar(50) | NOT NULL | NULL | - | - |
| description | text | NULL | NULL | - | - |

## utilisateurs

- **Rôle** : Comptes utilisateurs et rôles
- **Lignes estimées** : 10

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| nom_utilisateur | varchar(50) | NOT NULL | NULL | UNI | - |
| mot_de_passe | varchar(255) | NOT NULL | NULL | - | - |
| nom_complet | varchar(100) | NOT NULL | NULL | - | - |
| role | enum('super_admin','admin','vendeur') | NOT NULL | vendeur | - | - |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| actif | tinyint(1) | NOT NULL | 1 | - | - |
| agent_code | varchar(50) | NULL | NULL | UNI | - |
| email | varchar(100) | NULL | NULL | - | - |
| telephone | varchar(30) | NULL | NULL | - | - |
| two_factor_enabled | tinyint(1) | NOT NULL | 1 | - | - |
| profile_image | varchar(255) | NULL | NULL | - | - |

## ventes

- **Rôle** : En-têtes de ventes
- **Lignes estimées** : 484

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI auto_increment | - |
| numero_facture | varchar(50) | NOT NULL | NULL | UNI | - |
| sous_total_ht | decimal(10,2) | NOT NULL | NULL | - | - |
| tva | decimal(10,2) | NOT NULL | NULL | - | - |
| total | decimal(10,2) | NOT NULL | NULL | - | - |
| payments | json | NULL | NULL | - | - |
| vendeur_id | int | NOT NULL | NULL | MUL | -> utilisateurs.id |
| shop_id | int | NULL | NULL | MUL | -> shops.id |
| date | datetime | NULL | CURRENT_TIMESTAMP | DEFAULT_GENERATED | - |
| dateDGI | varchar(100) | NULL | NULL | - | - |
| qrCode | text | NULL | NULL | - | - |
| codeDEFDGI | varchar(100) | NULL | NULL | - | - |
| counters | varchar(100) | NULL | NULL | - | - |
| nim | varchar(100) | NULL | NULL | - | - |
| comment | text | NULL | NULL | - | - |
| client_id | int | NULL | NULL | MUL | -> clients.id |
| type_vente | enum('product','bill_payment') | NULL | product | MUL | - |
| provider_id | int | NULL | NULL | MUL | - |
| numero_compteur | varchar(50) | NULL | NULL | MUL | - |
| client_reference | varchar(100) | NULL | NULL | - | - |
| api_response | text | NULL | NULL | - | - |
| service | varchar(50) | NULL | NULL | MUL | - |

## ventes_archive

- **Rôle** : Ventes anciennes archivées
- **Lignes estimées** : 0

| Colonne | Type | Null | Défaut | Clé / Extra | FK |
|---|---|---|---|---|---|
| id | int | NOT NULL | NULL | PRI | - |
| numero_facture | varchar(50) | NOT NULL | NULL | MUL | - |
| sous_total_ht | decimal(10,2) | NOT NULL | NULL | - | - |
| tva | decimal(10,2) | NOT NULL | NULL | - | - |
| total | decimal(10,2) | NOT NULL | NULL | - | - |
| payments | json | NULL | NULL | - | - |
| vendeur_id | int | NOT NULL | NULL | MUL | - |
| shop_id | int | NULL | NULL | MUL | - |
| date | datetime | NULL | NULL | MUL | - |
| dateDGI | varchar(100) | NULL | NULL | - | - |
| qrCode | text | NULL | NULL | - | - |
| codeDEFDGI | varchar(100) | NULL | NULL | - | - |
| counters | varchar(100) | NULL | NULL | - | - |
| nim | varchar(100) | NULL | NULL | - | - |
| comment | text | NULL | NULL | - | - |
| client_id | int | NULL | NULL | - | - |
| type_vente | enum('product','bill_payment') | NULL | product | - | - |
| provider_id | int | NULL | NULL | - | - |
| numero_compteur | varchar(50) | NULL | NULL | - | - |
| client_reference | varchar(100) | NULL | NULL | - | - |
| api_response | text | NULL | NULL | - | - |
| service | varchar(50) | NULL | NULL | - | - |

---

# Résumé des relations (FK)

| Table source | Colonne | Table cible | Colonne cible |
|---|---|---|---|
| categories | shop_id | shops | id |
| clients | shop_id | shops | id |
| clients | type_client_id | type_client | id |
| notifications | shop_id | shops | id |
| notifications | user_id | utilisateurs | id |
| otp_codes | user_id | utilisateurs | id |
| password_resets | user_id | utilisateurs | id |
| payroll_absences | employee_id | payroll_employees | id |
| payroll_absences | payroll_period_id | payroll_periods | id |
| payroll_attendance | employee_id | payroll_employees | id |
| payroll_attendance | payroll_period_id | payroll_periods | id |
| payroll_contracts | employee_id | payroll_employees | id |
| payroll_employees | department_id | payroll_departments | id |
| payroll_employees | job_category_id | payroll_job_categories | id |
| payroll_employees | shop_id | shops | id |
| payroll_employees | user_id | utilisateurs | id |
| payroll_overtimes | employee_id | payroll_employees | id |
| payroll_overtimes | payroll_period_id | payroll_periods | id |
| payroll_payments | payment_method_id | payroll_payment_methods | id |
| payroll_payments | payslip_id | payroll_payslips | id |
| payroll_payments | created_by | utilisateurs | id |
| payroll_payslip_lines | payslip_id | payroll_payslips | id |
| payroll_payslips | employee_id | payroll_employees | id |
| payroll_payslips | payroll_period_id | payroll_periods | id |
| payroll_time_clock_events | employee_id | payroll_employees | id |
| payroll_time_clock_events | import_batch_id | payroll_time_clock_imports | id |
| payroll_time_clock_imports | imported_by | utilisateurs | id |
| product_batches | product_id | produits | id |
| produits | shop_id | shops | id |
| produits | category_id | categories | id |
| settings | shop_id | shops | id |
| shops | service_type_id | service_types | id |
| utilisateurs | shop_id | shops | id |
| ventes | client_id | clients | id |
| ventes | shop_id | shops | id |
| ventes | vendeur_id | utilisateurs | id |
