<!-- Rapports Fiscaux Page -->
<div id="page-rapports" class="page <?= $page == 'rapports' ? 'active' : '' ?>">
  <div class="rpt-page-head">
    <div class="rpt-page-head-icon">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
    </div>
    <div>
      <h2 class="rpt-page-title">Rapports Fiscaux</h2>
      <p class="rpt-page-sub">
        Boutique : <strong><?= htmlspecialchars($storeName ?? 'N/A') ?></strong>
      </p>
    </div>
  </div>

  <!-- Action Cards -->
  <div class="rpt-cards-row">
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
    <button id="btn-z-report" class="rpt-action-card rpt-card-danger">
      <div class="rpt-card-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
      </div>
      <div class="rpt-card-body">
        <span class="rpt-card-title">Z-rapport</span>
        <span class="rpt-card-desc">Cloture de periode fiscale</span>
      </div>
    </button>
    <button id="btn-a-report" class="rpt-action-card rpt-card-warning">
      <div class="rpt-card-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>
      </div>
      <div class="rpt-card-body">
        <span class="rpt-card-title">A-rapport</span>
        <span class="rpt-card-desc">Detail des articles vendus</span>
      </div>
    </button>
    <?php endif; ?>
    <button id="btn-x-daily" class="rpt-action-card rpt-card-primary">
      <div class="rpt-card-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"></line>
          <line x1="12" y1="20" x2="12" y2="4"></line>
          <line x1="6" y1="20" x2="6" y2="14"></line>
        </svg>
      </div>
      <div class="rpt-card-body">
        <span class="rpt-card-title">X-rapport</span>
        <span class="rpt-card-desc">Resume quotidien en cours</span>
      </div>
    </button>
  </div>

  <!-- Periodic Picker -->
  <div class="rpt-period-bar">
    <div class="rpt-period-label">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>X-rapport periodique</span>
    </div>
    <div class="rpt-period-inputs">
      <div class="rpt-date-group">
        <label>Du</label>
        <input type="date" id="report-from">
      </div>
      <div class="rpt-date-arrow">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </div>
      <div class="rpt-date-group">
        <label>Au</label>
        <input type="date" id="report-to">
      </div>
      <button id="btn-x-periodic" class="rpt-period-btn">Generer</button>
    </div>
  </div>

  <!-- Report Content -->
  <div id="report-content" class="rpt-content-area">
    <div class="rpt-empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4;">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
      </svg>
      <p>Selectionnez un type de rapport pour afficher les resultats</p>
    </div>
  </div>

  <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
  <!-- History Section -->
  <div class="rpt-history-section">
    <div class="rpt-history-grid">
      <div class="rpt-history-col">
        <div class="rpt-history-head">
          <div class="rpt-history-icon rpt-hist-icon-danger">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
          </div>
          <h3>Historique des Z-rapports</h3>
        </div>
        <div id="report-history-list" class="rpt-history-body"></div>
      </div>

      <div class="rpt-history-col">
        <div class="rpt-history-head">
          <div class="rpt-history-icon rpt-hist-icon-warning">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
          </div>
          <h3>Historique des A-rapports</h3>
        </div>
        <div id="a-report-history-list" class="rpt-history-body"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
/* ── Page Header ────────────────────────────── */
.rpt-page-head {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
}
.rpt-page-head-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(11, 94, 136, 0.3);
}
.rpt-page-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--foreground);
}
.rpt-page-sub {
  margin: 4px 0 0;
  font-size: 0.875rem;
  color: var(--muted);
}

/* ── Action Cards ───────────────────────────── */
.rpt-cards-row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  padding: 0 24px 20px;
}
.rpt-action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  border: none;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
  font-family: inherit;
  background: var(--card);
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
}
.rpt-action-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}
.rpt-action-card:active { transform: translateY(0); }
.rpt-card-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
}
.rpt-card-danger .rpt-card-icon { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25); }
.rpt-card-warning .rpt-card-icon { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.25); }
.rpt-card-primary .rpt-card-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); box-shadow: 0 4px 10px rgba(11, 94, 136, 0.25); }
.rpt-card-body { display: flex; flex-direction: column; gap: 2px; }
.rpt-card-title { font-size: 1rem; font-weight: 700; color: var(--foreground); }
.rpt-card-desc { font-size: 0.8rem; color: var(--muted); }

/* ── Period Bar ─────────────────────────────── */
.rpt-period-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 16px 24px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  margin: 0 24px 20px;
  box-shadow: var(--shadow);
}
.rpt-period-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--foreground);
}
.rpt-period-label svg { color: var(--primary); }
.rpt-period-inputs {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}
.rpt-date-group { display: flex; flex-direction: column; gap: 4px; }
.rpt-date-group label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.rpt-date-group input[type="date"] {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--background);
  color: var(--foreground);
  font-family: inherit;
  font-size: 0.875rem;
  transition: border-color 0.15s;
}
.rpt-date-group input[type="date"]:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(11, 94, 136, 0.1);
}
.rpt-date-arrow {
  display: flex;
  align-items: center;
  padding-bottom: 0.5rem;
  color: var(--muted);
}
.rpt-period-btn {
  padding: 0.55rem 1.25rem;
  border: none;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}
.rpt-period-btn:hover { opacity: 0.9; transform: translateY(-1px); }

/* ── Report Content Area ────────────────────── */
.rpt-content-area {
  margin: 0 24px 24px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 28px;
  min-height: 240px;
  box-shadow: var(--shadow);
}
.rpt-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 3rem 0;
  color: var(--muted);
  text-align: center;
}
.rpt-empty-state p { font-size: 0.9rem; }

/* ── Report Header (inside content) ─────────── */
.rpt-report-header {
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 2px solid var(--border);
}
.rpt-report-header h2 {
  margin: 0 0 0.5rem;
  font-size: 1.25rem;
  font-weight: 700;
}
.rpt-report-header .rpt-meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  font-size: 0.825rem;
  color: var(--muted);
  margin-top: 6px;
}
.rpt-report-header .rpt-meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 20px;
  background: var(--background);
  font-weight: 600;
  color: var(--foreground);
}

/* ── Report Sections ────────────────────────── */
.rpt-section {
  margin: 1.25rem 0;
}
.rpt-section h4 {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--primary);
  padding-bottom: 0.5rem;
  border-bottom: 2px solid var(--border);
}

/* ── Report Tables ──────────────────────────── */
.rpt-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
}
.rpt-table th,
.rpt-table td {
  padding: 0.7rem 1rem;
  border-bottom: 1px solid var(--border);
  font-size: 0.85rem;
}
.rpt-table th {
  font-weight: 700;
  background: var(--background);
  color: var(--foreground);
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.04em;
}
.rpt-table tbody tr:last-child td { border-bottom: none; }
.rpt-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.015); }
.rpt-table tbody tr:hover { background: rgba(11, 94, 136, 0.04); }
.rpt-table tr.rpt-totals-row {
  font-weight: 700;
  background: linear-gradient(90deg, rgba(11, 94, 136, 0.06), rgba(11, 94, 136, 0.02));
}
.rpt-table tr.rpt-totals-row td { border-top: 2px solid var(--primary); }

/* ── Stat Pills (for totals) ────────────────── */
.rpt-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin: 1rem 0;
}
.rpt-stat-pill {
  padding: 16px 20px;
  border-radius: 12px;
  background: var(--background);
  border: 1px solid var(--border);
}
.rpt-stat-pill .rpt-stat-label {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted);
  margin-bottom: 6px;
}
.rpt-stat-pill .rpt-stat-value {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--foreground);
}
.rpt-stat-pill.rpt-stat-accent {
  background: linear-gradient(135deg, rgba(11, 94, 136, 0.08), rgba(11, 94, 136, 0.02));
  border-color: rgba(11, 94, 136, 0.2);
}
.rpt-stat-pill.rpt-stat-accent .rpt-stat-value { color: var(--primary); }
.rpt-stat-pill.rpt-stat-success { background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.02)); border-color: rgba(16, 185, 129, 0.2); }
.rpt-stat-pill.rpt-stat-success .rpt-stat-value { color: var(--success); }
.rpt-stat-pill.rpt-stat-danger { background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.02)); border-color: rgba(239, 68, 68, 0.2); }
.rpt-stat-pill.rpt-stat-danger .rpt-stat-value { color: var(--danger); }

/* ── Print Button ───────────────────────────── */
.rpt-print-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 1.5rem;
  padding: 0.65rem 1.5rem;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
  box-shadow: 0 4px 12px rgba(11, 94, 136, 0.25);
}
.rpt-print-btn:hover { transform: translateY(-1px); opacity: 0.95; }

/* ── Loading & Error ────────────────────────── */
.rpt-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 3rem 0;
  color: var(--muted);
}
.rpt-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: rpt-spin 0.7s linear infinite;
}
@keyframes rpt-spin { to { transform: rotate(360deg); } }
.rpt-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: var(--danger);
  font-weight: 600;
}

/* ── History Section ────────────────────────── */
.rpt-history-section { padding: 0 24px 24px; }
.rpt-history-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 768px) {
  .rpt-history-grid { grid-template-columns: 1fr; }
}
.rpt-history-col {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: var(--shadow);
}
.rpt-history-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--background);
}
.rpt-history-head h3 {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 700;
}
.rpt-history-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}
.rpt-hist-icon-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
.rpt-hist-icon-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.rpt-history-body { padding: 0; }
.rpt-history-empty {
  padding: 1.5rem;
  text-align: center;
  color: var(--muted);
  font-size: 0.85rem;
}

/* ── Pagination ─────────────────────────────── */
.rpt-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 16px;
  flex-wrap: wrap;
}
.rpt-page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 34px;
  height: 34px;
  padding: 0 8px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--card);
  color: var(--foreground);
  font-size: 0.825rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}
.rpt-page-btn:hover:not(:disabled):not(.rpt-page-active) {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(11, 94, 136, 0.04);
}
.rpt-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.rpt-page-active {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  border-color: var(--primary-dark);
  box-shadow: 0 2px 6px rgba(11, 94, 136, 0.25);
}
.rpt-page-ellipsis {
  display: inline-flex;
  align-items: center;
  padding: 0 4px;
  color: var(--muted);
  font-size: 0.825rem;
}
.rpt-page-info {
  margin-left: 8px;
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 600;
}

@media (max-width: 600px) {
  .rpt-cards-row { grid-template-columns: 1fr; }
  .rpt-period-bar { flex-direction: column; align-items: stretch; }
  .rpt-period-inputs { flex-direction: column; align-items: stretch; }
  .rpt-date-arrow { display: none; }
}
</style>
