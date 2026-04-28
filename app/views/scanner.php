<?php

/**
 * Scanner de code-barres - Page autonome
 * Permet de scanner des produits et les ajouter directement au panier
 */

// Chemins corrects depuis app/views
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/Database.php';
require_once __DIR__ . '/../../app/models/Settings.php';

$page = 'scanner';
$storeName = 'Caisse';

// Utiliser le modèle Settings pour récupérer le nom du magasin
$settingsModel = new \App\Models\Settings();
$storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner Code-barres - <?= htmlspecialchars($storeName) ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/scanner.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        const APP_URL = window.location.origin;
    </script>
</head>

<body class="scanner-page">
    <!-- Header -->
    <header class="scanner-header">
        <a href="/caisse" class="back-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <h1>Scanner Code-barres</h1>
        <div class="header-actions">
            <button id="flash-toggle" class="icon-btn" title="Activer/Désactiver le flash">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </button>
        </div>
    </header>

    <!-- Scanner Container -->
    <main class="scanner-container">
        <!-- Scanner Icon Section (shown before starting) -->
        <div id="scanner-intro" class="scanner-intro">
            <div class="scanner-icon-wrapper">
                <svg class="scanner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                    <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                    <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                    <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                    <line x1="7" y1="12" x2="17" y2="12"></line>
                    <line x1="7" y1="8" x2="10" y2="8"></line>
                    <line x1="7" y1="16" x2="10" y2="16"></line>
                    <line x1="14" y1="8" x2="17" y2="8"></line>
                    <line x1="14" y1="16" x2="17" y2="16"></line>
                </svg>
                <div class="scanner-pulse"></div>
            </div>
            <h2>Scanner vos produits</h2>
            <p>Pointez la caméra vers le code-barres du produit pour l'ajouter au panier</p>
            <button id="start-scan-btn" class="btn-scan">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                </svg>
                Commencer le scan
            </button>
        </div>

        <!-- Camera View -->
        <div id="scanner-view" class="scanner-view hidden">
            <div id="scanner-region" class="scanner-region">
                <div class="scanner-frame">
                    <div class="corner top-left"></div>
                    <div class="corner top-right"></div>
                    <div class="corner bottom-left"></div>
                    <div class="corner bottom-right"></div>
                    <div class="scan-line"></div>
                </div>
                <div class="scanner-timer" id="scan-timer">00:00</div>
            </div>

            <div id="camera-select-container" class="camera-select-container hidden">
                <label for="camera-select">Choisir la caméra:</label>
                <select id="camera-select"></select>
            </div>

            <div class="scanner-controls">
                <button id="stop-scan-btn" class="btn-control btn-danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                    </svg>
                    Arrêter le scan
                </button>
                <button id="switch-camera-btn" class="btn-control btn-secondary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 19H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"></path>
                        <path d="M13 5h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <polyline points="8 17 3 12 8 7"></polyline>
                    </svg>
                    Changer de caméra
                </button>
            </div>
        </div>

        <!-- Status Messages -->
        <div id="scanner-status" class="scanner-status hidden">
            <div class="status-icon"></div>
            <div class="status-text"></div>
        </div>

        <!-- Last Scanned Product -->
        <div id="last-scanned" class="last-scanned hidden">
            <div class="scanned-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Dernier produit scanné</span>
            </div>
            <div class="scanned-product">
                <div class="product-info">
                    <div class="product-name" id="scanned-name">-</div>
                    <div class="product-barcode" id="scanned-barcode">-</div>
                </div>
                <div class="product-price" id="scanned-price">-</div>
            </div>
        </div>
    </main>

    <!-- Notification Toast -->
    <div id="notification" class="notification hidden">
        <div class="notification-icon"></div>
        <div class="notification-message"></div>
    </div>

    <!-- Sound Effects -->
    <audio id="beep-success" preload="auto">
        <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU" type="audio/wav">
    </audio>

    <!-- Scripts -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="<?= $baseUrl ?>/assets/js/scanner.js"></script>
    <script>
        // Debug: Vérifier si la bibliothèque est chargée
        window.addEventListener('load', function() {
            console.log('Page scanner chargée');
            console.log('Html5Qrcode disponible:', typeof Html5Qrcode !== 'undefined');

            // Tester l'accès à la caméra
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                console.log('getUserMedia disponible');
                navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(function(stream) {
                        console.log('Caméra accessible');
                        stream.getTracks().forEach(function(track) {
                            track.stop();
                        });
                    })
                    .catch(function(err) {
                        console.log('Erreur caméra:', err.message);
                    });
            }
        });
    </script>
</body>

</html>