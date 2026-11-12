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

// ── Rapports DGI via OSAT ─────────────────────────────────────
//
// Configuration de la lecture des agrégats fiscaux DGI. Les valeurs
// sensibles peuvent être injectées par variables d'environnement ou par
// config/config.local.php (chargé plus haut dans ce fichier).
if (!defined('DGI_REPORT_ENABLED')) {
    $dgiReportEnabled = getenv('DGI_REPORT_ENABLED');
    define(
        'DGI_REPORT_ENABLED',
        $dgiReportEnabled === false ? true : filter_var($dgiReportEnabled, FILTER_VALIDATE_BOOL)
    );
}

if (!defined('DGI_REPORT_API_URL')) {
    $dgiReportApiUrl = getenv('DGI_REPORT_API_URL');
    define(
        'DGI_REPORT_API_URL',
        $dgiReportApiUrl !== false && $dgiReportApiUrl !== ''
            ? $dgiReportApiUrl
            : 'https://osat-energie.com/dgi/rapport/index.php'
    );
}

if (!defined('DGI_REPORT_API_TOKEN')) {
    $dgiReportApiToken = getenv('DGI_REPORT_API_TOKEN');
    define('DGI_REPORT_API_TOKEN', $dgiReportApiToken !== false ? $dgiReportApiToken : '');
}

if (!defined('DGI_REPORT_CONNECT_TIMEOUT')) {
    $dgiReportConnectTimeout = getenv('DGI_REPORT_CONNECT_TIMEOUT');
    define(
        'DGI_REPORT_CONNECT_TIMEOUT',
        $dgiReportConnectTimeout !== false && ctype_digit((string)$dgiReportConnectTimeout)
            ? max(1, (int)$dgiReportConnectTimeout)
            : 5
    );
}

// Fuseau horaire de la boutique : heure de référence des factures POS.
// La DGI horodate ses factures en UTC, donc les périodes demandées au
// provider sont converties depuis ce fuseau (Kinshasa = UTC+1).
if (!defined('DGI_REPORT_TIMEZONE')) {
    $dgiReportTimezone = getenv('DGI_REPORT_TIMEZONE');
    define(
        'DGI_REPORT_TIMEZONE',
        $dgiReportTimezone !== false && $dgiReportTimezone !== ''
            ? $dgiReportTimezone
            : 'Africa/Kinshasa'
    );
}

// Fuseau des PÉRIODES construites par le tableau de bord.
//
// ⚠️ Distinct de `DGI_REPORT_TIMEZONE` : ici, on suit l'HORLOGE DU SERVEUR
// (Europe/Berlin par défaut => UTC+1), pas l'heure de Kinshasa.
//
// Les bornes restent converties en UTC avant l'appel provider : le provider
// indexe en UTC et ne comprend pas les offsets explicites (`+01:00` =>
// réponse illisible, mesuré). Sans cette conversion, la période demandée
// serait décalée d'une heure et la première heure de chaque journée serait
// perdue.
if (!defined('DGI_REPORT_REQUEST_TIMEZONE')) {
    $dgiReportRequestTimezone = getenv('DGI_REPORT_REQUEST_TIMEZONE');
    define(
        'DGI_REPORT_REQUEST_TIMEZONE',
        $dgiReportRequestTimezone !== false && $dgiReportRequestTimezone !== ''
            ? $dgiReportRequestTimezone
            : date_default_timezone_get()
    );
}
