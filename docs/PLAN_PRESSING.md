# Plan d'intégration — Module Pressing

**Statut :** implémenté — en attente de validation en conditions réelles
**Source :** `pressing/pressing/` (appli PHP autonome déposée dans le repo)
**Cible :** module du POS `sys-pos`, avec assistance au suivi des commandes de pressing
**Révision 2 (2026-11-04) :** modélisation revue — suppression des redondances avec les tables POS existantes (5 tables → 3 tables)
**Révision 3 (2026-11-04) :** implémentation livrée — migrations, modèles, contrôleur, routes, vue, accès, tests

---

## 0. État de l'implémentation

| Étape                                          | Statut | Livrable                                        |
| ---------------------------------------------- | ------ | ----------------------------------------------- |
| Migration SQL (schéma)                         | ✅     | `migrations/2026_11_04_pressing_module.sql`      |
| Modèles                                        | ✅     | `PressingCommande`, `PressingArticle`, `PressingPaiement` |
| Contrôleur + routes                            | ✅     | `PressingController` + 11 routes                |
| Actions d'écriture                             | ✅     | advance / recede / advance-all / pay / delete   |
| Lookup facture OSAT                            | ✅     | `DgiSyncService::lookupFacture()`               |
| Lien ventes POS                                | ✅     | `vente_id` + `GET /api/pressing/ventes-sans-commande` |
| Adaptation `Client`                            | ✅     | normalisation du téléphone + `code_client` robuste |
| Vue + CSS + JS                                | ✅     | `app/views/pressing.php`, `public/assets/js/pressing.js` |
| Accès + navigation                             | ✅     | flag API `pressing` (comme `/recharges`, `/hotel`), sidebar |
| Tests                                          | ✅     | 12 unitaires + 12 intégration                  |
| Docs                                           | ✅     | `ROUTES.md`, `AGENT.md`, ce fichier             |

### Décisions prises en cours d'implémentation

| # | Décision | Choix |
|---|----------|-------|
| D6 | Base de données `pressing` | **Supprimée.** Le module vit entièrement dans `pos_system`. L'appli autonome garde sa base, mais elle n'est plus une source pour le POS. |
| D7 | ISF utilisé par le pressing | **Celui de l'utilisateur connecté** (`shops.isf` de sa boutique). Le super_admin utilise l'ISF de sa boutique rattachée ; `company_info.isf` n'est qu'un repli s'il n'a aucune boutique. |
| D8 | Reprise de l'historique | **Abandonnée.** Pas de migration de transfert : les dossiers de production repartent de zéro. |
| D10 | Accès à la page | **Flag API `pressing`**, comme `/recharges` et `/hotel` (et non une case à cocher dans `/shops`). L'API OSAT expose déjà `data.pressing`. |
| D9 | Coefficient de majoration | **Appliqué côté serveur** : `prix_unitaire` est le prix de BASE, le contrôleur calcule `prix × coefficient` à la création. La source autonome majorait dans le JS ; côté serveur, le total facturé ne dépend plus du client HTTP. L'UI affiche le prix majoré en direct (aperçu) et envoie le prix de base. |

### Vérifications passées

- Migration appliquée sans erreur sur `pos_systeme_test` **et** `pos_system`,
  rejouable (idempotente).
- Suite PHPUnit complète : **71 tests, 130 assertions, 0 échec**.
- Script de bout en bout (48 assertions) exécuté contre le **vrai
  contrôleur** : garde d'accès, création, avancement, blocage de la remise,
  encaissement, remise, avancement global, suppression, scoping boutique,
  états, fusion client.
- PHPStan : **0 erreur** sur les 13 fichiers touchés (les 10 erreurs
  restantes sont pré-existantes, dans des fichiers non modifiés).
- PHP-CS-Fixer : **aucune violation** de style sur les 13 fichiers touchés.
- Page `/pressing` vérifiée dans un navigateur (rendu, tuiles, tableau,
  drawer de détail, progression) : **0 collision CSS**.

### Reste à faire (côté utilisateur)

1. Créer une boutique avec `isf = A1615551X` (ou aligner la boutique
   existante sur le NIF réellement utilisé) — sinon le lookup facture DGI
   échoue. Voir §2 / B3.
2. **Rien à cocher** : l'accès découle du flag `pressing` renvoyé par l'API OSAT pour l'ISF de la boutique. Si le flag est 0 côté OSAT, le module reste fermé.
3. Supprimer le dossier `pressing/` une fois le module validé.

---

## Sommaire

1. [Ce que fait la feature](#1-ce-que-fait-la-feature)
2. [Constat sur la source](#2-constat-sur-la-source)
3. [Analyse des redondances — 5 tables → 3 tables](#3-analyse-des-redondances--5-tables--3-tables)
4. [Décisions validées](#4-décisions-validées)
5. [Architecture cible](#5-architecture-cible)
6. [Les liens](#6-les-liens)
7. [Routes](#7-routes)
8. [Fichiers à créer](#8-fichiers-à-créer)
9. [Migrations](#9-migrations)
10. [Session et variables codées en dur](#10-session-et-variables-codées-en-dur)
11. [CSS : le piège à ne pas déclencher](#11-css--le-piège-à-ne-pas-déclencher)
12. [Accès et permissions](#12-accès-et-permissions)
13. [Étapes d'implémentation](#13-étapes-dimplémentation)
14. [Tests et documentation](#14-tests-et-documentation)
15. [Risques identifiés](#15-risques-identifiés)
16. [Hors périmètre](#16-hors-périmètre)

---

## 1. Ce que fait la feature

Dans la vraie vie d'un pressing (Kinshasa) :

1. **Le client a payé une facture DGI** pour des services de pressing (lavage, repassage, couture). La facture a été émise par la caisse POS → elle existe dans `ventes` avec un `numero_facture` et un `client_id`.
2. **Le pressing prend le relais** : le gérant saisit le numéro de facture, le système relit la facture chez OSAT, récupère le nom du client, son téléphone et la liste des vêtements avec leurs prix, et crée une **commande de pressing** rattachée à la vente POS.
3. **L'équipe traite le linge**, vêtement par vêtement, étape par étape :

   | # | Étape | État |
   |---|-------|------|
   | 0 | Déposé / En attente | `encours` |
   | 1 | Lavage commencé | `encours` |
   | 2 | Lavage terminé | `encours` |
   | 3 | Repassage commencé | `encours` |
   | 4 | Repassage terminé | `encours` |
   | 5 | Prêt pour récupération | `prete` |
   | 6 | Remis au client | `remise` |

4. **La remise au client est bloquée tant que la commande n'est pas soldée.** Règle métier existante, conservée telle quelle.
5. **La page `/pressing` est le tableau de bord** : compteurs (en cours / prêtes / remises / en retard), recherche, liste des commandes, détail d'une commande avec la progression de chaque vêtement.

**Le besoin « assistance »** : depuis le POS, voir qu'une facture a un pressing à suivre, et démarrer ce pressing en un clic.

---

## 2. Constat sur la source

`pressing/pressing/` est une appli PHP **autonome** :

| Fichier | Rôle |
|---|---|
| `index.php` | page unique + API JSON via `?api=` (9 actions) + POST de création |
| `db.php` | connexion PDO **séparée** + création auto des tables + 3 migrations légères |
| `assets/app.css` | 51 classes CSS, design autonome (bleu `#2563eb`, fond `#f5f6f8`) |
| `assets/app.js` | 328 lignes — lignes d'articles, modal détail, progression, paiements, refresh stats |
| `install.sql` | schéma de référence |
| `Base données/pressing.sql` | dump phpMyAdmin avec les données réelles (11 clients, 5 commandes) |

### 3 points bloquants vérifiés

**B1 — La base `pressing` n'existe pas sur le serveur MySQL local.**
Vérifié par `SHOW DATABASES` : sur `localhost:3306` (MySQL 9.1.0) il n'existe que `pos_system` et `pos_systeme_test` parmi les bases du projet. `pressing` est créée à la volée par `db.php` (`CREATE DATABASE IF NOT EXISTS`).

→ **Décision D6** : le module ne dépend d'aucune base `pressing`. Tout vit dans `pos_system`.

**B2 — `index.php:57` interroge une base cross-project qui n'existe pas ici.**

```php
$stmt = $pdo->prepare('SELECT * FROM u138949110_osat.facture_dgi_ok
                       WHERE store_isf = ? AND invoice_number = ? LIMIT 1');
```

`u138949110_osat` est absente de `SHOW DATABASES` et n'appartient pas à ce projet. → Remplacé par l'appel HTTP OSAT déjà utilisé par le POS (§6, lien DGI).

**B3 — `$local_nif = 'A1615551X'` est codé en dur (`index.php:31`).**

Ce NIF est en réalité l'**ISF** d'une boutique. Vérifié en live : `GET https://osat-energie.com/dgi/facture/?store_isf=A1615551X&invoice_number=2026/000849` renvoie bien la facture. En revanche aucune boutique de `shops` ne porte cet ISF aujourd'hui :

| shop | nom | isf |
|---|---|---|
| 1 | SuperMarché | A1720894F |
| 2 | Mon magasin | A900011T |
| 3 | test boutique | isftest |

→ Le NIF devient `shops.isf` de la boutique de l'**utilisateur connecté** (décision D7).

**Action manuelle requise** : la boutique qui héberge le pressing doit porter l'ISF réellement utilisé par l'atelier. L'appli autonome utilisait `A1615551X`, qui n'est l'ISF d'aucune boutique du POS aujourd'hui — il faut donc créer la boutique du pressing avec cet ISF (ou corriger l'ISF de la boutique existante si elle est déjà la bonne).

Sans ISF correct sur la boutique, le lookup facture DGI échoue : les factures de l'atelier ne sont pas retrouvées auprès d'OSAT.

### Autres points relevés

- **Fuseau horaire** : `db.php:13` pose `Africa/Kinshasa`. Le POS ne pose **rien** — le `php.ini` est en `Europe/Berlin`, MySQL en `W. Central Africa Standard Time`. Les deux valent UTC+1 aujourd'hui, mais Berlin passe en UTC+2 en été → décalage d'1 h entre PHP et MySQL en été. Le module pressing pose `Africa/Kinshasa` explicitement. Le problème global reste à traiter séparément.
- **Bugs de la source** (à ne pas reporter) : `demo.php:54` fait `UPDATE commandes` au lieu de `UPDATE commandes_pressing` ; `demo.php` insère les clients sans la colonne `nif` alors qu'elle est NOT NULL avec default.
- **`$_SESSION['flash']`** : utilisé par l'appli pressing, mais **jamais rendu** dans le POS (`app/views/` ne référence aucun flash). Le POS utilise `$_SESSION['flash_error']`, lui aussi non rendu. → Le feedback passera par des toasts JS, pas par le flash.

---

## 3. Analyse des redondances — 5 tables → 3 tables

La base `pressing` n'a existé que parce que l'appli était **autonome**. Intégrée au POS, plusieurs de ses tables font doublon avec des tables qui existent déjà. J'ai croisé chaque table avec le schéma POS réel **et** avec les usages dans le code (pas seulement les noms).

### Résultat de l'analyse

| Table pressing | Fonction | Table POS existante | Verdict |
|---|---|---|---|
| `clients_pressing` | nom + téléphone du client | **`clients`** (`numero`, `nom_client`, `nif`, `adresse`, `shop_id`) + `type_client` | **REDONDANT** → supprimée |
| `commandes_pressing` | commande de pressing | `ventes` (`numero_facture`, `client_id`, `total`, `shop_id`) | **PARTIELLEMENT redondant** → conservée, allégée |
| `commande_articles_pressing` | vêtement + prix + étape | `details_vente` + `produits` | **PARTIELLEMENT redondant** → conservée, allégée (§3.2) |
| `suivi_articles_pressing` | historique des étapes | **`audit_logs`** (`user_id`, `action`, `entity`, `entity_id`, `details` JSON) | **REDONDANT** → supprimée |
| `paiements_pressing` | encaissements pressing | `ventes.payments` | **NON redondant** → conservée (§3.3) |
| colonne `nif` (5 tables) | périmètre multi-tenant | `shops.isf` + `shop_id` | **REDONDANT** → supprimée |

**Résultat : 5 tables → 3 tables** (`pressing_commandes`, `pressing_articles`, `pressing_paiements`).

### 3.1 — `clients_pressing` → table `clients`

Le pressing n'a pas sa propre clientèle : c'est la même personne. La table `clients` du POS porte exactement ce qu'il faut :

| `clients_pressing` | `clients` | Type |
|---|---|---|
| `nom` | `nom_client` | VARCHAR(50) ⚠️ voir ci-dessous |
| `telephone` | `numero` | VARCHAR(30) |
| `client_pos_id` | `id` | FK |
| — | `code_client` | VARCHAR(20) UNIQUE, généré par `Client::create()` |
| — | `type_client_id` | FK → `type_client` (PP / PM / PC / PL / AO) |
| — | `nif` | TEXT |
| — | `shop_id` | FK → `shops` |

**Écarts à traiter** :

- **`nom_client` est VARCHAR(50)** alors que `clients_pressing.nom` est VARCHAR(120). Un nom de pressing plus long que 50 caractères serait tronqué. → À arbitrer : soit on tronque (acceptable pour un nom de client), soit on élargit `clients.nom_client` en migration. **Recommandé : élargir à VARCHAR(120)** — c'est un `ALTER` sans risque et sans redondance.
- **`code_client` est UNIQUE** et `Client::create()` le génère par `MAX(id)+1` → collision possible en cas de création concurrente. Le pressing crée des clients en flux continu. → `Client::create()` doit être called avec un code calculé en base (`SELECT MAX(id)` sur le même compte) ou on accepte le risque. **À traiter dans le modèle `Client`** (le pressing l'expose davantage).
- **Recherche par téléphone** : `Client::findByNumero()` existe déjà, mais les numéros pressing sont stockés formatés (`+243 89 548 96 08`) alors que le POS les stocke bruts (`0895511485`). → Le pressing doit **normaliser** le numéro avant recherche (retirer espaces, `+`, `243`, `0` de tête), sinon la clientèle se dédouble.
- **Création à la volée** : l'appli source fait « rechercher par téléphone + NIF, sinon créer ». On garde ce comportement via `Client::findByNumero()` puis `Client::create()`.

### 3.2 — `commande_articles_pressing` → table `pressing_articles` (allégée)

`details_vente` porte déjà les lignes de vente (`produit_id`, `quantite`, `prix`), mais **le pressing a besoin d'attributs qui n'existent pas dans le POS** :

| Attribut pressing | Existe dans le POS ? | Verdict |
|---|---|---|
| `type_vetement` (« NGOLO », « COTIS ») | ❌ non | **gardé** |
| `couleur` | ❌ non | **gardé** |
| `observation` (« reste : 20000 FC ») | ❌ non | **gardé** |
| `delai_type` (`normal` / `express` / `perso`) | ❌ non | **gardé** |
| `delai_heures` | ❌ non | **gardé** |
| `date_dispo` (retrait prévu) | ❌ non | **gardé** |
| `etape` (0 → 6) | ❌ non | **gardé** |
| `quantite` | ✅ `details_vente.quantite` | **gardé ici** (le vêtement a sa propre quantité de pièces) |
| `prix_unitaire` | ✅ `details_vente.prix` | **⚠️ voir ci-dessous** |
| `coefficient` | ❌ non | **gardé** |

**Sur le prix — point important, vérifié sur les données réelles :**

Le pressing applique un **coefficient** (majoration express, traitement délicat) sur le prix facturé. Vérification sur les 3 commandes réelles du dump :

| Commande | Facture DGI | Prix unitaire stocké | Coef | Contrôle |
|---|---|---|---|---|
| 16 (NGOLO) | 56 000 | 70 000 | 1.25 | 56 000 × 1.25 = 70 000 ✓ |
| 17 (COTIS) | 69 000 | 69 000 | 1.00 | 69 000 × 1.00 = 69 000 ✓ |
| 18 (COTIS) | 69 000 | 86 250 | 1.25 | 69 000 × 1.25 = 86 250 ✓ |

→ **Le total pressing ≠ total de la vente POS ≠ montant de la facture DGI.** Le prix du pressing est **spécifique** et ne peut pas être lu dans `details_vente`. Il reste dans `pressing_articles`.

**Conséquence** : on ne peut pas non plus réutiliser `details_vente` pour les lignes de pressing. Deux options :
- **(retenue)** `pressing_articles` porte tout : prix, quantité, coefficient, étape, attributs métier. La vente POS reste la source du *montant facturé* ; le pressing porte son *devis*.
- *(écartée)* dupliquer les lignes dans `details_vente` → double saisie, deux sources de vérité sur le prix. À proscrire.

### 3.2 bis — Calcul du total

`total = Σ (quantité × prix_unitaire stocké)`

Le `prix_unitaire` **stocké** est déjà majoré : le contrôleur calcule
`prix de base × coefficient` à la création (décision D9). Le coefficient
n'est donc **pas** réappliqué dans le calcul du total — sinon le montant
serait majoré deux fois.

Contrôle sur données réelles de l'appli autonome :

| Article | Prix stocké | Coef. | Base | Total attendu |
|---|---|---|---|---|
| NGOLO | 70 000 | 1.25 | 56 000 | 70 000 |
| COTIS | 86 250 | 1.25 | 69 000 | 86 250 |

### 3.3 — `paiements_pressing` → table `pressing_paiements` (conservée)

L'encaissement pressing est un **flux distinct** du paiement de la vente POS. Preuve dans les données :

> Commande 16 : facture DGI de **56 000**, mais encaissement pressing de **70 000** (le prix avec coefficient).

Si le pressing lisait le solde de `ventes.payments`, il afficherait « soldé » dès la création — et la règle « remise bloquée si solde > 0 » deviendrait inerte. Or cette règle est le cœur du workflow (on ne rend le linge qu'une fois payé).

**Position retenue : `pressing_paiements` est conservée.** Le solde pressing = total pressing (articles × prix × coefficient) − somme des paiements pressing. Le paiement de la vente POS n'est **pas** compté.

Conséquence à assumer : le pressing encaisse **en plus** de la vente POS. C'est le comportement observé dans les données réelles (le client paie la facture DGI à la caisse, puis règle son pressing au comptoir pressing).

> **Point à confirmer** : ce double encaissement est-il voulu, ou le pressing ne doit-il encaisser que les **compléments** (frais de retard, supplément express) au-dessus du montant déjà réglé ? Le cas échéant, `pressing_paiements` ne servirait qu'aux compléments, et le solde de départ serait `total pressing − montant vente POS déjà payé`. → Voir §15, risque R10.

### 3.4 — `suivi_articles_pressing` → table `audit_logs`

L'historique des étapes est un journal d'actions : *qui, quoi, quand*. C'est **exactement** la fonction de `audit_logs` :

```
user_id       → qui a fait l'action (remplace `valide_par`)
action        → 'pressing_advance' | 'pressing_recede'
entity        → 'pressing_article'
entity_id     → pressing_articles.id
details       → {"etape": 3, "label": "Repassage commencé"}
created_at    → quand
```

La table `audit_logs` est déjà dans le POS avec le modèle `AuditLog::log()` et l accessor `logAudit()` du contrôleur de base. Vérifié : `purgeOlderThan()` n'est appelé par aucun script → **l'historique n'est jamais purgé**, exactement comme `suivi_articles_pressing`.

⚠️ **Conséquence** : `audit_logs` n'a pas de FK vers `pressing_articles` (pas de `entity_id` FK). La lecture de l'historique d'un article se fait par `AuditLog::getByEntity('pressing_article', $id)` — une requête de plus, mais le modèle existe déjà et fonctionne.

⚠️ **Point d'attention** : `audit_logs.details` est un `json`. Le format `{"etape": 3}` est lisible, mais la **requête « l'historique d'un article, trié par étape »** n'est pas indexée (seuls `user_id`, `shop_id`, `action`, `created_at` le sont). Pour un usage à l'échelle d'un pressing (quelques centaines de lignes), c'est sans importance.

### 3.5 — colonne `nif` : suppression pure

Les 5 tables pressing portent `nif VARCHAR(30)` qui servait de périmètre multi-tenant. Dans le POS, le scoping se fait par :
- `shops.isf` → l'ISF de la boutique (remplace le `$local_nif` codé en dur) ;
- `shop_id` sur les tables → le scoping des requêtes.

→ La colonne `nif` disparaît des 3 tables conservées. Le backfill des données se fait par jointure `nif = shops.isf` → `shop_id`.

### 3.6 — Schéma final

```sql
-- 1. Le dossier de pressing (un par vente POS)
CREATE TABLE `pressing_commandes` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `vente_id`      INT NULL,              -- FK ventes(id) ON DELETE SET NULL
  `numero`        VARCHAR(20) NOT NULL,  -- PR-00001
  `facture`       VARCHAR(30) NOT NULL DEFAULT '',  -- n° de facture DGI
  `client_id`     INT NOT NULL,          -- FK clients(id)
  `date_depot`    DATETIME NOT NULL,
  `shop_id`       INT NULL,              -- FK shops(id)  (scoping)
  `user_id`       INT NULL,              -- FK utilisateurs(id) (qui a créé)
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pressing_cmd_numero` (`numero`),
  KEY `idx_pressing_cmd_shop`   (`shop_id`),
  KEY `idx_pressing_cmd_vente`  (`vente_id`),
  KEY `idx_pressing_cmd_facture` (`facture`),
  KEY `idx_pressing_cmd_client` (`client_id`),
  CONSTRAINT `fk_pressing_cmd_vente`  FOREIGN KEY (`vente_id`)  REFERENCES `ventes(id)`       ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_client` FOREIGN KEY (`client_id`) REFERENCES `clients(id)`      ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_shop`   FOREIGN KEY (`shop_id`)   REFERENCES `shops(id)`        ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_user`   FOREIGN KEY (`user_id`)   REFERENCES `utilisateurs(id)` ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dossiers de pressing';

-- 2. Les vêtements (avec leur état d'avancement)
CREATE TABLE `pressing_articles` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `commande_id`    INT NOT NULL,         -- FK pressing_commandes(id) ON DELETE CASCADE
  `type_vetement`  VARCHAR(80) NOT NULL,
  `couleur`        VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  `quantite`       INT NOT NULL DEFAULT 1,
  `prix_unitaire`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `coefficient`    DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  `observation`    VARCHAR(255) NOT NULL DEFAULT '',
  `delai_type`     ENUM('normal','express','perso') NOT NULL DEFAULT 'normal',
  `delai_heures`  INT NOT NULL DEFAULT 48,
  `date_dispo`     DATETIME NULL,        -- retrait prévu
  `etape`          TINYINT NOT NULL DEFAULT 0,  -- 0 Déposé → 6 Remis
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pressing_art_cmd`   (`commande_id`),
  KEY `idx_pressing_art_etape` (`etape`),
  CONSTRAINT `fk_pressing_art_cmd` FOREIGN KEY (`commande_id`) REFERENCES `pressing_commandes(id)` ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Vetements d''un dossier de pressing';

-- 3. Les encaissements pressing
CREATE TABLE `pressing_paiements` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `commande_id`   INT NOT NULL,          -- FK pressing_commandes(id) ON DELETE CASCADE
  `montant`       DECIMAL(12,2) NOT NULL,
  `mode`          ENUM('ESPECES','MOBILEMONEY','VIREMENT') NOT NULL DEFAULT 'ESPECES',
  `user_id`       INT NULL,              -- FK utilisateurs(id)
  `date_paiement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pressing_pay_cmd` (`commande_id`),
  CONSTRAINT `fk_pressing_pay_cmd` FOREIGN KEY (`commande_id`) REFERENCES `pressing_commandes(id)` ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_pay_user` FOREIGN KEY (`user_id`)     REFERENCES `utilisateurs(id)`   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Encaissements pressing (hors paiement de la vente POS)';
```

**Choix de l'ENUM `mode`** : le pressing utilise `'Espèces' / 'Mobile Money' / 'Virement'` (libellés accentués), le POS utilise `'ESPECES' / 'MOBILEMONEY' / 'VIREMENT'` (constantes, cf. `SaleController.php:613`, `ReportService.php:37`). → On aligne sur le **POS** (constantes majuscules), qui est le format normalisé et lisible par `ReportService` / `facture-ticket.php`.

### 3.7 — Ce qui est supprimé, et ce qui le remplace

| Supprimé | Remplacé par | Impact |
|---|---|---|
| `clients_pressing` (11 lignes) | `clients` | 11 clients à fusionner (dont 2 avec le même téléphone : id 8 et 9) |
| `suivi_articles_pressing` (~198 lignes) | `audit_logs` | historique conservé, mais requêté via `getByEntity()` |
| `paiements_pressing` → **conservé** sous le nom `pressing_paiements` | — | 7 lignes à migrer |
| `commande_articles_pressing` → **conservé** sous le nom `pressing_articles` | — | 6 lignes à migrer |
| `commandes_pressing` → **conservé** sous le nom `pressing_commandes` | — | 5 lignes à migrer |
| colonne `nif` (×5) | `shop_id` | backfill par jointure `nif = shops.isf` |

---

## 4. Décisions validées

| # | Décision | Choix |
|---|---|---|
| D1 | Emplacement des tables | **Dans `pos_system`** — une seule base, une seule connexion PDO |
| D2 | Lien vente POS ↔ commande | **Colonne `vente_id` explicite** + vue « ventes POS sans pressing » |
| D3 | Permissions | **Tout utilisateur authentifié** de la boutique cochée ; suppression réservée à admin/super_admin |
| D4 | Périmètre v1 | **Port complet** : création depuis facture DGI, avancement, retour, encaissement, suppression |
| **D5** | **Modélisation** | **3 tables** — réutilisation de `clients`, `audit_logs`, `ventes` ; suppression de `clients_pressing` et `suivi_articles_pressing` ; conservation de `pressing_paiements` (le pressing a son propre flux d'encaissement) |

---

## 5. Architecture cible

```
┌─ POS (pos_system) ────────────────────────────────────────────┐
│                                                                │
│  /pressing  ──► PageController::pressing()                    │
│                   └─► app/views/pressing.php                    │
│                       + public/assets/css/pressing.css (pr-)  │
│                       + public/assets/js/pressing.js           │
│                                                                │
│  /api/pressing/* ──► PressingController                        │
│                         ├─► PressingCommande  (pressing_commandes)  │
│                         ├─► PressingArticle   (pressing_articles)   │
│                         ├─► PressingPaiement  (pressing_paiements)  │
│                         ├─► Client       (clients)      ← réutilisé │
│                         ├─► AuditLog     (audit_logs)   ← réutilisé │
│                         └─► DgiSyncService (lookup facture)          │
└────────────────────────────────────────────────────────────────┘
```

Le dossier `pressing/` reste en référence jusqu'à validation de la migration, puis est supprimé (non suivi par git).

---

## 6. Les liens

### Lien A — DGI : retrouver la facture d'un client

`getPremiereFacture()` (base cross-project) est remplacé par l'appel HTTP OSAT déjà industrialisé dans le POS.

**Chaîne existante à réutiliser** (`app/Services/DgiSyncService.php`) :

```
DgiSyncService::resolveToken($storeIsf, $sale)
  → company_info.isf === store_isf  → company_info.token
  → Shop::findByIsf(store_isf)      → shops.token
  → shops.token                      → repli
  → company_info.token               → repli
```

**Résolution de l'ISF** (du plus fiable au moins fiable) :

1. `shops.isf` de la boutique en session
2. `company_info.isf` si le user est `super_admin`
3. l'ISF figé sur la vente (`ventes.store_isf`) quand la facture est déjà connue

**Format de la réponse OSAT** (vérifié en live) :

```json
{ "success": true, "data": {
    "client_name": "KLO", "client_number": "0842223333",
    "invoice_number": "2026/000849", "total": "56000.00",
    "articles": "[{\"name\":\"NGOLO\",\"price\":56000,\"quantity\":1,\"type\":\"BIE\"}]",
    "payment_type": "[{\"name\":\"ESPECES\",\"amount\":56000}]"
} }
```

`articles` et `payment_type` sont des **chaînes JSON** à décoder.

> ⚠️ Le POS possède déjà un service OSAT de lecture de facture : `DgiSyncService::rereadFromOsat()` (ligne 370) et le proxy `/api/service-bill` (`routes/api.php:585`). **Le pressing passe par le même chemin**, pas par un nouveau client HTTP — sinon on duplique la résolution de token et on divergera.

### Lien B — Vente POS → commande de pressing

Colonne `pressing_commandes.vente_id`, FK `ventes(id)` `ON DELETE SET NULL`.

À la création d'une commande depuis une facture :

1. on résout `ventes.numero_facture = <n° de facture>` (borné à la boutique + l'ISF) ;
2. si trouvé → `vente_id` = l'id, et `client_id` = `ventes.client_id` (le client POS existe déjà) ;
3. si **pas** trouvé (facture émise hors de cette caisse) → `vente_id` = NULL, et le client est créé dans `clients` à partir des données OSAT. La commande pressing fonctionne quand même.

**Vue « ventes POS sans pressing »** (`GET /api/pressing/ventes-sans-commande`) :

```sql
SELECT v.id, v.numero_facture, v.total, v.date, v.invoice_type,
       c.nom_client, c.numero
FROM ventes v
LEFT JOIN clients c ON c.id = v.client_id
LEFT JOIN pressing_commandes p ON p.vente_id = v.id
WHERE p.id IS NULL
  AND v.shop_id = :shop_id
  AND v.type_vente = 'product'
ORDER BY v.date DESC
LIMIT 50
```

C'est le cœur de la feature « assistance » : la liste des factures POS qui n'ont pas encore de pressing.

### Lien C — Commande de pressing → vente POS

`vente_id` en lecture : la page détail affiche le n° de facture et un lien vers `/facture/<vente_id>`.

### Lien D — Périmètre boutique

`pressing_commandes.shop_id` FK `shops(id)` — **obligatoire** pour le scoping multi-boutique. Toutes les requêtes filtrent dessus. Rempli à la migration par jointure `nif = shops.isf`.

---

## 7. Routes

### Page (`routes/web.php`)

```php
Router::get('/pressing', [PageController::class, 'pressing']);
```

### API (`routes/api.php`)

```php
// Tableau de bord + liste
Router::get('/api/pressing/stats',                    [PressingController::class, 'stats']);
Router::get('/api/pressing/commandes',                [PressingController::class, 'index']);
Router::get('/api/pressing/commandes/[i:id]',         [PressingController::class, 'show']);
Router::post('/api/pressing/commandes',               [PressingController::class, 'store']);

// Écriture
Router::post('/api/pressing/commandes/[i:id]/advance-all', [PressingController::class, 'advanceAll']);
Router::post('/api/pressing/commandes/[i:id]/pay',         [PressingController::class, 'pay']);
Router::post('/api/pressing/commandes/[i:id]/delete',      [PressingController::class, 'destroy']);
Router::post('/api/pressing/articles/[i:id]/advance',      [PressingController::class, 'advance']);
Router::post('/api/pressing/articles/[i:id]/recede',        [PressingController::class, 'recede']);

// Aides
Router::post('/api/pressing/facture/lookup',           [PressingController::class, 'lookupFacture']);
Router::get('/api/pressing/ventes-sans-commande',      [PressingController::class, 'ventesSansCommande']);
```

### Correspondance avec l'appli source

| Source (`?api=`) | Route cible | Remarques |
|---|---|---|
| `stats` | `GET /api/pressing/stats` | |
| `commande&id=` | `GET /api/pressing/commandes/[i:id]` | |
| (POST formulaire) | `POST /api/pressing/commandes` | |
| `avancer` | `POST /api/pressing/articles/[i:id]/advance` | l'historique part dans `audit_logs` |
| `reculer` | `POST /api/pressing/articles/[i:id]/recede` | idem |
| `avancer_tout` | `POST /api/pressing/commandes/[i:id]/advance-all` | |
| `payer` | `POST /api/pressing/commandes/[i:id]/pay` | écrit dans `pressing_paiements` |
| `supprimer` | `POST /api/pressing/commandes/[i:id]/delete` | |
| `facture` | `POST /api/pressing/facture/lookup` | **change de source** → OSAT HTTP (§6, lien A) |

**Le process métier est intégralement repris** : avancement, blocage de la remise si solde > 0, calcul d'état (encours / prete / remise / retard), retard, encaissement, suppression conditionnée à la remise complète.

---

## 8. Fichiers à créer

```
app/models/PressingCommande.php     pressing_commandes
app/models/PressingArticle.php      pressing_articles
app/models/PressingPaiement.php     pressing_paiements
app/controllers/PressingController.php
app/views/pressing.php              ($page = 'pressing'; drawer .pr-drawer)
public/assets/js/pressing.js        IIFE, port de pressing/pressing/assets/app.js
migrations/2026_11_04_pressing_module.sql
tests/Unit/PressingEtapesTest.php
tests/Integration/PressingCommandeTest.php
docs/PLAN_PRESSING.md               ce document
```

**Modifiés** : `routes/web.php`, `routes/api.php`, `app/controllers/PageController.php`, `app/controllers/Controller.php` (garde d'accès), `app/models/Client.php` (normalisation du numéro + `code_client` robuste), `app/Services/RechargeAccessService.php` (flag `pressing`), `app/Services/DgiSyncService.php` (`lookupFacture()`), `app/views/layout/header.php` (sidebar), `ROUTES.md`, `AGENT.md`.

**Non modifiés** (réutilisés tels quels) : `app/models/AuditLog.php` (déjà générique), `app/models/Client.php` (déjà complet — sauf les 2 points ci-dessus).

> ⚠️ Le CSS est **inline dans la vue** (`app/views/pressing.php`), comme les
> autres vues du projet. Il n'y a pas de `public/assets/css/pressing.css` :
> toutes les classes y sont préfixées `pr-`.

---

## 9. Migrations

### `migrations/2026_11_04_pressing_module.sql`

**1. Création des 3 tables** — `CREATE TABLE IF NOT EXISTS` (rejouable), schéma §3.6.

**2. Accès au module** — aucune colonne : le flag `pressing` de l'API OSAT fait foi (fail-closed). La migration supprime `shops.acces_pressing` si une version antérieure l'avait créée.

**3. `clients.nom_client` élargi** de VARCHAR(50) à VARCHAR(120) — `ALTER TABLE clients MODIFY nom_client VARCHAR(120) NOT NULL`. Idempotent par nature.

**4. `clients.numero_normalise`** — colonne **GENERATED STORED** + index.

```sql
`numero_normalise` VARCHAR(30) GENERATED ALWAYS AS (
  CASE
    WHEN REGEXP_REPLACE(numero,'[^0-9]','') LIKE '243%'
     AND LENGTH(REGEXP_REPLACE(numero,'[^0-9]','')) = 12
      THEN SUBSTRING(REGEXP_REPLACE(numero,'[^0-9]',''), 4)
    WHEN LEFT(REGEXP_REPLACE(numero,'[^0-9]',''),1) = '0'
      THEN SUBSTRING(REGEXP_REPLACE(numero,'[^0-9]',''), 2)
    ELSE REGEXP_REPLACE(numero,'[^0-9]','')
  END
) STORED
```

MySQL calcule la valeur à l'écriture : le code applicatif n'a pas à maintenir
la règle, et la recherche par téléphone reste indexée. La règle est
**identique** à `Client::normaliserNumero()` — vérifié par un test qui
compare les deux implémentations sur 7 formats de numéro.

**5. Backfill** `pressing_commandes.shop_id` par jointure `nif = shops.isf` :

```sql
UPDATE pos_system.pressing_commandes p
JOIN pos_system.shops s ON s.isf = p.nif
SET p.shop_id = s.id
WHERE p.shop_id IS NULL;
```

Les lignes dont le NIF ne correspond à aucune boutique restent `NULL` → **invisibles** dans le POS (scoping strict) tant que la boutique n'est pas configurée. C'est voulu : fail-closed.

> ⚠️ **Ordre d'exécution obligatoire** : (1) copie des données, (2) migration module. Sinon la copie réintroduit des lignes sans `shop_id`.

### Pas de migration de transfert

La base `pressing` de l'appli autonome **n'est pas une source** pour le POS
(elle n'existe pas sur le serveur de développement, et le module ne doit
pas dépendre d'une base séparée). Les dossiers de production repartent donc
de zéro : ils se recréent via `/pressing` → « Nouvelle commande », en
repartant de la facture DGI.

> Si tu dois malgré tout récupérer l'historique, le dump
> `pressing/pressing/Base données/pressing.sql` peut être chargé à la main
> dans une base `pressing` temporaire, puis exporté vers les tables
> `pressing_*` — mais il faudra recréer les boutiques correspondantes
> (par ISF) pour que les dossiers soient visibles.

### Commande à exécuter (par toi)

```bash
mysql -u root -p pos_system < migrations/2026_11_04_pressing_module.sql
```

---

## 10. Session et variables codées en dur

| Codé en dur dans la source | Remplacé par |
|---|---|
| `$local_nif = 'A1615551X'` | `shops.isf` de la boutique de l'utilisateur connecté ; `company_info.isf` en repli (super_admin sans boutique) |
| `date_default_timezone_set('Africa/Kinshasa')` | `Africa/Kinshasa` posé dans le contrôleur + le modèle |
| `getPremiereFacture()` → base `u138949110_osat` | `DgiSyncService` / proxy OSAT (§6) |
| `valide_par` saisi à la main | `audit_logs.user_id` (l'agent qui a fait l'action) ; le champ reste en surcharge optionnelle |
| `$DB_HOST/$DB_NAME/$DB_USER/$DB_PASS` (db.php) | `Database::getInstance()` (constantes `DB_*` de `config/config.php`) |
| `date('Y-m-d H:i:s')` (horloge PHP) | `NOW()` côté MySQL pour `created_at` / `date_paiement` |
| `clients_pressing` (table dédiée) | `clients` (table POS) |
| `suivi_articles_pressing` (table dédiée) | `audit_logs` (table POS) |
| `mode` en libellés accentués | constantes POS `ESPECES` / `MOBILEMONEY` / `VIREMENT` |

### Clés de session utilisées

| Clé | Usage |
|---|---|
| `$_SESSION['user_id']` | `pressing_commandes.user_id`, `pressing_paiements.user_id`, `audit_logs.user_id` |
| `$_SESSION['shop_id']` | scoping `pressing_commandes.shop_id` |
| `$_SESSION['role']` | `super_admin` / `admin` (droit de suppression) / `vendeur` |
| `$_SESSION['nom_complet']` | libellé de l'agent dans l'historique (fallback si `audit_logs.user_id` est NULL) |

---

## 11. CSS : le piège à ne pas déclencher

J'ai croisé les **51 classes** de `pressing/pressing/assets/app.css` avec les feuilles du POS (`styles.css`, `recharges.css`, `mobile-caisse.css`). **6 collisions** :

| Classe | POS | Pressing | Effet si importé tel quel |
|---|---|---|---|
| `.btn` | `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-small` | `.btn.primary`, `.btn.light`, `.btn.ghost` | **tous les boutons du POS changent** |
| `.card` | carte blanche ombrée, `card-header`, `card-body` | carte blanche à bordure | cartes de tout le POS déformées |
| `.btn-icon` | bouton icône carré | bouton rouge `×` | boutons icône de la POS cassés |
| `.modal-close` | `recharges.css` | `×` gris | modales recharges affectées |
| `.blue` / `.green` | `.stat-icon.blue` / `.stat-icon.green` | couleur de chiffre KPI | sans impact réel (scoping `.stat-icon`) |

**Règle absolue : préfixer TOUT en `pr-`.** Aucun nom de classe de l'appli source ne doit atteindre le POS. On obtient `pr-btn`, `pr-btn-primary`, `pr-card`, `pr-chip`, `pr-tablebox`, `pr-etat`, `pr-prog`, `pr-toast`…

### Composants POS à réutiliser plutôt que réinventer

| Besoin pressing | Composant POS à utiliser |
|---|---|
| Panneau latéral de détail | `.hotel-drawer` / `.hotel-drawer-backdrop` (standard sur les pages hôtel) — **pas** un `<dialog>`, sinon deux gestes différents à mémoriser |
| Tuiles KPI | `.stats-grid` / `.stat-card` / `.stat-value` / `.stat-label` |
| Boutons | `.btn` + `.btn-primary` / `.btn-secondary` / `.btn-ghost` / `.btn-small` / `.btn-danger` |
| Champs | `.form-group` + `.form-row` (pas de `.form-input` dans le POS) |
| Badges d'état | `.badge` + `.badge-success` / `.badge-warning` / `.badge-danger` |
| En-tête de page | `.page-header` |

Le `<dialog id="modal">` de l'appli source est remplacé par le drawer POS.

---

## 12. Accès et permissions

### Garde d'accès

Nouveau dans `app/controllers/Controller.php`, calqué sur `requireShopExtrasAccess()` :

```php
protected function requireShopPressingAccess(bool $json = true): bool
```

Helper dans `app/models/Shop.php` :

```php
public function hasPressingAccess($shopId): bool
```

Comportement :
- boutique introuvable / sans id → `false` (fail-closed) ;
- `super_admin` sans boutique → `false` (le contrôle est par boutique, comme `acces_extras`) ;
- erreur SQL → `false` (fail-closed), le caller renvoie 403.

### Matrice

| Action | Vendeur (boutique cochée) | Admin / Super_admin |
|---|---|---|
| Voir `/pressing` | ✅ | ✅ |
| Créer une commande | ✅ | ✅ |
| Avancer / reculer un vêtement | ✅ | ✅ |
| Encaisser | ✅ | ✅ |
| **Supprimer une commande** | ❌ 403 | ✅ |

La suppression reste conditionnée à l'état « tous les vêtements remis » (règle existante).

### Interface

- **Sidebar** (`app/views/layout/header.php`) : entrée « Pressing » visible seulement si le flag API `pressing` est vrai (lecture du cache `$_SESSION['feature_access_cache']`, comme le menu Hôtel). Encodé avec `$page = 'pressing'`.
- **Page `/shops`** : case à cocher « Accès au Pressing », dans le même bloc que « Accès aux Extras ». Badge dans la liste des boutiques (`shop-badge-extras` → `shop-badge-pressing`).
- **Feature flag JS** : `FEATURE_FLAGS.pressing` (calculé côté serveur comme `extras`).

---

## 13. Étapes d'implémentation

| # | Étape | Livrable |
|---|---|---|
| 1 | Migration SQL | `2026_11_04_pressing_module.sql` |
| 2 | Modèles | `PressingCommande`, `PressingArticle`, `PressingPaiement` — SQL pur, port fidèle de l'appli source |
| 3 | Contrôleur + routes | `PressingController` (stats, index, show, store) + garde d'accès + `routes/web.php` + `routes/api.php` |
| 4 | Actions d'écriture | advance, recede, advance-all, pay, delete — règles métier identiques (blocage remise si solde > 0) |
| 5 | Lookup facture OSAT | via `DgiSyncService` / proxy — remplace la base cross-project |
| 6 | Lien ventes POS | `vente_id` + `GET /api/pressing/ventes-sans-commande` + rattachement manuel d'une commande orphaned |
| 7 | Adaptation `Client` | normalisation du téléphone + `code_client` robuste + `nom_client` VARCHAR(120) |
| 8 | Vue + CSS + JS | `app/views/pressing.php`, `public/assets/css/pressing.css` (préfixe `pr-`), `public/assets/js/pressing.js` |
| 9 | Accès + navigation | flag API `pressing` (`RechargeAccessService`), sidebar, aucun réglage `/shops` |
| 10 | Tests + docs | PHPUnit (unit + integration), `ROUTES.md`, `AGENT.md` |
| 11 | Nettoyage | suppression du dossier `pressing/` (non suivi par git) après validation |

---

## 14. Tests et documentation

### Tests PHPUnit

**`tests/Unit/PressingEtapesTest.php`** — logique métier pure :
- `etat_commande()` : `encours` / `prete` / `remise` / `retard` (retard = `etape < 5` ET `date_dispo` dépassée) ;
- `solde_commande()` : total (articles × prix × coefficient) − paiements pressing ;
- retard ignoré quand `etape >= 5` ;
- commande vide → `encours`.

**`tests/Integration/PressingCommandeTest.php`** :
- création d'une commande liée à une vente (`vente_id` résolu) ;
- avancement d'un article + écriture dans `audit_logs` (`entity='pressing_article'`) ;
- **remise bloquée si solde > 0** (message explicite) ;
- `advance-all` : bloque les remises non soldées, avance les autres ;
- scoping boutique : une commande d'une autre boutique est invisible et inaccessible ;
- suppression refusée si un article n'est pas remis ;
- fusion client : un numéro déjà présent dans `clients` n'est pas dupliqué.

### Documentation à mettre à jour

- `ROUTES.md` — table des routes (section « Routes Web » + « Routes API »)
- `AGENT.md` — section « Module Pressing » (miroir de la section Hôtel : périmètre, tables, accès, pièges)
- `docs/PLAN_PRESSING.md` — ce document, mis à jour au fil de l'implémentation

---

## 15. Risques identifiés

| # | Risque | Impact | Traitement |
|---|---|---|---|
| R1 | Pas de reprise de l'historique de production | Les dossiers de prod repartent de zéro | Accepté (D8). Ils se recréent depuis la facture DGI via `/pressing` |
| R2 | Aucune boutique n'a `isf = A1615551X` | Le lookup facture échoue tant que la boutique n'est pas configurée | Action manuelle explicite au §2/B3 |
| R3 | `EMULATE_PREPARES = false` | Un placeholder nommé réutilisé → `SQLSTATE[HY093]` | Une clé par occurrence (`:k1`, `:k2`, `:k3`…). Vérifier avec `rg "LIKE :k"` avant chaque release |
| R4 | Params AltoRouter en tableau | `function show($id)` reçoit `['id' => 42]` | `$id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;` |
| R5 | `use` manquant dans le fichier de routes | `Class "PressingController" not found` | Ajouter `use App\Controllers\PressingController;` **dans le même lot** que la route |
| R6 | Collisions CSS | Toute l'interface POS déformée | Préfixe `pr-` obligatoire, vérifié par grep avant livraison |
| R7 | Décalage PHP/MySQL en heure d'été (Berlin vs Kinshasa) | `date_depot` faux d'1 h en été | `Africa/Kinshasa` posé dans le module. **Problème global au POS, non traité ici** |
| R8 | `Database::execute()` renvoie toujours `true` | Impossible de savoir si un UPDATE a matché | Re-vérifier par un `SELECT` quand le résultat compte |
| R9 | `date_dispo` calculé à la création | Un article en retard dès la création si le délai est court | Comportement identique à la source — conservé |
| **R10** | **Le pressing encaisse en double** (vente POS + pressing) | Le client paie deux fois | **À confirmer** : le pressing doit-il encaisser la totalité, ou seulement les compléments (frais de retard, supplément express) au-dessus du montant déjà réglé ? Si c'est le 2e cas, `pressing_paiements` ne stocke que les compléments et le solde de départ est `total pressing − montant vente POS déjà payé`. Voir §3.3 |
| **R11** | **`clients.code_client` UNIQUE + génération par `MAX(id)+1`** | Collision en cas de création concurrente (le pressing crée des clients en flux continu) | Calcul du `code_client` en base au moment de l'insert, avec retry sur collision. À traiter dans `Client::create()` |
| **R12** | **Numéros de téléphone dans deux formats** (`+243 89 548 96 08` vs `0895511485`) | La recherche client échoue → client dupliqué | Normalisation obligatoire avant `findByNumero()` : retirer espaces, `+`, préfixe `243`, `0` de tête |
| **R13** | **`clients.nom_client` VARCHAR(50)** | Un nom de client long est tronqué | Élargi à VARCHAR(120) par la migration |
| **R14** | **`audit_logs` n'a pas de FK vers `pressing_articles`** | Pas d'intégrité référentielle sur l'historique | Acceptable : l'historique survit à la suppression (comportement souhaité). Requête via `AuditLog::getByEntity()` |

---

## 16. Hors périmètre

- Le pressing comme **produit** vendu dans la caisse (le plan hôtel V1 le mentionne comme « service pressing » vendu) — c'est le module de suivi, pas la vente.
- Le multi-pressing (plusieurs ateliers) : le scoping est **par boutique**, donc un pressing = une boutique.
- Le lien automatique commande ↔ séjour hôtel.
- L'impression d'un ticket de suivi client (ticket de dépôt).
- Le stock de produits d'entretien (détachant, amidon).
- La purge automatique de l'historique des étapes (aucun script ne purge `audit_logs` aujourd'hui).

---

*Plan rédigé le 2026-11-04. Révision 2 : modélisation revue — 5 tables → 3 tables après croisement avec le schéma et le code du POS. Décisions D1–D5 validées. En attente de validation du plan complet avant toute écriture de code.*
