<!-- Recharges Page - SNEL/REGIDESO -->
<div id="page-recharges" class="page <?= $page == 'recharges' ? 'active' : '' ?>">
  <div class="cart-sidebar-overlay" id="cart-sidebar-overlay" onclick="toggleCartSidebar()"></div>

  <div class="caisse-container">
    <!-- Products Section with scroll -->
    <div class="caisse-products" style="max-height: calc(100vh - 120px); overflow-y: auto; padding-right: 8px;">

      <!-- Recherche + Infos Client (bloc unique) -->
      <div class="recharge-search-client-block">
        <h3 class="section-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          Recherche Facture & Informations Client
        </h3>

        <div class="recharge-search-section">
          <div class="search-form-row">
            <!-- Select Service (SNEL/REGIDESO) -->
            <div class="form-group">
              <label for="service-select">Service</label>
              <div class="select-wrapper">
                <select id="service-select" class="modern-select" onchange="loadRechargesByService(this.value)">
                  <option value="">-- Sélectionner --</option>
                  <option value="SNEL">⚡ SNEL (Électricité)</option>
                  <option value="REGIDESO">💧 REGIDESO (Eau)</option>
                </select>
                <div class="select-arrow">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Numéro compteur -->
            <div class="form-group">
              <label for="invoice-number">N° Compteur</label>
              <div class="input-with-btn">
                <input type="text" id="invoice-number" class="client-number-input" placeholder="Entrez le n° compteur...">
                <button class="btn btn-secondary" onclick="onSearchInvoice()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                  </svg>
                  Rechercher
                </button>
              </div>
            </div>
          </div>

          <!-- Filtre année -->
          <div class="search-form-row">
            <div class="form-group">
              <label for="year-filter">Année</label>
              <div class="select-wrapper">
                <select id="year-filter" class="modern-select" onchange="filterByYear(this.value)">
                  <option value="">Toutes les années</option>
                  <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                  <?php endfor; ?>
                </select>
                <div class="select-arrow">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="recharge-client-section">
          <div class="client-form-grid">
            <div class="form-group">
              <label for="client-nom">Nom</label>
              <input type="text" id="client-nom" class="client-number-input" placeholder="Nom">
            </div>
            <div class="form-group">
              <label for="client-postnom">Post-nom</label>
              <input type="text" id="client-postnom" class="client-number-input" placeholder="Post-nom">
            </div>
            <div class="form-group">
              <label for="client-prenom">Prénom</label>
              <input type="text" id="client-prenom" class="client-number-input" placeholder="Prénom">
            </div>
            <div class="form-group">
              <label for="client-tel">Téléphone</label>
              <input type="text" id="client-tel" class="client-number-input" placeholder="N° téléphone" value="0000">
            </div>
          </div>
        </div>
      </div>

      <!-- Mois à payer (après recherche API) -->
      <div class="recharge-list-section">
        <h3 class="section-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
          </svg>
          Mois impayés
        </h3>

        <div id="months-section" class="recharge-service-block" style="display: none;">
          <div class="recharge-section-header">
            <span class="section-icon">📋</span>
            <span>Sélectionnez les mois à payer</span>
          </div>
          <div id="months-grid"></div>
        </div>
      </div>
    </div>

    <!-- Cart Section -->
    <div class="caisse-cart" id="caisse-cart">
      <div class="cart-header">
        <h3>Panier Recharges</h3>
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

      <!-- Type facture -->
      <div class="client-number-section">
        <div style="display: flex; gap: 8px; margin-bottom: 10px;">
          <div style="flex: 1;">
            <label for="invoice-type" style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">TYPE FACTURE</label>
            <select id="invoice-type" class="client-number-input" style="width: 100%;">
              <option value="FV">FV</option>
              <option value="EV">EV</option>
              <option value="FT">FT</option>
            </select>
          </div>
          <div style="flex: 1;">
            <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">N° DOCUMENT</label>
            <input type="text" id="invoice-ref" class="client-number-input" placeholder="Auto" style="width: 100%;" readonly>
          </div>
        </div>
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
        <button id="show-preview" class="btn btn-primary btn-full" disabled onclick="billPayment.showPreview()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          Valider la recharge
        </button>
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
</div>

<script>
  // Charger les recharges par service (SNEL ou REGIDESO)
  function loadRechargesByService(service) {
    const snelSection = document.getElementById('snel-section');
    const regidesoSection = document.getElementById('regideso-section');

    snelSection.style.display = 'none';
    regidesoSection.style.display = 'none';

    if (service === 'SNEL') {
      snelSection.style.display = 'block';
    } else if (service === 'REGIDESO') {
      regidesoSection.style.display = 'block';
    }
  }

  // Rechercher avec billPayment (API Provider)
  function onSearchInvoice() {
    const service = document.getElementById('service-select').value;
    const numeroCompteur = document.getElementById('invoice-number').value;

    if (!service) {
      alert('Veuillez sélectionner un service (SNEL ou REGIDESO)');
      return;
    }
    if (!numeroCompteur) {
      alert('Veuillez entrer un numéro de compteur');
      return;
    }

    // Appeler billPayment pour fetch API
    billPayment.fetchBillInquiry(service, numeroCompteur)
      .then(result => {
        if (result.success) {
          // Afficher section mois
          const monthsSection = document.getElementById('months-section');
          if (monthsSection) monthsSection.style.display = 'block';
        }
      });
  }

  // Filtrer par année (delegation vers billPayment)
  function filterByYear(year) {
    if (year && billPayment) {
      billPayment.loadYear(year);
    }
  }

  // Ajouter recharge au panier
  function addRechargeToCart(id, nom, prix, service, description) {
    posCart.addItem({
      id: id,
      nom: nom + ' - ' + service,
      prix: prix,
      stock: 999,
      categorie: service,
      description: description
    });
  }

  // Ajouter au panier (legacy)
  function addMockProductToCart(id, nom, prix, categorie) {
    addRechargeToCart(id, nom, prix, categorie, '');
  }
</script>

<style>
  .recharge-search-client-block {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  }

  .recharge-search-section,
  .recharge-client-section {
    margin-bottom: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
  }

  .recharge-search-section:last-child,
  .recharge-client-section:last-child {
    margin-bottom: 0;
  }

  .recharge-list-section {
    background: white;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  }

  .section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e2e8f0;
  }

  .search-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
  }

  .form-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 4px;
  }

  .input-with-btn {
    display: flex;
    gap: 8px;
  }

  .input-with-btn input {
    flex: 1;
  }

  .input-with-btn .btn {
    white-space: nowrap;
  }

  .client-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .recharge-service-block {
    margin-bottom: 16px;
  }

  .recharge-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 8px;
    font-weight: 600;
    color: #1a1a2e;
    font-size: 0.9rem;
  }

  .snel-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%) !important;
    color: #fff !important;
    border-radius: 12px;
    padding: 10px;
  }

  .regideso-icon {
    background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%) !important;
    color: #fff !important;
    border-radius: 12px;
    padding: 10px;
  }

  .recharge-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 16px;
    background: transparent;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
  }

  .recharge-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: #0B5E88;
  }

  .product-desc {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
    margin-bottom: 8px;
  }

  .products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
  }

  @media (max-width: 768px) {

    .search-form-row,
    .client-form-grid {
      grid-template-columns: 1fr;
    }

    .products-grid {
      grid-template-columns: repeat(2, 1fr);
    }

    #caisse-cart {
      position: fixed;
      top: auto;
      bottom: 0;
      left: 5;
      right: 0;
      width: 100%;
      max-height: 60vh;
      border-radius: 16px 16px 0 0;
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    }
  }

  @media (min-width: 769px) and (max-width: 1024px) {
    .caisse-container {
      display: flex;
      gap: 20px;
      position: relative;
    }

    .caisse-products {
      flex: 1;
      max-width: calc(100% - 340px);
    }

    #caisse-cart {
      width: 320px;
      flex-shrink: 0;
      position: fixed;
      top: 100px;
      right: 10px;
      max-height: calc(100vh - 120px);
    }
  }
</style>