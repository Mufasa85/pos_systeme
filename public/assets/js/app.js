const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);

const formatCurrency = (amount) => amount.toFixed(2) + ' Fc';

// DGI API Configuration - utilise le proxy local pour eviter CORS
const DGI_API_URL = APP_URL + '/api/dgi';

// Variables globales pour les informations du magasin
let STORE_INFO = {
    name: 'SuperMarche Express',
    address: '123 Rue Mohammed V, Casablanca',
    phone: '+212 522 123 456',
    ice: '001234567890123'
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
            ice: data.store_ice || STORE_INFO.ice
        };
    } catch (e) {
        console.warn('Impossible de charger les paramètres du magasin, utilisation des valeurs par défaut');
    }
}

const posCart = {
    items: [],
    taxRate: 16,
    currentSaleData: null,
    dgiResponse: null,

    init() {
        // Caisse tabs
        if ($('#product-search')) {
            this.loadProducts();
            $('#product-search').addEventListener('input', (e) => this.filterProducts(e.target.value));
            $$('.category-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    $$('.category-tab').forEach(t => t.classList.remove('active'));
                    e.target.classList.add('active');
                    this.filterProducts($('#product-search').value, e.target.dataset.category);
                });
            });
        }

        // Produits page tabs
        if ($('#products-table')) {
            initProductsTabs();
        }

        // Product Modal Logic
        const addProductForm = $('#product-form');
        if (addProductForm) {
            addProductForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.createProduct();
            });
        }
    },

    allProducts: [],
    currentCategory: 'all',

    async loadProducts() {
        try {
            const res = await fetch(APP_URL + '/api/produits');
            this.allProducts = await res.json();
            this.renderProducts(this.allProducts);
        } catch (e) {
            console.error('Failed fetching products');
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
        grid.innerHTML = list.map(p => `
            <div class="product-card ${p.stock <= 0 ? 'out-of-stock' : ''}" 
                 onclick="posCart.addToCart(${p.id})"
                 ${p.stock <= 0 ? 'title="Rupture de stock"' : ''}>
              <div class="product-image">
                <img src="${p.image}" alt="${p.nom}" onerror="this.style.display='none'">
              </div>
              <div class="name">${p.nom}</div>
              <div class="price">${formatCurrency(parseFloat(p.prix))}</div>
              <div class="barcode-display">${p.code_barres}</div>
            </div>
        `).join('') || '<div class="empty-state">Aucun produit trouve</div>';
    },

    addToCart(id) {
        const product = this.allProducts.find(p => p.id == id);
        if (!product || product.stock <= 0) return;

        const existing = this.items.find(i => i.produit_id == id);
        if (existing) {
            if (existing.quantite < product.stock) existing.quantite++;
        } else {
            this.items.push({
                produit_id: product.id,
                nom: product.nom,
                prix: parseFloat(product.prix),
                quantite: 1,
                maxStock: product.stock
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
        } else if (item.quantite > item.maxStock) {
            item.quantite = item.maxStock;
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
        const clientInput = $('#client-number');
        if (clientInput) clientInput.value = '';
        this.renderCart();
    },

    updateClientNumber(number) {
        this.clientNumber = number;
        console.log('Numéro client mis à jour:', number);
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

        // Les prix sont maintenant HT (sans TVA), on calcule la TVA puis le TTC
        const subtotalHT = this.items.reduce((s, i) => s + (i.prix * i.quantite), 0);
        const taxRate = this.taxRate / 100;
        const tax = subtotalHT * taxRate;
        const subtotalTTC = subtotalHT;

        $('#subtotal').textContent = formatCurrency(subtotalHT);
        $('#tax').textContent = formatCurrency(tax);
        $('#total').textContent = formatCurrency(subtotalTTC);

        this.currentTotals = { sous_total_ht: subtotalHT, tva: tax, total: subtotalTTC };
    },

    // Appeler l'API DGI pour valider la facture
    async validateWithDGI() {
        try {
            // Generer le numero de facture
            const invoiceNum = 'FAC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');

            // Obtenir le nom du vendeur
            const sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : 'POS System';

            // Envoyer les donnees en POST a l'API DGI
            const res = await fetch(DGI_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    store_name: STORE_INFO.name,
                    store_phone: STORE_INFO.phone,
                    store_address: STORE_INFO.address,
                    store_ice: STORE_INFO.ice,
                    seller_name: sellerName,
                    amount: this.currentTotals.total,
                    client_number: this.clientNumber || '',
                    invoice_number: invoiceNum,
                    articles: this.items.map(item => ({
                        name: item.nom,
                        quantity: item.quantite,
                        price: item.prix
                    }))
                })
            });
            return await res.json();
        } catch (e) {
            console.error('Erreur appel DGI:', e);
            return { success: false, message: 'Erreur de connexion DGI' };
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

    // Afficher le récapitulatif de la vente (style ticket thermique moderne)
    showPreview() {
        if (this.items.length === 0) return;

        const formattedDate = new Date().toLocaleString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        const invoiceNum = 'FAC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');

        let itemsHtml = '';
        this.items.forEach(item => {
            itemsHtml += `
                <div class="receipt-item">
                    <span class="item-name">${item.nom}</span>
                    <span class="item-qty">x${item.quantite}</span>
                    <span class="item-price">${(item.prix * item.quantite).toFixed(2)}</span>
                </div>
            `;
        });

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        ${STORE_INFO.address}<br>
                        Tel: ${STORE_INFO.phone}<br>
                        ICE: ${STORE_INFO.ice}
                    </div>
                </div>

                <div class="receipt-meta">
                    <span>${invoiceNum}</span>
                    <span>${formattedDate}</span>
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
                        <span>TVA (16%):</span>
                        <span>${this.currentTotals.tva.toFixed(2)} Fc</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div class="vendeur-info">Vendeur: ${(typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : 'POS System'}</div>
                    <div class="barcode">||| ${invoiceNum} |||</div>
                    <div class="thank-you">Merci de votre visite!</div>
                    <div style="margin-top: 5px; font-size: 9px; font-style: italic;">Conservez ce ticket pour tout echange</div>
                </div>
            </div>
        `;

        $('#preview-modal').classList.add('active');
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
                    sous_total_ht: this.currentTotals.sous_total_ht,
                    tva: this.currentTotals.tva,
                    total: this.currentTotals.total,
                    dgi_data: {
                        dateDGI: dgiResponse.data ? dgiResponse.data.dateDGI : null,
                        qrCode: dgiResponse.data ? dgiResponse.data.qrCode : '',
                        codeDEFDGI: dgiResponse.data ? dgiResponse.data.codeDEFDGI : '',
                        counters: dgiResponse.data ? dgiResponse.data.counters : null,
                        nim: dgiResponse.data ? dgiResponse.data.nim : null
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
            let dgiInfoHtml = '<div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;"><div style="color: #2e7d32; font-weight: bold; font-size: 11px;">VALIDE DGI - ' + (dgiResponse.message || 'Facture generee avec succes') + '</div>';
            if (dgiResponse.data) {
                dgiInfoHtml += '<div style="font-size: 14px; color: #555; margin-top: 5px;">';
                if (dgiResponse.data.dateDGI) dgiInfoHtml += 'Date: ' + dgiResponse.data.dateDGI + '\n';
                if (dgiResponse.data.counters) dgiInfoHtml += 'Compteur: ' + dgiResponse.data.counters;
                if (dgiResponse.data.codeDEFDGI) dgiInfoHtml += '<br>DEF: ' + dgiResponse.data.codeDEFDGI;
                dgiInfoHtml += '</div>';
            }
            dgiInfoHtml += '</div>';

            // Contenu du QR code
            const qrContainerId = 'dgi-qrcode-container';
            const qrCodeContent = (dgiResponse.data && dgiResponse.data.qrCode) ? dgiResponse.data.qrCode : saleData.numero_facture;
            const formattedDate = new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

            // Construire les lignes du tableau
            let itemsHtml = '';
            for (let i = 0; i < this.items.length; i++) {
                const item = this.items[i];
                itemsHtml += `
                    <div class="receipt-item">
                        <span class="item-name">${item.nom}</span>
                        <span class="item-qty">x${item.quantite}</span>
                        <span class="item-price">${(item.prix * item.quantite).toFixed(2)}</span>
                    </div>
                `;
            }

            // Afficher le recu complet
            $('#receipt-content').innerHTML = `
                <div class="receipt">
                    <div class="receipt-header">
                        <div class="store-name">${STORE_INFO.name}</div>
                        <div class="store-info">
                            ${STORE_INFO.address}<br>
                            Tel: ${STORE_INFO.phone}<br>
                            ICE: ${STORE_INFO.ice}
                        </div>
                    </div>

                    <div class="receipt-meta">
                        <span>${saleData.numero_facture}</span>
                        <span>${formattedDate}</span>
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
                            <span>TVA (16%):</span>
                            <span>${this.currentTotals.tva.toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row grand-total">
                            <span>TOTAL TTC:</span>
                            <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                        </div>
                    </div>

                    ${dgiInfoHtml}

                    <div class="receipt-footer">
                        <div class="vendeur-info">Vendeur: ${(typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : 'POS System'}</div>
                        <div id="${qrContainerId}" class="qrcode-container"></div>
                        <div class="barcode">||| ${saleData.numero_facture} |||</div>
                        <div class="thank-you">Merci de votre visite!</div>
                        <p style="margin-top: 5px; color: #555; font-size: 9px;">Conservez ce ticket pour tout echange</p>
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
    document.getElementById('user-modal-title').textContent = 'Ajouter un utilisateur';
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
    document.getElementById('user-modal-title').textContent = 'Modifier l\'utilisateur';
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

// ==================== SALE DETAILS ====================

function viewSaleDetails(saleId) {
    fetch(APP_URL + '/api/vente/' + saleId + '/details')
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const sale = data.sale;
            const details = data.details;

            let itemsHtml = '';
            let totalItems = 0;

            details.forEach(item => {
                totalItems += parseInt(item.quantite);
                const subtotal = parseFloat(item.quantite) * parseFloat(item.prix);
                itemsHtml += `
                    <div class="receipt-item">
                        <span class="item-name">${item.produit_nom || 'Produit #' + item.produit_id}</span>
                        <span class="item-qty">x${item.quantite}</span>
                        <span class="item-price">${subtotal.toFixed(2)}</span>
                    </div>
                `;
            });

            const formattedDate = new Date(sale.date).toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            document.getElementById('sale-details-content').innerHTML = `
                <div class="receipt">
                    <div class="receipt-header">
                        <div class="store-name">${STORE_INFO.name}</div>
                        <div class="store-info">
                            ${STORE_INFO.address}<br>
                            Tel: ${STORE_INFO.phone}<br>
                            ICE: ${STORE_INFO.ice}
                        </div>
                    </div>

                    <div class="receipt-meta">
                        <span>${sale.numero_facture}</span>
                        <span>${formattedDate}</span>
                    </div>

                    <div class="receipt-items receipt-items-grid">
                        ${itemsHtml}
                    </div>

                    <div class="receipt-totals">
                        <div class="receipt-total-row">
                            <span>Sous-total HT:</span>
                            <span>${parseFloat(sale.sous_total_ht).toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row">
                            <span>TVA (16%):</span>
                            <span>${parseFloat(sale.tva).toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row grand-total">
                            <span>TOTAL TTC:</span>
                            <span>${parseFloat(sale.total).toFixed(2)} Fc</span>
                        </div>
                    </div>

                    <div class="receipt-footer">
                        <div class="vendeur-info">Vendeur: ${sale.nom_vendeur || (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName ? CURRENT_USER.fullName : 'N/A')}</div>
                        <div class="barcode">||| ${sale.numero_facture} |||</div>
                        <div class="thank-you">Merci de votre visite!</div>
                        <p style="margin-top: 5px; color: #555; font-size: 9px;">Conservez ce ticket pour tout echange</p>
                    </div>
                </div>
            `;

            // Setup print button
            $('#print-sale-btn').onclick = () => printSaleReceipt(saleId);

            document.getElementById('sale-details-modal').classList.add('active');
        })
        .catch(() => alert('Erreur serveur'));
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

function generateBarcode() {
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    $('#product-barcode').value = timestamp + random;
}

function openProductModal() {
    $('#product-modal').classList.add('active');
}

// Product form submit
document.addEventListener('DOMContentLoaded', () => {
    const productForm = $('#product-form');
    if (productForm) {
        productForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await posCart.saveProduct();
        });
    }

    initSidebar();
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

// Attach init
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

        // Afficher un message si aucun résultat
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

    // Ecouter le champ de recherche
    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            const category = categoryFilter ? categoryFilter.value : 'all';
            filterProductsTable(e.target.value, category);
        });
    }

    // Ecouter le select de catégorie
    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            const search = filterInput ? filterInput.value : '';
            filterProductsTable(search, e.target.value);
        });
    }

    // Bouton refresh
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            window.location.reload();
        });
    }

    // Filtrer initial
    filterProductsTable();
}

document.addEventListener('DOMContentLoaded', () => {
    loadStoreInfo(); // Charger les informations du magasin depuis les paramètres
    posCart.init();
    loadCategories(); // Charger les categories pour le modal produit

    // Initialiser les filtres de la page produits
    if ($('#products-table')) {
        initProductsTabs();
    }

    // Initialiser les filtres de la page historique
    if ($('#invoice-search')) {
        initHistoryFilters();
    }

    // Print Receipt Logic — via iframe (compatible Android/iOS/Desktop)
    const printBtn = $('#print-receipt');
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            const content = $('#receipt-content').innerHTML;
            _printReceiptContent(content);
        });
    }
});

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

        // Afficher un message si aucun résultat
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

    // Écouteurs d'événements
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

    // Filtrer initial
    filterHistory();
}

/**
 * Impression universelle via iframe caché.
 * Compatible Android Chrome, iOS Safari, Desktop.
 * Evite le blocage des popups window.open().
 */
function _printReceiptContent(content) {
    // Supprimer un ancien iframe s'il existe
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
            /* === EN-TETE MAGASIN === */
            .receipt { width: 100%; }
            .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 12px; }
            .receipt-header .store-name { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
            .receipt-header .store-info { font-size: 13px; line-height: 1.6; color: #222; }
            /* === META (numero + date) === */
            .receipt-meta { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; padding: 8px 0; margin-bottom: 10px; border-bottom: 2px solid #000; }
            /* === TABLEAU ARTICLES === */
            .receipt-items { margin-bottom: 10px; }
            .receipt-item { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 5px; font-size: 13px; gap: 3px; }
            .receipt-item .item-name  { flex: 2;   min-width: 0; white-space: normal; overflow-wrap: break-word; }
            .receipt-item .item-qty   { flex: 1;   text-align: center; white-space: nowrap; }
            .receipt-item .item-price { flex: 1;   text-align: right;  font-weight: 700; white-space: nowrap; }
            /* === TOTAUX === */
            .receipt-totals { margin-bottom: 8px; }
            .receipt-total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; }
            .receipt-total-row.grand-total { font-size: 18px; font-weight: 700; border-top: 3px solid #000; border-bottom: 3px solid #000; padding: 8px 0; margin-top: 8px; }
            /* === BOITE DGI VERTE (inline styles du JS) === */
            div[style*="e8f5e9"], div[style*="4caf50"] { border-radius: 4px; padding: 8px 10px !important; margin: 10px 0 !important; font-size: 13px !important; }
            /* === PIED DE PAGE === */
            .receipt-footer { text-align: center; margin-top: 12px; padding-top: 10px; border-top: 2px solid #000; font-size: 13px; }
            .vendeur-info { margin-bottom: 8px; }
            .qrcode-container {
                width: 100%;
                text-align: center;
                margin: 10px 0;
                overflow: visible;
            }
            .qrcode-container > div {
                display: inline-block;
                overflow: visible;
            }
            .qrcode-container svg,
            .qrcode-container img {
                display: block;
                margin: 0 auto;
                max-width: 160px;
                height: auto;
                overflow: visible;
            }
            .barcode { font-size: 18px; letter-spacing: 3px; font-weight: 700; margin: 8px 0; text-align: center; }
            .thank-you { font-style: italic; margin-top: 8px; font-size: 13px; }
        </style>
    `;


    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">${printStyles}</head><body>${content}</body></html>`);
    doc.close();

    // Attendre que l'iframe soit chargée puis lancer l'impression
    iframe.onload = function () {
        setTimeout(() => {
            try {
                // ✅ Patch SVG : forcer viewBox + taille pour éviter l'affichage "quart"
                const iDoc = iframe.contentDocument;
                iDoc.querySelectorAll('.qrcode-container svg').forEach(svg => {
                    const origW = parseInt(svg.getAttribute('width') || 180);
                    const origH = parseInt(svg.getAttribute('height') || 180);
                    // Ajouter viewBox s'il n'existe pas
                    if (!svg.getAttribute('viewBox')) {
                        svg.setAttribute('viewBox', `0 0 ${origW} ${origH}`);
                    }
                    // Forcer les dimensions CSS via attributs (override la lib)
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
            // Nettoyer l'iframe après un délai
            setTimeout(() => { if (iframe.parentNode) iframe.remove(); }, 2000);
        }, 400);
    };
}
