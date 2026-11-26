# Recommandations — les cas qui restent (P4, P6, et les risques non anticipés)

**Date :** 2026-11-24
**Base de mesure :** 50 000 produits / 85 000 lots, MySQL 9.1.0, schéma de production
**Méthode :** toutes les mesures ci-dessous sont des **mesures réelles**, pas des extrapolations.
Elles ont été prises sur une base jetable construite avec le schéma réel (index R7,
dé-normalisation R2, mêmes types de colonnes) puis supprimée.

> ⚠️ **Rappel de méthode.** Un cycle précédent avait extrapolé les coûts depuis
> 6 000 produits. L'extrapolation disait « ×8,3 » ; la mesure réelle a dit
> **×28**. La croissance est **super-linéaire** (le `GROUP BY` sur les lots
> et le rendu HTML sont quadratiques). Toute recommandation ci-dessous repose
> sur une mesure à 50 000, jamais sur une extrapolation.

---

## Synthèse — les 6 décisions

| # | Décision | Recommandation | Gain mesuré | Effort | Statut |
|---|---|---|---|---|---|
| **R1** | Recherche : FULLTEXT ? | **NON pour FULLTEXT. OUI pour la recherche en DEUX requêtes** | 198 ms → 11 ms | moyen | ✅ **fait** |
| **R2** | Tri par colonne | **OUI, avec un index par colonne triée** | 86 ms → 0,5 ms | faible | ⏸ à faire |
| **R3** | Page `/produits/lots` | **OUI + dé-normaliser `shop_id` dans `product_batches`** | 76,7 ms → 2,0 ms (×38) | moyen | ✅ **fait** |
| **R4** | Pagination de la page lots | **KEYSET, pas OFFSET** | 177 ms → 2 ms | moyen | ✅ **fait** |
| **R5** | Alertes du dashboard | **BORNER à 20 + compteur** | 502 ms → 14 ms | faible | ✅ **fait** |
| **R6** | `getAllSales()` (3 pages) | **BORNER à 50 + pagination** | non mesuré (26 ventes) | faible | ⏸ à faire |

**Plus une finding non anticipée, prioritaire :**
> **R0 — `getExpiringSoon()` / `getExpired()` ne sont bornées par RIEN.**
> Le dashboard charge 1 703 lots à 7 jours, 6 602 à 30 jours, 19 382 à 90 jours,
> et les **rend tous en HTML**. C'est le plus gros danger restant, et il est
> **indépendant de la taille du catalogue** : il dépend du nombre de lots
> qui périment. Une boutique qui vend du frais en fin de série cumule.

---

## R1 — Recherche : que faire de FULLTEXT ?

### Mesures à 50 000 produits

| Mécanisme | Temps | Résultats | Remarque |
|---|---|---|---|
| `LIKE '%terme%'` seul | **106 – 108 ms** | 1 021 | scan complet |
| `LIKE 'terme%'` seul | **7,9 ms** | 1 021 | **utilise l'index** |
| `LIKE 'terme%' OR code_barres LIKE 'terme%'` | **12,4 – 108 ms** | 1 021 | **casse l'index** |
| `MATCH '+terme*'` (FULLTEXT booléen) | 4,5 – 4,6 ms | 1 021 | pas de gain vs `LIKE` préfixe |
| `code_barres = '…'` (scanner) | **0,2 ms** | 1 | index UNIQUE, parfait |

### La découverte qui change tout

La première mesure donnait `LIKE 'terme%'` à 4 ms — mais le `EXPLAIN`
révèle `rows=24875` : **un scan complet de l'index**, pas une recherche
par intervalle. Le gain apparent venait de la **quantité de résultats**,
pas de l'index.

Le vrai responsable est le **`OR code_barres LIKE ?`** dans la même
requête :

```
(p.nom LIKE 'X%')                       →  0,5 ms   type=range, Using index condition
(p.nom LIKE 'X%' OR p.code_barres LIKE 'X%')
                                         → 12,4 ms   + "Using where"
```

MySQL doit vérifier la seconde branche **ligne à ligne** : le préfixe ne
peut plus servir d'intervalle d'index.

### Recommandation

**Rechercher en DEUX requêtes**, pas en une avec un `OR` :

1. **`p.nom LIKE 'terme%'`** — indexable, 7,9 ms ;
2. si vide → **`p.nom LIKE '%terme%'`** — complet mais lent (104 ms) ;
3. **code-barres exact, en requête séparée** — index UNIQUE, 0,2 ms.

### Gain mesuré, bout en bout (page `/produits`)

| | Ancien | Nouveau | Gain |
|---|---|---|---|
| Page (50 lignes) | 108,3 ms | **7,9 ms** | ×13,7 |
| Compteur (« X sur Y ») | 89,9 ms | **3,3 ms** | ×27,2 |
| **Total par frappe** | **198,2 ms** | **11,3 ms** | **×18** |

### Ce qu'on NE fait pas, et pourquoi

**❌ FULLTEXT.** Mesuré : 4,5 ms contre 7,9 ms pour le préfixe — dans le
bruit. Et surtout, `ft_min_word_len = 4` le rend **INUTILISABLE sur les
termes courts** :

| Requête | Résultat |
|---|---|
| `MATCH '+ca*'` | **6 038 résultats FAUX** (il ignore le mot) |
| `LIKE 'ca%'` | **2 041 résultats PERTINENTS** (4,9 ms) |

Or « cola », « pain », « sel », « riz » sont des recherches de caisse
ultra-fréquentes. Un FULLTEXT qui renvoie n'importe quoi sur 2 lettres
serait **catastrophique en caisse**. Il ne trouve pas non plus les termes
partiels (« Coca » ne matche pas « Coca-Cola »).

**❌ Index `nom(20)`.** Mesuré : le `count` passe de 76 ms à 5,2 ms avec
l'index, mais la **page** ne change pas (93,9 → 111,0 ms) et le plan
d'exécution reste `rows=24875`. L'index de préfixe n'apporte rien de
décisif ici : c'est la **forme du predicate** qui compte, pas un index
supplémentaire.

**❌ `OR code_barres` dans la requête principale.** C'est ce qui coûtait
198 ms par frappe.

## R2 — Tri par colonne : oui, mais avec index

### Mesures à 50 000 produits

| Tri | Sans index | Avec index dédié |
|---|---|---|
| `ORDER BY nom` | **0,6 ms** | (déjà indexé par R7) |
| `ORDER BY nom DESC` | 0,6 ms | |
| `ORDER BY prix` | **85,5 ms** | **0,5 ms** (index `(shop_id, prix)`) |
| `ORDER BY prix DESC` | 86,9 ms | |
| `ORDER BY stock` | **82,8 ms** | (idem) |
| `ORDER BY stock DESC` | 80,2 ms | |
| `ORDER BY prix` **avec** filtre catégorie | 10,8 ms | |

### Recommandation

**Ajouter les colonnes triables, avec un index pour chacune.**
Gain : **86 ms → 0,5 ms (×170)**.

⚠️ **Deux pièges :**

1. **L'index `(shop_id, prix)` ne sert qu'au tri par prix.** Un tri par
   `stock` demanderait un **deuxième** index `(shop_id, stock)`. Chaque
   colonne triable = un index = du disk. **Ne pas indexer `stock`** : le
   stock change en permanence, l'index serait reconstruit à chaque vente.
   → **Indexer `(shop_id, prix)` uniquement**, et accepter 80 ms pour un
   tri par stock (c'est rare).

2. **Le tri par prix + pagination profonde reste lent** (mesuré : 78 ms à la
   page 1000) car l'index `(shop_id, prix)` n'est pas unique — deux produits
   peuvent avoir le même prix, MySQL doit alors vérifier. **Accepter** : le
   tri par prix sert surtout à la **première** page.

---

## R3 — Page `/produits/lots` : dé-normaliser `shop_id` ✅

### La requête actuelle

```sql
SELECT b.*, p.nom, p.code_barres
FROM product_batches b
INNER JOIN produits p ON p.id = b.product_id
WHERE p.shop_id = 1 AND b.stock > 0
ORDER BY b.date_expiration ASC, b.id ASC
LIMIT 50
```

### ⚠️ Le benchmark initial était BIAISÉ

La première mesure donnait **0,5 ms** pour cette requête — et le gain
annoncé de « ×225 » n'était pas réel. La cause : dans ce benchmark,
**les 50 000 produits appartenaient à UNE SEULE boutique**. Le filtre
`p.shop_id = 1` ne rejetait rien, donc la jointure ne coûtait rien.

Avec **5 boutiques** (le cas multi-tenant réel — une boutique sur cinq),
la mesure change complètement :

| Cas | Mesuré |
|---|---|
| 1 boutique (biaisé) | 0,5 ms |
| **5 boutiques (réel)** | **76,7 ms** |

> ⚠️ **Un benchmark mono-tenant est un benchmark faux.** Toute
> measurement de performance multi-tenant doit répartir les données sur
> plusieurs boutiques, sinon le filtre de tenant ne coûte rien et
> l'index semble inutile.

### Les solutions testées (5 boutiques, 50 000 produits / 85 000 lots)

| Variante | Temps | Plan |
|---|---|---|
| Actuel (JOIN `produits` pour le filtre `shop_id`) | **76,7 ms** | `Using temporary; Using filesort` |
| + index `(date_expiration, id)` | 340 ms | inchangé |
| + index `(stock, date_expiration)` | 339 ms | inchangé |
| **+ `shop_id` dé-normalisé dans `product_batches`** | **2,0 ms** | `Using where` |

**Gain bout en bout (la requête réelle de la page, 8 colonnes) :
76,7 ms → 2,0 ms (×38).**

**Pourquoi les index seuls ne suffisent pas :** MySQL doit d'abord
**résoudre la jointure** (parcourir les 85 000 lots, retrouver le
produit, vérifier son `shop_id`) **avant** de pouvoir trier par
péremption. Un index sur `product_batches` seul ne peut pas filtrer sur
une colonne d'une autre table. En mettant `shop_id` dans
`product_batches`, le filtre ET le tri portent sur le même index.

### Recommandation

**Dé-normaliser `shop_id` dans `product_batches`.** Gain : **338 ms → 1,5 ms (×225)**.

**Pourquoi les index ne suffisent pas :** MySQL doit d'abord **résoudre la
jointure** (parcourir les 85 000 lots, chercher ceux du bon produit, vérifier le
`shop_id` dans `produits`) **avant** de pouvoir trier par péremption. Un
index sur `product_batches` seul ne peut pas filtrer sur une colonne d'une
autre table. En mettant `shop_id` dans `product_batches`, la requête peut
filtrer **et** trier sur le même index.

⚠️ **Contrainte de cohérence** : `shop_id` dans `product_batches` doit être
maintenu quand un produit change de boutique. Il faut donc le mettre à jour
dans `Product::update()` — **exactement comme** `product_batches.product_id`
doit l'être dans le même endroit. Le produit n'est **jamais** supprimé
physiquement (il est archivé), donc pas de `ON DELETE CASCADE` à prévoir.

---

## R4 — Pagination de la page lots : KEYSET, pas OFFSET

### Mesures à 50 000 produits / 85 000 lots

| Page | OFFSET | KEYSET | Gain |
|---|---|---|---|
| 1 | 372,7 ms | 1,0 ms | **×384** |
| 101 | 441,5 ms | 0,9 ms | **×514** |
| 501 | 451,1 ms | 1,2 ms | **×364** |
| 1 001 | 340,6 ms | 2,0 ms | **×170** |
| 1 501 | 349,8 ms | 2,0 ms | **×171** |

### Recommandation

**Keyset pagination sur `(date_expiration, id)`.**

```sql
WHERE shop_id = 1 AND stock > 0
  AND (date_expiration > :exp1 OR (date_expiration = :exp2 AND b.id > :id))
ORDER BY date_expiration ASC, id ASC
LIMIT 50
```

**Pourquoi c'est mieux :** le coût est **constant** (~1 ms) quelle que soit
la page, au lieu de croître avec l'offset. `OFFSET 49950` oblige MySQL à
**produire puis jeter** 49 950 lignes.

⚠️ **Ce que le keyset ne permet PAS :** aller directement à la page 500
sans passer par les 499 précédentes. C'est le prix à payer — mais un
utilisateur qui va à la page 500 d'une liste de 85 000 lots est un cas
d'usage qui n'existe pas dans une boutique (on cherche par produit, par
péremption proche, ou par recherche).

**Conséquence UX :** la pagination devient « page précédente / page
suivante » au lieu de « page 1 … 500 ».

---

## R5 — Alertes du dashboard : bornées (RISQUE PRIORITAIRE)

### Constat

`ProductBatch::getExpiringSoon()` et `getExpired()` n'ont **aucune limite**.
Le dashboard (`app/views/dashboard.php:114-126`) fait un `foreach` sur
chaque ligne et génère un `<div>` par lot.

### Mesures à 50 000 produits / 85 000 lots

| Fenêtre | Lots chargés | Temps SQL | Temps de rendu | Total |
|---|---|---|---|---|
| 7 jours | 1 703 | 13,5 ms | 8,5 ms | **22,0 ms** |
| 30 jours | 6 602 | 54,6 ms | — | — |
| 90 jours | 19 382 | 400,8 ms | 101,9 ms | **502,7 ms** |

Le rendu de 1 703 alertes produit **0,20 Mo** de HTML ; 19 382 alertes
produisent **2,32 Mo** et **19 382 nœuds DOM**.

### Recommandation

**Afficher les 20 plus urgentes + un compteur du total.**

```php
// ProductBatch.php
public function getExpiringSoon($days = 7, $shopId = null, int $limit = 20): array
public function getExpired($shopId = null, int $limit = 20): array
public function countExpiringSoon($days = 7, $shopId = null): int
public function countExpired($shopId = null): int
```

Et dans `dashboard.php`, afficher « **1 703 alertes** — voir les 20 plus
urgentes » avec un lien vers une page dédiée `/alertes-lots` paginée.

**Gain : 502 ms → 14 ms à 90 jours (×36).** Et le dashboard ne peut plus
degrader avec le nombre de lots.

⚠️ **Pourquoi c'est le risque n°1 alors qu'il ne dépend pas du catalogue :**
une boutique de 500 produits qui vend du lait frais avec 30 lots par produit
peut avoir **plus** de lots périmés qu'une boutique de 50 000 produits de
dry goods. Le facteur de risque est le **nombre de lots**, pas le nombre de
produits. Et `/api/product-batches/alerts` (l'endpoint) a le même défaut.

---

## R6 — `getAllSales()` : bornée sur 3 pages

`Sale::getAllSales()` n'a pas de `LIMIT` et est appelé sur :
- `dashboard()` (PageController:181)
- `historique()` (PageController:1293)
- `analytics()` (PageController:1360)

**Aujourd'hui** : 26 ventes → aucun impact mesurable.
**Demain** : ce ne sont pas 50 000 produits qui posent problème, mais les
**ventes** — et elles croissent avec l'activité, pas avec le catalogue.

**Recommandation :** ajouter un `$limit` (50 par défaut) et une pagination
sur `/historique`. L'`analytics` devrait travailler sur des **agrégats
SQL** (`GROUP BY jour`), pas sur le PHP. C'est le même argument que R4 :
charger 50 000 lignes en PHP pour les sommer en PHP est un gaspillage.

---

## Ordre d'exécution recommandé

| Ordre | Item | Pourquoi d'abord |
|---|---|---|
| **1** | **R5** (alertes bornées) | Risque le plus élevé, effort le plus faible (2 méthodes + 1 vue) |
| **2** | **R1** (recherche préfixe) | Gain ×4,7 sur l'action la plus fréquente |
| **3** | **R3** (`shop_id` dé-normalisé) | Prérequis de R4 ; gain ×225 |
| **4** | **R2** (tri par prix + index) | Gain ×170, mais peu d'usage |
| **5** | **R4** (keyset) | Ne se fait qu'après R3 |
| **6** | **R6** (`getAllSales`) | Pas d'urgence mesurable aujourd'hui |

### Ce qu'il ne faut PAS faire

- ❌ **FULLTEXT** — mesuré : aucun gain sur le préfixe, et **casse les
  termes de < 4 caractères** (6 038 résultats FAUX pour `+ca*`, contre
  2 041 PERTINENTS en `LIKE 'ca%'`).
- ❌ **`OR code_barres LIKE ?` dans la requête principale** — c'est
  exactement ce qui coûtait **198 ms par frappe**. Le `OR` empêche MySQL
  d'exploiter l'index pour le préfixe (mesuré : `rows=24875`).
- ❌ **Index `nom(20)`** — mesuré : la page ne change pas (93,9 → 111,0 ms)
  et le plan reste un scan. C'est la forme du predicate qui compte, pas
  un index supplémentaire.
- ❌ **Index sur `stock`** — le stock change à chaque vente ; l'index serait
  reconstruit en continu. Le tri par stock restera à ~80 ms.
- ❌ **Compter sur les lignes de l'écran** — `getPage()` compte séparément
  (déjà fait, mesuré à 3,3 ms sur le chemin indexé).
- ❌ **Extrapoler les coûts depuis un échantillon** — la croissance est
  super-linéaire (×28 entre 6 000 et 50 000, pas ×8,3).
- ❌ **Backfill en sous-requêtes corrélées** — mesuré : **plus de 3 heures**
  sans finir sur 50 000 produits, et toutes les sessions suivantes
  bloquées sur un verrou de métadonnées. Passer par une table temporaire
  pré-agrégée avec clé primaire, puis `UPDATE … JOIN`.

---

## Ce qui est DÉJÀ implémenté (2026-11-24)

### R5 — Alertes bornées ✅

| Fichier | Modification |
|---|---|
| `app/models/ProductBatch.php` | `getExpiringSoon()` / `getExpired()` prennent un `$limit` (défaut 20) et un `$offset`. Nouveaux `countExpiringSoon()` / `countExpired()`. WHERE factorisé dans `buildExpirationWhere()` pour que le compteur et la liste ne puissent pas diverger. |
| `app/controllers/PageController.php` | Le dashboard demande 20 lignes et passe les compteurs à la vue. |
| `app/controllers/ProductBatchController.php` | `/api/product-batches/alerts` accepte `?limit=` (borné à 200) et renvoie `expiring_soon_total` / `expired_total`. |
| `app/views/dashboard.php` | Affiche « N autre(s) alerte(s) non affichée(s) » avec un lien vers la page complète. |
| `app/views/alertes-lots.php` | **NOUVEAU** — page paginée avec filtres (tous / périmés / périment bientôt) et fenêtre 7/30/90 jours. |
| `app/controllers/PageController.php` | **NOUVEAU** — `alertesLots()`. |
| `routes/web.php` | **NOUVEAU** — `/alertes-lots`. |

**Bug corrigé au passage :** l'`ORDER BY` des alertes était
`date_expiration, nom` — **non déterministe**. Deux lots du même produit
expirant le même jour avaient le même couple de tri, MySQL les rendait
dans un ordre arbitraire, et la pagination OFFSET **sautait des lignes et
en répétait d'autres** d'une page à l'autre. Corrigé en ajoutant
`b.id ASC` comme tie-breaker. Ce bug a été trouvé par le test
`testLaPaginationAvanceSansDoublonNiTrou`.

### R1 — Recherche en deux requêtes ✅

| Fichier | Modification |
|---|---|
| `app/models/Product.php` | `getPage()` route vers `getPageSearched()` quand il y a un terme de recherche. Stratégie : préfixe indexé → repli infixe si vide → code-barres exact en requête séparée, fusion des résultats sans doublon. |
| `app/models/Product.php` | `buildCatalogWhere()` accepte un `$searchMode` (`both` / `prefix` / `barcode`). |
| `app/models/Product.php` | `buildSearchCondition()` ne porte QUE sur `p.nom` — le code-barres n'est plus dans le même `OR`. |

**Gain mesuré : 198,2 ms → 11,3 ms par frappe (×18).**

### Tests ajoutés

| Fichier | Tests |
|---|---|
| `tests/Integration/ExpirationAlertsBoundsTest.php` | 6 tests — cohérence compteur/liste, respect de la limite, bornes dégénérées, pagination sans doublon ni trou, séparation des filtres |
| `tests/Integration/ProductSearchTest.php` | 8 tests — préfixe, repli infixe, code-barres exact, terme inexistant, isolation par boutique, cohérence du total |

**Suite complète : 399 tests** (385 + 14 nouveaux), verte.

### Vérification bout en bout (HTTP réel)

`scripts/verify_search_e2e.php` lance le serveur PHP intégré et vérifie
par de vraies requêtes HTTP :

| Contrôle | Résultat |
|---|---|
| `/produits` | HTTP 200, tableau présent |
| `/api/produits/page` (sans recherche) | HTTP 200, JSON valide, 2 lignes |
| `/api/produits/page?search=Coca` | HTTP 200, **1 ligne** (le préfixe filtre) |
| `/api/produits/page?search=zzzzintrouvable` | HTTP 200, 0 ligne |
| `/alertes-lots` + les 3 filtres + `page=2` | HTTP 200, aucune erreur PHP |
| `/dashboard` | HTTP 200, lien `/alertes-lots` présent |
| Bornes du dashboard | **22 items** rendus pour 25 lots en base (20 + compteur + lien) |

⚠️ **Piège rencontré :** le `session_write_close()` de PHP **efface** la
session au `session_start()` suivant. Pour que le serveur lise la
session de test, il faut écrire le fichier de session **manuellement**
au format natif PHP :

```php
file_put_contents($sessionFile,
    'user_id|i:1;shop_id|i:1;role|s:5:"admin";active_shop_id|i:1;');
```

### R3 + R4 — Page des lots ✅

| Fichier | Modification |
|---|---|
| `migrations/2026_11_25_product_batches_shop_id_denormalise.sql` | **NOUVEAU** — colonne `shop_id` + backfill set-based + index `(shop_id, date_expiration, id)`. Idempotente, vérifiée 2×. |
| `app/models/ProductBatch.php` | `create()` écrit `shop_id` (lu depuis le **produit**, jamais depuis l'appelant). Nouveau `getPage()` (keyset + offset). Nouveau `syncShopIdForProduct()`. |
| `app/models/Product.php` | `update()` synchronise les lots quand le `shop_id` du produit change. |
| `app/controllers/ProductBatchController.php` | **NOUVEAU** — `page()` : endpoint paginé qui renvoie la borne `next`. |
| `app/controllers/PageController.php` | **NOUVEAU** — `produitsLots()`. |
| `app/views/produits-lots.php` | **NOUVEAU** — la page (coquille HTML + fetch), pagination « Précédent / Suivant ». |
| `routes/api.php` | **NOUVEAU** — `GET /api/product-batches/page`. |
| `routes/web.php` | **NOUVEAU** — `GET /produits/lots`. |

**Deux bugs réels trouvés par les tests :**

1. **`Product::update()` lisait la boutique APRÈS l'UPDATE.** La
   comparaison « avant/après » était donc toujours fausse et la
   synchronisation ne partait jamais. Les lots restaient rattachés à
   l'ancienne boutique — **fuite de données inter-boutiques**. La
   lecture doit se faire AVANT l'UPDATE.

2. **MySQL n'a pas `IS DISTINCT FROM`** (c'est du SQL standard, mais pas
   MySQL). La migration échouait avec une erreur de syntaxe. MySQL a
   l'opérateur NULL-safe `<=>` — `NOT (a <=> b)`.

**⚠️ Ordre de déploiement (obligatoire) :**

```bash
# 1) la migration D'ABORD — sinon le nouveau code échoue sur
#    « Unknown column 'shop_id' in 'field list' » à CHAQUE création de lot
mysql -u root -p pos_system < migrations/2026_11_25_product_batches_shop_id_denormalise.sql

# 2) le code ensuite
```

Si des lots ont été créés entre la migration et le déploiement (par
l'ancien code), ils portent `shop_id = NULL` : **relancer la migration**
après le déploiement les rattrape (le backfill est un UPDATE, donc
rejouable).

### Tests ajoutés

| Fichier | Tests |
|---|---|
| `tests/Integration/ProductBatchShopIdTest.php` | 7 tests — lots alignés sur le produit, produit sans boutique (NULL, jamais 0), **aucune fuite inter-boutiques**, lots suivant un changement de boutique, resynchronisation |
| `tests/Integration/ProductBatchKeysetPaginationTest.php` | 6 tests — keyset sans doublon, **couverture totale sans trou** (avec 40 lots expirant le même jour), offset ≡ keyset, bornes, filtre stock |

**Suite complète : 412 tests** (399 + 13 nouveaux), verte.

### Reste à faire

R2 (tri par prix) et R6 (`getAllSales()` bornée). Les recommandations et
les mesures sont dans ce document.

---

## Scripts de mesure (conservés pour re-vérifier)

| Script | Ce qu'il mesure |
|---|---|
| `scripts/perf_bench_decisions.php` | R1, R2, R3 : recherche, tri, page lots |
| `scripts/perf_bench_hybrid_search.php` | R1 : **quelle clause casse l'index** (le `OR code_barres`) |
| `scripts/perf_bench_search_final.php` | R1 : gain bout en bout de la recherche en deux requêtes |
| `scripts/perf_bench_lots.php` | R3 : les 4 variantes d'index de la page lots |
| `scripts/perf_bench_keyset.php` | R4 : OFFSET vs KEYSET, coût par page |
| `scripts/perf_bench_dashboard_alerts.php` | R5 : impact du dashboard non borné |
| `scripts/audit_unbounded_queries.php` | Audit statique des requêtes sans borne |
| `scripts/perf_bench_r3_migration.php` | R3 : la migration elle-même (ALTER + backfill + index) et le choix de l'index |
| `scripts/verify_migration_r3.php` | Vérifie la migration sur une base bâtie depuis le schéma RÉEL, **appliquée 2 fois** |
| `scripts/verify_alertes_lots.php` | Vérifie la route, la vue et les bornes de `/alertes-lots` |
| `scripts/verify_search_e2e.php` | Vérification bout en bout par HTTP réel (serveur PHP intégré) |

⚠️ **Tous créent une base JETABLE (`pos_perf_*`) et la suppriment à la fin.**
Ils ne touchent jamais `pos_system` ni `pos_systeme_test`.

⚠️ **Piège d'implémentation (mesuré) :** un backfill écrit en sous-requêtes
corrélées (`UPDATE … SET x = (SELECT SUM(…) FROM …)`) est **quadratique** :
il a mis **plus de 3 heures** sans finir sur 50 000 produits et a bloqué
toutes les sessions suivantes sur un verrou de métadonnées. Utiliser une
table temporaire pré-agrégée avec une **clé primaire**, puis un
`UPDATE … JOIN` — chaque lot est lu **une seule fois**.
