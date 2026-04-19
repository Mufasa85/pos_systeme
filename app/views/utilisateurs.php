      <!-- Users Page -->
      <div id="page-users" class="page <?= $page == 'utilisateurs' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Gestion des utilisateurs</h2>
          <button id="add-user-btn" class="btn btn-primary" onclick="alert('Fonctionnalité d\'ajout en construction')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter
          </button>
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Utilisateur</th>
                <th>Role</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($utilisateurs as $u): ?>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.75rem;">
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.875rem; background: var(--primary);">
                      <?= substr(htmlspecialchars($u['nom_complet']), 0, 1) ?>
                    </div>
                    <div>
                      <div style="font-weight: 500;"><?= htmlspecialchars($u['nom_complet']) ?></div>
                      <div style="font-size: 0.75rem; color: var(--muted);">@<?= htmlspecialchars($u['nom_utilisateur']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="padding:0.75rem;"><span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-success' ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'Vendeur' ?></span></td>
                <td style="padding:0.75rem;"><span class="badge <?= $u['actif'] ? 'badge-success' : 'badge-danger' ?>"><?= $u['actif'] ? 'Actif' : 'Inactif' ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
