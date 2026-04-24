      <!-- Users Page -->
      <div id="page-users" class="page <?= $page == 'utilisateurs' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Gestion des utilisateurs</h2>
          <button id="add-user-btn" class="btn btn-primary" onclick="openAddUserModal()">
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
              <?php foreach ($utilisateurs as $u): ?>
                <tr style="border-bottom:1px solid #eee;" data-user-id="<?= $u['id'] ?>">
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
                  <td style="padding:0.75rem; text-align:right;">
                    <button class="btn btn-ghost btn-small" onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nom_utilisateur'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['nom_complet'], ENT_QUOTES) ?>', '<?= $u['role'] ?>', <?= $u['actif'] ?>)" title="Modifier">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
                      </svg>
                    </button>
                    <button class="btn btn-ghost btn-small" style="color:red;" onclick="deleteUser(<?= $u['id'] ?>)" title="Supprimer">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal Ajouter/Modifier Utilisateur -->
      <div id="user-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3 id="user-modal-title" style="margin:0;">Ajouter un utilisateur</h3>
            <button onclick="closeUserModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
          </div>
          <form id="user-form" onsubmit="return saveUser(event)">
            <input type="hidden" id="user-id" name="id" value="">
            <div style="margin-bottom:1rem;">
              <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom d'utilisateur</label>
              <input type="text" id="user-username" name="username" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="margin-bottom:1rem;">
              <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom complet</label>
              <input type="text" id="user-fullname" name="fullname" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="margin-bottom:1rem;">
              <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Mot de passe <span id="password-hint" style="font-weight:normal; color:#666;">(laisser vide pour ne pas changer)</span></label>
              <input type="password" id="user-password" name="password" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="margin-bottom:1rem;">
              <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Rôle</label>
              <select id="user-role" name="role" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
                <option value="vendeur">Vendeur</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div style="margin-bottom:1.5rem;">
              <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Statut</label>
              <select id="user-actif" name="actif" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
                <option value="1">Actif</option>
                <option value="0">Inactif</option>
              </select>
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
              <button type="button" onclick="closeUserModal()" class="btn btn-secondary">Annuler</button>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
          </form>
        </div>
      </div>