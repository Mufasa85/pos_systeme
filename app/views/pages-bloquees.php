<?php

/**
 * Écran « Pages bloquées » (super_admin).
 *
 * Permet de retirer l'accès à une page à un utilisateur PRÉCIS, sans
 * toucher à son rôle ni à ses droits `acces_*`.
 *
 * Le catalogue des pages et la modale de sélection sont fournis par le
 * partial `partials/pages-access-modal.php` (partagé avec /hotel/roles).
 *
 * Variables attendues (fournies par PageController::pagesBloquees()) :
 *   - $pageUsers      : utilisateurs de la boutique ciblée
 *   - $pageShopId     : boutique ciblée
 *   - $pageShopName   : nom de la boutique ciblée
 *   - $pageAllShops   : toutes les boutiques (sélecteur du super_admin)
 *   - $blockedByUser  : [userId => [pageKey, ...]]
 *   - $defaultAccessByUser : [userId => [pageKey => bool]] (pages hors
 *     périmètre de l'utilisateur : elles sont FLOUTÉES dans la modale)
 *   - $pagesCatalog   : catalogue des pages
 */
$page = 'pages-bloquees';
$defaultAccessByUser = $defaultAccessByUser ?? [];
?>
<main class="pages-bloquees-wrap">
  <div class="pages-bloquees-card">
    <div class="pages-bloquees-header">
      <div>
        <h1>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0B5E88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            <circle cx="12" cy="12" r="3"></circle>
            <line x1="19" y1="5" x2="5" y2="19"></line>
          </svg>
          Pages bloquées
        </h1>
        <p class="subtitle">
          Retirez l'accès à une page à un utilisateur précis. Son rôle et ses
          droits sur les modules du POS ne sont pas modifiés : seule la page
          choisie devient inaccessible (menu, URL et API).
        </p>
      </div>
      <span class="pages-bloquees-shop-pill"><?= htmlspecialchars($pageShopName) ?></span>
    </div>

    <?php if (!empty($pageAllShops)): ?>
      <div class="pages-bloquees-shop-selector">
        <label for="pages-shop-switch">Boutique affichée :</label>
        <select id="pages-shop-switch" onchange="window.location.href='/pages-bloquees?shop_id='+encodeURIComponent(this.value)">
          <?php foreach ($pageAllShops as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)$pageShopId) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['nom'] ?? ('Boutique #' . $s['id'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="pages-bloquees-info">
      <strong>Comment ça marche :</strong>
      cliquez sur <em>Gérer</em> en face d'un utilisateur pour choisir les
      pages à lui bloquer. Un blocage est <strong>individuel</strong> : les
      autres utilisateurs de la boutique ne sont pas affectés.
      Le <code>super_admin</code> ne peut jamais être bloqué.
    </div>


    <?php if (empty($pageUsers)): ?>
      <div class="pages-bloquees-empty">
        Aucun utilisateur trouvé pour cette boutique.
      </div>
    <?php else: ?>
      <div class="pages-bloquees-table-wrap">
        <table class="pages-bloquees-table">
          <thead>
            <tr>
              <th>Utilisateur</th>
              <th>Rôle applicatif</th>
              <th>Pages bloquées</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pageUsers as $u): ?>
              <?php
              $__uid = (int)$u['id'];
                $__appRole = (string)($u['role'] ?? '');
                $__isSuperAdmin = ($__appRole === 'super_admin');
                $__blocked = $blockedByUser[$__uid] ?? [];
                $__labels = [];
                foreach ($__blocked as $__pk) {
                    if (isset($pagesCatalog[$__pk])) {
                        $__labels[] = $pagesCatalog[$__pk]['label'];
                    }
                }
                ?>
              <tr data-user-id="<?= $__uid ?>">
                <td>
                  <div class="pages-bloquees-user-cell">
                    <div class="pages-bloquees-avatar"><?= htmlspecialchars(mb_substr($u['nom_complet'] ?? '?', 0, 1)) ?></div>
                    <div>
                      <div class="pages-bloquees-username"><?= htmlspecialchars($u['nom_complet'] ?? '') ?></div>
                      <div class="pages-bloquees-handle">@<?= htmlspecialchars($u['nom_utilisateur'] ?? '') ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($__isSuperAdmin): ?>
                    <span class="pages-bloquees-badge is-superadmin">Super admin</span>
                  <?php else: ?>
                    <span class="pages-bloquees-badge is-vendeur"><?= htmlspecialchars($__appRole) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($__isSuperAdmin): ?>
                    <span class="pages-bloquees-locked">Jamais bloqué</span>
                  <?php else: ?>
                    <div class="hotel-roles-pages" data-user-id="<?= $__uid ?>">
                      <?php if ($__labels): ?>
                        <?php foreach ($__labels as $__label): ?>
                          <span class="hotel-roles-page is-blocked"
                            data-user-id="<?= $__uid ?>"
                            title="Accès bloqué pour cet utilisateur">
                            <?= htmlspecialchars($__label) ?>
                          </span>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span class="hotel-roles-pages-empty">Aucune page bloquée</span>
                      <?php endif; ?>
                      <button type="button"
                        class="hotel-roles-pages-manage"
                        data-user-id="<?= $__uid ?>"
                        data-user-name="<?= htmlspecialchars($u['nom_complet'] ?? '') ?>"
                        title="Gérer les pages bloquées pour cet utilisateur">
                        Gérer
                      </button>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<style>
  .pages-bloquees-wrap {
    max-width: 1200px;
    width: 100%;
    margin: 1.25rem auto;
    padding: 0 1.25rem;
  }

  @media (max-width: 640px) {
    .pages-bloquees-wrap {
      padding: 0 .75rem;
    }
  }

  .pages-bloquees-card {
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    padding: 1.5rem;
    min-width: 0;
  }

  .pages-bloquees-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
  }

  .pages-bloquees-header h1 {
    font-size: 1.4rem;
    margin: 0;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: .55rem;
  }

  .pages-bloquees-header .subtitle {
    font-size: .82rem;
    color: #64748b;
    margin-top: .5rem;
    max-width: 70ch;
    line-height: 1.5;
  }

  .pages-bloquees-shop-pill {
    background: #0B5E88;
    color: #fff;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .pages-bloquees-shop-selector {
    margin-bottom: 1.25rem;
    padding: .75rem 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px dashed #cbd5e1;
  }

  .pages-bloquees-shop-selector label {
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    margin-right: .5rem;
  }

  .pages-bloquees-shop-selector select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: .85rem;
  }

  .pages-bloquees-info {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    color: #075985;
    border-radius: 10px;
    padding: .75rem 1rem;
    margin-bottom: 1.25rem;
    font-size: .82rem;
    line-height: 1.6;
  }

  .pages-bloquees-info code {
    background: #e0f2fa;
    padding: .05rem .3rem;
    border-radius: 4px;
    font-size: .95em;
  }

  .pages-bloquees-table-wrap {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
  }

  .pages-bloquees-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .pages-bloquees-table th {
    text-align: left;
    font-size: .7rem;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 600;
    padding: .75rem .5rem;
    border-bottom: 2px solid #e2e8f0;
  }

  .pages-bloquees-table td {
    padding: .85rem .5rem;
    border-bottom: 1px solid #e2e8f0;
    font-size: .9rem;
    vertical-align: middle;
  }

  .pages-bloquees-table th:first-child { width: 32%; }
  .pages-bloquees-table th:nth-child(2) { width: 18%; }

  .pages-bloquees-user-cell {
    display: flex;
    align-items: center;
    gap: .65rem;
    min-width: 0;
  }

  .pages-bloquees-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #0B5E88;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: .85rem;
    flex-shrink: 0;
  }

  .pages-bloquees-username {
    font-weight: 500;
    overflow-wrap: anywhere;
  }

  .pages-bloquees-handle {
    font-size: .75rem;
    color: #64748b;
    overflow-wrap: anywhere;
  }

  .pages-bloquees-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .pages-bloquees-badge.is-vendeur {
    background: #dcfce7;
    color: #166534;
  }

  .pages-bloquees-badge.is-superadmin {
    background: #ede9fe;
    color: #5b21b6;
  }

  .pages-bloquees-locked {
    font-size: .75rem;
    color: #94a3b8;
    font-style: italic;
  }

  .pages-bloquees-empty {
    padding: 3rem;
    text-align: center;
    color: #64748b;
  }
</style>

  </div>
</main>

<?php
// Modale partagée avec /hotel/roles (catalogue + JS).
$pagesEndpoint = '/api/user/page-access/update';
// Ici, on ne propose que les pages GÉNÉRALES (POS) : les pages Hôtel se
// gèrent depuis /hotel/roles, via la même modale mais avec sa famille.
$pagesFamily   = \App\Services\UserPageAccessService::FAMILY_GENERAL;
$pagesShopId   = (int)$pageShopId;
$defaultAccessByUser = $defaultAccessByUser ?? [];
require_once __DIR__ . '/partials/pages-access-modal.php';
?>
