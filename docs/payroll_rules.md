# Règles métier du module Paie

Ce document formalise les règles de paie intégrées dans le POS. Il est destiné à être validé par le métier avant mise en production.

## 1. Contexte général

- **Pays / zone** : à compléter (ex. République Démocratique du Congo, CEMAC, UEMOA…)
- **Devise** : `Fc` (Franc congolais / devise du POS)
- **Périodicité** : mensuelle par défaut, avec possibilité de traitements journaliers ou horaires selon le contrat.
- **Langue** : français.

## 2. Modes de rémunération

| Type | Description | Calcul de base |
|------|-------------|----------------|
| `monthly` | Salaire mensuel fixe | `base / jours_ouvrables × jours_payés` |
| `daily`   | Salaire journalier | `base × jours_payés` |
| `hourly`  | Salaire horaire | `base × heures_travaillées` |

> Valeur par défaut : 22 jours ouvrables / mois et 8 heures / jour.

## 3. Formule de calcul d’un bulletin

```
Salaire de base   = base_salary proratisé selon le mode de rémunération
Sursalaire        = montant fixe du contrat
Heures supp.      = heures × taux_horaire × multiplicateur
Ancienneté        = base × taux_barème
Primes / avantages = somme des allocations actives
GROSS             = base + sursalaire + HS + ancienneté + avantages

Retenues salarié  = déductions fixes ou % du brut + cotisations salarié
IER employeur     = brut × ier_rate (%)
Cotis. employeur  = brut × taux employeur

NET               = GROSS - retenues salarié
COÛT EMPLOYEUR    = GROSS + cotis. employeur + IER
```

## 4. Avantages & primes

Les avantages sont stockés dans `payroll_allowances`.

- `fixed` : montant fixe ajouté au brut.
- `percent_base` : pourcentage du salaire de base.

Exemples types : prime de transport, logement, risque, repas, outillage.

## 5. Déductions & retenues

Les déductions sont stockées dans `payroll_deductions`.

- `fixed` : montant forfaitaire.
- `percent_gross` : pourcentage du brut.

Exemples : avance sur salaire, prêt entreprise, absences non payées.

## 6. Cotisations sociales

Les taux sont configurables par boutique / globalement dans `payroll_contribution_rates`.

| Code | Libellé | Taux salarié (%) | Taux employeur (%) |
|------|---------|------------------|--------------------|
| CNSS | Caisse nationale de sécurité sociale | à définir | à définir |
| INPP | Institut national de préparation professionnelle | à définir | à définir |
| ONEM | Office national de l’emploi | à définir | à défini |
| INSS | Institut national de sécurité sociale | à définir | à définir |

> Les taux exacts dépendent de la législation locale. Remplir les lignes dans **Paramètres > Cotisations** avant le premier calcul.

## 7. IER / charges patronales

Le taux **IER employeur** est fixé par employé (`payroll_employees.ier_rate`). Il s’applique sur le brut et représente une charge patronale supplémentaire (taxe sur l’emploi, contribution foncière, etc. selon la législation).

## 8. Barème d’ancienneté

Le barème est configurable dans `payroll_seniority_bands`.

| Min (années) | Max (années) | Taux (%) |
|--------------|--------------|----------|
| 0            | 2            | 0        |
| 2            | 5            | à définir |
| 5            | 10           | à définir |
| 10           | 15           | à définir |
| 15           | NULL         | à définir |

> Le montant d’ancienneté = `base × taux / 100`.

## 9. Périodes et statuts

| Statut | Signification | Actions possibles |
|--------|---------------|-------------------|
| `open` | Période ouverte, saisie en cours | Saisir présences, absences, heures supp. |
| `calculated` | Bulletins calculés | Valider, régénérer, imprimer PDF |
| `validated` | Bulletins validés | Paiements autorisés |
| `closed` | Période clôturée | Lecture seule |

## 10. Workflow type

1. Ouvrir une période (`/payroll/periods`).
2. Saisir les présences / absences / heures supp. (`/payroll/attendance`).
3. Vérifier les paramètres (avantages, déductions, cotisations, barème).
4. Lancer le calcul (`/payroll/payslips`).
5. Valider les bulletins.
6. Générer les PDF.
7. Saisir les paiements (`/payroll/payments`).
8. Exporter les rapports CSV (`/payroll/reports`).

## 11. Points d’attention

- Le taux horaire pour les heures supplémentaires est déduit automatiquement du salaire mensuel / journalier.
- Les absences non payées impactent le nombre de jours payés.
- Le bulletin est recalculé à chaque clic sur **Calculer**.
- Le PDF nécessite d’installer `dompdf/dompdf` (`composer require dompdf/dompdf`).
- Les imports de pointage attendent un CSV `device_user_id;date_heure;type` (séparateur `;`).

## 12. TODO / validation métier

- [ ] Valider le pays, la devise exacte et le SMIG local.
- [ ] Remplir les taux de cotisation dans la base de données.
- [ ] Remplir le barème d’ancienneté.
- [ ] Valider la formule de calcul avec un cas concret.
- [ ] Valider le modèle de fiche de paie imprimable.
