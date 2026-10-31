<?php

/**
 * FICHIER DE SECRETS LOCAUX — À COPIER, PAS À MODIFIER DIRECTEMENT
 *
 *   cp config/config.local.example.php config/config.local.php
 *
 * Puis renseigner les valeurs ci-dessous.
 *
 * ⚠️ `config/config.local.php` est IGNORÉ par Git (voir .gitignore) :
 *    il ne doit JAMAIS être commité. Seul ce modèle (…example.php) est
 *    versionné, et il ne contient que des valeurs vides.
 *
 * Ce fichier est chargé APRÈS config/config.php : les constantes définies
 * ici écrasent les valeurs par défaut.
 */

// ── Base de données (production) ──────────────────────────────────
// (facultatif : si absent, config/config.php / les variables d'environnement
//  sont utilisés)
//
// On n'utilise define() que si la constante n'existe pas déjà, pour éviter
// les warnings "Constant already defined" (config/config.php a déjà défini
// DB_HOST, DB_USER, DB_PASS, DB_NAME, APP_URL et DGI_WEBHOOK_TOKEN).
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'pos_app');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'un-mot-de-passe-solide');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'pos_system');
}

// ── URL de l'application (nécessaire pour que l'API OSAT sache où nous
//    rappeler) ────────────────────────────────────────────────────────
if (!defined('APP_URL')) {
    define('APP_URL', 'https://pos.mon-domaine.cd/');
}

// ── Secret partagé avec l'API OSAT ─────────────────────────────────
//
// C'est la valeur qu'OSAT doit envoyer dans l'en-tête `X-DGI-Token`
// de la requête POST /api/dgi/update.
//
// Tant que cette constante est vide, la route REFUSE tous les appels
// (fail-closed) : une écriture en base ne doit jamais être exposée sans
// authentification.
//
// Génération d'un secret solide (32 caractères) :
//   php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
//
// NB : config/config.php a déjà défini DGI_WEBHOOK_TOKEN (vide par défaut).
// On la redéfinit donc explicitement — PHP accepte la redéfinition d'une
// constante (simple warning, pas fatal), et c'est ici que le secret réel
// doit se trouver.
define('DGI_WEBHOOK_TOKEN', '');

