// ============================================
// POS SYSTEM - MAIN APPLICATION
// ============================================

// ============================================
// DATA STORE
// ============================================
const Store = {
  // Users
  users: [
    { id: 1, username: 'admin', password: 'admin123', fullName: 'Administrateur', role: 'admin', active: true },
    { id: 2, username: 'vendeur1', password: 'vendeur123', fullName: 'Mohammed Alami', role: 'vendeur', active: true },
    { id: 3, username: 'vendeur2', password: 'vendeur456', fullName: 'Fatima Benali', role: 'vendeur', active: true }
  ],

  // Categories
  categories: [
    { id: 1, name: 'Comestible', color: '#10b981' },
    { id: 2, name: 'Non Comestible', color: '#0B5E88' },
    { id: 3, name: 'Service', color: '#f59e0b' }
  ],

  // Products
  products: [
    { id: 1, barcode: '6111245001', name: 'Coca-Cola 1.5L', category: 'Comestible', price: 12.000, stock: 50, minStock: 10, image: 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=200&h=200&fit=crop' },
    { id: 2, barcode: '6111245002', name: 'Fanta Orange 1.5L', category: 'Comestible', price: 11.000, stock: 45, minStock: 10, image: 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=200&h=200&fit=crop' },
    { id: 3, barcode: '6111245003', name: 'Eau Sidi Ali 1.5L', category: 'Comestible', price: 5.000, stock: 100, minStock: 20, image: 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=200&h=200&fit=crop' },
    { id: 4, barcode: '6111245004', name: 'Jus Orange 1L', category: 'Comestible', price: 15.000, stock: 30, minStock: 10, image: 'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=200&h=200&fit=crop' },
    { id: 5, barcode: '6111245005', name: 'Lait Frais 1L', category: 'Comestible', price: 8.500, stock: 60, minStock: 15, image: 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=200&h=200&fit=crop' },
    { id: 6, barcode: '6111245006', name: 'Pain de Mie', category: 'Comestible', price: 12.000, stock: 25, minStock: 10, image: 'https://images.unsplash.com/photo-1598373182133-52452f7691ef?w=200&h=200&fit=crop' },
    { id: 7, barcode: '6111245007', name: 'Fromage Portion', category: 'Comestible', price: 25.000, stock: 40, minStock: 10, image: 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=200&h=200&fit=crop' },
    { id: 8, barcode: '6111245008', name: 'Oeufs (Pack 6)', category: 'Comestible', price: 15.000, stock: 35, minStock: 10, image: 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=200&h=200&fit=crop' },
    { id: 9, barcode: '6111245009', name: 'Riz 1kg', category: 'Comestible', price: 18.000, stock: 55, minStock: 15, image: 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=200&h=200&fit=crop' },
    { id: 10, barcode: '6111245010', name: 'Huile Vegetale 1L', category: 'Comestible', price: 22.000, stock: 40, minStock: 10, image: 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=200&h=200&fit=crop' },
    { id: 11, barcode: '6111245011', name: 'Sucre 1kg', category: 'Comestible', price: 8.000, stock: 70, minStock: 20, image: 'https://images.unsplash.com/photo-1558642452-9d2a7deb7f62?w=200&h=200&fit=crop' },
    { id: 12, barcode: '6111245012', name: 'Cafe Moulu 250g', category: 'Comestible', price: 35.000, stock: 25, minStock: 8, image: 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=200&h=200&fit=crop' },
    { id: 13, barcode: '6111245013', name: 'Savon de Toilette', category: 'Non Comestible', price: 18.000, stock: 30, minStock: 10, image: 'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=200&h=200&fit=crop' },
    { id: 14, barcode: '6111245014', name: 'Shampoing 400ml', category: 'Non Comestible', price: 45.000, stock: 20, minStock: 8, image: 'https://images.unsplash.com/photo-1631729371254-42c2892f0e6e?w=200&h=200&fit=crop' },
    { id: 15, barcode: '6111245015', name: 'Dentifrice', category: 'Non Comestible', price: 22.00, stock: 35, minStock: 10, image: 'https://images.unsplash.com/photo-1559013368-8c3c5619b92a?w=200&h=200&fit=crop' },
    { id: 16, barcode: '6111245016', name: 'Papier Toilette x4', category: 'Non Comestible', price: 25.000, stock: 40, minStock: 15, image: 'https://images.unsplash.com/photo-1584556812952-905ffd0c611a?w=200&h=200&fit=crop' },
    { id: 17, barcode: '6111245017', name: 'Eau de Javel 1L', category: 'Non Comestible', price: 12.000, stock: 5, minStock: 10, image: 'https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=200&h=200&fit=crop' },
    { id: 18, barcode: '6111245018', name: 'Lessive Poudre 3kg', category: 'Non Comestible', price: 65.000, stock: 20, minStock: 8, image: 'https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=200&h=200&fit=crop' },
    { id: 19, barcode: '6111245019', name: 'Liquide Vaisselle', category: 'Non Comestible', price: 18.000, stock: 8, minStock: 10, image: 'https://images.unsplash.com/photo-1622560480654-d96214fdc887?w=200&h=200&fit=crop' },
    { id: 20, barcode: '6111245020', name: 'Sacs Poubelle x20', category: 'Non Comestible', price: 15.000, stock: 25, minStock: 10, image: 'https://images.unsplash.com/photo-1610141805296-1fc723fce8f9?w=200&h=200&fit=crop' }
  ],

  // Sales
  sales: [],

  // Settings
  settings: {
    storeName: 'SuperMarche Express',
    storeAddress: '123 Rue Mohammed V, Casablanca',
    storePhone: '+212 522 123 456',
    storeICE: '001234567890123',
    taxRate: 20
  },

  // Current user
  currentUser: null,

  // Cart
  cart: [],

  // Invoice counter
  invoiceCounter: 1000,

  // Current Sale for modal
  currentSale: null,

  // Save to localStorage
  save() {
    localStorage.setItem('pos_users', JSON.stringify(this.users));
    localStorage.setItem('pos_products', JSON.stringify(this.products));
    localStorage.setItem('pos_sales', JSON.stringify(this.sales));
    localStorage.setItem('pos_settings', JSON.stringify(this.settings));
    localStorage.setItem('pos_categories', JSON.stringify(this.categories));
    localStorage.setItem('pos_invoice_counter', this.invoiceCounter.toString());
  },

  // Load from localStorage
  load() {
    const users = localStorage.getItem('pos_users');
    const products = localStorage.getItem('pos_products');
    const sales = localStorage.getItem('pos_sales');
    const settings = localStorage.getItem('pos_settings');
    const categories = localStorage.getItem('pos_categories');
    const counter = localStorage.getItem('pos_invoice_counter');

    if (users) this.users = JSON.parse(users);
    if (products) this.products = JSON.parse(products);
    if (sales) this.sales = JSON.parse(sales);
    if (settings) this.settings = JSON.parse(settings);
    if (categories) this.categories = JSON.parse(categories);
    if (counter) this.invoiceCounter = parseInt(counter);
  }
};

// ============================================
// UTILITY FUNCTIONS
// ============================================
function formatCurrency(amount) {
  return amount.toFixed(2) + ' Fc';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatDateShort(date) {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

function generateInvoiceNumber() {
  Store.invoiceCounter++;
  Store.save();
  return 'FAC-' + Store.invoiceCounter.toString().padStart(6, '0');
}

// ============================================
// DOM ELEMENTS
// ============================================
const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => document.querySelectorAll(selector);

// ============================================
// AUTHENTICATION
// ============================================
function login(username, password) {
  const user = Store.users.find(u =>
    u.username === username &&
    u.password === password &&
    u.active
  );

  if (user) {
    Store.currentUser = user;
    sessionStorage.setItem('pos_current_user', JSON.stringify(user));
    return true;
  }
  return false;
}

function logout() {
  Store.currentUser = null;
  Store.cart = [];
  sessionStorage.removeItem('pos_current_user');
  showLogin();
}

function checkAuth() {
  const saved = sessionStorage.getItem('pos_current_user');
  if (saved) {
    Store.currentUser = JSON.parse(saved);
    return true;
  }
  return false;
}

// ============================================
// UI FUNCTIONS
// ============================================
function showLogin() {
  $('#login-page').classList.remove('hidden');
  $('#main-app').classList.add('hidden');
}

function showApp() {
  $('#login-page').classList.add('hidden');
  $('#main-app').classList.remove('hidden');
  updateUserInfo();
  updateAdminElements();
  navigateTo('dashboard');
}

function updateUserInfo() {
  const user = Store.currentUser;
  if (!user) return;

  $('#user-name').textContent = user.fullName;
  $('#user-role-display').textContent = user.role === 'admin' ? 'Administrateur' : 'Vendeur';
  $('#user-avatar').textContent = user.fullName.charAt(0).toUpperCase();
  $('#mobile-user-info').textContent = user.fullName;
}

function updateAdminElements() {
  const isAdmin = Store.currentUser?.role === 'admin';
  $$('.admin-only').forEach(el => {
    el.style.display = isAdmin ? '' : 'none';
  });
}

function navigateTo(page) {
  // Update nav
  $$('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });

  // Update pages
  $$('.page').forEach(p => {
    p.classList.toggle('active', p.id === 'page-' + page);
  });

  // Close mobile sidebar
  $('#sidebar').classList.remove('open');
  $('#sidebar-overlay').classList.remove('active');

  // Load page data
  switch (page) {
    case 'dashboard':
      loadDashboard();
      break;
    case 'caisse':
      loadCaisse();
      break;
    case 'products':
      loadProducts();
      break;
    case 'users':
      loadUsers();
      break;
    case 'history':
      loadHistory();
      break;
    case 'settings':
      loadSettings();
      break;
    case 'categories':
      loadCategories();
      break;
  }
}

// ============================================
// DASHBOARD
// ============================================
function loadDashboard() {
  // Update date
  $('#current-date').textContent = formatDateShort(new Date());

  // Calculate stats
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const weekAgo = new Date(today);
  weekAgo.setDate(weekAgo.getDate() - 7);

  const todaySales = Store.sales
    .filter(s => new Date(s.date) >= today)
    .reduce((sum, s) => sum + s.total, 0);

  const weekSales = Store.sales
    .filter(s => new Date(s.date) >= weekAgo)
    .reduce((sum, s) => sum + s.total, 0);

  const lowStockProducts = Store.products.filter(p => p.stock <= p.minStock);

  $('#stat-today').textContent = formatCurrency(todaySales);
  $('#stat-week').textContent = formatCurrency(weekSales);
  $('#stat-products').textContent = Store.products.length;
  $('#stat-low-stock').textContent = lowStockProducts.length;

  // Recent sales
  const recentSales = Store.sales.slice(-5).reverse();
  const recentSalesHtml = recentSales.length ? recentSales.map(sale => `
    <div class="recent-item">
      <div>
        <strong>${sale.invoiceNumber}</strong>
        <span style="margin-left: 0.5rem; color: var(--muted);">${formatCurrency(sale.total)}</span>
      </div>
      <span class="time">${formatDate(sale.date)}</span>
    </div>
  `).join('') : '<div class="empty-state">Aucune vente recente</div>';

  $('#recent-sales').innerHTML = recentSalesHtml;

  // Stock alerts
  const alertsHtml = lowStockProducts.length ? lowStockProducts.map(p => `
    <div class="alert-item ${p.stock === 0 ? 'critical' : ''}">
      <span>${p.name}</span>
      <span><strong>${p.stock}</strong> / ${p.minStock}</span>
    </div>
  `).join('') : '<div class="empty-state">Aucune alerte de stock</div>';

  $('#stock-alerts').innerHTML = alertsHtml;
}

// ============================================
// CAISSE (POS)
// ============================================
let currentCategory = 'all';

function renderCategoryTabs() {
  const container = $('#caisse-category-tabs');
  if (!container) return;

  const tabs = ['<button class="category-tab active" data-category="all">Tous</button>'];
  Store.categories.forEach(cat => {
    tabs.push(`<button class="category-tab" data-category="${cat.name}">${cat.name}</button>`);
  });
  container.innerHTML = tabs.join('');

  // Event delegation on the container
  container.addEventListener('click', (e) => {
    const tab = e.target.closest('.category-tab');
    if (!tab) return;
    container.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    currentCategory = tab.dataset.category;
    renderProducts();
  });
}

function loadCaisse() {
  currentCategory = 'all';
  renderCategoryTabs();
  renderProducts();
  renderCart();
}

function renderProducts() {
  const search = $('#product-search').value.toLowerCase();
  const filtered = Store.products.filter(p => {
    const matchSearch = p.name.toLowerCase().includes(search) ||
      p.barcode.includes(search);
    const matchCategory = currentCategory === 'all' || p.category === currentCategory;
    return matchSearch && matchCategory;
  });

  const html = filtered.map(p => `
    <div class="product-card ${p.stock === 0 ? 'out-of-stock' : ''}" 
         onclick="addToCart(${p.id})" 
         ${p.stock === 0 ? 'title="Rupture de stock"' : ''}>
      <div class="product-image">
        <img src="${p.image}" alt="${p.name}" onerror="this.style.display='none'">
      </div>
      <div class="name">${p.name}</div>
      <div class="price">${formatCurrency(p.price)}</div>
      <div class="barcode-display">${p.barcode}</div>
    </div>
  `).join('');

  $('#products-grid').innerHTML = html || '<div class="empty-state">Aucun produit trouve</div>';
}

function addToCart(productId) {
  const product = Store.products.find(p => p.id === productId);
  if (!product || product.stock === 0) return;

  const existing = Store.cart.find(item => item.productId === productId);

  if (existing) {
    if (existing.quantity < product.stock) {
      existing.quantity++;
    }
  } else {
    Store.cart.push({
      productId: product.id,
      name: product.name,
      price: product.price,
      quantity: 1
    });
  }

  renderCart();
}

function updateCartQuantity(productId, delta) {
  const item = Store.cart.find(i => i.productId === productId);
  const product = Store.products.find(p => p.id === productId);

  if (!item || !product) return;

  item.quantity += delta;

  if (item.quantity <= 0) {
    Store.cart = Store.cart.filter(i => i.productId !== productId);
  } else if (item.quantity > product.stock) {
    item.quantity = product.stock;
  }

  renderCart();
}

function removeFromCart(productId) {
  Store.cart = Store.cart.filter(i => i.productId !== productId);
  renderCart();
}

function clearCart() {
  Store.cart = [];
  renderCart();
}

function renderCart() {
  const cartItems = $('#cart-items');

  if (Store.cart.length === 0) {
    cartItems.innerHTML = `
      <div class="cart-empty">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="21" r="1"></circle>
          <circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <p>Le panier est vide</p>
      </div>
    `;
    $('#validate-sale').disabled = true;
  } else {
    const html = Store.cart.map(item => `
      <div class="cart-item">
        <div class="info">
          <div class="name">${item.name}</div>
          <div class="price">${formatCurrency(item.price)} / unite</div>
        </div>
        <div class="quantity-controls">
          <button onclick="updateCartQuantity(${item.productId}, -1)">-</button>
          <span>${item.quantity}</span>
          <button onclick="updateCartQuantity(${item.productId}, 1)">+</button>
        </div>
        <div class="item-total">${formatCurrency(item.price * item.quantity)}</div>
        <button class="remove-item" onclick="removeFromCart(${item.productId})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    `).join('');

    cartItems.innerHTML = html;
    $('#validate-sale').disabled = false;
  }

  // Update totals
  const subtotal = Store.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const taxRate = Store.settings.taxRate / 100;
  const subtotalHT = subtotal / (1 + taxRate);
  const tax = subtotal - subtotalHT;

  $('#subtotal').textContent = formatCurrency(subtotalHT);
  $('#tax').textContent = formatCurrency(tax);
  $('#total').textContent = formatCurrency(subtotal);
}

function validateSale() {
  if (Store.cart.length === 0) return;

  const subtotal = Store.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const taxRate = Store.settings.taxRate / 100;
  const subtotalHT = subtotal / (1 + taxRate);
  const tax = subtotal - subtotalHT;

  const sale = {
    id: Date.now(),
    invoiceNumber: generateInvoiceNumber(),
    date: new Date().toISOString(),
    seller: Store.currentUser.fullName,
    sellerId: Store.currentUser.id,
    items: [...Store.cart],
    subtotalHT: subtotalHT,
    tax: tax,
    total: subtotal
  };

  // Update stock
  Store.cart.forEach(item => {
    const product = Store.products.find(p => p.id === item.productId);
    if (product) {
      product.stock -= item.quantity;
    }
  });

  // Save sale
  Store.sales.push(sale);
  Store.save();

  // Show receipt
  showReceipt(sale);

  // Clear cart
  Store.cart = [];
  renderCart();
  renderProducts();
}

function showReceipt(sale) {
  Store.currentSale = sale;
  const settings = Store.settings;

  // Reset modal state
  $('#validate-receipt').classList.remove('hidden');
  $('#validate-receipt').disabled = false;
  $('#validate-receipt').innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    Valider la facture
  `;
  $('#print-receipt').classList.add('hidden');

  const itemsHtml = sale.items.map(item => `
    <div class="receipt-item">
      <span class="item-name">${item.name}</span>
      <span class="item-qty">x${item.quantity}</span>
      <span class="item-pu">${formatCurrency(item.price)}</span>
      <span class="item-price">${formatCurrency(item.price * item.quantity)}</span>
    </div>
  `).join('');

  const receiptHtml = `
    <div class="receipt-header">
      <div class="store-name">${settings.storeName}</div>
      <div class="store-info">
        ${settings.storeAddress}<br>
        Tel: ${settings.storePhone}<br>
        ICE: ${settings.storeICE}
      </div>
    </div>
    
    <div class="receipt-meta">
      <span>${sale.invoiceNumber}</span>
      <span>${formatDate(sale.date)}</span>
    </div>
    
    <div class="receipt-items">
      <div class="receipt-item" style="font-weight: 600; border-bottom: 1px solid #ccc; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
        <span class="item-name">Article</span>
        <span class="item-qty">Qte</span>
        <span class="item-pu">PU</span>
        <span class="item-price">Total</span>
      </div>
      ${itemsHtml}
    </div>
    
    <div class="receipt-divider"></div>
    
    <div class="receipt-totals">
      <div class="receipt-total-row">
        <span>Sous-total HT:</span>
        <span>${formatCurrency(sale.subtotalHT)}</span>
      </div>
      <div class="receipt-total-row">
        <span>TVA (${settings.taxRate}%):</span>
        <span>${formatCurrency(sale.tax)}</span>
      </div>
      <div class="receipt-total-row grand-total">
        <span>TOTAL TTC:</span>
        <span>${formatCurrency(sale.total)}</span>
      </div>
    </div>
    
    <div class="receipt-footer">
      <div>Vendeur: ${sale.seller}</div>
      <div id="qrcode-container" class="qrcode-container"></div>
      <div id="validation-status" style="margin: 0.5rem 0; font-weight: 600; color: var(--primary);"></div>
      <div class="barcode">||| ${sale.invoiceNumber} |||</div>
      <div class="thank-you">Merci de votre visite!</div>
      <div style="margin-top: 0.5rem;">Conservez ce ticket pour tout echange</div>
    </div>
  `;

  $('#receipt-content').innerHTML = receiptHtml;
  $('#receipt-modal').classList.add('active');
}

async function validateReceipt() {
  const sale = Store.currentSale;
  if (!sale) return;

  const btn = $('#validate-receipt');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner spinner-small"></div> Validation...';

  // Simulated API Call
  try {
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Simulate API Response Data
    const apiResponse = {
      status: 'success',
      transactionId: 'TXN-' + Math.random().toString(36).substr(2, 9).toUpperCase(),
      validationDate: new Date().toISOString()
    };

    // Update UI
    $('#validation-status').textContent = 'Validé par l\'API le ' + formatDate(apiResponse.validationDate);

    // Generate QR Code
    const baseUrl = window.location.href.substring(0, window.location.href.lastIndexOf('/'));
    const dataStr = JSON.stringify({
      i: sale.invoiceNumber,
      d: sale.date,
      it: sale.items.map(item => ({ n: item.name, q: item.quantity, p: item.price })),
      t: sale.total,
      s: sale.seller,
      st: {
        n: Store.settings.storeName,
        a: Store.settings.storeAddress,
        p: Store.settings.storePhone,
        i: Store.settings.storeICE,
        t: Store.settings.taxRate
      }
    });

    // Simple Base64 encoding (not "hashed" as requested)
    const encodedData = btoa(unescape(encodeURIComponent(dataStr)));
    const receiptUrl = `${baseUrl}/facture.html?hash=${encodedData}`;

    const qrCode = new QRCodeStyling({
      width: 300,
      height: 300,
      type: "svg",
      data: receiptUrl,
      margin: 10,
      qrOptions: {
        typeNumber: 0,
        mode: 'Byte',
        errorCorrectionLevel: 'L'
      },
      dotsOptions: {
        color: "#000000",
        type: "rounded"
      },
      cornersSquareOptions: {
        color: "#000000",
        type: "extra-rounded"
      },
      cornersDotOptions: {
        color: "#000000",
        type: "dot"
      },
      backgroundOptions: {
        color: "#ffffff",
      }
    });

    const container = document.getElementById("qrcode-container");
    container.innerHTML = "";
    qrCode.append(container);

    // Toggle buttons
    btn.classList.add('hidden');
    $('#print-receipt').classList.remove('hidden');

  } catch (error) {
    alert('Erreur lors de la validation: ' + error.message);
    btn.disabled = false;
    btn.textContent = 'Réessayer la validation';
  }
}

function printReceipt() {
  window.print();
}

// ============================================
// PRODUCTS MANAGEMENT
// ============================================
let editingProductId = null;

function loadProducts() {
  // Populate category filter dynamically from Store.categories
  const filterSelect = $('#category-filter');
  if (filterSelect) {
    const current = filterSelect.value;
    filterSelect.innerHTML = '<option value="all">Toutes les catégories</option>' +
      Store.categories.map(cat => `<option value="${cat.name}">${cat.name}</option>`).join('');
    if (current && current !== 'all') filterSelect.value = current;
  }
  renderProductsTable();
}

function renderProductsTable() {
  const search = $('#products-filter').value.toLowerCase();
  const category = $('#category-filter').value;

  const filtered = Store.products.filter(p => {
    const matchSearch = p.name.toLowerCase().includes(search) ||
      p.barcode.includes(search);
    const matchCategory = category === 'all' || p.category === category;
    return matchSearch && matchCategory;
  });

  const isAdmin = Store.currentUser?.role === 'admin';

  const html = filtered.map(p => `
    <tr>
      <td>
        <div class="table-product-image">
          <img src="${p.image}" alt="${p.name}" onerror="this.style.display='none'">
        </div>
      </td>
      <td><strong>${p.name}</strong></td>
      <td><code class="barcode-code">${p.barcode}</code></td>
      <td><span class="badge badge-primary">${p.category}</span></td>
      <td><strong>${formatCurrency(p.price)}</strong></td>
      ${isAdmin ? `
        <td class="actions">
          <button class="btn btn-ghost btn-small" onclick="editProduct(${p.id})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
          </button>
          <button class="btn btn-ghost btn-small" onclick="deleteProduct(${p.id})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
          </button>
        </td>
      ` : ''}
    </tr>
  `).join('');

  $('#products-table').innerHTML = html || '<tr><td colspan="6" class="empty-state">Aucun produit trouve</td></tr>';
}

function openProductModal(product = null) {
  editingProductId = product?.id || null;

  $('#product-modal-title').textContent = product ? 'Modifier le produit' : 'Ajouter un produit';
  $('#product-barcode').value = product?.barcode || '';
  $('#product-name').value = product?.name || '';

  // Populate category select dynamically
  const catSelect = $('#product-category');
  catSelect.innerHTML = '<option value="">Sélectionner une catégorie...</option>' +
    Store.categories.map(cat => `<option value="${cat.name}">${cat.name}</option>`).join('');
  catSelect.value = product?.category || '';

  $('#product-price').value = product?.price || '';
  $('#product-stock').value = product?.stock || '';
  $('#product-min-stock').value = product?.minStock || 10;

  $('#product-modal').classList.add('active');
}

function editProduct(id) {
  const product = Store.products.find(p => p.id === id);
  if (product) openProductModal(product);
}

function saveProduct(e) {
  e.preventDefault();

  const data = {
    barcode: $('#product-barcode').value.trim(),
    name: $('#product-name').value.trim(),
    category: $('#product-category').value,
    price: parseFloat($('#product-price').value),
    stock: parseInt($('#product-stock').value),
    minStock: parseInt($('#product-min-stock').value)
  };

  if (editingProductId) {
    const product = Store.products.find(p => p.id === editingProductId);
    if (product) {
      Object.assign(product, data);
    }
  } else {
    data.id = Date.now();
    Store.products.push(data);
  }

  Store.save();
  closeModal('product-modal');
  renderProductsTable();
}

function deleteProduct(id) {
  if (confirm('Etes-vous sur de vouloir supprimer ce produit?')) {
    Store.products = Store.products.filter(p => p.id !== id);
    Store.save();
    renderProductsTable();
  }
}

// ============================================
// USERS MANAGEMENT
// ============================================
let editingUserId = null;

function loadUsers() {
  renderUsersTable();
  populateSellerFilter();
}

function renderUsersTable() {
  const html = Store.users.map(u => `
    <tr>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.875rem; background: var(--primary);">
            ${u.fullName.charAt(0)}
          </div>
          <div>
            <div style="font-weight: 500;">${u.fullName}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">@${u.username}</div>
          </div>
        </div>
      </td>
      <td><span class="badge ${u.role === 'admin' ? 'badge-primary' : 'badge-success'}">${u.role === 'admin' ? 'Admin' : 'Vendeur'}</span></td>
      <td><span class="badge ${u.active ? 'badge-success' : 'badge-danger'}">${u.active ? 'Actif' : 'Inactif'}</span></td>
      <td class="actions">
        <button class="btn btn-ghost btn-small" onclick="editUser(${u.id})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
        </button>
        <button class="btn btn-ghost btn-small" onclick="toggleUserStatus(${u.id})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            ${u.active ?
      '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>' :
      '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>'
    }
          </svg>
        </button>
        ${u.id !== Store.currentUser?.id ? `
          <button class="btn btn-ghost btn-small" onclick="deleteUser(${u.id})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
          </button>
        ` : ''}
      </td>
    </tr>
  `).join('');

  $('#users-table').innerHTML = html;
}

function openUserModal(user = null) {
  editingUserId = user?.id || null;

  $('#user-modal-title').textContent = user ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur';
  $('#user-username').value = user?.username || '';
  $('#user-password').value = '';
  $('#user-fullname').value = user?.fullName || '';
  $('#user-role').value = user?.role || 'vendeur';

  $('#password-hint').style.display = user ? 'block' : 'none';
  $('#user-password').required = !user;

  $('#user-modal').classList.add('active');
}

function editUser(id) {
  const user = Store.users.find(u => u.id === id);
  if (user) openUserModal(user);
}

function saveUser(e) {
  e.preventDefault();

  const data = {
    username: $('#user-username').value.trim(),
    fullName: $('#user-fullname').value.trim(),
    role: $('#user-role').value
  };

  const password = $('#user-password').value;

  if (editingUserId) {
    const user = Store.users.find(u => u.id === editingUserId);
    if (user) {
      Object.assign(user, data);
      if (password) user.password = password;
    }
  } else {
    if (!password) {
      alert('Le mot de passe est requis pour un nouvel utilisateur');
      return;
    }
    data.id = Date.now();
    data.password = password;
    data.active = true;
    Store.users.push(data);
  }

  Store.save();
  closeModal('user-modal');
  renderUsersTable();
}

function toggleUserStatus(id) {
  const user = Store.users.find(u => u.id === id);
  if (user && user.id !== Store.currentUser?.id) {
    user.active = !user.active;
    Store.save();
    renderUsersTable();
  }
}

function deleteUser(id) {
  if (id === Store.currentUser?.id) {
    alert('Vous ne pouvez pas supprimer votre propre compte');
    return;
  }

  if (confirm('Etes-vous sur de vouloir supprimer cet utilisateur?')) {
    Store.users = Store.users.filter(u => u.id !== id);
    Store.save();
    renderUsersTable();
  }
}

// ============================================
// HISTORY
// ============================================
function loadHistory() {
  populateSellerFilter();
  renderHistoryTable();
}

function populateSellerFilter() {
  const sellers = [...new Set(Store.sales.map(s => s.seller))];
  const options = ['<option value="all">Tous les vendeurs</option>'];
  sellers.forEach(seller => {
    options.push(`<option value="${seller}">${seller}</option>`);
  });
  $('#seller-filter').innerHTML = options.join('');
}

function renderHistoryTable() {
  const dateFilter = $('#date-filter').value;
  const sellerFilter = $('#seller-filter').value;

  let filtered = [...Store.sales].reverse();

  if (dateFilter) {
    const filterDate = new Date(dateFilter);
    filterDate.setHours(0, 0, 0, 0);
    const nextDay = new Date(filterDate);
    nextDay.setDate(nextDay.getDate() + 1);

    filtered = filtered.filter(s => {
      const saleDate = new Date(s.date);
      return saleDate >= filterDate && saleDate < nextDay;
    });
  }

  if (sellerFilter !== 'all') {
    filtered = filtered.filter(s => s.seller === sellerFilter);
  }

  const html = filtered.map(sale => `
    <tr>
      <td><strong>${sale.invoiceNumber}</strong></td>
      <td>${formatDate(sale.date)}</td>
      <td>${sale.seller}</td>
      <td>${sale.items.length} articles</td>
      <td><strong>${formatCurrency(sale.total)}</strong></td>
      <td class="actions">
        <button class="btn btn-ghost btn-small" onclick="viewSaleReceipt(${sale.id})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </button>
      </td>
    </tr>
  `).join('');

  $('#history-table').innerHTML = html || '<tr><td colspan="6" class="empty-state">Aucune vente trouvee</td></tr>';
}

function viewSaleReceipt(saleId) {
  const sale = Store.sales.find(s => s.id === saleId);
  if (sale) showReceipt(sale);
}

// ============================================
// CATEGORIES MANAGEMENT
// ============================================
let editingCategoryId = null;

function loadCategories() {
  renderCategoriesTable();
}

function renderCategoriesTable() {
  const search = ($('#categories-filter')?.value || '').toLowerCase();

  const filtered = Store.categories.filter(cat =>
    cat.name.toLowerCase().includes(search)
  );

  const html = filtered.map(cat => {
    const productCount = Store.products.filter(p => p.category === cat.name).length;
    return `
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${cat.color};flex-shrink:0;box-shadow:0 0 0 2px ${cat.color}33;"></span>
            <strong>${cat.name}</strong>
          </div>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="display:inline-block;width:30px;height:24px;border-radius:6px;background:${cat.color};border:1px solid var(--border);flex-shrink:0;"></span>
            <code style="font-size:0.8rem;color:var(--muted);">${cat.color}</code>
          </div>
        </td>
        <td>
          <span class="badge badge-primary">${productCount} produit${productCount !== 1 ? 's' : ''}</span>
        </td>
        <td>
          <div class="actions">
            <button class="btn btn-ghost btn-small" onclick="editCategory(${cat.id})" title="Modifier">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>
            <button class="btn btn-ghost btn-small" onclick="deleteCategory(${cat.id})" title="Supprimer"
              style="color:var(--danger);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');

  $('#categories-table').innerHTML = html ||
    '<tr><td colspan="4" class="empty-state">Aucune catégorie trouvée</td></tr>';
}

function openCategoryModal(category = null) {
  editingCategoryId = category?.id || null;
  $('#category-modal-title').textContent = category ? 'Modifier la catégorie' : 'Ajouter une catégorie';
  $('#category-name').value = category?.name || '';
  const color = category?.color || '#0B5E88';
  $('#category-color').value = color;
  $('#category-color-hex').textContent = color;
  $('#category-modal').classList.add('active');
}

function editCategory(id) {
  const cat = Store.categories.find(c => c.id === id);
  if (cat) openCategoryModal(cat);
}

function saveCategoryFn(e) {
  e.preventDefault();
  const name = $('#category-name').value.trim();
  const color = $('#category-color').value;
  if (!name) return;

  if (editingCategoryId) {
    const cat = Store.categories.find(c => c.id === editingCategoryId);
    if (cat) {
      const oldName = cat.name;
      cat.name = name;
      cat.color = color;
      // Update products that belonged to the old category name
      Store.products.forEach(p => { if (p.category === oldName) p.category = name; });
    }
  } else {
    Store.categories.push({ id: Date.now(), name, color });
  }

  Store.save();
  closeModal('category-modal');
  renderCategoriesTable();
}

function deleteCategory(id) {
  const cat = Store.categories.find(c => c.id === id);
  if (!cat) return;

  const productCount = Store.products.filter(p => p.category === cat.name).length;
  const msg = productCount > 0
    ? `Cette catégorie contient ${productCount} produit(s). Voulez-vous quand même la supprimer ?`
    : 'Êtes-vous sûr de vouloir supprimer cette catégorie ?';

  if (confirm(msg)) {
    Store.categories = Store.categories.filter(c => c.id !== id);
    Store.save();
    renderCategoriesTable();
  }
}

// ============================================
// SETTINGS
// ============================================
function loadSettings() {
  const s = Store.settings;
  $('#store-name').value = s.storeName;
  $('#store-address').value = s.storeAddress;
  $('#store-phone').value = s.storePhone;
  $('#store-ice').value = s.storeICE;
  $('#tax-rate').value = s.taxRate;
}

function saveStoreSettings(e) {
  e.preventDefault();
  Store.settings.storeName = $('#store-name').value;
  Store.settings.storeAddress = $('#store-address').value;
  Store.settings.storePhone = $('#store-phone').value;
  Store.settings.storeICE = $('#store-ice').value;
  Store.save();
  alert('Parametres enregistres');
}

function saveTaxSettings(e) {
  e.preventDefault();
  Store.settings.taxRate = parseInt($('#tax-rate').value);
  Store.save();
  alert('Taux TVA enregistre');
}

// ============================================
// MODAL FUNCTIONS
// ============================================
function closeModal(modalId) {
  $('#' + modalId).classList.remove('active');
}

// ============================================
// EVENT LISTENERS
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  // Load data
  Store.load();

  // Check auth
  if (checkAuth()) {
    showApp();
  } else {
    showLogin();
  }

  // Login form
  $('#login-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const username = $('#username').value;
    const password = $('#password').value;

    if (login(username, password)) {
      showApp();
      $('#login-error').textContent = '';
    } else {
      $('#login-error').textContent = 'Identifiants incorrects ou compte desactive';
    }
  });

  // Logout
  $('#logout-btn').addEventListener('click', logout);

  // Navigation
  $$('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      const page = item.dataset.page;
      if (page) navigateTo(page);
    });
  });

  // Mobile menu
  $('#menu-toggle').addEventListener('click', () => {
    $('#sidebar').classList.add('open');
    $('#sidebar-overlay').classList.add('active');
  });

  $('#close-sidebar').addEventListener('click', () => {
    $('#sidebar').classList.remove('open');
    $('#sidebar-overlay').classList.remove('active');
  });

  $('#sidebar-overlay').addEventListener('click', () => {
    $('#sidebar').classList.remove('open');
    $('#sidebar-overlay').classList.remove('active');
  });

  // Caisse
  $('#product-search').addEventListener('input', renderProducts);
  $('#clear-cart').addEventListener('click', clearCart);
  $('#validate-sale').addEventListener('click', validateSale);

  // Receipt modal
  $('#close-receipt').addEventListener('click', () => closeModal('receipt-modal'));
  $('#validate-receipt').addEventListener('click', validateReceipt);
  $('#print-receipt').addEventListener('click', printReceipt);

  // Products
  $('#add-product-btn').addEventListener('click', () => openProductModal());
  $('#product-form').addEventListener('submit', saveProduct);
  $('#products-filter').addEventListener('input', renderProductsTable);
  $('#category-filter').addEventListener('change', renderProductsTable);

  // Users
  $('#add-user-btn').addEventListener('click', () => openUserModal());
  $('#user-form').addEventListener('submit', saveUser);

  // History
  $('#date-filter').addEventListener('change', renderHistoryTable);
  $('#seller-filter').addEventListener('change', renderHistoryTable);

  // Categories
  $('#add-category-btn').addEventListener('click', () => openCategoryModal());
  $('#category-form').addEventListener('submit', saveCategoryFn);
  $('#categories-filter').addEventListener('input', renderCategoriesTable);

  // Category color picker live preview
  $('#category-color').addEventListener('input', (e) => {
    $('#category-color-hex').textContent = e.target.value;
  });

  // Settings
  $('#store-form').addEventListener('submit', saveStoreSettings);
  $('#tax-form').addEventListener('submit', saveTaxSettings);

  // Close modals
  $$('.close-modal').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal');
      if (modal) modal.classList.remove('active');
    });
  });

  // Close modal on backdrop click
  $$('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('active');
    });
  });
});
