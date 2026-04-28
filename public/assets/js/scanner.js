/**
 * Scanner de code-barres - JavaScript
 * Gère la lecture des codes-barres via la caméra et l'ajout au panier
 */

class BarcodeScanner {
    constructor() {
        this.videoElement = null;
        this.canvasElement = null;
        this.canvas = null;
        this.context = null;
        this.isScanning = false;
        this.scanStartTime = null;
        this.timerInterval = null;
        this.currentStream = null;
        this.lastScannedCode = null;
        this.scanCooldown = false;
        this.scanInterval = null;
        this.currentCameraId = null;
        this.facingMode = 'environment';
        this.barcodeDetector = null;

        // DOM Elements
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
            scannedPrice: document.getElementById('scanned-price')
        };

        this.init();
    }

    init() {
        // Event listeners
        this.elements.startBtn.addEventListener('click', () => this.startScanning());
        this.elements.stopBtn.addEventListener('click', () => this.stopScanning());
        this.elements.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        this.elements.flashToggle.addEventListener('click', () => this.toggleFlash());
        this.elements.cameraSelect.addEventListener('change', (e) => this.selectCamera(e.target.value));

        // Check for BarcodeDetector API
        this.initBarcodeDetector();

        // Load available cameras
        this.loadCameras();
    }

    async initBarcodeDetector() {
        // Check if BarcodeDetector API is available (Chrome 83+)
        if ('BarcodeDetector' in window) {
            try {
                const formats = await BarcodeDetector.getSupportedFormats();
                console.log('BarcodeDetector formats supported:', formats);
                this.barcodeDetector = new BarcodeDetector({
                    formats: formats
                });
            } catch (e) {
                console.log('BarcodeDetector non disponible:', e);
                this.barcodeDetector = null;
            }
        } else {
            console.log('BarcodeDetector API non disponible');
            this.barcodeDetector = null;
        }
    }

    async loadCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(d => d.kind === 'videoinput');

            if (videoDevices && videoDevices.length > 0) {
                this.cameras = videoDevices;
                this.populateCameraSelect(videoDevices);

                // Auto-select back camera if available
                const backCamera = videoDevices.find(d =>
                    d.label.toLowerCase().includes('back') ||
                    d.label.toLowerCase().includes('arrière') ||
                    d.label.toLowerCase().includes('rear') ||
                    d.label.toLowerCase().includes('environment')
                );
                if (backCamera) {
                    this.currentCameraId = backCamera.deviceId;
                } else if (videoDevices.length > 0) {
                    this.currentCameraId = videoDevices[0].deviceId;
                }
            }
        } catch (err) {
            console.error('Erreur chargement caméras:', err);
            this.showCameraError();
        }
    }

    populateCameraSelect(cameras) {
        this.elements.cameraSelect.innerHTML = cameras.map((cam, index) =>
            `<option value="${cam.deviceId}">${cam.label || 'Caméra ' + (index + 1)}</option>`
        ).join('');

        if (cameras.length > 1) {
            this.elements.cameraSelectContainer.classList.remove('hidden');
        }
    }

    showCameraError() {
        this.elements.region.innerHTML = `
            <div class="camera-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
                <h3>Caméra non disponible</h3>
                <p>Veuillez autoriser l'accès à la caméra pour scanner les codes-barres</p>
            </div>
        `;
    }

    async startScanning() {
        if (this.isScanning) return;

        try {
            console.log('Démarrage du scanner...');

            // Arrêter le stream existant si nécessaire
            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
            }

            // Utiliser html5-qrcode
            if (!this.html5QrCode) {
                this.html5QrCode = new Html5Qrcode('scanner-region');
            }

            // Configurer la caméra - html5-qrcode attend soit un ID string, soit un objet avec UNE seule clé
            let cameraConfig;
            if (this.currentCameraId) {
                cameraConfig = this.currentCameraId;
            } else {
                cameraConfig = { facingMode: this.facingMode };
            }

            const config = {
                fps: 10,
                qrbox: { width: 300, height: 200 },
                aspectRatio: 1.0,
                disableFlip: false
            };

            console.log('Démarrage avec html5-qrcode, camera:', cameraConfig);

            await this.html5QrCode.start(
                cameraConfig,
                config,
                (decodedText, result) => {
                    console.log('✅ CODE-BARRES DÉTECTÉ:', decodedText);
                    this.onBarcodeDetected(decodedText);
                },
                (errorMessage) => {
                    // Erreur normale quand pas de code visible
                }
            );

            this.isScanning = true;
            this.scanStartTime = Date.now();
            this.startTimer();

            // Update UI
            this.elements.intro.classList.add('hidden');
            this.elements.view.classList.remove('hidden');

        } catch (err) {
            console.error('Erreur démarrage scan:', err);
            this.showNotification('Erreur: ' + err.message, 'error');
            this.showCameraError();
        }
    }

    startBarcodeDetection() {
        // Scanner les codes-barres toutes les 200ms
        this.scanInterval = setInterval(() => {
            this.detectBarcode();
        }, 200);
    }

    async detectBarcode() {
        if (!this.isScanning || !this.videoElement || this.videoElement.readyState < 2) return;

        try {
            // Configurer le canvas pour la taille de la vidéo
            const video = this.videoElement;
            if (video.videoWidth === 0 || video.videoHeight === 0) return;

            this.canvasElement.width = video.videoWidth;
            this.canvasElement.height = video.videoHeight;

            // Dessiner l'image de la vidéo sur le canvas
            this.context.drawImage(video, 0, 0, this.canvasElement.width, this.canvasElement.height);

            // Si BarcodeDetector est disponible, l'utiliser
            if (this.barcodeDetector) {
                const barcodes = await this.barcodeDetector.detect(this.canvasElement);
                if (barcodes.length > 0) {
                    const code = barcodes[0].rawValue;
                    this.onBarcodeDetected(code);
                }
            } else {
                // Sinon, utiliser ZXing pour la détection
                this.detectBarcodeWithZXing();
            }
        } catch (err) {
            // Ignorer silencieusement les erreurs de détection
        }
    }

    detectBarcodeWithZXing() {
        // Cette fonction utilise ZXing-js pour décoder depuis le canvas
        if (!this.zxingReader) {
            if (typeof ZXing === 'undefined') {
                // Charger ZXing dynamiquement
                this.loadZXing().then(() => {
                    if (this.zxingReader) {
                        this.decodeWithZXing();
                    }
                });
                return;
            }
            // Créer le reader une seule fois
            this.zxingReader = new ZXing.BrowserMultiFormatReader();
        }

        this.decodeWithZXing();
    }

    decodeWithZXing() {
        if (!this.zxingReader) return;

        try {
            // Lire depuis le canvas - méthode simple
            const result = this.zxingReader.decodeFromCanvas(this.canvasElement);
            if (result) {
                console.log('✅ CODE-BARRES DÉTECTÉ:', result.text);
                this.onBarcodeDetected(result.text);
            }
        } catch (err) {
            // Pas de code-barres trouvé - c'est normal, on ne fait rien
        }
    }

    loadZXing() {
        return new Promise((resolve) => {
            if (document.querySelector('script[src*="zxing"]')) {
                if (typeof ZXing !== 'undefined') {
                    this.zxingReader = new ZXing.BrowserMultiFormatReader();
                }
                resolve(true);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://unpkg.com/@zxing/library@0.19.1/umd/index.min.js';
            script.onload = () => {
                console.log('ZXing chargé');
                this.zxingReader = new ZXing.BrowserMultiFormatReader();
                resolve(true);
            };
            script.onerror = () => {
                console.log('Erreur chargement ZXing');
                resolve(false);
            };
            document.head.appendChild(script);
        });
    }

    async stopScanning() {
        if (!this.isScanning) return;

        try {
            // Arrêter l'intervalle de scan
            if (this.scanInterval) {
                clearInterval(this.scanInterval);
                this.scanInterval = null;
            }

            // Arrêter html5-qrcode
            if (this.html5QrCode) {
                await this.html5QrCode.stop();
            }

            // Arrêter le flux vidéo
            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
                this.currentStream = null;
            }

            this.isScanning = false;
            this.stopTimer();

            // Update UI
            this.elements.view.classList.add('hidden');
            this.elements.intro.classList.remove('hidden');

        } catch (err) {
            console.error('Erreur arrêt scan:', err);
        }
    }

    onBarcodeDetected(code) {
        // Prevent duplicate scans
        if (this.scanCooldown || code === this.lastScannedCode) {
            return;
        }

        console.log('Code-barres détecté:', code);
        this.lastScannedCode = code;
        this.scanCooldown = true;

        // Play beep sound
        this.playBeep();

        // Show processing status
        this.showStatus('Scan en cours...', 'processing');

        // Search product in database
        this.searchProduct(code);

        // Reset cooldown after 2 seconds
        setTimeout(() => {
            this.scanCooldown = false;
        }, 2000);
    }

    async searchProduct(barcode) {
        try {
            const response = await fetch(`${APP_URL}/api/produit?code_barres=${encodeURIComponent(barcode)}`);
            const data = await response.json();

            if (data && data.id) {
                // Product found
                this.onProductFound(data);
            } else {
                // Product not found
                this.onProductNotFound(barcode);
            }
        } catch (err) {
            console.error('Erreur recherche produit:', err);
            this.showStatus('Erreur de connexion', 'error');
            this.showNotification('Erreur de connexion au serveur', 'error');
        }
    }

    onProductFound(product) {
        // Show success status
        this.showStatus('Produit trouvé !', 'success');
        this.showNotification(`${product.nom} ajouté au panier`, 'success');

        // Update last scanned product display
        this.elements.scannedName.textContent = product.nom;
        this.elements.scannedBarcode.textContent = product.code_barres;
        this.elements.scannedPrice.textContent = this.formatCurrency(product.prix);
        this.elements.lastScanned.classList.remove('hidden');

        // Send to main app cart (via localStorage or API)
        this.addToCart(product);

        // Reset last scanned code to allow rescan
        setTimeout(() => {
            this.lastScannedCode = null;
        }, 1000);
    }

    onProductNotFound(barcode) {
        this.showStatus('Produit introuvable', 'error');
        this.showNotification(`Code-barres ${barcode} non trouvé dans la base`, 'error');

        // Reset for retry
        setTimeout(() => {
            this.lastScannedCode = null;
        }, 3000);
    }

    async addToCart(product) {
        try {
            // Send to API to add to cart
            const response = await fetch(`${APP_URL}/api/cart/add`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    produit_id: product.id,
                    quantite: 1
                })
            });

            // Also update localStorage for the main app
            this.updateLocalCart(product);

        } catch (err) {
            console.error('Erreur ajout panier:', err);
        }
    }

    updateLocalCart(product) {
        // Get existing cart from localStorage
        let cart = JSON.parse(localStorage.getItem('pos_cart') || '[]');

        // Find existing item
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

        // Update cart badge if visible
        const badge = parent.document.getElementById('cart-badge');
        if (badge) {
            const totalItems = cart.reduce((sum, item) => sum + item.quantite, 0);
            badge.textContent = totalItems;
        }
    }

    showStatus(message, type) {
        const statusEl = this.elements.status;
        statusEl.className = `scanner-status ${type} show`;
        statusEl.querySelector('.status-text').textContent = message;

        // Set icon based on type
        const iconEl = statusEl.querySelector('.status-icon');
        if (type === 'success') {
            iconEl.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
        } else if (type === 'error') {
            iconEl.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
        } else if (type === 'processing') {
            iconEl.innerHTML = `<div class="spinner" style="width:20px;height:20px;border-width:2px;"></div>`;
        }

        // Auto-hide after 3 seconds
        setTimeout(() => {
            statusEl.classList.remove('show');
        }, 3000);
    }

    showNotification(message, type = 'success') {
        const notif = this.elements.notification;
        notif.className = `notification ${type}`;
        notif.querySelector('.notification-message').textContent = message;

        // Show notification
        setTimeout(() => {
            notif.classList.add('show');
        }, 10);

        // Hide after 3 seconds
        setTimeout(() => {
            notif.classList.remove('show');
        }, 3000);
    }

    playBeep() {
        // Create a simple beep sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 1000;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (err) {
            console.warn('Audio non disponible:', err);
        }
    }

    startTimer() {
        this.timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.scanStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            this.elements.timer.textContent = `${minutes}:${seconds}`;
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        this.elements.timer.textContent = '00:00';
    }

    async switchCamera() {
        if (!this.cameras || this.cameras.length <= 1) return;

        const currentIndex = this.cameras.findIndex(c => c.deviceId === this.currentCameraId);
        const nextIndex = (currentIndex + 1) % this.cameras.length;

        await this.stopScanning();
        this.currentCameraId = this.cameras[nextIndex].deviceId;
        this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
        this.elements.cameraSelect.value = this.currentCameraId;
        await this.startScanning();
    }

    async selectCamera(cameraId) {
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
                const currentTorch = track.getSettings().torch;
                await track.applyConstraints({
                    advanced: [{ torch: !currentTorch }]
                });
                this.elements.flashToggle.classList.toggle('active');
            }
        } catch (err) {
            console.warn('Flash non disponible:', err);
        }
    }

    formatCurrency(amount) {
        return parseFloat(amount).toFixed(2) + ' Fc';
    }
}

// Initialize scanner when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.barcodeScanner = new BarcodeScanner();
});
