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

// --- Contrat de variables injectées par PageController::hotelRoles() ---
// Ces variables existent à l'exécution (extract($data) dans render()).
// Les déclarations ci-dessous ne servent qu'à supprimer les warnings de
// l'IDE et à documenter explicitement le contrat. Aucun effet runtime.
// @phpstan-ignore-next-line
$hotelUsers      = $hotelUsers      ?? [];
// @phpstan-ignore-next-line
$targetShopId    = $targetShopId    ?? 0;
// @phpstan-ignore-next-line
$allShops        = $allShops        ?? [];
// @phpstan-ignore-next-line
$currentShopName = $currentShopName ?? '';
// ── Compteurs par rôle (utilisés par les cards-filtres) ──────────
$rolesCount = [
  'all'         => is_array($hotelUsers) ? count($hotelUsers) : 0,
  'none'        => 0,
  'manager'     => 0,
  'receptionniste' => 0,
  'gouvernante' => 0,
];
if (is_array($hotelUsers)) {
    foreach ($hotelUsers as $_u) {
        $_r = $_u['hotel_role'] ?? null;
        if ($_r === null || $_r === '') {
            $rolesCount['none']++;
        } elseif (isset($rolesCount[$_r])) {
            $rolesCount[$_r]++;
        }
    }
}
?>
<style>
  /* Conteneur large & spacieux (SaaS moderne), reste responsive. */
  .hotel-roles-wrap {
    max-width: 1600px;
    width: 100%;
    margin: 1.25rem auto;
    padding: 0 1.25rem;
    overflow-x: hidden;        /* Filet anti-overflow */
  }
  @media (max-width: 640px) {
    .hotel-roles-wrap { padding: 0 .75rem; }
  }
  .hotel-roles-card { background: var(--card-bg, #fff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.05); padding: 1.5rem; min-width: 0; }
  .hotel-roles-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .75rem; }
  .hotel-roles-header h1 { font-size: 1.4rem; margin: 0; color: #0f172a; }
  .hotel-roles-header .subtitle { font-size: .8rem; color: #64748b; margin-top: 4px; max-width: 60ch; overflow-wrap: anywhere; }
  .hotel-roles-shop-pill { background: #0B5E88; color: #fff; padding: 4px 12px; border-radius: 999px; font-size: .75rem; font-weight: 600; }

  /* ── Filtres par cards (5 chips cliquables) ─────────────── */
  .hotel-roles-filters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .65rem;
    margin-bottom: 1.25rem;
  }
  .hotel-roles-filter {
    background: var(--card, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    padding: .65rem .85rem;
    display: flex; align-items: center; gap: .65rem;
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: inherit;
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease, background .12s ease;
    min-width: 0;
    overflow: hidden;
    width: 100%;
  }
  .hotel-roles-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(15,23,42,.10);
    border-color: #cbd5e1;
  }
  .hotel-roles-filter.is-active {
    border-color: #0B5E88;
    background: #e0f2fa;
    box-shadow: 0 1px 2px rgba(11,94,136,.18);
  }
  .hotel-roles-filter__icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .hotel-roles-filter__icon svg { width: 18px; height: 18px; }
  .hotel-roles-filter.is-all      .hotel-roles-filter__icon { background: #e0f2fa; color: #0B5E88; }
  .hotel-roles-filter.is-none     .hotel-roles-filter__icon { background: #f1f5f9; color: #475569; }
  .hotel-roles-filter.is-manager  .hotel-roles-filter__icon { background: #ede9fe; color: #5b21b6; }
  .hotel-roles-filter.is-recept   .hotel-roles-filter__icon { background: #fef3c7; color: #92400e; }
  .hotel-roles-filter.is-gouv     .hotel-roles-filter__icon { background: #fce7f3; color: #9d174d; }
  .hotel-roles-filter__body { min-width: 0; flex: 1 1 auto; }
  .hotel-roles-filter__label { font-size: .8rem; color: #64748b; font-weight: 500; line-height: 1.1; }
  .hotel-roles-filter__value {
    font-size: 1.15rem; font-weight: 700; color: #0f172a;
    line-height: 1.1; margin-top: 2px;
    overflow-wrap: anywhere;
  }
  @media (max-width: 980px) {
    .hotel-roles-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 720px) {
    .hotel-roles-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 480px) {
    .hotel-roles-filters { grid-template-columns: 1fr; }
  }

  /* ── Tableau des rôles ─────────────────────────────────── */
  .hotel-roles-table-wrap { width: 100%; min-width: 0; overflow: hidden; }
  .hotel-roles-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .hotel-roles-table th { text-align: left; font-size: .7rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: .75rem .5rem; border-bottom: 2px solid #e2e8f0; overflow-wrap: anywhere; }
  .hotel-roles-table td { padding: .85rem .5rem; border-bottom: 1px solid #e2e8f0; font-size: .9rem; min-width: 0; overflow: hidden; vertical-align: middle; }
  .hotel-roles-table col.col-user  { width: 32%; }
  .hotel-roles-table col.col-app   { width: 18%; }
  .hotel-roles-table col.col-role  { width: 36%; }
  .hotel-roles-table col.col-stat  { width: 14%; }
  @media (max-width: 900px) {
    .hotel-roles-table col.col-user  { width: 38%; }
    .hotel-roles-table col.col-app   { width: 17%; }
    .hotel-roles-table col.col-role  { width: 35%; }
    .hotel-roles-table col.col-stat  { width: 10%; }
  }
  .hotel-roles-user-cell { display: flex; align-items: center; gap: .65rem; min-width: 0; }
  .hotel-roles-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #0B5E88; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: .85rem;
    flex-shrink: 0;
  }
  .hotel-roles-username { font-weight: 500; overflow-wrap: anywhere; }
  .hotel-roles-handle { font-size: .75rem; color: #64748b; overflow-wrap: anywhere; }
  .hotel-roles-badge-app { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 600; white-space: nowrap; max-width: 100%; overflow-wrap: anywhere; }
  .role-vendeur { background: #dcfce7; color: #166534; }
  .role-admin   { background: #dbeafe; color: #1e40af; }
  .role-hotel-manager        { background: #ede9fe; color: #5b21b6; }
  .role-hotel-receptionniste { background: #fef3c7; color: #92400e; }
  .role-hotel-gouvernante    { background: #fce7f3; color: #9d174d; }
  .hotel-roles-select {
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: .85rem;
    cursor: pointer;
    width: 100%;
    max-width: 100%;
    min-width: 0;
  }
  .hotel-roles-select:disabled { background: #f1f5f9; cursor: not-allowed; opacity: .6; }
  .hotel-roles-status { font-size: .75rem; color: #64748b; margin-top: 4px; overflow-wrap: anywhere; }
  .hotel-roles-status.saving { color: #d97706; }
  .hotel-roles-status.saved  { color: #16a34a; }
  .hotel-roles-status.error  { color: #dc2626; }
  .hotel-roles-empty { padding: 3rem; text-align: center; color: #64748b; }
  /* Cards mobile (≤640px) : la table est cachée, liste de cards empilées. */
  .hotel-roles-cards { display: none; }
  .hotel-roles-user-card {
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    padding: .85rem 1rem;
    margin-bottom: .65rem;
    background: var(--card, #fff);
    display: flex; flex-direction: column; gap: .65rem;
    min-width: 0; overflow: hidden;
  }
  .hotel-roles-user-card__head { display: flex; align-items: center; gap: .65rem; min-width: 0; }
  .hotel-roles-user-card__name-block { min-width: 0; flex: 1 1 auto; }
  .hotel-roles-user-card__name { font-weight: 600; overflow-wrap: anywhere; }
  .hotel-roles-user-card__handle { font-size: .75rem; color: #64748b; overflow-wrap: anywhere; }
  .hotel-roles-user-card__row {
    display: flex; align-items: center; justify-content: space-between;
    gap: .65rem; min-width: 0;
  }
  .hotel-roles-user-card__lbl {
    font-size: .7rem; text-transform: uppercase; color: #64748b;
    letter-spacing: .04em; flex-shrink: 0; font-weight: 600;
  }
  .hotel-roles-user-card__val { min-width: 0; flex: 1 1 auto; text-align: right; }
  @media (max-width: 640px) {
    .hotel-roles-table-wrap { display: none; }
    .hotel-roles-cards { display: block; }
    .hotel-roles-filters { gap: .5rem; }
    .hotel-roles-filter { padding: .55rem .7rem; }
    .hotel-roles-filter__value { font-size: 1rem; }
    .hotel-roles-filter__icon { width: 32px; height: 32px; }
  }
  .hotel-roles-back { display: inline-flex; align-items: center; gap: 6px; color: #0B5E88; text-decoration: none; font-size: .9rem; margin-bottom: 1rem; }
  .hotel-roles-back:hover { text-decoration: underline; }
  .hotel-roles-shop-selector { margin-bottom: 1.25rem; padding: .75rem 1rem; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
  .hotel-roles-shop-selector label { font-size: .8rem; font-weight: 600; color: #475569; margin-right: .5rem; }
  .hotel-roles-shop-selector select { padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: .85rem; }
  .hotel-roles-legend { background: #f8fafc; padding: 1rem; border-radius: 8px; font-size: .8rem; color: #475569; margin-bottom: 1.25rem; overflow-wrap: anywhere; }
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
        <h1 style="display:flex; align-items:center; gap:.55rem;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0B5E88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
          Gestion des rôles Hôtel
        </h1>
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
<!-- ── Filtres par cards (cliquables) ──────────────────────────── -->
    <div class="hotel-roles-filters" role="tablist" aria-label="Filtrer par rôle">
      <button type="button" class="hotel-roles-filter is-all is-active" data-filter="all" role="tab" aria-selected="true">
        <div class="hotel-roles-filter__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="hotel-roles-filter__body">
          <div class="hotel-roles-filter__label">Tous les utilisateurs</div>
          <div class="hotel-roles-filter__value" data-filter-count="all"><?= (int)$rolesCount['all'] ?></div>
        </div>
      </button>
      <button type="button" class="hotel-roles-filter is-none" data-filter="none" role="tab" aria-selected="false">
        <div class="hotel-roles-filter__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
          </svg>
        </div>
        <div class="hotel-roles-filter__body">
          <div class="hotel-roles-filter__label">Aucun accès hôtel</div>
          <div class="hotel-roles-filter__value" data-filter-count="none"><?= (int)$rolesCount['none'] ?></div>
        </div>
      </button>
      <button type="button" class="hotel-roles-filter is-manager" data-filter="manager" role="tab" aria-selected="false">
        <div class="hotel-roles-filter__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div class="hotel-roles-filter__body">
          <div class="hotel-roles-filter__label">Manager</div>
          <div class="hotel-roles-filter__value" data-filter-count="manager"><?= (int)$rolesCount['manager'] ?></div>
        </div>
      </button>
      <button type="button" class="hotel-roles-filter is-recept" data-filter="receptionniste" role="tab" aria-selected="false">
        <div class="hotel-roles-filter__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 21V8l9-5 9 5v13"/>
            <path d="M9 21V12h6v9"/>
          </svg>
        </div>
        <div class="hotel-roles-filter__body">
          <div class="hotel-roles-filter__label">Réceptionniste</div>
          <div class="hotel-roles-filter__value" data-filter-count="receptionniste"><?= (int)$rolesCount['receptionniste'] ?></div>
        </div>
      </button>
      <button type="button" class="hotel-roles-filter is-gouv" data-filter="gouvernante" role="tab" aria-selected="false">
        <div class="hotel-roles-filter__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </div>
        <div class="hotel-roles-filter__body">
          <div class="hotel-roles-filter__label">Gouvernante</div>
          <div class="hotel-roles-filter__value" data-filter-count="gouvernante"><?= (int)$rolesCount['gouvernante'] ?></div>
        </div>
      </button>
    </div>



    <?php if (empty($hotelUsers)): ?>
      <div class="hotel-roles-empty">
        Aucun utilisateur trouvé pour cette boutique.
      </div>
    <?php else: ?>
      <div class="hotel-roles-table-wrap">
      <table class="hotel-roles-table">
        <colgroup>
          <col class="col-user"><col class="col-app"><col class="col-role"><col class="col-stat">
        </colgroup>
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
              // Pour le filtre JS : on attribue un "rôle effectif" — l'admin
              // apparaît comme "manager" (auto-assigné), les autres conservent
              // leur valeur (null/vide → "none" pour le filtre).
              $effectiveRole = $isAdminAuto
                  ? 'manager'
                  : ($hotelRole !== null && $hotelRole !== '' ? $hotelRole : 'none');
              ?>
            <tr data-user-id="<?= (int)$u['id'] ?>" data-hotel-role="<?= htmlspecialchars($effectiveRole) ?>">
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
      </div>

      <!-- Vue cards (mobile ≤640px) — réplique accessible de la table -->
      <div class="hotel-roles-cards" id="hotel-roles-cards">
        <?php foreach ($hotelUsers as $u): ?>
          <?php
            $appRole = $u['role'];
            $hotelRole = $u['hotel_role'];
            $isAdminAuto = ($appRole === 'admin');
            $effectiveRole = $isAdminAuto
                ? 'manager'
                : ($hotelRole !== null && $hotelRole !== '' ? $hotelRole : 'none');
            ?>
          <article class="hotel-roles-user-card" data-user-id="<?= (int)$u['id'] ?>" data-hotel-role="<?= htmlspecialchars($effectiveRole) ?>">
            <div class="hotel-roles-user-card__head">
              <div class="hotel-roles-avatar"><?= htmlspecialchars(mb_substr($u['nom_complet'] ?? '?', 0, 1)) ?></div>
              <div class="hotel-roles-user-card__name-block">
                <div class="hotel-roles-user-card__name"><?= htmlspecialchars($u['nom_complet'] ?? '') ?></div>
                <div class="hotel-roles-user-card__handle">@<?= htmlspecialchars($u['nom_utilisateur'] ?? '') ?></div>
              </div>
            </div>
            <div class="hotel-roles-user-card__row">
              <span class="hotel-roles-user-card__lbl">Rôle applicatif</span>
              <span class="hotel-roles-user-card__val">
                <span class="hotel-roles-badge-app role-<?= htmlspecialchars($appRole) ?>">
                  <?= $appRole === 'admin' ? 'Admin (auto-manager)' : 'Vendeur' ?>
                </span>
              </span>
            </div>
            <div class="hotel-roles-user-card__row">
              <span class="hotel-roles-user-card__lbl">Rôle Hôtel</span>
              <span class="hotel-roles-user-card__val">
                <?php if ($isAdminAuto): ?>
                  <select class="hotel-roles-select" disabled>
                    <option>Manager (auto)</option>
                  </select>
                <?php else: ?>
                  <select class="hotel-roles-select" data-user-id="<?= (int)$u['id'] ?>" data-original="<?= htmlspecialchars($hotelRole ?? '') ?>">
                    <option value=""          <?= $hotelRole === null ? 'selected' : '' ?>>Aucun accès hôtel</option>
                    <option value="manager"        <?= $hotelRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="receptionniste" <?= $hotelRole === 'receptionniste' ? 'selected' : '' ?>>Réceptionniste</option>
                    <option value="gouvernante"    <?= $hotelRole === 'gouvernante' ? 'selected' : '' ?>>Gouvernante</option>
                  </select>
                <?php endif; ?>
              </span>
            </div>
            <div class="hotel-roles-user-card__row">
              <span class="hotel-roles-user-card__lbl">Statut</span>
              <span class="hotel-roles-user-card__val">
                <?php if ((int)$u['actif'] === 1): ?>
                  <span class="hotel-roles-badge-app role-vendeur">Actif</span>
                <?php else: ?>
                  <span class="hotel-roles-badge-app" style="background:#fee2e2;color:#991b1b">Inactif</span>
                <?php endif; ?>
              </span>
            </div>
            <div class="hotel-roles-user-card__row">
              <span class="hotel-roles-user-card__lbl">&nbsp;</span>
              <span class="hotel-roles-user-card__val">
                <span class="hotel-roles-status" id="status-card-<?= (int)$u['id'] ?>"><?= $isAdminAuto ? 'Auto-assigné' : 'Sélection actuelle' ?></span>
              </span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
(function() {
  'use strict';
  const APP_URL = window.location.origin;

  // ── 1. Gestion du filtre par cards (cliquable) ───────────────
  const filterBtns  = document.querySelectorAll('.hotel-roles-filter');
  const tableRows   = document.querySelectorAll('#hotel-roles-tbody tr[data-user-id]');
  const cardRows    = document.querySelectorAll('#hotel-roles-cards .hotel-roles-user-card');

  // Boutons auto (= admin) : la valeur effective côté DOM est forcée à "manager"
  // par PHP (`data-hotel-role="manager"`). Le filtre "all" les inclut donc dans
  // le set "manager" + "all". Tout est cohérent.

  function applyFilter(role) {
    // Filtrage table
    tableRows.forEach(function(tr) {
      const r = tr.getAttribute('data-hotel-role');
      tr.style.display = (role === 'all' || r === role) ? '' : 'none';
    });
    // Filtrage cards mobile
    cardRows.forEach(function(card) {
      const r = card.getAttribute('data-hotel-role');
      card.style.display = (role === 'all' || r === role) ? '' : 'none';
    });
    // État actif sur les boutons
    filterBtns.forEach(function(b) {
      const isActive = b.getAttribute('data-filter') === role;
      b.classList.toggle('is-active', isActive);
      b.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  filterBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      applyFilter(this.getAttribute('data-filter'));
    });
  });

  // ── 2. Mise à jour du compteur par rôle ───────────────────────
  // Quand un rôle change via un select, on met à jour data-hotel-role
  // sur la row + l'article (cards mobile), et on recalcule les compteurs.
  function recomputeCounts() {
    const counts = { all: tableRows.length, none: 0, manager: 0, receptionniste: 0, gouvernante: 0 };
    tableRows.forEach(function(tr) {
      const r = tr.getAttribute('data-hotel-role');
      if (counts[r] !== undefined) counts[r]++;
    });
    filterBtns.forEach(function(btn) {
      const role = btn.getAttribute('data-filter');
      const el = btn.querySelector('[data-filter-count="' + role + '"]');
      if (el) el.textContent = counts[role] !== undefined ? counts[role] : 0;
    });
  }

  // ── 3. Sauvegarde du rôle + MAJ DOM ───────────────────────────
  document.querySelectorAll('.hotel-roles-select:not([disabled])').forEach(function(sel) {
    sel.addEventListener('change', async function() {
      const userId = this.dataset.userId;
      const newRole = this.value; // '' pour aucun
      const original = this.dataset.original || '';
      const statusEl     = document.getElementById('status-' + userId);
      const statusCardEl = document.getElementById('status-card-' + userId);

      function setStatus(el, cls, text) {
        if (!el) return;
        el.className = 'hotel-roles-status ' + cls;
        el.textContent = text;
      }
      setStatus(statusEl,     'saving', 'Enregistrement…');
      setStatus(statusCardEl, 'saving', 'Enregistrement…');

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
        const labels = {
          '': 'Aucun accès hôtel',
          'manager': 'Manager',
          'receptionniste': 'Réceptionniste',
          'gouvernante': 'Gouvernante',
        };
        if (data.success) {
          const txt = '\u2713 ' + (labels[newRole] || 'Mis à jour');
          setStatus(statusEl,     'saved', txt);
          setStatus(statusCardEl, 'saved', txt);
          this.dataset.original = newRole;
          // Synchronise data-hotel-role sur table row + card mobile
          const effective = newRole === '' ? 'none' : newRole;
          tableRows.forEach(function(tr) {
            if (tr.getAttribute('data-user-id') === String(userId)) {
              tr.setAttribute('data-hotel-role', effective);
            }
          });
          cardRows.forEach(function(card) {
            if (card.getAttribute('data-user-id') === String(userId)) {
              card.setAttribute('data-hotel-role', effective);
            }
          });
          // Synchronise l'autre select (table ↔ cards) : si l'utilisateur change
          // depuis la table, on met à jour le select équivalent dans les cards.
          document.querySelectorAll('select.hotel-roles-select[data-user-id="' + userId + '"]').forEach(function(other) {
            if (other !== this) other.value = newRole;
          }, this);
          // Recalcule les compteurs après changement
          recomputeCounts();
          // Réapplique le filtre actif (peut masquer la ligne après changement)
          const activeBtn = document.querySelector('.hotel-roles-filter.is-active');
          if (activeBtn) applyFilter(activeBtn.getAttribute('data-filter'));
        } else {
          const errTxt = '\u2717 ' + (data.message || 'Erreur');
          setStatus(statusEl,     'error', errTxt);
          setStatus(statusCardEl, 'error', errTxt);
          this.value = original;
        }
      } catch (e) {
        setStatus(statusEl,     'error', '\u2717 Erreur réseau');
        setStatus(statusCardEl, 'error', '\u2717 Erreur réseau');
        this.value = original;
      }
    });
  });

  // Init : affichage de tous par défaut
  applyFilter('all');
})();
</script>
