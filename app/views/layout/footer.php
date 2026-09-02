    </main>
    </div>

    <!-- Receipt Modal -->
    <div id="receipt-modal" class="modal">
      <div class="modal-content receipt-modal">
        <div class="receipt-scrollable">
          <div id="receipt-content"></div>
        </div>
        <div class="receipt-actions">
          <button id="close-receipt" class="btn btn-secondary" onclick="closeReceiptModal()">
            Annuler
          </button>
          <button id="print-receipt" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Preview Modal - Recapitulatif de la facture -->
    <div id="preview-modal" class="modal">
      <div class="modal-content preview-modal">
        <div class="modal-header">
          <h3>Récapitulatif de la vente</h3>
          <button class="close-modal" onclick="posCart.closePreview()">&times;</button>
        </div>
        <div id="preview-content" class="preview-content">
          <!-- Contenu généré par JS -->
        </div>
        <div class="modal-actions" id="preview-modal-actions">
          <button type="button" class="btn btn-secondary" onclick="posCart.closePreview()">Annuler</button>
          <button type="button" id="confirm-sale" class="btn btn-primary" onclick="confirmSaleFromPreview()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Valider la facture
          </button>
        </div>
      </div>
    </div>

    <!-- Product Modal -->
    <div id="product-modal" class="modal">
      <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
          <h3 id="product-modal-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            Ajouter un produit
          </h3>
          <button class="close-modal" onclick="document.getElementById('product-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="product-form" onsubmit="return false;">
          <input type="hidden" id="product-id">

          <!-- Section Image avec aperçu -->
          <div class="product-image-section" style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; align-items: flex-start;">
            <div class="product-image-preview" id="product-image-preview" style="width: 140px; height: 140px; border: 2px dashed var(--border); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; background: var(--background); overflow: hidden; flex-shrink: 0;">
              <div style="text-align: center; color: var(--muted); padding: 1rem;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4;">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                  <circle cx="8.5" cy="8.5" r="1.5"></circle>
                  <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <p style="font-size: 0.75rem; margin-top: 0.5rem;">Aucune image</p>
              </div>
            </div>
            <div class="product-image-info" style="flex: 1;">
              <label style="font-weight: 500; margin-bottom: 0.5rem; display: block;">Image du produit</label>
              <p style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.75rem;">Formats acceptés: JPG, PNG, GIF, WebP. Taille max: 2MB</p>
              <div class="file-input-wrapper" style="position: relative;">
                <input type="file" id="product-image" accept="image/*" style="display: none;" onchange="previewProductImage(this);">
                <label for="product-image" class="btn btn-secondary" style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                  </svg>
                  image
                </label>
                <button type="button" class="btn btn-ghost" onclick="clearProductImage();" id="clear-image-btn" style="display: none; margin-left: 0.5rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                  Supprimer
                </button>
              </div>
              <p id="product-image-name" style="font-size: 0.75rem; color: var(--muted); margin-top: 0.5rem;"></p>
            </div>
          </div>

          <!-- Informations de base -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              Informations de base
            </h4>
            <div class="form-row">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                  </svg>
                  Code-barres
                </label>
                <div style="display:flex; gap:0.5rem;">
                  <input type="text" id="product-barcode" required style="flex:1;" placeholder="Ex: 1234567890123">
                  <button type="button" class="btn btn-secondary" onclick="generateBarcode()" title="Générer automatiquement" style="padding: 0 12px; white-space: nowrap;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="23 4 23 10 17 10"></polyline>
                      <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Générer
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  Nom du produit
                </label>
                <input type="text" id="product-name" required placeholder="Ex: Coca-Cola 1.5L">
              </div>
            </div>
            <div class="form-row" style="margin-top: 1rem;">
              <div class="form-group" style="flex: 1; min-width: 100%;">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  Date d'expiration par defaut
                </label>
                <input type="date" id="product-expiration-date">
                <small style="color: var(--muted);">Utilisee lors de la creation du stock initial. Laisser vide si non perissable.</small>
              </div>
            </div>
          </div>

          <!-- Prix et Catégorie -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
              Prix et Catégorie
            </h4>
            <div class="form-row">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                  </svg>
                  Catégorie
                </label>
                <select id="product-category" required>
                  <option value="">Sélectionner une catégorie</option>
                </select>
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                  </svg>
                  Type de taxe
                </label>
                <select id="product-tax" required>
                  <option value="">Sélectionner le groupe de taxe</option>
                  <?php foreach ($taxes ?? [] as $tax): ?>
                    <option value="<?= $tax['id'] ?>"><?= htmlspecialchars($tax['groupe_taxe']) ?> - <?= htmlspecialchars($tax['etiquette']) ?> (<?= $tax['taux'] ?>%)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-row" style="margin-top: 1rem;">
              <div class="form-group" style="flex: 1;">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                  </svg>
                  Prix de vente
                </label>
                <div style="position: relative;">
                  <input type="number" id="product-price" step="0.01" min="0" required placeholder="0.00" style="padding-right: 50px;">
                  <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.875rem;">Fc</span>
                </div>
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                  </svg>
                  Type de vente
                </label>
                <select id="product-type" required style="width: 100%;">
                  <option value="unite">À l'unité</option>
                  <option value="coupe">Vente à la coupe</option>
                </select>
                <small style="color: var(--muted);">"Au poids" pour charcuterie, fromage, etc.</small>
              </div>
            </div>

            <div class="form-row" style="margin-top: 1rem;">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M16 8l-8 8"></path>
                    <path d="M8 8l8 8"></path>
                  </svg>
                  Type de remise
                </label>
                <select id="product-remise-type" style="width: 100%;">
                  <option value="%">Pourcentage (%)</option>
                  <option value="CDF">Montant fixe (Fc)</option>
                </select>
                <small style="color: var(--muted);">Appliquée automatiquement sur le prix de vente</small>
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                  </svg>
                  Valeur de la remise
                </label>
                <input type="number" id="product-remise-value" min="0" step="0.01" value="0" placeholder="0.00" style="text-align: center;">
                <small style="color: var(--muted);">0-100 pour %, montant en Fc pour montant fixe</small>
              </div>
            </div>

            <div class="form-row" style="margin-top: 1rem;">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M16 8l-8 8"></path>
                    <path d="M8 8l8 8"></path>
                  </svg>
                  Taxe spécifique
                </label>
                <select id="product-taxe-specifique-type" style="width: 100%;">
                  <option value="%">Pourcentage (%)</option>
                  <option value="CDF">Montant fixe (Fc)</option>
                </select>
                <small style="color: var(--muted);">Appliquée comme taxe spécifique sur le produit</small>
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                  </svg>
                  Valeur taxe spécifique
                </label>
                <input type="number" id="product-taxe-specifique-value" min="0" step="0.01" value="0" placeholder="0.00" style="text-align: center;">
                <small style="color: var(--muted);">0-100 pour %, montant en Fc pour montant fixe</small>
              </div>
            </div>

            <div class="form-row" style="margin-top: 1rem;">
              <div class="form-group" style="flex: 1; min-width: 100%;">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                  </svg>
                  Type de service
                </label>
                <select id="product-prod-service" style="width: 100%;">
                  <option value="">Aucun</option>
                  <option value="BIE">BIE</option>
                  <option value="SER">SER</option>
                  <option value="TAX">TAX</option>
                </select>
                <small style="color: var(--muted);">Affiché sur la facture après l'étiquette</small>
              </div>
            </div>
          </div>

          <!-- Stock -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
              Gestion du stock
            </h4>
            <div class="form-row">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                  </svg>
                  Stock
                </label>
                <input type="number" id="product-stock" min="0" placeholder="Stock initial" style="text-align: center;" title="En creation, c'est le stock initial. En modification, le stock est calcule automatiquement depuis les lots.">
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                  </svg>
                  Stock minimum (alerte)
                </label>
                <input type="number" id="product-min-stock" min="0" required placeholder="0" style="text-align: center;">
                <small style="color: var(--muted);">Alerte quand le stock atteint ce niveau</small>
              </div>
            </div>
          </div>

          <!-- Gestion des lots -->
          <div id="batch-management-section" style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem; display: none;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
              Lots / Arrivages
            </h4>
            <div id="batch-list" style="margin-bottom: 1rem;">
              <p style="color: var(--muted); font-size: 0.85rem;">Aucun lot enregistre.</p>
            </div>
            <div class="form-row" style="align-items: flex-end;">
              <div class="form-group" style="flex: 1;">
                <label>Quantite recue</label>
                <input type="number" id="batch-new-stock" min="0.01" step="0.01" placeholder="0" style="text-align: center;">
              </div>
              <div class="form-group" style="flex: 1;">
                <label>Date d'expiration</label>
                <input type="date" id="batch-new-expiration">
              </div>
              <div class="form-group" style="flex: 1;">
                <label>N° de lot (optionnel)</label>
                <input type="text" id="batch-new-number" placeholder="Ex: LOT-001">
              </div>
              <div class="form-group" style="flex: 0;">
                <button type="button" class="btn btn-secondary" onclick="addProductBatch()" style="white-space: nowrap;">
                  Ajouter le lot
                </button>
              </div>
            </div>
          </div>

          <div class="modal-actions" style="padding-top: 1rem; border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-secondary" onclick="closeProductModal()" style="padding: 0.75rem 1.5rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Annuler
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <script>
      // Fonction pour prévisualiser l'image du produit
      function previewProductImage(input) {
        const preview = document.getElementById('product-image-preview');
        const fileNameDisplay = document.getElementById('product-image-name');
        const clearBtn = document.getElementById('clear-image-btn');

        if (input.files && input.files[0]) {
          const file = input.files[0];

          // Afficher le nom du fichier
          fileNameDisplay.textContent = file.name;
          clearBtn.style.display = 'inline-flex';

          // Vérifier la taille du fichier (2MB max)
          if (file.size > 2 * 1024 * 1024) {
            alert('L\'image est trop volumineuse. Taille maximum: 2MB');
            input.value = '';
            return;
          }

          const reader = new FileReader();
          reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
          };
          reader.readAsDataURL(file);
        }
      }

      // Fonction pour effacer l'image
      function clearProductImage() {
        document.getElementById('product-image').value = '';
        document.getElementById('product-image-name').textContent = '';
        document.getElementById('clear-image-btn').style.display = 'none';
        document.getElementById('product-image-preview').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 1rem;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg><p style="font-size: 0.75rem; margin-top: 0.5rem;">Aucune image</p></div>';
      }
    </script>

    <!-- User Modal -->
    <div id="user-modal" class="modal">
      <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
          <h3 id="user-modal-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="8.5" cy="7" r="4"></circle>
              <line x1="20" y1="8" x2="20" y2="14"></line>
              <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            Ajouter un utilisateur
          </h3>
          <button class="close-modal" onclick="document.getElementById('user-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="user-form">
          <input type="hidden" id="user-id">

          <!-- Informations de connexion -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              Informations de connexion
            </h4>
            <div class="form-row">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  Nom d'utilisateur
                </label>
                <input type="text" id="user-username" required placeholder="Ex: jdupont">
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                  Mot de passe
                </label>
                <input type="password" id="user-password" placeholder="Min. 6 caractères">
                <small id="password-hint" style="color: var(--muted); display: none;">Laisser vide pour ne pas modifier</small>
              </div>
            </div>
          </div>

          <!-- Informations personnelles -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Informations personnelles
            </h4>
            <div class="form-row">
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  Nom complet
                </label>
                <input type="text" id="user-fullname" required placeholder="Ex: Jean Dupont">
              </div>
              <div class="form-group">
                <label>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                  </svg>
                  Rôle
                </label>
                <select id="user-role" required>
                  <option value="vendeur">Vendeur</option>
                  <option value="admin">Administrateur</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Statut -->
          <div style="background: var(--background); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
            <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
              Statut du compte
            </h4>
            <div class="form-group">
              <label>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Statut
              </label>
              <select id="user-actif" style="width: 100%;">
                <option value="1">Actif</option>
                <option value="0">Inactif</option>
              </select>
            </div>
          </div>

          <div class="modal-actions" style="padding-top: 1rem; border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-secondary close-modal" onclick="document.getElementById('user-modal').classList.remove('active')" style="padding: 0.75rem 1.5rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Annuler
            </button>
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- New Client Modal -->
    <div id="new-client-modal" class="modal">
      <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
          <h3 id="new-client-modal-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Nouveau client
          </h3>
          <button class="close-modal" onclick="closeNewClientModal()">&times;</button>
        </div>
        <form id="new-client-form">
          <input type="hidden" id="new-client-numero-hidden">
          <div class="form-group" style="margin-bottom: 1rem;">
            <label for="new-client-nom">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Nom du client
            </label>
            <input type="text" id="new-client-nom" required placeholder="Ex: Jean Dupont">
          </div>
          <div class="form-group" style="margin-bottom: 1rem;">
            <label for="new-client-numero">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
              </svg>
              Numéro
            </label>
            <input type="text" id="new-client-numero" required placeholder="Numéro de téléphone">
          </div>
          <div class="form-group" style="margin-bottom: 1rem;">
            <label for="new-client-type">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              Type de client
            </label>
            <select id="new-client-type">
              <option value="1">Particulier</option>
              <option value="2">Entreprise</option>
            </select>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeNewClientModal()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Annuler
            </button>
            <button type="submit" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Category Modal -->
    <div id="category-modal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3 id="category-modal-title">Ajouter une catégorie</h3>
          <button class="close-modal" onclick="document.getElementById('category-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="category-form">
          <div class="form-group" style="margin-bottom:1rem;">
            <label for="category-name">Nom de la catégorie</label>
            <input type="text" id="category-name" placeholder="Ex: Comestible" required>
          </div>
          <div class="form-group" style="margin-bottom:1rem;">
            <label>Couleur</label>
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <input type="color" id="category-color" value="#0B5E88"
                style="width:48px;height:40px;padding:2px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;background:none;">
              <span id="category-color-hex" style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;color:var(--muted);">#0B5E88</span>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary close-modal" onclick="document.getElementById('category-modal').classList.remove('active')">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal : Mon Profil -->
    <div id="profile-modal" class="modal">
      <div class="modal-content" style="max-width:480px">
        <div class="modal-header">
          <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Mon Profil
          </h3>
          <button class="close-modal" onclick="document.getElementById('profile-modal').classList.remove('active')">&times;</button>
        </div>
        <div style="padding:0 1.25rem 1.25rem">
          <!-- Avatar + nom + rôle (non modifiable) -->
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1rem;background:var(--background,#f8fafc);border-radius:var(--radius,8px)">
            <div id="profile-avatar" style="width:56px;height:56px;border-radius:50%;background:var(--primary,#0B5E88);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;flex-shrink:0">
              <?= substr(htmlspecialchars($_SESSION['full_name'] ?? 'U'), 0, 1) ?>
            </div>
            <div>
              <div id="profile-display-name" style="font-weight:700;font-size:1.1rem"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['nom_complet'] ?? '') ?></div>
              <div style="color:var(--muted,#64748b);font-size:.85rem"><?php
                $r = $_SESSION['role'] ?? '';
                  echo $r === 'super_admin' ? 'Super Admin' : ($r === 'admin' ? 'Administrateur' : 'Vendeur');
                  ?></div>
              <div style="font-size:.75rem;color:var(--muted,#94a3b8);margin-top:2px">@<?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? '') ?></div>
            </div>
          </div>

          <!-- Mode consultation -->
          <div id="profile-view-mode">
            <div style="display:grid;gap:.75rem;margin-bottom:1.5rem">
              <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .8rem;background:var(--background,#f8fafc);border-radius:var(--radius,8px)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#64748b)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <div>
                  <div style="font-size:.7rem;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.05em">Nom complet</div>
                  <div id="pv-fullname" style="font-weight:500;font-size:.9rem"><?= htmlspecialchars($_SESSION['full_name'] ?? '-') ?></div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .8rem;background:var(--background,#f8fafc);border-radius:var(--radius,8px)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#64748b)" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                <div>
                  <div style="font-size:.7rem;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.05em">Email</div>
                  <div id="pv-email" style="font-weight:500;font-size:.9rem"><?= htmlspecialchars($_SESSION['email'] ?? '-') ?: '-' ?></div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .8rem;background:var(--background,#f8fafc);border-radius:var(--radius,8px)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#64748b)" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                <div>
                  <div style="font-size:.7rem;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.05em">Téléphone</div>
                  <div id="pv-phone" style="font-weight:500;font-size:.9rem"><?= htmlspecialchars($_SESSION['telephone'] ?? '-') ?: '-' ?></div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .8rem;background:var(--background,#f8fafc);border-radius:var(--radius,8px)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#64748b)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                <div>
                  <div style="font-size:.7rem;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.05em">Code Agent</div>
                  <div id="pv-agent" style="font-weight:500;font-size:.9rem"><?= htmlspecialchars($_SESSION['agent_code'] ?? '-') ?: '-' ?></div>
                </div>
              </div>
            </div>

            <button class="btn btn-primary" onclick="toggleProfileEdit(true)" style="width:100%;justify-content:center;gap:.5rem;margin-bottom:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon></svg>
              Modifier mon profil
            </button>

            <div style="display:flex;flex-direction:column;gap:.5rem">
              <button class="btn btn-secondary" onclick="document.getElementById('profile-modal').classList.remove('active');openChangePasswordModal()" style="width:100%;justify-content:flex-start;gap:.5rem">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Changer mon mot de passe
              </button>
              <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
              <button class="btn btn-secondary" onclick="document.getElementById('profile-modal').classList.remove('active');openClotureModal()" style="width:100%;justify-content:flex-start;gap:.5rem">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                Rapport de clôture
              </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Mode édition -->
          <div id="profile-edit-mode" style="display:none">
            <form id="profile-edit-form" onsubmit="submitProfileEdit(event)">
              <div class="form-group" style="margin-bottom:.75rem">
                <label style="font-size:.8rem;font-weight:600">Nom complet</label>
                <input type="text" id="pe-fullname" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>" required placeholder="Votre nom complet">
              </div>
              <div class="form-group" style="margin-bottom:.75rem">
                <label style="font-size:.8rem;font-weight:600">Email</label>
                <input type="email" id="pe-email" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" placeholder="votre@email.com">
              </div>
              <div class="form-group" style="margin-bottom:.75rem">
                <label style="font-size:.8rem;font-weight:600">Téléphone</label>
                <input type="text" id="pe-phone" value="<?= htmlspecialchars($_SESSION['telephone'] ?? '') ?>" placeholder="Ex: 0812345678">
              </div>
              <div class="form-group" style="margin-bottom:1rem">
                <label style="font-size:.8rem;font-weight:600">Code Agent</label>
                <input type="text" id="pe-agent" value="<?= htmlspecialchars($_SESSION['agent_code'] ?? '') ?>" placeholder="Code agent">
              </div>
              <div id="pe-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
              <div id="pe-success" style="color:#38a169;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
              <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="toggleProfileEdit(false)">Annuler</button>
                <button type="submit" class="btn btn-primary" id="pe-submit-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  Enregistrer
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script>
      function openProfileModal() {
        toggleProfileEdit(false);
        document.getElementById('profile-modal').classList.add('active');
      }
      function toggleProfileEdit(edit) {
        document.getElementById('profile-view-mode').style.display = edit ? 'none' : 'block';
        document.getElementById('profile-edit-mode').style.display = edit ? 'block' : 'none';
        document.getElementById('pe-error').style.display = 'none';
        document.getElementById('pe-success').style.display = 'none';
      }
      async function submitProfileEdit(e) {
        e.preventDefault();
        const errEl = document.getElementById('pe-error');
        const okEl = document.getElementById('pe-success');
        errEl.style.display = 'none';
        okEl.style.display = 'none';
        const btn = document.getElementById('pe-submit-btn');
        btn.disabled = true;
        const body = {
          nom_complet: document.getElementById('pe-fullname').value.trim(),
          email: document.getElementById('pe-email').value.trim(),
          telephone: document.getElementById('pe-phone').value.trim(),
          agent_code: document.getElementById('pe-agent').value.trim()
        };
        if (!body.nom_complet) { errEl.textContent = 'Le nom complet est requis.'; errEl.style.display = 'block'; btn.disabled = false; return; }
        try {
          const res = await fetch(APP_URL + '/api/user/update-profile', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)
          });
          const data = await res.json();
          if (data.success) {
            okEl.textContent = data.message || 'Profil mis à jour !';
            okEl.style.display = 'block';
            // Mettre à jour l'affichage en mode consultation
            document.getElementById('pv-fullname').textContent = body.nom_complet;
            document.getElementById('pv-email').textContent = body.email || '-';
            document.getElementById('pv-phone').textContent = body.telephone || '-';
            document.getElementById('pv-agent').textContent = body.agent_code || '-';
            document.getElementById('profile-display-name').textContent = body.nom_complet;
            document.getElementById('profile-avatar').textContent = body.nom_complet.charAt(0).toUpperCase();
            // Sidebar
            const sidebarName = document.getElementById('user-name');
            const sidebarAvatar = document.getElementById('user-avatar');
            if (sidebarName) sidebarName.textContent = body.nom_complet;
            if (sidebarAvatar) sidebarAvatar.textContent = body.nom_complet.charAt(0).toUpperCase();
            setTimeout(() => toggleProfileEdit(false), 1200);
          } else {
            errEl.textContent = data.message || data.error || 'Erreur';
            errEl.style.display = 'block';
          }
        } catch(ex) { errEl.textContent = 'Erreur réseau'; errEl.style.display = 'block'; }
        btn.disabled = false;
      }
    </script>

    <!-- Modal : Changer mon mot de passe -->
    <div id="change-password-modal" class="modal">
      <div class="modal-content" style="max-width:440px">
        <div class="modal-header">
          <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Changer mon mot de passe
          </h3>
          <button class="close-modal" onclick="document.getElementById('change-password-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="change-password-form" onsubmit="submitChangePassword(event)">
          <div class="form-group" style="margin-bottom:1rem">
            <label>Mot de passe actuel</label>
            <input type="password" id="cp-current" required placeholder="Mot de passe actuel" autocomplete="current-password">
          </div>
          <div class="form-group" style="margin-bottom:1rem">
            <label>Nouveau mot de passe</label>
            <input type="password" id="cp-new" required placeholder="Min. 6 caractères" minlength="6" autocomplete="new-password">
          </div>
          <div class="form-group" style="margin-bottom:1rem">
            <label>Confirmer le nouveau mot de passe</label>
            <input type="password" id="cp-confirm" required placeholder="Confirmer" autocomplete="new-password">
          </div>
          <div id="cp-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
          <div id="cp-success" style="color:#38a169;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('change-password-modal').classList.remove('active')">Annuler</button>
            <button type="submit" class="btn btn-primary" id="cp-submit-btn">Changer</button>
          </div>
        </form>
      </div>
    </div>
    <script>
      async function submitChangePassword(e) {
        e.preventDefault();
        const errEl = document.getElementById('cp-error');
        const okEl = document.getElementById('cp-success');
        errEl.style.display = 'none';
        okEl.style.display = 'none';
        const current = document.getElementById('cp-current').value;
        const newPw = document.getElementById('cp-new').value;
        const confirm = document.getElementById('cp-confirm').value;
        if (newPw !== confirm) { errEl.textContent = 'Les mots de passe ne correspondent pas.'; errEl.style.display = 'block'; return; }
        if (newPw.length < 6) { errEl.textContent = 'Le mot de passe doit contenir au moins 6 caractères.'; errEl.style.display = 'block'; return; }
        const btn = document.getElementById('cp-submit-btn');
        btn.disabled = true; btn.textContent = 'En cours...';
        try {
          const res = await fetch(APP_URL + '/api/user/change-password', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ current_password: current, new_password: newPw })
          });
          const data = await res.json();
          if (data.success) { okEl.textContent = 'Mot de passe modifié avec succès.'; okEl.style.display = 'block'; document.getElementById('change-password-form').reset(); }
          else { errEl.textContent = data.message || data.error || 'Erreur'; errEl.style.display = 'block'; }
        } catch(ex) { errEl.textContent = 'Erreur réseau'; errEl.style.display = 'block'; }
        btn.disabled = false; btn.textContent = 'Changer';
      }
    </script>

    <!-- Modal : Rapport de clôture -->
    <style>
      .cloture-kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1.25rem }
      .cloture-kpi { background:var(--background,#f8fafc); border:1px solid var(--border,#e2e8f0); border-radius:var(--radius,8px); padding:.85rem 1rem; text-align:center }
      .cloture-kpi.main { grid-column:1/-1; background:var(--primary,#0B5E88); color:#fff; border-color:transparent }
      .cloture-kpi-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; opacity:.7; margin-bottom:.25rem }
      .cloture-kpi-value { font-size:1.35rem; font-weight:800; line-height:1.2 }
      .cloture-kpi.main .cloture-kpi-value { font-size:1.6rem }
      .cloture-kpi-sub { font-size:.75rem; opacity:.65; margin-top:2px }
      .cloture-section { margin-bottom:1rem }
      .cloture-section-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted,#94a3b8); margin-bottom:.5rem; display:flex; align-items:center; gap:.4rem }
      .cloture-table { width:100%; border-collapse:collapse; font-size:.85rem }
      .cloture-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted,#94a3b8); font-weight:600; padding:.4rem .6rem; border-bottom:2px solid var(--border,#e2e8f0) }
      .cloture-table td { padding:.5rem .6rem; border-bottom:1px solid var(--border,#f1f5f9) }
      .cloture-table tr:last-child td { border-bottom:none }
      .cloture-table td:last-child { text-align:right; font-weight:600; font-family:'JetBrains Mono',monospace; font-size:.8rem }
      .cloture-table th:last-child { text-align:right }
      .cloture-table th:nth-child(2), .cloture-table td:nth-child(2) { text-align:center; width:50px }
      .cloture-empty { text-align:center; padding:2.5rem 1rem; color:var(--muted,#94a3b8) }
      .cloture-empty svg { margin-bottom:.75rem; opacity:.4 }
      @media print { .cloture-no-print { display:none!important } }
    </style>
    <div id="cloture-modal" class="modal">
      <div class="modal-content" style="max-width:600px">
        <div class="modal-header">
          <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            Rapport de clôture
          </h3>
          <button class="close-modal" onclick="document.getElementById('cloture-modal').classList.remove('active')">&times;</button>
        </div>
        <div style="padding:.25rem 1.25rem 1.25rem">
          <div class="cloture-no-print" style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem">
            <input type="date" id="cloture-date" value="<?= date('Y-m-d') ?>" style="flex:1;padding:.5rem .75rem">
            <button class="btn btn-primary btn-small" onclick="loadCloture()" style="padding:.5rem 1rem;white-space:nowrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:middle"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
              Charger
            </button>
            <button class="btn btn-secondary btn-small" onclick="printCloture()" id="cloture-print-btn" style="padding:.5rem;display:none" title="Imprimer">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </button>
          </div>
          <div id="cloture-content">
            <div class="cloture-empty">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              <p style="font-size:.9rem;margin-bottom:.25rem">Sélectionnez une date</p>
              <p style="font-size:.8rem">et cliquez <strong>Charger</strong> pour générer le rapport</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
      function fmtMoney(v) { return parseFloat(v||0).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+' Fc'; }
      async function loadCloture() {
        const date = document.getElementById('cloture-date').value;
        const el = document.getElementById('cloture-content');
        el.innerHTML = '<div style="text-align:center;padding:2rem"><div style="width:28px;height:28px;border:3px solid var(--border,#e2e8f0);border-top-color:var(--primary,#0B5E88);border-radius:50%;animation:spin .6s linear infinite;margin:0 auto 1rem"></div>Chargement du rapport...</div>';
        try {
          const res = await fetch(APP_URL + '/api/cloture?date=' + date);
          const d = await res.json();
          const t = d.totals || {};
          const dateFormatted = new Date(date).toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'});

          let html = `<div style="text-align:center;margin-bottom:1rem;font-size:.8rem;color:var(--muted,#94a3b8)">${dateFormatted}</div>`;

          // KPI cards
          html += `<div class="cloture-kpi-grid">
            <div class="cloture-kpi main">
              <div class="cloture-kpi-label">Chiffre d'affaires</div>
              <div class="cloture-kpi-value">${fmtMoney(t.total_ventes)}</div>
              <div class="cloture-kpi-sub">${t.nb_ventes||0} transaction${(t.nb_ventes||0)>1?'s':''}</div>
            </div>
            <div class="cloture-kpi">
              <div class="cloture-kpi-label">Total HT</div>
              <div class="cloture-kpi-value">${fmtMoney(t.total_ht)}</div>
            </div>
            <div class="cloture-kpi">
              <div class="cloture-kpi-label">Total TVA</div>
              <div class="cloture-kpi-value">${fmtMoney(t.total_tva)}</div>
            </div>
          </div>`;

          // Par vendeur
          if (d.by_vendeur && d.by_vendeur.length) {
            html += `<div class="cloture-section">
              <div class="cloture-section-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Ventes par vendeur
              </div>
              <table class="cloture-table"><thead><tr><th>Vendeur</th><th>Nb</th><th>Montant</th></tr></thead><tbody>`;
            d.by_vendeur.forEach(v => { html += `<tr><td>${v.nom_complet||'-'}</td><td>${v.nb}</td><td>${fmtMoney(v.total)}</td></tr>`; });
            html += '</tbody></table></div>';
          }

          // Par mode de paiement
          if (d.by_payment && d.by_payment.length) {
            html += `<div class="cloture-section">
              <div class="cloture-section-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Par mode de paiement
              </div>
              <table class="cloture-table"><thead><tr><th>Mode</th><th>Nb</th><th>Montant</th></tr></thead><tbody>`;
            const paymentLabels = { ESPECES: 'Espèces', MOBILEMONEY: 'Mobile Money', CARTEBANCAIRE: 'Carte Bancaire', VIREMENT: 'Virement' };
            d.by_payment.forEach(p => {
              const type = (p.type || p.payments || 'cash').toString().toUpperCase();
              const label = paymentLabels[type] || (type.charAt(0) + type.slice(1).toLowerCase());
              html += `<tr><td>${label}</td><td>${p.nb}</td><td>${fmtMoney(p.total)}</td></tr>`;
            });
            html += '</tbody></table></div>';
          }

          // Top produits
          if (d.top_products && d.top_products.length) {
            html += `<div class="cloture-section">
              <div class="cloture-section-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Top ${d.top_products.length} produits
              </div>
              <table class="cloture-table"><thead><tr><th>Produit</th><th>Qté</th><th>Revenu</th></tr></thead><tbody>`;
            d.top_products.forEach((p,i) => {
              const medal = i===0?'1 ':i===1?'2 ':i===2?'3 ':'';
              html += `<tr><td>${medal}${p.nom||'-'}</td><td>${p.qty}</td><td>${fmtMoney(p.revenue)}</td></tr>`;
            });
            html += '</tbody></table></div>';
          }

          // Aucune vente
          if (!t.nb_ventes || t.nb_ventes == 0) {
            html = `<div class="cloture-empty">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <p style="font-size:.9rem;margin-bottom:.25rem">Aucune vente</p>
              <p style="font-size:.8rem">pour le ${dateFormatted}</p>
            </div>`;
          }

          el.innerHTML = html;
          document.getElementById('cloture-print-btn').style.display = (t.nb_ventes && t.nb_ventes > 0) ? 'flex' : 'none';
        } catch(e) {
          el.innerHTML = '<div class="cloture-empty" style="color:#e53e3e"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:.5rem"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><p>Erreur lors du chargement du rapport</p></div>';
        }
      }
      function printCloture() {
        const content = document.getElementById('cloture-content').innerHTML;
        const w = window.open('','_blank','width=600,height=800');
        w.document.write(`<html><head><title>Rapport de clôture</title><style>
          body{font-family:system-ui,-apple-system,sans-serif;padding:2rem;color:#1e293b;max-width:550px;margin:0 auto}
          .cloture-kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.5rem}
          .cloture-kpi{border:1px solid #e2e8f0;border-radius:8px;padding:.85rem 1rem;text-align:center}
          .cloture-kpi.main{grid-column:1/-1;background:#0B5E88;color:#fff;border-color:transparent}
          .cloture-kpi-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;opacity:.7;margin-bottom:.25rem}
          .cloture-kpi-value{font-size:1.3rem;font-weight:800}
          .cloture-kpi.main .cloture-kpi-value{font-size:1.5rem}
          .cloture-kpi-sub{font-size:.7rem;opacity:.6;margin-top:2px}
          .cloture-section{margin-bottom:1rem}
          .cloture-section-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:.4rem;display:flex;align-items:center;gap:.3rem}
          .cloture-table{width:100%;border-collapse:collapse;font-size:.82rem}
          .cloture-table th{text-align:left;font-size:.65rem;text-transform:uppercase;color:#94a3b8;font-weight:600;padding:.35rem .5rem;border-bottom:2px solid #e2e8f0}
          .cloture-table td{padding:.4rem .5rem;border-bottom:1px solid #f1f5f9}
          .cloture-table td:last-child,.cloture-table th:last-child{text-align:right}
          .cloture-table th:nth-child(2),.cloture-table td:nth-child(2){text-align:center}
        </style></head><body>${content}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(()=>w.print(),300);
      }
      function openClotureModal() {
        document.getElementById('cloture-modal').classList.add('active');
      }
      function openChangePasswordModal() {
        document.getElementById('change-password-modal').classList.add('active');
      }
    </script>

    <!-- Modal : choix du format d'impression (57mm, 80mm, A4, A5, Letter, Legal) -->
    <?php include __DIR__ . '/print-format-modal.php'; ?>

    <script src="/assets/js/app.js?v=1.0.99889989999999999999999999999999999999999999999999999999999999999999"></script>
    <script src="/assets/js/recharges.js?v=1.0.1199"></script>
    <script src="/assets/js/paper-type.js?v=1.0.599"></script>

    </body>

    </html>