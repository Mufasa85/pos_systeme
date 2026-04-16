      <!-- Products Management Page -->
      <div class="page active" style="display:block;">
        <div class="page-header">
          <h2>Gestion des produits</h2>
          <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <button id="add-product-btn" class="btn btn-primary" onclick="openProductModal()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter
          </button>
          <?php endif; ?>
        </div>
        <div class="filters-bar" style="display:flex; gap:1rem; margin-bottom:1rem;">
          <input type="text" id="products-filter" placeholder="Rechercher un produit..." style="padding:0.5rem; border:1px solid #ccc; border-radius:4px;">
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead style="background:var(--background); text-align:left;">
              <tr>
                <th style="padding:0.75rem;">Nom</th>
                <th style="padding:0.75rem;">Code-barres</th>
                <th style="padding:0.75rem;">Catégorie</th>
                <th style="padding:0.75rem;">Stock</th>
                <th style="padding:0.75rem;">Prix</th>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <th style="padding:0.75rem; text-align:right;">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody id="products-table">
              <?php foreach($produits as $p): ?>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.75rem;"><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                <td style="padding:0.75rem;"><code class="barcode-code"><?= htmlspecialchars($p['code_barres']) ?></code></td>
                <td style="padding:0.75rem;"><span class="badge badge-primary"><?= htmlspecialchars($p['categorie']) ?></span></td>
                <td style="padding:0.75rem;">
                   <span style="color: <?= $p['stock'] <= $p['stock_minimum'] ? 'red' : 'green' ?>">
                      <?= $p['stock'] ?>
                   </span>
                </td>
                <td style="padding:0.75rem;"><strong><?= number_format($p['prix'], 2) ?> Fc</strong></td>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <td style="padding:0.75rem; text-align:right;">
                  <button class="btn btn-ghost btn-small" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" title="Modifier">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
                    </svg>
                  </button>
                  <button class="btn btn-ghost btn-small" style="color:red;" onclick="deleteProduct(<?= $p['id'] ?>)" title="Supprimer">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                  </button>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
