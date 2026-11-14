# Checklist — Éléments manquants / à valider du module Paie

Ce document liste ce qui reste à finaliser avant de déclarer le module Paie **opérationnel et en production**.  
Coche `[x]` au fur et à mesure que chaque point est terminé et vérifié.

---

## Phase 0 — Règles métier et paramétrage

- [x] Confirmer le **pays / zone** (ex. RDC, CEMAC, UEMOA…).
- [x] Confirmer la **devise exacte** et le SMIG / salaire minimum local.
- [x] Remplir les **taux de cotisation salarié / employeur** dans `payroll_contribution_rates`.
  - [x] CNSS
  - [x] INPP
  - [x] ONEM
  - [x] INSS
- [x] Remplir le **barème d’ancienneté** dans `payroll_seniority_bands`.
- [x] Définir les **avantages types** (transport, logement, risque, repas, outillage…).
- [x] Définir les **retenues types** (avance, prêt, absence non payée…).
- [x] Définir le **taux IER employeur** par défaut.

---

## Phase 1 — Données de test dans la base

- [x] Au moins **1 boutique** configurée avec `shop_id` valide.
- [x] Au moins **1 vendeur** transformé en employé (`payroll_employees`).
- [x] Contrat employé renseigné (`payroll_contracts`) : salaire, mode (mensuel/journalier/horaire).
      → **CRUD contrats créé le 2026-11-13** (`/payroll/contracts`) : avant, le
      salaire n'était saisissable **nulle part** et les employés sans contrat
      étaient ignorés silencieusement par le calcul.
      → ⚠️ **À COMPLÉTER** : seuls 2 agents sur 7 ont un contrat (salaire saisi).
- [x] Période paie ouverte (`payroll_periods`).
- [x] Présences / absences / heures sup saisies pour la période.

---

## Phase 2 — Calcul du bulletin

- [x] Calculer un bulletin pour un employé test.
- [x] Vérifier le **salaire de base proratisé** selon le mode de rémunération.
- [x] Vérifier les **heures supplémentaires**.
- [x] Vérifier l’**ancienneté**.
- [x] Vérifier les **avantages / primes**.
- [x] Vérifier les **retenues**.
- [x] Vérifier les **cotisations salarié**.
- [x] Vérifier le **NET**.
- [x] Vérifier le **coût employeur** (brut + cotis. employeur + IER).
- [x] Valider un **cas concret** avec un calcul manuel attendu.

---

## Phase 3 — Validations et statuts

- [x] Passer un bulletin de `draft` → `calculated`.
- [x] Valider un bulletin (`calculated` → `validated`).
- [x] Bloquer la modification après validation.
      → **Implémenté le 2026-11-13** (`app/Services/PayrollWorkflow.php`) :
      un bulletin `validated` ou `paid` ne peut plus être recalculé, modifié ni
      supprimé ; une période `closed` ou `validated` n'est plus recalculable.
- [x] Enregistrer un **paiement** (`validated` → `paid`).
      → **Garde ajoutée le 2026-11-13** : seul un bulletin `validated` peut
      être réglé (payer un bulletin `calculated` validait implicitement une paie).
- [x] Vérifier la **clôture automatique** de la période quand tous les bulletins sont payés.
      → **Implémenté le 2026-11-13** : `PayrollWorkflow::closePeriodIfAllPaid()`.

---

## Phase 4 — PDF et impressions

- [x] Générer un **PDF de bulletin**.
- [x] Vérifier le contenu du PDF (entête, lignes de paie, net, signature…).
- [x] Vérifier le **stockage multi-boutique** : `storage/payslips/{shop_id}/{year}/{month}/`.
- [x] Valider le modèle de fiche de paie imprimable avec le métier.

---

## Phase 5 — Paiements et rapports

- [x] Saisir un paiement pour un bulletin.
- [x] Consulter le rapport **Masse salariale**.
- [x] Consulter le rapport **Cotisations**.
- [x] Consulter le rapport **Paiements effectués**.
- [x] Consulter le rapport **Effectifs**.
- [x] Exporter un rapport au **format CSV**.

---

## Phase 6 — Sécurité et scoping multi-boutique

- [x] Vérifier qu’un admin ne voit que les employés de sa boutique.
- [x] Vérifier qu’un vendeur ne voit que ses propres bulletins.
- [x] Vérifier que le `super_admin` voit toutes les boutiques.
- [x] Vérifier que les requêtes SQL incluent `shop_id` (sauf super_admin).

---

## Phase 7 — Optionnel (hors v1)

- [ ] Calcul de **commission sur ventes**.
- [x] **Import de pointage** CSV. → `PayrollImportService::importCsv()` + `POST /api/payroll/timeclock/import`.
- [ ] Envoi des bulletins par e-mail.
      → `phpmailer/phpmailer` est déjà installé (non utilisé pour la paie).
- [x] **Import du récap Excel** (2026-11-13).
      → Bouton dans `/payroll/contracts` : analyse (simulation) puis import.
      `PayrollRecapImportService` lit le classeur `Recap paie.xlsx` (37 colonnes),
      mappe les en-têtes par LIBELLÉ (pas par index), ignore les colonnes
      calculées, et met à jour fiches Paie + contrats + primes + présences.

---

## Reste à faire (au 2026-11-13)

- [x] ~~Saisir le salaire des agents sans contrat~~ → **fait le 2026-11-13** :
      les 6 agents de la boutique 1 ont un contrat (import via
      `docs/modele_import_recap_paie.xlsx`). Salaires **provisoires à 300 000 Fc**
      pour David Nsimba, Sarah Mbala, Pauline Tshibanda et testeur → à ajuster.
- [x] **Trancher la devise et le taux de change** → **fait le 2026-11-13** :
      - la paie est en **Fc** (franc congolais) ;
      - le barème IRT est libellé en **USD** → conversion via
        `exchange_rate` ;
      - le taux par défaut est celui de l'**API OSAT** (`/dgi/currency/`),
        pas celui figé dans le classeur (2 658,7148 → 2 260,96 au 13/11/2026) ;
      - paramètre `exchange_rate_source` = `api` | `fixed`
        (modifiable dans /payroll/settings).
- [x] **Valider les taux patronaux** → **fait le 2026-11-13**
      (migration `2026_11_13_payroll_contribution_rates_align.sql`) :

      | Taux | Retenu | Source | Modifiable dans |
      |---|---|---|---|
      | CNSS salarié | 5 % | classeur | /payroll/settings |
      | CNSS patronale | 6,5 % **× 2 lignes** | classeur **ET** paie_db | /payroll/settings |
      | INPP | 2,2 % | paie_db | /payroll/settings |
      | ONEM | 0,15 % | paie_db | /payroll/settings |
      | IER | 25 % | classeur **ET** paie_db | fiche de l'agent |

      `INSS` a été **désactivée** : « Institut national de sécurité sociale » et
      « Caisse nationale de sécurité sociale » désignent la MÊME institution en
      RDC — la laisser active comptait deux fois la même cotisation.

- [ ] **Ajuster les salaires provisoires** (300 000 Fc) une fois les montants
      réels connus → réimport du récap Excel ou saisie sur /payroll/contracts.
- [ ] **Renseigner le taux d'IER agent par agent** : il est saisi dans la fiche
      de l'agent (et non global), comme dans le classeur et `paie_db`. Seuls
      César (25 %) en a un aujourd'hui.
- [ ] **Vérifier la date d'embauche** des 5 agents importés (2026-07-29 par
      défaut, sauf testeur 2026-09-08) : elle conditionne l'ancienneté.

---

## Livrables v1 acceptés

- [x] Schéma SQL intégré et exécuté.
- [x] CRUD employés / contrats.
- [x] Saisie des présences / absences / heures sup.
- [x] Calcul automatique des bulletins validé.
- [x] Génération des PDFs.
- [x] Enregistrement des paiements.
- [x] Tableaux de bord et exports CSV.
- [x] Séparation stricte des boutiques.
- [x] Portail vendeur : consultation de mon bulletin.
