<!-- History Page -->
<div id="page-history" class="page">
  <div class="page-header">
    <h2>Historique des ventes</h2>
  </div>
  <div class="filters-bar">
    <input type="date" id="date-filter">
    <select id="seller-filter">
      <option value="all">Tous les vendeurs</option>
    </select>
  </div>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>N Facture</th>
          <th>Date</th>
          <th>Vendeur</th>
          <th>Articles</th>
          <th>Total</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="history-table"></tbody>
    </table>
  </div>
</div>