# Plan d'Évolution — POS System Multi-Boutique

> **Objectif** : Transformer le POS System mono-boutique en système multi-boutique avec super_admin, archivage des données et correction de tous les manques identifiés.  
> **Date** : Juillet 2026  
> **Stratégie de données** : Archivage 3 mois + Purge audit 6 mois

---

## Table des matières

1. [Ce qui manque au système actuel](#1-ce-qui-manque-au-système-actuel)
2. [Architecture multi-boutique cible](#2-architecture-multi-boutique-cible)
3. [Stratégie de scalabilité](#3-stratégie-de-scalabilité)
4. [Modélisation BDD — Nouvelles tables](#4-modélisation-bdd--nouvelles-tables)
5. [Modélisation BDD — Modifications des tables existantes](#5-modélisation-bdd--modifications-des-tables-existantes)
6. [Diagramme relationnel cible](#6-diagramme-relationnel-cible)
7. [Matrice des permissions RBAC](#7-matrice-des-permissions-rbac)
8. [Fichiers impactés](#8-fichiers-impactés)
9. [Précautions et risques](#9-précautions-et-risques)
10. [Checklist d'implémentation](#10-checklist-dimplémentation)

---

## 1. Ce qui manque au système actuel

### 🔴 Manques critiques (sécurité & fiabilité)

| # | Manque | Impact |
|---|--------|--------|
| 1 | `/api/users` est publique — n'importe qui peut voir tous les utilisateurs | Fuite de données |
| 2 | `/api/delete/user` et `/api/delete/category` ne vérifient pas le rôle admin | Suppression non autorisée |
| 3 | `/api/vente/[id]/details` et `/api/vente/next-invoice` accessibles sans auth | Fuite de données |
| 4 | Pas de changement de mot de passe par l'utilisateur | UX et sécurité |
| 5 | Pas de journal d'audit (logs d'activité) | Impossible de tracer les actions |
| 6 | Pas de restauration de stock à la suppression d'une vente | Incohérence de stock |
| 7 | Pas de gestion d'expiration de session | Risque d'accès non autorisé |
| 8 | Pas de double authentification (2FA/OTP) | Comptes vulnérables au vol de mot de passe |
| 9 | Pas de récupération de mot de passe oublié | Dépendance totale à l'admin |

### 🟠 Manques importants (fonctionnels)

| # | Manque | Impact |
|---|--------|--------|
| 10 | Pas de rapport de clôture de caisse (Z de caisse) | Supervision impossible |
| 11 | Pas d'export des données (CSV/Excel) | Pas de reporting externe |
| 12 | Pas de gestion du prix d'achat / marge | Pas de calcul de rentabilité |
| 13 | Pas de gestion des fournisseurs / achats | Pas de suivi d'approvisionnement |
| 14 | Pas de gestion des retours formalisée | Pas de page dédiée retours |
| 15 | Pas de recherche/filtre avancé dans l'historique | Performance dégradée |
| 16 | Pas de pagination côté serveur | Crash avec gros volumes |
| 17 | Pas de gestion multi-devises (USD/CDF) | Limité au CDF |
| 18 | Pas de fond de caisse | Pas de contrôle d'écart |

### 🟡 Manques souhaitables (UX & production)

| # | Manque | Impact |
|---|--------|--------|
| 19 | Pas de système de notifications (stock faible, objectifs, actions suspectes) | Aucune alerte proactive |
| 20 | Pas de mode hors-ligne | Inutilisable sans internet |
| 21 | Pas de sauvegarde automatique de la BDD | Risque de perte |
| 22 | Pas de gestion des promotions / campagnes | Marketing limité |
| 23 | Pas de programme de fidélité client | Pas de rétention |
| 24 | Pas de gestion des permissions granulaire (2 rôles seulement) | RBAC limité |
| 25 | Pas de tests automatisés | Risque de régression |
| 26 | Pas de page 404 | UX dégradée |
| 27 | Pas de rate limiting sur le login | Vulnérable au brute force |
| 28 | Pas de double validation sur les actions critiques | Risque d'erreur |

---

## 2. Architecture multi-boutique cible

### Concept

Un **super_admin** possède plusieurs boutiques. Chaque boutique a son propre admin et ses vendeurs. Tous partagent la même base de données, mais chaque utilisateur ne voit que les données de sa boutique.

```
Super Admin → voit tout, gère les boutiques et les admins
    │
    ├── Boutique "Vêtements" (admin: Jean)
    │       ├── Catégories : T-Shirts, Pantalons, Chaussures
    │       ├── Produits : liés à cette boutique uniquement
    │       ├── Vendeurs : Marie, Paul
    │       └── Ventes : uniquement celles de cette boutique
    │
    ├── Boutique "Quincaillerie" (admin: Pierre)
    │       ├── Catégories : Ciment, Peinture, Outils
    │       ├── Produits, Vendeurs, Ventes...
    │
    └── Boutique "Restaurant" (admin: Sarah)
            ├── Catégories : Plats, Boissons, Desserts
            ├── Produits, Vendeurs, Ventes...
```

### Rôles

| Rôle | Portée | Qui le crée |
|------|--------|-------------|
| `super_admin` | Toutes les boutiques | Système (1 seul) |
| `admin` | Sa boutique uniquement | super_admin |
| `vendeur` | Sa boutique uniquement | admin ou super_admin |

### Règle clé

- `shop_id = NULL` dans `utilisateurs` → super_admin (voit tout)
- `shop_id = NULL` dans `settings` → paramètre global
- `shop_id = X` → données filtrées pour la boutique X

---

## 3. Stratégie de scalabilité

### Problème de volume

| Table | Aujourd'hui | Dans 1 an (3 boutiques) | Dans 3 ans |
|-------|------------|------------------------|------------|
| `ventes` | ~488 | ~30 000 | ~100 000+ |
| `details_vente` | ~610 | ~150 000 | ~500 000+ |
| `audit_logs` | 0 | ~200 000 | ~1 000 000+ |

### Solution retenue : Archivage 3 mois + Purge audit 6 mois

| Donnée | Stratégie | Rétention active | Archive |
|--------|-----------|-------------------|---------|
| `ventes` + `details_vente` | Archivage | 3 mois | Illimité (obligation DGI) |
| `audit_logs` | Purge | 6 mois | Pas d'archive |
| `produits`, `categories`, `utilisateurs` | Rien | Illimité | Volume faible |
| `clients` | Rien | Illimité | Volume faible |
| `settings` | Rien | Illimité | Quelques lignes |

### Tables d'archive

- `ventes_archive` — même structure que `ventes` + `shop_id`
- `details_vente_archive` — même structure que `details_vente`

### Script d'archivage mensuel

Un script PHP `archive_data.php` exécuté manuellement ou via cron :
1. Copie les ventes > 3 mois dans `ventes_archive`
2. Copie les details_vente liés dans `details_vente_archive`
3. Supprime les originaux
4. Purge les `audit_logs` > 6 mois

### Consultation des archives

La page **Historique** aura un onglet "Archives" qui interroge `ventes_archive` au lieu de `ventes`.

---

## 4. Modélisation BDD — Nouvelles tables

### Table `shops`

```sql
CREATE TABLE shops (
  id          INT NOT NULL AUTO_INCREMENT,
  nom         VARCHAR(100) NOT NULL,
  code        VARCHAR(20) NOT NULL UNIQUE,
  adresse     VARCHAR(255) DEFAULT NULL,
  telephone   VARCHAR(30) DEFAULT NULL,
  email       VARCHAR(100) DEFAULT NULL,
  ice         VARCHAR(50) DEFAULT NULL,
  rccm        VARCHAR(50) DEFAULT NULL,
  isf         VARCHAR(50) DEFAULT NULL,
  actif       TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Table `audit_logs`

```sql
CREATE TABLE audit_logs (
  id          INT NOT NULL AUTO_INCREMENT,
  user_id     INT DEFAULT NULL,
  shop_id     INT DEFAULT NULL,
  action      VARCHAR(50) NOT NULL,
  entity      VARCHAR(50) NOT NULL,
  entity_id   INT DEFAULT NULL,
  details     JSON DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_shop (shop_id),
  KEY idx_audit_action (action),
  KEY idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Table `ventes_archive`

```sql
CREATE TABLE ventes_archive LIKE ventes;
-- + ajout de shop_id si pas encore présent
```

### Table `details_vente_archive`

```sql
CREATE TABLE details_vente_archive LIKE details_vente;
```

### Table `login_attempts` (rate limiting)

```sql
CREATE TABLE login_attempts (
  id          INT NOT NULL AUTO_INCREMENT,
  username    VARCHAR(50) NOT NULL,
  ip_address  VARCHAR(45) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_user (username),
  KEY idx_login_ip (ip_address),
  KEY idx_login_date (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Table `otp_codes` (double authentification OTP)

```sql
CREATE TABLE otp_codes (
  id          INT NOT NULL AUTO_INCREMENT,
  user_id     INT NOT NULL,
  code        VARCHAR(6) NOT NULL,
  type        ENUM('login','password_reset') NOT NULL DEFAULT 'login',
  channel     ENUM('email','sms') NOT NULL DEFAULT 'email',
  expires_at  DATETIME NOT NULL,
  used        TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_otp_user (user_id),
  KEY idx_otp_code (code),
  KEY idx_otp_expires (expires_at),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

> **Fonctionnement** : À chaque connexion réussie (mot de passe correct), un code OTP à 6 chiffres est généré, enregistré dans cette table et envoyé par email ou SMS. Le code expire après 5 minutes. L'utilisateur doit le saisir pour compléter la connexion.

### Table `password_resets` (récupération mot de passe oublié)

```sql
CREATE TABLE password_resets (
  id          INT NOT NULL AUTO_INCREMENT,
  user_id     INT NOT NULL,
  token       VARCHAR(64) NOT NULL,
  channel     ENUM('email','sms') NOT NULL,
  expires_at  DATETIME NOT NULL,
  used        TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token (token),
  KEY idx_reset_user (user_id),
  KEY idx_reset_expires (expires_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

> **Fonctionnement** : L'utilisateur saisit son email ou numéro de téléphone. Le système génère un token unique + code OTP, l'envoie par email/SMS. L'utilisateur clique sur le lien ou saisit le code, puis définit un nouveau mot de passe. Le token expire après 15 minutes.

### Table `notifications` (alertes internes)

```sql
CREATE TABLE notifications (
  id          INT NOT NULL AUTO_INCREMENT,
  user_id     INT DEFAULT NULL,
  shop_id     INT DEFAULT NULL,
  type        ENUM('stock_low','sale_target','suspicious_action','system') NOT NULL,
  title       VARCHAR(150) NOT NULL,
  message     TEXT NOT NULL,
  link        VARCHAR(255) DEFAULT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  sent_email  TINYINT(1) NOT NULL DEFAULT 0,
  sent_sms    TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id),
  KEY idx_notif_shop (shop_id),
  KEY idx_notif_type (type),
  KEY idx_notif_read (is_read),
  KEY idx_notif_date (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

> **Types de notifications** :
> - `stock_low` : un produit passe sous son `stock_minimum` → envoyé à l'admin de la boutique
> - `sale_target` : objectif de vente atteint (journalier/mensuel) → envoyé à l'admin
> - `suspicious_action` : suppression de vente, modification de prix, tentative de login échouée → envoyé au super_admin
> - `system` : maintenance, archivage terminé, erreur critique → envoyé au super_admin

### Déclencheurs de notifications

```
Événement                          → Type               → Destinataire
─────────────────────────────────────────────────────────────────────────
Stock < stock_minimum après vente  → stock_low           → admin boutique
CA journalier dépasse objectif     → sale_target         → admin boutique
Suppression d'une vente            → suspicious_action   → super_admin
Modification prix produit          → suspicious_action   → super_admin
5 tentatives login échouées        → suspicious_action   → super_admin
Archivage mensuel terminé          → system              → super_admin
Erreur API DGI                     → system              → admin boutique
```

### Canaux de livraison

| Canal | Quand | Librairie |
|-------|-------|-----------|
| **In-app** (icône cloche 🔔) | Toujours | JavaScript polling `/api/notifications/unread` |
| **Email** | Si configuré par l'utilisateur | PHPMailer (déjà configuré pour 2FA) |
| **SMS** | Si critique (suspicious_action) | API OSAT-Energie (déjà configuré pour 2FA) |

---

### Flux d'authentification avec 2FA

```
Étape 1 — Login classique
    Utilisateur saisit : nom_utilisateur + mot_de_passe
    ↓
    Vérification credentials (password_verify)
    ↓ OK
    Génération OTP (6 chiffres, expire 5 min)
    ↓
    Envoi par EMAIL (PHPMailer) + SMS (API OSAT-Energie)
    ↓
    Affichage page de saisie OTP

Étape 2 — Vérification OTP
    Utilisateur saisit le code reçu
    ↓
    Vérification : code correct + non expiré + non utilisé
    ↓ OK
    Création de session complète (→ dashboard)
    ↓ ERREUR
    3 tentatives max, puis blocage 15 min
```

### Flux de récupération mot de passe oublié

```
Étape 1 — Demande
    Page login → lien "Mot de passe oublié ?"
    ↓
    Saisir email OU numéro de téléphone
    ↓
    Le système cherche l'utilisateur correspondant
    ↓ TROUVÉ
    Génération token + code OTP (expire 15 min)
    ↓
    Envoi par le canal choisi (email ou SMS)

Étape 2 — Vérification
    L'utilisateur saisit le code reçu
    ↓ OK
    Affichage formulaire "Nouveau mot de passe"

Étape 3 — Changement
    Saisir nouveau mot de passe + confirmation
    ↓
    Mise à jour en BDD (password_hash bcrypt)
    ↓
    Redirection vers login avec message de succès
```

---

## 5. Modélisation BDD — Modifications des tables existantes

### `utilisateurs`

```sql
ALTER TABLE utilisateurs 
  MODIFY COLUMN role ENUM('super_admin','admin','vendeur') NOT NULL DEFAULT 'vendeur';

ALTER TABLE utilisateurs 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER role,
  ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER agent_code,
  ADD COLUMN telephone VARCHAR(30) DEFAULT NULL AFTER email,
  ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER telephone,
  ADD KEY idx_user_shop (shop_id),
  ADD CONSTRAINT fk_user_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL;
```

> **Nouvelles colonnes** :
> - `email` : adresse email pour recevoir les OTP et les liens de récupération
> - `telephone` : numéro de téléphone pour recevoir les OTP par SMS
> - `two_factor_enabled` : activer/désactiver la 2FA (activé par défaut, désactivable par super_admin)

### `categories`

```sql
ALTER TABLE categories 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER category,
  ADD KEY idx_cat_shop (shop_id),
  ADD CONSTRAINT fk_cat_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE;
```

### `produits`

```sql
ALTER TABLE produits 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER category_id,
  ADD KEY idx_prod_shop (shop_id),
  ADD CONSTRAINT fk_prod_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE;
```

### `ventes`

```sql
ALTER TABLE ventes 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER vendeur_id,
  ADD KEY idx_vente_shop (shop_id),
  ADD CONSTRAINT fk_vente_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL;
```

### `clients`

```sql
ALTER TABLE clients 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER adresse,
  ADD KEY idx_client_shop (shop_id),
  ADD CONSTRAINT fk_client_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL;
```

### `settings`

```sql
ALTER TABLE settings 
  ADD COLUMN shop_id INT DEFAULT NULL AFTER setting_key,
  ADD KEY idx_setting_shop (shop_id),
  ADD CONSTRAINT fk_setting_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE;

ALTER TABLE settings DROP INDEX setting_key;
ALTER TABLE settings ADD UNIQUE KEY uq_setting_shop (setting_key, shop_id);
```

### Tables NON modifiées (partagées)

- `taxes` — groupes DGI communs à toutes les boutiques
- `type_client` — types de clients communs
- `service_providers` — fournisseurs SNEL/REGIDESO communs

---

## 6. Diagramme relationnel cible

```
shops ──────────────────────────────────────────────────┐
  │                                                     │
  ├──< utilisateurs (shop_id)    [super_admin: NULL]    │
  │       │                                             │
  │       └──< ventes (vendeur_id, shop_id)             │
  │               │                                     │
  │               └──< details_vente (vente_id)         │
  │                                                     │
  ├──< categories (shop_id)                             │
  │       │                                             │
  │       └──< produits (category_id, shop_id)          │
  │                                                     │
  ├──< clients (shop_id)                                │
  ├──< settings (shop_id)    [global: NULL]             │
  └──< audit_logs (shop_id, user_id)                    │
                                                        │
Tables partagées (sans shop_id) :                       │
  - taxes                                               │
  - type_client                                         │
  - service_providers                                   │
  - login_attempts                                      │
                                                        │
Tables d'authentification :                             │
  - otp_codes (lié à utilisateurs)                     │
  - password_resets (lié à utilisateurs)                │
                                                        │
Tables de notifications :                               │
  - notifications (user_id, shop_id)                    │
                                                        │
Tables d'archive :                                      │
  - ventes_archive (même structure + shop_id)           │
  - details_vente_archive (même structure)              │
```

---

## 7. Matrice des permissions RBAC

| Action | super_admin | admin | vendeur |
|--------|:-----------:|:-----:|:-------:|
| Gérer les boutiques (CRUD) | ✅ | ❌ | ❌ |
| Créer/modifier/supprimer un admin | ✅ | ❌ | ❌ |
| Voir toutes les boutiques et leurs données | ✅ | ❌ | ❌ |
| Voir analytics globaux (toutes boutiques) | ✅ | ❌ | ❌ |
| Gérer produits de sa boutique | ✅ | ✅ | ❌ |
| Gérer catégories de sa boutique | ✅ | ✅ | ❌ |
| Gérer vendeurs de sa boutique | ✅ | ✅ | ❌ |
| Gérer paramètres de sa boutique | ✅ | ✅ | ❌ |
| Voir analytics de sa boutique | ✅ | ✅ | ❌ |
| Supprimer une vente | ✅ | ✅ | ❌ |
| Gérer les taxes (partagées) | ✅ | ✅ | ❌ |
| Vendre (produits de sa boutique) | ✅ | ✅ | ✅ |
| Voir historique de sa boutique | ✅ | ✅ | ✅ |
| Changer son mot de passe | ✅ | ✅ | ✅ |
| Se connecter avec 2FA/OTP | ✅ | ✅ | ✅ |
| Récupérer mot de passe oublié | ✅ | ✅ | ✅ |
| Désactiver la 2FA pour un utilisateur | ✅ | ✅ (ses vendeurs) | ❌ |
| Scanner un code-barres | ✅ | ✅ | ✅ |
| Payer facture SNEL/REGIDESO | ✅ | ✅ | ✅ |
| Voir ses notifications | ✅ | ✅ | ✅ |
| Recevoir alertes stock faible | ✅ | ✅ (sa boutique) | ❌ |
| Recevoir alertes actions suspectes | ✅ | ❌ | ❌ |

---

## 8. Fichiers impactés

### Nouveaux fichiers

| Fichier | Type | Description |
|---------|------|-------------|
| `migrations/multi_shop_evolution.sql` | SQL | Migration complète |
| `app/models/Shop.php` | Modèle | CRUD boutiques |
| `app/models/AuditLog.php` | Modèle | Insertion et lecture des logs |
| `app/models/OtpCode.php` | Modèle | Génération, vérification et envoi OTP |
| `app/models/PasswordReset.php` | Modèle | Génération token, vérification, reset |
| `app/models/Notification.php` | Modèle | CRUD notifications, marquage lu, comptage non-lus |
| `app/controllers/ShopController.php` | Contrôleur | Gestion des boutiques |
| `app/controllers/NotificationController.php` | Contrôleur | API notifications (list, read, count) |
| `app/views/shops.php` | Vue | Page de gestion des boutiques |
| `app/views/otp-verify.php` | Vue | Page saisie code OTP après login |
| `app/views/forgot-password.php` | Vue | Page saisie email/téléphone |
| `app/views/reset-password.php` | Vue | Page nouveau mot de passe |
| `archive_data.php` | Script | Archivage mensuel des données |

### Fichiers modifiés

| Fichier | Changement |
|---------|-----------|
| `app/models/Product.php` | Filtrer par `shop_id` dans toutes les requêtes |
| `app/models/Category.php` | Filtrer par `shop_id` |
| `app/models/Sale.php` | Filtrer par `shop_id` + méthode `searchArchive()` |
| `app/models/SaleDetail.php` | Pas de changement direct (filtré via vente) |
| `app/models/Client.php` | Filtrer par `shop_id` |
| `app/models/Settings.php` | `get($key, $shopId)` — paramètres par boutique ou global |
| `app/models/User.php` | Filtrer par `shop_id` + supporter `super_admin` |
| `app/controllers/AuthController.php` | Session `shop_id`, rate limiting, audit log, 2FA OTP, mot de passe oublié |
| `app/controllers/ProductController.php` | RBAC 3 niveaux + `shop_id` |
| `app/controllers/CategoryController.php` | RBAC 3 niveaux + `shop_id` |
| `app/controllers/SaleController.php` | `shop_id` + restauration stock à suppression |
| `app/controllers/ClientController.php` | `shop_id` |
| `app/controllers/UserController.php` | RBAC 3 niveaux + sécurisation routes |
| `app/controllers/SettingsController.php` | Paramètres par boutique |
| `app/controllers/TaxController.php` | Pas de `shop_id` (partagé) |
| `app/controllers/PageController.php` | Filtrer données par `shop_id` + dashboard multi |
| `app/controllers/InvoiceController.php` | `shop_id` pour infos magasin |
| `app/controllers/BillPaymentController.php` | `shop_id` |
| `app/controllers/Controller.php` | Helper `getShopId()` + `logAudit()` |
| `routes/api.php` | Nouvelles routes boutiques + sécurisation |
| `routes/web.php` | Route `/shops` |
| `app/views/layout/header.php` | Menu boutique pour super_admin |
| `app/views/dashboard.php` | Stats multi-boutique pour super_admin |
| `app/views/historique.php` | Onglet Archives |
| `app/views/login.php` | Message rate limiting + lien "Mot de passe oublié ?" |
| `app/controllers/Controller.php` | Helper `notify()` pour créer des notifications |

---

## 9. Précautions et risques

| Risque | Précaution |
|--------|-----------|
| Perte de données pendant la migration | **SAUVEGARDER** `pos_system-2026-07-16_212007-dump.sql` avant toute action |
| Données existantes orphelines | Créer une boutique par défaut et y rattacher toutes les données existantes |
| Incompatibilité des requêtes existantes | Tester chaque modèle après modification |
| FK empêchant certaines opérations | Ordre d'exécution strict dans la migration |
| Taxes partagées entre boutiques | Ne PAS ajouter `shop_id` aux taxes (logique DGI nationale) |
| Settings avec contrainte UNIQUE cassée | Modifier la contrainte pour inclure `shop_id` |
| Volume de travail important | Procéder phase par phase, tester à chaque étape |

---

## 10. Checklist d'implémentation

### Phase 1 — Préparation

- [ ] Sauvegarder la BDD actuelle (dump complet)
- [ ] Tester la restauration du dump sur une BDD de test
- [ ] Lire et valider ce plan d'évolution

### Phase 2 — Migration SQL

- [ ] Créer le fichier `migrations/multi_shop_evolution.sql`
- [ ] Créer la table `shops`
- [ ] Créer la table `audit_logs`
- [ ] Créer la table `login_attempts`
- [ ] Créer la table `otp_codes`
- [ ] Créer la table `password_resets`
- [ ] Créer la table `notifications`
- [ ] Modifier `utilisateurs` : ajout ENUM `super_admin` + colonnes `shop_id`, `email`, `telephone`, `two_factor_enabled`
- [ ] Modifier `categories` : ajout colonne `shop_id`
- [ ] Modifier `produits` : ajout colonne `shop_id`
- [ ] Modifier `ventes` : ajout colonne `shop_id`
- [ ] Modifier `clients` : ajout colonne `shop_id`
- [ ] Modifier `settings` : ajout colonne `shop_id` + nouvelle contrainte UNIQUE
- [ ] Créer la table `ventes_archive`
- [ ] Créer la table `details_vente_archive`
- [ ] Insérer une boutique par défaut et rattacher les données existantes
- [ ] Promouvoir un utilisateur existant en `super_admin`
- [ ] Exécuter la migration sur la BDD de test
- [ ] Vérifier l'intégrité des données après migration

### Phase 3 — Nouveaux modèles

- [ ] Créer `app/models/Shop.php` (CRUD boutiques)
- [ ] Créer `app/models/AuditLog.php` (insertion + lecture logs)
- [ ] Créer `app/models/OtpCode.php` (génération, vérification, envoi OTP)
- [ ] Créer `app/models/PasswordReset.php` (token, vérification, reset)
- [ ] Créer `app/models/Notification.php` (CRUD, marquage lu, comptage non-lus)
- [ ] Créer `app/controllers/NotificationController.php` (list, read, count)
- [ ] Créer `archive_data.php` (script d'archivage mensuel)

### Phase 4 — Modifier les modèles existants

- [ ] `Product.php` : ajouter paramètre `$shopId` dans `getAll()`, `create()`, `update()`
- [ ] `Category.php` : ajouter paramètre `$shopId` dans `all()`, `add()`
- [ ] `Sale.php` : ajouter paramètre `$shopId` dans `getAllSales()`, `create()` + méthode `searchArchive()`
- [ ] `Client.php` : ajouter paramètre `$shopId` dans `getAll()`, `create()`
- [ ] `Settings.php` : `get($key, $shopId = null)` — priorité boutique > global
- [ ] `User.php` : ajouter paramètre `$shopId` dans `all()`, `create()`
- [ ] Tester chaque modèle individuellement

### Phase 5 — Modifier les contrôleurs (RBAC + sécurité)

- [ ] `Controller.php` : ajouter helpers `getShopId()`, `isSuperAdmin()`, `isAdmin()`, `logAudit()`
- [ ] `AuthController.php` : stocker `shop_id` en session, rate limiting, audit login/logout
- [ ] `AuthController.php` : ajouter méthode `verifyOtp()` (vérification code OTP après login)
- [ ] `AuthController.php` : ajouter méthode `forgotPassword()` (demande de récupération)
- [ ] `AuthController.php` : ajouter méthode `verifyResetCode()` (vérification code de reset)
- [ ] `AuthController.php` : ajouter méthode `resetPassword()` (changement effectif du mot de passe)
- [ ] `AuthController.php` : ajouter méthode `resendOtp()` (renvoi du code OTP)
- [ ] `ProductController.php` : RBAC 3 niveaux + `shop_id` dans toutes les opérations
- [ ] `CategoryController.php` : sécuriser `delete()` + `shop_id`
- [ ] `SaleController.php` : `shop_id` + restauration stock dans `delete()`
- [ ] `ClientController.php` : `shop_id`
- [ ] `UserController.php` : sécuriser `all()` et `delete()` + RBAC super_admin/admin
- [ ] `SettingsController.php` : paramètres par boutique
- [ ] `PageController.php` : filtrer données par `shop_id` + dashboard multi-boutique
- [ ] `InvoiceController.php` : infos magasin par boutique
- [ ] `BillPaymentController.php` : `shop_id`
- [ ] `TaxController.php` : vérifier auth (déjà OK, pas de `shop_id`)
- [ ] Créer `ShopController.php` : CRUD boutiques (super_admin only)
- [ ] `Controller.php` : ajouter helper `notify($userId, $shopId, $type, $title, $message)`
- [ ] `SaleController.php` : déclencher notification `stock_low` après vente si stock < minimum
- [ ] `SaleController.php` : déclencher notification `suspicious_action` à suppression de vente
- [ ] `ProductController.php` : déclencher notification `suspicious_action` à modification de prix
- [ ] `AuthController.php` : déclencher notification `suspicious_action` après 5 tentatives échouées
- [ ] Ajouter route `/api/user/change-password`
- [ ] Ajouter expiration de session (timeout 8h)
- [ ] Configurer PHPMailer pour l'envoi d'emails OTP
- [ ] Configurer l'envoi SMS via API OSAT-Energie pour OTP

### Phase 6 — Modifier les routes

- [ ] `routes/api.php` : ajouter routes boutiques `/api/shops`, `/api/shops/[i:id]`
- [ ] `routes/api.php` : sécuriser `/api/users` (exiger auth)
- [ ] `routes/api.php` : sécuriser `/api/delete/user` (exiger admin+)
- [ ] `routes/api.php` : sécuriser `/api/delete/category` (exiger admin+)
- [ ] `routes/api.php` : sécuriser `/api/vente/[id]/details` (exiger auth)
- [ ] `routes/api.php` : ajouter route `/api/user/change-password`
- [ ] `routes/api.php` : ajouter route `POST /api/auth/verify-otp`
- [ ] `routes/api.php` : ajouter route `POST /api/auth/resend-otp`
- [ ] `routes/api.php` : ajouter route `POST /api/auth/forgot-password`
- [ ] `routes/api.php` : ajouter route `POST /api/auth/verify-reset`
- [ ] `routes/api.php` : ajouter route `POST /api/auth/reset-password`
- [ ] `routes/web.php` : ajouter route `GET /forgot-password`
- [ ] `routes/web.php` : ajouter route `GET /reset-password`
- [ ] `routes/web.php` : ajouter route `GET /verify-otp`
- [ ] `routes/api.php` : ajouter route `GET /api/notifications` (liste paginée)
- [ ] `routes/api.php` : ajouter route `GET /api/notifications/unread-count` (nombre non-lus)
- [ ] `routes/api.php` : ajouter route `POST /api/notifications/[i:id]/read` (marquer comme lu)
- [ ] `routes/api.php` : ajouter route `POST /api/notifications/read-all` (tout marquer lu)
- [ ] `routes/api.php` : ajouter route `/api/cloture` (rapport de clôture)
- [ ] `routes/api.php` : ajouter route `/api/export/ventes` (export CSV)
- [ ] `routes/web.php` : ajouter route `/shops` (page boutiques)
- [ ] `routes/web.php` : ajouter route catch-all pour page 404

### Phase 7 — Modifier les vues et le frontend

- [ ] `header.php` : menu "Boutiques" pour super_admin + sélecteur de boutique
- [ ] `dashboard.php` : stats multi-boutique pour super_admin
- [ ] `historique.php` : onglet "Archives" pour ventes > 3 mois
- [ ] `login.php` : message de rate limiting + lien "Mot de passe oublié ?"
- [ ] `shops.php` : nouvelle page de gestion des boutiques
- [ ] `otp-verify.php` : page de saisie du code OTP après login
- [ ] `forgot-password.php` : page de saisie email ou téléphone
- [ ] `reset-password.php` : page de saisie du nouveau mot de passe
- [ ] Ajouter page/modale "Changer mon mot de passe"
- [ ] Ajouter page/modale "Rapport de clôture"
- [ ] Ajouter bouton "Exporter CSV" dans historique et analytics
- [ ] Ajouter page 404 (`404.php`)
- [ ] `produits.php` : afficher le nom de la boutique si super_admin
- [ ] `utilisateurs.php` : afficher le nom de la boutique si super_admin
- [ ] `analytics.php` : sélecteur de boutique pour super_admin
- [ ] `parametres.php` : sélecteur de boutique pour super_admin
- [ ] `header.php` : ajouter icône cloche 🔔 avec badge compteur de notifications non-lues
- [ ] `header.php` : dropdown des dernières notifications au clic sur la cloche
- [ ] JavaScript : polling `/api/notifications/unread-count` toutes les 30 secondes

### Phase 8 — Tests et validation

- [ ] Tester login/logout avec les 3 rôles (super_admin, admin, vendeur)
- [ ] Tester que chaque rôle ne voit que ses données
- [ ] Tester la création de boutique par super_admin
- [ ] Tester la création d'admin par super_admin
- [ ] Tester la création de vendeur par admin
- [ ] Tester une vente complète (ajout panier → validation → stock → facture)
- [ ] Tester la suppression de vente et vérifier la restauration du stock
- [ ] Tester le changement de mot de passe
- [ ] Tester le rate limiting (5 tentatives max)
- [ ] Tester la 2FA : login → OTP par email → vérification → accès
- [ ] Tester la 2FA : login → OTP par SMS → vérification → accès
- [ ] Tester l'expiration du code OTP (après 5 min)
- [ ] Tester le renvoi du code OTP
- [ ] Tester le blocage après 3 mauvais codes OTP
- [ ] Tester mot de passe oublié par email
- [ ] Tester mot de passe oublié par SMS
- [ ] Tester l'expiration du lien de reset (après 15 min)
- [ ] Tester la désactivation de la 2FA par super_admin/admin
- [ ] Tester notification stock faible après vente qui passe sous le minimum
- [ ] Tester notification action suspecte après suppression de vente
- [ ] Tester notification action suspecte après modification de prix
- [ ] Tester le compteur de notifications non-lues dans le header
- [ ] Tester le marquage lu d'une notification
- [ ] Tester que les notifications sont filtrées par boutique
- [ ] Tester le script d'archivage sur données de test
- [ ] Tester l'onglet Archives dans l'historique
- [ ] Tester l'export CSV
- [ ] Tester le rapport de clôture
- [ ] Tester la page 404
- [ ] Tester les routes sécurisées (accès refusé si non autorisé)
- [ ] Vérifier les audit_logs après chaque action
- [ ] Tester sur mobile (responsive)
- [ ] Tester avec les 3 boutiques simultanément

### Phase 9 — Documentation

- [ ] Mettre à jour `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md`
- [ ] Mettre à jour `MANUEL_UTILISATION.md`
- [ ] Mettre à jour `MANUEL_CONTROLE_SUPERVISION.md`
- [ ] Mettre à jour `SPECIFICATIONS_TECHNIQUES_OFFICIELLES.md`
- [ ] Mettre à jour `ROUTES.md`
- [ ] Mettre à jour `README-BDD.md`
- [ ] Créer un nouveau dump SQL de référence

### Phase 10 — Déploiement

- [ ] Sauvegarder la BDD de production
- [ ] Exécuter la migration SQL en production
- [ ] Déployer le nouveau code PHP
- [ ] Créer les boutiques en production
- [ ] Affecter les admins et vendeurs à leurs boutiques
- [ ] Réaffecter les produits et catégories aux boutiques
- [ ] Vérifier le bon fonctionnement en production pendant 7 jours
- [ ] Mettre en place le cron job d'archivage mensuel

---

*Ce document sert de référence pour l'ensemble du chantier. Cochez les cases au fur et à mesure de l'avancement.*
