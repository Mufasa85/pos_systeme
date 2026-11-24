<?php

// public/index.php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Configuration (constantes APP_URL, DB_*, DGI_WEBHOOK_TOKEN…).
// Elle est chargée ICI, et pas seulement via App\Core\Database (qui ne
// s'instancie qu'au premier accès BDD) : certaines routes ont besoin de la
// configuration SANS toucher la base — notamment POST /api/dgi/update, qui
// doit lire le secret DGI_WEBHOOK_TOKEN pour authentifier OSAT AVANT toute
// requête SQL. Sans ce require, la constante n'existerait pas au moment du
// contrôle et la route répondrait toujours 401.
//
// require_once : sans harm si Database.php l'a déjà chargée.
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

use App\Core\Router;

// Set base path for AltoRouter before loading routes
//Router::getInstance()->setBasePath('/pos/public');

// ── COMPRESSION DES RÉPONSES (gzip) ──────────────────────────────────────
//
// ⚠️ Pourquoi : le catalogue produits renvoie 3,2 Mo de JSON (et la page
// /produits 13,9 Mo de HTML) pour 6 000 produits. Sans compression, la
// caisse attend le catalogue EN CLAIR sur une connexion 3G/4G de boutique :
// 2 s de plus à chaque ouverture de poste.
//
// Ce bloc active `zlib.output_compression`, le compresseur du niveau SAPI
// (PHP). Il est choisi parce qu'il est SÛR par construction :
//
//   - il ne compresse que si le client a envoyé `Accept-Encoding: gzip` ;
//   - il se désactive AUTOMATIQUEMENT si la réponse porte un
//     `Content-Length` explicite (cas de MediaController, qui sert une
//     image avec `readfile()`) : le fichier est alors renvoyé tel quel,
//     octet pour octet. Vérifié : /media/logo/xxx.jpg → 36 145 octets
//     reçus, `Content-Length` intact, aucune compression ;
//   - il n'a pas besoin de `mod_deflate` / `mod_filter`, absents de
//     nombreuses installations (XAMPP les a en commentaire par défaut) et
//     de certains hébergeurs mutualisés.
//
// Le serveur (Apache) peut aussi compresser via `mod_deflate`
// (cf. public/.htaccess) : les deux mechanisms se tolèrent, Apache
// n'appliquant son filtre que si la réponse n'est pas déjà encodée.
//
// ⚠️ `zlib.output_compression` est modifiable à chaud (`ini_set`) : vérifié
//    ici, `access = 7` (PHP_INI_ALL). Il doit être positionné AVANT que
//    PHP n'ait envoyé les en-têtes : d'où la place de ce bloc, avant le
//    routeur.
//
// ⚠️ Les réponses déjà encodées ne sont pas recompressées
//    (`HTTP_CONTENT_ENCODING`).

if (
    PHP_SAPI !== 'cli'
    && !isset($_SERVER['HTTP_CONTENT_ENCODING'])
    && function_exists('ini_set')
    && stripos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false
) {
    @ini_set('zlib.output_compression', '1');
    @ini_set('zlib.output_compression_level', '5');
}

// ── Authentification MOBILE (token Bearer) ───────────────────────────────
// L'app mobile Android ne manipule pas le cookie de session PHP : elle
// s'authentifie avec un token Bearer renvoyé par POST /api/mobile/auth/login.
//
// Le token est résolu et la session hydratée ICI, AVANT le routeur : toutes
// les routes métier existantes (/api/produits, /api/vente, /api/dgi,
// /api/currency, /api/settings…) deviennent utilisables telles quelles par
// l'app mobile, avec TOUS les guards existants (requireAuth,
// requireCaisseAccess, requireActiveShop, politique des pages).
//
// ⚠️ L'hydratation est FAITE-CLOSED : si le token est invalide, expiré,
//     révoqué, ou si le compte/la boutique est désactivé, la session n'est
//     PAS hydratée → la requête est traitée comme anonyme (401/403).
try {
    (new \App\Services\MobileAuthService())->authenticateRequest();
} catch (\Throwable $e) {
    error_log('[MobileAuth] authenticateRequest error: ' . $e->getMessage());
}

// Load routes
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/api.php';

Router::matcher();
