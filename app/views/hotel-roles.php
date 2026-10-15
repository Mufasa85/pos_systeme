<?php
/**
 * Page "Gestion des rôles Hôtel" — contenu injecté dans le layout global.
 * Le manager (ou admin, ou super_admin) assigne chaque vendeur de SA boutique
 * à un rôle métier hôtel : aucun / manager / réceptionniste / gouvernante.
 *
 * Variables :
 *   $hotelUsers      array  Liste des users de la boutique ciblée
 *   $targetShopId    int    Boutique affichée
 *   $allShops        array  Toutes les boutiques (super_admin uniquement)
 *   $currentShopName string Nom résolu de la boutique
 */
$currentUserRoleApp = $_SESSION['role'] ?? '';
$isSuperAdminView  = $currentUserRoleApp === 'super_admin';
?>
<style>
  .hotel-roles-wrap { max-width: 920px; margin: 1.5rem auto; padding: 0 1rem; }
  .hotel-roles-card { background: var(--card-bg, #fff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.05); padding: 1.5rem; }
  .hotel-roles-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem; }
  .hotel-roles-header h1 { font-size: 1.4rem; margin: 0; color: #0f172a; }
  .hotel-roles-header .subtitle { font-size: .8rem; color: #64748b; margin-top: 4px; }
  .hotel-roles-shop-pill { background: #0B5E88; color: #fff; padding: 4px 12px; border-radius: 999px; font-size: .75rem; font-weight: 600; }
  .hotel-roles-table { width: 100%; border-collapse: collapse; }
  .hotel-roles-table th { text-align: left; font-size: .7rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: .75rem .5rem; border-bottom: 2px solid #e2e8f0; }
  .hotel-roles-table td { padding: .85rem .5rem; border-bottom: 1px solid #e2e8f0; font-size: .9rem; }
  .hotel-roles-user-cell { display: flex; align-items: center; gap: .75rem; }
  .hotel-roles-avatar { width: 36px; height: 36px; border-radius: 50%; background: #0B5E88; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: .85rem; flex-shrink: 0; }
  .hotel-roles-username { font-weight: 500; }
  .hotel-roles-handle { font-size: .75rem; color: #64748b; }
  .hotel-roles-badge-app { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 600; }
  .role-vendeur { background: #dcfce7; color: #166534; }
  .role-admin   { background: #dbeafe; color: #1e40af; }
  .role-hotel-manager        { background: #ede9fe; color: #5b21b6; }
  .role-hotel-receptionniste { background: #fef3c7; color: #92400e; }
  .role-hotel-gouvernante    { background: #fce7f3; color: #9d174d; }
  .hotel-roles-select { padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; font-size: .85rem; cursor: pointer; min-width: 170px; }
  .hotel-roles-select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: .6; }
  .hotel-roles-status { font-size: .75rem; color: #64748b; margin-top: 4px; }
  .hotel-roles-status.saving { color: #d97706; }
  .hotel-roles-status.saved  { color: #16a34a; }
  .hotel-roles-status.error  { color: #dc2626; }
  .hotel-roles-empty { padding: 3rem; text-align: center; color: #64748b; }
  .hotel-roles-back { display: inline-flex; align-items: center; gap: 6px; color: #0B5E88; text-decoration: none; font-size: .9rem; margin-bottom: 1rem; }
  .hotel-roles-back:hover { text-decoration: underline; }
  .hotel-roles-shop-selector { margin-bottom: 1.25rem; padding: .75rem 1rem; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
  .hotel-roles-shop-selector label { font-size: .8rem; font-weight: 600; color: #475569; margin-right: .5rem; }
  .hotel-roles-shop-selector select { padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: .85rem; }
  .hotel-roles-legend { background: #f8fafc; padding: 1rem; border-radius: 8px; font-size: .8rem; color: #475569; margin-bottom: 1.25rem; }
  .hotel-roles-legend strong { color: #0f172a; }
</style>

<main class="hotel-roles-wrap">
  <a href="/hotel" class="hotel-roles-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
    Retour au tableau de bord hôtel
  </a>

  <div class="hotel-roles-card">
    <div class="hotel-roles-header">
      <div>
        <h1>Gestion des rôles Hôtel</h1>
        <div class="subtitle">Assignez vos vendeurs à un rôle métier hôtel (manager, réceptionniste, gouvernante) ou laissez "Aucun accès" pour qu'ils restent uniquement sur la caisse.</div>
      </div>
      <span class="hotel-roles-shop-pill"><?= htmlspecialchars($currentShopName) ?></span>
    </div>

    <?php if ($isSuperAdminView && !empty($allShops)): ?>
      <div class="hotel-roles-shop-selector">
        <label for="shop-switch">Boutique affichée :</label>
        <select id="shop-switch" onchange="window.location.href='/hotel/roles?shop_id='+encodeURIComponent(this.value)">
          <?php foreach ($allShops as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)$targetShopId) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['nom'] ?? ('Boutique #' . $s['id'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="hotel-roles-legend">
      <strong>Comment ça marche :</strong>
      Les vendeurs (rôle applicatif) peuvent se voir attribuer un <strong>rôle métier hôtel</strong> distinct.
      Les <strong>administrateurs</strong> ont automatiquement le rôle <em>manager</em> sur leur boutique — inutile de le leur assigner.
      Les <strong>super_admin</strong> voient toutes les boutiques via le sélecteur ci-dessus.
    </div>

    <?php if (empty($hotelUsers)): ?>
      <div class="hotel-roles-empty">
        Aucun utilisateur trouvé pour cette boutique.
      </div>
    <?php else: ?>
      <table class="hotel-roles-table">
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Rôle applicatif</th>
            <th>Rôle Hôtel</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody id="hotel-roles-tbody">
          <?php foreach ($hotelUsers as $u): ?>
            <?php
              $appRole = $u['role'];
              $hotelRole = $u['hotel_role']; // null|manager|receptionniste|gouvernante
              $isAdminAuto = ($appRole === 'admin');
            ?>
            <tr data-user-id="<?= (int)$u['id'] ?>">
              <td>
                <div class="hotel-roles-user-cell">
                  <div class="hotel-roles-avatar"><?= htmlspecialchars(mb_substr($u['nom_complet'] ?? '?', 0, 1)) ?></div>
                  <div>
                    <div class="hotel-roles-username"><?= htmlspecialchars($u['nom_complet'] ?? '') ?></div>
                    <div class="hotel-roles-handle">@<?= htmlspecialchars($u['nom_utilisateur'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="hotel-roles-badge-app role-<?= htmlspecialchars($appRole) ?>">
                  <?= $appRole === 'admin' ? 'Admin (auto-manager)' : 'Vendeur' ?>
                </span>
              </td>
              <td>
                <?php if ($isAdminAuto): ?>
                  <select class="hotel-roles-select" disabled>
                    <option>Manager (auto)</option>
                  </select>
                  <div class="hotel-roles-status">Auto-assigné par le rôle admin</div>
                <?php else: ?>
                  <select class="hotel-roles-select" data-user-id="<?= (int)$u['id'] ?>" data-original="<?= htmlspecialchars($hotelRole ?? '') ?>">
                    <option value=""          <?= $hotelRole === null ? 'selected' : '' ?>>Aucun accès hôtel</option>
                    <option value="manager"        <?= $hotelRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="receptionniste" <?= $hotelRole === 'receptionniste' ? 'selected' : '' ?>>Réceptionniste</option>
                    <option value="gouvernante"    <?= $hotelRole === 'gouvernante' ? 'selected' : '' ?>>Gouvernante</option>
                  </select>
                  <div class="hotel-roles-status" id="status-<?= (int)$u['id'] ?>">Sélection actuelle</div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$u['actif'] === 1): ?>
                  <span class="hotel-roles-badge-app role-vendeur">Actif</span>
                <?php else: ?>
                  <span class="hotel-roles-badge-app" style="background:#fee2e2;color:#991b1b">Inactif</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</main>

<script>
(function() {
  const APP_URL = window.location.origin;
  document.querySelectorAll('.hotel-roles-select:not([disabled])').forEach(function(sel) {
    sel.addEventListener('change', async function() {
      const userId = this.dataset.userId;
      const newRole = this.value; // '' pour aucun
      const original = this.dataset.original || '';
      const statusEl = document.getElementById('status-' + userId);
      statusEl.className = 'hotel-roles-status saving';
      statusEl.textContent = 'Enregistrement…';
      try {
        const form = new FormData();
        form.append('user_id', userId);
        form.append('hotel_role', newRole);
        const res = await fetch(APP_URL + '/api/hotel/role/update', {
          method: 'POST',
          credentials: 'same-origin',
          body: form,
        });
        const data = await res.json();
        if (data.success) {
          const labels = {
            '': 'Aucun accès hôtel',
            'manager': 'Manager',
            'receptionniste': 'Réceptionniste',
            'gouvernante': 'Gouvernante',
          };
          statusEl.className = 'hotel-roles-status saved';
          statusEl.textContent = '\u2713 ' + (labels[newRole] || 'Mis à jour');
          this.dataset.original = newRole;
        } else {
          statusEl.className = 'hotel-roles-status error';
          statusEl.textContent = '\u2717 ' + (data.message || 'Erreur');
          this.value = original;
        }
      } catch (e) {
        statusEl.className = 'hotel-roles-status error';
        statusEl.textContent = '\u2717 Erreur réseau';
        this.value = original;
      }
    });
  });
})();
</script>
