<!-- Caisse Page -->
<div id="page-caisse" class="page <?= $page == 'caisse' ? 'active' : '' ?>">
  <!-- Overlay pour le panier mobile -->
  <div class="cart-sidebar-overlay" id="cart-sidebar-overlay" onclick="toggleCartSidebar()"></div>

  <div class="caisse-container">
    <!-- Products Section -->
    <div class="caisse-products">
      <div class="search-bar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="product-search" placeholder="Rechercher un produit..." autofocus>
        <a href="/scanner" class="scanner-btn" title="Scanner code-barres">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
        </a>
      </div>
      <!-- Select moderne pour les catégories -->
      <div class="category-select-wrapper">
        <div class="category-select-container">
          <label for="category-filter" class="category-select-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            Catégorie
          </label>
          <div class="select-wrapper">
            <select id="category-filter" class="modern-select">
              <option value="">-- Sélectionner une catégorie --</option>
              <option value="all">Toutes les catégories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="select-arrow">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>
          </div>
        </div>
        <!-- Loader pour le chargement des produits -->
        <div class="products-loader" id="products-loader">
          <div class="loader-spinner"></div>
          <span>Chargement...</span>
        </div>
      </div>
      <div id="products-grid" class="products-grid">
        <div class="empty-state">Chargement des produits...</div>
      </div>
    </div>

    <!-- Cart Section (Sidebar sur mobile) -->
    <div class="caisse-cart" id="caisse-cart">
      <div class="cart-header">
        <h3>Panier</h3>
        <button id="close-cart-sidebar" class="btn btn-ghost btn-small cart-close-btn" onclick="toggleCartSidebar()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
        <button id="clear-cart" class="btn btn-ghost btn-small" onclick="posCart.clearCart()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          </svg>
          Vider
        </button>
      </div>
      <div class="client-number-section">
        <label for="client-number" class="client-number-label">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Numéro du client
        </label>
        <input type="text" id="client-number" class="client-number-input" placeholder="Entrez le numéro du client" onchange="posCart.updateClientNumber(this.value)">
      </div>
      <div id="cart-items" class="cart-items">
        <div class="cart-empty">Le panier est vide</div>
      </div>
      <div class="cart-totals">
        <div class="total-row">
          <span>Sous-total</span>
          <span id="subtotal">0.00 Fc</span>
        </div>
        <div class="total-row">
          <span>TVA (16%)</span>
          <span id="tax">0.00 Fc</span>
        </div>
        <div class="total-row total-final">
          <span>TOTAL</span>
          <span id="total">0.00 Fc</span>
        </div>
      </div>
      <button id="show-preview" class="btn btn-primary btn-full" disabled onclick="posCart.showPreview()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        Valider la vente
      </button>
    </div>
  </div>

  <!-- Bouton flottant du panier (visible sur mobile) -->
  <button class="cart-floating-btn" id="cart-floating-btn" onclick="toggleCartSidebar()">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="9" cy="21" r="1"></circle>
      <circle cx="20" cy="21" r="1"></circle>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
    </svg>
    <span class="cart-badge" id="cart-badge">0</span>
    <span class="cart-floating-total" id="cart-floating-total">0.00 Fc</span>
  </button>
</div>