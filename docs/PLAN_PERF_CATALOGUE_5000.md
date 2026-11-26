# Plan de performance — catalogue produits à l'échelle 5000+

**Date :** 2026-11-24
**Branche :** `main`
**Source :** `docs/ANALYSE_CHARGE_PRODUITS_5000.md`
**Benchmark reproductible :** `php scripts/perf_bench_catalogue.php 6000`

**Ce document est la **liste de travaux** issue de l'analyse de charge. Chaque
entrée est actionnable : fichiers concernés, méthode, critère d'acceptation et
risque. Aucun code applicatif n'a encore été modifié.

**État au 2026-11-24 :**
- **R4, R5 et R6 sont IMPLÉMENTÉS et vérifiés** (code applicatif + tests).
  - R4 : import produits — 4,22 s → 0,05 s (80×), 15 000 requêtes → 10.
  - R5 : tableau de bord — ne charge plus le catalogue (400 ms → ~3 ms).
  - R6 : compression gzip — `/api/produits` 44 272 → 3 494 octets.
  - Tests ajoutés : `tests/Integration/ProductBatchLookupTest.php` (12 tests)
    et `tests/Integration/ProductDashboardCountTest.php` (6 tests).
- **R1 et R3 (pagination serveur) sont IMPLEMENTÉS** dans le cadre du plan
  à 50 000 produits (`docs/PLAN_PRODUITS_50000.md`) : endpoint
  `/api/produits/page`, pagination de la page `/produits`, filtre passé au
  serveur, caisse par blocs. Gains mesurés à 50 000 produits : 1re page
  3 693 ms → 478 ms, page HTML 60,8 Mo → 0,06 Mo.
  Tests : `tests/Integration/ProductPaginationTest.php` (9 tests).
- Les **deux migrations** (R7 et R2) sont **écrites et vérifiées** — appliquées
  deux fois de suite sur une base jetable construite depuis le dump réel,
  backfill contrôlé sur 3 cas (dont un produit dont tous les lots sont
  périmés). Fichiers :
  `migrations/2026_11_24_index_produits_shop_nom.sql` et
  `migrations/2026_11_24_produits_stock_denormalise.sql`.
  ⚠️ **Elles ne sont pas encore appliquées sur `pos_system`** (la base de
  production n'a pas été touchée).
- Suite complète : **385 tests OK** (2 ignorés : tests HTTP qui exigent un
  serveur joignable).

---

## Résumé

| # | Correctif | Gain mesuré | Effort | Risque | Dépendances |
|---|---|---|---|---|---|
| **R4** | Supprimer le N+1 de l'import | 4,22 s → 0,05 s (80×) | ~1 h | Faible | ✅ **fait** |
| **R5** | Compter au lieu de charger (dashboard) | 400 ms → ~3 ms | ~30 min | Faible | ✅ **fait** |
| **R6** | Activer la compression gzip | 44 272 → 3 494 o | ~15 min | Faible | ✅ **fait** |
| **R7** | Index `(shop_id, nom)` | 232 ms → 194 ms | ~5 min | Nul | ✅ migration prête |
| **R2** | Dénormaliser le stock sur `produits` | 106 ms → 31 ms | ~2 h | **Moyen** | ✅ migration prête |
| **R3** | Paginer le rendu de `/produits` | 13,9 Mo → 118 Ko | ~1 jour | **Moyen** | R2, R7 |
| **R1** | Endpoint catalogue paginé | socle de R3 | ~1 h | Faible | — |
| **R8** | Recherche FULLTEXT | **négatif à ce volume** | — | — | différer |
| **R9** | `code_barres` composite | correctif de fond | ~2 h | **Élevé** | à trancher |

**Ordre d'exécution recommandé :** R4 → R5 → R6 → R7 → R2 → R1 → R3.
Les quatre premiers sont indépendants, à faible risque et livrables séparément.
R2 et R3 forment le cœur du travail ; R9 est une décision de fond, pas un
correctif de performance.

---

## Migrations prêtes (écrites et vérifiées)

Les deux migrations nécessaires sont **déjà écrites** et **testées** — appliquées
deux fois de suite sur une base jetable construite depuis le dump réel
(`pos_system-2026-11-21_174246-dump.sql`), avec backfill contrôlé.

| Fichier | Contenu | État |
|---|---|---|
| `migrations/2026_11_24_index_produits_shop_nom.sql` | Index `(shop_id, nom)` | ✅ écrit, idempotent vérifié |
| `migrations/2026_11_24_produits_stock_denormalise.sql` | Colonnes `available_stock` + `nearest_expiration_date` + backfill | ✅ écrit, idempotent vérifié |

**Vérifications effectuées :**
- chaque migration appliquée **2 fois** sans erreur (`scripts/apply_migration.php`) ;
- schéma résultant contrôlé (`SHOW INDEX`, `SHOW COLUMNS`) ;
- backfill validé sur 3 cas, y compris un produit **dont tous les lots sont
  périmés** (`available_stock = 0`, `nearest_expiration_date = NULL`) ;
- comparaison colonne par colonne **requête agrégée vs colonnes dénormalisées** :
  `OK` sur les 3 cas.

**À faire par l'utilisateur (la base de production lui appartient) :**

```bash
mysql -u root -p pos_system < migrations/2026_11_24_index_produits_shop_nom.sql
mysql -u root -p pos_system < migrations/2026_11_24_produits_stock_denormalise.sql
```

⚠️ La seconde migration prend quelques secondes (backfill de 6 000 lignes) :
à lancer pendant une **fenêtre calme**, pas pendant une vente.

⚠️ Entre l'application de la migration R2 et la modification du modèle
(`ProductBatch::recalculateProductStock`), les colonnes dénormalisées sont
correctes mais **ne sont plus rafraîchies**. Enchaîner migration + code, ou
ne pas appliquer R2 avant d'avoir livré le code.

---

## R4 — Supprimer le N+1 de l'import · Priorité 1

### Problème

`ProductImportService::importFile()` appelle `Product::findByBarcode()`
**par ligne** du fichier (`app/Services/ProductImportService.php:183`).
Or `findByBarcode()` (`app/models/Product.php:92`) appelle
`enrichWithBatchInfo()` (`app/models/Product.php:129`) qui exécute **4
requêtes supplémentaires** (`ProductBatch::getTotalStock`,
`getAvailableStock`, `getNearestExpirationDate`, `findByProductId`).

Résultat : **5 requêtes × N lignes**. Mesuré à **7,5 s pour 5 000 produits**
(25 000 requêtes). La projection à 20 000 produits atteint 30 s — le timeout
PHP par défaut.

C'est **le** chemin par lequel le catalogue se garnit : c'est donc le
goulot d'étranglement le plus bloquant à 5 000+ produits.

### Correctif

Dans `importFile()`, charger **une seule fois** l'ensemble des code-barres
existants avant la boucle, puis chercher dans ce tableau.

```php
// À insérer après `$taxes = $this->loadTaxes();` (ligne ~142)
$existingBarcodes = $this->loadExistingBarcodes($shopId);
```

Nouvelle méthode privée, à placer près de `loadCategories()` (ligne 733) :

```php
/**
 * Charge les code-barres des produits de la boutique (indexés par code-barres).
 *
 * ⚠️ Remplace l'appel par ligne à `findByBarcode()` : ce dernier déclenche
 * `enrichWithBatchInfo()` (4 requêtes), soit 5 requêtes par ligne importée —
 * 25 000 requêtes pour 5 000 produits (7,5 s mesuré).
 */
private function loadExistingBarcodes($shopId): array
{
    $barcodes = [];
    try {
        $sql = 'SELECT id, code_barres FROM produits';
        $params = [];
        if ($shopId) {
            $sql .= ' WHERE shop_id = ?';
            $params[] = $shopId;
        }
        $rows = \App\Core\Database::getInstance()->fetchAll($sql, $params);
        foreach ($rows as $row) {
            $barcodes[(string)$row['code_barres']] = (int)$row['id'];
        }
    } catch (\Throwable $e) {
        error_log('ProductImportService::loadExistingBarcodes error: ' . $e->getMessage());
    }
    return $barcodes;
}
```

Puis, dans la boucle (remplacer la ligne 183) :

```php
$existingId = $existingBarcodes[$barcodeKey] ?? null;
$existing = $existingId ? $this->productModel->findById($existingId) : null;
```

⚠️ **Attention** : `findById()` fait toujours 5 requêtes (dont
`enrichWithBatchInfo`). C'est **acceptable** ici, car il n'est appelé que pour
les produits **existants** (donc peu nombreux en import initial). Mais si le
mode est `update` sur un catalogue déjà rempli, cela reste N × 5.

→ **Correctif complémentaire recommandé** : charger aussi le stock des
produits existants dans le même `SELECT` (voir la variante ci-dessous), et
n'appeler `findById()` que si les champs enrichis sont réellement nécessaires
à l'import.

**Variante plus complète (1 requête, tout inclus) :**

```php
private function loadExistingProducts($shopId): array
{
    $out = [];
    try {
        $sql = 'SELECT p.id, p.code_barres, p.nom, p.prix, p.stock, p.stock_minimum,
                       p.taxe_id, p.category_id, p.product_type, p.prod_service,
                       p.remise_type, p.remise_value, p.taxe_specifique_type,
                       p.taxe_specifique_value, p.image, p.date_expiration,
                       COALESCE(SUM(CASE WHEN pb.date_expiration IS NULL
                                          OR pb.date_expiration >= CURDATE()
                                         THEN pb.stock ELSE 0 END), 0) AS available_stock
                FROM produits p
                LEFT JOIN product_batches pb ON pb.product_id = p.id';
        $params = [];
        if ($shopId) {
            $sql .= ' WHERE p.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' GROUP BY p.id';
        $rows = \App\Core\Database::getInstance()->fetchAll($sql, $params);
        foreach ($rows as $row) {
            $out[(string)$row['code_barres']] = $row;
        }
    } catch (\Throwable $e) {
        error_log('ProductImportService::loadExistingProducts error: ' . $e->getMessage());
    }
    return $out;
}
```

### Critères d'acceptation

- [ ] `php scripts/perf_bench_catalogue.php 6000` → section 8 affiche un ratio
      > 100× entre l'ancien et le nouveau chemin.
- [ ] L'import d'un fichier de 5 000 produits se termine en **< 2 s**.
- [ ] Le rapport d'import affiche les **mêmes** compteurs qu'avant
      (`created` / `updated` / `skipped` / `errors`) — comparer sur un fichier
      de 500 produits importé avant et après le correctif.
- [ ] `tests/Integration/ProductImportIntegrationTest.php` passe.
- [ ] `tests/Unit/ProductImportServiceTest.php` passe.

### Risques

- **Le filtrage par `shop_id` change la sémantique.** Aujourd'hui
  `findByBarcode()` ne filtre PAS par boutique : un code-barres d'une autre
  boutique est trouvé et l'import le **met à jour** (au lieu de le créer).
  Avec `WHERE shop_id = ?`, ce produit devient « inexistant » → l'import le
  **crée** → `Duplicate entry` sur l'index UNIQUE de `code_barres`.
  → **Décision à trancher** (voir §Décisions ouvertes D1).
- La charge mémoire est proportionnelle au catalogue (≈ 500 Ko pour 5 000
  produits) — négligeable.

---

## R5 — Compter au lieu de charger (tableau de bord) · Priorité 1

### Problème

`PageController::dashboard()` (`app/controllers/PageController.php:182`) :

```php
$produits = $productModel->getAll($shopId);   // 6 000 lignes chargées…
'produits_compte' => count($produits),          // …pour en compter 6 000
```

Et, plus bas (ligne 258) :

```php
'stock_faible' => array_filter($produits, function ($p) { ... }),
```

Mesuré : **403,8 ms** pour `getAll() + count()`, contre **2,6 ms** pour un
`SELECT COUNT(*)` direct. **400 ms gaspillés** sur le tableau de bord, à chaque
chargement, pour afficher un compteur.

### Correctif

Remplacer le chargement par deux requêtes ciblées.

**1. Le compteur** — remplacer `$produits = $productModel->getAll($shopId);`
et `'produits_compte' => count($produits)` par :

```php
$db = \App\Core\Database::getInstance();
$productsCount = (int)$db->fetch(
    'SELECT COUNT(*) as c FROM produits WHERE shop_id = ?',
    [$shopId]
)['c'];
```

⚠️ Attention au cas `super_admin` : `$shopId` est `null` dans ce cas, et
`WHERE shop_id = NULL` ne matche aucune ligne. Le code existant utilise
`getAll(null)` qui retourne **tous** les produits. Il faut donc gérer les deux
branches :

```php
$productsCount = $shopId
    ? (int)$db->fetch('SELECT COUNT(*) as c FROM produits WHERE shop_id = ?', [$shopId])['c']
    : (int)$db->fetch('SELECT COUNT(*) as c FROM produits')['c'];
```

**2. Les stocks faibles** — remplacer l'`array_filter` par une requête :

```php
$stockFaible = $shopId
    ? $db->fetchAll('SELECT p.* FROM produits p WHERE p.shop_id = ? AND p.stock <= p.stock_minimum ORDER BY p.nom ASC', [$shopId])
    : $db->fetchAll('SELECT p.* FROM produits WHERE stock <= stock_minimum ORDER BY nom ASC');
```

⚠️ `p.stock` est la colonne dénormalisée qui existe déjà dans le schéma.
Elle est recalculée à chaque écriture de lot (voir R2) — donc fiable **à
condition que R2 soit livré**, sinon elle peut diverger de
`SUM(product_batches.stock)`.

→ **Point de vigilance :** si R2 n'est pas livré, la requête doit agréger les
lots explicitement :

```php
$db->fetchAll('SELECT p.*, COALESCE(SUM(pb.stock),0) AS total_stock
               FROM produits p
               LEFT JOIN product_batches pb ON pb.product_id = p.id
               WHERE p.shop_id = ? AND p.stock <= p.stock_minimum
               GROUP BY p.id', [$shopId]);
```

### Critères d'acceptation

- [ ] Le compteur affiché sur le tableau de bord est **identique** à celui
      d'avant le correctif (vérifier sur une boutique avec > 0 produit).
- [ ] La liste « stock faible » contient exactement les mêmes produits
      (comparer les ID avant/après sur la base réelle).
- [ ] Le tableau de bord se charge en **< 50 ms** côté requêtes produits
      (au lieu de 400 ms).
- [ ] Le cas `super_admin` (sans boutique en session) affiche bien le
      total **global**, pas 0.

### Risques

- Le cas `super_admin` est le piège principal : un `WHERE shop_id = NULL`
  silencieux affiche « 0 produit ». **Test explicite obligatoire.**
- `getAll()` est appelé ailleurs dans `dashboard()` — vérifier la ligne
  exacte après modification (le tableau `shopStats` utilise déjà sa propre
  requête `COUNT(*)`, ligne 234 : elle est déjà correcte).

---

## R6 — Activer la compression gzip · Priorité 1

### Problème

Aucune compression n'est active : ni dans `public/.htaccess` (35 lignes,
aucun module de compression), ni dans `php.ini`, ni au niveau applicatif.

Conséquences mesurées sur 6 000 produits :

| Ressource | Sans gzip | Avec gzip |
|---|---|---|
| JSON `/api/produits` | **3,23 Mo** | 0,14 Mo |
| HTML `/produits` | **13,90 Mo** | 0,40 Mo |

Sur une connexion 3G/4G en boutique (ou un Sunmi en Wi-Fi faible), c'est
la différence entre une caisse qui charge en 2 s et une caisse qui charge
en 40 s.

### Correctif

Ajouter dans `public/.htaccess`, **après** le bloc `RewriteRule` :

```apache
# ============================================================
# COMPRESSION — /api/produits renvoie 3,2 Mo en JSON sans gzip
# ============================================================
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE text/plain
</IfModule>
```

**Si `mod_deflate` n'est pas disponible** (hébergeur mutualisé qui le
désactive), compresser au niveau applicatif dans `public/index.php`, avant
l'envoi de la réponse :

```php
// Compression de la réponse (fallback si mod_deflate n'est pas actif).
// Le catalogue JSON passe de 3,2 Mo à 0,14 Mo : sur une connexion 3G/4G
// en boutique, c'est la différence entre 2 s et 40 s de chargement.
if (!headers_sent() && function_exists('ob_get_level')) {
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (strpos($acceptEncoding, 'gzip') !== false) {
        @ini_set('zlib.output_compression', '1');
        @ini_set('output_buffering', '4096');
    }
}
```

### Critères d'acceptation

- [ ] `curl -s -H "Accept-Encoding: gzip" -o /dev/null -w "%{size_download}" \
       http://localhost/api/produits` renvoie une taille **≤ 20 %** de la
       taille sans compression.
- [ ] `Content-Encoding: gzip` présent dans les en-têtes de réponse.
- [ ] La page `/produits` et la caisse s'affichent **identiquement** (le
      navigateur décompresse seul).
- [ ] Vérifier que le **Service Worker** (`sw.js`) n'est pas cassé par la
      compression : il manipule des réponses en JavaScript.

### Risques

- `mod_deflate` peut être indisponible chez l'hébergeur → **prévoir le
  fallback applicatif** ci-dessus (ou vérifier avant de livrer).
- Certains proxies d'entreprise réinjectent du contenu et cassent le gzip :
  le navigateur gère (il ignore un `Content-Encoding` incohérent), mais
  tester depuis le réseau réel de la boutique.
- Le fallback `zlib.output_compression` affecte **toute** la réponse : ne pas
  l'activer sur les endpoints streaming (il n'y en a pas actuellement).

---

## R7 — Index `(shop_id, nom)` · Priorité 2

### Problème

`produits` n'a que les index `code_barres` (UNIQUE), `category_id`,
`product_type`, `shop_id` — **pas d'index sur `nom`**.

Or la requête catalogue trie par `p.nom ASC` (`app/models/Product.php:89`) et
la pagination (R3) en dépend. `EXPLAIN` montre :

```
c  type=ALL   key=-  rows=9  extra=Using temporary; Using filesort
```

Le `Using filesort` est le tri de 6 000 lignes sans index.

### Correctif

Nouveau fichier `migrations/2026_11_24_index_produits_shop_nom.sql` :

```sql
-- ============================================================================
-- MIGRATION : Index composite (shop_id, nom) sur `produits`
-- Date       : 2026-11-24
-- Référence  : docs/PLAN_PERF_CATALOGUE_5000.md (correctif R7)
--
-- OBJECTIF
--   La requête catalogue (Product::getAllFiltered) filtre sur `shop_id` et
--   trie par `nom`. `produits` n'a aucun index sur `nom` : MySQL matérialise
--   donc un réservoir temporaire puis trie ("Using temporary; Using filesort").
--
--   Gain mesuré sur 6 000 produits : 232 ms → 194 ms.
--   Rend aussi viable la pagination profonde : OFFSET 4950 = 1,1 ms.
--
-- IDEMPOTENCE
--   Le CREATE INDEX n'est pas idempotent en MySQL : on teste donc
--   l'existence de l'index dans information_schema avant de le créer
--   (même pattern que migrations/2026_11_17_caisse_brouillons.sql).
--
-- RÉVERSIBLE
--   DROP INDEX idx_prod_shop_nom ON produits;
-- ============================================================================

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'produits'
    AND index_name = 'idx_prod_shop_nom'
);

SET @sql = IF(
  @idx_exists = 0,
  'CREATE INDEX idx_prod_shop_nom ON produits (shop_id, nom)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

### Critères d'acceptation

- [ ] `EXPLAIN` de la requête catalogue ne montre plus `Using filesort` sur
      la table `p`.
- [ ] `php scripts/perf_bench_catalogue.php 6000` → section 2 confirme
      la baisse (232 ms → ~194 ms).
- [ ] La migration est **appliquée deux fois** sans erreur :
      `php scripts/apply_migration.php migrations/2026_11_24_index_produits_shop_nom.sql`
      (le script exécute le fichier statement par statement et gère les
      blocs `PREPARE`/`EXECUTE`).

### Risques

- Nul côté fonctionnel. Index non unique → pas d'impact sur les écritures
  au-delà d'un coût marginal sur l'INSERT.
- ⚠️ Le nom du fichier de migration doit suivre la convention
  `YYYY_MM_DD_<verb>_<subject>.sql` du projet.

---

## R2 — Dénormaliser le stock sur `produits` · Priorité 1 (après R7)

### Problème

Le catalogue agrège les lots **à chaque lecture** :

```sql
COALESCE(SUM(pb.stock), 0) AS total_stock,
COALESCE(SUM(CASE WHEN pb.date_expiration IS NULL OR pb.date_expiration >= CURDATE()
                  THEN pb.stock ELSE 0 END), 0) AS available_stock,
MIN(CASE WHEN pb.date_expiration IS NOT NULL AND pb.stock > 0
         AND pb.date_expiration >= CURDATE() THEN pb.date_expiration END) AS nearest_expiration_date
```

C'est un `GROUP BY` sur 17 000 lignes de lots, **pour chaque affichage du
catalogue**. C'est la cause du `Using temporary; Using filesort`.

### Correctif

Ajouter deux colonnes sur `produits`, maintenues par les points d'écriture
existants :

- `available_stock float NOT NULL DEFAULT 0`
- `nearest_expiration_date date DEFAULT NULL`

**Point d'écriture unique :** `ProductBatch::recalculateProductStock()`
(`app/models/ProductBatch.php:234`) est déjà appelé par **toutes** les
mutations de lots :

| Mutation | Appel |
|---|---|
| `ProductBatch::create()` | ligne 26 |
| `ProductBatch::update()` | ligne 63 |
| `ProductBatch::delete()` | ligne 74 |
| `ProductBatch::deductStock()` | ligne 140 |
| `ProductBatch::restoreStock()` | ligne 174 |
| `Product::update()` | `Product.php:219` → `Product::recalculateProductStock()` |

→ **C'est le point unique à modifier.** La méthode devient :

```php
private function recalculateProductStock($productId)
{
    $total = $this->getTotalStock($productId);
    $available = $this->getAvailableStock($productId);
    $nearest = $this->getNearestExpirationDate($productId);
    $this->db->query(
        'UPDATE produits SET stock = :stock, available_stock = :available_stock,
                             nearest_expiration_date = :nearest
         WHERE id = :id',
        [
            ':stock'            => $total,
            ':available_stock'  => $available,
            ':nearest'          => $nearest,
            ':id'               => $productId,
        ]
    );
}
```

**Migration** `migrations/2026_11_24_produits_stock_denormalise.sql` :

```sql
-- ============================================================================
-- MIGRATION : Dénormalisation du stock sur `produits`
-- Date       : 2026-11-24
-- Référence  : docs/PLAN_PERF_CATALOGUE_5000.md (correctif R2)
--
-- OBJECTIF
--   Le catalogue (Product::getAllFiltered) agrège les lots à CHAQUE lecture :
--     COALESCE(SUM(pb.stock), 0) AS total_stock,
--     COALESCE(SUM(CASE WHEN pb.date_expiration IS NULL OR
--            pb.date_expiration >= CURDATE() THEN pb.stock ELSE 0 END), 0),
--     MIN(CASE WHEN ... THEN pb.date_expiration END)
--   C'est un GROUP BY sur 17 000 lignes de lots par affichage du catalogue,
--   et la source du "Using temporary; Using filesort" : 232 ms par requête.
--
--   On ajoute donc deux colonnes dénormalisées, maintenues par
--   ProductBatch::recalculateProductStock() — qui est déjà le point
--   d'écriture unique de TOUTES les mutations de lots (create, update,
--   delete, deductStock, restoreStock).
--
--   Gain mesuré (page de 50, avec l'index R7) : 106 ms → 31 ms.
--
-- ⚠️ CONTRAINTE (à respecter absolument) : toute écriture dans
--    `product_batches` DOIT passer par
--    ProductBatch::recalculateProductStock(). Une écriture
--    directe en SQL (script, phpMyAdmin, migration) laisserait la
--    dénormalisation diverger SILENCIEUSEMENT : le stock affiché serait faux.
--
-- IDEMPOTENCE
--   ALTER TABLE ... ADD COLUMN n'est pas idempotent en MySQL : on teste
--   l'existence de chaque colonne dans information_schema avant de l'ajouter
--   (même pattern que migrations/2026_11_17_caisse_brouillons.sql).
--
-- RÉVERSIBLE
--   ALTER TABLE produits DROP COLUMN available_stock, DROP COLUMN nearest_expiration_date;
-- ============================================================================

-- ── available_stock ────────────────────────────────────────────────────────
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'produits'
    AND column_name = 'available_stock'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE produits ADD COLUMN available_stock float NOT NULL DEFAULT 0',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── nearest_expiration_date ────────────────────────────────────────────────
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'produits'
    AND column_name = 'nearest_expiration_date'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE produits ADD COLUMN nearest_expiration_date date DEFAULT NULL',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Backfill : aligner les colonnes sur l'état actuel des lots ─────────────
-- ⚠️ Le UPDATE est idempotent par nature (il recalcule depuis la source
--    de vérité : `product_batches`). Il peut donc être rejoué sans risque.
UPDATE produits p SET
    p.stock = (
        SELECT COALESCE(SUM(b.stock), 0) FROM product_batches b
        WHERE b.product_id = p.id
    ),
    p.available_stock = (
        SELECT COALESCE(SUM(b.stock), 0) FROM product_batches b
        WHERE b.product_id = p.id
          AND (b.date_expiration IS NULL OR b.date_expiration >= CURDATE())
    ),
    p.nearest_expiration_date = (
        SELECT MIN(b.date_expiration) FROM product_batches b
        WHERE b.product_id = p.id AND b.stock > 0
          AND b.date_expiration >= CURDATE()
    );
```

⚠️ Le `ALTER TABLE` + `UPDATE` de 6 000 lignes prend **quelques secondes** :
à lancer pendant une fenêtre calme, pas pendant une vente.

⚠️ Après la migration, `ProductBatch::recalculateProductStock()` DOIT être
modifié (voir ci-dessus) **avant** toute vente : entre la migration et la
modification du modèle, les colonnes dénormalisées sont correctes mais ne
sont plus rafraîchies.

**Puis la requête catalogue** (`app/models/Product.php:78`) devient :

```php
return $this->db->fetchAll("SELECT p.*, c.category AS categorie, t.taux AS tax_rate, t.etiquette AS tax_etiquette, s.nom AS shop_name, s.actif AS shop_actif,
        p.stock AS total_stock,
        p.available_stock AS available_stock,
        p.nearest_expiration_date AS nearest_expiration_date
    FROM produits p
    INNER JOIN categories c ON p.category_id = c.id
    LEFT JOIN taxes t ON p.taxe_id = t.id
    LEFT JOIN shops s ON p.shop_id = s.id
    $whereSql
    ORDER BY p.nom ASC", $params);
```

⚠️ Le `LEFT JOIN product_batches pb` disparaît → le `GROUP BY p.id` aussi.

**Report des produits** (`app/models/Report.php:542`) : il utilise
`p.stock` directement — la dénormalisation le rend **plus** correct, pas moins.

### Critères d'acceptation

- [ ] La requête catalogue ne contient **plus** de `GROUP BY` ni de jointure
      sur `product_batches`.
- [ ] `EXPLAIN` ne montre plus `Using temporary; Using filesort`.
- [ ] `php scripts/perf_bench_catalogue.php 6000` → section 5 : « dénormalisation
      + LIMIT 50 » **< 35 ms**.
- [ ] **Test de non-régression du stock** — le point critique :
      - [ ] après une vente de 3 unités, `produits.stock` **et**
            `produits.available_stock` ont bien décrémenté ;
      - [ ] après la péremption d'un lot, `available_stock` diffère de
            `stock` de la quantité périmée ;
      - [ ] `nearest_expiration_date` pointe le lot **non périmé** le plus
            proche (tester avec 2 lots, dont 1 périmé) ;
      - [ ] après une annulation de vente (`restoreStock`), les 3 colonnes
            sont revenues à leur valeur d'avant.
- [ ] `tests/Integration/ProductApiTest.php` passe.
- [ ] Le rapport « stock » (`Report::getArticlesReport`) affiche les mêmes
      chiffres qu'avant.

### Risques — **le plus élevé de la liste**

- **Divergence silencieuse.** Si un lot est écrit en SQL direct (script,
  `phpMyAdmin`, migration) sans passer par `ProductBatch`, les colonnes
  dénormalisées ne sont plus à jour. Le stock affiché sera faux.
  → **Mitigation** : documenter la contrainte dans le fichier de migration
  (fait ci-dessus) et l'ajouter aux invariants du projet.
- `Product::recalculateProductStock()` (`app/models/Product.php:223`) fait
  **aussi** un `UPDATE produits SET stock` — il doit être aligné sur la
  nouvelle méthode, sinon il écrase `available_stock` avec une valeur
  calculée par un chemin différent.
- La migration `ALTER TABLE` + `UPDATE` de 6 000 lignes prend **quelques
  secondes** : à lancer pendant une fenêtre calme, pas pendant une vente.
- ⚠️ `nearest_expiration_date` est une **date**, pas un timestamp : une
  péremption dans la journée n'est pas détectée (comportement actuel
  identique — `date_expiration >= CURDATE()`).

---

## R1 — Endpoint catalogue paginé · Priorité 1

### Problème

`GET /api/produits` (`app/controllers/ProductController.php:12`) renvoie
**tout** le catalogue. C'est la source du problème côté client : la caisse
charge 6 000 objets pour afficher 20 cartes.

### Décision de conception (à trancher)

Deux besoins différents se partagent aujourd'hui la même route :

| Consommateur | Besoin | Volume |
|---|---|---|
| La caisse (`app.js:405`) | catalogue **complet** en mémoire (recherche + scanner par tableau) | 6 000 objets |
| La page `/produits` | **pages** de 50 | 50 lignes |
| Le client mobile Android (`docs/API_MOBILE.md:41`) | catalogue **complet** | 6 000 objets |

**Option A (recommandée)** — `GET /api/produits` reste sans limite (contrat
mobile et caisse préservés), et une **nouvelle** route paginée alimente la
page `/produits` :

```php
// routes/api.php — après la ligne 164
Router::get('/api/produits/page', [ProductController::class, 'page'], 'caisse|produits', 'shared_read');
```

**Option B** — `GET /api/produits` devient paginé par défaut. Plus propre,
mais **casse le client mobile Android** qui attend un tableau JSON.

→ **Recommandation : Option A.** Elle supprime le problème le plus lourd
(le HTML serveur de R3) sans casser aucun contrat existant.

### Correctif (Option A)

**1. Le modèle** — `app/models/Product.php`, nouvelle méthode :

```php
/**
 * Page de produits avec filtres (pagination serveur).
 *
 * @param array $filters search, category, shop_id
 * @param int   $limit
 * @param int   $offset
 * @return array{rows: array, total: int}
 */
public function getPage(array $filters = [], int $limit = 50, int $offset = 0): array
{
    // Réutilise getAllFiltered() en y ajoutant LIMIT/OFFSET, et un COUNT
    // séparé pour le total (2,0 ms mesuré sur 6 000 produits).
}
```

⚠️ **Piège `EMULATE_PREPARES = false`** (règle du skill) : le `LIMIT`/`OFFSET`
ne peut pas être mélangé avec des placeholders nommés dans la même requête.
Deux styles possibles, **il faut en choisir un** :

```php
// ✅ Possibilité 1 — LIMIT/OFFSET en littéral casté (le plus simple ici)
$limit  = max(1, min(200, (int)$limit));
$offset = max(0, (int)$offset);
$sql = "SELECT ... WHERE ... ORDER BY p.nom ASC LIMIT {$limit} OFFSET {$offset}";
// castés en int → pas d'injection possible

// ✅ Possibilité 2 — placeholders positionnels pour LIMIT/OFFSET,
//                 nommés pour le reste : INTERDIT (mixte = SQLSTATE HY093)
```

→ **Utiliser la possibilité 1** (littéraux castés en `int`). Le projet le
fait déjà : `app/models/CaisseBrouillon.php:179`
(`' LIMIT ' . (int)$limit`).

**2. Le contrôleur** — `app/controllers/ProductController.php` :

```php
public function page()
{
    $productModel = new Product();
    $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
    $includeInactive = $this->isSuperAdmin() && !empty($_GET['include_inactive']);

    $limit  = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $filters = [
        'search'  => $this->sanitaze($_GET['search'] ?? ''),
        'category'=> $_GET['category'] ?? '',
        'shop_id' => !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null,
    ];

    $result = $productModel->getPage($filters, $limit, $offset, $shopId, $includeInactive);
    $this->json([
        'rows'  => $result['rows'],
        'total' => $result['total'],
        'limit' => $limit,
        'offset'=> $offset,
    ]);
}
```

**3. La route** — `routes/api.php` :

```php
Router::get('/api/produits/page', [ProductController::class, 'page'], 'caisse|produits', 'shared_read');
```

⚠️ Règle du skill : `ProductController` est **déjà** importé dans
`routes/api.php` (ligne 27) — rien à ajouter.

### Critères d'acceptation

- [ ] `GET /api/produits/page?limit=50&offset=0` renvoie
      `{rows: [...50...], total: N}`.
- [ ] `GET /api/produits/page?search=cafe` filtre **côté serveur** et
      `total` reflète le filtre.
- [ ] `GET /api/produits/page?limit=50&offset=4950` renvoie la page 100.
- [ ] `GET /api/produits` (l'ancienne route) **n'a pas changé** de
      comportement — le client mobile Android fonctionne toujours.
- [ ] `limit=0` / `limit=99999` / `offset=-5` sont **bornés** (pas de crash,
      pas de requête qui ramène tout).
- [ ] `tests/Integration/ProductApiTest.php` passe.

### Risques

- Une route `/api/produits/page` est ambiguë avec `/api/produits` : vérifier
  que le routeur les distingue bien (AltoRouter matche les routes littérales
  avant les paramètres — à confirmer par un test).

---

## R3 — Paginer le rendu de `/produits` · Priorité 1

### Problème

`app/views/produits.php:70` rend **toutes** les lignes en HTML serveur :

```php
<?php foreach ($produits as $p): ?>
  <tr data-category="..." data-shop="...">
    ... 2 430 octets par ligne ...
  </tr>
<?php endforeach; ?>
```

Mesuré sur 6 000 produits : **332,8 ms** de rendu, **13,90 Mo** de HTML,
**6 000 `<tr>`** dans le DOM.

Et le filtre (`initProductsTabs`, `public/assets/js/app.js:4289`) fait
**3 `querySelector` par ligne à chaque frappe** :

```js
const rows = $$('#products-table tr[data-category]');
rows.forEach(row => {
    const nom  = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
    const code = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
    ...
});
```

→ **18 000 parcours DOM par caractère tapé** → gel de l'interface.

### Correctif

**1. Le serveur** (`PageController::produits()`, ligne 380) :

```php
$limit  = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);
$search = $this->sanitaze($_GET['search'] ?? '');
$cat    = $_GET['category'] ?? '';

$result = $productModel->getPage(
    ['search' => $search, 'category' => $cat],
    $limit,
    $offset,
    $shopId,
    $includeInactive
);

$this->render('produits', [
    'produits'  => $result['rows'],
    'total'     => $result['total'],
    'limit'     => $limit,
    'offset'    => $offset,
    'search'    => $search,
    // ... le reste inchangé
]);
```

**2. La vue** — remplacer la `foreach` par les `$produits` déjà paginés
(le code de rendu de la ligne ne change pas) et ajouter une barre de
pagination.

⚠️ **La vue contient deux contradictions qu'il faut corriger au passage** :

- La ligne 111 : `onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"`
  inlines **tout l'objet produit** dans le HTML — 6 000 fois. Avec la
  pagination, cela devient 50 fois : correct, mais le `json_encode` doit être
  protégé (`JSON_HEX_APOS | JSON_HEX_QUOT` ou `json_encode` + `htmlspecialchars`
  sur une constante JS).
- Le filtre actuel **masque les lignes après rendu** → il affiche « aucun
  produit » avant d'avoir les données, et ne peut pas paginer.

**3. Le JS** — remplacer le filtrage DOM par un appel serveur avec debounce :

```js
// AVANT (app.js:4289) : parcours de 6 000 <tr> à chaque frappe
// APRÈS : requête serveur, 50 lignes au retour
let searchTimer = null;
filterInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const params = new URLSearchParams({
            search: filterInput.value,
            category: categoryFilter.value,
            limit: '50',
            offset: '0',
        });
        window.location.href = APP_URL + '/produits?' + params.toString();
    }, 300);
});
```

Recharger la page à chaque recherche est acceptable ici : le rendu serveur
est déjà de 2,1 ms pour 50 lignes.

### Critères d'acceptation

- [ ] `/produits` renvoie **< 200 Ko** de HTML pour 6 000 produits.
- [ ] Le DOM contient **50 `<tr>`**, pas 6 000.
- [ ] Le filtre par nom / code-barres / catégorie fonctionne et **met à jour
      le total** affiché.
- [ ] La pagination affiche « page 3 / 120 » et les boutons précédent /
      suivant / premier / dernier fonctionnent.
- [ ] Le filtre « Toutes les boutiques » (super_admin) et « Inclure
      boutiques inactives » fonctionnent toujours.
- [ ] Le bouton **Modifier** remplit le formulaire avec les bonnes valeurs
      (c'est le `editProduct(json_encode($p))` qui est touché).
- [ ] Le bouton **Supprimer** fonctionne toujours.
- [ ] Mesurer dans le navigateur : le filtre ne doit plus geler l'UI
      (DevTools → Performance → un enregistrement pendant une frappe).

### Risques

- **Le plus gros lot de travail de la liste** : touche une vue, un JS
  (243 Ko, un seul IIFE), un modèle et un contrôleur.
- Le filtre actuel et le nouveau n'ont pas exactement le même
  comportement : l'ancien filtre **après** rendu (donc visible sur des
  produits déjà chargés), le nouveau filtre **avant** rendu. Un cas particulier
  à vérifier : filtrer par catégorie puis par nom — l'ordre des filtres
  change le résultat.
- `initProductsTabs` est aussi appelé depuis `app.js:400` (`if ($('#products-table'))`)
  → il ne doit pas casser la caisse (qui a un `#products-grid`, pas un
  `#products-table`).

---

## R8 — Recherche plein-texte · **DIFFÉRER**

### Mesure (6 000 produits)

| Approche | Temps |
|---|---|
| `LIKE '%terme%'` (scan complet) | **8,0 ms** |
| `MATCH … AGAINST` (FULLTEXT) | 22,5 ms |

Le FULLTEXT est **plus lent** à ce volume : MySQL paie la préparation du
dictionnaire, qui ne se rentabilise qu'au-delà de ~50 000 lignes.

→ **Ne pas l'implémenter maintenant.** Reconsidérer si le catalogue dépasse
30 000 produits. À noter : `code_barres` est **déjà** indexé en UNIQUE —
ce chemin est à 0,2 ms.

---

## R9 — `code_barres` composite · **décision de fond, à trancher**

### Problème

`produits.code_barres` a un index **UNIQUE global** :

```sql
UNIQUE KEY `code_barres` (`code_barres`)
```

Deux boutiques ne peuvent donc **pas** partager le même code-barres. Or un
code-barres EAN est, par définition, global : un même produit vendu partout
porte le même code.

**Conséquence à 5 000+ produits :** le premier import d'un catalogue existant
échoue sur `Duplicate entry '…' for key 'code_barres'` dès que deux boutiques
partagent un article. Le projet a déjà un script
`scripts/repair_barcodes_importes.php` — le problème a donc déjà mordu.

### Correctif (à valider avant implémentation)

```sql
-- Remplace l'UNIQUE global par un composite (shop_id, code_barres).
ALTER TABLE produits DROP INDEX code_barres;
ALTER TABLE produits ADD UNIQUE KEY uk_produit_shop_code (shop_id, code_barres);
```

⚠️ **Attention** : `shop_id` est **nullable** (`DEFAULT NULL`). En MySQL, un
`NULL` dans une clé composite **permet des doublons** (NULL ≠ NULL). Il y a
aujourd'hui 1 produit sans boutique. Il faudra donc traiter ce cas
explicitement (interdire l'import sans boutique, ou utiliser une valeur
sentinel).

### Impacté

- `Product::findByBarcode()` (`app/models/Product.php:92`) — le
  `WHERE code_barres = ?` ne filtre pas par boutique → il peut renvoyer
  le produit d'une **autre** boutique.
- `ProductController::create()` (ligne 73) — vérifie l'unicité par
  code-barres seul.
- `scripts/repair_barcodes_importes.php` — répare les doublons.
- `ProductImportService` — même logique.
- Le **scanner** : `/api/produit?code_barres=` doit rester scoped à la
  boutique.

### Critères d'acceptation

- [ ] Deux boutiques peuvent créer un produit avec le **même** code-barres.
- [ ] Une boutique ne peut pas créer **deux** produits avec le même
      code-barres.
- [ ] `findByBarcode()` est scoped à la boutique de la session.
- [ ] Le scanner fonctionne sur les deux boutiques.
- [ ] `scripts/repair_barcodes_importes.php` est adapté ou devient obsolète.
- [ ] Les produits sans boutique (`shop_id IS NULL`) sont traités
      explicitement.

### Risques — **le plus élevé de la liste**

- Touche le **scanner**, la **caisse** et le **client mobile** : c'est le
  chemin de vente. Une erreur ici vend le mauvais produit ou le stock
  d'une autre boutique.
- Le traitement des `shop_id IS NULL` n'est pas trivial (contrainte UNIQUE
  avec NULL).

---

## Décisions ouvertes

Ces points changent le comportement livré. À trancher **avant** d'implémenter
le correctif concerné.

| # | Décision | Options | Recommandation |
|---|---|---|---|
| **D1** | Portée du correctif R4 : l'import doit-il voir les produits des **autres** boutiques ? | (a) Non, filtrer par `shop_id` → cohérent mais risque de `Duplicate entry` ; (b) Oui, garder le comportement actuel → 1 requête qui charge **tous** les code-barres (pas de filtre boutique) | **(a)** + dédupliquer côté import avec un préfixe de boutique si conflit. C'est le comportement attendu par un caissier. |
| **D2** | R1 : quel contrat pour `/api/produits` ? | (a) Nouvelle route paginée, l'ancienne intacte ; (b) L'ancienne devient paginée | **(a)** — ne casse pas le client mobile Android. |
| **D3** | R3 : le filtre `/produits` passe-t-il par l'URL (rechargement de page) ou en AJAX ? | (a) URL + rechargement (simple, 2,1 ms de rendu) ; (b) AJAX (plus fluide, plus de code) | **(a)** pour commencer, (b) si l'usage montre que le rechargement gêne. |
| **D4** | R3 : taille de page par défaut | 25, 50, 100 | **50** (118 Ko de HTML, 2,1 ms). |
| **D5** | R6 : gzip via `.htaccess` ou fallback applicatif dans `index.php` ? | (a) `.htaccess` seul ; (b) les deux | **(b)** — l'hébergeur peut désactiver `mod_deflate`. |
| **D6** | R9 : faut-il rendre `code_barres` composite maintenant ou plus tard ? | (a) Maintenant (dette qui bloque les imports multi-boutiques) ; (b) Plus tard | **À trancher avec l'utilisateur** — impacte le scanner et le client mobile. |

---

## Ordre d'exécution

```
Étape 1 — R4  (import)           ~1 h    indépendant, faible risque
Étape 2 — R5  (dashboard)        ~30 min indépendant, faible risque
Étape 3 — R6  (gzip)             ~15 min indépendant, faible risque
        ─── les 3 premiers sont livrables séparément ───
Étape 4 — R7  (index)            ~5 min  migration simple
Étape 5 — R2  (dénormalisation)  ~2 h    dépend de R7, risque moyen
Étape 6 — R1  (endpoint paginé)  ~1 h    dépend de R2
Étape 7 — R3  (rendu paginé)     ~1 jour dépend de R1, R2
Étape 8 — R9  (code-barres)      ~2 h    décision D6, risque élevé
```

**Chaque étape se livre seule** : un commit par étape, avec son gain mesuré
dans le message de commit.

---

## Ce qu'il ne faut PAS faire

- **Ne pas** mettre un `LIMIT` arbitraire dans `getAllFiltered()` sans le
  propager à tous les appelants : `ProductExportService`
  (`app/Services/ProductExportService.php:72`) et `PageController::dashboard`
  ont besoin de l'exhaustivité. Un `LIMIT` silencieux produirait un export
  **tronqué** — un bug fiscal, pas un bug d'affichage.
- **Ne pas** remplacer `findById` par un cache en mémoire : le stock change à
  chaque vente et le prix peut changer à tout moment. Un cache produits
  introduirait des ventes au mauvais prix.
- **Ne pas** se contenter d'ajouter un index et croire que c'est réglé : le
  gain d'index est de 232 → 194 ms. Le problème est l'absence de `LIMIT`,
  pas l'absence d'index.
- **Ne pas** mélanger placeholders nommés et positionnels dans une même
  requête (`EMULATE_PREPARES = false` → `SQLSTATE HY093`). Pour le
  `LIMIT`/`OFFSET`, utiliser des littéraux castés en `int`.
- **Ne pas** écrire dans `product_batches` en SQL direct après R2 : la
  dénormalisation divergerait silencieusement.

---

## Vérification commune à chaque étape

- [ ] `php -l` passe sur chaque fichier PHP modifié.
- [ ] `node -c` passe sur chaque fichier JS modifié.
- [ ] Les tests existants passent : `vendor/bin/phpunit` (base `pos_systeme_test`).
- [ ] `php scripts/perf_bench_catalogue.php 6000` confirme le gain annoncé.
- [ ] `git status --short` ne montre **que** les fichiers de l'étape.
- [ ] La migration est **exécutée deux fois** (idempotence) via
      `scripts/run_sql_migration.php`.
- [ ] Les montants et les stocks sont **vérifiés sur des données réelles**,
      pas seulement « une ligne est apparue ».

---

## Annexe — les mesures brutes

Source : `php scripts/perf_bench_catalogue.php 6000` (MySQL 9.1.0 local,
base jetable `pos_perf_bench`, supprimée en fin de run).

```
EXPLAIN de la requête catalogue actuelle :
    c    type=ALL    key=-              rows=9      extra=Using temporary; Using filesort
    p    type=ref    key=category_id    rows=1      extra=Using where
    t    type=ALL    key=-              rows=3      extra=Using where; Using join buffer (hash join)
    s    type=const  key=PRIMARY        rows=1      extra=Using where
    pb   type=ref    key=product_id     rows=1      extra=

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
    code-barres exact (index UNIQUE)        0,2 ms

Import de 5 000 produits :
    ACTUEL : findByBarcode par ligne        7,49 s   (25 000 requêtes)
    APRÈS  : 1 SELECT + lookup mémoire      0,01 s   (1 requête) → 786× plus rapide
```

---

## Annexe — où le catalogue est chargé

| Emplacement | Ce qu'il charge | À corriger par |
|---|---|---|
| `app.js:405` (`loadProducts`) | tout le catalogue, dans la caisse | R1 (option A : pas de changement) |
| `app.js:377` | `allProducts.find(p => p.code_barres === barcode)` — recherche linéaire | R1 (voir note) |
| `app.js:422` (`filterProducts`) | filtre sur le tableau complet | R1 |
| `app.js:4289` (`initProductsTabs`) | 3 `querySelector` × 6 000 `<tr>` par frappe | R3 |
| `PageController.php:182` | `getAll()` pour un `count()` | R5 |
| `PageController.php:394` | `getAll()` pour le rendu HTML | R3 |
| `ProductExportService.php:72` | `getAllFiltered()` — **exhaustivité requise** | ⚠️ ne pas limiter |
| `ProductImportService.php:183` | `findByBarcode()` par ligne | R4 |
| `scanner.js:448` | `/api/produit?code_barres=` — **rapide** (0,2 ms) | ✅ rien à faire |
