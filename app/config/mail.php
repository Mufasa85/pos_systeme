<?php

/**
 * Configuration SMTP pour l'envoi d'emails (OTP, reset password, notifications)
 *
 * Remplissez avec vos informations SMTP réelles.
 */

return [
    'host'       => 'smtp.votre-serveur.com',   // Adresse du serveur SMTP
    'port'       => 587,                         // Port (587 pour TLS, 465 pour SSL, 25 sans chiffrement)
    'encryption' => 'tls',                       // 'tls', 'ssl', ou '' (aucun)
    'username'   => 'infos',   // Identifiant SMTP
    'password'   => 'jqQKE;c?-i@j:U6',        // Mot de passe SMTP
    'from_email' => 'info@nidrdc.com',       // Adresse expéditeur
    'from_name'  => 'POS System',                // Nom affiché
];
