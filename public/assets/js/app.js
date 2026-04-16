const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);

const formatCurrency = (amount) => amount.toFixed(2) + ' Fc';

const posCart = {
    items: [],
    taxRate: 20,

    init() {
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
        `).join('') || '<div class="empty-state">Aucun produit trouvé</div>';
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
            cartItems.innerHTML = '<div class="cart-empty">Le panier est vide</div>';
            $('#validate-sale').disabled = true;
        } else {
            cartItems.innerHTML = this.items.map(item => `
              <div class="cart-item">
                <div class="info">
                  <div class="name">${item.nom}</div>
                  <div class="price">${formatCurrency(item.prix)} / unité</div>
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
            $('#validate-sale').disabled = false;
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

    async validateSale() {
        if (this.items.length === 0) return;

        $('#validate-sale').disabled = true;

        try {
            const res = await fetch(APP_URL + '/api/vente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    articles: this.items,
                    sous_total_ht: this.currentTotals.sous_total_ht,
                    tva: this.currentTotals.tva,
                    total: this.currentTotals.total
                })
            });
            const data = await res.json();

            if (data.success) {
                // Show modal receipt
                $('#receipt-content').innerHTML = `
                <div style="font-family: monospace; font-size: 14px; max-width: 320px; margin: 0 auto; color: #000; padding: 20px; background: white;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <h2 style="font-size: 18px; margin: 0 0 5px 0;">SuperMarche Express</h2>
                        <div style="font-size: 12px; color: #333;">
                            123 Rue Mohammed V, Casablanca<br>
                            Tel: +212 522 123 456<br>
                            ICE: 001234567890123
                        </div>
                    </div>
                    
                    <div style="border-top: 1px dashed #666; margin: 10px 0;"></div>
                    
                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                        <span>${data.numero_facture}</span>
                        <span>${new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
    
    <div style="border-top: 1px dashed #666; margin: 10px 0;"></div>
    
    <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid #333;">
                <th style="text-align: left; padding-bottom: 5px;">Article</th>
                <th style="text-align: left; padding-bottom: 5px; padding-left: 10px; width: 60px;">PU</th>
                <th style="text-align: center; padding-bottom: 5px; width: 40px;">Qte</th>
                <th style="text-align: right; padding-bottom: 5px; width: 60px;">Total</th>
            </tr>
        </thead>
        <tbody>
            ${this.items.map(i => `
            <tr>
                <td style="padding: 5px 0;">${i.nom.length > 16 ? i.nom.substring(0, 14) + '...' : i.nom}</td>
                <td style="text-align: left; padding: 5px 0; padding-left: 10px;">${i.prix.toFixed(2)}</td>
                <td style="text-align: center; padding: 5px 0;">x${i.quantite}</td>
                <td style="text-align: right; padding: 5px 0;">${(i.prix * i.quantite).toFixed(2)} Fc</td>
            </tr>
            `).join('')}
        </tbody>
    </table>
    
    <div style="border-top: 1px dashed #666; margin: 10px 0;"></div>
    
    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
        <span>Sous-total HT:</span>
        <span>${this.currentTotals.sous_total_ht.toFixed(2)} Fc</span>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 12px;">
        <span>TVA (20%):</span>
        <span>${this.currentTotals.tva.toFixed(2)} Fc</span>
    </div>
    
    <div style="border-top: 2px solid #000; margin: 10px 0 5px 0;"></div>
    
    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px;">
        <span>TOTAL TTC:</span>
        <span>${this.currentTotals.total.toFixed(2)} Fc</span>
    </div>
    
    <div style="border-top: 2px solid #000; margin: 5px 0 10px 0;"></div>
    
    <div style="text-align: center; font-size: 12px; margin-top: 15px;">
        <p style="margin: 0 0 10px 0;">Vendeur: POS System</p>
        <div style="letter-spacing: 2px; font-size: 16px; margin-bottom: 10px;">
            ||||| ${data.numero_facture} |||||
        </div>
        <p style="font-weight: bold; margin: 0 0 5px 0;">Merci de votre visite!</p>
        <p style="margin: 0; color: #555;">Conservez ce ticket pour tout echange</p>
    </div>
</div>
                `;
                $('#receipt-modal').classList.add('active');

                this.clearCart();
                this.loadProducts(); // refresh stock
            } else {
                alert(data.error);
            }
        } catch (e) {
            alert('Erreur serveur');
        }

        $('#validate-sale').disabled = false;
    },

    async createProduct() {
        const body = {
            code_barres: $('#product-barcode').value,
            nom: $('#product-name').value,
            categorie: $('#product-category').value,
            prix: $('#product-price').value,
            stock: $('#product-stock').value,
            stock_minimum: $('#product-min-stock').value
        };
        try {
            const res = await fetch(APP_URL + '/api/produit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.success) {
                alert("Produit ajouté !");
                window.location.reload();
            } else {
                alert(data.error || "Erreur réseau");
            }
        } catch (e) {
            alert('Erreur serveur');
        }
    }
};

function openProductModal() {
    $('#product-modal').classList.add('active');
}

// Attach init
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
