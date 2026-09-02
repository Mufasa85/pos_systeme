# Guide de test — POS Systeme

Ce document liste toutes les commandes pour exécuter les tests du projet : **statiques, unitaires, intégration, fonctionnels, E2E, visuels, ESLint et Husky**.

## 1. Installation

Ouvrir un terminal dans `c:\laragon\www\ArcaneCore_projet\pos_systeme`.

L'application est servie par défaut sur `http://localhost/ArcaneCore_projet/pos_systeme/public/`.

### PHP

```powershell
composer update
```

### Node

```powershell
npm install
npx playwright install
```

> Si PowerShell bloque `npm`, utilisez `cmd /c "npm ..."` ou ouvrez un terminal classique (`cmd`).

## 2. Base de données de test

Par défaut, PHPUnit utilise la base `pos_systeme_test` (configuré dans `phpunit.xml`).
Créez-la une fois :

```sql
CREATE DATABASE pos_systeme_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis importez-y le dump du projet.

## 3. Commandes PHP

| Type | Commande |
|------|----------|
| **Tous les tests PHPUnit** | `composer test` |
| **Unitaire** | `composer test:unit` |
| **Intégration** | `composer test:integration` |
| **Fonctionnel** | `composer test:functional` |
| **PHP + PHPStan + PHPCS** | `composer test:all` |
| **Analyse statique (PHPStan)** | `composer static` |
| **Style PSR12 (dry-run)** | `composer cs:check` |
| **Correction auto style** | `composer cs:fix` |

## 4. Commandes JavaScript

| Type | Commande |
|------|----------|
| **Linter JS (ESLint)** | `npm run lint` |
| **Linter JS auto-fix** | `npm run lint:fix` |
| **E2E (Playwright)** | `npm run test:e2e` |
| **Visuel (Playwright)** | `npm run test:visual` |
| **Mode UI Playwright** | `npm run test:ui` |

Pour pointer vers une autre URL :

```powershell
$env:BASE_URL="http://localhost/ArcaneCore_projet/pos_systeme/public"; npm run test:e2e
$env:APP_URL="http://localhost/ArcaneCore_projet/pos_systeme/public"; composer test:integration
```

## 5. Mise à jour des screenshots de référence (visuel)

```powershell
npx playwright test --project=visual --update-snapshots
```

## 6. Husky — pre-commit

À chaque `git commit`, Husky exécute automatiquement :

```
composer cs:check
npm run lint
```

Si une des commandes échoue, le commit est bloqué.

Pour contourner temporairement :

```powershell
git commit --no-verify
```

## 7. Commande "tout tester" (manuelle)

```powershell
composer test:all
npm run lint
npm run test:e2e
```

> Les tests E2E et visuels nécessitent que le serveur local (`Apache + MySQL`) soit démarré.

## 8. Arborescence des tests

```
tests/
├── Unit/
├── Integration/
├── Functional/
└── bootstrap.php
e2e/
├── login.spec.js
└── visual/
    └── login.visual.spec.js
```

## Commandes à exécuter 

```bash
composer update
npm install
npx playwright install
```

## Type               Commande

Tests PHP             composer test
Unitaire              composer test:unit
Intégration           composer test:integration
Fonctionnel           composer test:functional
PHP+statique+style    composer test:all
Analyse statique      composer static
Style PHP             composer cs:check / composer cs:fix
ESLint                npm run lint / npm run lint:fix
E2E                   npm run test:e2e
Visuel                npm run test:visual

Ajoutez vos propres tests dans ces dossiers.
