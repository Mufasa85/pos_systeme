# La caisse et 50 000 produits — le nombre de requêtes HTTP

**Date :** 2026-11-25
**Branche :** `main`

---

## 1. Le symptôme

Sur un magasin de **50 000 produits**, la caisse met trop de temps à
avoir le catalogue complet. Le catalogue est chargé **par blocs de
200** — d'où 250 requêtes HTTP.

---

## 2. Le malentendu : le client et le serveur ne se parlaient pas

| Côté | Valeur | Conséquence |
|---|---|---|
| Le client (`app.js`, `catalogueChunkSize`) | **1 000** | il demandait 1 000 par bloc |
| Le serveur (`Product::getPage()`) | **200** | il plafonnait à 200 |

⚠️ **Le plafond serveur (200) était donc toujours leWinner** : le
client demandait 1 000, le serveur en renvoyait 200. La caisse
faisait **250 requêtes** pour 50 000 produits, au lieu de 50.

Le plafond de 200 datait de l'époque où la caisse chargeait le
catalogue en une seule requête : il n'a jamais été revu.

---

## 3. Le coût est dans le réseau, pas dans le SQL

**Mesuré** (base jetable, 50 000 produits dans UN magasin, MySQL
9.1.0, la requête EXACTE de la caisse) :

| limit | requêtes | SQL cumulé | **avec réseau (~80 ms/req.)** | JSON total |
|---|---|---|---|---|
| **200** (avant) | **250** | 77,8 s | **97,8 s** | 26,0 Mo |
| 1 000 | 50 | 15,9 s | 19,9 s | 26,0 Mo |
| **2 000** (retenu) | **25** | **8,2 s** | **10,2 s** | 26,0 Mo |
| 5 000 | 10 | 4,3 s | 5,1 s | 26,0 Mo |

> ⚠️ Le poids total du JSON est IDENTIQUE (26,0 Mo) quel que soit le
> `limit` : on ne charge pas plus de données, on fait moins de
> requêtes. La latence réseau (~80 ms par requête, plus forte en 3G/4G
> qu'en local) domine tout.

**Le gain vient donc du nombre de requêtes : ×10.**

---

## 4. Ce qui a été changé

| Fichier | Changement |
|---|---|
| `app/models/Product.php` | `MAX_PAGE_SIZE = 2000` (constante) ; `getPage()` accepte un plafond **paramétrable** |
| `app/controllers/ProductController.php` | l'API `/api/produits/page` utilise le plafond 2 000 |
| `app/controllers/PageController.php` | la page `/produits` garde son plafond de **200** |
| `public/assets/js/app.js` | `catalogueChunkSize: 2000` |
| `tests/Integration/ProductPaginationTest.php` | le test du plafond vérifie `MAX_PAGE_SIZE` + le plafond explicite |

### ⚠️ Pourquoi deux plafonds

Les deux appelants de `getPage()` n'ont pas le même besoin :

- **la page `/produits`** affiche 50 lignes à l'écran : une page de
  2 000 ne servirait qu'à ralentir le premier affichage → plafond
  **200** conservé ;
- **la caisse** charge le catalogue **entier** par blocs, en
  arrière-plan → plafond **2 000**.

---

## 5. Ce qui n'a PAS été fait (et pourquoi)

### ⚠️ `FORCE INDEX` : mesuré, REJETÉ

L'index `(shop_id, nom)` existe et fonctionne (1,8 ms seul), mais
MySQL **l'écarte** dès qu'il y a une jointure : il préfère
`ALL + filesort` (294 ms) à `ref + ref` (1,5 ms). C'est un mauvais
choix d'optimiseur.

Forcer l'index **marche** (1,5 ms)… mais sur le catalogue complet :

| Stratégie | limit 200 | limit 1 000 | limit 2 000 |
|---|---|---|---|
| Actuel (MySQL choisit) | 77,8 s | 15,9 s | **8,2 s** |
| `FORCE INDEX` | 224,7 s | 43,5 s | 25,0 s |

⚠️ **`FORCE INDEX` est 3× plus lent** sur le catalogue complet (le
tri par l'index impose un accès aléatoire, alors que le `filesort`
parcourt la table séquentiellement). Il reste à explorer, mais
**ce n'est pas ce qui fait gagner du temps ici** : le nombre de
requêtes HTTP est le vrai goulot.

### ⚠️ Retirer la jointure `categories`

Mesuré : sans elle, une page est **quasi constante** (112 → 179 ms
quel que soit le `limit`) au lieu de 400–1 244 ms. Mais la caisse
**a besoin** de `p.categorie` (elle l'affiche dans la ligne produit
et filtre dessus). Résoudre la catégorie côté client est possible
(la caisse charge déjà `/api/categories` dans `categoriesCache`), mais
c'est un changement de comportement : le filtre par catégorie de la
caisse s'appuierait alors sur un cache local. **Non fait dans cette
livraison** — à traiter si le temps d'ouverture de la caisse reste
insuffisant.

---

## 6. Vérifications

| Vérification | Résultat |
|---|---|
| Boucle de la caisse rejouée à 50 000 produits | **25 requêtes** (avant 250), 50 000 produits chargés |
| Doublons / ids manquants | **0 / 0** |
| Ordre de tri respecté d'un bloc à l'autre | OUI |
| `total` stable entre les blocs | OUI |
| Poids d'un bloc de 2 000 | 1,04 Mo de JSON (~1,6 Mo de RAM PHP) |
| Navigateur réel (Playwright) | `limit=2000` bien demandé, catalogue complet, 0 erreur console |
| Export Excel | inchangé (il utilise `getAllFiltered()`, pas `getPage()`) — 13 tests verts |
| Suite PHPUnit | **418 tests verts** |

---

## 7. Ce qui reste à faire

| # | Item | Impact | Effort |
|---|---|---|---|
| 1 | **R9 — `code_barres` composite `(shop_id, code_barres)`** | 🔴 **Vente erronée inter-boutiques** | ~4 h |
| 2 | **Les 3 migrations R7/R2/R3 sur `pos_system`** | 🔴 Bloquant au déploiement | 5 min |
| 3 | **Résoudre la catégorie côté client** (retirer le JOIN `categories` de la requête caisse) | page 3,7× plus rapide ; le JOIN coûte 246 ms au lieu de 67 ms | ~2 h |
| 4 | **R2 — tri par colonne sur `/produits`** | 86 ms → 0,5 ms | ~2 h |

> ⚠️ Le point 3 est la suite naturelle de cette livraison : c'est le
> seul levier qui reste sur le **coût SQL** lui-même. Mais il change
> le comportement du filtre par catégorie de la caisse (qui s'appuierait
> alors sur `categoriesCache`), donc ça mérite un tour de table.
