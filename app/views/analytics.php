      <!-- Analytics Page -->
      <div id="page-analytics" class="page <?= $page == 'analytics' ? 'active' : '' ?>">
        <div class="page-header">
          <div>
            <h2>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
                <path d="M3 3v18h18"></path>
                <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
              </svg>
              Analytics
            </h2>
            <p>Tableau de bord analytique des performances</p>
          </div>
          <div class="analytics-actions">
            <span id="analytics-date" class="analytics-date"><?= date('d/m/Y') ?></span>
            <button class="btn btn-secondary btn-small" onclick="window.print()">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
              Imprimer
            </button>
          </div>
        </div>

        <div class="analytics-grid">
          <div class="analytics-card kpi">
            <div class="kpi-icon blue">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Ventes totales</span>
              <span class="kpi-value"><?= number_format($stats['all_time']['total'] ?? 0, 2) ?> Fc</span>
              <span class="kpi-sub"><?= $stats['all_time']['count'] ?? 0 ?> transactions</span>
            </div>
          </div>

          <div class="analytics-card kpi">
            <div class="kpi-icon green">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="6" x2="12" y2="12"></line>
                <line x1="12" y1="12" x2="16" y2="10"></line>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Ce mois</span>
              <span class="kpi-value"><?= number_format($stats['month']['total'] ?? 0, 2) ?> Fc</span>
              <span class="kpi-sub"><?= $stats['month']['count'] ?? 0 ?> transactions</span>
            </div>
          </div>

          <div class="analytics-card kpi">
            <div class="kpi-icon purple">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Panier moyen</span>
              <span class="kpi-value"><?= number_format($averageBasket ?? 0, 2) ?> Fc</span>
              <span class="kpi-sub">par transaction</span>
            </div>
          </div>

          <div class="analytics-card kpi">
            <div class="kpi-icon orange">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                <polyline points="17 6 23 6 23 12"></polyline>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Moyenne journalière</span>
              <span class="kpi-value"><?= number_format($averageDailySales ?? 0, 2) ?> Fc</span>
              <span class="kpi-sub">sur <?= count($salesByDay ?? []) ?> jours</span>
            </div>
          </div>

          <div class="analytics-card kpi">
            <div class="kpi-icon cyan">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Produit star</span>
              <span class="kpi-value kpi-text"><?= htmlspecialchars($bestProductName ?? '-') ?></span>
              <span class="kpi-sub"><?= number_format($bestProductRevenue ?? 0, 2) ?> Fc</span>
            </div>
          </div>

          <div class="analytics-card kpi">
            <div class="kpi-icon red">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
            <div class="kpi-info">
              <span class="kpi-label">Meilleur vendeur</span>
              <span class="kpi-value kpi-text"><?= htmlspecialchars($bestSellerName ?? '-') ?></span>
              <span class="kpi-sub"><?= number_format($bestSellerAmount ?? 0, 2) ?> Fc</span>
            </div>
          </div>
        </div>

        <div class="analytics-card chart-card">
          <div class="card-header">
            <h3>Évolution des ventes - 30 derniers jours</h3>
          </div>
          <div class="card-body chart-body">
            <canvas id="daily-sales-chart"></canvas>
          </div>
        </div>

        <div class="analytics-charts-row">
          <div class="analytics-card chart-card">
            <div class="card-header">
              <h3>Ventes par mois</h3>
            </div>
            <div class="card-body chart-body">
              <canvas id="monthly-sales-chart"></canvas>
            </div>
          </div>

          <div class="analytics-card chart-card">
            <div class="card-header">
              <h3>Heures de vente</h3>
            </div>
            <div class="card-body chart-body">
              <canvas id="hourly-sales-chart"></canvas>
            </div>
          </div>
        </div>

        <div class="analytics-charts-row">
          <div class="analytics-card chart-card">
            <div class="card-header">
              <h3>Répartition par catégorie</h3>
            </div>
            <div class="card-body chart-body doughnut-body">
              <canvas id="category-chart"></canvas>
            </div>
          </div>

          <div class="analytics-card chart-card">
            <div class="card-header">
              <h3>Modes de paiement</h3>
            </div>
            <div class="card-body chart-body doughnut-body">
              <canvas id="payment-chart"></canvas>
            </div>
          </div>

          <div class="analytics-card chart-card">
            <div class="card-header">
              <h3>Produits vs Recharges</h3>
            </div>
            <div class="card-body chart-body doughnut-body">
              <canvas id="recharge-chart"></canvas>
            </div>
          </div>
        </div>

        <div class="analytics-charts-row">
          <div class="analytics-card chart-card wide">
            <div class="card-header">
              <h3>Top 10 vendeurs</h3>
            </div>
            <div class="card-body chart-body">
              <canvas id="sellers-chart"></canvas>
            </div>
          </div>
        </div>

        <div class="analytics-tables-row">
          <div class="analytics-card table-card">
            <div class="card-header">
              <h3>Top 10 produits</h3>
            </div>
            <div class="card-body">
              <div class="table-container">
                <table class="data-table analytics-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Produit</th>
                      <th class="text-right">Qté</th>
                      <th class="text-right">Chiffre</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($topProducts)): ?>
                      <?php $rank = 1; foreach ($topProducts as $pid => $p): ?>
                        <tr>
                          <td class="rank"><?= $rank ?></td>
                          <td><?= htmlspecialchars($p['name']) ?></td>
                          <td class="text-right"><?= number_format($p['qty'], 0) ?></td>
                          <td class="text-right"><strong><?= number_format($p['revenue'], 2) ?> Fc</strong></td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="empty-state">Aucune donnée produit</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="analytics-card table-card">
            <div class="card-header">
              <h3>Top 10 clients</h3>
            </div>
            <div class="card-body">
              <div class="table-container">
                <table class="data-table analytics-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Client</th>
                      <th class="text-right">Achats</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($topClients)): ?>
                      <?php $rank = 1; foreach ($topClients as $cid => $amount): ?>
                        <tr>
                          <td class="rank"><?= $rank ?></td>
                          <td><?= htmlspecialchars($topClientsNames[$cid] ?? 'Client #' . $cid) ?></td>
                          <td class="text-right"><strong><?= number_format($amount, 2) ?> Fc</strong></td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="empty-state">Aucune donnée client</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="analytics-tables-row">
          <div class="analytics-card insight-card">
            <div class="card-header">
              <h3>Insights clients</h3>
            </div>
            <div class="card-body">
              <div class="insight-item">
                <span class="insight-label">Clients actifs</span>
                <span class="insight-value"><?= $activeClients ?? 0 ?> / <?= $totalClients ?? 0 ?></span>
              </div>
              <div class="insight-item">
                <span class="insight-label">Taux de conversion</span>
                <span class="insight-value"><?= $customerRate ?? 0 ?>%</span>
              </div>
              <div class="insight-item">
                <span class="insight-label">Meilleur jour</span>
                <span class="insight-value"><?= htmlspecialchars($bestDayLabel ?? '-') ?></span>
              </div>
              <div class="insight-item">
                <span class="insight-label">Recette meilleur jour</span>
                <span class="insight-value"><?= number_format($bestDayAmount ?? 0, 2) ?> Fc</span>
              </div>
            </div>
          </div>

          <div class="analytics-card insight-card alert">
            <div class="card-header">
              <h3>Alertes stock</h3>
            </div>
            <div class="card-body">
              <div class="insight-item">
                <span class="insight-label">Produits en rupture</span>
                <span class="insight-value text-danger"><?= $stockOut ?? 0 ?></span>
              </div>
              <div class="insight-item">
                <span class="insight-label">Stock critique</span>
                <span class="insight-value text-warning"><?= count($stockAlerts ?? []) ?></span>
              </div>
              <?php if (!empty($stockAlerts)): ?>
                <?php foreach (array_slice($stockAlerts, 0, 5, true) as $sf): ?>
                  <div class="alert-item mini <?= $sf['stock'] == 0 ? 'critical' : '' ?>">
                    <span><?= htmlspecialchars($sf['nom']) ?></span>
                    <span><strong><?= $sf['stock'] ?></strong> / <?= $sf['stock_minimum'] ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
      <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#64748b';
        const formatFc = (value) => value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Fc';
        const commonOptions = {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
            tooltip: { callbacks: { label: (ctx) => (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + formatFc(ctx.parsed.y ?? ctx.parsed) } }
          }
        };
        const gradientBlue = (context) => {
          const ctx = context.chart.ctx;
          const g = ctx.createLinearGradient(0, 0, 0, 300);
          g.addColorStop(0, 'rgba(11, 94, 136, 0.4)');
          g.addColorStop(1, 'rgba(11, 94, 136, 0.0)');
          return g;
        };
        const chartColors = ['#0B5E88', '#2AB7E6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#6366f1', '#f97316'];

        new Chart(document.getElementById('daily-sales-chart'), {
          type: 'line',
          data: { labels: <?= $dailyLabels ?? '[]' ?>, datasets: [{ label: 'Ventes', data: <?= $dailyValues ?? '[]' ?>, borderColor: '#0B5E88', backgroundColor: gradientBlue, borderWidth: 2, tension: 0.4, fill: true, pointRadius: 3, pointBackgroundColor: '#0B5E88', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
          options: { ...commonOptions, plugins: { ...commonOptions.plugins, legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' Fc' } }, x: { grid: { display: false } } } }
        });

        new Chart(document.getElementById('monthly-sales-chart'), {
          type: 'bar',
          data: { labels: <?= $monthlyLabels ?? '[]' ?>, datasets: [{ label: 'Ventes', data: <?= $monthlyValues ?? '[]' ?>, backgroundColor: '#0B5E88', borderRadius: 6, borderSkipped: false }] },
          options: { ...commonOptions, plugins: { ...commonOptions.plugins, legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' Fc' } }, x: { grid: { display: false } } } }
        });

        new Chart(document.getElementById('hourly-sales-chart'), {
          type: 'bar',
          data: { labels: <?= $hourlyLabels ?? '[]' ?>, datasets: [{ label: 'Ventes', data: <?= $hourlyValues ?? '[]' ?>, backgroundColor: '#2AB7E6', borderRadius: 4, borderSkipped: false }] },
          options: { ...commonOptions, plugins: { ...commonOptions.plugins, legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' Fc' } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } } } }
        });

        new Chart(document.getElementById('category-chart'), {
          type: 'doughnut',
          data: { labels: <?= $categoryLabels ?? '[]' ?>, datasets: [{ data: <?= $categoryValues ?? '[]' ?>, backgroundColor: chartColors, borderWidth: 0, hoverOffset: 8 }] },
          options: { ...commonOptions, cutout: '65%', plugins: { ...commonOptions.plugins, tooltip: { callbacks: { label: (c) => c.label + ': ' + formatFc(c.parsed) } } } }
        });

        new Chart(document.getElementById('payment-chart'), {
          type: 'doughnut',
          data: { labels: <?= $paymentLabels ?? '[]' ?>, datasets: [{ data: <?= $paymentValues ?? '[]' ?>, backgroundColor: chartColors, borderWidth: 0, hoverOffset: 8 }] },
          options: { ...commonOptions, cutout: '65%', plugins: { ...commonOptions.plugins, tooltip: { callbacks: { label: (c) => c.label + ': ' + formatFc(c.parsed) } } } }
        });

        new Chart(document.getElementById('recharge-chart'), {
          type: 'doughnut',
          data: { labels: ['Produits', 'Recharges'], datasets: [{ data: <?= $rechargeValues ?? '[]' ?>, backgroundColor: ['#0B5E88', '#f59e0b'], borderWidth: 0, hoverOffset: 8 }] },
          options: { ...commonOptions, cutout: '65%', plugins: { ...commonOptions.plugins, tooltip: { callbacks: { label: (c) => c.label + ': ' + formatFc(c.parsed) } } } }
        });

        new Chart(document.getElementById('sellers-chart'), {
          type: 'bar',
          data: { labels: <?= $sellerLabels ?? '[]' ?>, datasets: [{ label: 'Chiffre d\'affaires', data: <?= $sellerValues ?? '[]' ?>, backgroundColor: '#10b981', borderRadius: 6, borderSkipped: false }] },
          options: { ...commonOptions, indexAxis: 'y', plugins: { ...commonOptions.plugins, legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' Fc' } }, y: { grid: { display: false } } } }
        });
      </script>

      <style>
        .analytics-actions { display: flex; align-items: center; gap: 1rem; }
        .analytics-date { color: var(--muted); font-size: 0.875rem; }
        .analytics-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .analytics-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .analytics-card .card-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
        .analytics-card .card-header h3 { font-size: 1rem; font-weight: 600; }
        .analytics-card .card-body { padding: 1.25rem; }
        .analytics-card.kpi { display: flex; align-items: center; gap: 0.875rem; padding: 1rem 1.25rem; }
        .kpi-icon { width: 44px; height: 44px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .kpi-icon.blue { background: rgba(11, 94, 136, 0.1); color: var(--primary); }
        .kpi-icon.green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .kpi-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .kpi-icon.orange { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .kpi-icon.cyan { background: rgba(42, 183, 230, 0.1); color: var(--accent); }
        .kpi-icon.red { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .kpi-info { display: flex; flex-direction: column; min-width: 0; }
        .kpi-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 1.1rem; font-weight: 700; color: var(--foreground); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kpi-value.kpi-text { font-size: 0.95rem; }
        .kpi-sub { font-size: 0.75rem; color: var(--muted); }
        .chart-card { margin-bottom: 1.5rem; }
        .chart-body { height: 320px; }
        .doughnut-body { height: 280px; display: flex; align-items: center; justify-content: center; }
        .analytics-charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .analytics-charts-row:has(> :nth-child(3)) { grid-template-columns: repeat(3, 1fr); }
        .analytics-charts-row .wide { grid-column: 1 / -1; }
        .analytics-tables-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-top: 1.5rem; }
        .analytics-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); background: var(--background); }
        .analytics-table td { font-size: 0.875rem; padding: 0.75rem; }
        .analytics-table .rank { font-weight: 700; color: var(--primary); width: 36px; text-align: center; }
        .text-right { text-align: right; }
        .insight-card { margin-bottom: 1.5rem; }
        .insight-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border); }
        .insight-item:last-child { border-bottom: none; }
        .insight-label { font-size: 0.875rem; color: var(--muted); }
        .insight-value { font-weight: 600; font-size: 0.875rem; }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .alert-item.mini { padding: 0.5rem; margin-top: 0.5rem; }

        @media (max-width: 1200px) {
          .analytics-grid { grid-template-columns: repeat(3, 1fr); }
          .analytics-charts-row { grid-template-columns: 1fr; }
          .analytics-charts-row:has(> :nth-child(3)) { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
          .analytics-grid { grid-template-columns: repeat(2, 1fr); }
          .analytics-tables-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
          .analytics-grid { grid-template-columns: 1fr; }
          .analytics-card.kpi { padding: 0.875rem 1rem; }
        }
        @media print {
          .sidebar, .mobile-header, .analytics-actions { display: none !important; }
          .main-content { margin-left: 0; padding: 0; }
          .analytics-card { break-inside: avoid; }
        }
      </style>
