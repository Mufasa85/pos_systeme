<?php


define('DB_HOST', false !== ($v = getenv('DB_HOST')) ? $v : 'localhost');
define('DB_USER', false !== ($v = getenv('DB_USER')) ? $v : 'root');
define('DB_PASS', false !== ($v = getenv('DB_PASS')) ? $v : 'root');
define('DB_NAME', false !== ($v = getenv('DB_NAME')) ? $v : 'pos_system');

define('APP_URL', 'http://localhost/pos_systeme/');
define('BASE_PATH', dirname(__DIR__) . '/');

// Secret partagé avec l'API OSAT pour la route de mise à jour DGI différée
// (POST /api/dgi/update). La route est REFUSÉE (fail-closed) tant que ce
// secret n'est pas défini — on ne veut pas exposer une écriture en base sans
// authentification.
//
// Deux modes d'authentification sont acceptés côté OSAT :
//   X-DGI-Token    : le secret en clair
//   X-DGI-Signature: HMAC-SHA256 du corps de la requête, calculé avec ce secret
//
// ORDRE DE RÉSOLUTION (du plus prioritaire au moins prioritaire) :
//   1. config/config.local.php   → le fichier de secrets local (non versionné)
//   2. variable d'environnement  → DGI_WEBHOOK_TOKEN
//   3. chaîne vide                → route fermée
//
//  PHP ne permet PAS de redéfinir une constante (la 1re valeur gagne,
// avec un warning). Le secret est donc résolu ICI, avant toute définition,
// pour que config.local.php puisse le fournir sans conflit.
$dgiWebhookToken = false !== ($v = getenv('DGI_WEBHOOK_TOKEN')) ? $v : '';

if (is_file(__DIR__ . '/config.local.php')) {
    // Le fichier de secrets local est chargé en premier, dans un scope
    // isolé. Il fournit le secret via la variable $dgiWebhookToken
    // (et NON via define(), pour éviter tout conflit de redéfinition).
    require_once __DIR__ . '/config.local.php';

    if (!empty($dgiWebhookToken)) {
        // Le fichier local a fourni une valeur : elle prime.
    }
}

define('DGI_WEBHOOK_TOKEN', (string)$dgiWebhookToken);
