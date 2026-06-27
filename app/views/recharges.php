<!-- Recharges Page - SNEL/REGIDESO -->
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title id="page-title-base">Caisse - <?= htmlspecialchars($storeName ?? 'Mon Magasin') ?></title>
  <link rel="stylesheet" href="./assets/css/styles.css?v=208">
  <link rel="stylesheet" href="./assets/css/mobile-caisse.css?v=209">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="./assets/css/recharges.css?v=7">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <div id="page-recharges" class="page <?= $page == 'recharges' ? 'active' : '' ?>">
    <div class="cart-sidebar-overlay" id="cart-sidebar-overlay" onclick="toggleCartSidebar()"></div>

    <div class="caisse-container">
      <!-- Products Section with scroll -->
      <div class="caisse-products" style="max-height: calc(150vh - 120px); overflow-y: auto; padding-right: 8px;">

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
            <div class="search-form-row search-form-row-3">
              <!-- Select Service (SNEL/REGIDESO) -->
              <div class="form-group">
                <label for="service-select">Service</label>
                <div class="select-wrapper">
                  <select id="service-select" class="modern-select" onchange="loadRechargesByService(this.value)">
                    <option value="">-- Sélectionner --</option>
                    <option value="ELECTRICITE">⚡ELECTRICITE</option>
                    <option value="EAU">💧 EAU</option>
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
                  </button>
                </div>
              </div>

              <!-- Filtre année -->
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
            <!-- Icône cliquable pour ouvrir le modal -->
            <button class="client-info-btn" onclick="openClientModal()">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <span>Informations Client</span>
              <svg class="chevron-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </button>
          </div>

          <!-- Modal informations client -->
          <div id="client-modal" class="modal-overlay" onclick="closeClientModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
              <div class="modal-header">
                <h3>Informations Client</h3>
                <button class="modal-close" onclick="closeClientModalDirect()">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </div>
              <div class="modal-body">
                <div class="form-group">
                  <label for="modal-client-nom">Noms</label>
                  <input type="text" id="modal-client-nom" class="client-number-input" placeholder="Nom">
                </div>
                <div class="form-group">
                  <label for="modal-client-postnom">Commune</label>
                  <input type="text" id="modal-client-postnom" class="client-number-input" placeholder="Post-nom">
                </div>
                <div class="form-group">
                  <label for="modal-client-prenom">Provinces</label>
                  <input type="text" id="modal-client-prenom" class="client-number-input" placeholder="Prénom">
                </div>
                <div class="form-group">
                  <label for="modal-client-tel">Téléphone</label>
                  <input type="text" id="modal-client-tel" class="client-number-input" placeholder="N° téléphone" value="0000">
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeClientModalDirect()">Annuler</button>
                <button class="btn btn-primary" onclick="saveClientInfo()">Enregistrer</button>
              </div>
            </div>
          </div>

          <!-- Champs cachés pour储存 les données client -->
          <input type="hidden" id="client-nom">
          <input type="hidden" id="client-postnom">
          <input type="hidden" id="client-prenom">
          <input type="hidden" id="client-tel">
        </div>

        <!-- MODAL INVOICE INFO (Résumé facture avant validation) -->
        <div id="invoice-info-modal" class="modal">
          <div class="modal-content" style="max-width: 500px; padding: 0; overflow: auto;">
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
              <button class="close-modal" onclick="closeInvoiceInfoModalRecharge()">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Type et Référence Document -->
                <div style="display: grid; grid-template-columns: 80px 1fr; gap: 12px; margin-bottom: 0.75rem; align-items: flex-end;">
                  <div>
                    <label for="modal-invoice-type" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">TYPE</label>
                    <select id="modal-invoice-type" class="client-number-input" style="width: 100%; padding: 6px 8px; font-size: 0.8rem;">
                      <option value="FV">FV</option>
                      <option value="EV">EV</option>
                      <option value="FT">FT</option>
                      <option value="FA">FA</option>
                      <option value="EA">EA</option>
                      <option value="ET">ET</option>
                    </select>
                  </div>
                  <div>
                    <label for="modal-invoice-ref" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">RÉF DOCUMENT</label>
                    <input type="text" id="modal-invoice-ref" class="client-number-input" placeholder="Réf..." style="width: 100%;">
                  </div>
                </div>
                <!-- Ref Facture et Exonération -->
                <div style="display: grid; grid-template-columns: 1fr 80px; gap: 12px; margin-bottom: 1rem; align-items: flex-end;">
                  <div>
                    <label for="modal-invoice-num" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">REF FACTURE</label>
                    <input type="text" id="modal-invoice-num" class="client-number-input" placeholder="N° facture..." style="width: 100%;">
                  </div>
                  <div>
                    <label for="modal-exoneration" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;"></label>
                    <select id="modal-exoneration" class="client-number-input" style="width: 100%; padding: 6px 4px; font-size: 0.75rem;">
                      <option value="RAM">RAM</option>
                      <option value="RRR">RRR</option>
                      <option value="RAN">RAN</option>
                      <option value="COR">COR</option>
                    </select>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Mode de Paiement -->
              <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 600; color: #166534; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                  <span style="display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                      <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    Mode de Paiement
                  </span>
                  <button type="button" onclick="addModalPaymentLineRecharge()" style="background: #0B5E88; color: #fff; border: none; border-radius: 6px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; line-height: 1;">+</button>
                </div>
                <div id="modal-payments-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
                  <!-- payment lines will be inserted here -->
                </div>
                <div id="modal-payment-summary" style="margin-top: 0.75rem; padding: 0.5rem; background: #fff; border-radius: 8px; text-align: center; font-weight: 600; display: none;">
                  <span id="modal-payment-summary-label" style="color: #166534;"></span>
                  <span id="modal-payment-summary-amount" style="color: #0B5E88; font-size: 1.1rem;"></span>
                </div>
                <input type="hidden" id="modal-payment-type" value="cash">
              </div>

              <!-- Info Client avec données pré-remplies -->
              <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  Informations Client
                  <div class="modal-client-actions" style="margin-left: auto; display: flex; gap: 6px;">
                    <button type="button" class="btn btn-success btn-tiny" onclick="saveClientFromModalRecharge()" style="padding: 4px 8px; font-size: 0.65rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                      </svg>
                      Nouveau
                    </button>
                  </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                  <div>
                    <label for="modal-client-name" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Nom</label>
                    <input type="text" id="modal-client-name" class="client-number-input" placeholder="Nom du client" style="width: 100%;">
                  </div>
                  <div>
                    <label for="modal-client-tel" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Téléphone</label>
                    <input type="text" id="modal-client-tel1" class="client-number-input" placeholder="N° téléphone" style="width: 100%;">
                  </div>
                  <div>
                    <label for="modal-client-type" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Type</label>
                    <select id="modal-client-type" class="client-number-input" style="width: 100%;">
                      <option value="">Type client</option>
                      <?php if (isset($clientTypes)): foreach ($clientTypes as $type): ?>
                          <option value="<?= $type['code'] ?>"><?= htmlspecialchars($type['code']) ?> - <?= htmlspecialchars($type['description'] ?? $type['nom'] ?? '') ?></option>
                      <?php endforeach;
                      endif; ?>
                    </select>
                  </div>
                  <div>
                    <label for="modal-client-nif" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">NIF</label>
                    <input type="text" id="modal-client-nif" class="client-number-input" placeholder="NIF client" style="width: 100%;">
                  </div>
                  <div style="grid-column: span 2;">
                    <label for="modal-client-address" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Adresse</label>
                    <input type="text" id="modal-client-address" class="client-number-input" placeholder="Adresse du client" style="width: 100%;">
                  </div>
                </div>
              </div>

              <!-- Boutons -->
              <div class="modal-actions" style="display: flex; gap: 0.75rem;">
                <button onclick="closeInvoiceInfoModalRecharge()" class="btn btn-secondary" style="flex: 1; padding: 0.875rem;">
                  Retour
                </button>
                <button onclick="confirmInvoiceInfoRecharge()" class="btn btn-primary" style="flex: 2; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  Continuer vers Preview
                </button>
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
              <span class="section-icon"></span>
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

        <!-- Champs cachés pour les infos client (utilisés par le modal) -->
        <input type="hidden" id="invoice-type" value="FV">
        <input type="hidden" id="invoice-ref" value="">
        <input type="hidden" id="client-type" value="">
        <input type="hidden" id="client-nif" value="">
        <input type="hidden" id="client-numero" value="">
        <input type="hidden" id="client-address" value="">

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

    // Modal client functions
    function openClientModal() {
      document.getElementById('client-modal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeClientModal(event) {
      if (event.target === event.currentTarget) {
        closeClientModalDirect();
      }
    }

    function closeClientModalDirect() {
      document.getElementById('client-modal').classList.remove('active');
      document.body.style.overflow = '';
    }

    function saveClientInfo() {
      // Récupérer les valeurs du modal
      const nom = document.getElementById('modal-client-nom').value;
      const postnom = document.getElementById('modal-client-postnom').value;
      const prenom = document.getElementById('modal-client-prenom').value;
      const tel = document.getElementById('modal-client-tel').value;

      // Stocker dans les champs cachés (si nécessaire pour le traitement)
      document.getElementById('client-nom').value = nom;
      document.getElementById('client-postnom').value = postnom;
      document.getElementById('client-prenom').value = prenom;
      document.getElementById('client-tel').value = tel;

      closeClientModalDirect();
    }

    // Fonctions pour le nouveau flow Recharges
    function closeInvoiceInfoModalRecharge() {
      if (typeof billPayment !== 'undefined') {
        billPayment.closeInvoiceInfoModalRecharge();
      }
    }

    function confirmInvoiceInfoRecharge() {
      if (typeof billPayment !== 'undefined') {
        billPayment.confirmInvoiceInfoRecharge();
      }
    }

    function saveClientFromModalRecharge() {
      if (typeof billPayment !== 'undefined') {
        billPayment.saveClientFromModalRecharge();
      }
    }

    function addModalPaymentLineRecharge() {
      if (typeof billPayment !== 'undefined') {
        billPayment.addModalPaymentLineRecharge();
      }
    }

    function removeModalPaymentLineRecharge(btn) {
      if (typeof billPayment !== 'undefined') {
        billPayment.removeModalPaymentLineRecharge(btn);
      }
    }
  </script>