# Analyse de charge — le POS peut-il gérer une boutique de 5 000+ produits ?

**Date :** 2026-11-24
**Périmètre :** `sys-pos/pos_systeme` (branche `main`)
**Mesures :** MySQL 9.1.0 local, jeu de données synthétique de 6 000 produits /
17 000 lots, base jetable `pos_perf_bench` (créée puis supprimée — `pos_system`
n'a pas été touchée : 57 produits / 78 lots avant et après).

---

## Verdict

**Non, pas en l'état.** L'application *fonctionne* avec 5 000 produits — rien ne casse,
aucune requête ne dépasse le timeout MySQL. Mais elle ne les gère pas *bien* :

- la page `/produits` renvoie **13,9 Mo de HTML** et construit un DOM de 6 000 lignes ;
- le catalogue est chargé **en entier** dans le navigateur à chaque ouverture de la caisse ;
- un import de 5 000 produits déclenche **25 000 requêtes SQL** (6,1 s).

Ce ne sont pas des blocages, ce sont des dettes qui se paient en temps de
chargement et en mémoire (caisse sur Sunmi/Tablette, réseau 3G/4G en boutique).

**Le bon Fond :** la base de données est saine. `produits` a ses index
(`code_barres` UNIQUE, `shop_id`, `category_id`), `product_batches` est indexé sur
`product_id`. La lecture unitaire est **rapide** : 0,2 ms par lecture par id,
0,2 ms par code-barres. Le problème n'est **pas** le schéma — c'est que
l'application ne pagine nulle part et répète le catalogue à chaque écran.

---

## 1. Les mesures

### 1.1 Requête catalogue (`Product::getAllFiltered`, `app/models/Product.php:78`)

C'est la requête qui alimente `/api/produits`, donc **la caisse, le scanner, le
tableau de bord et l'export** — tous en même temps.

| Scénario (6 000 produits) | Temps | Lignes |
|---|---|---|
| Requête actuelle, sans LIMIT | **232 ms** | 6 000 |
| + index `(shop_id, nom)` | 194 ms | 6 000 |
| Pré-agrégation des lots + index | 156 ms | 6 000 |
| Pré-agrégation + `LIMIT 50` | 67 ms | 50 |
| **Colonnes dénormalisées + `LIMIT 50`** | **31 ms** | 50 |

`EXPLAIN` de la requête actuelle :

```
c    type=ALL   key=-              rows=9     extra=Using temporary; Using filesort
p    type=ref   key=category_id    rows=675   extra=Using where
t    type=ALL   key=-              rows=3     extra=Using where; Using join buffer (hash join)
s    type=const key=PRIMARY        rows=1     extra=Using where
pb   type=ref   key=product_id     rows=2     extra=
```

`Using temporary; Using filesort` sur 6 000 lignes : MySQL matérialise un
réservoir temporaire puis trie. C'est le `GROUP BY p.id` + `ORDER BY p.nom`.

### 1.2 Poids de la réponse

| | Taille |
|---|---|
| JSON de `/api/produits` (6 000 produits) | **3,23 Mo** |
| le même en gzip | 0,14 Mo |
| HTML rendu de `/produits` | **13,90 Mo** (2 430 octets/ligne) |
| le même en gzip | 0,40 Mo |

**Aucun gzip n'est configuré** : ni `public/.htaccess`, ni `php.ini`, ni
compression au niveau applicatif. Le navigateur reçoit donc 3,23 Mo en clair
sur `/api/produits` et 13,9 Mo sur `/produits`.

### 1.3 Rendu de la page `/produits` (`app/views/produits.php:70`)

```
SELECT catalogue (PDO->fetchAll) :   363,8 ms  (6 000 lignes)
Rendu HTML des <tr>                 :   332,8 ms
HTML total (tbody)                  :   13,90 Mo
─────────────────────────────────────────────────
Page paginée (50 lignes)            :     2,1 ms  (118 Ko)
```

Le filtre de la page (`initProductsTabs`, `public/assets/js/app.js:4289`) fait
un `querySelector` sur **chaque `<tr>`** à chaque frappe :

```js
const rows = $$('#products-table tr[data-category]');
rows.forEach(row => {
    const nom = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
    ...
});
```

→ 6 000 × 3 `querySelector` par frappe = **18 000 parcours DOM** par caractère
tapé. Le navigateur gèle le fil principal.

### 1.4 La caisse (`public/assets/js/app.js:405`)

```js
const res = await fetch(APP_URL + '/api/produits');
this.allProducts = Array.isArray(data) ? data : (data.products || []);
```

Toute la pagination de la caisse est **côté client** (`itemsPerPage: 20`,
`app.js:344`) : les 6 000 objets sont en mémoire, et la recherche parcourt le
tableau entier à chaque frappe (`app.js:422`). Le serveur renvoie 3,23 Mo pour
afficher 20 cartes.

Le scanner a le même problème en plus grave : `app.js:377` fait

```js
const product = this.allProducts.find(p => p.code_barres === barcode);
```

→ recherche **linéaire** dans 6 000 objets. (Le scanner physique, lui, appelle
`/api/produit?code_barres=` — `scanner.js:448` — et reste rapide : 0,2 ms.)

### 1.5 Import de 5 000 produits

`ProductImportService::import` appelle `Product::findByBarcode()` **par ligne**
(`app/Services/ProductImportService.php:183`), et `findByBarcode` déclenche
`enrichWithBatchInfo` → 4 requêtes supplémentaires (`Product.php:129`).

```
ACTUEL : findByBarcode par ligne        6,1 s   (25 000 requêtes)
APRÈS  : 1 SELECT + lookup en mémoire   0,0 s   (1 requête)
→ gain : 1202×
```

C'est **le** point bloquant pour une boutique de 5 000 produits : l'import
Excel est le chemin par lequel le catalogue se garnit. 6,1 s pour 5 000
produits, c'est supportable mais ça empire linéairement, et à 20 000 produits
ça atteint 25 s — au-delà du timeout PHP de 30 s par défaut.

### 1.6 Tableau de bord (`PageController::dashboard`, `app/controllers/PageController.php:182`)

```php
$produits = $productModel->getAll($shopId);   // 6 000 lignes chargées…
'produits_compte' => count($produits),          // …pour en compter 6 000
```

```
getAll() + count($all)  :  403,8 ms  (charge 6 000 lignes pour en compter)
SELECT COUNT(*) direct  :    2,6 ms
→ écart                 :  401,2 ms
```

400 ms gaspillés sur le tableau de bord, pour un simple compteur.

### 1.7 Le reste des chemins — corrects

| Chemin | Mesure | Verdict |
|---|---|---|
| Lecture par id (PK) | 0,2 ms | OK |
| Code-barres exact (index UNIQUE) | 0,2 ms | OK |
| `LIKE '%terme%'` (scan 6 000 lignes) | 8,8 ms | Acceptable |
| Vente de 5 lignes (25 requêtes) | 7,7 ms | OK |
| `COUNT(*)` pour paginer | 2,0 ms | OK |
| Pagination profonde `OFFSET 4950` | 1,1 ms | OK (avec index) |

La vente n'est **pas** un problème : `SaleController::create` (`app/controllers/SaleController.php:335`)
charge le produit ligne par ligne, mais sur 5 lignes de vente ça reste à 7,7 ms.

---

## 2. Les causes racines

Il n'y a pas un bug, il y a **un pattern** répété : le catalogue est traité
comme un jeu de données petit, alors qu'il est un jeu de données relationnel.

| # | Cause | Où |
|---|---|---|
| **C1** | Aucune pagination : `getAllFiltered` n'a ni `LIMIT` ni `OFFSET` | `app/models/Product.php:31` |
| **C2** | La caisse charge tout le catalogue pour afficher 20 cartes | `public/assets/js/app.js:405` |
| **C3** | La page `/produits` rend toutes les lignes en HTML serveur | `app/views/produits.php:70` |
| **C4** | Le filtre `/produits` fait 3 `querySelector` par ligne et par frappe | `public/assets/js/app.js:4289` |
| **C5** | L'import fait 5 requêtes par produit (N+1) | `app/Services/ProductImportService.php:183` |
| **C6** | Le tableau de bord charge 6 000 lignes pour un `count()` | `app/controllers/PageController.php:182` |
| **C7** | `GROUP BY p.id` + `ORDER BY p.nom` → `Using temporary; Using filesort` | `app/models/Product.php:78` |
| **C8** | Aucun gzip ni compression | `public/.htaccess` |
| **C9** | La recherche produit est un `LIKE %…%` en scan complet | `app/models/Product.php:64` |
| **C10** | `code_barres` UNIQUE **global** : deux boutiques ne peuvent pas partager un code-barres | `produits` (dump ligne 1208) |

---

## 3. Recommandations

Priorisées par rapport gain / effort. Les timings « APRÈS » sont mesurés, pas
estimés.

### R1 — Paginer le catalogue (corrige C1, C2, C7) · **Priorité 1**

Ajouter `limit` / `offset` à `getAllFiltered()` et un `?page=&limit=` à
`/api/produits`.

**Décision de conception à trancher :** la caisse a besoin du catalogue
**complet** en mémoire (la recherche et le scanner y accèdent par tableau),
alors que la page `/produits` a besoin de **pages**. Ce sont deux besoins
différents.

- *Option A* — `GET /api/produits` reste sans limite (compatibilité mobile
  garantie), et une nouvelle route `GET /api/produits?page=&limit=` alimente
  la page `/produits`. La caisse ne change pas.
- *Option B* — `GET /api/produits` devient paginé par défaut, la caisse
  charge page par page en arrière-plan. Plus propre, mais casse le client
  mobile Android (`docs/API_MOBILE.md:41`) qui attend un tableau.

→ **Recommandation : Option A.** Elle respecte le contrat mobile existant et
supprime le problème le plus lourd (le HTML serveur) sans casser la caisse.
Le client mobile a besoin du catalogue complet pour vendre — c'est légitime.

### R2 — Dénormaliser le stock sur `produits` (corrige C7) · **Priorité 1**

Ajouter `available_stock` et `nearest_expiration_date` sur `produits`,
maintenus par les points d'écriture existants (`ProductBatch::recalculateProductStock`,
`Product::recalculateProductStock`).

**Résultat mesuré : 105,6 ms → 30,8 ms** pour une page de 50 (3,4×).

Le stock est déjà dénormalisé (`produits.stock` = somme des lots) — il ne
manque que les deux colonnes de lecture qui, elles, sont recalculées à chaque
*vente* via `SUM(CASE WHEN date_expiration…)`. C'est le seul point chaud.

⚠️ Contrainte : `ProductBatch::recalculateProductStock()` (`ProductBatch.php:234`)
devient le **point unique** de mise à jour. Toute écriture de lot doit y passer,
sinon la dénormalisation diverge. C'est un vrai couplage à documenter.

### R3 — Paginer le rendu de `/produits` (corrige C3, C4) · **Priorité 1**

Rendre 50 lignes par page côté serveur, avec une barre de pagination.
Le filtre passe au serveur (paramètre `search` déjà supporté par
`getAllFiltered`, ligne 63) : le `initProductsTabs` devient un
`fetch` avec debounce au lieu d'un parcours DOM.

**Résultat mesuré : 332,8 ms + 13,9 Mo → 2,1 ms + 118 Ko** de HTML.

Le filtre actuel a un bug caché : il filtre **après** le rendu, donc il
affiche « aucun produit » avant d'avoir les données. Le passer en serveur
supprime ce problème.

### R4 — Supprimer le N+1 de l'import (corrige C5) · **Priorité 1**

Dans `ProductImportService::import`, charger une seule fois les
code-barres existants en mémoire (un `SELECT code_barres FROM produits`)
au lieu d'appeler `findByBarcode()` par ligne.

**Résultat mesuré : 6,1 s → ~0,1 s.** C'est le correctif le plus rentable
de la liste : une seule requête au lieu de 25 000, et il conditionne
l'opération qui garnit le catalogue.

### R5 — Compter au lieu de charger (corrige C6) · **Priorité 2**

`PageController::dashboard` : remplacer `$productModel->getAll($shopId)` par
un `SELECT COUNT(*)`. Et `stock_faible` par une requête ciblée
(`WHERE stock <= stock_minimum`) au lieu d'un `array_filter` sur 6 000 lignes.

**Résultat mesuré : 403,8 ms → 2,6 ms.**

### R6 — Activer la compression (corrige C8) · **Priorité 2**

Une ligne dans `public/.htaccess` :

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json text/html text/css application/javascript
</IfModule>
```

**Gain mesuré : 3,23 Mo → 0,14 Mo** sur `/api/produits`,
**13,90 Mo → 0,40 Mo** sur `/produits`.

⚠️ Vérifier que `mod_deflate` est activé sur l'hébergeur (Hostinger le fait
en standard). À défaut, compresser au niveau applicatif dans `index.php`.

### R7 — Index `(shop_id, nom)` (complément de R2/R3) · **Priorité 2**

```sql
CREATE INDEX idx_prod_shop_nom ON produits (shop_id, nom);
```

**Gain mesuré : 232 ms → 194 ms** sur la requête complète ; c'est aussi ce qui
rend la pagination profonde Tenue (`OFFSET 4950` : 1,1 ms).

### R8 — Recherche plein-texte (corrige C9) · **Priorité 3**

À 6 000 produits, `LIKE '%terme%'` en scan complet coûte 8,8 ms : **ce n'est
pas un urgence**. Le FULLTEXT mesuré (22,5 ms) est même *plus lent* sur ce
volume — MySQL a un coût de preparation du dictionnaire qui ne se rentabilise
qu'au-delà de ~50 000 lignes.

→ **Recommandation : ne pas le faire maintenant.** Si le catalogue passe à
30 000+, ré-étudier avec un index `FULLTEXT(nom, code_barres)`. Note : le
`code_barres` est déjà indexé en UNIQUE — les deux chemins sont rapides.

### R9 — `code_barres` UNIQUE global (corrige C10) · **Décision à trancher · Priorité 3**

`produits.code_barres` a un index UNIQUE **global**, pas par boutique.
Deux boutiques ne peuvent donc pas avoir le même code-barres — ce qui est
impossible en pratique pour un EAN (un même produit vendu partout a le même
code-barres).

**Conséquence à 5 000+ produits :** soit la boutique renumérote ses articles
avec un préfixe interne, soit le premier import depuis un autre catalogue
échoue sur un `Duplicate entry`.

Le correctif est un index composite `(shop_id, code_barres)` — mais il change
le contrat du code-barres dans toute l'application (scanner, import, export,
réparation de codes-barres : `scripts/repair_barcodes_importes.php` existe
déjà, donc le problème a déjà mordu).

→ **Décision à prendre avant d'aller plus loin.** Je peux le chiffrer plus
finement si tu veux.

---

## 4. Plan de mise en œuvre proposé

| Étape | Contenu | Effort | Gain |
|---|---|---|---|
| **1** | **R4** — supprimer le N+1 de l'import | ~1 h | 6,1 s → 0,1 s à l'import |
| **2** | **R5** — compter au lieu de charger (dashboard) | ~30 min | 400 ms → 3 ms |
| **3** | **R6** — activer gzip | ~15 min | 3,2 Mo → 0,14 Mo |
| **4** | **R2** — dénormaliser le stock + **R7** index | ~2 h | 106 ms → 31 ms par page |
| **5** | **R3** — paginer le rendu de `/produits` | ~1 jour | 13,9 Mo → 118 Ko |
| **6** | **R1** — endpoint paginé `?page=&limit=` | ~1 h | socle de l'étape 5 |

Les étapes 1 à 4 sont indépendantes et à faible risque. L'étape 5 est la plus
grosse (elle touche une vue + un JS + le modèle) mais elle est celle qui
supprime l'essentiel du problème visible.

### Ce qu'il ne faut PAS faire

- **Ne pas** mettre un `LIMIT` arbitraire dans `getAllFiltered` sans le
  propager à tous les appelants : `ProductExportService` (`app/Services/ProductExportService.php:72`)
  et `PageController::dashboard` ont besoin de l'exhaustivité. Un `LIMIT` silencieux
  produirait un export **tronqué** — un bug fiscal, pas un bug d'affichage.
- **Ne pas** remplacer `findById` par un cache en mémoire : le stock change à
  chaque vente et le prix peut changer à tout moment. Un cache produits
  introduirait des ventes au mauvais prix.
- **Ne pas** se contenter d'ajouter un index et croire que c'est réglé : le gain
  d'index est de 232 → 194 ms. Le problème est l'absence de `LIMIT`, pas
  l'absence d'index.

---

## 5. Ce qui a été vérifié / non vérifié

**Vérifié (mesures réelles, MySQL 9.1.0) :**
- temps de la requête catalogue exacte de `Product::getAllFiltered` à 6 000 produits ;
- poids JSON et HTML réels, avec et sans gzip ;
- coût de rendu serveur des `<tr>` avec le gabarit réel de `app/views/produits.php` ;
- coût de l'import 5 000 produits (chemin `findByBarcode` réel) ;
- gain mesuré de chaque correctif proposé (index, pré-agrégation, dénormalisation, `LIMIT`) ;
- coût d'une vente de 5 lignes à 6 000 produits.

**Non vérifié :**
- comportement **réel** dans le navigateur (freeze de l'UI pendant le filtre,
  temps de rendu sur Sunmi/Tablette) — mesuré en PHP/PDO, pas en Chrome ;
- `mod_deflate` activé ou non sur l'hébergeur de production ;
- comportement du `LIMIT` sur `ProductExportService` (l'export de 5 000
  produits n'a pas été chronométré — la méthode est O(n) en PHP, pas en SQL) ;
- le correctif R2 (dénormalisation) n'a **pas** été implémenté : les 30,8 ms
  sont le résultat d'un `ALTER TABLE` + `UPDATE` sur la base jetable, pas d'un
  code en production.

**Aucun changement de code n'a été fait.** `pos_system` est intacte
(57 produits / 78 lots, vérifié avant et après). Les scripts de benchmark
étaient jetables et ont été supprimés.

---

## 6. Reproduction

Les benchmarks sont reproductibles avec ce script (base jetable, supprimée en
fin de run) :

```bash
php scripts/perf_bench_catalogue.php 6000
```

Il crée `pos_perf_bench`, rejoue le schéma réel extrait du dump, peuple
6 000 produits / 17 000 lots, exécute la requête catalogue exacte de
`Product::getAllFiltered`, mesure le JSON, le HTML et chaque correctif
candidate, puis supprime la base.
