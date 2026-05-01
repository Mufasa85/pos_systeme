<!-- Categories Page -->
<div id="page-categories" class="page <?= $page == 'categories' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>Gestion des catégories</h2>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <button id="add-category-btn" class="btn btn-primary" onclick="openCategoryModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Ajouter
      </button>
    <?php endif; ?>
  </div>
  <div class="filters-bar">
    <input type="text" id="categories-filter" placeholder="Rechercher une catégorie...">
  </div>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Couleur</th>
          <th>Produits</th>
          <th class="admin-only">Actions</th>
        </tr>
      </thead>
      <tbody id="categories-table">
        <?php if (!empty($categories)): ?>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td style="padding:0.75rem;"><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
              <td style="padding:0.75rem;">
                <span style="display:inline-block;width:20px;height:20px;background:<?= htmlspecialchars($c['couleur']) ?>;border-radius:4px;"></span>
                <code style="margin-left:0.5rem;"><?= htmlspecialchars($c['couleur']) ?></code>
              </td>
              <td style="padding:0.75rem;"><?= $c['nombre_produits'] ?? 0 ?></td>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <td style="padding:0.75rem;">
                  <button class="btn btn-ghost btn-small" onclick="editCategory(<?= htmlspecialchars(json_encode($c)) ?>)" title="Modifier">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
                    </svg>
                  </button>
                  <button class="btn btn-ghost btn-small" style="color:red;" onclick="deleteCategory(<?= $c['id'] ?>)" title="Supprimer">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                  </button>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" style="text-align:center; padding:2rem; color:var(--muted);">Aucune catégorie trouvée</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Ajouter/Modifier Categorie -->
<div id="category-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 id="category-modal-title" style="margin:0;">Ajouter une catégorie</h3>
      <button onclick="closeCategoryModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <form id="category-form" onsubmit="return saveCategory(event)">
      <input type="hidden" id="category-id" name="id" value="">
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom de la catégorie</label>
        <input type="text" id="category-name" name="category" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
        <button type="button" onclick="closeCategoryModal()" class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>