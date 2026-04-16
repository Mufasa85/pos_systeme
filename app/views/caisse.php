      <!-- Caisse Page -->
      <div class="page active" style="display:block;">
        <div class="caisse-container">
          <!-- Products Section -->
          <div class="caisse-products">
            <div class="search-bar">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input type="text" id="product-search" placeholder="Rechercher un produit ou scanner le code-barres..." autofocus>
            </div>
            <div class="category-tabs">
              <button class="category-tab active" data-category="all">Tous</button>
              <button class="category-tab" data-category="Boissons">Boissons</button>
              <button class="category-tab" data-category="Alimentation">Alimentation</button>
              <button class="category-tab" data-category="Hygiene">Hygiène</button>
              <button class="category-tab" data-category="Menage">Ménage</button>
            </div>
            <div id="products-grid" class="products-grid">
                <div class="empty-state">Chargement des produits...</div>
            </div>
          </div>

          <!-- Cart Section -->
          <div class="caisse-cart">
            <div class="cart-header">
              <h3>Panier</h3>
              <button id="clear-cart" class="btn btn-ghost btn-small" onclick="posCart.clearCart()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Vider
              </button>
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
                <span>TVA (20%)</span>
                <span id="tax">0.00 Fc</span>
              </div>
              <div class="total-row total-final">
                <span>TOTAL</span>
                <span id="total">0.00 Fc</span>
              </div>
            </div>
            <button id="validate-sale" class="btn btn-primary btn-full" disabled onclick="posCart.validateSale()">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Valider la vente
            </button>
          </div>
        </div>
      </div>
