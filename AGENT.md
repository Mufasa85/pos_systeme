# AGENT.md — Guide pour les agents IA travaillant sur ce projet

> Document à lire en premier par toute IA (Cursor, Claude, Hermes, etc.) avant de
> proposer du code, des migrations, ou des modifications. Il est maintenu par les
> humains ET les IA qui passent sur le projet.

---

## 1. C'est quoi ce projet

Système de **caisse enregistreuse (POS)** multi-boutique en PHP MVC + MySQL, plus
depuis octobre 2026 un **module de gestion hôtelière (PMS)** greffé dessus.

Le but métier : faire tourner un hôtel de 20 chambres (réception, housekeeping,
facturation) en réutilisant la caisse existante pour la vente des extras (bar,
restaurant, room service).

Utilisateur principal : un seul hôtel réel, ~20 chambres, simples et doubles.
Le propriétaire est aussi le développeur — il lit et valide tout le code.

---

## 2. Stack et conventions

| Aspect | Choix | Notes |
|---|---|---|
| Backend | PHP 8.1+, MVC maison | Pas de Laravel. Router = `AltoRouter` (voir `app/core/`) |
| BDD | MySQL 9.x (gérée séparément, pas XAMPP) | Connexion via PDO, config dans `config/config.php` |
| Front | Pages PHP server-side + JS vanilla | Pas de framework JS. CSS dans `app/views/mobile-caisse.css` et `public/assets/css/` |
| Routing web | `routes/web.php` (pages) + `routes/api.php` (API) | `Router::get(...)`, `Router::post(...)` |
| Auth | Session PHP (`$_SESSION['user_id']`, `$_SESSION['role']`) | Vérifiée dans `PageController::render()` |
| Tests | PHPUnit + Playwright | `composer test`, `npm run test:e2e` |
| Lint PHP | PHP-CS-Fixer + PHPStan | `composer cs:check`, `composer static` |
| Lint JS | ESLint + Prettier | `npm run lint`, `npm run format` |

**Conventions SQL** :
- Migrations dans `migrations/`, nommées `AAAA_MM_JD_description.sql`
- Préfixe par module : `payroll_*` (paie), `hotel_*` (PMS), `ventes` (POS)
- Toutes les tables ont `created_at` et `updated_at` quand c'est pertinent
- Foreign keys explicites + index sur les colonnes de jointure

**Conventions PHP** :
- PSR-4 via Composer, namespace `App\` pour `app/`
- Modèles dans `app/models/`, contrôleurs dans `app/controllers/`
- Vues dans `app/views/`, layout commun dans `app/views/layout/`
- Pas de helper global magique : injection par constructeur ou `use`
- `var_dump` interdit en prod, `error_log` OK

**Conventions UI** :
- Sidebar conditionnelle par rôle dans `app/views/layout/header.php`
- SVG inline pour les icônes (pas de bibliothèque d'icônes)
- Mobile-first pour les pages gouvernante (`/hotel/etages`)

---

## 3. Rôles utilisateurs (table `utilisateurs`)

Valeurs possibles de la colonne `role` :
- `super_admin` — accès total, voit toutes les boutiques
- `admin` — manager d'une boutique (= manager d'hôtel)
- `vendeur` — caissier (POS uniquement, pas le PMS)
- `receptionniste` — accueil de l'hôtel (POS + PMS) — **ajouté en octobre 2026**
- `gouvernante` — personnel d'étage (PMS mobile uniquement) — **ajouté en octobre 2026**

Pour ajouter un nouveau rôle : modifier la colonne `role` (c'est un `VARCHAR` ou
`ENUM`, vérifier le type), ajouter une condition dans la sidebar
(`header.php`), et ajouter les vérifications de session dans les contrôleurs.

---

## 4. État du module Hôtel (PMS)

Plan de référence : `docs/PLAN_HOTEL_V1.md` (à lire avant toute modif).

### Avancement par sprint

- [x] **Sprint 1 — Fondations** (en cours) : tables, seed 20 chambres, bouton sidebar, route `/hotel`
- [ ] Sprint 2 — Le réceptionniste (grille chambres, check-in/out, extras)
- [ ] Sprint 3 — La gouvernante (vue mobile)
- [ ] Sprint 4 — Le manager (tableau de bord, CRUD chambres, saisons)
- [ ] Sprint 5 — Liaison caisse POS + polish

### Tables du module

Toutes préfixées `hotel_` :
- `hotel_chambres` — les chambres physiques
- `hotel_guests` — clients de passage
- `hotel_sejours` — séjour = guest + chambre + période
- `hotel_extras` — extras consommés (bar, etc.) liés à un séjour ET à une `vente` POS
- `hotel_paiements` — paiements partiels sur un séjour
- `hotel_housekeeping` — tâches de ménage
- `hotel_tarifs_saison` — multiplicateurs saisonniers

### Lien avec le POS existant

Le seul pont entre POS et PMS = la table `ventes`. Quand un client de chambre
consomme au bar, la vente est faite normalement dans la caisse, mais elle est
**rattachée** à un séjour via `hotel_extras.vente_id`. Au check-out, le total
des extras rattachés est ajouté à la note.

**Ne jamais** dupliquer la logique de facturation POS dans le module hôtel :
toujours réutiliser la table `ventes` et les modèles `Sale`, `SaleDetail`.

---

## 5. Commandes utiles

```bash
# Migrations
mysql -u root -p pos_system < migrations/2026_10_15_hotel_module_v1.sql

# Tests
composer test
composer test:unit
npm run test:e2e

# Lint
composer cs:check
composer static
npm run lint

# Démarrer en local
php -S localhost:8000 -t public/
```

---

## 6. Pièges connus (à NE PAS faire)

- ❌ Modifier le schéma de `ventes` ou `utilisateurs` sans migration dédiée
- ❌ Créer une table `hotel_ventes` séparée — utiliser `ventes` existante
- ❌ Mettre du JS jQuery / Vue / React — projet en JS vanilla uniquement
- ❌ Toucher à la sidebar pour un nouveau rôle sans vérifier `header.php` ligne ~271
- ❌ Commit / push automatique — l'utilisateur commit lui-même, en manuel
- ❌ Réinventer la facturation — toujours passer par `Sale` / `SaleController`

---

## 7. Workflow avec l'utilisateur

L'utilisateur (César) :
- Lit et valide **chaque** document de conception avant tout code
- Valide sprint par sprint, pas en bloc
- Commit lui-même, manuellement, après vérification
- Préfère du code minimal, lisible, commenté en français
- Répond très court (souvent un seul mot) : interpréter le contexte, pas juste le mot

Toute IA qui propose un changement structurel (nouvelle table, nouvelle route,
nouveau rôle, modification de schéma existant) doit d'abord poser la question,
même si le plan de référence semble l'autoriser.

---

## 8. Documents liés

- `docs/PLAN_HOTEL_V1.md` — plan complet du module hôtel
- `docs/STRUCTURE_SIMPLE.md` — structure du projet POS
- `docs/SPECIFICATIONS_TECHNIQUES_OFFICIELLES.md` — spec générales
- `ROUTES.md` — table des routes
- `TODO.md` — tâches en cours (POS)
- `README.md` — vue d'ensemble utilisateur
