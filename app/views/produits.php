      <!-- Products Management Page -->
      <div id="page-products" class="page <?= $page == 'produits' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Gestion des produits</h2>
          <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'], true)): ?>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
              <button id="import-products-btn" class="btn btn-secondary" onclick="openProductImportModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="17 8 12 3 7 8"></polyline>
                  <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Importer (Excel)
              </button>
              <button id="add-product-btn" class="btn btn-primary" onclick="openProductModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Ajouter
              </button>
            </div>
          <?php endif; ?>
        </div>
<div class="filters-bar">
          <input type="text" id="products-filter" placeholder="Rechercher un produit...">
          <select id="category-filter">
            <option value="all">Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
            <select id="shop-filter">
              <option value="all">Toutes les boutiques</option>
              <?php foreach ($shops ?? [] as $shop): ?>
                <option value="<?= htmlspecialchars($shop['id']) ?>"><?= htmlspecialchars($shop['nom']) ?></option>
              <?php endforeach; ?>
            </select>
            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--text-secondary,#475569);cursor:pointer">
              <input type="checkbox" id="include-inactive-shops" <?= !empty($includeInactive) ? 'checked' : '' ?>>
              Inclure boutiques inactives
            </label>
          <?php endif; ?>
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Image</th>
                <th>Nom</th>
                <th>Code-barres</th>
                <th>Categorie</th>
                <th>Stock</th>
                <th>Péremption</th>
                <th>Prix</th>
                <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?><th>Boutique</th><?php endif; ?>
                <th class="admin-only">Actions</th>
              </tr>
            </thead>
            <tbody id="products-table">
              <?php foreach ($produits as $p): ?>
                <tr data-category="<?= htmlspecialchars($p['categorie']) ?>" data-shop="<?= htmlspecialchars($p['shop_id'] ?? '') ?>" style="border-bottom:1px solid #eee;">
                  <td style="padding:0.75rem;">
                    <img src="<?= $p['image'] ?>" alt="<?= htmlspecialchars($p['nom']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" onerror="this.style.display='none'">
                  </td>
                  <td style="padding:0.75rem;"><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                  <td style="padding:0.75rem;"><code class="barcode-code"><?= htmlspecialchars($p['code_barres']) ?></code></td>
                  <td style="padding:0.75rem;"><span class="badge badge-primary"><?= htmlspecialchars($p['categorie']) ?></span></td>
                  <td style="padding:0.75rem;">
                    <?php $totalStock = $p['total_stock'] ?? $p['stock']; ?>
                    <span style="color: <?= $totalStock <= $p['stock_minimum'] ? 'red' : 'green' ?>">
                      <?= number_format($totalStock, 2) ?>
                    </span>
                  </td>
                  <td style="padding:0.75rem;">
                    <?php if (!empty($p['nearest_expiration_date'])): ?>
                      <?php
                        $days = (strtotime($p['nearest_expiration_date']) - strtotime(date('Y-m-d'))) / 86400;
                        $color = $days < 0 ? 'red' : ($days <= 7 ? 'orange' : 'green');
                        ?>
                      <span style="color: <?= $color ?>; font-size: 0.85rem;">
                        <?= date('d/m/Y', strtotime($p['nearest_expiration_date'])) ?>
                        <?= $days < 0 ? '(périmé)' : '(' . floor($days) . 'j)' ?>
                      </span>
                    <?php else: ?>
                      <span style="color: #999; font-size: 0.85rem;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:0.75rem;"><strong><?= number_format($p['prix'], 2) ?> Fc</strong></td>
                  <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                    <td style="padding:0.75rem;">
                      <span class="badge <?= empty($p['shop_actif']) ? 'badge-danger' : 'badge-info' ?>">
                        <?= htmlspecialchars($p['shop_name'] ?? 'N/A') ?>
                        <?php if (isset($p['shop_actif']) && empty($p['shop_actif'])): ?>
                          <span style="font-size:.65rem;margin-left:.25rem;opacity:.85">(inactif)</span>
                        <?php endif; ?>
                      </span>
                    </td>
                  <?php endif; ?>
                  <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <td style="padding:0.75rem;">
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

      <!-- Modal : import de produits depuis un fichier Excel / CSV -->
      <div id="product-import-modal" class="modal">
        <div class="modal-content pi-modal">
          <div class="modal-header">
            <h3 id="product-import-modal-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              Importer des produits
            </h3>
            <button class="close-modal" onclick="closeProductImportModal()">&times;</button>
          </div>

          <div class="pi-body">
            <!-- Étape 1 : choix du fichier -->
            <div id="product-import-step-form">
              <div class="pi-section">
                <h4 class="pi-section-title">1. Fichier à importer</h4>
                <label class="pi-file-label" for="product-import-file">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                  </svg>
                  <span id="product-import-filename">Choisir un fichier…</span>
                </label>
                <input type="file" id="product-import-file" accept=".xlsx,.xls,.csv,.txt" class="pi-file-input" onchange="onProductImportFileSelected()">
                <small class="pi-hint">Formats acceptés : .xlsx, .xls, .csv (5 Mo maximum).</small>
              </div>

              <div class="pi-section">
                <h4 class="pi-section-title">2. Options</h4>

                <div class="pi-field">
                  <label for="product-import-mode">Si le produit existe déjà</label>
                  <select id="product-import-mode">
                    <option value="create">Ignorer (conserver le produit existant)</option>
                    <option value="update">Mettre à jour (écrase les données)</option>
                  </select>
                </div>

                <div class="pi-field">
                  <label for="product-import-default-category">Catégorie par défaut</label>
                  <select id="product-import-default-category">
                    <option value="">Aucune</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['category']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="pi-hint">Appliquée si la colonne « categorie » est vide.</small>
                </div>

                <div class="pi-field">
                  <label for="product-import-default-tax">Taxe par défaut</label>
                  <select id="product-import-default-tax">
                    <option value="">Groupe A (exonéré)</option>
                    <?php foreach ($taxes ?? [] as $tax): ?>
                      <option value="<?= (int)$tax['id'] ?>"><?= htmlspecialchars($tax['groupe_taxe']) ?> - <?= htmlspecialchars($tax['etiquette']) ?> (<?= $tax['taux'] ?>%)</option>
                    <?php endforeach; ?>
                  </select>
                  <small class="pi-hint">Appliquée si la colonne « taxe » est vide.</small>
                </div>

                <label class="pi-check">
                  <input type="checkbox" id="product-import-create-categories">
                  <span>Créer automatiquement les catégories absentes</span>
                </label>
              </div>

              <div class="pi-columns">
                <strong>Colonnes attendues</strong>
                <code>code_barres</code>, <code>nom</code>, <code>categorie</code>, <code>prix</code>,
                <code>stock</code>, <code>stock_minimum</code>, <code>taxe</code>, <code>product_type</code>,
                <code>prod_service</code>, <code>remise_type</code>, <code>remise_value</code>,
                <code>taxe_specifique_type</code>, <code>taxe_specifique_value</code>, <code>date_expiration</code>.
                <span class="pi-hint">Seuls <b>nom</b>, <b>categorie</b> et <b>prix</b> sont obligatoires.</span>
              </div>

              <button type="button" class="btn btn-ghost btn-small pi-template-btn" onclick="downloadProductImportTemplate()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Télécharger le modèle
              </button>
            </div>

            <!-- Étape 2 : résultat -->
            <div id="product-import-step-result" style="display:none;">
              <div id="product-import-summary" class="pi-summary"></div>
              <div class="pi-report">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Ligne</th>
                      <th>Produit</th>
                      <th>Statut</th>
                      <th>Détail</th>
                    </tr>
                  </thead>
                  <tbody id="product-import-rows"></tbody>
                </table>
              </div>
            </div>

            <div class="modal-actions pi-actions">
              <button type="button" class="btn btn-secondary" id="product-import-cancel-btn" onclick="closeProductImportModal()">Fermer</button>
              <button type="button" class="btn btn-primary" id="product-import-submit-btn" onclick="submitProductImport()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="17 8 12 3 7 8"></polyline>
                  <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Lancer l'import
              </button>
            </div>
          </div>
        </div>
      </div>
