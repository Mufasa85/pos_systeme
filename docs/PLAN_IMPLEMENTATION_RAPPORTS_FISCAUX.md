# Plan d'Implémentation : Rapports Statistiques Fiscaux (Z-rapport & X-rapport)

> **Date :** 09/09/2026  
> **Projet :** SYS-POS — Système de Point de Vente  
> **Contexte :** Intégration des rapports fiscaux conformément aux spécifications SFE

---

## Table des matières

1. [Rappel des spécifications](#1-rappel-des-spécifications)
2. [Architecture actuelle du projet](#2-architecture-actuelle-du-projet)
3. [Vue d'ensemble des fichiers](#3-vue-densemble-des-fichiers-à-créer--modifier)
4. [Phase 1 — Migration base de données](#4-phase-1--migration-base-de-données)
5. [Phase 2 — Modèle Report](#5-phase-2--modèle-report)
6. [Phase 3 — Service ReportService](#6-phase-3--service-reportservice)
7. [Phase 4 — ReportController (API)](#7-phase-4--reportcontroller-api)
8. [Phase 5 — Routes API](#8-phase-5--routes-api)
9. [Phase 6 — Page web de rapports](#9-phase-6--page-web-de-rapports)
10. [Phase 7 — Frontend JavaScript](#10-phase-7--frontend-javascript)
11. [Phase 8 — Template d'impression](#11-phase-8--template-dimpression)
12. [Phase 9 — Tests](#12-phase-9--tests)
13. [Annexes : Requêtes SQL détaillées](#13-annexes--requêtes-sql-détaillées)

---

## 1. Rappel des spécifications

### 1.1 Types de rapports

| Rapport | Définition | Réinitialise la période |
|---------|-----------|------------------------|
| **Z-rapport** | Résumé de toutes les transactions depuis le dernier Z-rapport | ✅ Oui (clôture la période) |
| **X-rapport quotidien** | Résumé des transactions du jour en cours (depuis le dernier Z-rapport) | ❌ Non |
| **X-rapport périodique** | Résumé pour une période personnalisée définie par l'utilisateur | ❌ Non |
| **A-rapport** | Détail complet par article : ventes, retours, stocks depuis le dernier A-rapport | ✅ Oui (clôture la période) |

### 1.2 Informations obligatoires dans chaque rapport

**Z et X rapports :**
- Dénomination commerciale
- NIF (Numéro d'Identification Fiscale)
- Date et heure de génération
- Type de rapport (Z / X quotidien / X périodique)
- Période sélectionnée
- ISF (Identifiant du Système de Facturation)
- Montant total, montant taxable et montant total de la taxe pour **chaque type de facture**
- Montant total, montant taxable et montant total de la taxe pour **chaque groupe de taxation** (par type de facture)
- **Nombre de factures** par type de facture
- **Montants totaux** par mode de paiement
- **Toutes les réductions commerciales** (remises)
- Autres enregistrements réduisant les ventes (avoirs FA, annulations EA)
- **Nombre de ventes incomplètes** (factures en cours / paniers abandonnés)

**A-rapport (Article Report) — supplémentaire :**
- Dénomination commerciale
- NIF
- Date et heure de génération
- Mention explicite "A-rapport"
- ISF
- Pour **chaque article** : code article, nom article, prix unitaire, taux d'impôt, quantité vendue, quantité retournée, quantité en stock
- Regroupement par catégorie d'articles

---

## 2. Architecture actuelle du projet

```
pos_systeme/
├── app/
│   ├── Controllers/       # Contrôleurs (Auth, Page, Sale, Product, etc.)
│   ├── Models/            # Modèles (Sale, SaleDetail, Tax, Client, Shop, etc.)
│   ├── Services/          # Services métier
│   ├── views/             # Vues PHP
│   │   ├── layout/        # Layouts (header, footer, sidebar)
│   │   ├── payroll/       # Vues paie
│   │   └── ...            # Pages (caisse, dashboard, analytics, etc.)
│   └── core/              # Core (Database, Router)
├── config/                # Configuration
├── public/
│   └── assets/
│       ├── css/           # Styles
│       └── js/            # Scripts (app.js, recharges.js, etc.)
├── routes/
│   ├── web.php            # Routes web (pages)
│   └── api.php            # Routes API (endpoints JSON)
├── migrations/            # Migrations SQL
└── vendor/                # Dépendances Composer
```

### 2.1 Tables existantes pertinentes

| Table | Utilité |
|-------|---------|
| `ventes` | Ventes réalisées (`sous_total_ht`, `tva`, `total`, `payments` JSON, `type_vente`, `shop_id`, `date`) |
| `details_vente` | Lignes de vente (`prix`, `quantite`, `remise_type`, `remise_value`, `taxe_specifique_*`) |
| `produits` | Produits (avec `taxe_id` lié à `taxes`) |
| `taxes` | Taxations (`groupe_taxe`, `taux`, `etiquette`) |
| `invoice_types` | Types de facture (FV, FA, EA, etc.) |
| `clients` | Clients (`nif`, `code_client`) |
| `shops` | Boutiques (`nom`, `isf`, `ice`, `rccm`, `homologation`, `adresse`) |
| `company_info` | Infos société super_admin (`name`, `isf`, `ice`, `rccm`, `nid`) |
| `utilisateurs` | Utilisateurs / vendeurs |
---

## 3. Vue d'ensemble des fichiers à créer / modifier

### 3.1 Fichiers à créer

| # | Fichier | Description |
|---|---------|-------------|
| 1 | `migrations/2026_09_09_create_fiscal_reports_tables.sql` | Tables `z_reports` + `a_reports` (suivi des Z et A rapports) |
| 2 | `app/Models/Report.php` | Modèle de données pour tous les rapports |
| 3 | `app/Services/ReportService.php` | Service métier d'agrégation des données |
| 4 | `app/Controllers/ReportController.php` | Contrôleur API des rapports |
| 5 | `app/views/rapports.php` | Page web des rapports |
| 6 | `app/views/rapport-ticket.php` | Template d'impression du rapport |
| 7 | `public/assets/js/rapports.js` | Script frontend des rapports |

### 3.2 Fichiers à modifier

| # | Fichier | Modification |
|---|---------|-------------|
| 1 | `routes/api.php` | Ajouter les 5 routes API des rapports |
| 2 | `routes/web.php` | Ajouter la route `/rapports` |
| 3 | `app/Controllers/PageController.php` | Ajouter la méthode `rapports()` |
| 4 | `public/assets/js/app.js` | Intégrer le menu de navigation vers `/rapports` |

---

## 4. Phase 1 — Migration base de données

**Fichier :** `migrations/2026_09_09_create_fiscal_reports_tables.sql`

### 4.1 Table `z_reports` (suivi des Z-rapports)

```sql
CREATE TABLE IF NOT EXISTS `z_reports` (
  `id`          INT           NOT NULL AUTO_INCREMENT,
  `shop_id`     INT           DEFAULT NULL,
  `issued_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start` DATETIME     NOT NULL COMMENT 'Début de période couverte',
  `period_end`  DATETIME      NOT NULL COMMENT 'Fin de période couverte',
  `counter`     INT           NOT NULL DEFAULT 0 COMMENT 'N° séquentiel Z-rapport',
  `total_sales_ht`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_tax`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_ttc`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `invoice_count`   INT           NOT NULL DEFAULT 0,
  `status`      ENUM('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_z_reports_shop` (`shop_id`),
  KEY `idx_z_reports_date` (`issued_at`),
  CONSTRAINT `fk_z_reports_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### 4.2 Table `a_reports` (suivi des A-rapports — par article)

```sql
CREATE TABLE IF NOT EXISTS `a_reports` (
  `id`           INT           NOT NULL AUTO_INCREMENT,
  `shop_id`      INT           DEFAULT NULL,
  `issued_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start` DATETIME      NOT NULL COMMENT 'Début de période couverte',
  `period_end`   DATETIME      NOT NULL COMMENT 'Fin de période couverte',
  `counter`      INT           NOT NULL DEFAULT 0 COMMENT 'N° séquentiel A-rapport',
  `articles_count` INT         NOT NULL DEFAULT 0 COMMENT 'Nombre d''articles dans le rapport',
  `total_quantity_sold` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_quantity_returned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount_collected`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_a_reports_shop` (`shop_id`),
  KEY `idx_a_reports_date` (`issued_at`),
  CONSTRAINT `fk_a_reports_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

> **Note :** la logique des A-rapports avec des tables séparées (`z_reports` / `a_reports`) permet à chaque rapport de clôturer **sa propre période indépendamment** — un A-rapport ne réinitialise pas le Z-rapport et vice-versa. Si vous préférez une table unifiée avec une colonne `type ENUM('Z','X_DAILY','X_PERIODIC','A')`, c'est possible ; les méthodes ci-après restent identiques.

---

## 5. Phase 2 — Modèle Report

**Fichier :** `app/Models/Report.php`

Méthodes à implémenter :

| Méthode | Description |
|---------|-------------|
| `getLastZReport(shopId)` | Dernier Z-rapport actif |
| `createZReport(shopId, start, end, data)` | Créer et archiver l'ancien Z |
| `getLastAReport(shopId)` | Dernier A-rapport actif |
| `createAReport(shopId, start, end, data)` | Créer et archiver l'ancien A |
| `getSalesByInvoiceType(shopId, start, end)` | Ventes groupées par type facture |
| `getSalesByTaxGroup(shopId, start, end)` | Ventes par groupe de taxation |
| `getPaymentsParsed(shopId, start, end)` | Paiements bruts (JSON parsé en PHP) |
| `getTotalDiscounts(shopId, start, end)` | Total des réductions |
| `getCreditNotes(shopId, start, end)` | Avoirs et annulations |
| `getIncompleteSalesCount(shopId, start, end)` | Ventes incomplètes |
| `getArticlesReport(shopId, start, end)` | Détail des ventes par article |
| `getShopInfo(shopId)` | Infos boutique / société |

### Requêtes SQL principales

**Ventes par type de facture :**
```sql
SELECT COALESCE(it.code, 'FV') as invoice_type,
       COUNT(v.id) as invoice_count,
       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
       COALESCE(SUM(v.tva), 0) as total_tax,
       COALESCE(SUM(v.total), 0) as total_ttc
FROM ventes v
LEFT JOIN invoice_types it ON v.type_vente = it.code
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
GROUP BY it.code ORDER BY it.code;
```

**Ventes par groupe de taxation :**
```sql
SELECT t.groupe_taxe, t.etiquette, t.taux,
       COUNT(DISTINCT v.id) as invoice_count,
       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
       COALESCE(SUM(dv.quantite * dv.prix * t.taux / 100), 0) as total_tax,
       COALESCE(SUM(dv.quantite * dv.prix * (1 + t.taux / 100)), 0) as total_ttc
FROM details_vente dv INNER JOIN ventes v ON dv.vente_id = v.id
LEFT JOIN produits p ON dv.produit_id = p.id
LEFT JOIN taxes t ON p.taxe_id = t.id
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
GROUP BY t.groupe_taxe, t.etiquette, t.taux ORDER BY t.groupe_taxe;
```

**Réductions commerciales :**
```sql
SELECT COUNT(DISTINCT v.id) as invoices_with_discount,
       COALESCE(SUM(
           CASE WHEN dv.remise_type IN ('%','percent')
               THEN (dv.prix * dv.quantite * dv.remise_value / 100)
               ELSE dv.remise_value END
       ), 0) as total_discount_amount
FROM details_vente dv INNER JOIN ventes v ON dv.vente_id = v.id
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?) AND dv.remise_value > 0;
```
**A-rapport — Détail des articles vendus :**
```sql
SELECT 
    p.id,
    p.code_barres as article_code,
    p.nom as article_name,
    p.prix as unit_price,
    p.stock as stock_quantity,
    COALESCE(t.taux, 0) as tax_rate,
    t.groupe_taxe,
    t.etiquette as tax_etiquette,
    c.id as category_id,
    c.category as category_name,
    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ? 
                        AND (? IS NULL OR v.shop_id = ?) 
                        AND dv.quantite > 0 
                  THEN dv.quantite ELSE 0 END), 0) as quantite_vendue,
    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ? 
                        AND (? IS NULL OR v.shop_id = ?) 
                        AND dv.quantite < 0 
                  THEN ABS(dv.quantite) ELSE 0 END), 0) as quantite_retournee,
    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ? 
                        AND (? IS NULL OR v.shop_id = ?) 
                        AND dv.quantite > 0 
                  THEN (dv.quantite * dv.prix) ELSE 0 END), 0) as montant_collecte
FROM produits p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN taxes t ON p.taxe_id = t.id
LEFT JOIN details_vente dv ON dv.produit_id = p.id
LEFT JOIN ventes v ON dv.vente_id = v.id
WHERE (? IS NULL OR p.shop_id = ?)
GROUP BY p.id, p.code_barres, p.nom, p.prix, p.stock, 
         t.taux, t.groupe_taxe, t.etiquette, c.id, c.category
ORDER BY c.category, p.nom;
```

---

## 6. Phase 3 — Service ReportService

**Fichier :** `app/Services/ReportService.php`

Le service orchestre la collecte des données via le modèle `Report` et les agrège dans une structure cohérente. Il expose maintenant 4 méthodes de génération : Z, X quotidien, X périodique, et **A**.

### Méthode principale `generate($shopId, $type, $startDate, $endDate)`

```php
public function generate($shopId, $type, $startDate, $endDate)
{
    $shopInfo = $this->reportModel->getShopInfo($shopId);

    $denomination = $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A';
    $isf = $shopInfo['isf'] ?? 'N/A';
    $adresse = $shopInfo['adresse'] ?? '';
    $telephone = $shopInfo['telephone'] ?? '';

    $salesByInvoiceType = $this->reportModel->getSalesByInvoiceType($shopId, $startDate, $endDate);
    $salesByTaxGroup = $this->reportModel->getSalesByTaxGroup($shopId, $startDate, $endDate);
    $discounts = $this->reportModel->getTotalDiscounts($shopId, $startDate, $endDate);
    $creditNotes = $this->reportModel->getCreditNotes($shopId, $startDate, $endDate);
    $incompleteSales = $this->reportModel->getIncompleteSalesCount($shopId, $startDate, $endDate);

    // Paiements : parsing JSON en PHP
    $rawPayments = $this->reportModel->getPaymentsParsed($shopId, $startDate, $endDate);
    $paymentsByMethod = [];
    $paymentLabels = [
        'ESPECES' => 'Espèces', 'MOBILEMONEY' => 'Mobile Money',
        'CARTEBANCAIRE' => 'Carte Bancaire', 'VIREMENT' => 'Virement',
        'CREDIT' => 'Crédit', 'CHEQUES' => 'Chèques', 'AUTRE' => 'Autres',
    ];
    foreach ($rawPayments as $row) {
        $payments = json_decode($row['payments'] ?? '[]', true);
        if (!is_array($payments)) continue;
        foreach ($payments as $p) {
            $typeP = $p['type'] ?? ($p['mode'] ?? 'ESPECES');
            $amount = floatval($p['amount'] ?? 0);
            $paymentsByMethod[$typeP] = ($paymentsByMethod[$typeP] ?? 0) + $amount;
        }
    }

    return [
        'header' => [
            'type' => $type,
            'denomination' => $denomination, 'nif' => $shopInfo['isf'] ?? 'N/A',
            'isf' => $isf, 'adresse' => $adresse, 'telephone' => $telephone,
            'generated_at' => date('Y-m-d H:i:s'),
            'period_start' => $startDate, 'period_end' => $endDate,
        ],
        'totals' => [
            'total_ht' => array_sum(array_column($salesByInvoiceType, 'total_ht')),
            'total_tax' => array_sum(array_column($salesByInvoiceType, 'total_tax')),
            'total_ttc' => array_sum(array_column($salesByInvoiceType, 'total_ttc')),
            'total_invoices' => array_sum(array_column($salesByInvoiceType, 'invoice_count')),
        ],
        'by_invoice_type' => $salesByInvoiceType,
        'by_tax_group' => $salesByTaxGroup,
        'by_payment_method' => array_map(fn($k, $v) => [
            'method' => $k, 'label' => $paymentLabels[$k] ?? $k, 'total' => $v
        ], array_keys($paymentsByMethod), $paymentsByMethod),
        'discounts' => $discounts, 'credit_notes' => $creditNotes,
        'incomplete_sales' => $incompleteSales,
    ];
}
```

### Méthodes spécialisées

```php
// Z-rapport : clôture la période et enregistre en base
public function generateZReport($shopId)
{
    $last = $this->reportModel->getLastZReport($shopId);
    $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00', strtotime('-1 year'));
    $periodEnd = date('Y-m-d H:i:s');
    $data = $this->generate($shopId, 'Z', $periodStart, $periodEnd);
    $this->reportModel->createZReport($shopId, $periodStart, $periodEnd, [
        'total_ht' => $data['totals']['total_ht'],
        'total_tax' => $data['totals']['total_tax'],
        'total_ttc' => $data['totals']['total_ttc'],
        'invoice_count' => $data['totals']['total_invoices'],
    ]);
    return $data;
}

// X-rapport quotidien
public function generateXReportDaily($shopId)
{
    $last = $this->reportModel->getLastZReport($shopId);
    $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00');
    return $this->generate($shopId, 'X_DAILY', $periodStart, date('Y-m-d H:i:s'));
}

// X-rapport périodique
public function generateXReportPeriodic($shopId, $startDate, $endDate)
{
    return $this->generate($shopId, 'X_PERIODIC', $startDate, $endDate);
}

// A-rapport : détail par article (clôture la période A)
public function generateAReport($shopId)
{
    $last = $this->reportModel->getLastAReport($shopId);
    $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00', strtotime('-1 year'));
    $periodEnd = date('Y-m-d H:i:s');

    $shopInfo = $this->reportModel->getShopInfo($shopId);
    $articles = $this->reportModel->getArticlesReport($shopId, $periodStart, $periodEnd);

    $totalQtySold = array_sum(array_column($articles, 'quantite_vendue'));
    $totalQtyReturned = array_sum(array_column($articles, 'quantite_retournee'));
    $totalCollected = array_sum(array_column($articles, 'montant_collecte'));

    $this->reportModel->createAReport($shopId, $periodStart, $periodEnd, [
        'articles_count' => count($articles),
        'total_quantity_sold' => $totalQtySold,
        'total_quantity_returned' => $totalQtyReturned,
        'total_amount_collected' => $totalCollected,
    ]);

    return [
        'header' => [
            'type' => 'A',
            'denomination' => $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A',
            'nif' => $shopInfo['isf'] ?? 'N/A',
            'isf' => $shopInfo['isf'] ?? 'N/A',
            'adresse' => $shopInfo['adresse'] ?? '',
            'telephone' => $shopInfo['telephone'] ?? '',
            'generated_at' => $periodEnd,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ],
        'articles' => $articles,
        'totals' => [
            'total_quantity_sold' => $totalQtySold,
            'total_quantity_returned' => $totalQtyReturned,
            'total_amount_collected' => $totalCollected,
            'articles_count' => count($articles),
        ],
    ];
}
```

## 7. Phase 4 — ReportController (API)

**Fichier :** `app/Controllers/ReportController.php`

Endpoints du contrôleur :

| Méthode | Route | Fonction |
|---------|-------|----------|
| GET | `/api/reports/z-report` | `generateZReport()` — Générer Z-rapport |
| GET | `/api/reports/x-report/daily` | `generateXReportDaily()` — X quotidien |
| GET | `/api/reports/x-report/periodic` | `generateXReportPeriodic()` — X périodique |
| GET | `/api/reports/a-report` | `generateAReport()` — Générer A-rapport |
| GET | `/api/reports/history` | `getReportHistory()` — Historique Z-rapports |
| GET | `/api/reports/a-history` | `getAReportHistory()` — Historique A-rapports |
| GET | `/api/reports/print/[i:id]` | `printReport()` — Re-générer pour impression |

```php
<?php
namespace App\Controllers;

use App\Services\ReportService;

class ReportController extends Controller
{
    private $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    // GET /api/reports/z-report
    public function generateZReport()
    {
        if (!$this->requireAdmin()) return;
        try {
            $report = $this->reportService->generateZReport($this->getShopId());
            $this->json(['success' => true, 'data' => $report,
                'message' => 'Z-rapport généré. Période clôturée.']);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/x-report/daily
    public function generateXReportDaily()
    {
        if (!$this->requireAuth()) return;
        try {
            $this->json(['success' => true,
                'data' => $this->reportService->generateXReportDaily($this->getShopId())]);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/x-report/periodic?from=YYYY-MM-DD&to=YYYY-MM-DD
    public function generateXReportPeriodic()
    {
        if (!$this->requireAuth()) return;
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = $_GET['to'] ?? date('Y-m-d');
        if (!strtotime($from) || !strtotime($to)) {
            $this->status(400)->json(['success' => false, 'message' => 'Dates invalides']);
            return;
        }
        try {
            $this->json(['success' => true,
                'data' => $this->reportService->generateXReportPeriodic(
                    $this->getShopId(), $from.' 00:00:00', $to.' 23:59:59')]);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/history
    public function getReportHistory()
    {
        if (!$this->requireAdmin()) return;
        $db = \App\Core\Database::getInstance();
        $history = $db->fetchAll(
            'SELECT zr.*, s.nom as shop_name FROM z_reports zr
             LEFT JOIN shops s ON zr.shop_id = s.id
             WHERE zr.shop_id = ? ORDER BY zr.issued_at DESC LIMIT 50',
            [$this->getShopId()]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/a-history
    public function getAReportHistory()
    {
        if (!$this->requireAdmin()) return;
        $db = \App\Core\Database::getInstance();
        $history = $db->fetchAll(
            'SELECT ar.*, s.nom as shop_name FROM a_reports ar
             LEFT JOIN shops s ON ar.shop_id = s.id
             WHERE ar.shop_id = ? ORDER BY ar.issued_at DESC LIMIT 50',
            [$this->getShopId()]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/print/:id
    public function printReport($params)
    {
        if (!$this->requireAuth()) return;
        $db = \App\Core\Database::getInstance();
        $zReport = $db->fetch('SELECT * FROM z_reports WHERE id = ? AND shop_id = ?',
            [$params['id'], $this->getShopId()]);
        if (!$zReport) {
            $this->status(404)->json(['success' => false, 'message' => 'Rapport introuvable']);
            return;
        }
        $report = $this->reportService->generate($this->getShopId(), 'Z',
            $zReport['period_start'], $zReport['period_end']);
        $this->json(['success' => true, 'data' => $report]);
    }

    // GET /api/reports/a-report
    public function generateAReport()
    {
        if (!$this->requireAdmin()) return;
        try {
            $report = $this->reportService->generateAReport($this->getShopId());
            $this->json(['success' => true, 'data' => $report,
                'message' => 'A-rapport généré. Période article clôturée.']);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

## 8. Phase 5 — Routes API

### Ajouts dans `routes/api.php`

```php
// ── Rapports Fiscaux (Z/X/A) ────────────────────────────
Router::get('/api/reports/z-report', [\App\Controllers\ReportController::class, 'generateZReport']);
Router::get('/api/reports/x-report/daily', [\App\Controllers\ReportController::class, 'generateXReportDaily']);
Router::get('/api/reports/x-report/periodic', [\App\Controllers\ReportController::class, 'generateXReportPeriodic']);
Router::get('/api/reports/a-report', [\App\Controllers\ReportController::class, 'generateAReport']);
Router::get('/api/reports/a-history', [\App\Controllers\ReportController::class, 'getAReportHistory']);
Router::get('/api/reports/history', [\App\Controllers\ReportController::class, 'getReportHistory']);
Router::get('/api/reports/print/[i:id]', [\App\Controllers\ReportController::class, 'printReport']);
```

### Ajouts dans `routes/web.php`

```php
Router::get('/rapports', [\App\Controllers\PageController::class, 'rapports']);
```

### Ajout méthode dans `app/Controllers/PageController.php`

```php
public function rapports()
{
    if (!isset($_SESSION['user_id'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        header('Location: ' . $protocol . '://' . $host . '/');
        exit;
    }
    $this->render('rapports');
}
```

---

## 9. Phase 6 — Page web de rapports

**Fichier :** `app/views/rapports.php`

Structure de la page :

```html
<h1>Rapports Fiscaux</h1>
<p>Boutique : <strong><?= htmlspecialchars($storeName) ?></strong></p>

<div class="report-actions">
    <button id="btn-z-report" class="btn btn-danger"
            onclick="return confirm('Clôturer la période en cours ?')">
        📄 Z-rapport (résumé ventes)
    </button>
    <button id="btn-a-report" class="btn btn-warning"
            onclick="return confirm('Générer un A-rapport clôture la période articles. Continuer ?')">
        📦 A-rapport (détail articles)
    </button>
    <button id="btn-x-daily" class="btn btn-primary">
        📊 X-rapport quotidien
    </button>
    <div class="period-picker">
        <label>Du : <input type="date" id="report-from"></label>
        <label>Au : <input type="date" id="report-to"></label>
        <button id="btn-x-periodic" class="btn btn-secondary">
            📅 X-rapport périodique
        </button>
    </div>
</div>

<div id="report-content" class="report-container">
    <p class="hint">Sélectionnez un type de rapport ci-dessus pour afficher les résultats.</p>
</div>

<div class="report-history">
    <h3>📋 Historique des Z-rapports</h3>
    <div id="report-history-list"></div>
</div>

<div class="report-history">
    <h3>📦 Historique des A-rapports</h3>
    <div id="a-report-history-list"></div>
</div>
---

## 10. Phase 7 — Frontend JavaScript

**Fichier :** `public/assets/js/rapports.js`

```javascript
const ReportsManager = {
    init() { this.bindEvents(); this.loadHistory(); },

    bindEvents() {
        document.getElementById('btn-z-report')?.addEventListener('click', () => this.generateZ());
        document.getElementById('btn-a-report')?.addEventListener('click', () => this.generateA());
        document.getElementById('btn-x-daily')?.addEventListener('click', () => this.generateXD());
        document.getElementById('btn-x-periodic')?.addEventListener('click', () => this.generateXP());
    },

    generateZ() {
        if (!confirm('Générer un Z-rapport va clôturer la période. Continuer ?')) return;
        this.fetch('/api/reports/z-report', 'Z-rapport');
    },

    generateA() {
        if (!confirm('Générer un A-rapport va clôturer la période articles. Continuer ?')) return;
        this.fetch('/api/reports/a-report', 'A-rapport (détail articles)');
    },

    generateXD() { this.fetch('/api/reports/x-report/daily', 'X-rapport quotidien'); },

    generateXP() {
        const from = document.getElementById('report-from')?.value;
        const to = document.getElementById('report-to')?.value;
        if (!from || !to) { alert('Sélectionnez une période.'); return; }
        this.fetch(`/api/reports/x-report/periodic?from=${from}&to=${to}`, 'X périodique');
    },

    fetch(url, label) {
        const c = document.getElementById('report-content');
        c.innerHTML = '<div class="loading">⏳ Génération...</div>';
        fetch(url).then(r => r.json()).then(resp => {
            if (!resp.success) { c.innerHTML = `<div class="error">${resp.message}</div>`; return; }
            this.display(resp.data, label);
            this.loadHistory();
        }).catch(e => { c.innerHTML = `<div class="error">Erreur: ${e.message}</div>`; });
    },

    display(data, label) {
        window._currentReportData = data;
        window._currentReportLabel = label;
        const c = document.getElementById('report-content');
        const h = data.header;
        const isAReport = h.type === 'A';

        let html = `
            <div class="report-header">
                <h2>${label}</h2>
                <p><strong>${h.denomination}</strong> | NIF: ${h.nif} | ISF: ${h.isf}</p>
                <p>Période: ${h.period_start} → ${h.period_end}</p>
                <p>Généré: ${h.generated_at}</p>
            </div>`;

        if (isAReport) {
            // ── A-rapport : tableau des articles ──
            html += `<p><strong>Total articles :</strong> ${data.totals.articles_count}
                     | Qté vendue : ${this.number(data.totals.total_quantity_sold)}
                     | Qté retournée : ${this.number(data.totals.total_quantity_returned)}
                     | Montant collecté : ${this.money(data.totals.total_amount_collected)}</p>`;
            if (data.articles?.length) {
                html += `<table class="report-table">
                    <thead><tr><th>Code</th><th>Article</th><th>Prix U.</th><th>Taxe</th>
                    <th>Qté vendue</th><th>Qté retour</th><th>Montant</th></tr></thead><tbody>`;
                data.articles.forEach(a => {
                    html += `<tr><td>${a.article_code}</td><td>${a.article_name}</td>
                        <td>${this.money(a.unit_price)}</td><td>${a.tax_rate}%</td>
                        <td>${this.number(a.quantite_vendue)}</td><td>${this.number(a.quantite_retournee)}</td>
                        <td>${this.money(a.montant_collecte)}</td></tr>`;
                });
                html += `</tbody></table>`;
            } else {
                html += `<p>Aucun article vendu sur cette période.</p>`;
            }
        } else {
            // ── Z / X rapports : totaux généraux ──
            html += `<table class="report-table">
                <tr><th>Total HT</th><td>${this.money(data.totals.total_ht)}</td></tr>
                <tr><th>Total Taxe</th><td>${this.money(data.totals.total_tax)}</td></tr>
                <tr class="totaux"><th>TOTAL TTC</th><td>${this.money(data.totals.total_ttc)}</td></tr>
                <tr><th>Factures</th><td>${data.totals.total_invoices}</td></tr>
            </table>`;
        }

        html += `<button onclick="ReportsManager.print()" class="btn">🖨️ Imprimer</button>`;
        c.innerHTML = html;
    },

    number(v) { return new Intl.NumberFormat('fr-FR').format(v||0); },

    money(v) { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(v||0); },

    print() {
        const w = window.open('/rapport-ticket', '_blank');
        if (w) { w.reportData = window._currentReportData; w.reportLabel = window._currentReportLabel; }
    },

    loadHistory() {
        // Historique Z-rapports
        fetch('/api/reports/history').then(r => r.json()).then(resp => {
            if (!resp.success) return;
            const list = document.getElementById('report-history-list');
            if (!resp.data.length) { list.innerHTML = '<p>Aucun Z-rapport généré.</p>'; return; }
            list.innerHTML = '<table class="report-table"><tr><th>#</th><th>Date</th><th>Période</th><th>Total TTC</th><th>Factures</th></tr>' +
                resp.data.map(z => `<tr><td>${z.counter}</td><td>${z.issued_at}</td>
                    <td>${z.period_start} → ${z.period_end}</td>
                    <td>${this.money(z.total_ttc)}</td><td>${z.invoice_count}</td></tr>`).join('') + '</table>';
        });

        // Historique A-rapports
        fetch('/api/reports/a-history').then(r => r.json()).then(resp => {
            if (!resp.success) return;
            const list = document.getElementById('a-report-history-list');
            if (!resp.data.length) { list.innerHTML = '<p>Aucun A-rapport généré.</p>'; return; }
            list.innerHTML = '<table class="report-table"><tr><th>#</th><th>Date</th><th>Période</th><th>Articles</th><th>Qté vendue</th><th>Montant</th></tr>' +
                resp.data.map(a => `<tr><td>${a.counter}</td><td>${a.issued_at}</td>
                    <td>${a.period_start} → ${a.period_end}</td>
                    <td>${a.articles_count}</td>
                    <td>${this.number(a.total_quantity_sold)}</td>
                    <td>${this.money(a.total_amount_collected)}</td></tr>`).join('') + '</table>';
        });
    },
};
document.addEventListener('DOMContentLoaded', () => ReportsManager.init());
```

---

## 11. Phase 8 — Template d'impression

**Fichier :** `app/views/rapport-ticket.php`

Template monospace format ticket (80mm) avec les données injectées depuis `window.opener._currentReportData`. Affiche en HTML puis appelle `window.print()`. Sections incluses :

- En-tête (dénomination, NIF, ISF, période, type de rapport)
- **Pour Z/X** : Totaux généraux (HT, taxe, TTC, factures), ventilation par type facture, groupe taxation, mode paiement, réductions, avoirs, ventes incomplètes
- **Pour A-rapport** : Tableau détaillé des articles (code, nom, prix unitaire, taux taxe, qté vendue, qté retournée, stock, montant collecté) + regroupement par catégorie

---

## 12. Phase 9 — Tests

### Scénarios de test

| # | Test | Résultat attendu |
|---|------|------------------|
| 1 | X-rapport quotidien sans vente | Rapport vide, totaux à 0 |
| 2 | Vente espèces → X-rapport | Vente apparaît, mode "Espèces" |
| 3 | Vente Mobile Money → X-rapport | Paiement classé "Mobile Money" |
| 4 | Vente avec remise → X-rapport | Remise dans section dédiée |
| 5 | Facture FA (avoir) → X-rapport | Apparaît dans "Avoirs/Annulations" |
| 6 | Z-rapport → clôture période | Nouvelle période démarre après Z |
| 7 | X-rapport après Z-rapport | Ne contient que les ventes post-Z |
| 8 | X-rapport périodique | Données correctes sur la plage |
| 9 | Historique Z-rapports | Liste complète et ordonnée |
| 10 | A-rapport après ventes multiples | Tableau articles correct, quantités cumulées |
| 11 | A-rapport : article avec retours (FA) | Quantité retournée > 0 dans la colonne dédiée |
| 12 | A-rapport : stock affiché | `stock_quantity` correspond au stock actuel du produit |
| 13 | Z-rapport puis A-rapport | Indépendants : Z clôture période fiscale, A clôture période articles |
| 14 | A-rapport sans vente | Tableau vide, totaux à zéro |
| 15 | Impression A-rapport | Tableau articles visible et bien formaté |
    ## 13. Annexes : Requêtes SQL détaillées

### Ventes par type de facture

```sql
SELECT COALESCE(it.code, 'FV') as invoice_type,
       COUNT(v.id) as invoice_count,
       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
       COALESCE(SUM(v.tva), 0) as total_tax,
       COALESCE(SUM(v.total), 0) as total_ttc
FROM ventes v
LEFT JOIN invoice_types it ON v.type_vente = it.code
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
GROUP BY it.code ORDER BY it.code;
```

### Ventes par groupe de taxation

```sql
SELECT t.groupe_taxe, t.etiquette, t.taux,
       COUNT(DISTINCT v.id) as invoice_count,
       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
       COALESCE(SUM(dv.quantite * dv.prix * t.taux / 100), 0) as total_tax,
       COALESCE(SUM(dv.quantite * dv.prix * (1 + t.taux / 100)), 0) as total_ttc
FROM details_vente dv INNER JOIN ventes v ON dv.vente_id = v.id
LEFT JOIN produits p ON dv.produit_id = p.id
LEFT JOIN taxes t ON p.taxe_id = t.id
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
GROUP BY t.groupe_taxe, t.etiquette, t.taux ORDER BY t.groupe_taxe;
```

### Réductions commerciales

```sql
SELECT COUNT(DISTINCT v.id) as invoices_with_discount,
       COALESCE(SUM(
           CASE WHEN dv.remise_type IN ('%','percent')
               THEN (dv.prix * dv.quantite * dv.remise_value / 100)
               ELSE dv.remise_value END
       ), 0) as total_discount_amount
FROM details_vente dv INNER JOIN ventes v ON dv.vente_id = v.id
WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?) AND dv.remise_value > 0;
```

### Avoirs et annulations

```sql
SELECT v.type_vente as invoice_type,
       COUNT(v.id) as invoice_count,
       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
       COALESCE(SUM(v.tva), 0) as total_tax,
       COALESCE(SUM(v.total), 0) as total_ttc
FROM ventes v
WHERE v.date BETWEEN ? AND ? AND v.type_vente IN ('FA', 'EA')
  AND (? IS NULL OR v.shop_id = ?)
GROUP BY v.type_vente;
```

### Infos boutique / société

```sql
SELECT s.nom, s.isf, s.adresse, s.telephone, s.email, s.ice, s.rccm
FROM shops s WHERE s.id = ?;

SELECT c.name as nom, c.isf, c.adresse, c.email, c.telephone, c.ice, c.rccm
FROM company_info c WHERE c.id = 1;
```

---

## Récapitulatif des fichiers

```
FICHIERS À CRÉER (7) :
├── migrations/2026_09_09_create_fiscal_reports_tables.sql   (tables z_reports + a_reports)
├── app/Models/Report.php
├── app/Services/ReportService.php
├── app/Controllers/ReportController.php
├── app/views/rapports.php
├── app/views/rapport-ticket.php
└── public/assets/js/rapports.js

FICHIERS À MODIFIER (4) :
├── routes/api.php          → +7 lignes (routes Z, X quotidien, X périodique, A, historique Z, historique A, print)
├── routes/web.php          → +1 ligne  (route page /rapports)
├── app/Controllers/PageController.php → méthode rapports()
└── public/assets/js/app.js → intégration menu navigation vers /rapports

NOUVELLES TABLES SQL (2) :
├── z_reports   → suivi des Z-rapports (périodes fiscales)
└── a_reports   → suivi des A-rapports (périodes articles)

NOUVEAUX ENDPOINTS API (7) :
├── GET /api/reports/z-report       → Générer Z-rapport (admin)
├── GET /api/reports/a-report       → Générer A-rapport (admin)
├── GET /api/reports/x-report/daily → X-rapport quotidien (auth)
├── GET /api/reports/x-report/periodic → X-rapport périodique (auth)
├── GET /api/reports/history        → Historique Z-rapports (admin)
├── GET /api/reports/a-history      → Historique A-rapports (admin)
└── GET /api/reports/print/:id      → Re-générer pour impression (auth)
```

---

*Document généré le 09/09/2026 — Plan d'implémentation pour l'intégration des rapports Z et X dans le système SYS-POS.*
