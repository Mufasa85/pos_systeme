# Plan — Brouillons de panier (caisse)

**Statut :** implémenté — en attente de validation en conditions réelles
**Date :** 2026-11-17
**Module :** POS (caisse) — préfixe SQL `caisse_`

---

## 0. État de l'implémentation

| Étape | Statut | Livrable |
| ----- | ------ | -------- |
| Migration SQL (schéma) | ✅ | `migrations/2026_11_17_caisse_brouillons.sql` |
| Modèle | ✅ | `app/models/CaisseBrouillon.php` |
| Contrôleur + routes | ✅ | `CaisseBrouillonController` + 6 routes |
| Vue (bouton + modal) | ✅ | `app/views/caisse.php` |
| JS (module dédié) | ✅ | `public/assets/js/caisse-brouillons.js` |
| Script de purge (Q3) | ✅ | `scripts/purge_brouillons_caisse.php` |
| Route lecture client | ✅ | `GET /api/client/[i:id]` |
| Tests | ✅ | `tests/Integration/CaisseBrouillonTest.php` (7 tests) |
| Docs | ✅ | `ROUTES.md`, `AGENT.md`, ce fichier |

---

## 1. Le problème

Le panier de la caisse (`posCart.items`, dans `public/assets/js/app.js`) vit
**uniquement en mémoire JavaScript**. Toute action qui vide la page le perd :

- un simple rechargement (F5) ;
- un crash du navigateur ;
- une coupure réseau pendant l'enregistrement ;
- un changement de poste de travail.

Le caissier doit alors rescanner toute la commande à la main.

---

## 2. La solution retenue

Un **brouillon** est un *snapshot* du panier persisté **côté serveur**, dans
une table dédiée. Le caissier peut :

1. **enregistrer** son panier en cours (bouton « Brouillon ») ;
2. le **reprendre** plus tard, sur n'importe quel poste de la même boutique ;
3. **relancer la facture** avec le flux normal (DGI → `POST /api/vente`).

---

## 3. Décisions validées

| # | Question | Choix retenu |
| - | -------- | ------------ |
| **Q1** | Le stock est-il réservé à la sauvegarde ? | **Non.** La déduction reste à la facturation (`SaleController::create`), comme pour toute vente. Un panier « gelé » ne bloquerait pas le stock d'un article pendant des heures. |
| **Q2** | Les brouillons sont-ils visibles par les collègues ? | **Oui en lecture** (même boutique), suppression réservée à l'auteur ou à un admin. |
| **Q3** | Purge des brouillons abandonnés ? | **Oui, automatique** : les brouillons `ouvert` de plus de **30 jours** sont supprimés par `scripts/purge_brouillons_caisse.php` (cron quotidien). Les brouillons `converti` ne sont **jamais** purgés. |

---

## 4. Pourquoi une table DÉDIÉE (et pas un statut dans `ventes`)

`ventes` est la table **fiscale**. Tout `SELECT ... FROM ventes` (rapports
A/X/Z DGI, clôture de caisse, historique, export CSV, archivage) y compte des
ventes. Y loger un brouillon **faudrait les rapports** : une facture préparée
mais non payée gonflerait le chiffre d'affaires.

De plus :

- le stock est déduit au moment de la **vente**, pas de la création →
  un brouillon ne doit rien déduire ;
- le numéro de facture serait consommé → collision sur
  `ventes.numero_facture` (UNIQUE).

→ Table séparée `caisse_brouillons`, préfixe par module (AGENT.md §2).

---

## 5. Modèle de données

**Une seule table** : le snapshot du panier tient dans une colonne `JSON`.
Pas de table `caisse_brouillon_lignes` : le panier est un *snapshot jetable*,
pas un agrégat financier. Cela évite une 2e table à migrer/nettoyer/purger
et surtout de dupliquer le calcul de TVA (les lignes de vente figent déjà
`tax_rate` / `tax_etiquette` / `tax_groupe` — cf. migration 2026_11_06).

```sql
CREATE TABLE `caisse_brouillons` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `shop_id`      INT NOT NULL,          -- boutique propriétaire
  `user_id`      INT NOT NULL,          -- auteur
  `libelle`      VARCHAR(120) NOT NULL, -- "Coca x3 + Pain" (auto si vide)
  `client_id`    INT DEFAULT NULL,      -- FK → clients (SET NULL)
  `sejour_id`    INT DEFAULT NULL,      -- FK → hotel_sejours (SET NULL)
  `invoice_type` VARCHAR(5) NOT NULL DEFAULT 'FV',
  `payload`      JSON NOT NULL,         -- {articles, client, sejour, payments}
  `total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `nb_articles`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`       ENUM('ouvert','converti') NOT NULL DEFAULT 'ouvert',
  `vente_id`     INT DEFAULT NULL,      -- FK → ventes (SET NULL)
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_brouillons_shop_user` (`shop_id`, `user_id`),
  KEY `idx_brouillons_status` (`status`),
  KEY `idx_brouillons_updated` (`updated_at`)
);
```

La colonne `ventes.brouillon_id` (ajoutée par la même migration) assure la
**traçabilité** inverse : on sait qu'une facture vient d'un brouillon. Elle
est `NULL` par défaut et **aucun `SELECT` sur `ventes` n'est modifié**.

---

## 6. Règles invariantes

> Ces règles sont le cœur du feature. Elles sont verrouillées par les tests.

1. Un brouillon **ne déduit JAMAIS** le stock.
2. Un brouillon **ne consomme JAMAIS** de numéro de facture.
3. Un brouillon **n'est pas** une vente : il ne pollue ni les rapports DGI,
   ni la clôture de caisse, ni le chiffre d'affaires.
4. La facturation passe **exclusivement** par `SaleController::create`
   (AGENT.md §7 : ne pas réinventer la facturation).
5. Le payload ne contient **que** des articles et des identifiants :
   ni NIF, ni adresse, ni coordonnées bancaires du client. La fiche client
   est relue depuis l'API au moment de la reprise.
6. Le scoping boutique est **fail-closed** : un brouillon d'une autre
   boutique est *inexistant* (404), pas *interdit* (403).
7. Un brouillon `converti` **n'est jamais purgé**.

---

La colonne `ventes.brouillon_id` (ajoutée par la même migration) assure la
**tracabilite** inverse : on sait qu une facture vient d un brouillon. Elle
est `NULL` par defaut et **aucun `SELECT` sur `ventes` n est modifie**.

---

## 6. Regles invariantes

> Ces regles sont le coeur du feature. Elles sont verrouillees par les tests.

1. Un brouillon **ne deduit JAMAIS** le stock.
2. Un brouillon **ne consomme JAMAIS** de numero de facture.
3. Un brouillon **n est pas** une vente : il ne pollue ni les rapports DGI,
   ni la cloture de caisse, ni le chiffre d affaires.
4. La facturation passe **exclusivement** par `SaleController::create`
   (AGENT.md 7 : ne pas reinventer la facturation).
5. Le payload ne contient **que** des articles et des identifiants :
   ni NIF, ni adresse, ni coordonnees bancaires du client. La fiche client
   est relue depuis l API au moment de la reprise.
6. Le scoping boutique est **fail-closed** : un brouillon d une autre
   boutique est *inexistant* (404), pas *interdit* (403).
7. Un brouillon `converti` **n est jamais purge**.

---

## 7. Routes API

| Methode | Route | Role | Acces |
| ------- | ----- | ---- | ----- |
| `GET` | `/api/caisse/brouillons` | Liste (scopee boutique) | authentifie |
| `GET` | `/api/caisse/brouillons/compteur` | Nb de brouillons `ouvert` (badge) | authentifie |
| `GET` | `/api/caisse/brouillons/[i:id]` | Detail + payload | authentifie + scoping |
| `POST` | `/api/caisse/brouillons` | Creation / mise a jour | authentifie + `acces_caisse` |
| `POST` | `/api/caisse/brouillons/[i:id]/restore` | Reprise dans le panier | authentifie + `acces_caisse` |
| `POST` | `/api/caisse/brouillons/[i:id]/delete` | Suppression | auteur ou admin |

Route annexe ajoutee pour la reprise :

| Methode | Route | Role |
| ------- | ----- | ---- |
| `GET` | `/api/client/[i:id]` | Relit la fiche client (le brouillon n en garde que l ID) |

> `POST .../delete` et non `DELETE` : le projet n utilise aucune route
> `Router::delete` (cf. `/api/pressing/commandes/[i:id]/delete`).

---

## 8. Flux utilisateur

```
+-- Enregistrer ----------------------------------------------+
| Bouton Brouillon -> modal -> Enregistrer                    |
|   POST /api/caisse/brouillons {articles, client, ...}      |
|   le stock n est PAS touche                                |
|   Toast Brouillon enregistre                               |
+-------------------------------------------------------------+

+-- Reprendre ------------------------------------------------+
| Bouton Brouillon -> liste -> Reprendre                     |
|   POST /api/caisse/brouillons/{id}/restore                 |
|   posCart.items = payload.articles (totaux recalcules)      |
|   Client / sejour / type de facture restaures              |
|   Toast validez la facture pour l encaisser                 |
|                                                              |
| Bouton Valider la facture -> flux NORMAL :                  |
|   DGI (OSAT) puis POST /api/vente                           |
|   -> le backend deduit le stock et cree la vente            |
|   -> le brouillon passe en converti                         |
+-------------------------------------------------------------+
```

---

## 9. Purge (Q3)

```bash
php scripts/purge_brouillons_caisse.php          # 30 jours (defaut)
php scripts/purge_brouillons_caisse.php --jours=7 # seuil personnalise
```

Cron quotidien recommande :

```cron
0 3 * * * php /chemin/vers/scripts/purge_brouillons_caisse.php
```

- Seuls les brouillons `ouvert` (jamais factures) sont purges.
- Les brouillons `converti` sont **conserves** (trace metier).
- **Aucune facture n est touchee.**
- Seuil : `CaisseBrouillon::PURGE_JOURS` (30 par defaut).

---

## 10. Fichiers impactes

| Fichier | Nature |
| ------- | ------ |
| `migrations/2026_11_17_caisse_brouillons.sql` | **nouveau** - table + `ventes.brouillon_id` |
| `app/models/CaisseBrouillon.php` | **nouveau** - modele |
| `app/controllers/CaisseBrouillonController.php` | **nouveau** - API |
| `public/assets/js/caisse-brouillons.js` | **nouveau** - module front |
| `tests/Integration/CaisseBrouillonTest.php` | **nouveau** - 7 tests |
| `scripts/purge_brouillons_caisse.php` | **nouveau** - purge (Q3) |
| `routes/api.php` | 6 routes + `GET /api/client/[i:id]` |
| `app/controllers/ClientController.php` | methode `show()` |
| `app/controllers/SaleController.php` | tracabilite `brouillon_id` + `markConverted` |
| `app/models/Sale.php` | colonne `brouillon_id` a l INSERT |
| `app/views/caisse.php` | bouton + modal + CSS |
| `app/views/layout/footer.php` | chargement du script JS |
| `public/assets/js/app.js` | 3 points d appel (tracabilite + badge) |

---

## 11. Verifications passees

- Migration appliquee sur `pos_systeme_test`, **rejouable** (idempotente).
- Suite PHPUnit complete : **227 tests, 947 assertions, 0 echec**.
- 7 tests dedies couvrent : creation, panier vide refuse, absence de
  deduction de stock, scoping boutique (404), restauration, purge (converti
  conserve / ouvert purge), autorisation de suppression, compteur.
- PHPStan : **0 erreur** sur les 6 fichiers PHP touches.
- PHP-CS-Fixer : **aucune violation** sur les 8 fichiers touches.
- ESLint : **0 erreur** sur `caisse-brouillons.js`.

---

## 12. Reste a faire (cote utilisateur)

1. **Appliquer la migration en production** :
   ```bash
   mysql -u root -p pos_system < migrations/2026_11_17_caisse_brouillons.sql
   ```
2. **Brancher le cron de purge** (optionnel mais recommande) :
   ```cron
   0 3 * * * php /chemin/vers/scripts/purge_brouillons_caisse.php
   ```
3. Tester en conditions reelles : enregistrer un panier, changer de poste,
   reprendre, valider la facture.
