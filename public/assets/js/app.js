const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);

const formatCurrency = (amount) => amount.toFixed(2) + ' Fc';

// Formater le numéro de téléphone pour masquer les 4 derniers chiffres
const formatPhoneNumber = (phone) => {
    if (!phone || phone.length < 6) return phone;
    const visible = phone.substring(0, 6);
    return visible + '****';
};

// DGI API Configuration - utilise le proxy local pour eviter CORS
const DGI_API_URL = APP_URL + '/api/dgi';

// Fonction pour jouer un beep lors du scan
function playScanBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type = 'sine';
        oscillator.frequency.value = 1000; // 1kHz
        gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);

        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.1);
    } catch (e) {
        console.warn('Audio feedback failed:', e);
    }
}

// Variables globales pour les informations du magasin
let STORE_INFO = {
    name: 'SuperMarche Express',
    address: '123 Rue Mohammed V, Casablanca',
    phone: '+212 522 123 456',
    ice: '001234567890123',
    rccm: '',
    isf: ''
};

// Charger les informations du magasin depuis les paramètres
async function loadStoreInfo() {
    try {
        const res = await fetch(APP_URL + '/api/settings');
        const data = await res.json();
        STORE_INFO = {
            name: data.store_name || STORE_INFO.name,
            address: data.store_address || STORE_INFO.address,
            phone: data.store_phone || STORE_INFO.phone,
            ice: data.store_ice || STORE_INFO.ice,
            rccm: data.store_rccm || '',
            isf: data.store_isf || ''
        };

        console.log('Informations du magasin chargées:', data);

        console.log('Informations du magasin chargées:', data);
    } catch (e) {
        console.warn('Impossible de charger les paramètres du magasin, utilisation des valeurs par défaut');
    }

    // Charger aussi les types de clients pour le select
    loadClientTypes();
}

// Charger les types de clients depuis l'API
async function loadClientTypes() {
    const typeSelect = document.getElementById('client-type');
    if (!typeSelect) return;

    try {
        const res = await fetch(APP_URL + '/api/client/types');
        const types = await res.json();

        if (Array.isArray(types) && types.length > 0) {
            // Garder la première option "Type client"
            const firstOption = typeSelect.querySelector('option[value=""]');
            typeSelect.innerHTML = '';
            if (firstOption) {
                typeSelect.appendChild(firstOption);
            } else {
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = 'Type client';
                typeSelect.appendChild(defaultOpt);
            }

            // Ajouter les types de clients
            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = `${type.code} `;
                typeSelect.appendChild(option);
            });
        }
    } catch (e) {
        console.warn('Impossible de charger les types de clients:', e);
    }
}

const posCart = {
    items: [],
    taxRate: 16,
    currentSaleData: null,
    dgiResponse: null,

    init() {
        // Vérifier si un produit a été scanné et ajouté via l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const addProductId = urlParams.get('add_product');

        // Caisse - Select moderne pour catégories
        if ($('#category-filter')) {
            this.loadProducts().then(() => {
                // Ajouter le produit scanné au panier si présent
                if (addProductId) {
                    console.log('[CAISSE] Produit scanné détecté:', addProductId);
                    this.addToCart(parseInt(addProductId));
                    // Nettoyer l'URL sans recharger
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            });

            if ($('#product-search')) {
                $('#product-search').addEventListener('input', (e) => this.filterProducts(e.target.value));
            }

            // Événement de changement pour le select de catégorie
            $('#category-filter').addEventListener('change', (e) => {
                if ($('#product-search')) {
                    this.filterProducts($('#product-search').value, e.target.value);
                } else {
                    this.filterProducts('', e.target.value);
                }
            });

            // Recherche client par numero avec la touche Entrée
            if ($('#client-number')) {
                $('#client-number').addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchClientByNumero();
                    }
                });
            }
        }

        // Produits page tabs
        if ($('#products-table')) {
            initProductsTabs();
        }
    },

    allProducts: [],
    currentCategory: 'all',

    async loadProducts() {
        try {
            const res = await fetch(APP_URL + '/api/produits');
            const data = await res.json();
            this.allProducts = Array.isArray(data) ? data : (data.products || []);
            this.renderProducts(this.allProducts);
        } catch (e) {
            console.error('Failed fetching products:', e);
            if ($('#products-grid')) $('#products-grid').innerHTML = '<div class="empty-state">Erreur de chargement</div>';
        }
    },

    filterProducts(search, category = null) {
        if (category) this.currentCategory = category;
        const q = search.toLowerCase();
        const filtered = this.allProducts.filter(p => {
            const matchQuery = p.nom.toLowerCase().includes(q) || p.code_barres.includes(q);
            const matchCat = this.currentCategory === 'all' || p.categorie === this.currentCategory;
            return matchQuery && matchCat;
        });
        this.renderProducts(filtered);
    },

    renderProducts(list) {
        const grid = $('#products-grid');
        if (!grid) return;

        let html = list.map(p => {
            const name = p.nom || 'Sans nom';
            const price = parseFloat(p.prix) || 0;
            const barcode = p.code_barres || 'N/A';
            const stock = parseInt(p.stock) || 0;
            const image = p.image || '';

            return `
            <div class="product-card" 
                 onclick="posCart.addToCart(${p.id})">
              <div class="product-image">
                ${image ? `<img src="${image}" alt="${name}" onerror="this.parentElement.innerHTML='<svg width=\\'40\\\' height=\\'50\\\' viewBox=\\'0 0 24 24\\\' fill=\\'none\\\' stroke=\\'currentColor\\\' stroke-width=\\'1.5\\\'><path d=\\'M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z\\\'></path><line x1=\\'3\\\' y1=\\'6\\\' x2=\\'21\\\' y2=\\'6\\\'></line><path d=\\'M16 10a4 4 0 0 1-8 0\\\'></path></svg>'">` : '<svg width="40" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>'}
              </div>
              <div class="product-info">
                <div class="name">${name}</div>
                <div class="price">${formatCurrency(price)}</div>
                <div class="barcode-display">${barcode}</div>
              </div>
            </div>
            `;
        });

        // Compléter jusqu'à 20 cartes avec des placeholders (PC/Tablette uniquement, pas mobile)
        const targetCards = 20;
        const currentCards = html.length;
        const isMobile = window.matchMedia('(max-width: 767px)').matches;
        if (!isMobile && currentCards < targetCards) {
            for (let i = currentCards; i < targetCards; i++) {
                html.push(`<div class="product-card placeholder-card hide-on-mobile" style="opacity: 0.2; cursor: default; min-height: 130px; background: #e8e8e8; border: 2px dashed #bbb; display: flex; align-items: center; justify-content: center; border-radius: 8px;" title="Emplacement réservé"><span style="color: #999; font-size: 11px;">Vide</span></div>`);
            }
        }

        grid.innerHTML = html.join('') || '<div class="empty-state">Aucun produit trouvé</div>';
    },

    addToCart(id) {
        const product = this.allProducts.find(p => p.id == id);
        if (!product) return;

        const existing = this.items.find(i => i.produit_id == id);
        if (existing) {
            existing.quantite++;
        } else {
            this.items.push({
                produit_id: product.id,
                nom: product.nom,
                prix: parseFloat(product.prix),
                quantite: 1,
                maxStock: product.stock,
                tax_rate: parseFloat(product.tax_rate) || 0,  // Taux de taxe du produit
                tax_etiquette: product.tax_etiquette || ''
            });
        }
        this.renderCart();
    },

    updateQty(id, delta) {
        const item = this.items.find(i => i.produit_id == id);
        if (!item) return;
        item.quantite += delta;
        if (item.quantite <= 0) {
            this.items = this.items.filter(i => i.produit_id != id);
        }
        this.renderCart();
    },

    removeFromCart(id) {
        this.items = this.items.filter(i => i.produit_id != id);
        this.renderCart();
    },

    clearCart() {
        this.items = [];
        this.clientNumber = '';
        this.currentClient = null;
        const clientInput = $('#client-number');
        if (clientInput) clientInput.value = '';
        const clientDisplay = $('#client-name-display');
        if (clientDisplay) clientDisplay.remove();
        this.renderCart();
    },

    toggleCalcMode(enabled) {
        this.calcMode = enabled;
        const toggle = $('#calculator-toggle');
        if (enabled) {
            toggle.classList.add('active');
        } else {
            toggle.classList.remove('active');
        }
    },

    updateClientNumber(number) {
        this.clientNumber = number;
        // Recherche automatique du client
        if (number && number.length >= 8) {
            this.lookupClient(number);
        } else {
            // Effacer le nom affiché si le numéro est vide ou trop court
            const clientDisplay = $('#client-name-display');
            if (clientDisplay) clientDisplay.remove();
        }
    },

    async lookupClient(numero) {
        try {
            const res = await fetch(APP_URL + '/api/client/lookup?numero=' + encodeURIComponent(numero));
            const data = await res.json();

            if (data.found && data.client) {
                // Client trouvé - remplir automatiquement les champs
                this.currentClient = data.client;

                // Remplir le champ nom avec le nom du client
                const nomInput = $('#client-nom');
                if (nomInput) {
                    nomInput.value = data.client.nom || '';
                    nomInput.style.borderColor = '#10b981';
                    nomInput.style.background = '#f0fdf4';
                }

                this.displayClientName(data.client);
            } else {
                // Client non trouvé - ouvrir le modal pour créer
                this.currentClient = null;
                openNewClientModal(numero);
            }
        } catch (e) {
            console.error('Erreur lookup client:', e);
        }
    },

    displayClientName(client) {
        // Remplir le champ nom avec le nom du client
        const nomInput = $('#client-nom');
        if (nomInput) {
            nomInput.value = client.nom || '';
            nomInput.style.borderColor = '#10b981';
            nomInput.style.background = '#f0fdf4';
        }

        // Ajouter badge de confirmation
        const existing = $('#client-name-display');
        if (existing) existing.remove();

        const clientNumberSection = $('.client-number-section');
        if (clientNumberSection) {
            const display = document.createElement('div');
            display.id = 'client-name-display';
            display.style.cssText = 'margin-top: 0.5rem; padding: 0.4rem 0.6rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 6px; font-size: 0.75rem; color: white; display: flex; align-items: center; gap: 0.4rem; font-weight: 500;';
            display.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>${client.nom} <span style="opacity: 0.7;">• ${client.code_client}</span></span>
            `;
            clientNumberSection.appendChild(display);
        }
    },

    renderCart() {
        const cartItems = $('#cart-items');
        if (!cartItems) return;
        if (this.items.length === 0) {
            cartItems.innerHTML = `
              <div class="cart-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.3;">
                  <circle cx="9" cy="21" r="1"></circle>
                  <circle cx="20" cy="21" r="1"></circle>
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span>Ajoutez des produits au panier</span>
              </div>
            `;
            $('#show-preview').disabled = true;
        } else {
            cartItems.innerHTML = this.items.map(item => `
              <div class="cart-item">
                <div class="info">
                  <div class="name">${item.nom}</div>
                  <div class="price">${formatCurrency(item.prix)} / unite</div>
                </div>
                <div class="quantity-controls">
                  <button onclick="posCart.updateQty(${item.produit_id}, -1)">-</button>
                  <span>${item.quantite}</span>
                  <button onclick="posCart.updateQty(${item.produit_id}, 1)">+</button>
                </div>
                <div class="item-total">${formatCurrency(item.prix * item.quantite)}</div>
                <button class="remove-item" onclick="posCart.removeFromCart(${item.produit_id})">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </div>
            `).join('');
            $('#show-preview').disabled = false;
        }

        // Les prix sont maintenant HT (sans TVA), calculer la TVA par produit selon son taux
        let subtotalHT = 0;
        let totalTax = 0;

        for (const item of this.items) {
            const itemHT = item.prix * item.quantite;
            const itemTax = itemHT * (item.tax_rate / 100);
            subtotalHT += itemHT;
            totalTax += itemTax;
        }

        const subtotalTTC = subtotalHT;

        $('#subtotal').textContent = formatCurrency(subtotalHT);
        $('#total').textContent = formatCurrency(subtotalTTC);

        this.currentTotals = { sous_total_ht: subtotalHT, tva: totalTax, total: subtotalTTC };
    },

    // Appeler l'API DGI pour valider la facture
    async validateWithDGI() {
        try {
            // Generer le numero de facture
            const invoiceNum = 'FAC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');

            // Obtenir le nom du vendeur
            const sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;

            // Récupérer les infos client pour la DGI
            const clientNom = $('#client-nom')?.value || (this.currentClient?.nom || '');
            const clientTypeText = $('#client-type option:checked')?.textContent || (this.currentClient?.type_description || '');
            const clientTypeInitiales = clientTypeText.split(' - ')[0].trim() || '';
            const clientNif = $('#client-nif')?.value || (this.currentClient?.nif || '');
            const clientNumero = $('#client-number')?.value || (this.clientNumber || '');

            // Récupérer le type de facture et la référence document
            const invoiceType = document.getElementById('invoice-type')?.value || 'FV';
            const invoiceRef = document.getElementById('invoice-ref')?.value || '';

            // Construire le payload DGI
            const dgiPayload = {
                store_name: STORE_INFO.name,
                store_phone: STORE_INFO.phone,
                store_address: STORE_INFO.address,
                store_ice: STORE_INFO.ice,
                store_isf: STORE_INFO.isf,
                store_rccm: STORE_INFO.rccm,
                seller_name: sellerName,
                amount: this.currentTotals.total,
                client_number: clientNumero,
                invoice_number: this.currentInvoiceNum,
                invoice_type: invoiceType,
                invoice_ref: invoiceRef,
                articles: this.items.map(item => ({
                    name: item.nom,
                    quantity: item.quantite,
                    price: item.prix,
                    tax_rate: item.tax_rate || 0,
                    tax_etiquette: item.tax_etiquette || ''
                })),
                client_name: clientNom,
                client_type: clientTypeInitiales,
                client_nif: clientNif
            };

            // Log du payload DGI (CAISSE)
            console.log('[DGI CAISSE] Payload envoyé:', JSON.stringify(dgiPayload, null, 2));

            // Envoyer les donnees en POST a l'API DGI
            const res = await fetch(DGI_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dgiPayload)
            });

            // Vérifier si la réponse estOK
            if (!res.ok) {
                console.warn('[DGI] Réponse non OK:', res.status, res.statusText);
                return { success: false, message: 'Erreur serveur DGI: ' + res.status };
            }

            // Lire le texte de la réponse
            const text = await res.text();

            // Vérifier si le texte est vide
            if (!text || text.trim() === '') {
                console.warn('[DGI] Réponse vide du serveur');
                return { success: false, message: 'Réponse vide du serveur DGI' };
            }

            // Parser le JSON
            try {
                return JSON.parse(text);
            } catch (jsonErr) {
                console.warn('[DGI] Réponse non-JSON:', text.substring(0, 200));
                return { success: false, message: 'Réponse invalide du serveur DGI' };
            }
        } catch (e) {
            console.error('Erreur appel DGI:', e);
            return { success: false, message: 'Erreur de connexion DGI: ' + e.message };
        }
    },

    // Charger la bibliotheque QR Code si necessaire
    loadQRCodeLibrary() {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[src*="qr-code-styling"]')) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    // Generer le code QR avec le contenu DGI
    async generateDGIQRCode(qrCodeContent, containerId) {
        try {
            await this.loadQRCodeLibrary();
            const qrCode = new QRCodeStyling({
                width: 180,
                height: 180,
                type: "svg",
                data: qrCodeContent,
                margin: 10,
                qrOptions: {
                    typeNumber: 0,
                    mode: 'Byte',
                    errorCorrectionLevel: 'M'
                },
                dotsOptions: {
                    color: "#000000",
                    type: "rounded"
                },
                cornersSquareOptions: {
                    color: "#000000",
                    type: "extra-rounded"
                },
                backgroundOptions: {
                    color: "#ffffff"
                }
            });
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = '';
                qrCode.append(container);
            }
        } catch (e) {
            console.error('Erreur generation QR:', e);
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = '<div style="text-align:center;font-size:12px;color:#666;">QR Code DGI</div>';
            }
        }
    },

    // Afficher le récapitulatif de la vente (ticket final avec DGI)
    async showPreview() {
        if (this.items.length === 0) return;

        // Fermer le panier mobile (sidebar) avant d'afficher la validation
        const cart = document.getElementById('caisse-cart');
        const overlay = document.getElementById('cart-sidebar-overlay');
        if (cart) {
            cart.classList.remove('open');
            cart.classList.remove('mobile-open');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
        document.body.style.overflow = '';

        // Si le mode calculatrice est activé, ouvrir le modal de paiement
        if (this.calcMode) {
            this.showPaymentModal();
            return;
        }

        // Récupérer le numéro de facture depuis la base de données
        try {
            const res = await fetch(APP_URL + '/api/vente/next-invoice');
            const data = await res.json();
            if (data.invoice_number) {
                this.currentInvoiceNum = data.invoice_number;
            }
        } catch (e) {
            console.warn('Erreur récupération numéro facture:', e);
        }

        const formattedDate = new Date().toLocaleString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        const invoiceNum = this.currentInvoiceNum || 'FAC-000001';

        // Construire les items du reçu
        let itemsHtml = '';
        for (let i = 0; i < this.items.length; i++) {
            const item = this.items[i];
            const itemHT = item.prix * item.quantite;
            const itemTax = itemHT * (item.tax_rate / 100);
            const itemTTC = itemHT + itemTax;
            const taxLabel = item.tax_etiquette || (item.tax_rate > 0 ? 'TVA ' + item.tax_rate + '%' : 'Exonere');
            itemsHtml += `
                <div class="receipt-item">
                    <span class="item-name">${item.nom}<span class="item-tax-badge">${taxLabel}</span></span>
                    <span class="item-qty">x${item.quantite}</span>
                    <span class="item-price">${itemHT.toFixed(2)}</span>
                </div>
            `;
        }

        // Ajouter les infos RCCM et ISF si disponibles (chacun sur sa propre ligne)
        let storeExtraInfo = '';
        if (STORE_INFO.rccm) {
            storeExtraInfo += `<div>RCCM: ${STORE_INFO.rccm}</div>`;
        }
        if (STORE_INFO.isf) {
            storeExtraInfo += `<div>Numero Impot: ${STORE_INFO.isf}</div>`;
        }


        // Récupérer les infos de l'acheteur depuis les inputs du panier
        const acheteurNom = $('#client-nom')?.value || (this.currentClient?.nom || '');
        const acheteurTypeText = $('#client-type option:checked')?.textContent || (this.currentClient?.type_description || '');
        // Extraire juste les initiales (ex: "PP - Particulier" -> "PP")
        const acheteurTypeInitiales = acheteurTypeText.split(' - ')[0].trim() || '';
        const acheteurNif = $('#client-nif')?.value || (this.currentClient?.nif || '');
        const acheteurNumero = $('#client-number')?.value || (this.clientNumber || '');
        const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;

        // Construire les infos en une seule section sans separarer vendeur/acheteur
        let infoSection = `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 11px; line-height: 1.5;">
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Vendeur:</strong></span><span>${vendeur}</span></div>
                           ${acheteurNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Client:</strong></span><span>${acheteurNom}</span></div>` : ''}
                           ${acheteurNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Num:</strong></span><span>${acheteurNumero}</span></div>` : ''}
                           ${acheteurTypeInitiales ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Type:</strong></span><span>${acheteurTypeInitiales}</span></div>` : ''}
                           ${acheteurNif ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>NIF:</strong></span><span>${acheteurNif}</span></div>` : ''}
                           ${STORE_INFO.isf ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Numero Impot:</strong></span><span>${STORE_INFO.isf}</span></div>` : ''}
                        </div>`;

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div>${STORE_INFO.address}</div>
                        <div>Tel: ${STORE_INFO.phone}</div>
                        <div>ID Nat: ${STORE_INFO.ice}</div>

                        <!--<div>RCCM: ${STORE_INFO.rccm}</div>-->
                        ${storeExtraInfo}

                    </div>
                    ${infoSection}
                </div>

                <div class="receipt-meta">
                    <span>${invoiceNum}</span>
                    <span>${document.getElementById('invoice-type')?.value || 'FV'}</span>
                </div>

                <div class="receipt-items receipt-items-grid">
                    ${itemsHtml}
                </div>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Sous-total HT:</span>
                        <span>${this.currentTotals.sous_total_ht.toFixed(2)} Fc</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div class="barcode">${invoiceNum}</div>
                    <div class="thank-you">Merci de votre visite!</div>
                    <div style="margin-top: 5px; font-size: 9px; font-style: italic;">---Powered By Osat----</div>
                </div>
            </div>
        `;

        $('#preview-modal').classList.add('active');

        // Ajouter le bouton de confirmation après le reçu
        const previewFooter = document.querySelector('#preview-modal .modal-footer');
        if (previewFooter) {
            previewFooter.innerHTML = `
                <button class="btn btn-secondary" onclick="posCart.closePreview()">Fermer</button>
                <button class="btn btn-primary" onclick="posCart.confirmSale()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Valider la facture
                </button>
            `;
        }
    },

    // Afficher le modal de paiement (popup séparé)
    showPaymentModal() {
        if (this.items.length === 0) return;

        // Récupérer le numéro de facture depuis la base de données
        let invoiceNum = 'FAC-000001';
        fetch(APP_URL + '/api/vente/next-invoice')
            .then(res => res.json())
            .then(data => {
                if (data.invoice_number) {
                    this.currentInvoiceNum = data.invoice_number;
                }
            })
            .catch(() => { });

        // Afficher le modal
        const modal = $('#payment-modal');
        const totalFc = this.currentTotals.total;
        const tauxChange = 2300; // Taux fixe: 1 USD = 2300 Fc
        const totalUsd = totalFc / tauxChange;

        // Mettre à jour les totaux affichés
        $('#payment-total').textContent = formatCurrency(totalFc);
        $('#payment-total-usd').textContent = '($' + totalUsd.toFixed(2) + ' USD)';

        // Réinitialiser le champ et le mode
        $('#payment-received').value = '';
        $('#payment-result').style.display = 'none';
        this.currentPaymentMode = 'usd';

        // Reset radio buttons
        const usdRadio = $('input[name="payment-mode"][value="usd"]');
        const fcRadio = $('input[name="payment-mode"][value="fc"]');
        if (usdRadio) usdRadio.checked = true;
        if (fcRadio) fcRadio.checked = false;

        // Mettre à jour le label
        const label = $('#payment-received-label');
        if (label) label.innerHTML = ' Montant reçu (USD)';

        modal.classList.add('active');
        $('#payment-received').focus();
    },

    // Mode de paiement (USD ou FC)
    currentPaymentMode: 'usd',

    togglePaymentMode(mode) {
        this.currentPaymentMode = mode;
        const input = $('#payment-received');
        const label = $('#payment-received-label');

        if (mode === 'usd') {
            label.innerHTML = ' Montant reçu (USD)';
            input.placeholder = 'Entrez en dollars...';
            input.value = '';
        } else {
            label.innerHTML = ' Montant reçu (Fc)';
            input.placeholder = 'Entrez en francs...';
            input.value = '';
        }

        // Cacher le résultat
        $('#payment-result').style.display = 'none';
        input.focus();
    },

    // Calculer la monnaie à rendre dans le modal (supporte USD et FC)
    calculateChange() {
        const totalFc = this.currentTotals.total;
        const inputValue = parseFloat($('#payment-received').value) || 0;

        // Taux de change constant
        const TAUX_CHANGE = 2300; // 1 USD = 2300 Fc

        // Mode de paiement actuel (USD ou FC)
        const paymentMode = $('input[name="payment-mode"]:checked')?.value || 'usd';

        let restFc, restUsd;

        if (paymentMode === 'usd') {
            // Client paie en USD
            const receivedFc = inputValue * TAUX_CHANGE;
            restFc = receivedFc - totalFc;
            restUsd = inputValue - (totalFc / TAUX_CHANGE);
        } else {
            // Client paie en Fc
            restFc = inputValue - totalFc;
            restUsd = restFc / TAUX_CHANGE;
        }

        const resultDiv = $('#payment-result');
        const changeLabel = $('#payment-change-label');
        const changeFcEl = $('#payment-change-fc');
        const changeUsdEl = $('#payment-change-usd');

        if (inputValue <= 0) {
            resultDiv.style.display = 'none';
            this.paymentAmount = 0;
            this.paymentStatus = 'pending';
        } else if (restFc < 0) {
            // Montant insuffisant - Rouge
            resultDiv.style.display = 'block';
            resultDiv.style.background = '#ffebee';
            resultDiv.style.border = '2px solid #f44336';
            changeLabel.textContent = '⚠️ MONTANT INSUFFISANT';
            changeLabel.style.color = '#c62828';
            changeFcEl.textContent = 'Il manque ' + Math.abs(restFc).toFixed(2) + ' Fc';
            changeFcEl.style.color = '#c62828';
            changeUsdEl.textContent = '($' + Math.abs(restUsd).toFixed(2) + ' USD)';
            changeUsdEl.style.color = '#c62828';
            this.paymentAmount = 0;
            this.paymentStatus = 'insufficient';
        } else {
            // Monnaie à rendre - Vert
            resultDiv.style.display = 'block';
            resultDiv.style.background = '#e8f5e9';
            resultDiv.style.border = '2px solid #4caf50';
            changeLabel.textContent = '💵 MONNAIE À RENDRE';
            changeLabel.style.color = '#2e7d32';
            changeFcEl.textContent = formatCurrency(Math.abs(restFc));
            changeFcEl.style.color = '#2e7d32';
            changeUsdEl.textContent = '(' + restUsd.toFixed(2) + ' USD)';
            changeUsdEl.style.color = '#2e7d32';
            this.paymentAmount = inputValue;
            this.paymentStatus = 'ok';
        }
    },

    // Fermer le modal de prévisualisation
    closePreview() {
        $('#preview-modal').classList.remove('active');
    },

    // Confirmer et traiter la vente (appel DGI + sauvegarde)
    async confirmSale() {
        $('#confirm-sale').disabled = true;
        $('#confirm-sale').innerHTML = '<span class="spinner"></span> Traitement...';

        try {
            // Etape 1: Appeler l'API DGI
            const dgiResponse = await this.validateWithDGI();
            this.dgiResponse = dgiResponse;

            if (!dgiResponse.success) {
                alert('Erreur DGI: ' + (dgiResponse.message || 'Impossible de valider la facture'));
                $('#confirm-sale').disabled = false;
                $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Valider la facture';
                return;
            }

            // Etape 2: Sauvegarder la vente
            const saleRes = await fetch(APP_URL + '/api/vente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    articles: this.items,
                    client_id: this.currentClient ? this.currentClient.id : null,
                    sous_total_ht: this.currentTotals.sous_total_ht,
                    tva: this.currentTotals.tva,
                    total: this.currentTotals.total,
                    type_facture: document.getElementById('invoice-type')?.value || 'FV',
                    dgi_data: {
                        dateDGI: dgiResponse.data ? dgiResponse.data.dateDGI : null,
                        qrCode: dgiResponse.data ? dgiResponse.data.qrCode : '',
                        codeDEFDGI: dgiResponse.data ? dgiResponse.data.codeDEFDGI : '',
                        counters: dgiResponse.data ? dgiResponse.data.counters : null,
                        nim: dgiResponse.data ? dgiResponse.data.nim : null,
                        total: dgiResponse.data ? dgiResponse.data.total : null,
                        vtotal: dgiResponse.data ? dgiResponse.data.vtotal : null,
                        isf: dgiResponse.data ? dgiResponse.data.isf : null,
                        comment: dgiResponse.comment || (dgiResponse.data ? dgiResponse.data.comment : null)
                    }
                })
            });
            const saleData = await saleRes.json();

            if (!saleData.success) {
                alert(saleData.error);
                $('#confirm-sale').disabled = false;
                $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Valider la facture';
                return;
            }

            // Fermer le modal de prévisualisation
            this.closePreview();

            // Stocker les donnees de vente
            this.currentSaleData = {
                invoiceNumber: saleData.numero_facture,
                date: new Date().toISOString(),
                total: this.currentTotals.total,
                items: [...this.items]
            };

            // Construire le HTML du recap DGI
            let dgiInfoHtml = '<div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;"><div style="color: #2e7d32; font-weight: bold; font-size: 11px;">--- Elements de securite de la facture normalisee ---</div>';
            if (dgiResponse.data) {
                console.log(dgiResponse)
                dgiInfoHtml += '<div style="font-size: 12px; color: #555; margin-top: 4px;">';
                if (dgiResponse.data.codeDEFDGI) dgiInfoHtml += 'CODE DEF/DGI: ' + dgiResponse.data.codeDEFDGI;
                if (dgiResponse.data.nim) dgiInfoHtml += '<br> DEF NID : ' + dgiResponse.data.nim;
                if (dgiResponse.data.counters) dgiInfoHtml += '<br> DEF Compteurs: ' + dgiResponse.data.counters;
                if (dgiResponse.data.dateDGI) dgiInfoHtml += '<br> DEF Heure : ' + dgiResponse.data.dateDGI + '\n';
                //if (dgiResponse.data.isf) dgiInfoHtml += '<br> ISF : ' + dgiResponse.data.isf;
                else dgiInfoHtml += '<br> ISF : 0';
                dgiInfoHtml += '</div>';
            }
            dgiInfoHtml += '</div>';

            // Contenu du QR code
            const qrContainerId = 'dgi-qrcode-container';
            const qrCodeContent = (dgiResponse.data && dgiResponse.data.qrCode) ? dgiResponse.data.qrCode : saleData.numero_facture;

            const formattedDate = new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

            // Récupérer les infos client pour le reçu
            const acheteurNom = $('#client-nom')?.value || (this.currentClient?.nom || '');
            const acheteurTypeText = $('#client-type option:checked')?.textContent || (this.currentClient?.type_description || '');
            const acheteurTypeInitiales = acheteurTypeText.split(' - ')[0].trim() || '';
            const acheteurNif = $('#client-nif')?.value || (this.currentClient?.nif || '');
            const acheteurNumero = $('#client-number')?.value || (this.clientNumber || '');
            const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;


            // Ajouter les infos RCCM et ISF si disponibles (chacun sur sa propre ligne)
            let storeExtraInfo = '';
            if (STORE_INFO.rccm) {
                storeExtraInfo += `<div>RCCM: ${STORE_INFO.rccm}</div>`;
            }
            if (STORE_INFO.isf) {
                storeExtraInfo += `<div>Numero Impot: ${STORE_INFO.isf}</div>`;
            }


            // Construire les infos en une seule section
            let infoSection = `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 15px; line-height: 1.5;">
                               <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Vendeur:</strong></span><span>${vendeur}</span></div>
                               ${acheteurNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Client:</strong></span><span>${acheteurNom}</span></div>` : ''}
                               ${acheteurNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Num:</strong></span><span>${formatPhoneNumber(acheteurNumero)}</span></div>` : ''}
                               ${acheteurTypeInitiales ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Type:</strong></span><span>${acheteurTypeInitiales}</span></div>` : ''}
                               ${acheteurNif ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>NIF:</strong></span><span>${acheteurNif}</span></div>` : ''}
                               ${STORE_INFO.isf ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>ISF:</strong></span><span>${STORE_INFO.isf}</span></div>` : ''}
                            </div>`;

            // Construire les items du reçu avec les taxes par produit
            let itemsHtml = '';
            for (let i = 0; i < this.items.length; i++) {
                const item = this.items[i];
                const itemHT = item.prix * item.quantite;
                const itemTax = itemHT * (item.tax_rate / 100);
                const itemTTC = itemHT;

                const taxLabel = item.tax_etiquette || (item.tax_rate > 0 ? 'TVA ' + item.tax_rate + '%' : 'Exonere');
                itemsHtml += `
                    <div class="receipt-item">
                        <span class="item-name">${item.nom}<span class="item-tax-badge">${taxLabel}</span></span>
                        <span class="item-qty">x${item.quantite}</span>
                        <span class="item-price">${itemTTC.toFixed(2)}</span>
                    </div>
                `;
            }

            // Afficher le recu complet (identique au preview mais avec QR code)
            $('#receipt-content').innerHTML = `
                <div class="receipt">
                    <div class="receipt-header">
                        <div class="store-name">${STORE_INFO.name}</div>
                        <div class="store-info">
                            <div>${STORE_INFO.address}</div>
                            <div>Tel: ${STORE_INFO.phone}</div>
                            <div>ID Nat: ${STORE_INFO.ice}</div>

                            <!--<div>RCCM: ${STORE_INFO.rccm}</div>-->
                            ${storeExtraInfo}

                        </div>
                        ${infoSection}
                    </div>
                    <div class="receipt-meta">
                        <span>${saleData.numero_facture}</span>
                        <span>${saleData.type_facture || document.getElementById('invoice-type')?.value || 'FV'}</span>
                    </div>

                    <div class="receipt-items receipt-items-grid">
                        ${itemsHtml}
                    </div>

                    <div class="receipt-totals">
                        <div class="receipt-total-row">
                            <span>Sous-total HT:</span>
                            <span>${this.currentTotals.sous_total_ht.toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row">
                            <span>TVA:</span>
                            <span>${dgiResponse.data.vtotal.toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row grand-total">
                            <span>TOTAL TTC:</span>
                            <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                        </div>
                         <div style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: left;">
                            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Commentaire/Remarque :</div>
                            <div>${(dgiResponse.comment || (dgiResponse.data && dgiResponse.data.comment)) || 'Aucun commentaire'}</div>
                         </div>
                    </div>

                    ${dgiInfoHtml}

                    <div class="receipt-footer">
                    
                        <div id="${qrContainerId}" class="qrcode-container"></div>
                        <div class="barcode">${saleData.numero_facture}</div>
                       
                        <div class="thank-you">Merci de votre visite!</div>
                        <div style="margin-top: 5px; font-size: 9px; font-style: italic;">---Powered By Osat---</div>
                    </div>
                </div>
            `;

            // Generer le QR code
            this.generateDGIQRCode(qrCodeContent, qrContainerId);

            // Afficher le modal du ticket
            $('#receipt-modal').classList.add('active');

            // Reinitialiser le bouton
            $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Valider la facture';

            // Vider le panier et rafraichir les produits
            this.clearCart();
            this.loadProducts();

        } catch (e) {
            console.error('Erreur confirmSale:', e);
            alert('Erreur serveur: ' + e.message);
        }

        $('#confirm-sale').disabled = false;
    },

    async saveProduct() {
        const isEdit = !!$('#product-id').value;
        const url = isEdit ? APP_URL + '/api/produit/update' : APP_URL + '/api/produit';
        const formData = new FormData();
        formData.append('id', $('#product-id').value);
        formData.append('code_barres', $('#product-barcode').value);
        formData.append('nom', $('#product-name').value);
        formData.append('category_id', $('#product-category').value);
        formData.append('prix', $('#product-price').value);
        formData.append('stock', $('#product-stock').value);
        formData.append('stock_minimum', $('#product-min-stock').value);
        formData.append('taxe_id', $('#product-tax').value);
        if ($('#product-image').files[0]) {
            formData.append('image', $('#product-image').files[0]);
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                alert(isEdit ? "Produit modifie !" : "Produit ajoute !");
                closeProductModal();
                window.location.reload();
            } else {
                alert(data.error || "Erreur reseau");
            }
        } catch (e) {
            alert('Erreur serveur');
        }
    }
};

function editProduct(product) {
    // Recharger les categories si necessaire pour le select
    if (categoriesCache.length === 0) {
        loadCategories().then(() => {
            setProductForm(product);
        });
    } else {
        setProductForm(product);
    }
}

function setProductForm(product) {
    $('#product-id').value = product.id;
    $('#product-modal-title').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Modifier le produit';
    $('#product-barcode').value = product.code_barres;
    $('#product-name').value = product.nom;
    $('#product-price').value = product.prix;
    $('#product-stock').value = product.stock;
    $('#product-min-stock').value = product.stock_minimum;
    $('#product-image').value = '';

    // Afficher l'image existante si elle existe
    const preview = $('#product-image-preview');
    const fileNameDisplay = $('#product-image-name');
    const clearBtn = $('#clear-image-btn');

    if (product.image && product.image.trim() !== '') {
        preview.innerHTML = '<img src="' + APP_URL + '/' + product.image + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML=\'<div style=\\\'text-align:center; color:var(--muted); padding:1rem;\\\'><svg width=\\\'40\\\' height=\\\'40\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' stroke=\\\'currentColor\\\' stroke-width=\\\'1.5\\\' style=\\\'opacity:0.4;\\\'><rect x=\\\'3\\\' y=\\\'3\\\' width=\\\'18\\\' height=\\\'18\\\' rx=\\\'2\\\' ry=\\\'2\\\'></rect><circle cx=\\\'8.5\\\' cy=\\\'8.5\\\' r=\\\'1.5\\\'></circle><polyline points=\\\'21 15 16 10 5 21\\\'></polyline></svg><p style=\\\'font-size:0.75rem; margin-top:0.5rem;\\\'>Image introuvable</p></div>\'">';
        fileNameDisplay.textContent = 'Image actuelle';
        clearBtn.style.display = 'inline-flex';
    } else {
        preview.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 1rem;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg><p style="font-size: 0.75rem; margin-top: 0.5rem;">Aucune image</p></div>';
        fileNameDisplay.textContent = '';
        clearBtn.style.display = 'none';
    }

    // Selectionner la bonne categorie
    const categorySelect = $('#product-category');
    categorySelect.value = product.category_id;

    // Selectionner le type de taxe
    const taxSelect = $('#product-tax');
    if (taxSelect && product.taxe_id) {
        taxSelect.value = product.taxe_id;
    }

    $('#product-modal').classList.add('active');
}

function deleteProduct(id) {
    if (confirm('Supprimer definitivement ce produit ?')) {
        const formData = new FormData();
        formData.append('id', id);
        fetch(APP_URL + '/api/produit/delete', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Produit supprime !');
                    window.location.reload();
                } else {
                    alert(data.error || 'Erreur suppression');
                }
            })
            .catch(() => alert('Erreur serveur'));
    }
}

// ==================== USER MANAGEMENT ====================

function openAddUserModal() {
    document.getElementById('user-modal-title').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>Ajouter un utilisateur';
    document.getElementById('user-id').value = '';
    document.getElementById('user-username').value = '';
    document.getElementById('user-fullname').value = '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = true;
    document.getElementById('user-role').value = 'vendeur';
    document.getElementById('user-actif').value = '1';
    document.getElementById('password-hint').style.display = 'none';
    document.getElementById('user-modal').style.display = 'flex';
}

function openEditUserModal(id, username, fullname, role, actif) {
    // Get elements
    const modal = document.getElementById('user-modal');
    const roleSelect = document.getElementById('user-role');
    const actifSelect = document.getElementById('user-actif');

    // Set values first while modal is still hidden
    document.getElementById('user-modal-title').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Modifier l\'utilisateur';
    document.getElementById('user-id').value = id;
    document.getElementById('user-username').value = username;
    document.getElementById('user-fullname').value = fullname;
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('password-hint').style.display = 'inline';

    // Set select values using selectedIndex
    if (role === 'admin') {
        roleSelect.selectedIndex = 1; // Admin is second option
    } else {
        roleSelect.selectedIndex = 0; // Vendeur is first option
    }

    if (actif == 1) {
        actifSelect.selectedIndex = 0; // Actif is first option
    } else {
        actifSelect.selectedIndex = 1; // Inactif is second option
    }

    // Debug
    console.log('openEditUserModal - role parameter:', role);
    console.log('openEditUserModal - roleSelect.selectedIndex:', roleSelect.selectedIndex);
    console.log('openEditUserModal - roleSelect.value:', roleSelect.value);

    // Now show modal
    modal.style.display = 'flex';
}

function closeUserModal() {
    document.getElementById('user-modal').style.display = 'none';
}

function saveUser(event) {
    event.preventDefault();
    const formData = new FormData();
    const userId = document.getElementById('user-id').value;
    const username = document.getElementById('user-username').value;
    const fullname = document.getElementById('user-fullname').value;
    const password = document.getElementById('user-password').value;

    // Get role value directly from the select element
    const roleSelect = document.getElementById('user-role1');
    const role = roleSelect ? roleSelect.value : 'vendeur';
    const actif = document.getElementById('user-actif').value;


    const url = userId ? APP_URL + '/api/update/user' : APP_URL + '/api/create/user';

    if (userId) {
        formData.append('id', userId);
        formData.append('nom_utilisateur', username);
        formData.append('nom_complet', fullname);
        formData.append('role', role);
        formData.append('actif', actif);
        if (password) {
            formData.append('mot_de_passe', password);
        }
    } else {
        formData.append('username', username);
        formData.append('fullname', fullname);
        formData.append('password', password);
        formData.append('role', role);
        formData.append('actif', actif);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
        .then(res => {
            console.log('HTTP status:', res.status);
            return res.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                alert(userId ? 'Utilisateur modifie !' : 'Utilisateur cree !');
                closeUserModal();
                window.location.reload();
            } else {
                // Show detailed error
                let errorMsg = 'Erreur: ';
                if (data.error) {
                    errorMsg += data.error;
                } else if (data.success === false) {
                    errorMsg += 'La mise a jour a echoue (success=false)';
                } else {
                    errorMsg += JSON.stringify(data);
                }
                alert(errorMsg);
            }
        })
        .catch((err) => {
            console.error('Fetch error:', err);
            alert('Erreur serveur: ' + err.message);
        });

    return false;
}

function deleteUser(id) {
    if (confirm('Supprimer definitivement cet utilisateur ?')) {
        const formData = new FormData();
        formData.append('id', id);
        fetch(APP_URL + '/api/delete/user', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Utilisateur supprime !');
                    window.location.reload();
                } else {
                    alert(data.error || 'Erreur suppression');
                }
            })
            .catch(() => alert('Erreur serveur'));
    }
}

// ==================== CATEGORY MANAGEMENT ====================

// Load categories from API into select elements
let categoriesCache = [];

async function loadCategories() {
    try {
        const res = await fetch(APP_URL + '/api/categories');
        categoriesCache = await res.json();
        populateCategorySelect('product-category', categoriesCache);
        // Ne pas écraser category-filter car il est déjà rempli côté PHP
    } catch (e) {
        console.error('Erreur chargement categories:', e);
    }
}

function populateCategorySelect(selectId, categories) {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = '<option value="">Sélectionner...</option>';
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.category || cat.nom || cat.name;
        select.appendChild(option);
    });
}

function openCategoryModal() {
    document.getElementById('category-modal-title').textContent = 'Ajouter une catégorie';
    document.getElementById('category-id').value = '';
    document.getElementById('category-name').value = '';
    document.getElementById('category-modal').style.display = 'flex';
}

function editCategory(category) {
    document.getElementById('category-modal-title').textContent = 'Modifier la catégorie';
    document.getElementById('category-id').value = category.id;
    document.getElementById('category-name').value = category.nom;
    document.getElementById('category-modal').style.display = 'flex';
}

function closeCategoryModal() {
    document.getElementById('category-modal').style.display = 'none';
}

function saveCategory(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.append('category', document.getElementById('category-name').value);
    const categoryId = document.getElementById('category-id').value;
    const url = categoryId ? APP_URL + '/api/categories/update' : APP_URL + '/api/categories';

    if (categoryId) {
        formData.append('id', categoryId);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(categoryId ? 'Catégorie modifiée !' : 'Catégorie créée !');
                closeCategoryModal();
                window.location.reload();
            } else {
                alert(data.error || 'Erreur');
            }
        })
        .catch(() => alert('Erreur serveur'));

    return false;
}

function deleteCategory(id) {
    if (confirm('Supprimer definitivement cette catégorie ?')) {
        const formData = new FormData();
        formData.append('id', id);
        fetch(APP_URL + '/api/delete/category', {
            method: 'POST',
            body: formData
        })
            .then(res => res.text())
            .then(data => {
                alert('Catégorie supprimée !');
                window.location.reload();
            })
            .catch(() => alert('Erreur serveur'));
    }
}


// ==================== SERVICE BILL FETCHER ====================
// Service Bill API proxy URL (évite CORS)
const SERVICE_BILL_API_URL = APP_URL + '/api/service-bill';

async function fetchServiceBillData(nfacture, client_isf) {
    try {
        const resp = await fetch(SERVICE_BILL_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nfacture: nfacture,
                client_isf: client_isf
            })
        });
        if (resp.ok) {
            const data = await resp.json();
            return data.success ? data : null;
        }
    } catch (e) {
        console.warn('Service bill API error:', e);
    }
    return null;
}

function renderServiceBillContent(data, sale) {
    let info = data.data;

    info = info.data

    // Parser les articles si c'est une chaîne JSON
    let articlesList = [];
    if (info.articles) {
        if (typeof info.articles === 'string') {
            try { articlesList = JSON.parse(info.articles); } catch (e) { console.warn('Articles parse error:', e); }
        } else {
            articlesList = info.articles;
        }
    }

    let html = '<div class="receipt">';

    // Header avec PROFORMA
    html += '<div class="receipt-header">';
    html += '<div style="text-align:center; font-weight:800; font-size:24px; color:#000; margin-bottom:10px; border-bottom:2px solid #000; padding-bottom:5px;">PROFORMA</div>';
    html += '<div class="store-name">' + (info.store_name || STORE_INFO.name) + '</div>';
    html += '<div class="store-info">';
    html += '<div>' + (info.store_address || STORE_INFO.address) + '</div>';
    html += '<div>Tel: ' + (info.store_phone || STORE_INFO.phone) + '</div>';
    html += '<div>ID Nat: ' + (info.store_ice || STORE_INFO.ice) + '</div>';
    if (info.store_rccm) html += '<div>RCCM: ' + info.store_rccm + '</div>';
    html += '<div>Numero Impot: ' + (info.store_isf || STORE_INFO.isf) + '</div>';
    html += '</div>';

    // Section Vendeur/Client
    const vendeur = info.sellerName || 'N/A';
    const clientNom = info.client_name || '';
    const clientNumero = info.client_number || '';
    const clientType = info.client_type || '';
    const clientNif = info.client_nif || '';

    html += '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:15px; line-height:1.5;">';
    html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Vendeur:</strong></span><span>' + vendeur + '</span></div>';
    if (clientNom) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Client:</strong></span><span>' + clientNom + '</span></div>';
    if (clientNumero) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Num:</strong></span><span>' + formatPhoneNumber(clientNumero) + '</span></div>';
    if (clientType) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Type:</strong></span><span>' + clientType + '</span></div>';
    if (clientNif) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>NIF:</strong></span><span>' + clientNif + '</span></div>';
    html += '</div>';
    html += '</div>'; // fin receipt-header

    // Meta (numéro facture et type)
    html += '<div class="receipt-meta">';
    html += '<span>' + (info.invoice_number || sale.numero_facture) + '</span>';
    html += '<span>' + (info.invoice_type || 'FV') + '</span>';
    html += '</div>';

    // Items des articles
    html += '<div class="receipt-items receipt-items-grid">';
    if (articlesList.length > 0) {
        articlesList.forEach(article => {
            const articleHT = parseFloat(article.price) || 0;
            const taxLabel = article.taxSpecificValue || 'Exonere';
            html += '<div class="receipt-item">';
            html += '<span class="item-name">' + article.name + '<span class="item-tax-badge">' + taxLabel + '</span></span>';
            html += '<span class="item-qty">x' + (article.quantity || 1) + '</span>';
            html += '<span class="item-price">' + articleHT.toFixed(2) + '</span>';
            html += '</div>';
        });
    }
    html += '</div>';

    // Totaux
    const total = parseFloat(info.total || 0);
    const sousTotalHT = total - parseFloat(info.vtotal || 0);
    const tva = parseFloat(info.vtotal || 0);

    html += '<div class="receipt-totals">';

    html += '<div class="receipt-total-row"><span>TVA:</span><span>' + tva.toFixed(2) + ' Fc</span></div>';
    html += '<div class="receipt-total-row grand-total"><span>TOTAL TTC:</span><span>' + total.toFixed(2) + ' Fc</span></div>';

    // Commentaire
    if (info.comment || info.providerService) {
        html += '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:left;">';
        html += '<div style="font-weight:bold; text-decoration:underline; margin-bottom:4px;">Commentaire/Remarque :</div>';
        html += '<div>' + (info.comment ? info.comment : "Aucun commentaire") + '</div>';
        html += '</div>';
    }
    html += '</div>';

    // Éléments de sécurité DGI
    if (info.codeDEFDGI || info.counters || info.nim) {
        html += '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">';
        html += '<div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite ---</div>';
        html += '<div style="font-size:12px; color:#555; margin-top:4px;">';
        if (info.codeDEFDGI) html += 'CODE DEF/DGI: ' + info.codeDEFDGI + '<br>';
        if (info.nim) html += ' DEF NID : ' + info.nim + '<br>';
        if (info.counters) html += ' DEF Compteurs: ' + info.counters + '<br>';
        html += ' ISF : ' + (info.isf || info.store_isf || '0');
        html += '</div></div>';
    }

    // Footer avec QR
    html += '<div class="receipt-footer">';
    if (info.qrCode) html += '<div id="service-bill-qrcode" class="qrcode-container"></div>';
    html += '<div class="barcode">' + (info.invoice_number || sale.numero_facture) + '</div>';
    html += '<div class="thank-you">Merci de votre visite!</div>';
    html += '<div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div>';
    html += '</div></div>';

    if (info.qrCode) {
        setTimeout(() => posCart.generateDGIQRCode(info.qrCode, 'service-bill-qrcode'), 100);
    }

    return html;
}

// ==================== SALE DETAILS ====================

async function viewSaleDetails(saleId) {
    const response = await fetch(APP_URL + '/api/vente/' + saleId + '/details');
    const data = await response.json();

    if (data.error) {
        alert(data.error);
        return;
    }

    const sale = data.sale;

    if (sale.service) {
        document.getElementById('sale-details-content').innerHTML = '<div style="text-align:center; padding:40px;"><div class="spinner"></div><p style="margin-top:1rem;">Chargement des donnees...</p></div>';
        document.getElementById('sale-details-modal').classList.add('active');

        const serviceData = await fetchServiceBillData(sale.numero_facture, STORE_INFO.isf);

        console.log(serviceData)

        if (serviceData) {
            document.getElementById('sale-details-content').innerHTML = renderServiceBillContent(serviceData, sale);
        } else {
            document.getElementById('sale-details-content').innerHTML = '<div style="background:#ffebee; padding:15px; border-radius:8px; margin:10px 0;"><strong>Erreur:</strong> Impossible de charger les details.</div><button class="btn btn-secondary" onclick="document.getElementById(\'sale-details-modal\').classList.remove(\'active\')">Fermer</button>';
        }

        document.getElementById('print-sale-btn').onclick = () => printSaleReceipt(saleId);
    } else {
        // Normal receipt flow - PROFORMA
        let itemsHtml = '';
        data.details.forEach(item => {
            const itemHT = parseFloat(item.prix) * parseFloat(item.quantite);
            const taxRate = parseFloat(item.tax_rate || 0);
            const itemTTC = itemHT;
            const taxLabel = item.tax_etiquette || (taxRate > 0 ? 'TVA ' + taxRate + '%' : 'Exonere');
            itemsHtml += '<div class="receipt-item"><span class="item-name">' + (item.produit_nom || 'Produit') + '<span class="item-tax-badge">' + taxLabel + '</span></span><span class="item-qty">x' + item.quantite + '</span><span class="item-price">' + itemTTC.toFixed(2) + '</span></div>';
        });

        let storeExtraInfo = '';
        if (STORE_INFO.rccm) storeExtraInfo += '<div>RCCM: ' + STORE_INFO.rccm + '</div>';
        if (STORE_INFO.isf) storeExtraInfo += '<div>Numero Impot: ' + STORE_INFO.isf + '</div>';

        const vendeur = sale.nom_vendeur || 'N/A';
        const acheteurNom = sale.nom_client || '';
        const acheteurNumero = sale.client_numero || '';
        const acheteurType = sale.client_type_code || '';
        const acheteurNif = sale.client_nif || '';

        let infoSection = '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:15px; line-height:1.5;"><div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Vendeur:</strong></span><span>' + vendeur + '</span></div>';
        if (acheteurNom) infoSection += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Client:</strong></span><span>' + acheteurNom + '</span></div>';
        if (acheteurNumero) infoSection += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Num:</strong></span><span>' + formatPhoneNumber(acheteurNumero) + '</span></div>';
        if (acheteurType) infoSection += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Type:</strong></span><span>' + acheteurType + '</span></div>';
        if (acheteurNif) infoSection += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>NIF:</strong></span><span>' + acheteurNif + '</span></div>';
        if (STORE_INFO.isf) infoSection += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>ISF:</strong></span><span>' + STORE_INFO.isf + '</span></div>';
        infoSection += '</div>';

        let dgiInfoHtml = '';
        if (sale.counters) {
            dgiInfoHtml = '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;"><div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite ---</div><div style="font-size:12px; color:#555; margin-top:4px;">';
            if (sale.codeDEFDGI) dgiInfoHtml += 'CODE DEF/DGI: ' + sale.codeDEFDGI;
            if (sale.nim) dgiInfoHtml += '<br> DEF NID : ' + sale.nim;
            if (sale.counters) dgiInfoHtml += '<br> DEF Compteurs: ' + sale.counters;
            if (sale.dateDGI) dgiInfoHtml += '<br> DEF Heure : ' + sale.dateDGI;
            dgiInfoHtml += '<br> ISF : ' + (STORE_INFO.isf || '0') + '</div></div>';
        }

        document.getElementById('sale-details-content').innerHTML = '<div class="receipt"><div class="receipt-header"><div style="text-align:center; font-weight:800; font-size:24px; color:#000; margin-bottom:10px; border-bottom:2px solid #000; padding-bottom:5px;">PROFORMA</div><div class="store-name">' + STORE_INFO.name + '</div><div class="store-info"><div>' + STORE_INFO.address + '</div><div>Tel: ' + STORE_INFO.phone + '</div><div>ID Nat: ' + STORE_INFO.ice + '</div>' + storeExtraInfo + '</div>' + infoSection + '</div><div class="receipt-meta"><span>' + sale.numero_facture + '</span><span>' + (sale.type_facture || 'FV') + '</span></div><div class="receipt-items receipt-items-grid">' + itemsHtml + '</div><div class="receipt-totals"><div class="receipt-total-row"><span>Sous-total HT:</span><span>' + parseFloat(sale.sous_total_ht).toFixed(2) + ' Fc</span></div><div class="receipt-total-row"><span>TVA:</span><span>' + parseFloat(sale.tva).toFixed(2) + ' Fc</span></div><div class="receipt-total-row grand-total"><span>TOTAL TTC:</span><span>' + parseFloat(sale.total).toFixed(2) + ' Fc</span></div><div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:left;"><div style="font-weight:bold; text-decoration:underline; margin-bottom:4px;">Commentaire/Remarque :</div><div>' + (sale.comment || 'Aucun commentaire') + '</div></div></div>' + dgiInfoHtml + '<div class="receipt-footer"><div id="history-qrcode-container" class="qrcode-container"></div><div class="barcode">' + sale.numero_facture + '</div><div class="thank-you">Merci de votre visite!</div><div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div></div></div>';

        posCart.generateDGIQRCode(sale.qrCode || sale.numero_facture, 'history-qrcode-container');
        document.getElementById('print-sale-btn').onclick = () => printSaleReceipt(saleId);
        document.getElementById('sale-details-modal').classList.add('active');
    }
}

function printSaleReceipt(saleId) {
    const content = $('#sale-details-content').innerHTML;
    _printReceiptContent(content);
}

function closeProductModal() {
    $('#product-modal').classList.remove('active');
    $('#product-form').reset();
    $('#product-id').value = '';
    $('#product-modal-title').textContent = 'Ajouter un produit';
}

function closeReceiptModal() {
    // Fermer le modal du reçu
    const receiptModal = document.getElementById('receipt-modal');
    if (receiptModal) {
        receiptModal.classList.remove('active');
    }
    // Fermer le panier mobile (sidebar) si on est sur mobile
    const cart = document.getElementById('caisse-cart');
    const overlay = document.getElementById('cart-sidebar-overlay');
    if (cart) {
        cart.classList.remove('open');
        cart.classList.remove('mobile-open');
        cart.classList.remove('active');
    }
    if (overlay) {
        overlay.classList.remove('active');
    }
    document.body.style.overflow = '';
    // Cacher le bouton flottant si le panier est vide
    const floatingBtn = document.getElementById('cart-floating-btn');
    if (floatingBtn && posCart && posCart.items.length === 0) {
        floatingBtn.style.display = 'none';
    }
}

function generateBarcode() {
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    $('#product-barcode').value = timestamp + random;
}

function openProductModal() {
    // Charger les catégories si nécessaire avant d'afficher le modal
    if (categoriesCache.length === 0) {
        loadCategories().then(() => {
            $('#product-modal').classList.add('active');
        });
    } else {
        $('#product-modal').classList.add('active');
    }
}

// ==================== PAYMENT MODAL ====================

function closePaymentModal() {
    const modal = $('#payment-modal');
    if (modal) {
        modal.classList.remove('active');
    }
    // Reset payment amount
    if (posCart) {
        posCart.paymentAmount = 0;
        posCart.paymentStatus = 'pending';
    }
}

function confirmPayment() {
    // Vérifier que le montant est suffisant
    if (!posCart || !posCart.paymentAmount || posCart.paymentStatus === 'insufficient') {
        alert('Le montant reçu est insuffisant!');
        return;
    }

    if (!posCart.paymentAmount || posCart.paymentAmount <= 0) {
        alert('Veuillez entrer le montant reçu du client');
        return;
    }

    // Fermer le modal de paiement
    closePaymentModal();

    // Appeler confirmSale pour finaliser la vente
    posCart.confirmSale();
}

// ==================== SCANNER MODAL ====================

let scannerHtml5QrCode = null;
let isScannerActive = false;
let scannerLastCode = null;
let scannerProcessing = false;

function openScannerModal() {
    const modal = document.getElementById('scanner-modal');
    if (!modal) return;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Démarrer le scanner après l'affichage du modal
    setTimeout(() => {
        startInlineScanner();
    }, 300);
}

function closeScannerModal() {
    const modal = document.getElementById('scanner-modal');
    if (!modal) return;

    modal.classList.remove('active');
    document.body.style.overflow = '';

    // Arrêter le scanner
    stopInlineScanner();
}

async function startInlineScanner() {
    // Charger la bibliothèque si nécessaire
    if (typeof Html5Qrcode === 'undefined') {
        await loadHtml5QrCode();
    }

    const readerEl = document.getElementById('scanner-reader');
    if (!readerEl) return;

    // Reset UI
    document.getElementById('scanner-loading').classList.remove('loading');
    document.getElementById('scanner-loading').style.display = 'none';
    document.getElementById('scanner-result').className = 'scanner-status';
    document.getElementById('scanner-result').style.display = 'none';
    document.getElementById('scanner-product').classList.remove('show');
    document.getElementById('scanner-rescan-btn').style.display = 'none';
    document.getElementById('scanner-reader').style.display = 'block';
    readerEl.innerHTML = '';

    scannerHtml5QrCode = new Html5Qrcode('scanner-reader');
    isScannerActive = true;
    scannerLastCode = null;
    scannerProcessing = false;

    try {
        await scannerHtml5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onInlineScanSuccess,
            onInlineScanFailure
        );
        console.log('[SCANNER MODAL] Démarré avec succès');
    } catch (err) {
        console.error('[SCANNER MODAL] Erreur:', err);
        readerEl.innerHTML = '<div style="text-align:center;padding:20px;color:#dc3545;">Erreur caméra: ' + err.message + '</div>';
    }
}

function onInlineScanSuccess(code) {
    if (scannerProcessing || code === scannerLastCode) return;

    scannerLastCode = code;
    scannerProcessing = true;

    console.log('[SCANNER MODAL] Code détecté:', code);

    // Feedback sonore et tactile
    if (typeof playScanBeep === 'function') {
        playScanBeep();
    }
    if ('vibrate' in navigator) {
        navigator.vibrate(100);
    }

    // Afficher le chargement
    const loadingEl = document.getElementById('scanner-loading');
    loadingEl.style.display = 'flex';
    loadingEl.classList.add('loading');

    const resultEl = document.getElementById('scanner-result');
    resultEl.style.display = 'none';
    document.getElementById('scanner-product').classList.remove('show');

    // Rechercher le produit
    searchProductForCart(code);
}

function onInlineScanFailure(error) {
    // Ne rien faire - c'est normal
}

function searchProductForCart(barcode) {
    // Rechercher dans les produits déjà chargés
    document.getElementById('scanner-loading').style.display = 'none';

    const product = posCart.allProducts.find(p => p.code_barres === barcode);

    if (product) {
        // Produit trouvé - ajouter au panier
        onInlineProductFound(product, barcode);
    } else {
        onInlineProductNotFound(barcode);
    }
}

function onInlineProductFound(product, barcode) {
    // Ajouter au panier
    posCart.addToCart(product.id);

    const resultEl = document.getElementById('scanner-result');
    resultEl.className = 'scanner-status success';
    resultEl.textContent = '✓ ' + product.nom + ' ajouté !';
    resultEl.style.display = 'block';

    // Afficher les infos
    document.getElementById('scanner-product').classList.add('show');
    document.getElementById('scanned-name').textContent = product.nom;
    document.getElementById('scanned-price').textContent = formatCurrency(product.prix);

    // NE PAS fermer le modal, NE PAS arrêter le scanner
    // Réinitialiser pour le prochain scan après un court délai (cooldown)
    setTimeout(() => {
        scannerProcessing = false;
        scannerLastCode = null; // Permet de rescanner le même produit
        resultEl.style.display = 'none';
        document.getElementById('scanner-product').classList.remove('show');
    }, 1500); // 1.5 seconde de délai entre deux scans
}

function onInlineProductNotFound(barcode) {
    const resultEl = document.getElementById('scanner-result');
    resultEl.className = 'scanner-status error';
    resultEl.textContent = '✗ Code-barres introuvable dans la base';
    resultEl.style.display = 'block';

    setTimeout(() => {
        scannerProcessing = false;
        scannerLastCode = null;
        resultEl.style.display = 'none';
    }, 3000);
}

async function restartScanner() {
    await startInlineScanner();
}

async function stopInlineScanner() {
    if (scannerHtml5QrCode && isScannerActive) {
        try {
            await scannerHtml5QrCode.stop();
        } catch (err) {
            console.warn('[SCANNER MODAL] Erreur arrêt:', err);
        }
        isScannerActive = false;
    }
}

function loadHtml5QrCode() {
    return new Promise((resolve, reject) => {
        if (typeof Html5Qrcode !== 'undefined') {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// ==================== NEW CLIENT MODAL ====================

function openNewClientModal(numero) {
    const modal = document.getElementById('new-client-modal');
    if (!modal) return;

    // Reset form
    document.getElementById('new-client-nom').value = '';
    document.getElementById('new-client-numero').value = numero || '';
    document.getElementById('new-client-numero-hidden').value = numero || '';
    document.getElementById('new-client-type').value = '1';

    // Show modal
    modal.classList.add('active');
    document.getElementById('new-client-nom').focus();
}

function closeNewClientModal() {
    const modal = document.getElementById('new-client-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Handle new client form submission
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('new-client-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nom = document.getElementById('new-client-nom').value.trim();
            const numero = document.getElementById('new-client-numero').value.trim();
            const typeClientId = document.getElementById('new-client-type').value;

            if (!nom || !numero) {
                alert('Veuillez remplir tous les champs');
                return;
            }

            try {
                const res = await fetch(APP_URL + '/api/client', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nom: nom,
                        numero: numero,
                        type_client_id: typeClientId
                    })
                });

                const data = await res.json();

                if (data.success && data.client) {
                    // Client créé avec succès
                    closeNewClientModal();

                    // Mettre à jour le panier avec le nouveau client
                    posCart.currentClient = data.client;
                    posCart.clientNumber = numero;
                    posCart.displayClientName(data.client);

                    // Mettre à jour le champ de numéro
                    const clientInput = document.getElementById('client-number');
                    if (clientInput) clientInput.value = numero;
                } else {
                    alert(data.message || 'Erreur lors de la création du client');
                }
            } catch (e) {
                console.error('Erreur création client:', e);
                alert('Erreur de connexion');
            }
        });
    }
});

// ==================== QUICK CLIENT SAVE ====================

async function saveClientQuick() {
    const nom = document.getElementById('client-nom').value.trim();
    const numero = document.getElementById('client-number').value.trim();
    const typeClientId = document.getElementById('client-type').value;
    const nif = document.getElementById('client-nif').value.trim();

    if (!nom || !numero) {
        alert('Veuillez remplir le nom et le numéro du client');
        return;
    }

    if (!typeClientId) {
        alert('Veuillez sélectionner le type de client');
        return;
    }

    try {
        const res = await fetch(APP_URL + '/api/client', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nom: nom,
                numero: numero,
                type_client_id: typeClientId,
                nif: nif
            })
        });

        const data = await res.json();

        if (data.success && data.client) {
            // Client créé avec succès
            posCart.currentClient = data.client;
            posCart.clientNumber = numero;
            posCart.displayClientName(data.client);

            // Afficher une confirmation visuelle
            const btn = document.getElementById('btn-save-client');
            if (btn) {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-success');
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Client enregistré!';
                setTimeout(() => {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-secondary');
                    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg> Enregistrer client';
                }, 2000);
            }
        } else {
            // Client existe déjà - l'utiliser
            if (data.client) {
                posCart.currentClient = data.client;
                posCart.clientNumber = numero;
                posCart.displayClientName(data.client);
                alert('Client déjà enregistré: ' + data.client.nom);
            } else {
                alert(data.message || 'Erreur lors de l\'enregistrement');
            }
        }
    } catch (e) {
        console.error('Erreur saveClientQuick:', e);
        alert('Erreur de connexion');
    }
}

// Main initialization
document.addEventListener('DOMContentLoaded', () => {
    // Initialize cart
    posCart.init();

    // Load store info
    loadStoreInfo();

    // Load categories for product modal
    loadCategories();

    // Initialize products tabs if on products page
    if ($('#products-table')) {
        initProductsTabs();
    }

    // Initialize history filters if on history page
    if ($('#invoice-search')) {
        initHistoryFilters();
    }

    // Print button
    const printBtn = $('#print-receipt');
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            const content = $('#receipt-content').innerHTML;
            _printReceiptContent(content);
        });
    }

    // Initialize sidebar
    initSidebar();

    // Product form submit - UNIQUEMENT si le formulaire existe
    const productForm = $('#product-form');
    if (productForm) {
        productForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await posCart.saveProduct();
        });
    }
});

// Mobile sidebar
function initSidebar() {
    const menuToggle = $('#menu-toggle');
    const closeSidebar = $('#close-sidebar');
    const sidebarOverlay = $('#sidebar-overlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            $('#sidebar').classList.add('open');
            sidebarOverlay.classList.add('active');
        });
    }

    if (closeSidebar) {
        closeSidebar.addEventListener('click', () => {
            $('#sidebar').classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            $('#sidebar').classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    $$('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            $('#sidebar').classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    });
}

// ==================== CART SIDEBAR TOGGLE (Mobile) ====================

function toggleCartSidebar() {
    const cart = $('#caisse-cart');
    const overlay = $('#cart-sidebar-overlay');

    if (!cart) return;

    const isOpen = cart.classList.contains('open');

    if (isOpen) {
        cart.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    } else {
        cart.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function updateCartFloatingButton() {
    const badge = $('#cart-badge');
    const total = $('#cart-floating-total');

    if (!badge || !total) return;

    const itemCount = posCart.items.reduce((sum, item) => sum + item.quantite, 0);
    const totalAmount = posCart.items.reduce((sum, item) => sum + (item.prix * item.quantite), 0);

    badge.textContent = itemCount;
    total.textContent = totalAmount.toFixed(2) + ' Fc';
}

// Intercept posCart methods to update floating button
const originalRenderCart = posCart.renderCart.bind(posCart);
posCart.renderCart = function () {
    originalRenderCart();
    updateCartFloatingButton();
};

const originalClearCart = posCart.clearCart.bind(posCart);
posCart.clearCart = function () {
    originalClearCart();
    updateCartFloatingButton();
};

const originalAddToCart = posCart.addToCart.bind(posCart);
posCart.addToCart = function (id) {
    originalAddToCart(id);
    updateCartFloatingButton();
};

const originalUpdateQty = posCart.updateQty.bind(posCart);
posCart.updateQty = function (id, delta) {
    originalUpdateQty(id, delta);
    updateCartFloatingButton();
};

const originalRemoveFromCart = posCart.removeFromCart.bind(posCart);
posCart.removeFromCart = function (id) {
    originalRemoveFromCart(id);
    updateCartFloatingButton();
};

// Initialize floating button on load
document.addEventListener('DOMContentLoaded', () => {
    updateCartFloatingButton();
});

// ==================== PRODUCTS TABS ====================

function initProductsTabs() {
    const filterInput = $('#products-filter');
    const categoryFilter = $('#category-filter');
    const refreshBtn = $('#refresh-products');

    function filterProductsTable(search = '', category = 'all') {
        const rows = $$('#products-table tr[data-category]');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowCategory = row.dataset.category ? row.dataset.category.toLowerCase() : '';
            const nom = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
            const code = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';

            const matchesSearch = !search || nom.includes(search.toLowerCase()) || code.includes(search.toLowerCase());
            const matchesCategory = category === 'all' || rowCategory === category.toLowerCase();

            if (matchesSearch && matchesCategory) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        let emptyMsg = $('#products-empty-message');
        if (!emptyMsg && visibleCount === 0 && rows.length > 0) {
            const table = $('#products-table');
            const tr = document.createElement('tr');
            tr.id = 'products-empty-message';
            tr.innerHTML = '<td colspan="7" style="text-align:center; padding:2rem; color:#888;">Aucun produit trouvé</td>';
            table.appendChild(tr);
        } else if (emptyMsg && visibleCount > 0) {
            emptyMsg.remove();
        }
    }

    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            const category = categoryFilter ? categoryFilter.value : 'all';
            filterProductsTable(e.target.value, category);
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            const search = filterInput ? filterInput.value : '';
            filterProductsTable(search, e.target.value);
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            window.location.reload();
        });
    }

    filterProductsTable();
}

// ==================== HISTORY FILTERS ====================

function initHistoryFilters() {
    const invoiceSearch = $('#invoice-search');
    const dateFilter = $('#date-filter');
    const sellerFilter = $('#seller-filter');

    function filterHistory() {
        const invoiceQuery = invoiceSearch ? invoiceSearch.value.toLowerCase().trim() : '';
        const dateQuery = dateFilter ? dateFilter.value : '';
        const sellerQuery = sellerFilter ? sellerFilter.value : 'all';

        const rows = $$('#page-history tbody tr[data-invoice]');
        let visibleCount = 0;

        rows.forEach(row => {
            const invoice = row.dataset.invoice ? row.dataset.invoice.toLowerCase() : '';
            const date = row.dataset.date || '';
            const seller = row.dataset.seller || '';

            const matchInvoice = !invoiceQuery || invoice.includes(invoiceQuery);
            const matchDate = !dateQuery || date === dateQuery;
            const matchSeller = sellerQuery === 'all' || seller === sellerQuery;

            if (matchInvoice && matchDate && matchSeller) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        let emptyMsg = $('#history-empty-message');
        if (!emptyMsg && visibleCount === 0 && rows.length > 0) {
            const tbody = document.querySelector('#page-history tbody');
            const tr = document.createElement('tr');
            tr.id = 'history-empty-message';
            tr.innerHTML = '<td colspan="5" style="text-align:center; padding:2rem; color:#888;">Aucune vente trouvée</td>';
            tbody.appendChild(tr);
        } else if (emptyMsg && visibleCount > 0) {
            emptyMsg.remove();
        }
    }

    if (invoiceSearch) {
        invoiceSearch.addEventListener('input', filterHistory);
        invoiceSearch.addEventListener('keyup', filterHistory);
    }
    if (dateFilter) {
        dateFilter.addEventListener('change', filterHistory);
    }
    if (sellerFilter) {
        sellerFilter.addEventListener('change', filterHistory);
    }

    filterHistory();
}

// ==================== PRINT RECEIPT ====================

function _printReceiptContent(content) {
    const oldFrame = document.getElementById('_print-frame');
    if (oldFrame) oldFrame.remove();

    const iframe = document.createElement('iframe');
    iframe.id = '_print-frame';
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:80mm;height:1px;border:none;overflow:hidden;';
    document.body.appendChild(iframe);

    const printStyles = `
        <style>
            @page { margin: 3mm 2mm; size: 80mm auto; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Courier New', Courier, monospace;
                font-size: 14px;
                line-height: 1.5;
                width: 100%;
                max-width: 76mm;
                margin: 0 auto;
                color: #000;
                background: #fff;
            }
            .receipt { width: 100%; }
            .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 12px; }
            .receipt-header .store-name { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
            .receipt-header .store-info { font-size: 13px; line-height: 1.6; color: #222; }
            .receipt-meta { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; padding: 8px 0; margin-bottom: 10px; border-bottom: 2px solid #000; }
            .receipt-items { margin-bottom: 10px; }
            .receipt-item { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 5px; font-size: 13px; gap: 3px; }
            .receipt-item .item-name { flex: 2; min-width: 0; white-space: normal; overflow-wrap: break-word; }
            .receipt-item .item-qty { flex: 1; text-align: center; white-space: nowrap; }
            .receipt-item .item-price { flex: 1; text-align: right; font-weight: 700; white-space: nowrap; }
            .receipt-totals { margin-bottom: 8px; }
            .receipt-total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; }
            .receipt-total-row.grand-total { font-size: 18px; font-weight: 700; border-top: 3px solid #000; border-bottom: 3px solid #000; padding: 8px 0; margin-top: 8px; }
            div[style*="e8f5e9"], div[style*="4caf50"] { border-radius: 4px; padding: 8px 10px !important; margin: 10px 0 !important; font-size: 13px !important; }
            .receipt-footer { text-align: center; margin-top: 12px; padding-top: 10px; border-top: 2px solid #000; font-size: 13px; }
            .vendeur-info { margin-bottom: 8px; }
            .qrcode-container { width: 100%; text-align: center; margin: 10px 0; overflow: visible; }
            .qrcode-container > div { display: inline-block; overflow: visible; }
            .qrcode-container svg, .qrcode-container img { display: block; margin: 0 auto; max-width: 160px; height: auto; overflow: visible; }
            .barcode { font-size: 18px; letter-spacing: 3px; font-weight: 700; margin: 8px 0; text-align: center; }
            .thank-you { font-style: italic; margin-top: 8px; font-size: 13px; }
        </style>
    `;

    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">${printStyles}</head><body>${content}</body></html>`);
    doc.close();

    iframe.onload = function () {
        setTimeout(() => {
            try {
                const iDoc = iframe.contentDocument;
                iDoc.querySelectorAll('.qrcode-container svg').forEach(svg => {
                    const origW = parseInt(svg.getAttribute('width') || 180);
                    const origH = parseInt(svg.getAttribute('height') || 180);
                    if (!svg.getAttribute('viewBox')) {
                        svg.setAttribute('viewBox', `0 0 ${origW} ${origH}`);
                    }
                    svg.setAttribute('width', '220');
                    svg.setAttribute('height', '220');
                    svg.style.width = '220px';
                    svg.style.height = '220px';
                    svg.style.display = 'block';
                    svg.style.margin = '0 auto';
                    svg.style.overflow = 'visible';
                });

                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                console.error('Erreur impression iframe:', e);
            }
            setTimeout(() => { if (iframe.parentNode) iframe.remove(); }, 2000);
        }, 400);
    };
}

// ==================== CLIENT SEARCH ====================

async function searchClientByNumero() {
    const numeroInput = document.getElementById('client-number');
    const nomInput = document.getElementById('client-nom');
    const typeInput = document.getElementById('client-type');
    const messageDiv = document.getElementById('client-search-message');

    if (!numeroInput) return;

    const numero = numeroInput.value.trim();

    if (!numero) {
        showClientMessage('Veuillez entrer un numéro de téléphone', 'error');
        return;
    }

    // Afficher le loader
    const searchBtn = document.getElementById('btn-search-client');
    if (searchBtn) {
        searchBtn.disabled = true;
        searchBtn.innerHTML = '<div class="loader-spinner" style="width:16px;height:16px;border-width:2px;"></div>';
    }

    try {
        const res = await fetch(APP_URL + '/api/client/search?numero=' + encodeURIComponent(numero));
        const data = await res.json();

        if (data.success && data.client) {
            // Client trouvé - remplir les champs
            if (nomInput) {
                nomInput.value = data.client.nom_client || '';
                nomInput.style.borderColor = '#10b981';
                nomInput.style.background = '#f0fdf4';
            }

            if (typeInput) {
                // Essayer de trouver le type par ID d'abord, puis par code
                const typeOptions = typeInput.querySelectorAll('option');
                let found = false;

                for (let i = 0; i < typeOptions.length; i++) {
                    if (data.client.type_id && typeOptions[i].value == data.client.type_id) {
                        typeInput.value = data.client.type_id;
                        found = true;
                        break;
                    }
                }

                if (!found && data.client.type_code) {
                    for (let i = 0; i < typeOptions.length; i++) {
                        if (typeOptions[i].textContent.includes(data.client.type_code)) {
                            typeInput.value = typeOptions[i].value;
                            found = true;
                            break;
                        }
                    }
                }

                if (found) {
                    typeInput.style.borderColor = '#10b981';
                    typeInput.style.background = '#f0fdf4';
                }
            }

            // Afficher le NIF
            const nifInput = document.getElementById('client-nif');
            if (nifInput) {
                nifInput.value = data.client.nif || '';
                nifInput.style.borderColor = '#10b981';
                nifInput.style.background = '#f0fdf4';
            }

            // Sauvegarder le client dans le panier
            if (posCart) {
                posCart.currentClient = data.client;
                posCart.clientNumber = numero;
            }

            showClientMessage('Client trouvé: ' + data.client.nom_client, 'success');
        } else {
            // Client non trouvé
            if (nomInput) {
                nomInput.value = '';
                nomInput.style.borderColor = '#f44336';
                nomInput.style.background = '#ffebee';
            }

            if (typeInput) {
                typeInput.value = '';
                typeInput.style.borderColor = '#f44336';
                typeInput.style.background = '#ffebee';
            }

            const nifInput = document.getElementById('client-nif');
            if (nifInput) {
                nifInput.value = '';
                nifInput.style.borderColor = '#f44336';
                nifInput.style.background = '#ffebee';
            }

            if (posCart) {
                posCart.currentClient = null;
            }

            showClientMessage('Client non trouvé. Vous pouvez l\'enregistrer.', 'error');
        }
    } catch (e) {
        console.error('Erreur recherche client:', e);
        showClientMessage('Erreur de connexion', 'error');
    } finally {
        // Retirer le loader
        if (searchBtn) {
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
        }
    }
}

function showClientMessage(message, type) {
    const messageDiv = document.getElementById('client-search-message');
    if (!messageDiv) return;

    messageDiv.textContent = message;
    messageDiv.style.display = 'block';

    if (type === 'success') {
        messageDiv.style.color = '#10b981';
        messageDiv.style.background = '#f0fdf4';
        messageDiv.style.border = '1px solid #10b981';
    } else {
        messageDiv.style.color = '#f44336';
        messageDiv.style.background = '#ffebee';
        messageDiv.style.border = '1px solid #f44336';
    }

    // Masquer après 3 secondes
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 3000);
}

// ==================== CALCULATOR MODE ====================

function toggleCalcMode(enabled) {
    if (posCart) {
        posCart.calcMode = enabled;
        const toggle = $('#calculator-toggle');
        if (toggle) {
            if (enabled) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        }
    }
}
