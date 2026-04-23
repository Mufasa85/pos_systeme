<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System - Caisse Professionnelle</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="mobile-caisse.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
</head>
<body>
  <!-- Login Page Container -->
  <div id="login-container"></div>

  <!-- Main App -->
  <div id="main-app" class="main-app hidden">
    <!-- Mobile Header Container -->
    <div id="header-container"></div>
    
    <!-- Sidebar Container -->
    <aside id="sidebar-container" class="sidebar-container"></aside>

    <!-- Main Content -->
    <main class="main-content">
      <div id="page-container"></div>
    </main>
  </div>

  <!-- Modals (always available) -->
  <!-- Receipt Modal -->
  <div id="receipt-modal" class="modal">
    <div class="modal-content receipt-modal">
      <div id="receipt-content" class="receipt"></div>
      <div class="receipt-actions">
        <button id="validate-receipt" class="btn btn-primary">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          Valider la facture
        </button>
        <button id="print-receipt" class="btn btn-success hidden">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          Imprimer
        </button>
        <button id="close-receipt" class="btn btn-secondary">Fermer</button>
      </div>
    </div>
  </div>

  <!-- Product Modal -->
  <div id="product-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="product-modal-title">Ajouter un produit</h3>
        <button class="close-modal">&times;</button>
      </div>
      <form id="product-form">
        <div class="form-row">
          <div class="form-group">
            <label>Code-barres</label>
            <input type="text" id="product-barcode" required>
          </div>
          <div class="form-group">
            <label>Nom</label>
            <input type="text" id="product-name" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Categorie</label>
          <select id="product-category" required>
              <option value="">Sélectionner une catégorie...</option>
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
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary close-modal">Annuler</button>
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
        <button class="close-modal">&times;</button>
      </div>
      <form id="user-form">
        <div class="form-group">
          <label>Nom d'utilisateur</label>
          <input type="text" id="user-username" required>
        </div>
        <div class="form-group">
          <label>Mot de passe</label>
          <input type="password" id="user-password">
          <small id="password-hint">Laissez vide pour conserver l'ancien mot de passe</small>
        </div>
        <div class="form-group">
          <label>Nom complet</label>
          <input type="text" id="user-fullname" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select id="user-role" required>
            <option value="vendeur">Vendeur</option>
            <option value="admin">Administrateur</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary close-modal">Annuler</button>
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
        <button class="close-modal">&times;</button>
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
          <button type="button" class="btn btn-secondary close-modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <script src="templates.js"></script>
  <script src="app.js"></script>
</body>
</html>