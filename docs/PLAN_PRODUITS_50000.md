# Plan d'implémentation — `/produits` à l'échelle 50 000 produits

**Date :** 2026-11-24
**Branche :** `main`
**Contexte :** `docs/ANALYSE_CHARGE_PRODUITS_5000.md` (analyse à 6 000 produits)
et `docs/PLAN_PERF_CATALOGUE_5000.md` (plan R1→R9).

⚠️ **Ce plan part d'un cap différent : 50 000 produits** (et non 5 000).
Les mesures ci-dessous ont été **réellement exécutées** sur une base
jetable de 50 000 produits / 85 000 lots, schéma réel (`pos_perf_b`,
MySQL 9.1.0).

⚠️ **La croissance n'est PAS linéaire** : entre 6 000 et 50 000 produits,
le temps de la requête catalogue a été multiplié par **28** (232 ms →
6 556 ms), pas par 8,3. Toute extrapolation linéaire est donc **fausse** —
c'est ce que les premières mesures à 6 000 laissaient croire. **Il faut
toujours mesurer à l'échelle cible.**

---

## 0. État d'avancement (2026-11-24)

**P1, P2, P3 et P5 sont IMPLÉMENTÉS et vérifiés.**

| Étape | Contenu | Statut |
|---|---|---|
| **P1** | Migrations R7 + R2, dé-normalisation du stock, point d'écriture unique | ✅ fait |
| **P2** | `GET /api/produits/page` (pagination serveur) + `GET /api/produit/[i:id]` | ✅ fait |
| **P3** | Pagination serveur de la page `/produits`, filtre passé au serveur | ✅ fait |
| **P5** | Caisse : catalogue par blocs + repli serveur (recherche, scan, panier) | ✅ fait |
| **P4** | Recherche FULLTEXT | ✅ **tranché et fait** : FULLTEXT écarté (mesuré), recherche en **deux requêtes** → 198 ms → 11 ms |
| **P6** | Tri par colonne + page `/produits/lots` | ⏸ **partiel** : alertes bornées + page `/alertes-lots` faits ; tri par colonne et page `/produits/lots` à faire |

> 📄 Les décisions et les MESURES de la phase restante sont dans
> `docs/RECOMMANDATIONS_CAS_RESTANTS.md`.

### Gains mesurés à 50 000 produits

Base jetable de 50 000 produits / 85 000 lots, MySQL 9.1.0, schéma réel :

| Poste | Avant | Après | Gain |
|---|---|---|---|
| Temps d'affichage de la 1re page | 3 693 ms | **478 ms** | **8×** |
| Poids de la page HTML | 60,8 Mo | **0,06 Mo** | **1001×** |
| Parcours du DOM par frappe (filtre) | 150 000 | **150** | **1000×** |
| JSON du catalogue envoyé au navigateur | 24,5 Mo | 0,02 Mo (bloc) | **1200×** |
| Requête catalogue SQL | 2 229 ms | 477 ms | 4,7× |

### Tests

**399 tests au total** (376 existants + 9 dans
`tests/Integration/ProductPaginationTest.php` + 6 dans
`tests/Integration/ExpirationAlertsBoundsTest.php` + 8 dans
`tests/Integration/ProductSearchTest.php`), suite verte.

Vérification bout en bout par HTTP réel : `scripts/verify_search_e2e.php`
(serveur PHP intégré + requêtes réelles sur `/produits`,
`/api/produits/page`, `/alertes-lots` et `/dashboard`).

Le nouveau fichier de test verrouille :
- `getPage()` et `getAllFiltered()` rendent les mêmes valeurs ;
- les bornes sont appliquées (limit plafonné à 200, offset négatif → 0) ;
- les pages se suivent sans chevauchement ;
- les colonnes dénormalisées suivent les lots (y compris le cas d'un lot
  **périmé**, qui ne compte pas dans le stock disponible).

### ⚠️ Migrations à appliquer sur la base de PRODUCTION

La base de production (`pos_system`) **n'a pas été touchée**. Les deux
migrations sont écrites et vérifiées (idempotentes, testées 2×) :

```bash
mysql -u root -p pos_system < migrations/2026_11_24_index_produits_shop_nom.sql
mysql -u root -p pos_system < migrations/2026_11_24_produits_stock_denormalise.sql
```

⚠️ La migration R2 **doit** être suivie du déploiement du code : le point
d'écriture `ProductBatch::recalculateProductStock()` est modifié pour
rafraîchir les trois colonnes. Entre les deux, les colonnes dénormalisées
sont correctes mais **ne sont plus rafraîchies** — le stock affiché
deviendrait faux.

---

## 1. Le problème, à l'échelle 50 000

`/produits` est aujourd'hui une page **sans pagination** : la vue rend
**toutes** les lignes du catalogue dans le HTML.

```php
// app/views/produits.php:70
<?php foreach ($produits as $p): ?>
  <tr data-category="..." data-shop="...">
    ... 2 430 octets par ligne ...
  </tr>
<?php endforeach; ?>
```

Et le filtre parcourt le DOM **à chaque frappe** :

```js
// public/assets/js/app.js:4289
const rows = $$('#products-table tr[data-category]');
rows.forEach(row => {
    const nom  = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
    const code = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
    ...
});
```

### Les coûts réels, mesurés à 50 000 produits

**SQL** (base jetable, 50 000 produits / 85 000 lots, MySQL 9.1.0) :

| Scénario | Temps | Note |
|---|---|---|
| Requête catalogue actuelle (sans `LIMIT`) | **5 883 – 6 676 ms** | `GROUP BY` sur 85 000 lots + `Using temporary; Using filesort` |
| Actuelle + `LIMIT 50` | **1 239 – 1 384 ms** | le `GROUP BY` agrège **avant** de tronquer → insuffisant |
| Dé-normalisée, sans `LIMIT` | 5 716 – 5 883 ms | |
| **Dé-normalisée + `LIMIT 50`** | **586 – 675 ms** | ✅ la cible |
| Dé-normalisée + `LIMIT 50 OFFSET 49 950` | **801 ms** | dernière page (index `(shop_id, nom)`) |
| Backfill migration R2 (sous-requêtes corrélées) | 2 477 – 5 161 ms | correct, tel qu'écrit |

**Navigateur** (extrapolé du ratio mesuré à 6 000 — la structure DOM est
la même, seule l'échelle change) :

| Poste | 6 000 (mesuré) | 50 000 |
|---|---|---|
| Lignes dans le DOM | 6 000 | **50 000** |
| HTML rendu | 13,90 Mo | **≈ 116 Mo** |
| HTML gzip | 0,40 Mo | ≈ 3,3 Mo |
| `querySelector` par frappe | 18 000 | **150 000** |
| JSON `/api/produits` | 3,23 Mo | **≈ 27 Mo** |

⚠️ **À 50 000 produits, l'utilisateur attend 6,5 s avant de voir la
première ligne de `/produits`** — puis reçoit 116 Mo de HTML à parser.
La page est **inutilisable**, et cette fois ce n'est pas une
extrapolation : c'est mesuré.

**Conclusion : la pagination n'est pas une optimisation, c'est une
condition de fonctionnement.**

### ⚠️ Le piège de la pagination seule

À 6 000 produits, `LIMIT 50` suffisait (232 ms → 31 ms). À 50 000, `LIMIT 50`
ne suffit **pas** (6 556 ms → 1 239 ms) : le `GROUP BY` agrège les 85 000
lots **avant** que le `LIMIT` ne tronque le résultat.

**Il faut donc la dé-normalisation (P1) ET la pagination (P3) — l'une
sans l'autre ne suffit pas :**

| | Sans dé-normalisation | Avec dé-normalisation |
|---|---|---|
| Page de 50 | 1 239 ms | **586 ms** ✅ |
| Dernière page | ~2 000 ms | 801 ms |

C'est pourquoi **P1 (migrations) est le premier chantier**, et non une
simple optimisation : c'est ce qui rend la pagination possible.

---

## 2. Ce qui a déjà été fait (et ne doit pas être refait)

Ces correctifs sont **livrés et commités** (`3f736ca`) :

| # | Correctif | Gain mesuré | Où |
|---|---|---|---|
| R4 | Import : N+1 supprimé | 4,22 s → 0,05 s (80×) | `ProductImportService`, `Product::findIdsByBarcodes()` |
| R5 | Tableau de bord : `COUNT(*)` au lieu de charger | 400 ms → ~3 ms | `Product::countFiltered()`, `Product::getLowStock()` |
| R6 | Compression gzip | 44 272 → 3 494 octets | `public/index.php`, `public/.htaccess` |

Deux **migrations sont écrites et vérifiées** (idempotentes, testées 2×) :

| Fichier | Contenu |
|---|---|
| `migrations/2026_11_24_index_produits_shop_nom.sql` | Index `(shop_id, nom)` |
| `migrations/2026_11_24_produits_stock_denormalise.sql` | `available_stock` + `nearest_expiration_date` + backfill |

**Ces deux migrations sont le socle de la pagination** : sans l'index
`(shop_id, nom)`, le `ORDER BY p.nom` d'une page profonde est un scan ;
sans la dénormalisation, chaque page ré-agrège 85 000 lots.

---

## 3. Le plan

Six étapes. **P1 et P2 sont indépendantes** et peuvent être livrées
séparément. **P3 est le cœur.** P4–P6 Treatent les surfaces annexes.

```
P1  Migrations (index + dénormalisation)     ~30 min   ← déjà écrites
P2  Endpoint catalogue paginé (/api/produits/page)  ~1 h
P3  Pagination serveur de la page /produits  ~1 jour  ← LE CHANTIER
P4  Recherche instantanée (FULLTEXT)          ~3 h
P5  La caisse : ne plus charger 50 000 objets ~1 jour
P6  Tri par colonne + colonne « lot »         ~1 jour
```

---

### P1 — Appliquer les deux migrations (déjà écrites)

**Pourquoi d'abord :** c'est mesuré, ce n'est pas une intuition.

- Sans l'index `(shop_id, nom)`, une page 1000 (`OFFSET 49 950`) trie
  50 000 lignes à chaque fois.
- Sans la dé-normalisation, **chaque page agrège 85 000 lots** : mesuré à
  1 239 ms pour une page de 50, contre **586 ms** avec — soit un facteur 2,
  sur un écran où le reste du temps de rendu est déjà de l'ordre de la
  seconde.

Autrement dit : **la pagination seule ne suffit pas à 50 000 produits.**
C'est la dé-normalisation qui rend les pages utilisables.

⚠️ **Ordre obligatoire**, et la migration R2 doit être suivie de la
modification du modèle (`ProductBatch::recalculateProductStock()`), sinon
les colonnes dénormalisées ne sont plus rafraîchies.

**Étapes :**
1. Modifier `ProductBatch::recalculateProductStock()` pour maintenir
   `available_stock` et `nearest_expiration_date` (le point d'écriture
   unique est déjà identifié : `ProductBatch.php:234`).
2. Faire pareil dans `Product::recalculateProductStock()`
   (`app/models/Product.php` (méthode
`recalculateProductStock`)), qui écrase `produits.stock` par un
   chemin différent.
3. Modifier la requête catalogue (`Product::getAllFiltered()`) pour lire
   les colonnes dénormalisées au lieu d'agréger les lots.

**Critères d'acceptation :**
- [ ] `EXPLAIN` de la requête catalogue ne montre plus
      `Using temporary; Using filesort`.
- [ ] Test de non-régression du stock : après une vente de 3 unités,
      `produits.stock` ET `produits.available_stock` ont décrémenté ;
      après la péremption d'un lot, `available_stock` diffère de `stock`
      de la quantité périmée ; `nearest_expiration_date` pointe le lot
      **non périmé** le plus proche.
- [ ] Les 376 tests existants passent.

**⚠️ Risque principal :** divergence silencieuse. Toute écriture dans
`product_batches` en SQL direct laisse la dénormalisation fausse et le
stock affiché faux. La contrainte est documentée dans l'en-tête de la
migration ; il faut aussi l'ajouter aux invariants du projet.

---

### P2 — Endpoint catalogue paginé

**Pourquoi :** c'est le socle de P3. La page `/produits` a besoin de
**pages** ; la caisse et le client mobile ont besoin du catalogue
**complet** (ils le stockent en mémoire pour la recherche et le scanner).

**Décision :** `GET /api/produits` **reste inchangé** (contrat mobile
garanti, `docs/API_MOBILE.md:41`). Une **nouvelle** route alimente la
page `/produits`.

**Implémentation :**

1. `app/models/Product.php` — nouvelle méthode `getPage()` :

```php
/**
 * Page de produits avec filtres (pagination serveur).
 *
 * @param array    $filters search, category, shop_id
 * @param int      $limit
 * @param int      $offset
 * @param int|null $shopId
 * @param bool     $includeInactiveShops
 * @return array{rows: array, total: int}
 */
public function getPage(array $filters = [], int $limit = 50, int $offset = 0, $shopId = null, $includeInactiveShops = false): array
```

Elle réutilise la construction de `$where` de `getAllFiltered()` (ligne
122) au lieu de la dupliquer — sinon les deux filtres divergent, et c'est
exactement le bug que R5 a corrigé.

⚠️ **Piège `EMULATE_PREPARES = false`** : le `LIMIT`/`OFFSET` ne peut pas
être mélangé avec des placeholders nommés. Utiliser des littéraux castés
en `int`, comme le fait déjà `CaisseBrouillon.php:179` :

```php
$limit  = max(1, min(200, (int)$limit));
$offset = max(0, (int)$offset);
// ...
$sql .= " ORDER BY p.nom ASC LIMIT {$limit} OFFSET {$offset}";
```

2. `app/controllers/ProductController.php` — nouvelle méthode `page()` :

```php
public function page()
{
    $productModel = new Product();
    $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
    $includeInactive = $this->isSuperAdmin() && !empty($_GET['include_inactive']);

    $result = $productModel->getPage(
        [
            'search'   => $this->sanitaze($_GET['search'] ?? ''),
            'category' => $_GET['category'] ?? '',
            'shop_id'  => !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null,
        ],
        (int)($_GET['limit'] ?? 50),
        (int)($_GET['offset'] ?? 0),
        $shopId,
        $includeInactive
    );

    $this->json([
        'rows'   => $result['rows'],
        'total'  => $result['total'],
        'limit'  => (int)($_GET['limit'] ?? 50),
        'offset' => (int)($_GET['offset'] ?? 0),
    ]);
}
```

3. `routes/api.php` — **après la ligne 164** :

```php
Router::get('/api/produits/page', [ProductController::class, 'page'], 'caisse|produits', 'shared_read');
```

⚠️ `ProductController` est **déjà** importé dans `routes/api.php`
(ligne 27) — ne pas ajouter d'import.

**Critères d'acceptation :**
- [ ] `GET /api/produits/page?limit=50&offset=0` renvoie
      `{rows: [...50...], total: N}`.
- [ ] `?search=cafe` filtre **côté serveur** et `total` reflète le filtre.
- [ ] `?offset=44950` renvoie la dernière page sans planter.
- [ ] `limit=0` / `limit=99999` / `offset=-5` sont **bornés**.
- [ ] `GET /api/produits` (l'ancienne route) **n'a pas changé** : le client
      mobile Android fonctionne toujours.

---

### P3 — Pagination serveur de la page `/produits` · **LE CHANTIER**

C'est l'étape qui débloque tout. Elle touche 4 fichiers.

#### 3.1 Le contrôleur — `app/controllers/PageController.php:380`

```php
public function produits()
{
    $this->requireModulePageAccess('produits');

    $productModel = new Product();
    $categoryModel = new \App\Models\Category();
    $taxModel = new \App\Models\Tax();

    $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
    $includeInactive = $this->isSuperAdmin() && !empty($_GET['include_inactive']);

    // ── Pagination serveur ──────────────────────────────────────────
    $limit  = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $search = $this->sanitaze($_GET['search'] ?? '');
    $cat    = $_GET['category'] ?? '';

    $page = $productModel->getPage(
        ['search' => $search, 'category' => $cat],
        $limit,
        $offset,
        $shopId,
        $includeInactive
    );

    $this->render('produits', [
        'produits'  => $page['rows'],
        'total'     => $page['total'],
        'limit'     => $limit,
        'offset'    => $offset,
        'search'    => $search,
        'category'  => $cat,
        'categories'=> $categoryModel->all($shopId),
        'taxes'     => $taxModel->getAll(),
        'shops'     => $this->isSuperAdmin() ? (new Shop())->getAll() : [],
        'includeInactive' => $includeInactive,
    ]);
}
```

#### 3.2 La vue — `app/views/produits.php`

**a) La boucle `foreach` reste identique** (elle itère sur `$produits`,
désormais déjà paginés). Mais **deux corrections obligatoires** :

⚠️ **Correction 1 — le `json_encode` inline (ligne 111).** Aujourd'hui :

```php
onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"
```

L'objet produit **entier** est encodé en JSON et inliné dans le HTML,
**pour chaque ligne**. À 6 000 produits c'est 13,9 Mo ; à 50 000 (avec la
pagination) ce serait 50 fois plus gros que nécessaire. Le problème
disparaît avec la pagination (50 lignes), mais il faut quand même
corriger l'échappement : `json_encode` peut produire des `'` qui cassent
l'attribut HTML.

**Version propre :** passer l'identifiant et recharger le produit.

```php
onclick="editProductById(<?= (int)$p['id'] ?>)"
```

et dans `app.js` :

```js
async function editProductById(id) {
    const res = await fetch(`${APP_URL}/api/produit/${id}`);
    if (res.ok) {
        editProduct(await res.json());
    }
}
```

⚠️ Cela suppose une route `GET /api/produit/[i:id]`. **Sinon** (plus
simple, sans backend) : conserver le JSON inline mais l'échapper
correctement :

```php
onclick='editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'
```

→ **Décision à trancher (D2 ci-dessous).**

**b) La barre de pagination.** Ajouter sous le `<table>` :

```php
<div class="products-pagination">
  <span class="pagination-info">
    <?= number_format($offset + 1) ?>–<?= number_format(min($offset + count($produits), $total)) ?>
    sur <?= number_format($total) ?> produits
  </span>
  <div class="pagination-buttons">
    <?php if ($offset > 0): ?>
      <a class="btn btn-ghost btn-small" href="?offset=<?= max(0, $offset - $limit) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($cat) ?>">Précédent</a>
    <?php endif; ?>
    <span class="pagination-page">Page <?= intdiv($offset, $limit) + 1 ?> / <?= max(1, (int)ceil($total / $limit)) ?></span>
    <?php if ($offset + $limit < $total): ?>
      <a class="btn btn-ghost btn-small" href="?offset=<?= $offset + $limit ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($cat) ?>">Suivant</a>
    <?php endif; ?>
  </div>
</div>
```

⚠️ Le `<select id="category-filter">` doit marquer la catégorie active
(`selected` sur l'option correspondante), sinon le filtre repart à
« Toutes » au rechargement.

#### 3.3 Le filtre — `public/assets/js/app.js:4289`

**Remplacer le filtrage DOM par un filtrage serveur.**

```js
// AVANT : parcours des 50 000 <tr> à chaque frappe
// APRÈS : requête serveur, 50 lignes au retour
let searchTimer = null;
if (filterInput) {
    filterInput.addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const params = new URLSearchParams(window.location.search);
            params.set('search', e.target.value);
            params.set('offset', '0');
            window.location.search = params.toString();
        }, 400);
    });
}
```

Le rechargement de page est acceptable : le rendu de 50 lignes est de
~2 ms.

⚠️ **`initProductsTabs()` est aussi appelé depuis `app.js:400`**, quand
`#products-table` existe. La caisse a un `#products-grid`, pas un
`#products-table` → pas de collision. **Mais** il faut vérifier que le
nouveau code ne casse pas la caisse (elle ne charge pas cette vue).

⚠️ **Ne pas supprimer `initProductsTabs` entièrement** : la vue a encore
le filtre « Toutes les boutiques » (super_admin) et « Inclure boutiques
inactives », qui sont des `<select>` à re-soumettre.

#### 3.4 Le tri — **à traiter avec P6** (ne pas le faire dans P3)

#### 3.5 Critères d'acceptation

- [ ] `/produits` renvoie **< 200 Ko** de HTML à 50 000 produits.
- [ ] Le DOM contient **50 `<tr>`**, pas 50 000.
- [ ] Le filtre par nom / code-barres / catégorie fonctionne **côté
      serveur** et le total affiché est mis à jour.
- [ ] La pagination affiche « page 3 / 1000 » et les boutons
      précédent / suivant / premier / dernier fonctionnent.
- [ ] Le filtre « Toutes les boutiques » (super_admin) et « Inclure
      boutiques inactives » fonctionnent toujours.
- [ ] Le bouton **Modifier** remplit le formulaire avec les bonnes
      valeurs (c'est le `editProduct()` qui est touché).
- [ ] Le bouton **Supprimer** fonctionne toujours.
- [ ] **Mesure navigateur** (DevTools → Performance) : le filtre ne gèle
      plus l'interface ; le temps de rendu de `/produits` < 300 ms.
- [ ] Les 376 tests existants + les nouveaux passent.

#### 3.6 Risques

- **Le plus gros lot de travail du plan** : touche une vue, un JS
  (243 Ko, un seul IIFE), un modèle et un contrôleur.
- **Le filtre change de sémantique** : l'ancien filtre s'exécutait
  *après* le rendu (donc sur des produits déjà chargés) ; le nouveau
  s'exécute *avant*. Un cas à vérifier : filtrer par catégorie puis par
  nom — l'ordre des filtres change le résultat.
- Le filtre actuel affiche « aucun produit » avant d'avoir les données
  (il masque après rendu) : le nouveau ne peut plus avoir ce bug.

---

### P4 — Recherche instantanée (FULLTEXT)

**Pourquoi :** à 50 000 produits, `LIKE '%terme%'` est un **scan
complet** de 50 000 lignes. La mesure à 6 000 donnait 8,8 ms ; à 50 000
(×8,3) on extrapolerait à **~73 ms par frappe** — supportable mais
mauvais, et ça dégrade la base.

**⚠️ Le FULLTEXT n'était PAS rentable à 6 000 produits** (mesuré : 22,5 ms
contre 8,0 ms pour le `LIKE`). À 50 000, la balance change : le `LIKE`
dégrade linéairement, le FULLTEXT reste quasi-constant. **C'est le
seul correctif du plan R1→R9 qui change de verdict selon l'échelle** —
et c'est exactement pour ça qu'il était conditionné à « au-delà de
30 000 ».

**Implémentation :**

1. Migration `migrations/2026_11_24_produits_fulltext.sql` :

```sql
-- Index FULLTEXT sur le nom et le code-barres.
--
-- À 50 000 produits, le filtre `LIKE '%terme%'` est un scan complet
-- (~73 ms extrapolés). Le FULLTEXT rend la recherche quasi constante.
--
-- ⚠️ FULLTEXT utilise le naturel language mode : les mots de moins de
--    `ft_min_word_len` (4 par défaut) sont IGNORÉS. Un code-barres
--    court (« 123 ») ne sera donc pas trouvé par MATCH — il faut le
--    garder sur le chemin exact (index UNIQUE), déjà couvert.
SET @ft_exists = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'produits'
    AND index_name = 'ft_produits_nom'
);

SET @sql = IF(
  @ft_exists = 0,
  'CREATE FULLTEXT INDEX ft_produits_nom ON produits (nom, code_barres)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

2. Dans `getPage()`, le filtre `search` devient :

```php
if (!empty($filters['search'])) {
    $search = $filters['search'];
    // Le code-barres exact reste sur l'index UNIQUE (0,2 ms).
    // Le nom passe en FULLTEXT.
    $where[] = '(MATCH(p.nom) AGAINST (:search IN BOOLEAN MODE) OR p.code_barres = :search_exact)';
    $params[':search'] = '+' . $search . '*';
    $params[':search_exact'] = $search;
}
```

⚠️ **Deux placeholders distincts** pour la même valeur : avec
`EMULATE_PREPARES = false`, réutiliser `:search` deux fois lève
`SQLSTATE[HY093]`.

3. **Repli** : si le terme fait moins de 4 caractères, le FULLTEXT ne
   matche rien → la recherche renvoie 0 résultat alors qu'il y en a. Il
faut **combiner** avec un `LIKE` quand le terme est court :

```php
if (mb_strlen($search) < 4) {
    // Trop court pour FULLTEXT (ft_min_word_len = 4) : on scanne.
    $where[] = '(p.nom LIKE :search_like OR p.code_barres = :search_exact)';
    $params[':search_like'] = '%' . $search . '%';
} else {
    $where[] = '(MATCH(p.nom) AGAINST (:search_full IN BOOLEAN MODE) OR p.code_barres = :search_exact)';
    $params[':search_full'] = '+' . $search . '*';
}
```

**Critères d'acceptation :**
- [ ] Un terme de 5+ caractères renvoie les mêmes résultats que le `LIKE`
      actuel (comparer sur 100 produits réels).
- [ ] Un terme de 1–3 caractères renvoie **aussi** des résultats (le repli
      `LIKE` fonctionne).
- [ ] Une recherche par code-barres **exact** reste instantanée.
- [ ] La recherche ne renvoie **pas** de résultats hors du filtre
      boutique.

**⚠️ Risque :** FULLTEXT est une dépendance de configuration MySQL
(`ft_min_word_len`, `ft_max_word_len`, `stopwords`). Sur l'hébergeur, ces
valeurs peuvent différer → **le test doit être fait sur la base de
production**, pas seulement en local.

---

### P5 — La caisse : ne plus charger 50 000 objets

**Pourquoi :** la caisse charge le catalogue **entier** :

```js
// public/assets/js/app.js:405
const res = await fetch(APP_URL + '/api/produits');
this.allProducts = Array.isArray(data) ? data : (data.products || []);
```

À 50 000 produits : **≈ 27 Mo** de JSON (≈ 1 Mo gzip), et 50 000 objets JS
en mémoire (le V8 de Chrome occupe ~150–200 Mo).

Et elle en a besoin pour 3 choses (`app.js:377`, `422`, `631`) :

| Usage | Ligne | Fréquence |
|---|---|---|
| `allProducts.find(p => p.code_barres === barcode)` | 377 | à chaque scan |
| `allProducts.filter(...)` (recherche) | 422 | à chaque frappe |
| `allProducts.find(p => p.id == id)` | 631 | à chaque ajout au panier |

**Deux options :**

**Option A — la caisse garde tout en mémoire (status quo).**
- JSON ≈ 1 Mo gzip : le transfert est acceptable.
- Mais 50 000 objets JS en mémoire restent, et la recherche parcourt
  50 000 objets à chaque frappe (≈ 5–15 ms en JS pur, ×10 avec le DOM).
- **Verdict : suffisant, mais pas confortable.** Un Sunmi d'entrée de
  gamme (2 Go RAM) peut scléroser.

**Option B — la caisse charge par pages (recommandée).**
- Le catalogue est chargé par blocs de 500, en arrière-plan, au démarrage.
- La recherche et le scanner interrogent le serveur quand le produit
  n'est pas en mémoire.
- Complexité : les 3 usages ci-dessus doivent devenir des appels
  serveur.

**⚠️ Décision à trancher (D1 ci-dessous).** Ma recommandation :
**Option B pour le scanner et la recherche, Option A pour l'affichage**.

En pratique : le scanner physique appelle **déjà** le serveur
(`scanner.js:448` → `/api/produit?code_barres=`, 0,2 ms) — il n'est donc
**pas** concerné. Seuls la **recherche textuelle** de la caisse et le
**préchargement** le sont.

**Implémentation minimale (Option B, pragmatique) :**

1. `app.js:405` — charger la première page + le reste en arrière-plan :

```js
async loadProducts() {
    try {
        // 1re page : affichage immédiat.
        const first = await fetch(`${APP_URL}/api/produits/page?limit=500&offset=0`);
        const firstData = await first.json();
        this.allProducts = firstData.rows || [];
        this.totalProducts = firstData.total || this.allProducts.length;
        this.renderProducts(this.allProducts);

        // La suite en arrière-plan : l'utilisateur peut déjà travailler.
        this.loadProductsInBackground();
    } catch (e) { ... }
}
```

2. La **recherche** de la caisse : si le terme ne trouve rien en mémoire,
   interroger le serveur :

```js
async filterProducts(search, category = null) {
    // ...
    const local = this.allProducts.filter(...);
    if (local.length === 0 && search.length >= 3) {
        // Repli serveur : le produit peut être sur une page non chargée.
        const res = await fetch(`${APP_URL}/api/produits/page?search=${encodeURIComponent(search)}&limit=50`);
        const data = await res.json();
        this.renderProducts(data.rows || []);
        return;
    }
    this.renderProducts(local);
}
```

**Critères d'acceptation :**
- [ ] La caisse s'affiche en **< 2 s** avec 50 000 produits (première
      page seulement).
- [ ] Le scanner physique trouve un produit de la **dernière** page
      (< 500 ms).
- [ ] La recherche textuelle trouve un produit de la dernière page.
- [ ] Le panier continue de fonctionner (ajout, modification de quantité,
      suppression).
- [ ] La **mémoire** du navigateur reste stable (DevTools → Memory : pas
      de croissance continue pendant le préchargement).

---

### P6 — Tri par colonne + colonne « lot »

**Tri par colonne.** Aujourd'hui le tri est **fixe** : `ORDER BY p.nom ASC`
(`Product.php:122` et suivants). À 50 000 produits, le caissier veut trier par **stock**
(les plus faibles d'abord) ou par **prix**. Un tri par colonne doit être
un paramètre **contrôlé** (liste blanche), pas un `ORDER BY` interpolé.

```php
// Liste blanche : jamais de valeur libre dans le SQL.
$sortable = [
    'nom'       => 'p.nom ASC',
    'nom_desc'  => 'p.nom DESC',
    'prix'      => 'p.prix ASC',
    'prix_desc' => 'p.prix DESC',
    'stock'     => 'p.stock ASC',
    'stock_desc'=> 'p.stock DESC',
];
$sort = $sortable[$filters['sort'] ?? 'nom'] ?? $sortable['nom'];
```

⚠️ **Le tri par `p.stock` n'est cohérent qu'avec P1** : la colonne
`stock` est dénormalisée et doit être à jour.

**Colonne « lot ».** À 50 000 produits, la gestion des lots ( péremption,
quantités) est un métier à part entière. Aujourd'hui, les lots sont
visibles **uniquement** dans le modal d'édition d'un produit
(`loadProductBatches(product.id)` → `ProductBatchController::index`).

À 50 000 produits × 1,7 lot, il y a **85 000 lots**. Un écran « lots » avec
recherche « produit périmé sous 7 jours » est nécessaire — c'est
d'ailleurs ce que le tableau de bord fait déjà via
`ProductBatch::getExpiringSoon()`.

**Proposition :** une page `/produits/lots` listant les lots par
**péremption**, paginée, avec le nom du produit. Réutilise
`getExpiringSoon()` / `getExpired()` (déjà paginables) + la pagination
serveur de P3.

**Critères d'acceptation :**
- [ ] Le tri par nom / prix / stock / péremption fonctionne et se combine
      avec le filtre et la pagination.
- [ ] Une valeur de tri invalide ne provoque **pas** d'erreur SQL.
- [ ] `/produits/lots` liste les lots par péremption, paginée, avec le nom
      du produit.
- [ ] La liste des lots périmés est identique à celle du tableau de bord.

---

## 4. Décisions ouvertes

| # | Décision | Options | Recommandation |
|---|---|---|---|
| **D1** | La caisse charge-t-elle 50 000 produits en mémoire ? | (a) Oui, status quo (1 Mo gzip, 200 Mo RAM) ; (b) Par pages, avec repli serveur sur la recherche | **(b)** — mais (a) est acceptable si le parc est essentiellement desktop. À trancher selon le parc de terminaux (Sunmi ?). |
| **D2** | Le bouton « Modifier » de `/produits` : JSON inline ou rechargement ? | (a) JSON inline échappé correctement (0 backend) ; (b) `editProductById(id)` + route `GET /api/produit/[i:id]` (plus propre) | **(b)** — le JSON inline dupliqué 50 fois par page est fragile ; mais (a) est un diff plus petit. |
| **D3** | Le filtre `/produits` passe par l'URL ou en AJAX ? | (a) URL + rechargement (simple, 2 ms) ; (b) AJAX (plus fluide, + de code) | **(a)** pour commencer, (b) si l'usage montre que le rechargement gêne. |
| **D4** | Taille de page par défaut | 25, 50, 100 | **50** (118 Ko de HTML à 6 000 produits, ~2 ms de rendu). |
| **D5** | Pager par offset ou par curseur (keyset) ? | (a) `OFFSET` (simple) ; (b) keyset `WHERE nom > ?` (constant, mais incompatible avec « page 42 ») | **(a)** — avec l'index `(shop_id, nom)`, `OFFSET 44 950` reste rapide ; le keyset ne se justifierait qu'au-delà de 200 000. |
| **D6** | FULLTEXT ou pas ? | (a) Oui (recherche instantanée à 50 000) ; (b) Non, garder le `LIKE` | **(a)** — le `LIKE` Extrapolé à ~73 ms par frappe est mauvais. Mais **à valider sur la base réelle** avant de livrer. |

---

## 5. Ordre d'exécution

```
P1  Migrations + dénormalisation        ~30 min   (déjà écrit, à appliquer)
P2  Endpoint paginé                     ~1 h      (indépendant)
P3  Pagination serveur de /produits     ~1 jour   (LE CHANTIER)
P4  FULLTEXT                             ~3 h      (après P3)
P5  Caisse par pages                     ~1 jour   (indépendant de P3)
P6  Tri par colonne + page /lots        ~1 jour   (après P3)
```

**P1 + P2 se livrent seuls** (le catalogue devient-il plus rapide à lire
et à paginer, sans changer l'UX). **P3 est le blockade à lever.**
P4–P6 sont des améliorations d'ergonomie une fois P3 en place.

---

## 6. Ce qu'il ne faut PAS faire

- **Ne pas** mettre un `LIMIT` arbitraire dans `getAllFiltered()` sans le
  propager à tous les appelants : `ProductExportService`
  (`app/Services/ProductExportService.php:72`) a besoin de
  l'exhaustivité — un export **tronqué** est un bug fiscal, pas un bug
  d'affichage. D'où la **nouvelle** méthode `getPage()` plutôt qu'une
  modification de `getAllFiltered()`.
- **Ne pas** interpoler une valeur de tri dans le SQL : passer par une
  **liste blanche**.
- **Ne pas** supprimer `getAllFiltered()` : la caisse, l'export et le
  client mobile en dépendent.
- **Ne pas** mettre le FULLTEXT sans le repli `LIKE` pour les termes de
  moins de 4 caractères (`ft_min_word_len`) : sinon la recherche renvoie
  0 résultat pour « abc ».
- **Ne pas** réutiliser un placeholder nommé deux fois dans la même
  requête (`EMULATE_PREPARES = false` → `SQLSTATE HY093`).
- **Ne pas** écrire dans `product_batches` en SQL direct après P1 : la
  dénormalisation divergerait **silencieusement** et le stock affiché
  serait faux.
- **Ne pas** faire le tri par colonne avant P1 : `p.stock` doit être à
  jour.

---

## 7. Vérification commune à chaque étape

- [ ] `php -l` passe sur chaque fichier PHP modifié.
- [ ] `node -c` passe sur chaque fichier JS modifié.
- [ ] Les 376 tests existants passent, + les nouveaux.
- [ ] Une migration est **exécutée deux fois** (idempotence) via
      `php scripts/apply_migration.php <fichier.sql>`.
- [ ] Les montants et les stocks sont vérifiés sur des **données réelles**,
      pas seulement « une ligne est apparue ».
- [ ] Une **mesure navigateur** (DevTools → Performance) accompagne chaque
      étape qui touche le rendu : le SQL ne dit rien de ce que subit le
      navigateur.
- [ ] `git status --short` ne montre que les fichiers de l'étape.

---

## 8. Mesures à 50 000 — ce qui reste à confirmer

✅ **Mesuré** (base jetable `pos_perf_b`, 50 000 produits / 85 000 lots,
MySQL 9.1.0) :

- requête catalogue sans `LIMIT` : 5 883 – 6 676 ms ;
- avec `LIMIT 50` : 1 239 – 1 384 ms ;
- dé-normalisée + `LIMIT 50` : 586 – 675 ms ;
- dé-normalisée + `OFFSET 49 950` : 801 ms ;
- backfill R2 (sous-requêtes corrélées) : 2 477 – 5 161 ms, **0 écart**
  vérifié contre la source de vérité.

⚠️ **À confirmer** (non mesuré, à faire sur la base réelle) :

1. Le `LIKE '%terme%'` et le FULLTEXT sur 50 000 lignes (décision **D6**) :
   c'est le seul point du plan dont la réponse **change** selon l'échelle.
2. Le rendu HTML et le coût du filtre **dans un vrai navigateur** — les
   chiffres JS sont un **biais optimiste** : ils ne mesurent ni le
   parcours du DOM ni le reflow.
3. Le comportement de FULLTEXT sur l'hébergeur (`ft_min_word_len` peut y
   différer de la config locale).

### Une leçon à retenir

La croissance entre 6 000 et 50 000 produits a été de **×28**, pas de
×8,3 : le `GROUP BY` sur les lots avec `Using temporary; Using filesort`
se dégrade **plus vite que le linéaire**.

Les premiers chiffres (à 6 000) donnaient l'impression que le FULLTEXT
serait une perte (22,5 ms contre 8,0 ms) et que la pagination seule
suffirait. À 50 000, les deux conclusions **s'inversent**. Toute
décision d'architecture prise sur un échantillon réduit doit être
**re-mesurée à l'échelle cible**.

---

## Annexe — les mesures de référence (6 000 produits, mesurées)

```
Requête catalogue (sans LIMIT) :      127,7 ms   (6 000 lignes)
JSON                               :   3,23 Mo   (gzip : 0,14 Mo)
HTML rendu /produits                :  13,90 Mo   (gzip : 0,40 Mo) — 6 000 <tr>
Page de 50 lignes                  :   0,12 Mo   (1,0 ms)

Correctifs candidats (LIMIT 50) :
    actuel + index (shop_id, nom)          95,5 ms
    pré-agrégation + LIMIT 50              70,6 ms
    dénormalisation + LIMIT 50             30,6 ms  ← le correctif

Pagination profonde (avec index) :
    LIMIT 50 OFFSET 0    (page 1)           0,2 ms
    LIMIT 50 OFFSET 4950 (page 100)         1,1 ms

Recherche :
    LIKE '%terme%' (scan complet)           8,0 ms
    FULLTEXT                                 22,5 ms  ← plus lent à 6 000
    code-barres exact (index UNIQUE)        0,2 ms

Import de 5 000 produits :
    ACTUEL : findByBarcode par ligne        7,49 s   (25 000 requêtes)
    APRÈS  : 1 SELECT + lookup mémoire      0,01 s   (1 requête) → 786× plus rapide
```

---

## Annexe — où le catalogue est chargé

| Emplacement | Ce qu'il charge | Traité par |
|---|---|---|
| `app.js:405` (`loadProducts`) | tout le catalogue, dans la caisse | **P5** |
| `app.js:377` | `allProducts.find(p => p.code_barres === barcode)` | ✅ déjà rapide si serveur (scanner) |
| `app.js:422` (`filterProducts`) | filtre sur le tableau complet | **P5** |
| `app.js:4289` (`initProductsTabs`) | 3 `querySelector` × 50 000 `<tr>` par frappe | **P3** |
| `app.js:631` | `allProducts.find(p => p.id == id)` | **P5** |
| `PageController.php:182` | `getAll()` pour un `count()` | ✅ fait (R5) |
| `PageController.php:394` | `getAll()` pour le rendu HTML | **P3** |
| `ProductExportService.php:72` | `getAllFiltered()` — **exhaustivité requise** | ⚠️ ne pas limiter |
| `ProductImportService.php:183` | `findByBarcode()` par ligne | ✅ fait (R4) |
| `scanner.js:448` | `/api/produit?code_barres=` — **rapide** (0,2 ms) | ✅ rien à faire |
