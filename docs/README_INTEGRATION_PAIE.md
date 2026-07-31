# Intégration d’un module de paie dans `pos_systeme`

## Objectif

Intégrer les fonctionnalités du projet `systeme-de-gestion-de-paie` au sein du POS existant (`pos_systeme`) en mode **multi-boutique**, de sorte que :

- **Chaque admin** ne gère la paie que des **vendeurs de sa boutique** (`shop_id`).
- Le **super_admin** puisse consulter / gérer toutes les boutiques.
- Le **vendeur** puisse consulter ses propres bulletins (lecture seule).
- On réutilise au maximum l’authentification, la base de données et l’architecture MVC du POS.

---

## Différences clés par rapport au projet paie autonome

| Aspect | Projet paie autonome | Intégration POS |
|--------|----------------------|-----------------|
| Authentification | Table `users` spécifique | Réutilise `utilisateurs` du POS |
| Rôles | `admin`, `rh`, `finance` | `super_admin`, `admin`, `vendeur` |
| Scoping | Pas de multi-boutique | Toutes les tables paie reçoivent un `shop_id` |
| Employés | Table `employees` indépendante | Table `payroll_employees` liée à `utilisateurs.id` |
| Modèles/Vues | Sous-dossier `systeme-de-gestion-de-paie` | Directement dans `app/Models`, `app/Controllers`, `app/views` du POS |

---

## Mapping des tables

Les tables du système de paie doivent être créées dans la base du POS avec un préfixe `payroll_` et une colonne `shop_id` (sauf tables globales).

| Table source paie | Nouvelle table POS | Notes |
|-------------------|--------------------|-------|
| `employees` | `payroll_employees` | `user_id` FK → `utilisateurs(id)`, `shop_id` |
| `contracts` | `payroll_contracts` | `employee_id` FK → `payroll_employees(id)` |
| `departments` | `payroll_departments` | `shop_id` nullable (générique si null) |
| `job_categories` | `payroll_job_categories` | `shop_id` nullable |
| `payroll_periods` | `payroll_periods` | `shop_id` obligatoire |
| `attendance_records` | `payroll_attendance` | `shop_id` obligatoire |
| `absences` | `payroll_absences` | `shop_id` obligatoire |
| `overtimes` | `payroll_overtimes` | `shop_id` obligatoire |
| `payslips` | `payroll_payslips` | `shop_id` obligatoire |
| `payslip_lines` | `payroll_payslip_lines` | hérite de `shop_id` via `payslip_id` |
| `payments` | `payroll_payments` | `shop_id` obligatoire |
| `payment_methods` | `payroll_payment_methods` | `shop_id` nullable |
| `allowances` | `payroll_allowances` | `shop_id` nullable |
| `deductions` | `payroll_deductions` | `shop_id` nullable |
| `contribution_rates` | `payroll_contribution_rates` | `shop_id` nullable |
| `seniority_bands` | `payroll_seniority_bands` | `shop_id` nullable |
| `time_clock_events` | `payroll_time_clock_events` | `shop_id` obligatoire |
| `time_clock_imports` | `payroll_time_clock_imports` | `shop_id` obligatoire |
| `audit_logs` | Réutilise `audit_logs` du POS | ajouter `entity_type = 'payroll_*'` |

### Lien employé ↔ vendeur

```sql
CREATE TABLE payroll_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shop_id INT NOT NULL,
    matricule VARCHAR(50) NOT NULL,
    device_user_id VARCHAR(50) DEFAULT NULL,
    hire_date DATE NOT NULL,
    iban VARCHAR(50) DEFAULT NULL,
    cnss_number VARCHAR(50) DEFAULT NULL,
    status ENUM('active','suspended','left') DEFAULT 'active',
    -- ... autres champs de la fiche employé
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
);
```

---

## Architecture proposée

### Fichiers à créer dans le POS

```
app/
  Models/
    PayrollEmployee.php
    PayrollContract.php
    PayrollPeriod.php
    PayrollAttendance.php
    PayrollAbsence.php
    PayrollOvertime.php
    PayrollPayslip.php
    PayrollPayslipLine.php
    PayrollPayment.php
    PayrollAllowance.php
    PayrollDeduction.php
    PayrollContributionRate.php
    PayrollSeniorityBand.php
    PayrollTimeClock.php
  Controllers/
    PayrollController.php        # calcul, validation, cloture
    PayrollEmployeeController.php
    PayrollAttendanceController.php
    PayrollPayslipController.php
    PayrollPaymentController.php
    PayrollReportController.php
    PayrollTimeClockController.php
  Services/
    PayrollCalculator.php
    PayrollPdfService.php
    PayrollImportService.php
  views/
    payroll/
      index.php
      employees.php
      employee_form.php
      periods.php
      attendance.php
      payslips.php
      payslip_detail.php
      payslip_pdf.php
      payments.php
      reports.php
      timeclock.php
migrations/
  add_payroll_module.sql
routes/
  (ajouter dans api.php et web.php les routes paie)
```

---

## Plan d’implémentation par phases

> **Légende des statuts :**
> - `[ ]` Non démarré
> - `[~]` En cours
> - `[x]` Terminé & testé
>
> Chaque phase se termine par une étape `**Validation : …**`. C’est le critère minimum à atteindre avant de passer à la phase suivante. Coche `[x]` quand la validation est OK.

### Phase 0 — Préparation [~]

1. [~] Valider le pays, la devise (XOF), le mode de rémunération (mensuel/journalier/horaire), les cotisations et le barème d’ancienneté.
2. [x] Rédiger `docs/payroll_rules.md` avec les règles métier (SMIG, taux CNSS, impôts, primes obligatoires, etc.).

**Validation :** `docs/payroll_rules.md` est rédigé, lu et validé par le métier.

### Phase 1 — Base de données [x]

1. [x] Créer `migrations/add_payroll_module.sql` avec toutes les tables paie (voir mapping ci-dessus).
2. [x] Vérifier l’encodage `utf8mb4`.
3. [x] Exécuter la migration sur la base du POS.

**Validation :** Toutes les tables existent, `DESCRIBE` fonctionne, pas d’erreur FK, jeu de test `phpMyAdmin` OK.

### Phase 2 — Liaison vendeurs / employés [x]

1. [x] Créer `PayrollEmployee` model.
2. [x] Permettre à l’admin de transformer un vendeur existant (`utilisateurs`) en fiche employé.
3. [x] Pré-remplir `nom_complet`, `email`, `telephone` depuis `utilisateurs`.
4. [x] Scoper par `shop_id` : l’admin ne voit que les vendeurs de sa boutique.

**Validation :** Un admin visualise/créé/modifie une fiche employé de sa boutique uniquement ; super_admin voit tout.

### Phase 3 — Paramétrage paie [~]

1. [x] CRUD pour `payroll_allowances`, `payroll_deductions`, `payroll_contribution_rates`, `payroll_seniority_bands`, `payroll_payment_methods`.
2. [x] Gérer les valeurs par défaut (`shop_id IS NULL`) et les surcharges par boutique.
3. [x] Créer l’écran "Paramètres de paie" accessible aux admins et super_admin.

**Validation :** L’admin peut enregistrer un barème par défaut et une surcharge pour sa boutique ; sauvegarde rechargée correctement.

### Phase 4 — Présences et pointage [~]

1. [x] Créer `PayrollPeriod` : ouverture d’une période (mois/année) par boutique.
2. [x] Créer la grille de saisie des présences (`payroll_attendance`) pour tous les vendeurs de la boutique.
3. [x] Saisie des absences (`payroll_absences`) et heures supplémentaires (`payroll_overtimes`).
4. [x] (Optionnel) Importer des pointages CSV.

**Validation :** Une période s’ouvre, les jours travaillés / absences / HS sont enregistrés et relus sans perte.

### Phase 5 — Moteur de calcul [~]

1. [x] Créer `PayrollCalculator` : prorata jours, ancienneté, heures supp, primes, cotisations, net.
2. [x] Générer `payroll_payslips` et `payroll_payslip_lines` pour toute la période.
3. [x] Statuts du bulletin : `draft` → `calculated` → `validated` → `paid`.
4. [x] Commande de recalcul possible tant que le bulletin n’est pas `validated`.

**Validation :** Le calcul produit un bulletin avec brut, retenues et net cohérents pour 2-3 vendeurs testés.

### Phase 6 — Paiements [~]

1. [x] Enregistrer les paiements des vendeurs (`payroll_payments`).
2. [x] Mettre à jour le statut `payslip` en `paid`.
3. [x] Clôturer la période quand tous les bulletins sont payés.

**Validation :** Un paiement enregistré met bien à jour le statut ; la période passe en `closed` quand tous sont payés.

### Phase 7 — Bulletins PDF [~]

1. [x] Installer/configurer Dompdf si ce n’est pas déjà fait (`composer require dompdf/dompdf`).
2. [x] Créer `PayrollPdfService`.
3. [x] Générer un PDF par bulletin et archiver dans `storage/payslips/{shop_id}/{year}/{month}/`.

**Validation :** Le PDF généré contient les bonnes informations et est accessible en téléchargement.

### Phase 8 — Rapports et exports [~]

1. [x] Rapports par période et par boutique :
   - [x] Masse salariale
   - [x] Cotisations
   - [x] Paiements effectués
   - [x] Effectifs
2. [x] Export CSV de chaque rapport.

**Validation :** Les totaux affichés correspondent aux bulletins et les exports CSV s’ouvrent correctement.

### Phase 9 — Sécurité et menus [~]

1. [x] Vérifier que chaque requête SQL inclut `shop_id = ?` (sauf super_admin).
2. [x] Ajouter les middlewares/routes dans le routeur du POS.
3. [x] Ajouter un menu "Paie" dans le layout du POS.
4. [x] Un vendeur connecté ne voit que **ses** bulletins (`WHERE user_id = ?`).

**Validation :** Un admin d’une autre boutique ne voit pas les employés/périodes d’une autre boutique ; un vendeur ne voit que ses bulletins.

---

## Gestion des droits

| Action | super_admin | admin | vendeur |
|--------|-------------|-------|---------|
| Ouvrir / gérer les périodes | ✅ toutes boutiques | ✅ sa boutique | ❌ |
| Saisir présences / absences / HS | ✅ toutes | ✅ sa boutique | ❌ |
| Calculer / valider paie | ✅ toutes | ✅ sa boutique | ❌ |
| Générer PDF bulletin | ✅ toutes | ✅ sa boutique | ❌ |
| Enregistrer paiement | ✅ toutes | ✅ sa boutique | ❌ |
| Consulter son bulletin | ✅ toutes | ✅ sa boutique | ✅ |
| Paramétrer primes / cotisations | ✅ global + surcharges | ✅ sa boutique | ❌ |

### Contrôles d’accès à mettre en place

- `PayrollEmployeeController` : `WHERE shop_id = :shop_id` ou `super_admin`.
- `PayrollController` : calculer uniquement la période demandée pour la boutique autorisée.
- `PayrollPayslipController` : si rôle `vendeur`, vérifier `payslip.user_id === $_SESSION['user_id']`.

---

## Commission sur ventes (optionnel)

Le POS possède déjà la table `ventes` avec `vendeur_id`. On peut ajouter à la calculatrice de paie une ligne de type `earning` : **commission**.

```sql
-- Exemple de commission calculée automatiquement sur le CA du vendeur sur la période
SELECT vendeur_id, SUM(total) AS ca FROM ventes
WHERE DATE(date) BETWEEN :start AND :end AND shop_id = :shop_id
GROUP BY vendeur_id;
```

L’admin pourrait configurer un taux de commission par vendeur ou par catégorie, qui serait ajouté au bulletin sous forme de `payslip_line`.

---

## Ressources à copier / adapter

Le projet `systeme-de-gestion-de-paie` contient déjà :
- `database/schema.sql` → adapter en `migrations/add_payroll_module.sql`
- `app/Services/PayrollCalculator.php` → copier / adapter (`App\Services\PayrollCalculator`)
- `app/Services/PdfService.php` → copier / adapter pour stockage multi-boutique
- `app/Models/Employee.php`, `Payslip.php`, etc. → renommer en `Payroll*`
- `views/payroll/` → adapter au layout du POS
- `docs/PLAN_ETAPES.md` → détailler s’il faut un plan de suivi

---

## Commandes utiles

```bash
# Exécuter la migration
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe -u root pos_db < migrations/add_payroll_module.sql

# Installer Dompdf (si absent)
composer require dompdf/dompdf

# Créer le dossier d’archivage des bulletins
mkdir -p storage/payslips
```

---

## Livrables de la v1

- [ ] Schéma SQL intégré au POS
- [ ] CRUD employés / contrats par boutique
- [ ] Saisie des présences / absences / heures sup
- [ ] Calcul automatique des bulletins
- [ ] PDF des bulletins
- [ ] Enregistrement des paiements
- [ ] Tableaux de bord et exports CSV
- [ ] Séparation stricte des boutiques
- [ ] Portail vendeur : consultation de mon bulletin
