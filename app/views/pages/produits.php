<!-- Products Management Page -->
<div id="page-products" class="page">
  <div class="page-header">
    <h2>Gestion des produits</h2>
    <button id="add-product-btn" class="btn btn-primary admin-only">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      Ajouter
    </button>
  </div>
  <div class="filters-bar">
    <input type="text" id="products-filter" placeholder="Rechercher un produit...">
    <select id="category-filter">
      <option value="all">Toutes les catégories</option>
    </select>
  </div>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Nom</th>
          <th>Code-barres</th>
          <th>Categorie</th>
          <th>Prix</th>
          <th class="admin-only">Actions</th>
        </tr>
      </thead>
      <tbody id="products-table"></tbody>
    </table>
  </div>
</div>