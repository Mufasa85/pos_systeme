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
- [x] Enregistrer un **paiement** (`validated` → `paid`).
- [x] Vérifier la **clôture automatique** de la période quand tous les bulletins sont payés.

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
- [ ] **Import de pointage** CSV.
- [ ] Envoi des bulletins par e-mail.

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
