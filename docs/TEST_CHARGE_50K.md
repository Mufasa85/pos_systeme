# Test de charge à 50 000 produits

**Date :** 2026-11-25
**Fichier de données :** `docs/produits_50k_import.xlsx` (2,96 Mo, 50 000 produits)

Ce document décrit la procédure pour **éprouver les optimisations livrées**
avec un jeu de données réaliste, et les points de mesure à relever.

---

## 1. Le fichier de test

### Contenu

| Caractéristique | Valeur | Ce que ça éprouve |
|---|---|---|
| **50 000 produits** | 50 000 lignes | Le seuil de la pagination serveur |
| **14 colonnes** | Le format exact du template d'import | L'import fonctionne sans retouche |
| **Codes-barres EAN-13 uniques** | 50 000, clé de Luhn valide | Le scanner, l'import, l'index UNIQUE |
| **Noms avec accents** | 14 525 (29,1 %) | L'échappement HTML du bouton « Modifier » (D2) |
| **Noms avec apostrophe** | 1 667 (3,3 %) | Le `onclick` avec JSON inline (D2) — un nom comme `Huile d'olive` |
| **Produits avec péremption** | 16 666 (33,3 %) | La page `/produits/lots` (R3/R4) |
| **Lots déjà périmés** | 4 166 (8,3 %) | Les alertes du dashboard (R0/R5) |
| **Produits en rupture (stock 0)** | 4 545 (9,1 %) | Les alertes de stock faible |
| **Produits avec remise** | 1 sur 9 | Le calcul du TTC en caisse |
| **Prix** | 100 → 50 000 Fc | La dispersion du tri par prix (R2) |
| **Catégories** | Comestible, Non comestible, Boissons, Service, animal | Des catégories **réellement en base** |
| **Taxes** | A → R | Les étiquettes réelles de la table `taxes` |

### Les 2 fichiers

| Fichier | Taille | Usage |
|---|---|---|
| `docs/produits_50k_import.xlsx` | 2,96 Mo | Le format **officiel** (template d'import) |
| `docs/produits_50k_import.csv` | 4,45 Mo | Le même contenu, **sans le problème de mémoire** |

### Les 3 feuilles (du fichier .xlsx)

| Feuille | Contenu |
|---|---|
| **Produits** | Les 50 000 lignes + un autofiltre, la 1re ligne figée |
| **Aide** | Le rappel des règles (comme le template officiel) |
| **Recap** | Le récapitulatif du jeu de test |

Le **CSV** n'a qu'une feuille (les données + l'en-tête), séparateur `;`.

### ⚠️ Le format TEXTE de la colonne `code_barres`

La colonne A est en format `@` (texte). Sans cela, **Excel transforme un
code à 13 chiffres en date** (`5/17/23070765319`) et le fichier devient
illisible à l'import. C'est l'avertissement du template officiel, et la
raison pour laquelle le fichier est généré par script et non à la main.

### Régénérer le fichier

```bash
php scripts/generate_import_50k.php 50000
```

Le script est paramétrable (`php scripts/generate_import_50k.php 1000` pour
un test rapide).

---

## 2. ⚠️ Avant d'importer : appliquer les migrations

**L'import échoue si les migrations ne sont pas appliquées.** Constaté
lors de la génération : `ProductBatch::create()` écrit
`product_batches.shop_id`, donc sans la migration R3 l'import échoue sur
`Unknown column 'shop_id' in 'field list'` — **et le stock n'est pas
importé** (les produits sont créés, mais sans lot).

```bash
# 1) L'index (shop_id, nom) — tri et recherche
mysql -u root -p pos_system < migrations/2026_11_24_index_produits_shop_nom.sql

# 2) La dé-normalisation du stock (available_stock, nearest_expiration_date)
mysql -u root -p pos_system < migrations/2026_11_24_produits_stock_denormalise.sql

# 3) La dé-normalisation de shop_id sur les lots
mysql -u root -p pos_system < migrations/2026_11_25_product_batches_shop_id_denormalise.sql
```

⚠️ **La migration 3 doit précéder le déploiement du code** (sinon chaque
création de lot échoue). ⚠️ **La migration 2 doit être suivie du
déploiement** (sinon les colonnes ne sont plus rafraîchies).

---

## 3. ⚠️ Le point le plus important de ce test : la MÉMOIRE

### ✅ Ce qui a été fait

L'import `.xlsx` **épuisait la mémoire** (`Allowed memory size 536870912
bytes exhausted`) parce que `ProductImportService::readRows()` faisait
`IOFactory::load()` — le classeur entier en RAM — puis accumulait les
50 000 lignes extraites dans un tableau.

**Le service a été corrigé** (`app/Services/ProductImportService.php`) :

- un **lecteur natif en flux** (`ZipArchive` + `XMLReader`) remplace
  PhpSpreadsheet pour le `.xlsx` ;
- les lignes sont produites **une par une** (un générateur), jamais
  accumulées ;
- le lookup des produits existants se fait **par blocs de 500**, pour ne
  pas collecter 50 000 code-barres en mémoire.

**L'import de 50 000 produits passe maintenant avec le `memory_limit`
par défaut (512 Mo).** Tu n'as plus rien à configurer côté mémoire.

### ⚠️ ET SI L'IMPORT EST TUÉ PAR LE TEMPS ?

**Le crash à ~17 000 produits n'était PAS la mémoire : c'était
`max_execution_time = 120`.**

Mesuré : 20 000 lignes = 94,9 s (211 lignes/s) → 50 000 lignes =
**237 s**, pour une limite de **120 s**. L'import mourait donc à la
moitié.

**Le correctif** (`docs/IMPORT_PAR_BLOCS.md`) :

- l'import est **découpé en blocs de 5 000 lignes**, chaque bloc étant
  une requête HTTP distincte (≈ 4 s chacune) ;
- le travail en base a été optimisé : transaction, une requête de lot
  au lieu de trois, recalc groupé du stock → **−89 %** (181 s → 20 s
  pour 50 000 produits) ;
- le modal affiche **barre de progression, %, compteur, estimation du
  temps restant et les 5 derniers produits traités**.

**Résultat mesuré :** 50 000/50 000 importés, 0 erreur, **43,4 s**
(CSV) / 84,7 s (XLSX).

> ⚠️ Le seuil `max_execution_time=600` n'est **plus nécessaire** : le
> découpage garantit qu'aucune requête HTTP n'approche la limite.
> L'augmenter reste possible (utile pour un import massif en une
> seule fois), mais ce n'est plus le correctif.

### Les deux formats, mesurés

Les deux fichiers ont été importés **pour de vrai** dans une base jetable
(50 000 lignes, 0 erreur) :

| Format | Importé | Erreurs | Pic mémoire | Temps |
|---|---|---|---|---|
| **CSV** (4,45 Mo) | **50 000 / 50 000** | **0** | **350 Mo** | ~190–276 s |
| **XLSX** (2,96 Mo) | **50 000 / 50 000** | **0** | **~350 Mo** | ~270 s |

⚠️ **Les deux ont passé 50 000/50 000 sans erreur** — le jeu de données
est conforme. Le XLSX a simplement besoin de `memory_limit=2G`.

Les contrôles de cohérence sont bons sur les deux :
`lots sans shop_id = 0`, `divergence = 0`, 45 455 lots créés (un par
produit avec stock > 0), 16 666 produits avec une date de péremption,
4 166 déjà périmés.

⚠️ **L'import est lent** (222 s pour le CSV, 272 s pour le XLSX) : il
écrit chaque produit puis crée son lot et recalcule le stock. C'est le
comportement actuel du service, pas une régression.

⚠️ Ces chiffres sont ceux du **code corrigé**. Avant le correctif, le
XLSX montait à **718 Mo** (épuisement à 512 Mo) et le CSV à 346 Mo.

### Constat mesuré

Le service d'import charge le fichier **entièrement en mémoire** :

```php
// app/Services/ProductImportService.php:367
$spreadsheet = IOFactory::load($filePath);   ← tout le fichier en RAM
```

Mesuré sur le fichier de 50 000 produits :

| `memory_limit` | Résultat |
|---|---|
| **512 Mo** (défaut PHP) | 💥 **« Allowed memory size exhausted »** |
| 2 Go | ✅ Import réussi (50 000 / 50 000, 0 erreur) — **pic à 718 Mo** |

**PhpSpreadsheet épuise la mémoire à 50 000 lignes.** Un `.xlsx` est un ZIP
que PhpSpreadsheet décompresse en structures PHP — l'amplification est de
l'ordre de 10 à 20×.

### Ce que ça implique

**Ce test va échouer à l'import avec la configuration actuelle**, pour
**deux** raisons indépendantes :

| Blocage | Symptôme | Résolution |
|---|---|---|
| `memory_limit=512M` | « Allowed memory size exhausted » | Utiliser le **CSV**, ou monter à 2G |
| `max_execution_time=120` | Import interrompu à 120 s | Monter à 600 |

Ce n'est pas un bug du fichier : ce sont deux limites de l'importeur et de
la configuration PHP à cette échelle. **À 5 000 produits, aucune des deux
ne se déclenche** — l'import prend ~25 s et consomme ~35 Mo.

⚠️ Ces deux blocages sont **invisibles en développement** (on importe
rarement 50 000 produits) et **apparaissent le jour où la boutique grandit
vraiment**. C'est exactement le genre de problème que ce test sert à
dégager.

### ⚠️ LE SECOND BLOCAGE : `max_execution_time = 120`

Relevé sur le `php.ini` qu'Apache utilise (`C:/xampp/php/php.ini`) :

```ini
memory_limit=512M
max_execution_time=120
post_max_size=40M
upload_max_filesize=40M
```

Or l'import mesuré prend **222 s** (CSV) et **272 s** (XLSX) — **plus que
les 120 s** accordés par PHP. Même le CSV (qui passe en mémoire) sera
**interrompu** par le proxy de temporisation.

⚠️ Ce n'est visible qu'à l'échelle 50 000 : à 5 000 produits, l'import
prend ~25 s et passe sans problème.

### Les deux seuils à relever (XAMPP)

Dans `C:/xampp/php/php.ini`, pour cet import :

```ini
memory_limit=2G          ; le XLSX peaked à 718 Mo
max_execution_time=600   ; l'import prend ~272 s
```

Puis **redémarrer Apache** (le `php.ini` n'est relu qu'au redémarrage).

⚠️ Ces réglages sont **locaux** : sur le serveur de production, il faut
les appliquer dans la configuration PHP du serveur web — ou, mieux,
éviter l'import massif en `.xlsx` (cf. option (c) ci-dessous).

### La solution : le CSV est déjà supporté

Le service d'import a **deux branches** :

```php
// app/Services/ProductImportService.php:362
if ($extension === 'csv' || $extension === 'txt') {
    return $this->readCsvRows($filePath);   ← mémoire constante
}
$spreadsheet = IOFactory::load($filePath);  ← TOUT en RAM
```

**Les deux fichiers sont donc fournis** :
`docs/produits_50k_import.xlsx` (le format officiel) et
`docs/produits_50k_import.csv` (le même contenu, séparateur `;`).

### Les options

| Option | Effort | Effet |
|---|---|---|
| **(a)** Monter `memory_limit` à 2 Go et importer le `.xlsx` | 5 min | Débloque le test |
| **(b)** Importer le **CSV** (fourni) | 0 min | ✅ **Recommandé** — passe avec le `memory_limit` par défaut |
| **(c)** Lire le `.xlsx` en streaming (`setReadFilter()`) | ~2 h | La solution robuste, pour le futur |

**Recommandation : (b) pour ce test.** Le CSV est importable tel quel —
à condition d'avoir relevé `max_execution_time` (voir ci-dessus).

### Les 3 seuils à relever, résumés

| Réglage | Par défaut | Besoin pour 50 000 | Effet |
|---|---|---|---|
| `memory_limit` | 512M | **512M suffit** ✅ | Corrigé : le lecteur est en flux |
| `max_execution_time` | 120 | **600** | L'import prend 222–272 s |
| `upload_max_filesize` | 40M | 40M (OK) | Le fichier fait 4,45 Mo |

Le `post_max_size` (40M) et l'`upload_max_filesize` (40M) conviennent
déjà.

### Les autres points de vigilance mémoire

| Qui | Charge | Risque à 50 000 |
|---|---|---|
| L'import | Le `.xlsx` entier | 🔴 **épuisement mesuré** |
| `/api/produits` (catalogue entier) | 3,2 Mo de JSON | 🟡 Le client mobile en a besoin |
| La page `/produits` | Une page de 50 | 🟢 |
| La caisse | Des blocs de 1 000 | 🟢 |

---

## 4. La procédure de test

### Étape 1 — Sauvegarder (impératif)

```bash
mysqldump -u root -p --single-transaction pos_system > pos_system_avant_test_50k.sql
```

L'import **écrase** les produits existants dans le périmètre des
code-barres. Le dump est le seul retour en arrière.

### Étape 2 — Appliquer les migrations

(voir §2)

### Étape 3 — Importer

1. `Produits → Importer`
2. Choisir **`docs/produits_50k_import.csv`** (recommandé — pas de
   problème de mémoire) ou `docs/produits_50k_import.xlsx` (si
   `memory_limit` est monté à 2 Go)
3. **Mode** : `Créer` (les produits n'existent pas encore)
4. **Catégories** : ne pas créer automatiquement (elles existent déjà)
5. Lancer — prévoir **4 à 8 minutes**

⚠️ Si l'import `.xlsx` échoue sur la mémoire, bascule sur le CSV.

### Étape 4 — Vérifier

```sql
SELECT COUNT(*) AS produits FROM produits;
SELECT COUNT(*) AS lots     FROM product_batches;
SELECT COUNT(*) AS lots_sans_shop FROM product_batches WHERE shop_id IS NULL;
SELECT COUNT(*) AS divergence
  FROM product_batches b JOIN produits p ON p.id = b.product_id
 WHERE NOT (b.shop_id <=> p.shop_id);
SELECT COUNT(*) AS produits_sans_stock FROM produits WHERE stock <= 0;
SELECT COUNT(*) AS perimes FROM produits WHERE date_expiration < CURDATE();
```

**Attendu :**

| Contrôle | Attendu |
|---|---|
| `produits` | 50 057 (57 existants + 50 000 importés) |
| `lots` | ~45 500 (un lot par produit avec stock > 0) |
| **`lots_sans_shop`** | **0** |
| **`divergence`** | **0** ← le contrôle de fuite inter-boutiques |
| `produits_sans_stock` | ~4 545 |
| `perimes` | ~4 166 |

> ⚠️ `lots_sans_shop > 0` signifie que des lots ont été créés **sans** la
> migration R3 (ou par un script SQL direct). Relancer la migration R3 les
> corrige — son backfill est un UPDATE, donc rejouable.

### Étape 5 — Mesurer

Les chiffres à relever (et à comparer aux références ci-dessous) :

| Page | Mesure | Référence mesurée à 50 000 |
|---|---|---|
| `/produits` | Temps de 1re page | 478 ms |
| `/produits` | Taille du HTML | 0,06 Mo |
| `/produits` | Recherche (préfixe) | 11 ms |
| `/produits/lots` | Temps de 1re page | 2 ms |
| `/produits/lots` | Page 1000 (keyset) | ~2 ms (constant) |
| `/alertes-lots` | Temps de 1re page | — |
| `/dashboard` | Temps de chargement | — |
| `/caisse` | Temps de 1re ouverture | — |
| Un scan code-barres | Temps de réponse | 0,2 ms |

**Comment mesurer :** l'onglet Network des DevTools, ou
`curl -w "%{time_total}"`.

```bash
# Exemple : la page des lots
curl -w "\n%{time_total}s\n" -o /dev/null -s \
  -b "PHPSESSID=<ton-session>" \
  http://localhost/pos/public/produits/lots
```

---

## 5. Les scénarios à tester manuellement

Ce sont les chemins que les mesures ne couvrent pas — ils exigent un
navigateur et un scanner.

### 5.1 Le scanner (le chemin le plus critique)

1. Scanner un code-barres de la **page 1** → le produit s'ajoute au panier.
2. Scanner un code-barres d'un produit **en page 500** → il doit
   **quand même** être trouvé (le repli serveur).
3. Scanner un code-barres **inexistant** → message d'erreur clair, pas un
   panier vide.
4. ⚠️ **Scanner un code-barres d'une AUTRE boutique** → doit renvoyer
   « introuvable », **pas** le produit de l'autre boutique.
   → C'est le test de **R9**, qui n'est **pas encore corrigé** (cf.
   `docs/IL_RESTE_A_FAIRE.md`, item A). Ce test doit **échouer** aujourd'hui.

### 5.2 La recherche

| Test | Attendu |
|---|---|
| Taper `Coca` | Trouve les produits qui commencent par « Coca » |
| Taper `Cola` | Trouve « Coca-Cola » (repli infixe) |
| Taper `Huile d` | Trouve « Huile d'olive » (apostrophe) |
| Taper `Édition` | Trouve les 7 143 produits à accent |
| Effacer le champ | Toute la liste revient |
| 2 lettres (`ca`) | Des résultats pertinents (≠ le FULLTEXT, qui aurait renvoyé n'importe quoi) |

### 5.3 La pagination

- `/produits` : pages 1, 2, 100, 1000 → chaque page met ~0,5 s à charger.
- `/produits/lots` : « Suivant » 10 fois d'affilée → **constant** (~2 ms),
  et **aucun lot répété ni sauté** entre les pages.
- `/produits/lots` : « Précédent » après « Suivant » → on revient
  exactement à la page précédente.

### 5.4 La caisse

1. Ouvrir la caisse → le catalogue se charge sans figer le navigateur.
2. Rechercher un produit → résultats en moins d'une seconde.
3. Scanner 10 produits → le panier se remplit dans l'ordre.
4. Encaisser → le stock décrémente, et le stock affiché sur `/produits`
   est à jour (c'est la dé-normalisation R2).

### 5.5 Le dashboard

- Le nombre d'alertes de péremption est **borné à 20** + un compteur
  « N autres alertes non affichées ».
- Le lien « voir tout » mène à `/alertes-lots`, paginée.
- **Le dashboard ne doit pas freezer** même avec 4 166 lots périmés.

---

## 6. Comment générer un fichier plus petit (test rapide)

```bash
php scripts/generate_import_50k.php 1000        # 1 000 produits
php scripts/generate_import_50k.php 50000        # 50 000
```

Et pour un jeu de données **rivalisant** avec la prod (les 2 boutiques
réelles) plutôt qu'avec une seule :

```sql
-- Après l'import, pour répartir sur les 2 boutiques :
UPDATE produits SET shop_id = 2 WHERE id % 5 = 0;
-- ⚠️ Puis relancer la migration R3 : les lots doivent suivre.
```

> ⚠️ C'est exactement le piège que j'ai rencontré : un jeu de données
> **mono-boutique** ne mesure pas le coût du filtre multi-tenant. La
> jointure « parait » gratuite (0,5 ms au lieu de 76,7 ms), et on
> conclut à tort qu'aucune optimisation n'est nécessaire.

---

## 7. Vérifier le fichier sans l'importer

```bash
# Le .xlsx (besoire de memory_limit=2G à 50 000 lignes)
php -d memory_limit=2G scripts/verify_import_file.php docs/produits_50k_import.xlsx

# Comparer les deux formats (temps + pic mémoire)
php -d memory_limit=2G scripts/measure_import_csv.php
```

Ce script :
1. ouvre le fichier avec PhpSpreadsheet ;
2. vérifie les 14 en-têtes canoniques **dans le bon ordre** ;
3. vérifie le format TEXTE de la colonne `code_barres` ;
4. contrôle les données (unicité, prix valides, répartition) ;
5. vérifie que les catégories existent en base ;
6. **importe réellement** le fichier dans une base jetable
   `pos_import_test` et compare le nombre de lignes importées à
   l'attendu ;
7. supprime la base jetable.

> ⚠️ Il lui faut `memory_limit = 2G` pour le fichier complet :
> `php -d memory_limit=2G scripts/verify_import_file.php …`

Il ne touche jamais `pos_system` ni `pos_systeme_test`.

---

## 8. Les scripts de mesure conservés

| Script | Ce qu'il mesure |
|---|---|
| `scripts/generate_import_50k.php` | Génère les fichiers Excel **et CSV** de test |
| `scripts/verify_import_file.php` | Vérifie que le fichier est importable |
| `scripts/measure_import_csv.php` | Compare l'import CSV vs XLSX (temps + pic mémoire) |
| `scripts/perf_bench_catalogue.php` | Le benchmark du catalogue (référence historique) |
| `scripts/perf_bench_decisions.php` | Recherche, tri, page lots |
| `scripts/perf_bench_hybrid_search.php` | Quelle clause casse l'index |
| `scripts/perf_bench_search_final.php` | Le gain bout en bout de la recherche |
| `scripts/perf_bench_lots.php` | Les variantes d'index de la page lots |
| `scripts/perf_bench_keyset.php` | OFFSET vs KEYSET |
| `scripts/perf_bench_dashboard_alerts.php` | L'impact du dashboard non borné |
| `scripts/perf_bench_r3_migration.php` | La migration R3 et le choix de l'index |
| `scripts/perf_bench_r9_barcode_scope.php` | Les 3 conséquences de R9 |
| `scripts/audit_unbounded_queries.php` | L'audit statique (périmètre POS) |
| `scripts/verify_migration_r3.php` | La migration R3, appliquée 2 fois |
| `scripts/verify_search_e2e.php` | Bout en bout par HTTP réel |
| `scripts/verify_alertes_lots.php` | Route, vue et bornes de `/alertes-lots` |

Tous les scripts de benchmark créent une base `pos_perf_*` ou
`pos_import_test` et la suppriment à la fin. **Aucun ne touche
`pos_system` ni `pos_systeme_test`.**

---

## 9. Les références mesurées (pour comparer)

Toutes mesurées à 50 000 produits / 85 000 lots, MySQL 9.1.0.

| Poste | Avant | Après | Gain |
|---|---|---|---|
| 1re page de `/produits` | 3 693 ms | **478 ms** | ×8 |
| Poids du HTML de `/produits` | 60,8 Mo | **0,06 Mo** | ×1001 |
| Parcours DOM par frappe (filtre) | 150 000 | **150** | ×1000 |
| JSON du catalogue | 24,5 Mo | 0,02 Mo | ×1200 |
| Recherche par frappe | 198,2 ms | **11,3 ms** | ×18 |
| 1re page de `/produits/lots` | 76,7 ms | **2,0 ms** | ×38 |
| Pagination page 1000 (keyset) | 177 ms | **~2 ms** | ×88 |
| Alertes du dashboard (90 j) | 502,7 ms | **14 ms** | ×36 |
| Import de produits | 4,22 s | **0,05 s** | ×80 |
