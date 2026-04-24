    </main>
    </div>

    <!-- Receipt Modal -->
    <div id="receipt-modal" class="modal">
      <div class="modal-content receipt-modal">
        <div class="receipt-scrollable">
          <div id="receipt-content"></div>
        </div>
        <div class="receipt-actions">
          <button id="close-receipt" class="btn btn-secondary" onclick="document.getElementById('receipt-modal').classList.remove('active')">
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
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="posCart.closePreview()">Annuler</button>
          <button type="button" id="confirm-sale" class="btn btn-primary" onclick="posCart.confirmSale()">
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
      <div class="modal-content">
        <div class="modal-header">
          <h3 id="product-modal-title">Ajouter un produit</h3>
          <button class="close-modal" onclick="document.getElementById('product-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="product-form">
          <input type="hidden" id="product-id">
          <div class="form-row">
            <div class="form-group">
              <label>Code-barres</label>
              <div style="display:flex; gap:0.5rem;">
                <input type="text" id="product-barcode" required style="flex:1;">
                <button type="button" class="btn btn-secondary" onclick="generateBarcode()" title="Générer un code-barres" style="padding: 0 10px;">Générer</button>
              </div>
            </div>
            <div class="form-group">
              <label>Nom</label>
              <input type="text" id="product-name" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Catégorie</label>
              <select id="product-category" required>
                <option value="">Chargement...</option>
              </select>
            </div>
            <div class="form-group">
              <label>Prix (Fc)</label>
              <input type="number" id="product-price" step="0.01" min="0" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Stock</label>
              <input type="number" id="product-stock" min="0" required>
            </div>
            <div class="form-group">
              <label>Stock minimum</label>
              <input type="number" id="product-min-stock" min="0" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group" style="flex: 1;">
              <label>Image Produit (Optionnel)</label>
              <input type="file" id="product-image" accept="image/*">
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- User Modal -->
    <div id="user-modal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3 id="user-modal-title">Ajouter un utilisateur</h3>
          <button class="close-modal" onclick="document.getElementById('user-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="user-form">
          <input type="hidden" id="user-id">
          <div class="form-row">
            <div class="form-group">
              <label>Nom d'utilisateur</label>
              <input type="text" id="user-username" required>
            </div>
            <div class="form-group">
              <label>Mot de passe</label>
              <input type="password" id="user-password" placeholder="(Laisser vide si inchangé)">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Nom Complet</label>
              <input type="text" id="user-fullname" required>
            </div>
            <div class="form-group">
              <label>Rôle</label>
              <select id="user-role" required>
                <option value="vendeur">Vendeur</option>
                <option value="admin">Administrateur</option>
              </select>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary close-modal" onclick="document.getElementById('user-modal').classList.remove('active')">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
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

    <script src="./assets/js/app.js"></script>
    </body>

    </html>