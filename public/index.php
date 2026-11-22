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
