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
      <!-- Hidden fields pour compatibilite JS (sera mis a jour depuis le modal) -->
      <input type="hidden" id="invoice-type" value="FV">
      <input type="hidden" id="invoice-ref" value="">
      <input type="hidden" id="client-nom" value="">
      <input type="hidden" id="client-number" value="0000">
      <input type="hidden" id="client-type" value="">
      <input type="hidden" id="client-nif" value="">
      <!-- Bouton icone pour ouvrir les infos client/facture -->
      <button type="button" onclick="openInvoiceInfoModal()" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0.6rem; margin-bottom: 0.5rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; color: #64748b; transition: all 0.2s;" title="Ajouter client / facture">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      </button>
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
        <div class="total-row" style="font-size: 0.8rem; color: #64748b; justify-content: center; margin-top: 4px; min-height: 24px;">
          <span id="currency-loader" style="display: none; align-items: center; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" style="animation: spin 1s linear infinite;">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
              <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"></path>
            </svg>
            <span style="margin-left: 4px; font-size: 11px; color: var(--muted);">Chargement...</span>
          </span>
          <span id="currency-status" style="display: none; margin-right: 4px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </span>
          <span id="total-usd">≈ $0.00 USD</span>
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
        <button id="show-preview" class="btn btn-primary btn-full" disabled onclick="openInvoiceInfoModal()">
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
      <div class="modal-header">
        <h3>
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

  <!-- MODAL POIDS (Pour produits vendus au kilo) -->
  <div id="poids-modal" class="modal">
    <div class="modal-content" style="max-width: 380px; padding: 0;">
      <div class="modal-header">
        <h3>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          Produit au poids
        </h3>
        <button class="close-modal" onclick="closePoidsModal()" style="color: white;">&times;</button>
      </div>
      <div style="padding: 1.5rem;">
        <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; text-align: center;">
          <div id="poids-product-name" style="font-size: 1.1rem; font-weight: 600; color: #1a1a2e; margin-bottom: 0.5rem;">-</div>
          <div style="font-size: 0.9rem; color: #64748b;">Prix: <strong id="poids-product-price">0.00 Fc</strong>/L'unite</div>
        </div>
        <div style="margin-bottom: 1rem;">
          <label for="poids-quantity" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1a1a2e; margin-bottom: 0.5rem;">
            Quantité ( ex : kg)
          </label>
          <input type="number" id="poids-quantity"
            placeholder="Ex: 0.500"
            step="0.001"
            min="0.001"
            style="width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1.25rem; text-align: center; font-weight: 600; outline: none; transition: border-color 0.2s;"
            oninput="updatePoidsTotal()">
          <small style="color: #64748b; font-size: 0.75rem;">Entrez le poids en kilogrammes (ex: 0.500 pour 500g)</small>
        </div>
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 12px; padding: 1rem; text-align: center; margin-bottom: 1rem;">
          <div style="font-size: 0.8rem; color: #166534; margin-bottom: 0.25rem;">TOTAL</div>
          <div id="poids-total" style="font-size: 1.75rem; font-weight: 700; color: #166534;">0.00 Fc</div>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button onclick="closePoidsModal()" class="btn btn-secondary" style="flex: 1; padding: 0.875rem;">
            Annuler
          </button>
          <button id="btn-add-poids" onclick="confirmAddPoids()" class="btn btn-success" style="flex: 2; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Ajouter au panier
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL INVOICE INFO (Résumé facture avant validation) -->
  <div id="invoice-info-modal" class="modal">
    <div class="modal-content" style="max-width: 500px; padding: 0;">
      <div class="modal-header">
        <h3>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          Informations Facture
        </h3>
        <button class="close-modal" onclick="closeInvoiceInfoModal()">&times;</button>
      </div>
      <div style="padding: 1.5rem;">
        <!-- Type, Référence et Exonération -->
        <div style="display: flex; gap: 12px; margin-bottom: 1rem; align-items: flex-end;">
          <div style="flex: 0 0 100px;">
            <label for="modal-invoice-type" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">TYPE</label>
            <select id="modal-invoice-type" class="client-number-input" style="width: 100%;">
              <option value="FV">FV</option>
              <option value="EV">EV</option>
              <option value="FT">FT</option>
              <option value="FA">FA</option>
              <option value="EA">EA</option>
              <option value="ET">ET</option>
            </select>
          </div>
          <div style="flex: 2;">
            <label for="modal-invoice-ref" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">RÉF DOCUMENT</label>
            <input type="text" id="modal-invoice-ref" class="client-number-input" placeholder="Référence..." style="width: 100%;">
          </div>
          <div style="flex: 1;">
            <label for="modal-exoneration" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">EXONÉRATION</label>
            <select id="modal-exoneration" class="client-number-input" style="width: 100%;">
              <option value="">-</option>
              <option value="RAM">RAM</option>
              <option value="RRR">RRR</option>
              <option value="RAN">RAN</option>
            </select>
          </div>
        </div>

        <!-- Mode de Paiement -->
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
          <div style="font-size: 0.75rem; font-weight: 600; color: #166534; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
              <line x1="1" y1="10" x2="23" y2="10"></line>
            </svg>
            Mode de Paiement
          </div>
          <div>
            <label for="modal-payment-type" style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Type de paiement</label>
            <select id="modal-payment-type" class="client-number-input" style="width: 100%; background: #fff;">
              <option value="cash">Espèces</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="card">Carte Bancaire</option>
              <option value="transfer">Virement</option>
              <option value="credit">Crédit</option>
            </select>
          </div>
          <div id="modal-payment-change" style="margin-top: 0.75rem; padding: 0.5rem; background: #fff; border-radius: 8px; text-align: center; font-weight: 600; display: none;">
            <span style="color: #166534;">Monnaie à rendre: </span>
            <span id="modal-change-amount" style="color: #0B5E88; font-size: 1.1rem;">0.00 Fc</span>
          </div>
        </div>

        <!-- Info Client avec recherche -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
          <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Informations Client
            <div style="margin-left: auto; display: flex; gap: 6px;">
              <button type="button" class="btn btn-secondary btn-tiny" onclick="editClientFromModal()" style="padding: 4px 8px; font-size: 0.65rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Modifier
              </button>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <button type="button" class="btn btn-success btn-tiny" onclick="saveClientFromModal()" style="padding: 4px 8px; font-size: 0.65rem;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                  </svg>
                  Nouveau
                </button>
              <?php endif; ?>
            </div>
          </div>
          <!-- Recherche client -->
          <div style="margin-bottom: 8px;">
            <label for="modal-client-number" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 4px;">N° Téléphone</label>
            <div style="position: relative;">
              <input type="text" id="modal-client-number" class="client-number-input" placeholder="N° téléphone pour rechercher..." style="width: 100%; padding-right: 40px;" onkeypress="if(event.key==='Enter') searchClientFromModal()">
              <button type="button" onclick="searchClientFromModal()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; color: #0B5E88;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
              </button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
              <div>
                <label for="modal-client-name" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Nom</label>
                <input type="text" id="modal-client-name" class="client-number-input" placeholder="Nom du client" style="width: 100%;">
              </div>
              <div>
                <label for="modal-client-type" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Type</label>
                <select id="modal-client-type" class="client-number-input" style="width: 100%;">
                  <option value="">Type client</option>
                  <?php if (isset($clientTypes)): foreach ($clientTypes as $type): ?>
                      <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['code']) ?></option>
                  <?php endforeach;
                  endif; ?>
                </select>
              </div>
              <div style="grid-column: span 2;">
                <label for="modal-client-nif" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">NIF</label>
                <input type="text" id="modal-client-nif" class="client-number-input" placeholder="NIF client" style="width: 100%;">
              </div>
            </div>
            <!-- Message de recherche -->
            <div id="modal-client-search-message" style="font-size: 0.7rem; margin-top: 6px; display: none;"></div>
          </div>

          <!-- Boutons -->
          <div style="display: flex; gap: 0.75rem;">
            <button onclick="closeInvoiceInfoModal()" class="btn btn-secondary" style="flex: 1; padding: 0.875rem;">
              Retour
            </button>
            <button onclick="confirmInvoiceInfo()" class="btn btn-primary" style="flex: 2; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Continuer vers Preview
            </button>
          </div>
        </div>
      </div>
    </div>

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