# 🍽️ Module Restaurant & 🧺 Module Pressing

## Cahier des charges d'intégration

> **Règle d'or : ne rien casser.** Ce document décrit l'ajout de **deux nouveaux modules** (Restaurant, Pressing) au POS existant, en respectant strictement l'architecture MVC en place (`app/controllers`, `app/models`, `app/views`, `routes/web.php`, `routes/api.php`). Aucun fichier existant lié aux modules Caisse / Produits / Ventes / Clients / Utilisateurs ne doit être modifié dans sa logique métier — seulement des points d'extension additifs (nouveau menu sidebar, nouvelles routes, nouvelles tables).

---

## 1. Principe d'activation par boutique

Le système gère déjà un **type de service par boutique** (`shops.type_service`, voir `App\Models\ServiceType` et `App\Controllers\ServiceTypeController`) qui personnalise dynamiquement le libellé "Caisse" dans la sidebar (`app/views/layout/header.php`, variable `$serviceType`).

Les modules Restaurant et Pressing réutilisent ce même mécanisme :

- Un type de service `Restaurant` et un type de service `Pressing` existent (ou sont ajoutés) dans `service_types`.
- Quand une boutique (`shops.type_service`) est réglée sur **Restaurant**, l'entrée de menu **Restaurant** apparaît dans la sidebar pour les utilisateurs de cette boutique.
- Quand elle est réglée sur **Pressing**, l'entrée de menu **Pressing** apparaît.
- Le `super_admin` voit les deux menus sur toutes les boutiques concernées (comportement identique à celui déjà en place pour `/shops`, `/otp-codes`, etc. dans `header.php`).
- Aucune donnée n'est mélangée entre boutiques : toutes les nouvelles tables portent une colonne `shop_id` (comme `ventes`, `clients`, `produits`).

---

## 2. Nouvelles tables SQL (aucune table existante modifiée)

### 2.1 Module Restaurant

```sql
CREATE TABLE restaurant_tables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  numero VARCHAR(20) NOT NULL,
  nom VARCHAR(100) DEFAULT NULL,
  capacite INT NOT NULL DEFAULT 4,
  etat ENUM('libre','occupee','reservee','nettoyage') NOT NULL DEFAULT 'libre',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(id)
);

CREATE TABLE restaurant_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  nom VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (shop_id) REFERENCES shops(id)
);

CREATE TABLE restaurant_menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  categorie_id INT NOT NULL,
  nom VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  prix DECIMAL(12,2) NOT NULL,
  temps_preparation INT DEFAULT 0 COMMENT 'minutes',
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(id),
  FOREIGN KEY (categorie_id) REFERENCES restaurant_categories(id)
);

CREATE TABLE restaurant_commandes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  table_id INT NOT NULL,
  serveur_id INT NOT NULL COMMENT 'utilisateurs.id',
  statut ENUM('ouverte','envoyee_cuisine','servie','payee','annulee') NOT NULL DEFAULT 'ouverte',
  sous_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  taxes DECIMAL(12,2) NOT NULL DEFAULT 0,
  remise DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  vente_id INT DEFAULT NULL COMMENT 'lien vers ventes.id une fois payée',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(id),
  FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
  FOREIGN KEY (serveur_id) REFERENCES utilisateurs(id),
  FOREIGN KEY (vente_id) REFERENCES ventes(id)
);

CREATE TABLE restaurant_commande_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  quantite INT NOT NULL DEFAULT 1,
  prix_unitaire DECIMAL(12,2) NOT NULL,
  statut_cuisine ENUM('en_attente','en_preparation','pret','servi') NOT NULL DEFAULT 'en_attente',
  started_at DATETIME DEFAULT NULL COMMENT 'horodatage du clic "Commencer" — sert de base au calcul auto du temps de préparation',
  commentaire VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (commande_id) REFERENCES restaurant_commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES restaurant_menu_items(id)
);
```

### 2.2 Module Pressing

```sql
CREATE TABLE pressing_depots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  numero VARCHAR(30) NOT NULL UNIQUE,
  client_id INT NOT NULL,
  statut ENUM('recu','en_lavage','en_sechage','en_repassage','pret','livre') NOT NULL DEFAULT 'recu',
  sous_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  remise DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  vente_id INT DEFAULT NULL COMMENT 'lien vers ventes.id une fois encaissé',
  qr_code VARCHAR(255) DEFAULT NULL,
  code_barre VARCHAR(255) DEFAULT NULL,
  date_reception DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_prevue DATETIME DEFAULT NULL,
  date_livraison DATETIME DEFAULT NULL,
  created_by INT NOT NULL COMMENT 'utilisateurs.id',
  FOREIGN KEY (shop_id) REFERENCES shops(id),
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (vente_id) REFERENCES ventes(id),
  FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
);

CREATE TABLE pressing_articles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  depot_id INT NOT NULL,
  nom_article VARCHAR(100) NOT NULL,
  quantite INT NOT NULL DEFAULT 1,
  etat_initial VARCHAR(255) DEFAULT NULL,
  commentaire VARCHAR(255) DEFAULT NULL,
  service ENUM('lavage','repassage','nettoyage_sec','express') NOT NULL,
  prix_unitaire DECIMAL(12,2) NOT NULL,
  prix_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (depot_id) REFERENCES pressing_depots(id) ON DELETE CASCADE
);
```

> Les deux modules **réutilisent** `clients`, `utilisateurs`, `shops`, `ventes` (pour l'encaissement) et `service_types`. Aucune de ces tables n'est modifiée en structure (seulement lues/référencées via clé étrangère).

---

## 3. Architecture MVC (fichiers à créer)

### 3.1 Restaurant

| Type | Fichier | Rôle |
|---|---|---|
| Model | `app/models/RestaurantTable.php` | CRUD tables |
| Model | `app/models/RestaurantCategory.php` | CRUD catégories menu |
| Model | `app/models/RestaurantMenuItem.php` | CRUD plats |
| Model | `app/models/RestaurantOrder.php` | Commandes + détails, calcul totaux |
| Controller | `app/controllers/RestaurantTableController.php` | API tables (CRUD, changement d'état) |
| Controller | `app/controllers/RestaurantMenuController.php` | API catégories + plats |
| Controller | `app/controllers/RestaurantOrderController.php` | Créer/modifier commande, envoyer en cuisine, paiement |
| Controller | `app/controllers/RestaurantKitchenController.php` | Endpoints écran cuisine (liste avec statut calculé auto + démarrage manuel "Commencer" + passage manuel "Servi") |
| Controller | `app/controllers/RestaurantReportController.php` | Rapports restaurant |
| View | `app/views/restaurant/tables.php` | Cartes colorées des tables |
| View | `app/views/restaurant/menu.php` | Gestion catégories + plats |
| View | `app/views/restaurant/commandes.php` | Prise de commande par table |
| View | `app/views/restaurant/cuisine.php` | Écran cuisine (auto-refresh AJAX) |
| View | `app/views/restaurant/rapports.php` | Rapports |

### 3.2 Pressing

| Type | Fichier | Rôle |
|---|---|---|
| Model | `app/models/PressingDepot.php` | CRUD dépôts + statuts |
| Model | `app/models/PressingArticle.php` | Articles d'un dépôt |
| Controller | `app/controllers/PressingDepotController.php` | Créer dépôt, suivi statut, retrait, recherche/QR |
| Controller | `app/controllers/PressingReportController.php` | Rapports pressing |
| View | `app/views/pressing/depot.php` | Formulaire de dépôt |
| View | `app/views/pressing/suivi.php` | Barre de progression par dépôt |
| View | `app/views/pressing/retrait.php` | Scan QR / recherche + encaissement |
| View | `app/views/pressing/historique.php` | Liste + filtres (date, client, statut) |
| View | `app/views/pressing/rapports.php` | Rapports |

### 3.3 Routage (ajouts uniquement, dans `routes/web.php` et `routes/api.php`)

```php
// routes/web.php — pages
Router::get("/restaurant/tables", [PageController::class, 'restaurantTables']);
Router::get("/restaurant/menu", [PageController::class, 'restaurantMenu']);
Router::get("/restaurant/commandes", [PageController::class, 'restaurantCommandes']);
Router::get("/restaurant/cuisine", [PageController::class, 'restaurantCuisine']);
Router::get("/restaurant/rapports", [PageController::class, 'restaurantRapports']);

Router::get("/pressing/depot", [PageController::class, 'pressingDepot']);
Router::get("/pressing/suivi", [PageController::class, 'pressingSuivi']);
Router::get("/pressing/retrait", [PageController::class, 'pressingRetrait']);
Router::get("/pressing/historique", [PageController::class, 'pressingHistorique']);
Router::get("/pressing/rapports", [PageController::class, 'pressingRapports']);

// routes/api.php — actions
Router::get("/api/restaurant/tables", [RestaurantTableController::class, 'index']);
Router::post("/api/restaurant/tables", [RestaurantTableController::class, 'create']);
Router::put("/api/restaurant/tables/[i:id]", [RestaurantTableController::class, 'update']);
Router::delete("/api/restaurant/tables/[i:id]", [RestaurantTableController::class, 'delete']);

Router::get("/api/restaurant/menu", [RestaurantMenuController::class, 'index']);
Router::post("/api/restaurant/categories", [RestaurantMenuController::class, 'createCategory']);
Router::post("/api/restaurant/plats", [RestaurantMenuController::class, 'createItem']);

Router::post("/api/restaurant/commandes", [RestaurantOrderController::class, 'create']);
Router::put("/api/restaurant/commandes/[i:id]", [RestaurantOrderController::class, 'update']);
Router::post("/api/restaurant/commandes/[i:id]/envoyer-cuisine", [RestaurantOrderController::class, 'sendToKitchen']);
Router::post("/api/restaurant/commandes/[i:id]/paiement", [RestaurantOrderController::class, 'pay']);

Router::get("/api/restaurant/cuisine", [RestaurantKitchenController::class, 'index']);
Router::post("/api/restaurant/cuisine/[i:detailId]/commencer", [RestaurantKitchenController::class, 'start']);
Router::post("/api/restaurant/cuisine/[i:detailId]/servi", [RestaurantKitchenController::class, 'markServed']);

Router::get("/api/pressing/depots", [PressingDepotController::class, 'index']);
Router::post("/api/pressing/depots", [PressingDepotController::class, 'create']);
Router::get("/api/pressing/depots/search", [PressingDepotController::class, 'search']);
Router::put("/api/pressing/depots/[i:id]/statut", [PressingDepotController::class, 'updateStatus']);
Router::post("/api/pressing/depots/[i:id]/paiement", [PressingDepotController::class, 'pay']);
Router::post("/api/pressing/depots/[i:id]/retrait", [PressingDepotController::class, 'withdraw']);
```

Tous les `Controller` héritent de `App\Controllers\Controller` et utilisent `requireAuth()`, `requireAdmin()`, `isSuperAdmin()`, `getShopId()` déjà disponibles (voir `app/controllers/Controller.php`) pour l'isolation multi-boutique et la protection des routes — **exactement le même schéma que `ClientController` / `SaleController`**.

---

## 4. Réutilisation des briques existantes

| Besoin | Composant existant réutilisé |
|---|---|
| Authentification / rôles | `AuthController`, sessions `$_SESSION['role']`, `$_SESSION['shop_id']` |
| Clients (dépôt pressing) | `App\Models\Client` (`findByNumero`, `create`, `getAll`) |
| Encaissement / facture | `App\Models\Sale::create()` — une commande restaurant payée ou un retrait pressing payé insère une ligne dans `ventes` (+ `details_vente` si pertinent) avec `service = 'restaurant'` ou `service = 'pressing'` (colonne `ventes.service` déjà existante, voir `App\Models\Sale::create`) |
| Facture / impression | `InvoiceController`, `app/views/facture.php`, `facture-ticket.php` |
| Rapports | Étendre `analytics.php` / `App\Controllers\PageController::analytics` en filtrant par `service` sans toucher aux requêtes existantes — ou pages dédiées `restaurant/rapports.php`, `pressing/rapports.php` qui interrogent `ventes WHERE service = ...` |
| Notifications | `Controller::notify()`, `notifyShopAdmins()` (ex : notifier la cuisine qu'une commande arrive) |
| Design (sidebar, cartes, tableaux, modales) | `app/views/layout/header.php`, `footer.php`, classes CSS déjà utilisées dans `produits.php` / `shops.php` |

Ainsi, **les ventes issues du Restaurant et du Pressing apparaissent nativement dans l'historique (`/historique`) et les rapports (`/analytics`) existants**, sans aucune modification de leur code, simplement en peuplant la colonne `service` déjà présente sur `ventes`.

---

## 5. Flux fonctionnels

### 5.1 Restaurant

1. Serveur ouvre `/restaurant/tables` → sélectionne une table libre.
2. Redirection vers `/restaurant/commandes?table_id=...` → ajoute des plats depuis le menu, modifie quantités, supprime des lignes.
3. Calcul auto sous-total / taxes (réutilise `App\Models\Tax`) / remise / total en JS (identique à la logique panier de `caisse.php`).
4. **Envoyer en cuisine** → `POST /api/restaurant/commandes/{id}/envoyer-cuisine` → statut commande `envoyee_cuisine`, chaque détail passe à `en_attente`.
5. Écran `/restaurant/cuisine` : polling AJAX (`setInterval` + `fetch('/api/restaurant/cuisine')`) toutes les 5 à 10 s. Le cycle de statut est **semi-automatique**, pour éviter les clics inutiles :
   - **En attente** → le cuisinier clique **Commencer** (`POST /api/restaurant/cuisine/{detailId}/commencer`) : `statut_cuisine = 'en_preparation'`, `started_at = NOW()`.
   - **En préparation** → *aucun clic requis*. Le serveur calcule automatiquement l'état à chaque lecture en comparant `NOW()` à `started_at + temps_preparation` (colonne déjà prévue sur `restaurant_menu_items`) :
     ```sql
     SELECT d.*,
       CASE
         WHEN d.statut_cuisine = 'en_preparation'
              AND NOW() >= DATE_ADD(d.started_at, INTERVAL m.temps_preparation MINUTE)
         THEN 'pret'
         ELSE d.statut_cuisine
       END AS statut_affiche
     FROM restaurant_commande_details d
     JOIN restaurant_menu_items m ON m.id = d.menu_item_id
     ```
     Dès que le délai est écoulé, le plat apparaît en **Prêt** dans le prochain rafraîchissement — le front-end affiche aussi un **compte à rebours visuel** (barre de progression) calculé côté client à partir de `started_at` et `temps_preparation`, sans attendre le serveur.
     *(Le passage effectif en base de `en_preparation` → `pret` est persisté au prochain appel de `RestaurantKitchenController::index` ou via une tâche courte, pour garder l'historique cohérent.)*
   - **Prêt** → le plat est visible côté serveur (notification), aucun clic cuisinier requis.
   - **Servi** → reste une action manuelle du **serveur** (`POST /api/restaurant/cuisine/{detailId}/servi`) car c'est un geste physique (dépose à table) que le système ne peut pas deviner.
6. Une fois tous les plats servis, le serveur clôture la commande : **Paiement** (espèces/carte/mobile money/mixte, même composant que `caisse.php`) → insertion dans `ventes` avec `service='restaurant'`, génération facture via `InvoiceController`.
7. La table repasse à `libre` (ou `nettoyage`).

### 5.2 Pressing

1. `/pressing/depot` : rechercher un client existant (`GET /api/client/search`, déjà en place) ou en créer un.
2. Ajouter les articles (nom, quantité, état initial, commentaire, service, prix unitaire) → `prix_total` calculé en JS et revalidé côté serveur.
3. Soumission → `POST /api/pressing/depots` : génère `numero`, `qr_code`, `code_barre`, statut initial `recu`.
4. Impression du reçu (réutilise le moteur d'impression déjà présent pour les factures, ex. `facture-ticket.php`) contenant numéro, QR code, code-barre, client, date, montant, date prévue.
5. `/pressing/suivi` : barre de progression pilotée par `pressing_depots.statut` (`recu → en_lavage → en_sechage → en_repassage → pret → livre`).
6. `/pressing/retrait` : scan QR ou recherche par numéro → si non payé, encaissement (insertion `ventes`, `service='pressing'`) ; sinon validation directe → statut `livre`.
7. `/pressing/historique` : liste filtrable par date, client, statut.

---

## 6. Rapports

Réutilisation de la même approche que `App\Controllers\PageController::analytics` (agrégations SQL sur `ventes`/`details` filtrées par `service`) :

- **Restaurant** : nb commandes, CA, plats les plus vendus (`GROUP BY menu_item_id`), ventes par jour/mois, ventes par serveur (`GROUP BY serveur_id`).
- **Pressing** : nb dépôts, revenus, services les plus utilisés (`GROUP BY service` dans `pressing_articles`), clients fidèles (`GROUP BY client_id`), revenus journaliers/mensuels.

---

## 7. Sécurité

- Toutes les requêtes SQL via **PDO préparées** (`App\Core\Database`), comme le reste du projet.
- Validation des entrées côté contrôleur (types, montants ≥ 0, `shop_id` cohérent avec la session).
- `requireAuth()` / `requireAdmin()` / `requireSuperAdmin()` sur chaque endpoint API sensible.
- Isolation stricte par `shop_id` : un admin/vendeur ne voit que les tables, commandes, dépôts de sa boutique ; le `super_admin` voit tout.
- Traçabilité via `Controller::logAudit()` sur les actions critiques (changement statut, paiement, suppression).

---

## 8bis. Scénario d'une journée type (simulation)

> Objectif : illustrer concrètement qui fait quoi, à quel moment, sur quel écran, pour une boutique de type **Restaurant** et une boutique de type **Pressing**.

### 🍽️ Journée type — Boutique "Restaurant"

**Acteurs** : le système ne compte que 3 rôles réels (`super_admin`, `admin`, `vendeur` — voir `App\Models\User::create()`). "Serveur" et "Cuisinier" ci-dessous sont **tous deux des utilisateurs `vendeur`**, distingués uniquement par l'écran qu'ils utilisent, pas par un rôle en base. `super_admin` = siège, `admin` = gérant du resto.

| Heure | Acteur | Action | Écran / Route | Effet système |
|---|---|---|---|---|
| 08h00 | Admin | Se connecte, vérifie que les tables sont toutes `libre` après le nettoyage de la veille | `/restaurant/tables` | Lecture `restaurant_tables` |
| 08h15 | Admin | Met à jour le menu du jour (retire "Poisson braisé" en rupture) | `/restaurant/menu` | `disponible = 0` sur `restaurant_menu_items` |
| 12h00 | Serveur | Un client s'installe à la Table 5 → il clique sur la carte "Table 5" (verte = libre) | `/restaurant/tables` → `/restaurant/commandes?table_id=5` | Table passe à `occupee` |
| 12h02 | Serveur | Ajoute 2x "Poulet DG", 1x "Jus de gingembre" au panier de la commande | `/restaurant/commandes` | Calcul auto sous-total/taxes/total en JS |
| 12h03 | Serveur | Clique **Envoyer en cuisine** | `POST /api/restaurant/commandes/{id}/envoyer-cuisine` | Commande → `envoyee_cuisine`, détails → `en_attente` |
| 12h04 | Cuisinier | Voit la nouvelle commande apparaître automatiquement (polling AJAX 5-10s) sur l'écran cuisine | `/restaurant/cuisine` | `GET /api/restaurant/cuisine` |
| 12h05 | Cuisinier | Clique **Commencer** sur "Poulet DG" (seul clic requis) | `POST /api/restaurant/cuisine/{id}/commencer` | `en_attente → en_preparation`, `started_at = NOW()` |
| 12h05-12h20 | Système | Affiche un compte à rebours (15 min = `temps_preparation` du plat) sans aucune action cuisinier | Écran cuisine (front-end) | Barre de progression calculée côté client |
| 12h20 | Système | Le délai est écoulé → le plat passe automatiquement **Prêt** au prochain rafraîchissement | Écran cuisine | `en_preparation → pret` (calcul auto, pas de clic) |
| 12h21 | Serveur | Voit la notification "Table 5 : plat prêt" (via `notify()`), va servir | Notification cloche | `notifyShopAdmins()` / `notify()` |
| 12h22 | Serveur | Marque le plat **Servi** une fois apporté à table | Écran commande | `pret → servi` |
| 12h45 | Client | Demande l'addition | — | — |
| 12h46 | Serveur | Clique **Paiement**, choisit "Mixte" (espèces + Mobile Money) | `/restaurant/commandes` → modale paiement (réutilise composant `caisse.php`) | `POST /api/restaurant/commandes/{id}/paiement` |
| 12h46 | Système | Insère la vente dans `ventes` avec `service='restaurant'`, génère la facture | `App\Models\Sale::create()`, `InvoiceController` | Facture imprimable, visible dans `/historique` |
| 12h47 | Système | Table 5 repasse automatiquement en `nettoyage` puis `libre` après validation serveur | `/restaurant/tables` | `restaurant_tables.etat` |
| 18h00 | Admin | Consulte le rapport du jour : CA, plats les plus vendus, ventes par serveur | `/restaurant/rapports` | Agrégation sur `ventes WHERE service='restaurant'` |
| 20h00 | Super Admin | Depuis le siège, compare le CA du Restaurant avec les autres boutiques dans `/analytics` | `/analytics` | Vue consolidée multi-boutique |

### 🧺 Journée type — Boutique "Pressing"

**Acteurs** : `super_admin`, `admin` (gérant), et l'"agent au comptoir" qui est également un utilisateur `vendeur` (même rôle que le serveur du Restaurant, aucune distinction en base par défaut).

| Heure | Acteur | Action | Écran / Route | Effet système |
|---|---|---|---|---|
| 08h30 | Agent | Un client apporte 3 chemises + 1 costume | `/pressing/depot` | — |
| 08h31 | Agent | Recherche le client par numéro (déjà existant) ou crée une fiche client | `GET /api/client/search` | Réutilise `App\Models\Client` |
| 08h33 | Agent | Ajoute les articles : 3x Chemise (Lavage+Repassage), 1x Costume (Nettoyage à sec, Express) | `/pressing/depot` | Calcul auto `prix_total` |
| 08h34 | Agent | Valide le dépôt → le système génère numéro, QR code, code-barre | `POST /api/pressing/depots` | `pressing_depots.statut = 'recu'` |
| 08h34 | Agent | Imprime le reçu (numéro, QR, client, date, montant, date prévue) | Impression thermique (réutilise moteur `facture-ticket.php`) | — |
| 09h00 | Agent | Met à jour le statut : les articles partent en machine | `/pressing/suivi` | `recu → en_lavage` (barre de progression avance) |
| 11h00 | Agent | Statut suivant | `/pressing/suivi` | `en_lavage → en_sechage` |
| 13h00 | Agent | Statut suivant | `/pressing/suivi` | `en_sechage → en_repassage` |
| 15h00 | Agent | Articles repassés, statut **Prêt** | `/pressing/suivi` | `en_repassage → pret` |
| 15h01 | Système | Notifie le client (si téléphone renseigné) que sa commande est prête | `notify()` / SMS optionnel | — |
| 17h00 | Client | Revient récupérer ses vêtements | — | — |
| 17h01 | Agent | Scanne le QR code du reçu (ou recherche par numéro) | `/pressing/retrait` | `GET /api/pressing/depots/search` |
| 17h02 | Agent | Le système affiche : dépôt non payé → clique **Encaisser** | Modale paiement (réutilise composant caisse) | `POST /api/pressing/depots/{id}/paiement` → insertion `ventes` avec `service='pressing'` |
| 17h03 | Agent | Valide le retrait | `POST /api/pressing/depots/{id}/retrait` | `pret → livre`, `date_livraison` renseignée |
| 18h30 | Admin | Consulte l'historique du jour, filtre par statut "livré" | `/pressing/historique` | Filtres date/client/statut |
| 20h00 | Admin | Consulte le rapport : nb dépôts, revenus, services les plus utilisés, clients fidèles | `/pressing/rapports` | Agrégation sur `pressing_depots` / `pressing_articles` |
| 20h05 | Super Admin | Vérifie dans `/analytics` que les ventes Pressing du jour remontent bien dans le CA global | `/analytics` | `ventes WHERE service='pressing'` |

### Points clés illustrés par la simulation

- **Pas de nouveau rôle** : "serveur", "cuisinier", "agent pressing" sont tous le rôle `vendeur` existant — la distinction est fonctionnelle (quel écran ils utilisent au quotidien), pas une isolation technique par défaut. Pour une isolation stricte optionnelle, voir §8bis-bis ci-dessous.
- **Aucune double-saisie** : le paiement (Restaurant ou Pressing) alimente directement `ventes`, donc `/historique` et `/analytics` existants montrent tout sans code additionnel dans ces pages.
- **Traçabilité complète** : chaque changement de statut (table, plat, dépôt) est horodaté et peut être audité via `Controller::logAudit()`.
- **Isolation multi-boutique** : le `super_admin` a une vue transverse ; `admin`/`vendeur` ne voient que les données de leur `shop_id`.

### 8bis-bis. (Optionnel) Isoler réellement serveur / cuisinier / agent pressing

Si l'isolation stricte par fonction est souhaitée (ex : empêcher un cuisinier d'ouvrir l'écran commandes), il faut une extension **additive**, sans toucher au système de rôles existant :

- Ajouter une colonne optionnelle `utilisateurs.poste` (ex: `NULL`, `serveur`, `cuisinier`, `agent_pressing`) — nullable, valeur par défaut `NULL`, donc **aucun impact** sur les utilisateurs existants ni sur `AuthController` / `User::all()`.
- Dans les nouveaux contrôleurs Restaurant/Pressing uniquement, vérifier `$_SESSION['poste']` en plus de `$_SESSION['role']` pour restreindre l'accès (ex: un `vendeur` avec `poste='cuisinier'` ne peut appeler que les endpoints `RestaurantKitchenController`).
- La sidebar (`header.php`) peut alors n'afficher que le lien correspondant au `poste` de l'utilisateur.

Sans cette extension, tout `vendeur` de la boutique peut accéder à tous les écrans Restaurant/Pressing de sa boutique (comme n'importe quel autre écran POS aujourd'hui) — ce qui est suffisant pour un usage en petite équipe où la confiance interne existe.

---

## 8. Feuille de route (checklist détaillée, étape par étape)

> Ordre d'implémentation recommandé : **Étape 0 → 1 → 2 ... → 9**. Chaque étape est testable indépendamment avant de passer à la suivante. On coche au fur et à mesure.

### Étape 0 — Socle commun (préalable aux deux modules)

- [x] Migration SQL `migrations/xxx_restaurant_pressing.sql` : créer les 7 tables (`restaurant_tables`, `restaurant_categories`, `restaurant_menu_items`, `restaurant_commandes`, `restaurant_commande_details`, `pressing_depots`, `pressing_articles`)
- [x] Ajouter les types de service `Restaurant` et `Pressing` dans `service_types` — `Restaurant` existait déjà (seed `multi_shop_evolution.sql`), `Pressing` ajouté via `migrations/add_pressing_service_type.sql` (exécutée)
- [x] Vérifier que `ventes.service` accepte bien les valeurs `restaurant` / `pressing` — colonne `VARCHAR(50)` sans contrainte ENUM (`migrations/add_service_column.sql`), aucune modification requise
- [x] Ajouter les entrées de menu conditionnelles dans `app/views/layout/header.php` — liens "Restaurant" (`/restaurant/tables`) et "Pressing" (`/pressing/depot`) affichés via `$showRestaurantMenu` / `$showPressingMenu` calculés dans `PageController::render()` (visibles si la boutique a ce type de service, ou toujours pour `super_admin`). Routes cibles à créer aux Étapes 1 et 6 (404 attendu en attendant).

### Étape 1 — Restaurant : Gestion des tables ✅

- [x] Migration `migrations/create_restaurant_pressing_tables.sql` (les 7 tables — exécutée en base)
- [x] Model `app/models/RestaurantTable.php` (CRUD + filtre `shop_id`)
- [x] Controller `app/controllers/RestaurantTableController.php` (`index`, `create`, `update`, `delete`, `updateState`)
- [x] Route page `/restaurant/tables` (`PageController::restaurantTables`) + routes API `/api/restaurant/tables[...]`
- [x] Vue `app/views/restaurant/tables.php` : cartes colorées par état (libre/occupée/réservée/nettoyage), modale CRUD, sélecteur de changement d'état rapide
- [ ] **Test manuel à faire** : ouvrir `/restaurant/tables`, créer une table, changer son état via le sélecteur, modifier, supprimer — vérifier l'isolation par `shop_id` (un `admin`/`vendeur` ne voit que les tables de sa boutique, `super_admin` voit tout + peut choisir la boutique à la création)

### Étape 2 — Restaurant : Catégories & Menu ✅

- [x] Model `app/models/RestaurantCategory.php` + `app/models/RestaurantMenuItem.php`
- [x] Controller `app/controllers/RestaurantMenuController.php` (CRUD catégories + plats, upload image dans `public/assets/img/restaurant/`)
- [x] Route page `/restaurant/menu` + routes API `/api/restaurant/categories[...]`, `/api/restaurant/plats[...]`
- [x] Vue `app/views/restaurant/menu.php` : chips de catégories (édition/suppression inline), grille de plats avec image/prix/temps de préparation, badge disponible/indisponible cliquable (toggle rapide)
- [ ] **Test manuel à faire** : créer une catégorie, créer un plat avec image et `temps_preparation`, cliquer sur le badge pour activer/désactiver la disponibilité, modifier/supprimer

### Étape 3 — Restaurant : Commandes ✅

- [x] Model `app/models/RestaurantOrder.php` (création commande, ajout/modif/suppression lignes, recalcul auto sous-total/taxes/remise/total à chaque changement)
- [x] Controller `app/controllers/RestaurantOrderController.php` (`create`, `get`, `addItem`, `updateItem`, `removeItem`, `setRemise`, `sendToKitchen`, `cancel`, `pay`)
- [x] Route page `/restaurant/commandes?table_id=...` + routes API `/api/restaurant/commandes[...]`
- [x] Vue `app/views/restaurant/commandes.php` : grille de sélection de table (si aucune table choisie), onglets catégories, grille de plats cliquables, panier avec +/- quantité, suppression ligne, remise éditable, totaux en direct
- [x] Boutons **Envoyer en cuisine** / **Paiement** (mode espèces/carte/mobile money/mixte) / **Annuler**
- [x] Paiement → crée une ligne `ventes` (`service='restaurant'`, sans `details_vente` car les plats ne sont pas des `produits`) et redirige vers la facture existante (`/facture?ref=...`)
- [ ] **Test manuel à faire** : sélectionner une table (passe à `occupee`), ajouter des plats, changer quantités, envoyer en cuisine, payer (table passe à `nettoyage`, facture générée, vente visible dans `/historique`)

### Étape 4 — Restaurant : Écran Cuisine (semi-automatique) ✅

- [x] Colonne `started_at` sur `restaurant_commande_details` (déjà dans la migration Étape 0)
- [x] Controller `app/controllers/RestaurantKitchenController.php` (`index` avec calcul auto du statut via `temps_preparation` + persistance auto `en_preparation → pret`, `start`, `markServed`)
- [x] Route page `/restaurant/cuisine` + routes API `/api/restaurant/cuisine/[...]/commencer` et `.../servi`
- [x] Vue `app/views/restaurant/cuisine.php` : cartes par commande (table, heure, plats), bouton **Commencer** (seul clic requis), barre de progression animée calculée depuis `started_at`/`temps_preparation`, badge **Prêt** automatique, bouton **Marquer servi**
- [x] Polling AJAX (`setInterval` 6s) + interpolation visuelle de la barre de progression toutes les secondes côté client
- [x] Notification (`notifyShopAdmins`) envoyée dès qu'un plat passe **Prêt**
- [x] Commande passe automatiquement à `servie` quand tous ses plats sont servis
- [ ] **Test manuel à faire** : cycle complet En attente → clic Commencer → attente automatique (barre de progression) → Prêt (sans clic) → Marquer servi

### Étape 5 — Restaurant : Paiement & Rapports ✅

- [x] Modale de paiement (espèces/carte/mobile money/mixte) intégrée dans `commandes.php` (Étape 3)
- [x] `RestaurantOrderController::pay()` → insertion `ventes` (`service='restaurant'`) + `App\Models\Sale::create()`, redirection vers la facture existante (`InvoiceController::showByRef`)
- [x] Retour automatique de la table à `nettoyage` après paiement (Étape 3)
- [x] Controller `app/controllers/RestaurantReportController.php` + vue `app/views/restaurant/rapports.php` (nb commandes, CA, plats les plus vendus, ventes par jour/mois/serveur) — requêtes basées sur `ventes WHERE service='restaurant'` et `restaurant_commande_details`
- [ ] **Test manuel à faire** : ouvrir `/restaurant/rapports`, vérifier que la vente restaurant apparaît aussi dans `/historique` et `/analytics` existants sans régression

### Étape 6 — Pressing : Dépôt ✅

- [x] Model `app/models/PressingDepot.php` + `app/models/PressingArticle.php`
- [x] Controller `app/controllers/PressingDepotController.php` (`index`, `get`, `search`, `create`, `updateStatus`, `pay`, `withdraw`)
- [x] Route page `/pressing/depot` + routes API `/api/pressing/depots[...]`
- [x] Vue `app/views/pressing/depot.php` : recherche/création client (réutilise `GET /api/client/search`, `POST /api/client`), ajout articles multiples (nom, quantité, état initial, service, prix unitaire → total auto), récapitulatif avec remise éditable
- [x] Génération QR code **côté client en JS** (CDN `qrcode.js`, comme `Chart.js` déjà utilisé) — évite d'ajouter une dépendance Composer ; le champ `qr_code`/`code_barre` stocke simplement le `numero` du dépôt
- [x] Modale reçu après dépôt : numéro, QR code, bouton **Imprimer** (`window.print()`)
- [x] **Catalogue d'articles pressing** (`app/views/pressing/depot.php` → `PD_ARTICLES_CATALOGUE`) : sélection guidée par `<select>`/`<optgroup>` groupée en 6 catégories (Vêtements, Chaussures/accessoires, Linge de maison, Bébé, Professionnel, Spécial) — géré côté front, pas de nouvelle table
- [x] **Extension des services** (`migrations/add_pressing_services_catalogue.sql`) : ENUM `pressing_articles.service` étendu à 11 valeurs (lavage, repassage, lavage_repassage, nettoyage_sec, detachage, desinfection, blanchiment, anti_odeur, express, pliage, emballage_cintre) ; `PressingArticle::getValidServices()` et libellés FR dans `depot.php`/`rapports.php` mis à jour en conséquence
- [ ] **Migration SQL à exécuter** : `migrations/add_pressing_services_catalogue.sql` (ALTER TABLE additif, n'affecte aucune autre table)
- [x] **Double affichage devise (Fc / $)** : totaux (sous-total, total, total par article, reçu) convertis et affichés en dollars via un taux configurable `taux_usd` stocké dans la table `settings` existante (réutilise `GET/POST /api/settings`, aucune nouvelle route). Taux modifiable en ligne par `admin`/`super_admin` uniquement (icône ✎), lecture seule pour `vendeur`. Conversion faite **côté client en JS** (`PD_TAUX_USD`), pas d'appel à une API externe (pas de CORS/coût)
- [ ] **Test manuel à faire** : créer un dépôt complet (client existant + nouveau client), choisir des articles dans le catalogue + un service étendu (ex: Lavage + Repassage), vérifier le calcul auto des totaux en Fc et $, modifier le taux (si admin), imprimer le reçu

### Étape 7 — Pressing : Suivi & Retrait ✅

- [x] `PressingDepotController::updateStatus()` (recu → en_lavage → en_sechage → en_repassage → pret ; `livre` bloqué tant que non payé)
- [x] Route page `/pressing/suivi` + vue `app/views/pressing/suivi.php` : barre de progression à étapes (points reliés) par dépôt, sélecteur de statut, badge payé/non payé
- [x] `PressingDepotController::search()` (par numéro/QR) + `pay()` + `withdraw()` (bloque le retrait tant que non payé)
- [x] Route page `/pressing/retrait` + vue `app/views/pressing/retrait.php` : recherche par numéro (scan QR ou saisie manuelle), affichage dépôt + articles, encaissement si non payé, sinon bouton "Valider le retrait" → statut `livre`
- [ ] **Test manuel à faire** : cycle complet dépôt → changer les statuts via `/pressing/suivi` → rechercher sur `/pressing/retrait` → encaisser → valider retrait (statut `livre`)

### Étape 8 — Pressing : Historique & Rapports ✅

- [x] Route page `/pressing/historique` + vue `app/views/pressing/historique.php` avec filtres (statut, date début/fin)
- [x] Controller `app/controllers/PressingReportController.php` + vue `app/views/pressing/rapports.php` (nb dépôts, revenus, services les plus utilisés, clients fidèles, revenus jour/mois)
- [ ] **Test manuel à faire** : filtrer l'historique par statut/date, vérifier que la vente pressing (retrait payé) apparaît dans `/historique` et `/analytics` existants sans régression

### Étape 9 — Finalisation & non-régression ✅ (vérifications statiques)

- [x] `php -l` sur les 27 fichiers créés/modifiés : aucune erreur de syntaxe
- [x] `git diff --stat` sur `header.php`, `PageController.php`, `routes/web.php`, `routes/api.php` : **271 insertions, 0 suppression** — confirmé additif uniquement, `caisse.php`/`produits.php`/`historique.php`/`analytics.php` non touchés
- [x] Isolation multi-boutique : chaque controller Restaurant/Pressing utilise `$this->getShopId()` / `$this->isSuperAdmin()` (même pattern que `ClientController`/`SaleController`) et vérifie `shop_id` sur `findById()`
- [x] Requêtes préparées : toutes les requêtes passent par `App\Core\Database` (PDO préparé), aucune concaténation SQL de valeurs utilisateur
- [ ] (Optionnel, non fait) §8bis-bis : colonne `utilisateurs.poste` — à faire seulement si l'isolation stricte serveur/cuisinier/agent pressing est demandée plus tard
- [ ] **Test fonctionnel manuel final à faire** (voir checklist ci-dessous) avant mise en production

---

## 9. Checklist de test fonctionnel (à exécuter maintenant)

### Restaurant
- [ ] `/restaurant/tables` : CRUD table + changement d'état
- [ ] `/restaurant/menu` : créer catégorie + plat (avec image, `temps_preparation`), toggle disponibilité
- [ ] `/restaurant/commandes` : sélectionner table → ajouter plats → +/- quantité → remise → envoyer en cuisine
- [ ] `/restaurant/cuisine` : cliquer Commencer → observer la barre de progression → passage auto en Prêt → Marquer servi
- [ ] Paiement commande → facture générée, table repasse `nettoyage`, vente visible dans `/historique` et `/analytics`
- [ ] `/restaurant/rapports` : KPIs et tableaux se chargent

### Pressing
- [ ] `/pressing/depot` : créer/rechercher client, ajouter articles, enregistrer, QR code affiché, impression
- [ ] `/pressing/suivi` : faire avancer les statuts d'un dépôt
- [ ] `/pressing/retrait` : rechercher par numéro, encaisser si non payé, valider le retrait (statut `livre`)
- [ ] `/pressing/historique` : filtrer par statut/date
- [ ] `/pressing/rapports` : KPIs et tableaux se chargent
- [ ] Vente pressing visible dans `/historique` et `/analytics`

### Non-régression POS existant
- [ ] `/caisse`, `/produits`, `/historique`, `/analytics`, `/utilisateurs` fonctionnent normalement
- [ ] Sidebar : les liens Restaurant/Pressing n'apparaissent que si le type de service de la boutique correspond (ou toujours pour `super_admin`)
