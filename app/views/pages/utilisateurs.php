<!-- Users Page -->
<div id="page-users" class="page admin-only">
  <div class="page-header">
    <h2>Gestion des utilisateurs</h2>
    <button id="add-user-btn" class="btn btn-primary">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      Ajouter
    </button>
  </div>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Role</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="users-table"></tbody>
    </table>
  </div>
</div>