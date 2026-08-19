# Plan d'évolution — POS System Multi-Boutique

Ce document est la version "README / checklist" du plan `PLAN_EVOLUTION_MULTI_BOUTIQUE.md`.

> Objectif : transformer le POS mono-boutique en système multi-boutique avec `super_admin`, archivage, sécurité et toutes les corrections identifiées.

Cocher les cases au fur et à mesure de l'avancement.

---

## Phase 1 — Préparation

- [x] Sauvegarder la BDD actuelle (dump complet)
- [ ] Tester la restauration du dump sur une BDD de test
- [x] Lire et valider ce plan d'évolution

---

## Phase 2 — Migration SQL

- [x] Créer le fichier `migrations/multi_shop_evolution.sql`
- [x] Créer la table `shops`
- [x] Créer la table `audit_logs`
- [x] Créer la table `login_attempts`
- [x] Créer la table `otp_codes`
- [x] Créer la table `password_resets`
- [x] Créer la table `notifications`
- [x] Modifier `utilisateurs` : ajout ENUM `super_admin` + colonnes `shop_id`, `email`, `telephone`, `two_factor_enabled`
- [x] Modifier `categories` : ajout colonne `shop_id`
- [x] Modifier `produits` : ajout colonne `shop_id`
- [x] Modifier `ventes` : ajout colonne `shop_id`
- [x] Modifier `clients` : ajout colonne `shop_id`
- [x] Modifier `settings` : ajout colonne `shop_id` + nouvelle contrainte UNIQUE
- [x] Créer la table `ventes_archive`
- [x] Créer la table `details_vente_archive`
- [x] Insérer une boutique par défaut et rattacher les données existantes
- [x] Promouvoir un utilisateur existant en `super_admin`
- [x] Exécuter la migration sur la BDD de test
- [ ] Vérifier l'intégrité des données après migration

---

## Phase 3 — Nouveaux modèles

- [x] Créer `app/models/Shop.php` (CRUD boutiques)
- [x] Créer `app/models/AuditLog.php` (insertion + lecture logs)
- [x] Créer `app/models/OtpCode.php` (génération, vérification, envoi OTP)
- [x] Créer `app/models/PasswordReset.php` (token, vérification, reset)
- [x] Créer `app/models/Notification.php` (CRUD, marquage lu, comptage non-lus)
- [x] Créer `app/controllers/NotificationController.php` (list, read, count)
- [x] Créer `archive_data.php` (script d'archivage mensuel) → `scripts/archive_data.php`

---

## Phase 4 — Modifier les modèles existants

- [x] `Product.php` : ajouter paramètre `$shopId` dans `getAll()`, `create()`, `update()`
- [x] `Category.php` : ajouter paramètre `$shopId` dans `all()`, `add()`
- [x] `Sale.php` : ajouter paramètre `$shopId` dans `getAllSales()`, `create()` + méthode `searchArchive()`
- [x] `Client.php` : ajouter paramètre `$shopId` dans `getAll()`, `create()`
- [x] `Settings.php` : `get($key, $shopId = null)` — priorité boutique > global
- [x] `User.php` : ajouter paramètre `$shopId` dans `all()`, `create()`
- [ ] Tester chaque modèle individuellement

---

## Phase 5 — Modifier les contrôleurs (RBAC + sécurité)

- [x] `Controller.php` : ajouter helpers `getShopId()`, `isSuperAdmin()`, `isAdmin()`, `logAudit()`
- [x] `AuthController.php` : stocker `shop_id` en session, rate limiting, audit login/logout
- [x] `AuthController.php` : ajouter méthode `verifyOtp()` (vérification code OTP après login)
- [x] `AuthController.php` : ajouter méthode `forgotPassword()` (demande de récupération)
- [x] `AuthController.php` : ajouter méthode `verifyResetCode()` (vérification code de reset)
- [x] `AuthController.php` : ajouter méthode `resetPassword()` (changement effectif du mot de passe)
- [x] `AuthController.php` : ajouter méthode `resendOtp()` (renvoi du code OTP)
- [x] `ProductController.php` : RBAC 3 niveaux + `shop_id` dans toutes les opérations
- [x] `CategoryController.php` : sécuriser `delete()` + `shop_id`
- [x] `SaleController.php` : `shop_id` + restauration stock dans `delete()`
- [x] `ClientController.php` : `shop_id`
- [x] `UserController.php` : sécuriser `all()` et `delete()` + RBAC super_admin/admin
- [x] `SettingsController.php` : paramètres par boutique
- [x] `PageController.php` : filtrer données par `shop_id` + dashboard multi-boutique
- [x] `InvoiceController.php` : infos magasin par boutique
- [x] `BillPaymentController.php` : `shop_id`
- [x] `TaxController.php` : vérifier auth (déjà OK, pas de `shop_id`)
- [x] Créer `ShopController.php` : CRUD boutiques (super_admin only)
- [x] `Controller.php` : ajouter helper `notify($userId, $shopId, $type, $title, $message)`
- [x] `SaleController.php` : déclencher notification `stock_low` après vente si stock < minimum
- [x] `SaleController.php` : déclencher notification `suspicious_action` à suppression de vente
- [x] `ProductController.php` : déclencher notification `suspicious_action` à modification de prix
- [x] `AuthController.php` : déclencher notification `suspicious_action` après 5 tentatives échouées
- [x] Ajouter route `/api/user/change-password`
- [x] Ajouter expiration de session (timeout 8h)
- [x] Configurer PHPMailer pour l'envoi d'emails OTP
- [x] Configurer l'envoi SMS via API OSAT-Energie pour OTP

---

## Phase 6 — Modifier les routes

- [x] `routes/api.php` : ajouter routes boutiques `/api/shops`, `/api/shops/[i:id]`
- [x] `routes/api.php` : sécuriser `/api/users` (exiger auth) → `requireAuth()` dans `UserController::all()`
- [x] `routes/api.php` : sécuriser `/api/delete/user` (exiger admin+) → `requireAdmin()` dans `UserController::delete()`
- [x] `routes/api.php` : sécuriser `/api/delete/category` (exiger admin+) → `requireAdmin()` dans `CategoryController::delete()`
- [x] `routes/api.php` : sécuriser `/api/vente/[id]/details` (exiger auth) → via `SaleController::details()`
- [x] `routes/api.php` : ajouter route `/api/user/change-password`
- [x] `routes/api.php` : ajouter route `POST /api/auth/verify-otp`
- [x] `routes/api.php` : ajouter route `POST /api/auth/resend-otp`
- [x] `routes/api.php` : ajouter route `POST /api/auth/forgot-password`
- [x] `routes/api.php` : ajouter route `POST /api/auth/verify-reset`
- [x] `routes/api.php` : ajouter route `POST /api/auth/reset-password`
- [x] `routes/web.php` : ajouter route `GET /forgot-password`
- [x] `routes/web.php` : ajouter route `GET /reset-password`
- [x] `routes/web.php` : ajouter route `GET /verify-otp`
- [x] `routes/api.php` : ajouter route `GET /api/notifications` (liste paginée)
- [x] `routes/api.php` : ajouter route `GET /api/notifications/unread-count` (nombre non-lus)
- [x] `routes/api.php` : ajouter route `POST /api/notifications/[i:id]/read` (marquer comme lu)
- [x] `routes/api.php` : ajouter route `POST /api/notifications/read-all` (tout marquer lu)
- [x] `routes/api.php` : ajouter route `/api/cloture` (rapport de clôture)
- [x] `routes/api.php` : ajouter route `/api/export/ventes` (export CSV)
- [x] `routes/web.php` : ajouter route `/shops` (page boutiques)
- [x] `routes/web.php` : ajouter route catch-all pour page 404 → `Router::respondNotFound()`

---

## Phase 7 — Modifier les vues et le frontend

- [x] `header.php` : menu "Boutiques" pour super_admin + sélecteur de boutique
- [x] `dashboard.php` : stats multi-boutique pour super_admin
- [x] `historique.php` : onglet "Archives" pour ventes > 3 mois
- [x] `login.php` : message de rate limiting + lien "Mot de passe oublié ?"
- [x] `shops.php` : nouvelle page de gestion des boutiques
- [x] `otp-verify.php` : page de saisie du code OTP après login
- [x] `forgot-password.php` : page de saisie email ou téléphone
- [x] `reset-password.php` : page de saisie du nouveau mot de passe
- [x] Ajouter page/modale "Changer mon mot de passe"
- [x] Ajouter page/modale "Rapport de clôture"
- [x] Ajouter bouton "Exporter CSV" dans historique et analytics
- [x] Ajouter page 404 (`404.php`)
- [x] `produits.php` : afficher le nom de la boutique si super_admin
- [x] `utilisateurs.php` : afficher le nom de la boutique si super_admin
- [x] `analytics.php` : sélecteur de boutique pour super_admin
- [x] `parametres.php` : sélecteur de boutique pour super_admin
- [x] `header.php` : ajouter icône cloche 🔔 avec badge compteur de notifications non-lues
- [x] `header.php` : dropdown des dernières notifications au clic sur la cloche
- [x] JavaScript : polling `/api/notifications/unread-count` toutes les 30 secondes

---

## Phase 8 — Tests et validation

- [x] Tester login/logout avec les 3 rôles (super_admin, admin, vendeur)
- [x] Tester que chaque rôle ne voit que ses données
- [x] Tester la création de boutique par super_admin
- [x] Tester la création d'admin par super_admin
- [x] Tester la création de vendeur par admin
- [x] Tester une vente complète (ajout panier → validation → stock → facture)
- [x] Tester la suppression de vente et vérifier la restauration du stock
- [x] Tester le changement de mot de passe
- [x] Tester le rate limiting (5 tentatives max)
- [x] Tester la 2FA : login → OTP par email → vérification → accès
- [x] Tester la 2FA : login → OTP par SMS → vérification → accès
- [x] Tester l'expiration du code OTP (après 5 min)
- [x] Tester le renvoi du code OTP
- [x] Tester le blocage après 3 mauvais codes OTP
- [x] Tester mot de passe oublié par email
- [x] Tester mot de passe oublié par SMS
- [x] Tester l'expiration du lien de reset (après 15 min)
- [x] Tester la désactivation de la 2FA par super_admin/admin
- [x] Tester notification stock faible après vente qui passe sous le minimum
- [x] Tester notification action suspecte après suppression de vente
- [x] Tester notification action suspecte après modification de prix
- [x] Tester le compteur de notifications non-lues dans le header
- [x] Tester le marquage lu d'une notification
- [x] Tester que les notifications sont filtrées par boutique
- [x] Tester le script d'archivage sur données de test
- [x] Tester l'onglet Archives dans l'historique
- [x] Tester l'export CSV
- [x] Tester le rapport de clôture
- [x] Tester la page 404
- [x] Tester les routes sécurisées (accès refusé si non autorisé)
- [x] Vérifier les audit_logs après chaque action
- [x] Tester sur mobile (responsive)
- [x] Tester avec les 3 boutiques simultanément

---

## Phase 9 — Documentation

- [x] Mettre à jour `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md`
- [x] Mettre à jour `MANUEL_UTILISATION.md`
- [x] Mettre à jour `MANUEL_CONTROLE_SUPERVISION.md`
- [x] Mettre à jour `SPECIFICATIONS_TECHNIQUES_OFFICIELLES.md`
- [x] Mettre à jour `ROUTES.md`
- [x] Mettre à jour `README-BDD.md`
- [x] Créer un nouveau dump SQL de référence

---

## Phase 10 — Déploiement

- [x] Sauvegarder la BDD de production
- [x] Exécuter la migration SQL en production
- [x] Déployer le nouveau code PHP
- [x] Créer les boutiques en production
- [x] Affecter les admins et vendeurs à leurs boutiques
- [x] Réaffecter les produits et catégories aux boutiques
- [x] Vérifier le bon fonctionnement en production pendant 7 jours
- [x] Mettre en place le cron job d'archivage mensuel

---

*Légende :* `[x]` = déjà réalisé dans le plan actuel  ·  `[ ]` = à faire / à vérifier
