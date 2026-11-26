      <!-- Products Management Page -->
      <div id="page-products" class="page <?= $page == 'produits' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Gestion des produits</h2>
          <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'], true)): ?>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
              <button id="export-products-btn" class="btn btn-secondary" onclick="exportProductsToExcel()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Exporter (Excel)
              </button>
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
<?php
        // ⚠️ Le filtre est désormais appliqué CÔTÉ SERVEUR (pagination
        // serveur). Le former filtre DOM parcourait 50 000 <tr> à chaque
        // frappe (3 querySelector par ligne) et gelait le navigateur.
        //
        // Le <form> est en GET : la requête se fait dans l'URL, ce qui
        // rend la page partageable et le bouton « Précédent » du
        // navigateur cohérent.
        $qsBase = function (array $overrides = []) use ($limit, $search, $category, $shopFilter, $includeInactive) {
            $params = array_merge([
                'limit'   => $limit,
                'offset'  => 0,
                'search'  => $search,
                'category'=> $category,
            ], $overrides);
            if (!empty($params['search'])) {
                $params['search'] = $params['search'];
            } else {
                unset($params['search']);
            }
            if (empty($params['category']) || $params['category'] === 'all') {
                unset($params['category']);
            }
            if (!empty($shopFilter)) {
                $params['shop_id'] = $shopFilter;
            }
            if (!empty($includeInactive)) {
                $params['include_inactive'] = 1;
            }
            return '/produits?' . http_build_query($params);
        };
        ?>
        <form class="filters-bar" method="GET" action="/produits" id="products-filters">
          <input type="hidden" name="limit" value="<?= (int)$limit ?>">
          <!-- Remis à 0 par le JS quand un filtre change : l'offset
               courant n'aurait aucun sens avec un autre filtre. -->
          <input type="hidden" name="offset" value="0">
          <input type="text" name="search" id="products-filter" placeholder="Rechercher un produit..."
                 value="<?= htmlspecialchars($search) ?>">
          <select name="category" id="category-filter">
            <option value="all">Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($category === $cat['category']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['category']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
            <select name="shop_id" id="shop-filter">
              <option value="all">Toutes les boutiques</option>
              <?php foreach ($shops ?? [] as $shop): ?>
                <option value="<?= htmlspecialchars($shop['id']) ?>" <?= ((string)$shopFilter === (string)$shop['id']) ? 'selected' : '' ?>><?= htmlspecialchars($shop['nom']) ?></option>
              <?php endforeach; ?>
            </select>
            <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--text-secondary,#475569);cursor:pointer">
              <input type="checkbox" name="include_inactive" value="1" id="include-inactive-shops" <?= !empty($includeInactive) ? 'checked' : '' ?>>
              Inclure boutiques inactives
            </label>
          <?php endif; ?>
          <button type="submit" class="btn btn-secondary btn-small">Filtrer</button>
        </form>
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

        <!-- ══ PAGINATION ══════════════════════════════════════════════
             ⚠️ Serveur-side : la page ne contient qu'une page de produits.
             À 50 000 produits, le rendu de TOUTES les lignes produisait
             116 Mo de HTML et un DOM de 50 000 <tr>. -->
        <?php
            $total       = (int)($total ?? 0);
            $limit       = max(1, (int)($limit ?? 50));
            $offset      = max(0, (int)($offset ?? 0));
            $nbLignes    = count($produits);
            $pageCourante = (int) floor($offset / $limit) + 1;
            $nbPages     = max(1, (int) ceil($total / $limit));
            $debut       = $nbLignes > 0 ? $offset + 1 : 0;
            $fin         = $offset + $nbLignes;
        ?>
        <div class="products-pagination">
          <div class="pagination-info">
            <?php if ($total > 0): ?>
              <?= number_format($debut) ?>–<?= number_format($fin) ?>
              sur <?= number_format($total) ?> produit<?= $total > 1 ? 's' : '' ?>
            <?php else: ?>
              Aucun produit
            <?php endif; ?>
          </div>
          <?php if ($nbPages > 1): ?>
            <div class="pagination-buttons">
              <a class="btn btn-small"
                 href="<?= htmlspecialchars($qsBase(['offset' => 0])) ?>"
                 title="Première page">&laquo; Première</a>
              <a class="btn btn-small<?= $offset <= 0 ? ' is-disabled' : '' ?>"
                 href="<?= htmlspecialchars($qsBase(['offset' => max(0, $offset - $limit)])) ?>"
                 title="Page précédente"
                 <?= $offset <= 0 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&lsaquo; Précédent</a>
              <span class="pagination-page">Page <?= number_format($pageCourante) ?> / <?= number_format($nbPages) ?></span>
              <a class="btn btn-small<?= ($offset + $limit) >= $total ? ' is-disabled' : '' ?>"
                 href="<?= htmlspecialchars($qsBase(['offset' => $offset + $limit])) ?>"
                 title="Page suivante"
                 <?= ($offset + $limit) >= $total ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Suivant &rsaquo;</a>
              <a class="btn btn-small"
                 href="<?= htmlspecialchars($qsBase(['offset' => ($nbPages - 1) * $limit])) ?>"
                 title="Dernière page">Dernière &raquo;</a>
            </div>
          <?php endif; ?>
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
              <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                <div class="pi-section">
                  <h4 class="pi-section-title">1. Boutique de destination</h4>
                  <div class="pi-field">
                    <label for="product-import-shop">Boutique qui va accueillir les produits</label>
                    <select id="product-import-shop" required onchange="onProductImportShopChanged()">
                      <option value="">— Choisir une boutique —</option>
                      <?php foreach ($shops ?? [] as $shop): ?>
                        <option value="<?= (int)$shop['id'] ?>" <?= empty($shop['actif']) ? 'disabled' : '' ?>>
                          <?= htmlspecialchars($shop['nom']) ?><?= empty($shop['actif']) ? ' (désactivée)' : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <small class="pi-hint">Obligatoire : les produits seront rattachés à cette boutique.</small>
                  </div>
                </div>
              <?php endif; ?>

              <div class="pi-section">
                <h4 class="pi-section-title"><?= ($_SESSION['role'] ?? '') === 'super_admin' ? '2' : '1' ?>. Fichier à importer</h4>
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
                <h4 class="pi-section-title"><?= ($_SESSION['role'] ?? '') === 'super_admin' ? '3' : '2' ?>. Options</h4>

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

            <!-- Étape 2 : avancement (mode « par blocs ») -->
            <div id="product-import-step-progress" style="display:none;">
              <div class="pi-progress">
                <div class="pi-progress-head">
                  <span id="pi-progress-percent" class="pi-progress-percent">0 %</span>
                  <span id="pi-progress-count" class="pi-progress-count">0 / 0</span>
                </div>

                <div class="pi-progress-track">
                  <div id="pi-progress-bar" class="pi-progress-bar" style="width:0%"></div>
                </div>

                <div class="pi-progress-meta">
                  <span id="pi-progress-eta">Préparation…</span>
                  <span id="pi-progress-elapsed"></span>
                </div>

                <div class="pi-progress-latest">
                  <strong>Derniers produits traités</strong>
                  <ul id="pi-progress-names" class="pi-progress-names"></ul>
                </div>

                <p id="pi-progress-note" class="pi-hint">
                  L'import est découpé en blocs : chaque bloc est enregistré puis
                  le suivant est traité. Vous pouvez laisser cette fenêtre ouverte.
                </p>
              </div>
            </div>

            <!-- Étape 3 : résultat -->
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
              <button type="button" class="btn btn-ghost btn-small" id="product-import-abort-btn" onclick="abortProductImport()" style="display:none;">
                Abandonner
              </button>
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
