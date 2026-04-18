      <!-- Dashboard Page -->
      <div class="page active" style="display:block;">
        <div class="page-header">
          <h2>Tableau de bord</h2>
          <p id="current-date"><?= date('d/m/Y') ?></p>
        </div>
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
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 3h18v8H3z"></path>
                <path d="M3 12h18v7H3z"></path>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Ventes de la semaine</span>
              <span class="stat-value" id="stat-week"><?= number_format($ventes_semaine ?? 0, 2) ?> Fc</span>
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
          <div class="stat-card">
            <div class="stat-icon orange">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
            </div>
            <div class="stat-info">
              <span class="stat-label">Stock faible</span>
              <span class="stat-value" id="stat-low-stock"><?= count($stock_faible ?? []) ?></span>
            </div>
          </div>
        </div>
        
        <div class="dashboard-grid">
          <div class="card">
            <div class="card-header">
              <h3>Dernières ventes</h3>
            </div>
            <div class="card-body">
              <div id="recent-sales" class="recent-list">
                <?php if(!empty($ventes)): ?>
                    <?php foreach(array_slice($ventes, 0, 5) as $v): ?>
                    <div class="recent-item">
                      <div>
                        <strong><?= htmlspecialchars($v['numero_facture']) ?></strong>
                        <span style="margin-left: 0.5rem; color: var(--muted);"><?= number_format($v['total'], 2) ?> Fc</span>
                      </div>
                      <span class="time"><?= date('d/m/Y H:i', strtotime($v['date'])) ?> par <?= htmlspecialchars($v['nom_vendeur'] ?? 'Inconnu') ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">Aucune vente récente</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <div class="card">
            <div class="card-header">
              <h3>Alertes de stock</h3>
            </div>
            <div class="card-body">
              <div id="stock-alerts" class="alert-list">
                <?php if(!empty($stock_faible)): ?>
                    <?php foreach($stock_faible as $sf): ?>
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
      </div>
