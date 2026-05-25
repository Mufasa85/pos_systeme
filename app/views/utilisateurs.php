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
                  <td style="padding:0.75rem;">
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
      <div id="user-modal" class="modal">
        <div class="modal-content" style="max-width: 450px; padding: 0;">
          <div class="modal-header">
            <h3 id="user-modal-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Ajouter un utilisateur
            </h3>
            <button class="close-modal" onclick="closeUserModal()">&times;</button>
          </div>
          <form id="user-form" onsubmit="return saveUser(event)" style="padding: 1.5rem;">
            <input type="hidden" id="user-id" name="id" value="">
            <div style="margin-bottom: 1rem;">
              <label for="user-agent-code" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Code Agent</label>
              <input type="text" id="user-agent-code" name="agent_code" class="client-number-input" style="width: 100%;" placeholder="Ex: AG001">
            </div>
            <div style="margin-bottom: 1rem;">
              <label for="user-token" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Token API</label>
              <input type="text" id="user-token" name="token" class="client-number-input" style="width: 100%;" placeholder="Token pour API externe">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
              <div>
                <label for="user-username" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Nom d'utilisateur</label>
                <input type="text" id="user-username" name="username" required class="client-number-input" style="width: 100%;">
              </div>
              <div>
                <label for="user-fullname" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Nom complet</label>
                <input type="text" id="user-fullname" name="fullname" required class="client-number-input" style="width: 100%;">
              </div>
            </div>
            <div style="margin-bottom: 1rem;">
              <label for="user-password" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Mot de passe <span id="password-hint" style="font-weight: normal; font-size: 0.7rem;">(laisser vide pour ne pas changer)</span></label>
              <input type="password" id="user-password" name="password" class="client-number-input" style="width: 100%;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
              <div>
                <label for="user-role1" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Rôle</label>
                <select id="user-role1" name="role" class="client-number-input" style="width: 100%;">
                  <option value="vendeur">Vendeur</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div>
                <label for="user-actif" style="font-size: 0.75rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Statut</label>
                <select id="user-actif" name="actif" class="client-number-input" style="width: 100%;">
                  <option value="1">Actif</option>
                  <option value="0">Inactif</option>
                </select>
              </div>
            </div>
            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
              <button type="button" onclick="closeUserModal()" class="btn btn-secondary" style="flex: 1;">Annuler</button>
              <button type="submit" class="btn btn-primary" style="flex: 2;">Enregistrer</button>
            </div>
          </form>
        </div>
      </div>
