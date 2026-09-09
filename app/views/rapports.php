<!-- Rapports Fiscaux Page -->
<div id="page-rapports" class="page <?= $page == 'rapports' ? 'active' : '' ?>">
  <div class="page-header" style="margin: 20px;">
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
      Rapports Fiscaux
    </h2>
    <p style="color: var(--muted); margin-top: 4px;">
      Boutique : <strong><?= htmlspecialchars($storeName ?? 'N/A') ?></strong>
    </p>
  </div>

  <div class="report-actions" style="display: flex; flex-wrap: wrap; gap: 12px; margin: 0 20px 20px; align-items: center;">
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
    <button id="btn-z-report" class="btn btn-danger" style="padding: 0.625rem 1rem; border: none; border-radius: var(--radius); cursor: pointer; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
      </svg>
      Z-rapport (cloture)
    </button>
    <button id="btn-a-report" class="btn btn-warning" style="padding: 0.625rem 1rem; border: none; border-radius: var(--radius); cursor: pointer; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
        <line x1="12" y1="22.08" x2="12" y2="12"></line>
      </svg>
      A-rapport (articles)
    </button>
    <?php endif; ?>
    <button id="btn-x-daily" class="btn btn-primary" style="padding: 0.625rem 1rem; border: none; border-radius: var(--radius); cursor: pointer; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"></line>
        <line x1="12" y1="20" x2="12" y2="4"></line>
        <line x1="6" y1="20" x2="6" y2="14"></line>
      </svg>
      X-rapport quotidien
    </button>
    <div class="period-picker" style="display: flex; align-items: center; gap: 8px;">
      <label style="color: var(--muted); font-size: 0.875rem;">Du :</label>
      <input type="date" id="report-from" style="padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text);">
      <label style="color: var(--muted); font-size: 0.875rem;">Au :</label>
      <input type="date" id="report-to" style="padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text);">
      <button id="btn-x-periodic" class="btn btn-secondary" style="padding: 0.625rem 1rem; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        X-rapport periodique
      </button>
    </div>
  </div>

  <div id="report-content" class="report-container" style="margin: 0 20px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; min-height: 200px;">
    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
      Selectionnez un type de rapport ci-dessus pour afficher les resultats.
    </p>
  </div>

  <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
  <div class="report-history" style="margin: 0 20px 20px;">
    <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
      </svg>
      Historique des Z-rapports
    </h3>
    <div id="report-history-list" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden;"></div>
  </div>

  <div class="report-history" style="margin: 0 20px 20px;">
    <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
        <line x1="12" y1="22.08" x2="12" y2="12"></line>
      </svg>
      Historique des A-rapports
    </h3>
    <div id="a-report-history-list" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden;"></div>
  </div>
  <?php endif; ?>
</div>

<style>
.report-table { width: 100%; border-collapse: collapse; text-align: left; }
.report-table th, .report-table td { padding: 0.75rem; border-bottom: 1px solid var(--border); }
.report-table th { font-weight: 600; background: var(--background); }
.report-table tr.totaux { font-weight: bold; background: var(--background); }
.report-header { margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border); }
.report-header h2 { margin: 0 0 0.5rem 0; }
.report-header p { margin: 0.25rem 0; font-size: 0.875rem; color: var(--muted); }
.report-section { margin: 1rem 0; }
.report-section h4 { margin: 0 0 0.5rem 0; font-size: 0.95rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
.loading { text-align: center; padding: 2rem; color: var(--muted); }
.error { color: #e53e3e; padding: 1rem; text-align: center; }
</style>
