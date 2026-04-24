const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);

const formatCurrency = (amount) => amount.toFixed(2) + ' Fc';

// DGI API Configuration - utilise le proxy local pour eviter CORS
const DGI_API_URL = APP_URL + '/api/dgi';

const posCart = {
    items: [],
    taxRate: 20,
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
        this.renderCart();
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

        const subtotal = this.items.reduce((s, i) => s + (i.prix * i.quantite), 0);
        const taxRate = this.taxRate / 100;
        const subtotalHT = subtotal / (1 + taxRate);
        const tax = subtotal - subtotalHT;

        $('#subtotal').textContent = formatCurrency(subtotalHT);
        $('#tax').textContent = formatCurrency(tax);
        $('#total').textContent = formatCurrency(subtotal);

        this.currentTotals = { sous_total_ht: subtotalHT, tva: tax, total: subtotal };
    },

    // Appeler l'API DGI pour valider la facture
    async validateWithDGI() {
        try {
            // DGI API GET simple - retourne les infos de la facture
            const res = await fetch(DGI_API_URL, {
                method: 'GET'
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
                    <span class="item-pu">${item.prix.toFixed(2)}</span>
                    <span class="item-price">${(item.prix * item.quantite).toFixed(2)}</span>
                </div>
            `;
        });

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">SuperMarche Express</div>
                    <div class="store-info">
                        123 Rue Mohammed V, Casablanca<br>
                        Tel: +212 522 123 456<br>
                        ICE: 001234567890123
                    </div>
                </div>

                <div class="receipt-meta">
                    <span>${invoiceNum}</span>
                    <span>${formattedDate}</span>
                </div>

                <div class="receipt-items">
                    <div class="receipt-item" style="font-weight: 700; border-bottom: 1px solid #333; margin-bottom: 5px;">
                        <span class="item-name">Article</span>
                        <span class="item-qty">Qte</span>
                        <span class="item-pu">PU</span>
                        <span class="item-price">Total</span>
                    </div>
                    ${itemsHtml}
                </div>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Sous-total HT:</span>
                        <span>${this.currentTotals.sous_total_ht.toFixed(2)} Fc</span>
                    </div>
                    <div class="receipt-total-row">
                        <span>TVA (20%):</span>
                        <span>${this.currentTotals.tva.toFixed(2)} Fc</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div class="vendeur-info">Vendeur: Administrateur</div>
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
                dgiInfoHtml += '<div style="font-size: 10px; color: #555; margin-top: 5px;">';
                if (dgiResponse.data.dateDGI) dgiInfoHtml += 'Date: ' + dgiResponse.data.dateDGI + ' | ';
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
                        <span class="item-pu">${item.prix.toFixed(2)}</span>
                        <span class="item-price">${(item.prix * item.quantite).toFixed(2)}</span>
                    </div>
                `;
            }

            // Afficher le recu complet
            $('#receipt-content').innerHTML = `
                <div class="receipt">
                    <div class="receipt-header">
                        <div class="store-name">SuperMarche Express</div>
                        <div class="store-info">
                            123 Rue Mohammed V, Casablanca<br>
                            Tel: +212 522 123 456<br>
                            ICE: 001234567890123
                        </div>
                    </div>

                    <div class="receipt-meta">
                        <span>${saleData.numero_facture}</span>
                        <span>${formattedDate}</span>
                    </div>

                    <div class="receipt-items">
                        <div class="receipt-item" style="font-weight: 700; border-bottom: 1px solid #333; margin-bottom: 5px;">
                            <span class="item-name">Article</span>
                            <span class="item-qty">Qte</span>
                            <span class="item-pu">PU</span>
                            <span class="item-price">Total</span>
                        </div>
                        ${itemsHtml}
                    </div>

                    <div class="receipt-totals">
                        <div class="receipt-total-row">
                            <span>Sous-total HT:</span>
                            <span>${this.currentTotals.sous_total_ht.toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row">
                            <span>TVA (20%):</span>
                            <span>${this.currentTotals.tva.toFixed(2)} Fc</span>
                        </div>
                        <div class="receipt-total-row grand-total">
                            <span>TOTAL TTC:</span>
                            <span>${this.currentTotals.total.toFixed(2)} Fc</span>
                        </div>
                    </div>

                    ${dgiInfoHtml}

                    <div class="receipt-footer">
                        <div class="vendeur-info">Vendeur: POS System</div>
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
    $('#product-id').value = product.id;
    $('#product-modal-title').textContent = 'Modifier le produit';
    $('#product-barcode').value = product.code_barres;
    $('#product-name').value = product.nom;
    $('#product-category').value = product.category_id;
    $('#product-price').value = product.prix;
    $('#product-stock').value = product.stock;
    $('#product-min-stock').value = product.stock_minimum;
    $('#product-image').value = '';
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
    document.getElementById('user-modal-title').textContent = 'Modifier l\'utilisateur';
    document.getElementById('user-id').value = id;
    document.getElementById('user-username').value = username;
    document.getElementById('user-fullname').value = fullname;
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('user-role').value = role;
    document.getElementById('user-actif').value = actif;
    document.getElementById('password-hint').style.display = 'inline';
    document.getElementById('user-modal').style.display = 'flex';
}

function closeUserModal() {
    document.getElementById('user-modal').style.display = 'none';
}

function saveUser(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.append('username', document.getElementById('user-username').value);
    formData.append('fullname', document.getElementById('user-fullname').value);
    const password = document.getElementById('user-password').value;
    const userId = document.getElementById('user-id').value;

    const url = userId ? APP_URL + '/api/update/user' : APP_URL + '/api/create/user';

    if (userId) {
        formData.append('id', userId);
        formData.append('nom_utilisateur', document.getElementById('user-username').value);
        formData.append('nom_complet', document.getElementById('user-fullname').value);
        formData.append('role', document.getElementById('user-role').value);
        formData.append('actif', document.getElementById('user-actif').value);
        if (password) {
            formData.append('mot_de_passe', password);
        }
    } else {
        formData.append('password', password);
        formData.append('role', document.getElementById('user-role').value);
        formData.append('actif', document.getElementById('user-actif').value);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(userId ? 'Utilisateur modifie !' : 'Utilisateur cree !');
                closeUserModal();
                window.location.reload();
            } else {
                alert(data.error || 'Erreur');
            }
        })
        .catch(() => alert('Erreur serveur'));

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
    const refreshBtn = $('#refresh-products');
    const tabs = $$('.category-tab');

    function filterProductsTable(search = '', activeCategory = 'all') {
        const rows = $$('#products-table tr[data-category]');
        let visibleCount = 0;

        rows.forEach(row => {
            const category = row.dataset.category.toLowerCase();
            const nom = row.cells[0].textContent.toLowerCase();
            const code = row.cells[1].textContent.toLowerCase();

            const matchesSearch = !search || nom.includes(search.toLowerCase()) || code.includes(search.toLowerCase());
            const matchesCategory = activeCategory === 'all' || category === activeCategory.toLowerCase();

            if (matchesSearch && matchesCategory) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0) {
            // Could add empty state row if wanted
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            tabs.forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            filterProductsTable(filterInput.value, e.target.dataset.category);
        });
    });

    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            filterProductsTable(e.target.value, document.querySelector('.category-tab.active').dataset.category);
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            window.location.reload();
        });
    }

    filterProductsTable();
}

document.addEventListener('DOMContentLoaded', () => {
    posCart.init();

    // Print Receipt Logic
    const printBtn = $('#print-receipt');
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            const content = $('#receipt-content').innerHTML;
            const printWindow = window.open('', '_blank', 'width=400,height=600');

            const styles = `
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap');
                    @page { margin: 0; }
                    body { margin: 0; padding: 10px; background: white; color: black; display: flex; justify-content: center; }
                    .receipt { font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 12px; line-height: 1.4; width: 80mm; padding: 5mm; }
                    .receipt-header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 15px; margin-bottom: 15px; }
                    .receipt-header .store-name { font-size: 18px; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
                    .receipt-header .store-info { font-size: 11px; }
                    .receipt-meta { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px dashed #000; }
                    .receipt-items { margin-bottom: 15px; }
                    .receipt-item { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 11px; }
                    .receipt-item .item-name { flex: 3; min-width: 0; white-space: normal; overflow-wrap: break-word; }
                    .receipt-item .item-qty { flex: 0.5; text-align: right; min-width: 30px; }
                    .receipt-item .item-pu { flex: 1; text-align: center; min-width: 50px; }
                    .receipt-item .item-price { flex: 1; text-align: right; font-weight: 600; min-width: 60px; }
                    .receipt-totals { margin-bottom: 15px; }
                    .receipt-total-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 11px; }
                    .receipt-total-row.grand-total { font-size: 16px; font-weight: 700; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 10px 0; margin-top: 10px; }
                    .receipt-footer { text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #000; font-size: 11px; }
                    .barcode { font-size: 18px; letter-spacing: 3px; font-weight: 700; margin: 15px 0; }
                    .qrcode-container { display: flex; justify-content: center; align-items: center; margin: 15px auto; width: 100%; }
                    .qrcode-container > div { padding: 5px; }
                    .qrcode-container svg, .qrcode-container img { max-width: 150px; height: auto; }
                </style>
            `;

            printWindow.document.write(`
                <html>
                    <head>
                        <title>Ticket de Caisse</title>
                        ${styles}
                    </head>
                    <body>
                        ${content}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();

            // Give the browser short time to parse the font and styles
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        });
    }
});
