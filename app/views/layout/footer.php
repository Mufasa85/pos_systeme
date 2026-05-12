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
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
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
                  Stock actuel
                </label>
                <input type="number" id="product-stock" min="0" required placeholder="0" style="text-align: center;">
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

    <script src="./assets/js/app.js?v=1.0.497977491"></script>
    <script src="./assets/js/recharges.js?v=1.0.3"></script>

    </body>

    </html>