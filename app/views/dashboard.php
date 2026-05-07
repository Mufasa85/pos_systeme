      <!-- Dashboard Page -->
      <div id="page-dashboard" class="page <?= $page == 'dashboard' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            Tableau de bord
          </h2>
          <p id="current-date"><?= date('d/m/Y') ?></p>
        </div>

        <!-- Statistiques principales -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon blue">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Ventes du jour</span>
              <span class="stat-value" id="stat-today"><?= number_format($ventes_jour ?? 0, 2) ?> Fc</span>
              <span class="stat-count"><?= $nb_ventes_jour ?? 0 ?> transactions</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon green">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                <polyline points="17 6 23 6 23 12"></polyline>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Cette semaine</span>
              <span class="stat-value" id="stat-week"><?= number_format($ventes_semaine ?? 0, 2) ?> Fc</span>
              <span class="stat-count"><?= $nb_ventes_semaine ?? 0 ?> transactions</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon purple">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Ce mois</span>
              <span class="stat-value" id="stat-month"><?= number_format($ventes_mois ?? 0, 2) ?> Fc</span>
              <span class="stat-count"><?= $nb_ventes_mois ?? 0 ?> transactions</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon cyan">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Produits</span>
              <span class="stat-value" id="stat-products"><?= $produits_compte ?? 0 ?></span>
            </div>
          </div>
        </div>

        <!-- Graphique des ventes -->
    
          

  


          <div class="card">
            <div class="card-header">
              <h3>Alertes de stock</h3>
            </div>
            <div class="card-body">
              <div id="stock-alerts" class="alert-list">
                <?php if (!empty($stock_faible)): ?>
                  <?php foreach ($stock_faible as $sf): ?>
                    <div class="alert-item <?= $sf['stock'] == 0 ? 'critical' : '' ?>">
                      <span><?= htmlspecialchars($sf['nom']) ?></span>
                      <span><strong><?= $sf['stock'] ?></strong> / <?= $sf['stock_minimum'] ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="empty-state">Aucune alerte de stock</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          
       
      </div>

      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
      <script>
        // Données du graphique
        const labels = <?= $chart_labels ?? '[]' ?>;
        const values = <?= $chart_values ?? '[]' ?>;

        const ctx = document.getElementById('sales-chart').getContext('2d');
        const salesChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: 'Ventes (Fc)',
              data: values,
              backgroundColor: 'rgba(59, 130, 246, 0.5)',
              borderColor: 'rgba(59, 130, 246, 1)',
              borderWidth: 2,
              borderRadius: 6,
              borderSkipped: false,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.parsed.y.toFixed(2) + ' Fc';
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                grid: {
                  color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                  callback: function(value) {
                    return value + ' Fc';
                  }
                }
              },
              x: {
                grid: {
                  display: false
                }
              }
            }
          }
        });
      </script>