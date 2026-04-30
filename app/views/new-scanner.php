<?php

/**
 * Scanner de code-barres - NOUVEAU SYSTÈME PROPRE
 * Utilise html5-qrcode pour une compatibilité maximale
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/Database.php';
require_once __DIR__ . '/../../app/models/Settings.php';

$settingsModel = new \App\Models\Settings();
$storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner - <?= htmlspecialchars($storeName) ?></title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a1628;
            min-height: 100vh;
            color: white;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #0B5E88, #074a68);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            font-size: 20px;
            font-weight: 600;
        }

        .header a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
        }

        /* HTTPS Warning */
        .https-warning {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            padding: 12px 20px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: none;
        }

        .https-warning.show {
            display: block;
        }

        /* Debug Panel */
        .debug-panel {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 20px;
            font-size: 12px;
            font-family: monospace;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .debug-panel span {
            opacity: 0.7;
        }

        .debug-panel .ok {
            color: #4CAF50;
        }

        .debug-panel .error {
            color: #f44336;
        }

        .debug-panel .pending {
            color: #ff9800;
        }

        /* Main Content */
        .container {
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Start Button */
        .start-section {
            text-align: center;
            padding: 60px 20px;
        }

        .start-section svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.5);
        }

        .start-section h2 {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .start-section p {
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 30px;
            font-size: 16px;
        }

        .btn-start {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 18px 36px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(76, 175, 80, 0.5);
        }

        /* Scanner Section */
        .scanner-section {
            display: none;
        }

        .scanner-section.active {
            display: block;
        }

        /* Camera Preview */
        .camera-box {
            width: 100%;
            height: 320px;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        .camera-box video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Scanner Overlay */
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 260px;
            height: 140px;
            border: 3px solid rgba(76, 175, 80, 0.8);
            border-radius: 12px;
            pointer-events: none;
        }

        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 100%;
            background: linear-gradient(transparent, #4CAF50, transparent);
            animation: scanMove 2s linear infinite;
        }

        @keyframes scanMove {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        .corner-tl,
        .corner-tr,
        .corner-bl,
        .corner-br {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #4CAF50;
            border-style: solid;
        }

        .corner-tl {
            top: -3px;
            left: -3px;
            border-width: 4px 0 0 4px;
            border-radius: 4px 0 0 0;
        }

        .corner-tr {
            top: -3px;
            right: -3px;
            border-width: 4px 4px 0 0;
            border-radius: 0 4px 0 0;
        }

        .corner-bl {
            bottom: -3px;
            left: -3px;
            border-width: 0 0 4px 4px;
            border-radius: 0 0 0 4px;
        }

        .corner-br {
            bottom: -3px;
            right: -3px;
            border-width: 0 4px 4px 0;
            border-radius: 0 0 4px 0;
        }

        /* Timer */
        .timer {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.6);
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            font-family: monospace;
        }

        /* Camera Select */
        .camera-select-box {
            margin-top: 16px;
            text-align: center;
        }

        .camera-select-box label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-right: 8px;
        }

        .camera-select-box select {
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 14px;
            cursor: pointer;
        }

        .camera-select-box select option {
            background: #1a1a2e;
        }

        /* Controls */
        .controls {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-control {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-stop {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }

        .btn-switch {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-control:active {
            transform: scale(0.98);
        }

        /* Last Product */
        .last-product {
            margin-top: 24px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: none;
        }

        .last-product.show {
            display: block;
        }

        .last-product-header {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4CAF50;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .last-product-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .last-product-info {
            flex: 1;
        }

        .last-product-name {
            font-size: 18px;
            font-weight: 600;
        }

        .last-product-barcode {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            font-family: monospace;
        }

        .last-product-price {
            font-size: 22px;
            font-weight: 700;
            color: #4CAF50;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            padding: 14px 24px;
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 100;
            max-width: 90%;
            text-align: center;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .toast.success {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
        }

        .toast.error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }

        .toast.warning {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }

        /* Hidden */
        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1>📷 Scanner</h1>
        <a href="/caisse">←</a>
    </div>

    <!-- HTTPS Warning -->
    <div id="https-warning" class="https-warning">
        ⚠️ HTTPS requis pour la caméra. Veuillez utiliser https://
    </div>

    <!-- Debug Panel -->
    <div class="debug-panel" id="debug-panel">
        <span id="debug-camera">○ Caméra: en attente</span> |
        <span id="debug-permission">○ Permission: en attente</span> |
        <span id="debug-stream">○ Flux: en attente</span>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Start Section -->
        <div id="start-section" class="start-section">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                <circle cx="12" cy="13" r="4" />
            </svg>
            <h2>Scanner Code-barres</h2>
            <p>Pointez la caméra vers le code-barres du produit</p>
            <button id="btn-start" class="btn-start">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3" />
                </svg>
                Commencer le scan
            </button>
        </div>

        <!-- Scanner Section -->
        <div id="scanner-section" class="scanner-section">
            <div id="camera-box" class="camera-box">
                <!-- html5-qrcode va rendre le vidéo ici -->
                <div id="qr-reader" style="width:100%;height:100%;"></div>
                <div class="scanner-overlay">
                    <div class="corner-tl"></div>
                    <div class="corner-tr"></div>
                    <div class="corner-bl"></div>
                    <div class="corner-br"></div>
                </div>
                <div class="timer" id="timer">00:00</div>
            </div>

            <div class="camera-select-box">
                <label>Caméra:</label>
                <select id="camera-select">
                    <option value="">Chargement...</option>
                </select>
            </div>

            <div class="controls">
                <button id="btn-stop" class="btn-control btn-stop">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="6" y="6" width="12" height="12" rx="2" />
                    </svg>
                    Arrêter
                </button>
                <button id="btn-switch" class="btn-control btn-switch">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 19H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5" />
                        <path d="M13 5h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5" />
                        <polyline points="16 17 21 12 16 7" />
                        <polyline points="8 17 3 12 8 7" />
                    </svg>
                    Changer
                </button>
            </div>

            <div id="last-product" class="last-product">
                <div class="last-product-header">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Dernier produit scanné</span>
                </div>
                <div class="last-product-body">
                    <div class="last-product-info">
                        <div class="last-product-name" id="product-name">-</div>
                        <div class="last-product-barcode" id="product-barcode">-</div>
                    </div>
                    <div class="last-product-price" id="product-price">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"></div>

    <script>
        // ============================================
        // NOUVEAU SCANNER - CODE PROPRE ET FONCTIONNEL
        // ============================================

        const APP_URL = window.location.origin;
        let html5QrCode = null;
        let isScanning = false;
        let scanStartTime = null;
        let timerInterval = null;
        let cameras = [];
        let currentCameraId = null;
        let lastScannedCode = null;
        let scanCooldown = false;

        // Éléments DOM
        const elements = {
            startSection: document.getElementById('start-section'),
            scannerSection: document.getElementById('scanner-section'),
            btnStart: document.getElementById('btn-start'),
            btnStop: document.getElementById('btn-stop'),
            btnSwitch: document.getElementById('btn-switch'),
            cameraSelect: document.getElementById('camera-select'),
            timer: document.getElementById('timer'),
            toast: document.getElementById('toast'),
            httpsWarning: document.getElementById('https-warning'),
            debugCamera: document.getElementById('debug-camera'),
            debugPermission: document.getElementById('debug-permission'),
            debugStream: document.getElementById('debug-stream'),
            lastProduct: document.getElementById('last-product'),
            productName: document.getElementById('product-name'),
            productBarcode: document.getElementById('product-barcode'),
            productPrice: document.getElementById('product-price')
        };

        // ============================================
        // DÉBOGAGE
        // ============================================
        function debug(type, message, status) {
            const statusText = status === 'ok' ? '✓' : status === 'error' ? '✗' : '○';
            const statusClass = status === 'ok' ? 'ok' : status === 'error' ? 'error' : 'pending';

            if (type === 'camera') elements.debugCamera.innerHTML = `${statusText} Caméra: ${message}`;
            if (type === 'permission') elements.debugPermission.innerHTML = `${statusText} Permission: ${message}`;
            if (type === 'stream') elements.debugStream.innerHTML = `${statusText} Flux: ${message}`;

            console.log(`[SCANNER] ${type}: ${message}`);
        }

        // ============================================
        // VÉRIFICATION HTTPS
        // ============================================
        function checkHTTPS() {
            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                elements.httpsWarning.classList.add('show');
            }
        }

        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(message, type = 'success') {
            elements.toast.className = `toast ${type} show`;
            elements.toast.textContent = message;

            setTimeout(() => {
                elements.toast.classList.remove('show');
            }, 4000);
        }

        // ============================================
        // BIP SONORE
        // ============================================
        function playBeep() {
            try {
                const audioContext = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 1200;
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.4, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.15);
            } catch (e) {
                console.log('Audio non disponible');
            }
        }

        // ============================================
        // VIBRATION
        // ============================================
        function vibrate() {
            if ('vibrate' in navigator) {
                navigator.vibrate([100, 50, 100]);
            }
        }

        // ============================================
        // TIMER
        // ============================================
        function startTimer() {
            scanStartTime = Date.now();
            timerInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - scanStartTime) / 1000);
                const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
                const seconds = (elapsed % 60).toString().padStart(2, '0');
                elements.timer.textContent = `${minutes}:${seconds}`;
            }, 1000);
        }

        function stopTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            elements.timer.textContent = '00:00';
        }

        // ============================================
        // LISTE DES CAMÉRAS
        // ============================================
        async function loadCameras() {
            try {
                debug('camera', 'Recherche en cours...', 'pending');

                // Demander permission d'abord
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: true
                });
                stream.getTracks().forEach(track => track.stop());
                debug('permission', 'Accordée ✓', 'ok');

                // Lister les caméras
                const devices = await navigator.mediaDevices.enumerateDevices();
                cameras = devices.filter(d => d.kind === 'videoinput');

                debug('camera', `${cameras.length} trouvée(s) ✓`, 'ok');

                if (cameras.length === 0) {
                    showToast('Aucune caméra détectée', 'error');
                    return;
                }

                // Remplir le select
                elements.cameraSelect.innerHTML = cameras.map((cam, i) => {
                    const label = cam.label || `Caméra ${i + 1}`;
                    const isBack = label.toLowerCase().includes('back') ||
                        label.toLowerCase().includes('rear') ||
                        label.toLowerCase().includes('environment');
                    return `<option value="${cam.deviceId}">${label}${isBack ? ' (Arrière)' : ''}</option>`;
                }).join('');

                // Sélectionner caméra arrière par défaut
                const backCamera = cameras.find(cam => {
                    const label = cam.label.toLowerCase();
                    return label.includes('back') || label.includes('rear') || label.includes('environment');
                });

                if (backCamera) {
                    currentCameraId = backCamera.deviceId;
                    elements.cameraSelect.value = backCamera.deviceId;
                } else {
                    currentCameraId = cameras[0].deviceId;
                }

            } catch (err) {
                debug('camera', 'Erreur', 'error');
                debug('permission', err.name, 'error');

                let message = 'Erreur caméra';
                if (err.name === 'NotAllowedError') message = 'Permission refusée';
                if (err.name === 'NotFoundError') message = 'Caméra non trouvée';

                showToast(message, 'error');
            }
        }

        // ============================================
        // DÉMARRER LE SCAN
        // ============================================
        async function startScanning() {
            if (isScanning) return;

            try {
                // Afficher la section scanner
                elements.startSection.classList.add('hidden');
                elements.scannerSection.classList.add('active');

                debug('stream', 'Démarrage...', 'pending');

                // Configurer la caméra
                let configCamera;
                if (currentCameraId) {
                    configCamera = {
                        deviceId: {
                            exact: currentCameraId
                        }
                    };
                } else {
                    configCamera = {
                        facingMode: 'environment'
                    };
                }

                // Configuration du scanner
                const config = {
                    fps: 10,
                    qrbox: {
                        width: 260,
                        height: 140
                    },
                    aspectRatio: 1.0,
                    disableFlip: false
                };

                console.log('[SCANNER] Démarrage avec config:', configCamera);

                // Créer instance html5-qrcode
                if (!html5QrCode) {
                    html5QrCode = new Html5Qrcode('qr-reader');
                }

                // Démarrer
                await html5QrCode.start(
                    configCamera,
                    config,
                    // Succès - Code détecté
                    (decodedText, result) => {
                        console.log('[SCANNER] Code détecté:', decodedText);

                        if (scanCooldown || decodedText === lastScannedCode) return;

                        lastScannedCode = decodedText;
                        scanCooldown = true;

                        playBeep();
                        vibrate();

                        // Chercher le produit
                        searchProduct(decodedText);

                        setTimeout(() => {
                            scanCooldown = false;
                        }, 2000);
                    },
                    // Erreur - Normal quand pas de code visible
                    (errorMessage) => {
                        // Ne rien faire - c'est normal
                    }
                );

                isScanning = true;
                startTimer();
                debug('stream', 'Actif ✓', 'ok');

            } catch (err) {
                console.error('[SCANNER] Erreur:', err);
                debug('stream', err.message, 'error');
                showToast('Erreur: ' + err.message, 'error');

                // Revenir à la section start
                elements.scannerSection.classList.remove('active');
                elements.startSection.classList.remove('hidden');
            }
        }

        // ============================================
        // ARRÊTER LE SCAN
        // ============================================
        async function stopScanning() {
            if (!isScanning) return;

            try {
                if (html5QrCode) {
                    await html5QrCode.stop();
                }
            } catch (err) {
                console.log('[SCANNER] Erreur arrêt:', err);
            }

            isScanning = false;
            stopTimer();
            debug('stream', 'Arrêté', 'pending');

            elements.scannerSection.classList.remove('active');
            elements.startSection.classList.remove('hidden');
        }

        // ============================================
        // RECHERCHER PRODUIT
        // ============================================
        async function searchProduct(barcode) {
            try {
                console.log('[SCANNER] Recherche:', barcode);

                const response = await fetch(`${APP_URL}/api/produit?code_barres=${encodeURIComponent(barcode)}`);
                const data = await response.json();

                if (data && data.id) {
                    console.log('[SCANNER] Produit trouvé:', data.nom);

                    showToast(`${data.nom} ajouté!`, 'success');

                    // Afficher le produit
                    elements.lastProduct.classList.add('show');
                    elements.productName.textContent = data.nom;
                    elements.productBarcode.textContent = data.code_barres;
                    elements.productPrice.textContent = parseFloat(data.prix).toFixed(2) + ' Fc';

                    // Ajouter au panier (localStorage)
                    updateCart(data);

                } else {
                    console.log('[SCANNER] Produit non trouvé');
                    showToast('Produit introuvable', 'error');

                    setTimeout(() => {
                        lastScannedCode = null;
                    }, 3000);
                }

            } catch (err) {
                console.error('[SCANNER] Erreur recherche:', err);
                showToast('Erreur de connexion', 'error');
            }
        }

        // ============================================
        // MISE À JOUR PANIER
        // ============================================
        function updateCart(product) {
            let cart = JSON.parse(localStorage.getItem('pos_cart') || '[]');

            const existing = cart.find(item => item.produit_id === product.id);

            if (existing) {
                existing.quantite++;
            } else {
                cart.push({
                    produit_id: product.id,
                    nom: product.nom,
                    prix: parseFloat(product.prix),
                    quantite: 1,
                    maxStock: product.stock
                });
            }

            localStorage.setItem('pos_cart', JSON.stringify(cart));

            // Mettre à jour badge
            const total = cart.reduce((sum, item) => sum + item.quantite, 0);
            const badge = parent.document.getElementById('cart-badge');
            if (badge) badge.textContent = total;

            console.log('[SCANNER] Panier:', cart.length, 'articles');
        }

        // ============================================
        // CHANGER DE CAMÉRA
        // ============================================
        async function switchCamera() {
            if (cameras.length <= 1) {
                showToast('Une seule caméra disponible', 'warning');
                return;
            }

            const currentIndex = cameras.findIndex(c => c.deviceId === currentCameraId);
            const nextIndex = (currentIndex + 1) % cameras.length;

            currentCameraId = cameras[nextIndex].deviceId;
            elements.cameraSelect.value = currentCameraId;

            // Redémarrer si déjà en scan
            if (isScanning) {
                await stopScanning();
                await startScanning();
            }
        }

        // ============================================
        // ÉVÉNEMENTS
        // ============================================
        document.addEventListener('DOMContentLoaded', () => {
            // Vérifier HTTPS
            checkHTTPS();

            // Charger les caméras
            loadCameras();

            // Bouton commencer
            elements.btnStart.addEventListener('click', startScanning);

            // Bouton arrêter
            elements.btnStop.addEventListener('click', stopScanning);

            // Bouton changer
            elements.btnSwitch.addEventListener('click', switchCamera);

            // Select caméra
            elements.cameraSelect.addEventListener('change', (e) => {
                currentCameraId = e.target.value;
                if (isScanning) {
                    stopScanning().then(startScanning);
                }
            });

            console.log('[SCANNER] Initialisé');
        });
    </script>
</body>

</html>