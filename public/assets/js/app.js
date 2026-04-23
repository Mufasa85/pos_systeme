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
                width: 280,
                height: 280,
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

        let itemsHtml = '';
        for (let i = 0; i < this.items.length; i++) {
            const item = this.items[i];
            itemsHtml += `
                <div style="display:flex;justify-content:space-between;margin-bottom:3px;font-size:11px;padding:4px 0;border-bottom:1px dotted #ddd;">
                    <span style="flex:1;font-weight:500;">${item.nom}</span>
                    <span style="width:30px;text-align:center;color:#888;">x${item.quantite}</span>
                    <span style="width:70px;text-align:right;font-weight:600;">${formatCurrency(item.prix * item.quantite)}</span>
                </div>
            `;
        }

        const formattedDate = new Date().toLocaleString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        $('#preview-content').innerHTML = `
            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:12px;line-height:1.4;padding:20px;background:#fff;color:#1a1a1a;width:280px;border:1px solid #e0e0e0;">
                <div style="text-align:center;margin-bottom:16px;padding-bottom:16px;border-bottom:2px dashed #333;">
                    <div style="font-size:18px;font-weight:700;color:#0B5E88;margin-bottom:6px;">SUPER MARCHÉ</div>
                    <div style="font-size:10px;color:#666;line-height:1.5;">123 Avenue Mohammed V<br>Casablanca, Maroc<br>+212 522 123 456</div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:10px;color:#888;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #eee;">
                    <span style="font-weight:600;color:#333;">TICKET</span>
                    <span>${formattedDate}</span>
                </div>
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:9px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid #333;">
                        <span style="flex:1;">Article</span>
                        <span style="width:30px;text-align:center;">Qté</span>
                        <span style="width:70px;text-align:right;">Montant</span>
                    </div>
                    ${itemsHtml}
                </div>
                <div style="margin:12px 0;padding:12px 0;border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;background:#f8f9fa;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:11px;"><span style="color:#666;">Sous-total HT</span><span>${formatCurrency(this.currentTotals.sous_total_ht)}</span></div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:11px;"><span style="color:#666;">TVA (20%)</span><span>${formatCurrency(this.currentTotals.tva)}</span></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;padding:10px 0;border-top:2px solid #333;border-bottom:2px solid #333;background:#0B5E88;color:#fff;margin-bottom:16px;">
                    <span>TOTAL</span>
                    <span>${formatCurrency(this.currentTotals.total)}</span>
                </div>
                <div style="text-align:center;font-size:10px;color:#888;padding-top:12px;border-top:1px dashed #ccc;">
                    <div style="font-weight:600;font-size:12px;color:#333;margin-bottom:4px;">Merci de votre confiance!</div>
                    <div style="font-size:9px;color:#999;">Confirmez pour valider la vente</div>
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
                $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmer la vente';
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
                $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmer la vente';
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
                const displayName = item.nom.length > 16 ? item.nom.substring(0, 14) + '...' : item.nom;
                itemsHtml += '<tr><td style="padding: 5px 0;">' + displayName + '</td><td style="text-align: left; padding: 5px 0; padding-left: 10px;">' + item.prix.toFixed(2) + '</td><td style="text-align: center; padding: 5px 0;">x' + item.quantite + '</td><td style="text-align: right; padding: 5px 0;">' + (item.prix * item.quantite).toFixed(2) + ' Fc</td></tr>';
            }

            // Afficher le recu complet
            $('#receipt-content').innerHTML = '<div style="font-family: monospace; font-size: 14px; max-width: 320px; margin: 0 auto; color: #000; padding: 20px; background: white;"><div style="text-align: center; margin-bottom: 15px;"><h2 style="font-size: 18px; margin: 0 0 5px 0;">SuperMarche Express</h2><div style="font-size: 12px; color: #333;">123 Rue Mohammed V, Casablanca<br>Tel: +212 522 123 456<br>ICE: 001234567890123</div></div><div style="border-top: 1px dashed #666; margin: 10px 0;"></div><div style="display: flex; justify-content: space-between; font-size: 12px;"><span>' + saleData.numero_facture + '</span><span>' + formattedDate + '</span></div><div style="border-top: 1px dashed #666; margin: 10px 0;"></div><table style="width: 100%; font-size: 12px; border-collapse: collapse;"><thead><tr style="border-bottom: 1px solid #333;"><th style="text-align: left; padding-bottom: 5px;">Article</th><th style="text-align: left; padding-bottom: 5px; padding-left: 10px; width: 60px;">PU</th><th style="text-align: center; padding-bottom: 5px; width: 40px;">Qte</th><th style="text-align: right; padding-bottom: 5px; width: 60px;">Total</th></tr></thead><tbody>' + itemsHtml + '</tbody></table><div style="border-top: 1px dashed #666; margin: 10px 0;"></div><div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;"><span>Sous-total HT:</span><span>' + this.currentTotals.sous_total_ht.toFixed(2) + ' Fc</span></div><div style="display: flex; justify-content: space-between; font-size: 12px;"><span>TVA (20%):</span><span>' + this.currentTotals.tva.toFixed(2) + ' Fc</span></div><div style="border-top: 2px solid #000; margin: 10px 0 5px 0;"></div><div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px;"><span>TOTAL TTC:</span><span>' + this.currentTotals.total.toFixed(2) + ' Fc</span></div><div style="border-top: 2px solid #000; margin: 5px 0 10px 0;"></div>' + dgiInfoHtml + '<div style="text-align: center; font-size: 12px; margin-top: 10px;"><p style="margin: 0 0 10px 0;">Vendeur: POS System</p><div id="' + qrContainerId + '" style="display: flex; justify-content: center; margin: 10px auto; min-height: 200px;"></div><div style="letter-spacing: 2px; font-size: 16px; margin-bottom: 10px;">' + saleData.numero_facture + '</div><p style="font-weight: bold; margin: 0 0 5px 0;">Merci de votre visite!</p><p style="margin: 0; color: #555;">Conservez ce ticket pour tout echange</p></div></div>';

            // Generer le QR code
            this.generateDGIQRCode(qrCodeContent, qrContainerId);

            // Afficher le modal du ticket
            $('#receipt-modal').classList.add('active');

            // Reinitialiser le bouton
            $('#confirm-sale').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Confirmer la vente';

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
        fetch(APP_URL + '/api/produit/delete?id=' + id, {
            method: 'POST'
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
            printWindow.document.write('<html><head><title>Ticket de Caisse</title></head><body style="margin:0; padding:10px;">');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();

            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        });
    }
});