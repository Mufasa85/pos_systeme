<!-- Payroll Index -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Module Paie</h2>
        <p id="current-date"><?= date('d/m/Y') ?></p>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <a href="/payroll/employees" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon blue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Employés</span>
                <span class="stat-value">Fiches vendeurs</span>
            </div>
        </a>

        <a href="/payroll/periods" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Périodes</span>
                <span class="stat-value">Ouvrir une paie</span>
            </div>
        </a>

        <a href="/payroll/attendance" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon purple">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Présences</span>
                <span class="stat-value">Pointage & HS</span>
            </div>
        </a>

        <a href="/payroll/payslips" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon cyan">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Bulletins</span>
                <span class="stat-value">Calcul & PDF</span>
            </div>
        </a>

        <a href="/payroll/payments" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon orange">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Paiements</span>
                <span class="stat-value">Règlements</span>
            </div>
        </a>

        <a href="/payroll/reports" class="stat-card" style="text-decoration: none;">
            <div class="stat-icon red">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Rapports</span>
                <span class="stat-value">Exports CSV</span>
            </div>
        </a>
    </div>
</div>
