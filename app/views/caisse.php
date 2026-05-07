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
        <button class="scanner-btn" onclick="openScannerModal()" title="Scanner code-barres">
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
        </button>
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
        </div>
      </div>
      <div id="products-grid" class="products-grid"></div>
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
          Client
        </label>
        <!-- Ligne 1: Nom + N° téléphone côte à côte -->
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
          <input type="text" id="client-nom" class="client-number-input" placeholder="Nom du client" style="flex: 1;">
          <div style="position: relative; display: flex; align-items: center; flex: 1;">
            <input type="text" id="client-number" class="client-number-input" placeholder="N° téléphone" style="width: 100%; padding-right: 40px;">
            <button type="button" id="btn-search-client" onclick="searchClientByNumero()" style="position: absolute; right: 8px; background: none; border: none; cursor: pointer; padding: 4px; color: #0B5E88; display: flex; align-items: center; justify-content: center;" title="Rechercher client">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
            </button>
          </div>
        </div>
        <!-- Ligne 2: Type client + NIF côte à côte -->
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
          <select id="client-type" class="client-number-input" style="flex: 1;">
            <option value="">Type client</option>
            <?php foreach ($clientTypes as $type): ?>
              <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['code']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" id="client-nif" class="client-number-input" placeholder="NIF client" style="flex: 1;">
        </div>
        <!-- Message d'erreur/succès -->
        <div id="client-search-message" style="font-size: 0.75rem; margin-bottom: 8px; display: none;"></div>
        <button type="button" id="btn-save-client" class="btn btn-secondary btn-small" onclick="saveClientQuick()" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          Enregistrer client
        </button>
      </div>
      <div id="cart-items" class="cart-items">
        <div class="cart-empty">Le panier est vide</div>
      </div>
      <div class="cart-totals">
        <div class="total-row">
          <span>Sous-total HT</span>
          <span id="subtotal">0.00 Fc</span>
        </div>
        <div class="total-row total-final">
          <span>TOTAL TTC</span>
          <span id="total">0.00 Fc</span>
        </div>
      </div>
      <div class="btn-group-valider">
        <label id="calculator-toggle" class="calculator-radio" title="Mode calculatrice">
          <input type="checkbox" id="calc-mode-checkbox" onchange="toggleCalcMode(this.checked)">
          <span class="calc-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="4" y="2" width="16" height="20" rx="2"></rect>
              <line x1="8" y1="6" x2="16" y2="6"></line>
              <line x1="8" y1="10" x2="16" y2="10"></line>
              <line x1="8" y1="14" x2="12" y2="14"></line>
              <line x1="8" y1="18" x2="10" y2="18"></line>
            </svg>
            Calculatrice
          </span>
        </label>
        <button id="show-preview" class="btn btn-primary btn-full" disabled onclick="posCart.showPreview()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          Valider la vente
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL PAIEMENT (Popup séparé pour calculer la monnaie) -->
  <div id="payment-modal" class="modal">
    <div class="modal-content" style="max-width: 400px; padding: 0;">
      <div class="modal-header" style="background: linear-gradient(135deg, #0B5E88 0%, #2AB7E6 100%); color: white; border: none;">
        <h3 style="color: white;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
          </svg>
          PAIEMENT
        </h3>
        <button class="close-modal" onclick="closePaymentModal()" style="color: white;">&times;</button>
      </div>

      <div style="padding: 1.5rem;">
        <!-- Total à payer -->
        <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 12px; padding: 1.25rem; text-align: center; margin-bottom: 1rem;">
          <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">TOTAL À PAYER</div>
          <div id="payment-total" style="font-size: 2rem; font-weight: 700; color: #0B5E88;">0.00 Fc</div>
          <div id="payment-total-usd" style="font-size: 0.9rem; color: #64748b; margin-top: 0.25rem;">($0.00 USD)</div>
        </div>

        <!-- Taux de change fixe -->
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 0.75rem; text-align: center; margin-bottom: 1rem;">
          <span style="font-size: 0.8rem; color: #856404;">💱 Taux: 1 USD = <strong>2 300 Fc</strong></span>
        </div>

        <!-- Mode de paiement (Radio) -->
        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #1a1a2e; margin-bottom: 0.5rem;">
            Mode de paiement
          </label>
          <div style="display: flex; gap: 1rem; margin-bottom: 0.75rem;">
            <label style="flex: 1; cursor: pointer;">
              <input type="radio" name="payment-mode" value="usd" checked onchange="posCart.togglePaymentMode('usd')" style="margin-right: 0.5rem;">
              <span style="font-weight: 500;"> Dollars (USD)</span>
            </label>
            <label style="flex: 1; cursor: pointer;">
              <input type="radio" name="payment-mode" value="fc" onchange="posCart.togglePaymentMode('fc')" style="margin-right: 0.5rem;">
              <span style="font-weight: 500;"> Francs (Fc)</span>
            </label>
          </div>
        </div>

        <!-- Entrée montant reçu -->
        <div style="margin-bottom: 1rem;">
          <label for="payment-received" id="payment-received-label" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1a1a2e; margin-bottom: 0.5rem;">
            Montant reçu (USD)
          </label>
          <input type="number" id="payment-received"
            placeholder="Entrez le montant..."
            step="0.01"
            min="0"
            style="width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1.25rem; text-align: center; font-weight: 600; outline: none; transition: border-color 0.2s;"
            oninput="posCart.calculateChange()">
        </div>

        <!-- Résultat -->
        <div id="payment-result" style="display: none; border-radius: 12px; padding: 1.25rem; text-align: center;">
          <div id="payment-change-label" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;"></div>
          <div id="payment-change-fc" style="font-size: 2rem; font-weight: 700;"></div>
          <div id="payment-change-usd" style="font-size: 0.9rem; margin-top: 0.25rem;"></div>
        </div>

        <!-- Boutons -->
        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
          <button onclick="closePaymentModal()" class="btn btn-secondary" style="flex: 1; padding: 0.875rem;">
            Annuler
          </button>
          <button id="btn-confirm-payment" onclick="confirmPayment()" class="btn btn-success" style="flex: 2; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Continuer
          </button>
        </div>
      </div>
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

  <!-- MODAL SCANNER -->
  <div id="scanner-modal" class="scanner-modal">
    <div class="scanner-modal-content">
      <div class="scanner-modal-header">
        <h3> Scanner Code-barres</h3>
        <button class="scanner-close-btn" onclick="closeScannerModal()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <!-- État de chargement -->
      <div id="scanner-loading" class="scanner-status loading">
        <div class="scanner-spinner"></div>
        <span>Recherche du produit...</span>
      </div>

      <!-- Résultat -->
      <div id="scanner-result" class="scanner-status"></div>

      <!-- Info produit -->
      <div id="scanner-product" class="scanner-product-info">
        <div class="scanner-product-name" id="scanned-name">-</div>
        <div class="scanner-product-price" id="scanned-price">-</div>
      </div>

      <!-- Zone de scan -->
      <div id="scanner-reader"></div>

      <!-- Actions -->
      <div class="scanner-actions">
        <button id="scanner-cancel-btn" class="scanner-btn-cancel" onclick="closeScannerModal()">Fermer</button>
        <button id="scanner-rescan-btn" class="scanner-btn-rescan" onclick="restartScanner()" style="display:none;">Scanner à nouveau</button>
      </div>
    </div>
  </div>
</div>