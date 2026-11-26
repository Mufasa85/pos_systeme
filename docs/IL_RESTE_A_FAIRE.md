# Ce qu'il reste à faire

**État au 2026-11-25** — inventaire fait par **balayage du code**, pas par
lecture des plans (les plans datent : certains items sont déjà faits,
d'autres sont apparus depuis).

**Vérification à chaque ligne :** le constat est mesuré ou vérifié dans le
code. Un item marqué « ⚠️ non mesuré » est une hypothèse à confirmer.

---

## Vue d'ensemble

| # | Item | Impact | Risque | Effort | Statut |
|---|---|---|---|---|---|
| **A** | **R9 — `code_barres` composite (shop_id, code_barres)** | **Vente erronée inter-boutiques** | 🔴 **Élevé** | ~4 h | ⏸ **à faire — priorité 1** |
| **B** | **Migrations R7 + R2 + R3 à appliquer sur `pos_system`** | Prérequis de déploiement | 🔴 Bloquant | 5 min | ⏸ **à faire — priorité 0** |
| **C** | **R2 — Tri par colonne sur `/produits`** | 86 ms → 0,5 ms | 🟢 Faible | ~2 h | ⏸ à faire |
| **D** | **R6 — `getAllSales()` bornée (3 pages)** | Croît avec l'activité | 🟡 Moyen | ~3 h | ⏸ à faire |
| **E** | **D2 — Bouton « Modifier » : JSON inline → API** | Fragilité, HTML ×50 | 🟢 Faible | ~1 h | ⏸ à faire |
| **F** | **Migration R9 : les produits sans boutique** | Cas `shop_id IS NULL` | 🟡 Moyen | ~1 h | ⏸ à faire (lié à A) |

**Total estimé : ~11 h de développement + les validations.**

Deux items ne sont **pas** du travail de performance mais du **travail de
correction** (A et B) — ils sont classés plus haut parce que A peut vendre
le mauvais produit.

---

## B — Appliquer les 3 migrations sur `pos_system` (PRIORITÉ 0)

**Pourquoi c'est prioritaire sur tout le reste :** le code des phases
précédentes écrit déjà les colonnes dénormalisées. Sans les migrations, la
production tourne sur du code qui **échoue en dur**.

```bash
# 1) L'index (shop_id, nom) — utilisé par le tri et la recherche
mysql -u root -p pos_system < migrations/2026_11_24_index_produits_shop_nom.sql

# 2) La dé-normalisation du stock (available_stock, nearest_expiration_date)
mysql -u root -p pos_system < migrations/2026_11_24_produits_stock_denormalise.sql

# 3) La dé-normalisation de shop_id sur les lots
mysql -u root -p pos_system < migrations/2026_11_25_product_batches_shop_id_denormalise.sql
```

⚠️ **L'ordre compte** : la migration 3 **doit** précéder le déploiement du
code qui écrit `product_batches.shop_id` — sinon chaque création de lot
échoue sur `Unknown column 'shop_id'`.

⚠️ **La migration 2 doit être suivie du déploiement du code** : le point
d'écriture `ProductBatch::recalculateProductStock()` a changé. Entre les
deux, les colonnes sont correctes mais ne sont plus rafraîchies.

**Vérification après application :** chaque migration affiche un contrôle
final (`lots_shop_mismatch`, `available_stock`, etc.). Toutes les valeurs
doivent être à 0 ou cohérentes.

---

## A — R9 : `code_barres` composite (PRIORITÉ 1)

### Le problème, mesuré

`produits.code_barres` porte un index **UNIQUE global** :

```sql
UNIQUE KEY `code_barres` (`code_barres`)
```

**Conséquence 1 — le scanner vend le produit d'une autre boutique.**

`Product::findByBarcode()` **ne filtre pas sur la boutique** :

```php
WHERE p.code_barres = :code_barres
  AND (s.id IS NULL OR s.actif = 1)     ← « actif », pas « la mienne »
```

Et c'est **le chemin du scanner** : `GET /api/produit?code_barres=`
(`app.js:803`). Mesuré sur une base à 2 boutiques : un caissier de la
boutique A qui scanne le code-barres de B **obtient le produit de B** —
avec son prix et son stock. Une erreur de vente silencieuse.

**Conséquence 2 — deux boutiques ne peuvent pas partager un EAN.**

Mesuré :

```
INSERT INTO produits (code_barres, …) VALUES ('EAN-3017620422003', …)
→ SQLSTATE[23000]: Integrity constraint violation: 1062
  Duplicate entry 'EAN-3017620422003' for key 'produits.code_barres'
```

Un code-barres EAN est **global par nature** : le même produit vendu
partout porte le même code. Le premier import d'un catalogue existant
échoue. Le projet a déjà un script `scripts/repair_barcodes_importes.php` —
le problème a donc déjà mordu.

**Conséquence 3 — le cas `shop_id IS NULL`.**

`shop_id` est nullable (1 produit dans `pos_system`). Avec un UNIQUE
composite, **deux lignes NULL passent** (mesuré : `NULL != NULL` en MySQL).
La contrainte est contournable — il faut traiter le cas explicitement.

### Le correctif

```sql
ALTER TABLE produits DROP INDEX code_barres;
ALTER TABLE produits ADD UNIQUE KEY uk_produit_shop_code (shop_id, code_barres);
-- Un index NON unique sur code_barres reste nécessaire pour la
-- recherche par code-barres (le scanner, l'import) :
CREATE INDEX idx_produits_code_barres ON produits (code_barres);
```

**Coût mesuré :** la recherche scopée est même **plus rapide** que la
recherche globale (0,23 ms contre 0,71 ms) — parce qu'elle utilise le
composite.

### Les points d'écriture à corriger

| Fichier | Correction |
|---|---|
| `app/models/Product.php` `findByBarcode()` | **Ajouter le filtre boutique.** C'est LA correction de sécurité. |
| `app/models/Product.php` `findIdByBarcode()` | Idem |
| `app/models/Product.php` `findIdsByBarcodes()` | Idem (le batch d'import) |
| `app/controllers/ProductController.php` `create()` | Vérifier l'unicité **par boutique**, pas globalement |
| `app/controllers/ProductController.php` `find()` | Le scanner : scoper à la boutique de la session |
| `app/Services/ProductImportService.php` | Idem |
| `scripts/repair_barcodes_importes.php` | Devient obsolète (ou à adapter) |
| `app/models/Product.php` `create()` | Le `code_barres` fourni par l'appelant : valider le composite |

### Critères d'acceptation

- [ ] Deux boutiques peuvent créer un produit avec le **même** code-barres
- [ ] Une boutique ne peut pas créer **deux** produits avec le même code-barres
- [ ] `findByBarcode()` est scoped à la boutique de la session
- [ ] Le scanner renvoie 404 pour un code-barres d'une autre boutique
- [ ] Les produits sans boutique (`shop_id IS NULL`) sont traités explicitement
- [ ] `scripts/repair_barcodes_importes.php` est adapté ou supprimé

### ⚠️ Le point qui demande une décision

**Que doit-il se passer pour les produits `shop_id IS NULL` ?**

| Option | Effet | Conséquence |
|---|---|---|
| **(a)** Interdire toute création de produit sans boutique | Un `shop_id` non-null garanti | Casse le produit « orphelin » actuel (1 en base) et le super_admin sans boutique |
| **(b)** Garder NULL, et accepter que l'UNIQUE ne les protège pas | Le cas actuel est préservé | Deux produits sans boutique peuvent partager un code-barres → à mitiger côté application |
| **(c)** Rendre le code-barres NULL pour les produits sans boutique | Le NULL **échappe** à l'UNIQUE (comme `shop_id`) | Le produit perd son code-barres — le scanner ne peut plus le lire |

Ma recommandation : **(b) + garde applicative** — `findByBarcode()` avec
`shop_id IS NULL` ne renvoie le produit que si aucun autre ne correspond,
et l'unicité est vérifiée en PHP pour ce cas.

> ⚠️ Ce point **touche le chemin de vente** (scanner, caisse, client
> mobile). C'est le risque le plus élevé de la liste.

---

## C — R2 : Tri par colonne sur `/produits`

**État mesuré :** `/produits` n'a **aucune colonne triable** (vérifié :
pas de `sort`, `order_by` ou `data-sort` dans la vue).

**Le coût réel** (mesuré à 50 000 produits) :

| Tri | Sans index | Avec index `(shop_id, prix)` |
|---|---|---|
| `ORDER BY nom` | **0,6 ms** | (déjà indexé par R7) |
| `ORDER BY prix` | **85,5 ms** | **0,5 ms** (×170) |
| `ORDER BY stock` | **82,8 ms** | — |

**Recommandation :**
- Ajouter un index `(shop_id, prix)` — **le seul** à ajouter.
- ❌ **Ne pas indexer `stock`** : il change à chaque vente, l'index serait
  réécrit en continu. Le tri par stock restera à ~80 ms.
- La page doit passer un paramètre `sort` (server-side, comme le filtre).

**Effet :** la vue `produits.php` currently trie par `p.nom ASC` en dur.

---

## D — R6 : `getAllSales()` bornée (3 pages)

**État mesuré :** `Sale::getAllSales()` n'a **aucune limite** et est
appelé par 3 pages :

| Page | Ligne | Usage |
|---|---|---|
| `dashboard()` | PageController:181 | Compteurs + graphique |
| `historique()` | PageController:1302 | **Affiche toutes les ventes** (`foreach ($ventes as $v)`) |
| `analytics()` | PageController:1446 | Statistiques |

**Aujourd'hui :** 26 ventes → aucun impact mesurable.
**Demain :** les ventes croissent avec l'**activité**, pas avec le
catalogue. Une boutique qui fait 100 ventes/jour aura 3 650 ventes/mois,
43 800/an.

**Recommandation :**
1. `getAllSales()` prend un `$limit` (défaut 50) + `$offset`.
2. `/historique` devient paginé (comme `/produits`).
3. `dashboard()` et `analytics()` ne doivent plus charger les ventes
   pour les **compter** — un `COUNT(*)` agrégé par jour suffit
   (même argument que R5 pour les alertes).

⚠️ **Mesure à faire avant de dimensionner** : le nombre de ventes cibles
sur 12 mois, et le coût d'un `GROUP BY jour` sur cette base.

---

## E — D2 : Bouton « Modifier » — JSON inline → API

**État mesuré :** `app/views/produits.php:149` fait :

```php
onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"
```

Le JSON du produit est **dupliqué dans chaque ligne** du tableau. À 50
lignes par page, c'est 50 copies du même JSON dans le HTML.

**Le problème réel :** un produit avec un nom contenant une apostrophe ou
un guillemet casse l'attribut `onclick` (le `htmlspecialchars` échappe le
`"` mais pas la logique JavaScript).

**Recommandation :** l'option (b) — `editProductById(id)` + la route
`GET /api/produit/[i:id]` (**qui existe déjà**, j'ai ajouté la route
`show()` pour P5).

**Effort :** ~1 h. Le HTML de la page `/produits` rétrécit.

---

## F — Cas des produits sans boutique

**À traiter avec A** (c'est la même migration).

État mesuré : **1 produit** dans `pos_system` a `shop_id IS NULL`.

Actions :
- [ ] Décider de la politique (voir la question ci-dessus)
- [ ] Migrer le produit existant, ou documenter son cas
- [ ] Vérifier `Product::create()` : `':shop_id' => $data['shop_id'] ?? null`
      accepte NULL — à restreindre si (a)

---

## Ce qui est DÉJÀ fait (pour ne pas le refaire)

| Item | Statut |
|---|---|
| P1 — Migrations R7 + R2 + dé-normalisation du stock | ✅ code écrit + vérifié (migrations à appliquer) |
| P2 — `GET /api/produits/page` + `GET /api/produit/[i:id]` | ✅ |
| P3 — Pagination serveur de `/produits`, filtre serveur | ✅ |
| P4 — Recherche : FULLTEXT écarté, recherche en 2 requêtes | ✅ 198 ms → 11 ms |
| P5 — Caisse par blocs + repli serveur | ✅ |
| R0/R5 — Alertes bornées + compteur + page `/alertes-lots` | ✅ 502 ms → 14 ms |
| R3 — `product_batches.shop_id` dé-normalisé | ✅ 76,7 ms → 2,0 ms |
| R4 — Pagination keyset de la page des lots | ✅ |
| D1 — Caisse par pages | ✅ (P5) |
| D3 — Filtre par URL | ✅ (P3) |
| D4 — Page de 50 | ✅ |
| D5 — OFFSET pour `/produits` | ✅ (keyset réservé à `/produits/lots`) |
| D6 — FULLTEXT | ✅ tranché et écarté par la mesure |

---

## Ordre d'exécution proposé

| Ordre | Item | Pourquoi |
|---|---|---|
| **1** | **B** (migrations) | Prérequis de tout déploiement |
| **2** | **A + F** (R9) | Seul item qui peut **vendre le mauvais produit** |
| **3** | **E** (D2) | Petit, indépendant, améliore la robustesse |
| **4** | **C** (R2) | Bon gain, effort faible |
| **5** | **D** (R6) | Le plus long ; à dimensionner avec le parc réel |

---

## Hors périmètre (signalés, non traités)

- **Module hôtel** : `HotelChambre::all()`, `HotelReservation::sejoursSurPlage()`,
  `HotelHousekeeping::tachesPourGouvernante()` — requêtes sans borne, mais
  l'échelle d'un hôtel (20–200 chambres) ne justifie pas le même
  traitement. À traiter si le module hôtel grandit.
- **Paie** : `PayrollContract::all()`, `PayrollAllowance::all()` — même
  raisonnement.
- **`AuditLog::getByEntity()`** — sans borne, à surveiller si la table
  d'audit grossit.
- **Module mobile** : le client Android consomme `GET /api/produits`
  (le catalogue entier) — il a besoin de l'exhaustif, donc l'endpoint
  paginé ne le remplace pas. À revoir si la synchronisation mobile
  devient un sujet.

---

## Scripts de mesure (conservés)

| Script | Ce qu'il mesure |
|---|---|
| `scripts/perf_bench_r9_barcode_scope.php` | Les 3 conséquences de R9 + le coût de la correction |
| `scripts/perf_bench_r3_migration.php` | La migration R3 et le choix de l'index |
| `scripts/perf_bench_decisions.php` | Recherche, tri, page lots |
| `scripts/perf_bench_hybrid_search.php` | Quelle clause casse l'index |
| `scripts/perf_bench_search_final.php` | Gain bout en bout de la recherche |
| `scripts/perf_bench_lots.php` | Les variantes d'index de la page lots |
| `scripts/perf_bench_keyset.php` | OFFSET vs KEYSET |
| `scripts/perf_bench_dashboard_alerts.php` | Impact du dashboard non borné |
| `scripts/audit_unbounded_queries.php` | Audit statique (périmètre POS) |
| `scripts/verify_migration_r3.php` | La migration R3, appliquée 2 fois |
| `scripts/verify_search_e2e.php` | Bout en bout par HTTP réel |
| `scripts/verify_alertes_lots.php` | Route, vue et bornes de `/alertes-lots` |
