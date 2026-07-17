# Spécifications Techniques Officielles - POS System

> **Document** : Spécifications Techniques Officielles (STO)  
> **Projet** : pos_systeme  
> **Version** : 1.0  
> **Date** : Juillet 2026  
> **Statut** : Approuvé pour référence officielle

---

## Table des matières

1. [Objet du document](#objet-du-document)
2. [Portée et périmètre](#portée-et-périmètre)
3. [Documents de référence](#documents-de-référence)
4. [Terminologie et acronymes](#terminologie-et-acronymes)
5. [Contexte et objectifs](#contexte-et-objectifs)
6. [Exigences fonctionnelles](#exigences-fonctionnelles)
7. [Exigences non fonctionnelles](#exigences-non-fonctionnelles)
8. [Architecture technique](#architecture-technique)
9. [Interfaces utilisateur](#interfaces-utilisateur)
10. [Interfaces système et API](#interfaces-système-et-api)
11. [Spécifications de la base de données](#spécifications-de-la-base-de-données)
12. [Sécurité](#sécurité)
13. [Performance et contraintes](#performance-et-contraintes)
14. [Livrables](#livrables)
15. [Phases de mise en œuvre](#phases-de-mise-en-œuvre)
16. [Annexes](#annexes)

---

## Objet du document

Le présent document définit les spécifications techniques officielles du système **POS System**. Il a pour vocation de :

- Décrire l'architecture technique du système
- Lister les exigences fonctionnelles et non fonctionnelles
- Définir les interfaces utilisateur et système
- Servir de référence pour le développement, le déploiement et la maintenance
- Servir de base à la validation et à l'acceptation du système

> Ce document est complémentaire au manuel d'utilisation et au manuel de supervision.

---

## Portée et périmètre

### Inclus dans le périmètre

- Application web de caisse (frontend et backend)
- Gestion des produits, catégories, taxes, clients et utilisateurs
- Enregistrement des ventes avec calculs fiscaux
- Gestion des stocks et alertes de seuil
- Historisation des transactions
- Génération de factures et reçus
- Paiement de factures SNEL et REGIDESO via API externe
- Intégration DGI (Direction Générale des Impôts) via proxy
- Tableau de bord et rapports de supervision
- Gestion des rôles et authentification

### Exclus du périmètre (hors scope actuel)

- Gestion comptable avancée (grand livre, balance)
- Intégration bancaire directe
- Application mobile native (iOS/Android)
- Gestion multi-magasin avec synchronisation temps réel
- Passerelle de paiement mobile money intégrée (hors proxy de facturation)
- Module de gestion des ressources humaines

---

## Documents de référence

| Document | Fichier | Usage |
|----------|---------|-------|
| Documentation technique officielle | `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md` | Référence technique complète |
| Manuel d'utilisation | `MANUEL_UTILISATION.md` | Guide utilisateur final |
| Manuel de contrôle et supervision | `MANUEL_CONTROLE_SUPERVISION.md` | Guide superviseur/gérant |
| Routes du système | `ROUTES.md` | Détail des routes web et API |
| Schéma de base de données | `README-BDD.md` | Modèle relationnel détaillé |
| Historique des modifications | `README-MODIF.md` | Journal des évolutions |
| Dump de base de données | `pos_system-2026-07-06_160842-dump.sql` | Référence du schéma de production |

---

## Terminologie et acronymes

| Terme | Définition |
|-------|------------|
| **POS** | Point of Sale (point de vente / caisse) |
| **MVC** | Model-View-Controller (architecture logicielle) |
| **API** | Application Programming Interface |
| **REST** | Representational State Transfer (style d'architecture API) |
| **PDO** | PHP Data Objects (interface d'accès base de données) |
| **CSRF** | Cross-Site Request Forgery (attaques par requête intersites) |
| **TVA** | Taxe sur la Valeur Ajoutée |
| **HT** | Hors Taxes |
| **TTC** | Toutes Taxes Comprises |
| **DGI** | Direction Générale des Impôts |
| **SNEL** | Société Nationale d'Électricité |
| **REGIDESO** | Régie de Distribution d'Eau |
| **OSAT-Energie** | Fournisseur tiers de services API pour recharges et DGI |
| **RBAC** | Role-Based Access Control (contrôle d'accès par rôle) |
| **PDF** | Portable Document Format |
| **NIF** | Numéro d'Identification Fiscale |
| **ICE** | Identifiant Commun de l'Entreprise |
| **RCCM** | Registre du Commerce et du Crédit Mobilier |
| **IF** | Identifiant Fiscal |

---

## Contexte et objectifs

### Contexte

Le POS System est développé pour répondre aux besoins des petits commerces, supermarchés et boutiques en RDC. Il remplace les caisses manuelles ou les solutions génériques mal adaptées au contexte local (taxes DGI, paiements SNEL/REGIDESO, langue française, connexion internet variable).

### Objectifs

- Centraliser et sécuriser les transactions de vente
- Réduire les erreurs de calcul et de saisie
- Améliorer le suivi des stocks
- Faciliter la facturation et la conformité fiscale
- Permettre le paiement de services publics (eau, électricité) en caisse
- Offrir une supervision simple aux gérants et responsables

---

## Exigences fonctionnelles

### 1. Authentification et gestion des utilisateurs

| ID | Exigence | Priorité |
|----|----------|----------|
| F1 | Le système doit permettre la connexion par nom d'utilisateur et mot de passe. | Obligatoire |
| F2 | Le système doit gérer deux rôles : `admin` et `vendeur`. | Obligatoire |
| F3 | Le système doit permettre la création, modification et désactivation des comptes utilisateurs. | Obligatoire |
| F4 | Le système doit bloquer la connexion des comptes inactifs (`actif = 0`). | Obligatoire |
| F5 | Le système doit hasher les mots de passe avec bcrypt. | Obligatoire |
| F6 | Le système doit permettre l'association d'un code agent à un vendeur. | Optionnel |

### 2. Gestion des produits

| ID | Exigence | Priorité |
|----|----------|----------|
| F7 | Le système doit permettre le CRUD complet des produits. | Obligatoire |
| F8 | Chaque produit doit avoir un code-barres unique. | Obligatoire |
| F9 | Le système doit gérer le stock, le stock minimum et les alertes. | Obligatoire |
| F10 | Le système doit supporter les produits vendus à l'unité et au poids. | Obligatoire |
| F11 | Le système doit permettre l'upload d'une image par produit. | Optionnel |
| F12 | Le système doit classer les produits par catégorie. | Obligatoire |
| F13 | Le système doit appliquer un groupe de taxe par produit. | Obligatoire |
| F14 | Le système doit gérer les remises et taxes spécifiques par produit. | Optionnel |

### 3. Gestion des ventes (caisse)

| ID | Exigence | Priorité |
|----|----------|----------|
| F15 | Le système doit permettre l'ajout de produits au panier par clic, recherche ou scan. | Obligatoire |
| F16 | Le système doit calculer automatiquement les totaux HT, TVA et TTC. | Obligatoire |
| F17 | Le système doit gérer les quantités, incréments et décréments. | Obligatoire |
| F18 | Le système doit bloquer la vente si le stock est insuffisant. | Obligatoire |
| F19 | Le système doit permettre les paiements fractionnés (espèces, Mobile Money, carte). | Obligatoire |
| F20 | Le système doit générer un numéro de facture unique. | Obligatoire |
| F21 | Le système doit enregistrer chaque vente dans la base de données avec le vendeur, la date et les détails. | Obligatoire |
| F22 | Le système doit permettre l'association d'un client à une vente. | Obligatoire |
| F23 | Le système doit gérer les avoirs et retours (quantités négatives). | Optionnel |
| F24 | Le système doit permettre l'annulation d'une vente par un admin. | Obligatoire |

### 4. Gestion des clients

| ID | Exigence | Priorité |
|----|----------|----------|
| F25 | Le système doit permettre le CRUD des clients. | Obligatoire |
| F26 | Le système doit générer automatiquement un code client. | Obligatoire |
| F27 | Le système doit gérer les types de clients (PP, PM, PC, PL, AO). | Obligatoire |
| F28 | Le système doit permettre la recherche de client par numéro de téléphone. | Obligatoire |
| F29 | Le système doit permettre la saisie du NIF et de l'adresse du client. | Optionnel |

### 5. Gestion des taxes

| ID | Exigence | Priorité |
|----|----------|----------|
| F30 | Le système doit gérer les groupes de taxes DGI (A à P). | Obligatoire |
| F31 | Le système doit protéger les 16 taxes système par défaut. | Obligatoire |
| F32 | Le système doit permettre l'ajout de taxes personnalisées. | Optionnel |
| F33 | Le système doit calculer la TVA en fonction du taux de taxe sélectionné. | Obligatoire |

### 6. Facturation et impression

| ID | Exigence | Priorité |
|----|----------|----------|
| F34 | Le système doit générer un reçu/ticket après chaque vente. | Obligatoire |
| F35 | Le système doit permettre l'impression du ticket. | Obligatoire |
| F36 | Le système doit supporter plusieurs formats d'impression (80mm, 57mm, A4, A5, Letter). | Obligatoire |
| F37 | Le système doit permettre l'affichage d'une facture publique via un lien. | Optionnel |
| F38 | Le système doit permettre l'envoi d'une facture par WhatsApp ou SMS. | Optionnel |
| F39 | Le système doit générer des factures au format PDF. | Optionnel |

### 7. Historique et supervision

| ID | Exigence | Priorité |
|----|----------|----------|
| F40 | Le système doit conserver un historique de toutes les ventes. | Obligatoire |
| F41 | Le système doit permettre la recherche et le filtrage des ventes. | Obligatoire |
| F42 | Le système doit afficher un tableau de bord avec les indicateurs clés. | Obligatoire |
| F43 | Le système doit fournir des rapports analytiques (ventes par produit, vendeur, période). | Obligatoire |
| F44 | Le système doit afficher les alertes de stock faible. | Obligatoire |

### 8. Paiement de factures SNEL / REGIDESO

| ID | Exigence | Priorité |
|----|----------|----------|
| F45 | Le système doit permettre la consultation des factures impayées via API OSAT-Energie. | Obligatoire |
| F46 | Le système doit permettre la sélection des mois à payer. | Obligatoire |
| F47 | Le système doit enregistrer le paiement dans l'historique avec le compteur et le fournisseur. | Obligatoire |
| F48 | Le système doit générer un reçu de recharge. | Obligatoire |
| F49 | Le système doit stocker la réponse API pour traçabilité. | Obligatoire |

### 9. Intégration DGI

| ID | Exigence | Priorité |
|----|----------|----------|
| F50 | Le système doit intégrer un proxy local vers l'API DGI. | Obligatoire |
| F51 | Le système doit envoyer les factures à la DGI selon le format attendu. | Obligatoire |
| F52 | Le système doit récupérer et stocker le QR code, la date DGI et le code de confirmation. | Obligatoire |
| F53 | Le système doit gérer le token DGI via les paramètres. | Obligatoire |

---

## Exigences non fonctionnelles

### 1. Performance

| ID | Exigence | Critère |
|----|----------|---------|
| NF1 | Temps de réponse moyen des pages web | < 2 secondes |
| NF2 | Temps de réponse des requêtes API locales | < 500 ms |
| NF3 | Temps de génération d'une facture | < 3 secondes |
| NF4 | Capacité de gestion simultanée | Jusqu'à 5 caisses actives en parallèle |
| NF5 | Temps de chargement initial de la caisse | < 5 secondes |

### 2. Fiabilité

| ID | Exigence | Critère |
|----|----------|---------|
| NF6 | Disponibilité du système | > 95 % pendant les heures d'ouverture |
| NF7 | Perte de données | Aucune perte en cas d'arrêt normal |
| NF8 | Récupération après incident | Restauration sous 4 heures avec sauvegarde quotidienne |

### 3. Sécurité

| ID | Exigence | Critère |
|----|----------|---------|
| NF9 | Authentification | Session + mot de passe hashé |
| NF10 | Autorisation | RBAC avec deux rôles |
| NF11 | Protection des données | Requêtes préparées PDO obligatoires |
| NF12 | CSRF | Token CSRF sur les formulaires sensibles |
| NF13 | Fichiers sensibles | Non accessibles depuis le web |
| NF14 | HTTPS | Obligatoire en production |

### 4. Maintenabilité

| ID | Exigence | Critère |
|----|----------|---------|
| NF15 | Structure du code | MVC clair avec séparation des responsabilités |
| NF16 | Documentation | README, manuels et spécifications à jour |
| NF17 | Migrations | Scripts SQL versionnés dans `migrations/` |
| NF18 | Logs | Erreurs PHP loguées dans `error_log` |

### 5. Utilisabilité

| ID | Exigence | Critère |
|----|----------|---------|
| NF19 | Interface responsive | Fonctionnelle sur desktop, tablette et mobile |
| NF20 | Temps d'apprentissage | < 2 heures pour un vendeur |
| NF21 | Langue | Français |
| NF22 | Feedback utilisateur | Messages clairs en cas d'erreur |

### 6. Compatibilité

| ID | Exigence | Critère |
|----|----------|---------|
| NF23 | Navigateurs | Chrome, Edge, Firefox, Safari (2 dernières versions) |
| NF24 | PHP | Version 8.0 ou supérieure |
| NF25 | MySQL | Version 8.0 ou supérieure |
| NF26 | Serveur web | Apache avec mod_rewrite |
| NF27 | Imprimantes | Imprimantes thermiques 80mm/57mm et classiques A4/A5 |

---

## Architecture technique

### Architecture générale

L'application suit une architecture **monolithique MVC** en PHP :

- **Modèle** : accès aux données via PDO/MySQL (`app/models/`)
- **Vue** : templates PHP/HTML (`app/views/`)
- **Contrôleur** : logique métier et orchestration (`app/controllers/`)
- **Routeur** : AltoRouter 2.0
- **Base de données** : MySQL 9.1 avec moteur InnoDB
- **Frontend** : JavaScript vanilla, CSS3, HTML5

### Diagramme simplifié du flux

```
Navigateur
    ↓ HTTP/HTTPS
Apache + .htaccess (réécriture)
    ↓
public/index.php (point d'entrée unique)
    ↓
AltoRouter (routes/web.php + routes/api.php)
    ↓
Contrôleur
    ↓
Modèle ←→ PDO ←→ MySQL
    ↓
Vue / Réponse JSON
```

### Couche de données

- **Database** : singleton PDO avec requêtes préparées
- **Modèles** : une classe par table principale
- **Transactions** : utilisées pour les ventes et les recharges afin d'assurer la cohérence

### Couche de présentation

- **Layout** : `header.php` et `footer.php`
- **Pages** : une vue par écran fonctionnel
- **JavaScript** : `app.js` (logique caisse), `recharges.js` (factures), `scanner.js`, `theme.js`, `paper-type.js`

### Couche de sécurité

- Sessions PHP
- Vérification des rôles sur les routes sensibles
- CSRF token via `App\Core\Security`
- Sanitisation basique via `Controller::sanitaze()`

---

## Interfaces utilisateur

### Écrans obligatoires

| Écran | Utilisateur | Usage |
|-------|-------------|-------|
| Login | Tous | Authentification |
| Tableau de bord | Tous | Vue d'ensemble de l'activité |
| Caisse | Vendeur, Admin | Enregistrement des ventes |
| Produits | Admin | Gestion du catalogue |
| Catégories | Admin | Gestion des groupes de produits |
| Utilisateurs | Admin | Gestion des comptes |
| Historique | Tous | Consultation des ventes |
| Taxes | Admin | Gestion des taxes |
| Paramètres | Admin | Configuration du magasin |
| Analytics | Admin | Rapports et statistiques |
| Recharges | Vendeur, Admin | Paiement SNEL/REGIDESO |
| Scanner | Vendeur, Admin | Scan de code-barres par caméra |
| Facture | Tous | Affichage et impression |

### Charte graphique

- Police principale : Inter
- Police monospace : JetBrains Mono
- Couleur principale : configurée via le thème (indigo par défaut)
- Theme-color : `#0B5E88`
- Icônes : SVG inline
- Responsive : oui

---

## Interfaces système et API

### API REST interne

L'application expose une API REST via les routes définies dans `routes/api.php`.

Format des échanges : **JSON**.

Authentification : **session PHP** (cookie).

Exemples de endpoints obligatoires :

- `GET /api/produits` : liste des produits
- `POST /api/produit` : création d'un produit
- `POST /api/vente` : création d'une vente
- `GET /api/taxes` : liste des taxes
- `POST /api/taxes` : création d'une taxe
- `GET /api/settings` : lecture des paramètres
- `POST /api/settings` : mise à jour des paramètres
- `GET /api/clients` : liste des clients
- `POST /api/client` : création d'un client

### API externes via proxy

| Service externe | Route proxy | Protocole |
|-----------------|-------------|-----------|
| API DGI | `/api/dgi` | HTTPS |
| SMS DGI | `/api/dgi/sms` | HTTPS |
| Facture enregistrée DGI | `/api/service-bill` | HTTPS |
| Taux de change | `/api/currency` | HTTPS |
| OSAT-Energie Bill Payment | `/api/bill-payment` | HTTPS |

### Contraintes d'intégration

- Les appels API externes se font via des proxies locaux pour éviter les problèmes CORS.
- Le token DGI est lu depuis la table `settings` (clé `token`).
- Les réponses API sont stockées dans `ventes.api_response` pour traçabilité.

---

## Spécifications de la base de données

### Système de gestion de base de données

- **SGDB** : MySQL 9.1
- **Moteur** : InnoDB
- **Jeu de caractères** : utf8mb4
- **Collation** : utf8mb4_0900_ai_ci

### Tables principales

| Table | Description | Volumétrie estimée |
|-------|-------------|-------------------|
| `categories` | Groupes de produits | < 100 |
| `clients` | Clients enregistrés | < 100 000 |
| `type_client` | Types de clients (PP, PM, PC, PL, AO) | < 10 |
| `produits` | Catalogue produits | < 50 000 |
| `taxes` | Groupes de taxes DGI | < 100 |
| `utilisateurs` | Comptes vendeurs et admins | < 100 |
| `ventes` | Transactions de vente | < 1 000 000 |
| `details_vente` | Lignes de vente | < 10 000 000 |
| `service_providers` | Fournisseurs SNEL/REGIDESO | < 10 |
| `settings` | Paramètres clé/valeur | < 200 |

### Contraintes d'intégrité

- Clés primaires auto-incrémentées
- Clés étrangères avec `ON DELETE CASCADE` ou `ON DELETE SET NULL` selon la règle métier
- Codes-barres et numéros de facture uniques
- Seuils de stock et types de produits contrôlés

### Indexation

- Index sur les clés étrangères
- Index sur `ventes.numero_facture`, `ventes.type_vente`, `ventes.numero_compteur`, `ventes.provider_id`, `ventes.service`
- Index sur `produits.code_barres`, `produits.product_type`, `produits.category_id`

---

## Sécurité

### Exigences de sécurité

- Authentification par session PHP avec durée définie par le serveur
- Mots de passe hashés avec `password_hash()` (bcrypt, coût 10)
- Vérification du rôle avant chaque action sensible
- Requêtes SQL préparées obligatoires (pas de concaténation SQL)
- Protection CSRF sur les formulaires de modification
- Fichiers sensibles (`app/`, `config/`, `routes/`, `vendor/`, dumps SQL) non exposés via le web
- HTTPS obligatoire en production
- Logs des erreurs PHP activés

### Menaces identifiées et mesures

| Menace | Mesure |
|--------|--------|
| Injection SQL | Requêtes préparées PDO |
| XSS | Sanitisation et `htmlspecialchars` dans les vues |
| CSRF | Token CSRF en session |
| Accès non autorisé | RBAC, session, vérification admin |
| Fuite de données | .htaccess, DocumentRoot sur `public/` |
| Manipulation de prix | Contrôle admin sur les modifications de produits |

---

## Performance et contraintes

### Contraintes techniques

- PHP 8.0 minimum
- MySQL 8.0 minimum
- Extension PHP requises : `pdo_mysql`, `mbstring`, `curl`, `json`, `fileinfo`
- Apache avec `mod_rewrite` activé
- Espace disque minimum : 1 Go (hors données)
- RAM minimum : 512 Mo (1 Go recommandé)

### Contraintes opérationnelles

- Connexion internet requise pour les intégrations DGI et SNEL/REGIDESO
- Sauvegarde quotidienne recommandée
- DocumentRoot doit pointer vers `public/`
- Les migrations SQL doivent être appliquées avant chaque mise à jour majeure

### Objectifs de performance

- Temps de réponse des pages principales < 2 secondes
- Génération de facture < 3 secondes
- Recherche produit < 500 ms
- Capacité à supporter 5 caisses simultanées

---

## Livrables

| Livrable | Format | Emplacement |
|----------|--------|-------------|
| Code source | PHP, JS, CSS, SQL | Dossier racine du projet |
| Documentation technique | Markdown | `README_DOCUMENTATION_TECHNIQUE_OFFICIELLE.md` |
| Manuel d'utilisation | Markdown | `MANUEL_UTILISATION.md` |
| Manuel de supervision | Markdown | `MANUEL_CONTROLE_SUPERVISION.md` |
| Spécifications techniques | Markdown | `SPECIFICATIONS_TECHNIQUES_OFFICIELLES.md` |
| Schéma de base de données | Markdown + SQL | `README-BDD.md`, `pos_system-*.sql` |
| Routes | Markdown | `ROUTES.md` |
| Migrations | SQL | `migrations/` |
| Dépendances | Composer | `composer.json`, `composer.lock` |

---

## Phases de mise en œuvre

### Phase 1 : Préparation

- Installation de l'environnement (PHP, MySQL, Apache)
- Création de la base de données
- Import du dump SQL de référence
- Configuration de `config/config.php` et `app/core/Database.php`
- Installation des dépendances Composer

### Phase 2 : Déploiement

- Mise en place du DocumentRoot sur `public/`
- Configuration du `.htaccess`
- Activation de HTTPS
- Vérification des permissions des dossiers

### Phase 3 : Configuration métier

- Paramètres du magasin (nom, adresse, téléphone, ICE, RCCM, IF)
- Taux de TVA par défaut
- Thème et format d'impression
- Token DGI
- Création des comptes utilisateurs
- Saisie des catégories et produits

### Phase 4 : Formation

- Formation des vendeurs (utilisation de la caisse, scanner, recharges)
- Formation des superviseurs (contrôle, historique, analytics, paramètres)
- Tests en conditions réelles

### Phase 5 : Mise en production

- Lancement officiel
- Sauvegarde initiale
- Suivi des premiers jours
- Correction des éventuels bugs

### Phase 6 : Maintenance

- Sauvegardes régulières
- Application des migrations
- Mises à jour de sécurité (PHP, MySQL, Composer)
- Évolution fonctionnelle si besoin

---

## Annexes

### Annexe A : Liste des fichiers critiques

| Fichier | Rôle |
|---------|------|
| `public/index.php` | Point d'entrée unique |
| `public/.htaccess` | Réécriture Apache et protection |
| `config/config.php` | Constantes de configuration |
| `app/core/Database.php` | Connexion PDO |
| `app/core/Router.php` | Routage AltoRouter |
| `app/core/Security.php` | Token CSRF |
| `app/App.php` | Singleton AltoRouter |
| `routes/web.php` | Routes web |
| `routes/api.php` | Routes API |
| `app/controllers/Controller.php` | Classe de base contrôleur |
| `app/models/Sale.php` | Modèle ventes |
| `app/models/Product.php` | Modèle produits |
| `app/models/User.php` | Modèle utilisateurs |
| `app/models/Settings.php` | Modèle paramètres |
| `public/assets/js/app.js` | Logique principale caisse |
| `public/assets/js/recharges.js` | Paiement factures SNEL/REGIDESO |

### Annexe B : Gestion des erreurs

Les erreurs sont loguées dans les logs PHP via `error_log`. Les codes HTTP retournés par l'API incluent :

- `200` : succès
- `400` : requête invalide (champs manquants, format incorrect)
- `401` : non authentifié
- `403` : accès refusé (rôle insuffisant)
- `404` : ressource non trouvée
- `500` : erreur serveur

### Annexe C : Validation et acceptation

Le système est considéré comme accepté lorsque :

- Toutes les exigences obligatoires (F1-F54) sont satisfaites
- Les tests de caisse, de recharge, d'impression et de connexion sont réussis
- Les sauvegardes et restaurations sont testées
- Les manuels utilisateur et superviseur sont fournis
- Le déploiement en production est stable pendant 7 jours consécutifs

---

*Fin des spécifications techniques officielles.*
