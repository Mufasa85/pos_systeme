# 🚀 Guide de Déploiement POS System sur Hostinger (Sous-domaine)

Ce guide explique comment déployer le système POS sur Hostinger via un **sous-domaine** avec la route pointant vers le dossier `public`.

---

## 📋 Prérequis

- Un compte Hostinger avec hébergement PHP
- Accès au panneau de contrôle Hostinger (hPanel)
- Accès FTP ou Gestionnaire de fichiers Hostinger
- Base de données MySQL créée sur Hostinger
- Un sous-domaine créé (ex: `caisse.votredomaine.com`)

---

## 📁 Structure du Projet

```
pos_systeme/
├── /app              → Contrôleurs, Modèles, Vues
├── /public           → Point d'entrée (index.php) ← DOIT ÊTRE LA RACINE
├── /config           → Configuration
├── /routes           → Routes web et API
├── /vendor           → Dépendances Composer
├── composer.json     → Dépendances PHP
└── pos_system.sql     → Script SQL de la base de données
```

> ⚠️ **Important** : Le dossier `public` sera la racine du sous-domaine.

---

## Étape 1 : Créer le Sous-domaine sur Hostinger

### 1.1 Via hPanel

1. Connectez-vous à [hPanel Hostinger](https://hpanel.hostinger.com)
2. Allez dans **Domaine → Gérer les sous-domaines**
3. Cliquez sur **Créer un sous-domaine**
4. Entrez le nom du sous-domaine (ex: `caisse`)
5. Choisissez le dossier de destination : `public_html/caisse/public`
6. Cliquez sur **Créer**

Hostinger va automatiquement :

- Créer le dossier `public_html/caisse/`
- Créer un dossier `public/` à l'intérieur
- Configurer le sous-domaine pour pointer vers `public/`

---

## Étape 2 : Préparation des Fichiers

### 2.1 Exclure les fichiers sensibles (optionnel)

Avant l'upload, SUPPRIMEZ ces dossiers/fichiers s'ils existent :

- `.git/` (dossier Git)
- `.gitignore`
- `.cursorrules`
- `.php-cs-fixer.cache`
- `composer.lock` (optionnel)
- Dossier `test/` ou `docs/`

### 2.2 Vérifier le contenu de `public/`

Assurez-vous que le dossier `public/` contient :

```
public/
├── index.php          ← Point d'entrée principal
├── .htaccess          ← Configuration Apache
└── /assets
    ├── /css
    ├── /js
    └── /images
```

### 2.3 Structure finale sur le serveur

```
public_html/
└── /caisse              ← Dossier du sous-domaine
    ├── /public           ← RACINE DU SOUS-DOMAINE (Document Root)
    │   ├── index.php
    │   ├── .htaccess
    │   └── /assets
    ├── /app
    ├── /config
    ├── /routes
    ├── /vendor
    └── composer.json
```

---

## Étape 3 : Créer la Base de Données sur Hostinger

### 3.1 Via hPanel

1. Connectez-vous à [hPanel Hostinger](https://hpanel.hostinger.com)
2. Allez dans **Base de données → MySQL**
3. Cliquez sur **Créer une nouvelle base de données**
4. Notez les informations :
   - Nom de la base
   - Nom d'utilisateur
   - Mot de passe
   - Hôte (souvent `localhost` ou une adresse spécifique)

### 3.2 Importer la Base de Données

1. Dans **phpMyAdmin** (accessible depuis hPanel)
2. Sélectionnez votre base de données
3. Cliquez sur **Importer**
4. Uploadez le fichier `pos_system.sql` ou `pos_system3.sql`

---

## Étape 4 : Configurer les Paramètres

### 4.1 Modifier `config/config.php`

Mettez à jour avec les informations de votre serveur Hostinger :

```php
<?php
// config/config.php

define('DB_HOST', 'localhost');        // Ou l'adresse MySQL de Hostinger
define('DB_USER', 'votre_utilisateur'); // Utilisateur de la base
define('DB_PASS', 'votre_mot_de_passe'); // Mot de passe
define('DB_NAME', 'votre_base');        // Nom de la base

// URL de l'application (sous-domaine)
define('APP_URL', 'https://caisse.votre-domaine.com');
define('BASE_PATH', dirname(__DIR__) . '/');
```

---

## Étape 5 : Uploader les Fichiers

### 5.1 Via FTP (FileZilla)

1. Connectez-vous avec les identifiants FTP de Hostinger
2. Naviguez vers `public_html/caisse/`
3. Uploadez TOUT le contenu du projet (y compris `public/`, `app/`, `config/`, etc.)

**OU**

### 5.2 Via Gestionnaire de Fichiers Hostinger

1. Ouvrez le **Gestionnaire de fichiers** dans hPanel
2. Naviguez vers `public_html/caisse/`
3. Uploadez les fichiers en arrastrant

---

## Étape 6 : Installer les Dépendances Composer

### 6.1 Via Terminal SSH

```bash
# Connexion SSH via Hostinger
ssh utilisateur@votre-serveur

# Naviguer vers le projet
cd public_html/caisse

# Installer les dépendances
composer install --no-dev --optimize-autoloader
```

### 6.2 Si SSH non disponible

Uploadez le dossier `vendor/` depuis votre environnement local (déjà compilé avec Composer).

---

## Étape 7 : Vérifier les Permissions

### 7.1 Permissions des dossiers

Assurez-vous que ces dossiers ont les bonnes permissions :

| Dossier   | Permission | Via hPanel                                          |
| --------- | ---------- | --------------------------------------------------- |
| `/vendor` | 755        | Gestionnaire de fichiers → clic droit → Permissions |
| `/app`    | 755        | Même méthode                                        |
| `/config` | 755        | Même méthode                                        |
| `/routes` | 755        | Même méthode                                        |

### 7.2 Fichier de configuration

Si vous avez des erreurs d'écriture, donnez 644 au fichier `config.php`.

---

## Étape 8 : Vérifier la Configuration PHP

### 8.1 Via hPanel

1. Allez dans **Paramètres avancés → PHP**
2. Vérifiez que la version PHP est **PHP 8.0+**
3. Activez les extensions nécessaires :
   - `pdo_mysql`
   - `mbstring`
   - `curl`
   - `json`

---

## Étape 9 : Configurer le .htaccess dans `public/`

Créez ou modifiez le fichier `.htaccess` dans `public_html/caisse/public/.htaccess` :

```apache
# Activer le module de réécriture
RewriteEngine On

# Forcer HTTPS (recommandé)
# Forcer HTTPS (compatible avec les serveurs avec proxy comme Cloudflare/Hostinger)
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Empêcher l'accès aux dossiers sensibles
RewriteRule ^app/ - [F,L]
RewriteRule ^config/ - [F,L]
RewriteRule ^routes/ - [F,L]
RewriteRule ^vendor/ - [F,L]
RewriteRule ^\.env$ - [F,L]

# Configurer lecharset
AddDefaultCharset UTF-8

# Compression GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/javascript
</IfModule>

# Cache des assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

---

## Étape 10 : Tester le Déploiement

### 10.1 Accéder à l'application

Ouvrez votre navigateur et accédez à :

```
https://caisse.votre-domaine.com
```

### 10.2 Tests à effectuer

- [ ] Page de connexion s'affiche
- [ ] Connexion avec identifiants fonctionne
- [ ] Ajout de produits fonctionne
- [ ] Système de caisse fonctionne
- [ ] Génération de facture fonctionne

---

## 🔧 Dépannage

### Erreur 500 (Internal Server Error)

1. Vérifiez le fichier `.htaccess` dans `public/`
2. Vérifiez les permissions (755 pour les dossiers)
3. Consultez les logs d'erreur dans hPanel

### Erreur "No such file or directory" pour autoload.php

1. Vérifiez que le dossier `vendor/` est bien uploadé
2. Exécutez `composer install` sur le serveur

### Erreur de connexion MySQL

1. Vérifiez les identifiants dans `config/config.php`
2. Vérifiez que l'hôte MySQL est correct (`localhost` ou adresse spécifique)
3. Assurez-vous que l'utilisateur a accès à la base

### Page blanche

1. Ajoutez cette ligne au début de `index.php` pour voir les erreurs :
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

### CSS/JS non chargé

1. Vérifiez que le dossier `assets/` est dans `public/`
2. Vérifiez les chemins dans `APP_URL` dans `config.php`

### Sous-domaine non trouvé

1. Vérifiez que le sous-domaine est bien créé dans hPanel
2. Attendez quelques minutes après la création (Propagation DNS)
3. Vérifiez que le DNS pointe vers le bon serveur

---

## 🌐 Configuration SSL (HTTPS)

Hostinger fournit automatiquement un certificat SSL Let's Encrypt pour les sous-domaines.

### 5.1 Activer HTTPS forcé

Assurez-vous que le `.htaccess` contient :

```apache
RewriteEngine On
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 5.2 Installer le certificat SSL

1. Allez dans **Domaine → SSL**
2. Sélectionnez votre sous-domaine
3. Cliquez sur **Installer** pour le certificat gratuit

---

## 📞 Ressources Utiles

- **hPanel Hostinger** : https://hpanel.hostinger.com
- **Gestion des sous-domaines** : https://www.hostinger.fr/tutoriels/comment-creer-sous-domaine
- **Documentation Hostinger** : https://www.hostinger.fr/tutoriels
- **Support Hostinger** : Chat en direct disponible 24/7

---

## ✅ Checklist Finale

Avant de lancer en production :

- [ ] Sous-domaine créé et configuré
- [ ] Base de données importée
- [ ] `config.php` mis à jour avec les identifiants Hostinger
- [ ] `vendor/` uploadé (ou Composer exécuté)
- [ ] Dossier `public/` configuré comme Document Root
- [ ] Permissions des dossiers vérifiées (755)
- [ ] Extensions PHP activées (pdo_mysql)
- [ ] SSL/HTTPS forcé
- [ ] Test de connexion réussi

---

## 🔗 URL Finale

```
https://caisse.votre-domaine.com
```

---

**Bon déploiement ! 🎉**
