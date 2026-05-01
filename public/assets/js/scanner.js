/**
 * Scanner de code-barres - Refactorisé pour fonctionner sur tous les navigateurs
 * Caméra arrière par défaut sur mobile, détection fiable des codes-barres
 */

class BarcodeScanner {
    constructor() {
        this.html5QrCode = null;
        this.isScanning = false;
        this.scanStartTime = null;
        this.currentStream = null;
        this.lastScannedCode = null;
        this.scanCooldown = false;
        this.currentCameraId = null;
        this.cameras = [];
        this.debugMode = true;

        // Configuration optimale pour tous les navigateurs
        this.defaultConstraints = {
            video: {
                facingMode: { ideal: "environment" },
                width: { ideal: 1280 },
                height: { ideal: 720 },
                aspectRatio: { ideal: 16 / 9 }
            },
            audio: false
        };

        // DOM Elements
        this.elements = {};

        this.init();
    }

    init() {
        // Attendre que le DOM soit prêt
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        console.log('[SCANNER] Initialisation du scanner...');

        // Récupérer tous les éléments DOM
        this.elements = {
            intro: document.getElementById('scanner-intro'),
            view: document.getElementById('scanner-view'),
            region: document.getElementById('scanner-region'),
            status: document.getElementById('scanner-status'),
            lastScanned: document.getElementById('last-scanned'),
            notification: document.getElementById('notification'),
            timer: document.getElementById('scan-timer'),
            cameraSelect: document.getElementById('camera-select'),
            cameraSelectContainer: document.getElementById('camera-select-container'),
            startBtn: document.getElementById('start-scan-btn'),
            stopBtn: document.getElementById('stop-scan-btn'),
            switchCameraBtn: document.getElementById('switch-camera-btn'),
            flashToggle: document.getElementById('flash-toggle'),
            scannedName: document.getElementById('scanned-name'),
            scannedBarcode: document.getElementById('scanned-barcode'),
            scannedPrice: document.getElementById('scanned-price'),
            loader: document.getElementById('camera-loader')
        };

        // Event listeners
        if (this.elements.startBtn) {
            this.elements.startBtn.addEventListener('click', () => this.startScanning());
        }
        if (this.elements.stopBtn) {
            this.elements.stopBtn.addEventListener('click', () => this.stopScanning());
        }
        if (this.elements.switchCameraBtn) {
            this.elements.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        }
        if (this.elements.flashToggle) {
            this.elements.flashToggle.addEventListener('click', () => this.toggleFlash());
        }
        if (this.elements.cameraSelect) {
            this.elements.cameraSelect.addEventListener('change', (e) => this.selectCamera(e.target.value));
        }

        // Vérifier HTTPS
        this.checkHTTPS();

        // Charger les caméras disponibles
        this.loadCameras();

        // Initialiser html5-qrcode
        this.initHtml5QrCode();

        console.log('[SCANNER] Scanner initialisé avec succès');
    }

    checkHTTPS() {
        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
            console.warn('[SCANNER] ATTENTION: HTTPS requis pour accéder à la caméra');
            this.showNotification('⚠️ HTTPS requis pour la caméra. Veuillez utiliser https://', 'warning');
        }
    }

    async initHtml5QrCode() {
        // Vérifier si la bibliothèque est chargée
        if (typeof Html5Qrcode === 'undefined') {
            console.error('[SCANNER] Html5Qrcode non chargé!');
            this.showNotification('Erreur: Bibliothèque de scan non chargée', 'error');
            return false;
        }

        try {
            // Créer l'instance html5-qrcode sur l'élément 'scanner-region'
            this.html5QrCode = new Html5Qrcode('scanner-region');
            console.log('[SCANNER] Html5Qrcode initialisé avec succès');
            return true;
        } catch (err) {
            console.error('[SCANNER] Erreur initialisation Html5Qrcode:', err);
            return false;
        }
    }

    async loadCameras() {
        try {
            console.log('[SCANNER] Recherche des caméras...');

            // Demander les permissions d'abord
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            stream.getTracks().forEach(track => track.stop());
            console.log('[SCANNER] Permissions caméra accordées');

            // Lister les caméras
            const devices = await navigator.mediaDevices.enumerateDevices();
            this.cameras = devices.filter(d => d.kind === 'videoinput');

            console.log(`[SCANNER] ${this.cameras.length} caméra(s) trouvée(s)`);

            if (this.cameras.length > 0) {
                this.populateCameraSelect();
            }

            return true;
        } catch (err) {
            console.error('[SCANNER] Erreur chargement caméras:', err);
            this.handleCameraError(err);
            return false;
        }
    }

    handleCameraError(err) {
        let message = 'Erreur caméra';
        let details = err.message || '';

        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            message = 'Permission refusée';
            details = 'Veuillez autoriser l\'accès à la caméra dans les paramètres du navigateur';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            message = 'Caméra non trouvée';
            details = 'Aucune caméra détectée sur cet appareil';
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            message = 'Caméra déjà utilisée';
            details = 'La caméra est peut-être utilisée par une autre application';
        } else if (err.name === 'OverconstrainedError') {
            message = 'Caméra non compatible';
            details = 'Les contraintes demandées ne sont pas supportées par cette caméra';
        }

        console.error('[SCANNER] Erreur détaillée:', err.name, details);

        if (this.elements.region) {
            this.elements.region.innerHTML = `
                <div class="camera-error" style="text-align:center;padding:30px;color:#fff;">
                    <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#ff4444" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                    <h3 style="margin:15px 0 10px;color:#ff4444;">${message}</h3>
                    <p style="color:#ccc;font-size:14px;">${details}</p>
                    <button onclick="location.reload()" style="
                        margin-top:20px;padding:12px 24px;background:#0B5E88;color:white;
                        border:none;border-radius:8px;cursor:pointer;font-size:16px;">
                        Réessayer
                    </button>
                </div>
            `;
        }
    }

    populateCameraSelect() {
        if (!this.elements.cameraSelect || this.cameras.length === 0) return;

        this.elements.cameraSelect.innerHTML = this.cameras.map((cam, index) => {
            const label = cam.label || `Caméra ${index + 1}`;
            const isBack = label.toLowerCase().includes('back') ||
                label.toLowerCase().includes('rear') ||
                label.toLowerCase().includes('environment');
            const displayLabel = isBack ? `${label} (Recommandée)` : label;

            return `<option value="${cam.deviceId}" ${index === 0 ? 'selected' : ''}>${displayLabel}</option>`;
        }).join('');

        // Sélectionner automatiquement la caméra arrière
        const backCamera = this.cameras.find(cam => {
            const label = cam.label.toLowerCase();
            return label.includes('back') || label.includes('rear') || label.includes('environment');
        });

        if (backCamera) {
            this.currentCameraId = backCamera.deviceId;
            this.elements.cameraSelect.value = backCamera.deviceId;
        } else if (this.cameras.length > 0) {
            this.currentCameraId = this.cameras[0].deviceId;
        }

        // Afficher le sélecteur seulement si plusieurs caméras
        if (this.elements.cameraSelectContainer && this.cameras.length > 1) {
            this.elements.cameraSelectContainer.classList.remove('hidden');
        }

        console.log('[SCANNER] Caméras listées:', this.cameras.length);
    }

    async startScanning() {
        if (this.isScanning) {
            console.log('[SCANNER] Scan déjà en cours');
            return;
        }

        console.log('[SCANNER] Démarrage du scan...');

        try {
            // Arrêter le stream existant
            await this.stopStream();

            // Afficher le loader
            this.showLoader(true);

            // Vérifier la bibliothèque
            if (!this.html5QrCode) {
                const initialized = await this.initHtml5QrCode();
                if (!initialized) {
                    throw new Error('Impossible d\'initialiser le scanner');
                }
            }

            // Configuration de la caméra - méthode universelle
            let cameraConfig;

            if (this.currentCameraId) {
                // Utiliser l'ID de la caméra sélectionnée
                cameraConfig = {
                    deviceId: {
                        exact: this.currentCameraId
                    }
                };
                console.log('[SCANNER] Utilisation caméra ID:', this.currentCameraId);
            } else {
                // Fallback vers facingMode
                cameraConfig = {
                    facingMode: "environment"
                };
                console.log('[SCANNER] Utilisation facingMode: environment');
            }

            // Configuration du scanner - optimisée pour codes-barres 1D
            const config = {
                fps: 10,                    // Images par seconde
                qrbox: {
                    width: 280,
                    height: 150
                },
                aspectRatio: 1.777778,       // 16:9
                disableFlip: false,
                // Formats de codes-barres supportés
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.ITF,
                    Html5QrcodeSupportedFormats.CODABAR,
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.DATA_MATRIX
                ]
            };

            console.log('[SCANNER] Configuration:', JSON.stringify(config, null, 2));

            // Démarrer le scanner avec callbacks
            await this.html5QrCode.start(
                cameraConfig,
                config,
                (decodedText, result) => {
                    console.log('[SCANNER] ✅ Code détecté:', decodedText);
                    this.onBarcodeDetected(decodedText);
                },
                (errorMessage) => {
                    // Errors are normal when no barcode is visible - don't log them
                    // Only log errors that are not "No MultiFormat Readers" errors
                    if (!errorMessage.includes('No MultiFormat') && !errorMessage.includes('NotFoundException')) {
                        // Optional: uncomment for debugging
                        // console.log('[SCANNER] Scan en cours...', errorMessage);
                    }
                }
            ).then(() => {
                console.log('[SCANNER] ✅ Scanner démarré avec succès!');
                this.isScanning = true;
                this.scanStartTime = Date.now();
                this.startTimer();
                this.showLoader(false);
                this.showScannerView();
            }).catch(err => {
                console.error('[SCANNER] Erreur lors du démarrage:', err);
                throw err;
            });

        } catch (err) {
            console.error('[SCANNER] Erreur fatale:', err);
            this.showLoader(false);
            this.handleCameraError(err);
            this.showNotification('Erreur: ' + err.message, 'error');
        }
    }

    async stopStream() {
        // Arrêter html5-qrcode proprement
        if (this.html5QrCode && this.isScanning) {
            try {
                await this.html5QrCode.stop();
                console.log('[SCANNER] html5-qrcode arrêté');
            } catch (err) {
                console.warn('[SCANNER] Erreur arrêt html5-qrcode:', err);
            }
        }

        // Arrêter tous les tracks du stream
        if (this.currentStream) {
            this.currentStream.getTracks().forEach(track => {
                track.stop();
                console.log('[SCANNER] Track vidéo arrêté');
            });
            this.currentStream = null;
        }

        this.isScanning = false;
    }

    async stopScanning() {
        console.log('[SCANNER] Arrêt du scan...');

        await this.stopStream();
        this.stopTimer();
        this.showIntroView();

        console.log('[SCANNER] Scan arrêté');
    }

    async switchCamera() {
        if (this.cameras.length <= 1) {
            console.log('[SCANNER] Une seule caméra disponible');
            return;
        }

        console.log('[SCANNER] Changement de caméra...');

        const currentIndex = this.cameras.findIndex(c => c.deviceId === this.currentCameraId);
        const nextIndex = (currentIndex + 1) % this.cameras.length;
        const nextCamera = this.cameras[nextIndex];

        this.currentCameraId = nextCamera.deviceId;

        // Redémarrer avec la nouvelle caméra
        await this.stopScanning();
        await this.startScanning();
    }

    async selectCamera(cameraId) {
        if (cameraId === this.currentCameraId) return;

        console.log('[SCANNER] Sélection caméra:', cameraId);
        this.currentCameraId = cameraId;

        if (this.isScanning) {
            await this.stopScanning();
            await this.startScanning();
        }
    }

    async toggleFlash() {
        if (!this.currentStream) return;

        try {
            const track = this.currentStream.getVideoTracks()[0];
            const capabilities = track.getCapabilities();

            if (capabilities.torch) {
                const currentTorch = track.getSettings().torch || false;
                await track.applyConstraints({
                    advanced: [{ torch: !currentTorch }]
                });

                if (this.elements.flashToggle) {
                    this.elements.flashToggle.classList.toggle('active', !currentTorch);
                }

                console.log('[SCANNER] Flash:', !currentTorch ? 'ON' : 'OFF');
            } else {
                console.log('[SCANNER] Flash non disponible sur cette caméra');
                this.showNotification('Flash non disponible', 'info');
            }
        } catch (err) {
            console.warn('[SCANNER] Erreur flash:', err);
        }
    }

    onBarcodeDetected(code) {
        // Éviter les scans duplicates
        if (this.scanCooldown || code === this.lastScannedCode) {
            return;
        }

        console.log('[SCANNER] 📦 Code-barres:', code);
        this.lastScannedCode = code;
        this.scanCooldown = true;

        // Feedback visuel et sonore
        this.playBeep();
        this.vibrate();
        this.showStatus('Produit scanné!', 'success');

        // Rechercher le produit
        this.searchProduct(code);

        // Reset cooldown après 2 secondes
        setTimeout(() => {
            this.scanCooldown = false;
        }, 2000);
    }

    async searchProduct(barcode) {
        try {
            console.log('[SCANNER] Recherche produit:', barcode);

            const response = await fetch(`${APP_URL}/api/produit?code_barres=${encodeURIComponent(barcode)}`);

            if (!response.ok) {
                throw new Error('Erreur réponse API');
            }

            const data = await response.json();
            console.log('[SCANNER] Réponse API:', data);

            if (data && data.id) {
                this.onProductFound(data);
            } else {
                this.onProductNotFound(barcode);
            }
        } catch (err) {
            console.error('[SCANNER] Erreur recherche produit:', err);
            this.showStatus('Erreur connexion', 'error');
            this.showNotification('Erreur de connexion au serveur', 'error');
        }
    }

    onProductFound(product) {
        console.log('[SCANNER] ✅ Produit trouvé:', product.nom);

        this.showStatus(`${product.nom} trouvé!`, 'success');
        this.showNotification(`Redirection vers la caisse...`, 'info');

        // Rediriger vers la caisse avec l'ID du produit
        // La caisse ajoutera automatiquement le produit au panier
        const redirectUrl = `${APP_URL}/caisse?add_product=${product.id}&barcode=${encodeURIComponent(product.code_barres)}`;

        console.log('[SCANNER] Redirection vers:', redirectUrl);

        // Petit délai pour voir la notification, puis redirection
        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 1000);

        // Permettre de rescanner le même produit après 1.5 secondes
        setTimeout(() => {
            this.lastScannedCode = null;
        }, 1500);
    }

    onProductNotFound(barcode) {
        console.warn('[SCANNER] ❌ Produit non trouvé:', barcode);

        this.showStatus('Produit introuvable', 'error');
        this.showNotification(`Code ${barcode} non trouvé dans la base`, 'error');

        setTimeout(() => {
            this.lastScannedCode = null;
        }, 3000);
    }

    async addToCart(product) {
        try {
            // Stocker dans localStorage pour synchronisation avec la caisse
            this.updateLocalCart(product);

            // Envoyer au serveur si l'API existe
            await fetch(`${APP_URL}/api/cart/add`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    produit_id: product.id,
                    quantite: 1
                })
            }).catch(err => {
                console.log('[SCANNER] API panier non disponible, utilisation localStorage only');
            });

        } catch (err) {
            console.error('[SCANNER] Erreur ajout panier:', err);
        }
    }

    updateLocalCart(product) {
        let cart = JSON.parse(localStorage.getItem('pos_cart') || '[]');

        const existingItem = cart.find(item => item.produit_id === product.id);

        if (existingItem) {
            existingItem.quantite++;
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

        // Mettre à jour le badge du panier si visible
        const totalItems = cart.reduce((sum, item) => sum + item.quantite, 0);
        const badge = parent.document.getElementById('cart-badge');
        if (badge) {
            badge.textContent = totalItems;
        }

        console.log('[SCANNER] Panier mis à jour:', cart.length, 'articles');
    }

    playBeep() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
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
        } catch (err) {
            console.warn('[SCANNER] Audio non disponible:', err);
        }
    }

    vibrate() {
        if ('vibrate' in navigator) {
            navigator.vibrate([100, 50, 100]);
        }
    }

    showLoader(show) {
        const loader = document.getElementById('camera-loader');
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }

    showScannerView() {
        if (this.elements.intro) {
            this.elements.intro.classList.add('hidden');
        }
        if (this.elements.view) {
            this.elements.view.classList.remove('hidden');
        }
    }

    showIntroView() {
        if (this.elements.view) {
            this.elements.view.classList.add('hidden');
        }
        if (this.elements.intro) {
            this.elements.intro.classList.remove('hidden');
        }
    }

    showStatus(message, type) {
        const statusEl = this.elements.status;
        if (!statusEl) return;

        statusEl.className = `scanner-status ${type} show`;
        statusEl.querySelector('.status-text').textContent = message;

        const iconEl = statusEl.querySelector('.status-icon');
        if (iconEl) {
            if (type === 'success') {
                iconEl.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
            } else if (type === 'error') {
                iconEl.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f44336" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
            }
        }

        // Masquer après 3 secondes
        setTimeout(() => {
            statusEl.classList.remove('show');
        }, 3000);
    }

    showNotification(message, type) {
        const notif = this.elements.notification;
        if (!notif) return;

        notif.className = `notification ${type} show`;
        notif.querySelector('.notification-message').textContent = message;

        setTimeout(() => {
            notif.classList.remove('show');
        }, 4000);
    }

    startTimer() {
        this.timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.scanStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');

            if (this.elements.timer) {
                this.elements.timer.textContent = `${minutes}:${seconds}`;
            }
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        if (this.elements.timer) {
            this.elements.timer.textContent = '00:00';
        }
    }

    formatCurrency(amount) {
        return parseFloat(amount).toFixed(2) + ' Fc';
    }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    console.log('[SCANNER] Script chargé, création du scanner...');
    window.barcodeScanner = new BarcodeScanner();
});