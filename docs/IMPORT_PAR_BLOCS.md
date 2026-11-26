# Import de produits par blocs — le crash à 17 000 produits

**Date :** 2026-11-25
**Branche :** `main`

---

## 1. Le symptôme

L'import de 50 000 produits s'arrêtait vers **17 000 produits** :
la requête HTTP était tuée en plein milieu, et les produits déjà
écrits restaient en base (sans que l'import soit terminé).

---

## 2. La cause — MESURÉE, pas devinée

⚠️ **Ce n'était PAS la mémoire.**

| Limite PHP (`C:/xampp/php/php.ini`) | Valeur | Verdict |
|---|---|---|
| `memory_limit` | 512 Mo | pic mesuré **346 Mo** → passe |
| **`max_execution_time`** | **120 s** | **→ le coupable** |

**Mesures** (base jetable, schéma réel, MySQL 9.1.0) :

| Test | Résultat |
|---|---|
| 20 000 lignes, `max_execution_time=120` | **94,9 s** — passe (211 lignes/s) |
| 50 000 lignes, `max_execution_time=120` | **💥 mort à 119,8 s** — 26 315 produits écrits, 0 erreur en base |
| 50 000 lignes, `max_execution_time=0` | passe en ~237 s |

Le débit mesuré (211 produits/s) × 50 000 = **237 s**, pour une limite
de **120 s**. Le fichier s'arrêtait donc **à la moitié**.

> ⚠️ Ce que le fichier de test laisse en base après le crash : les
> **17 830 produits** du `docs/produits_50k_import.csv` étaient déjà
> importés (sur 17 887 en base). C'est la trace du crash — pas une
> incohérence.

---

## 3. Les trois leviers (mesurés sur 5 000 produits)

| Variante | Temps | 50 000 projeté |
|---|---|---|
| 5 requêtes/produit, auto-commit (**état actuel**) | 18,1 s | **181 s** |
| 5 requêtes/produit, 1 transaction | 5,8 s | 58 s |
| 2 requêtes/produit, 1 transaction | 1,7 s | 17 s |
| + recalc groupé du stock | 2,0 s | **20 s** |

**Ce que ça change :**

1. **Une transaction par bloc** (−68 %). Chaque `INSERT` en
   auto-commit provoque un fsync : c'était le coût dominant, pas le SQL.
2. **2 requêtes au lieu de 5** par produit (−66 %). Le `SELECT shop_id`
   disparaît : le lot est inséré par un `INSERT ... SELECT` qui prend
   le `shop_id` **du produit** — la garantie « le lot porte la boutique
   de son produit » est préservée (vérifié : `lastInsertId()` correct,
   `shop_id` identique).
3. **Recalc groupé** du stock dénormalisé : un `UPDATE ... JOIN` pour
   tout le bloc (0,26 s) au lieu de 2 requêtes par produit (13 s).
   La formule est **identique** à `ProductBatch::recalculateProductStock()`.

**Résultat final mesuré (base jetable) :**

| Format | Avant | Après | Gain |
|---|---|---|---|
| CSV 50 000 | **237 s → mort à 26 000** | **43,4 s** (1 151 l/s) | **×5,5** |
| XLSX 50 000 | idem | 84,7 s (590 l/s), pic **10 Mo** | ×2,8 |

Contrôles après import (50 000/50 000, **0 erreur**) :
`lots sans shop_id = 0` · `divergence boutique = 0` ·
`écart stock = 0` · `écart available_stock = 0` ·
`écart nearest_expiration_date = 0` · `doublons = 0`.

---

## 4. Le découpage en blocs

Un bloc de 5 000 lignes ≈ **4 s** de traitement : chaque requête HTTP
reste très largement sous la limite de 120 s.

**Le protocole (2 requêtes) :**

| Étape | Route | Rôle |
|---|---|---|
| 1 | `POST /api/produit/import` (`chunked=1`) | dépose le fichier, renvoie un **jeton** + le nombre total de lignes |
| 2 | `POST /api/produit/import/chunk` | traite le bloc suivant (5 000 lignes), renvoie l'avancement |
| — | `POST /api/produit/import/abort` | abandonne (supprime le fichier déposé) |

Le fichier est **copié** dans `storage/imports/` (le fichier temporaire
de PHP disparaît à la fin de la requête) et l'état du jeton est écrit
dans `storage/import_state/`.

### ⚠️ Ce que le découpage change (et ce qu'il ne change pas)

- **Le décompte des doublons est LOCAL au bloc** : deux blocs distincts
  ne se voient pas. Le mode `create` reste sûr (c'est l'UNIQUE global
  sur `code_barres` qui rejette, pas PHP) ; seul le compteur
  « ignorés » est moins exhaustif qu'en un seul passage.
- **Le `.xlsx` est relu depuis le début** à chaque bloc (le lecteur
  natif est un flux séquentiel) : 10,3 s de relecture cumulée pour
  50 000 lignes en blocs de 5 000 (contre 50,7 s en blocs de 1 000).
  Le **CSV reste le format le plus rapide**.
- **Un bloc wholly invalide n'arrête pas l'import** : `done` ne dépend
  que de la fin du FICHIER.

---

## 5. Le modal

Pendant l'import, le modal affiche :

- la **barre de progression** (0 → 100 %) ;
- le **pourcentage** ;
- le **compteur** « 12 340 / 50 000 produits » ;
- le **temps restant** estimé (« Temps restant : ~17 s ») et le temps
  **écoulé** ;
- les **5 derniers produits traités** (par nom) — pour montrer que
  l'import avance et qu'il n'est pas planté ;
- un bouton **Abandonner** (supprime le fichier déposé).

> La barre se met à jour **au retour de chaque bloc** (le serveur ne
> peut pas pousser l'avancement) : avec un bloc de 5 000 lignes, cela
> donne un rafraîchissement toutes les ~4 s.

**Vérifié dans un vrai navigateur** (Playwright) : 20 000 produits
importés en 4 blocs, `0 CRÉÉS … 20 000 CRÉÉS · 0 ERREURS`, barre qui
passe de 25 % → 50 % → 75 %, estimation qui décroît (27 s → 17 s → 7 s),
5 derniers noms affichés.

---

## 6. Le seuil de bascule

Le client bascule en mode « par blocs » si :

- le fichier fait **≥ 512 Ko** (≈ 10 000 lignes en CSV) ; ou
- (CSV < 512 Ko) le comptage exact des lignes dépasse **12 000**.

En dessous, l'import se fait en **une seule requête** — le
comportement d'avant, inchangé.

---

## 7. Nettoyage

Les imports abandonnés (navigateur fermé, réseau coupé) laissaient
des fichiers de plusieurs Mo. Ils sont purgés :

- à chaque **nouveau dépôt** (donc à chaque import) ;
- sur deux critères : **2 h d'âge** ou **10 min d'inactivité** (chaque
  bloc rafraîchit l'horodatage, donc un import vivant le réécrit) ;
- les **états orphelins** (dont le fichier a disparu) sont supprimés
  aussi.

---

## 8. Fichiers modifiés

| Fichier | Nature |
|---|---|
| `app/Services/ProductImportService.php` | `importChunk()`, lecture par plage, transaction, recalc groupé, `INSERT ... SELECT` |
| `app/controllers/ProductImportController.php` | dépôt du fichier, route `chunk()`, route `abort()`, purge |
| `app/views/produits.php` | étape « avancement » du modal + bouton « Abandonner » |
| `public/assets/js/product-excel.js` | protocole par blocs, barre de progression, estimation du temps |
| `public/assets/css/styles.css` | styles `.pi-progress-*` (préfixés, pas de collision) |
| `routes/api.php` | 2 routes (`/chunk`, `/abort`) |
| `.gitignore` | `storage/imports/`, `storage/import_state/` |

---

## 9. Tests

**417 tests PHPUnit passent** (2 skipped, 1 dépréciation PHPUnit
préexistante).

> ⚠️ Un échec INTERMITTENT est apparu pendant la mise au point : il
> venait du **fichier de test de diagnostic** qui avait laissé 20 000
> produits en base — le test `ProductDashboardCountTest` compte les
> produits de la boutique et le compte était faux. La base a été
> nettoyée (56 produits, 0 écart) et 6 runs consécutifs sont verts.

**Vérifications complémentaires (hors PHPUnit) :**

| Vérification | Résultat |
|---|---|
| Import CSV 50 000 par blocs (base jetable) | 50 000/50 000, 0 erreur, 43,4 s |
| Import XLSX 50 000 par blocs (base jetable) | 50 000/50 000, 0 erreur, 84,7 s, pic 10 Mo |
| Équivalence des 3 colonnes dénormalisées | 0 écart (comparaison ligne à ligne avec `product_batches`) |
| Parcours HTTP réel + navigateur | 20 000 importés, barre/%/ETA/noms corrects |
| Purge des imports abandonnés | 2 fichiers purgés au dépôt suivant |
| `php -l` / `node -c` | OK sur tous les fichiers modifiés |

---

## 10. Ce qu'il RESTE à faire (mesuré, non traité)

| # | Item | Impact | Effort |
|---|---|---|---|
| 1 | **R9 — `code_barres` composite `(shop_id, code_barres)`** | 🔴 **Vente erronée inter-boutiques** (le scanner vend le produit d'une autre boutique) | ~4 h — cf. `docs/IL_RESTE_A_FAIRE.md` |
| 2 | **Les 3 migrations R7/R2/R3 sur `pos_system`** | 🔴 Bloquant : le code écrit `product_batches.shop_id`, sans la migration chaque création de lot échoue | 5 min |
| 3 | **R2 — tri par colonne sur `/produits`** | 86 ms → 0,5 ms | ~2 h |
| 4 | **R6 — `getAllSales()` bornée** | croît avec l'activité | ~3 h |
| 5 | **D2 — bouton « Modifier » : JSON inline → API** | fragilité, HTML ×50 | ~1 h |

> ⚠️ Le point 1 est **le plus important de la liste** : c'est le seul
> qui puisse **vendre le mauvais produit**. Le point 2 est un
> **préalable de déploiement** : les migrations sont écrites et
> vérifiées, mais pas appliquées sur `pos_system`.
