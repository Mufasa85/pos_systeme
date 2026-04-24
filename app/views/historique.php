      <!-- History Page -->
      <div id="page-history" class="page <?= $page == 'historique' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Historique des ventes</h2>
        </div>
        <div class="filters-bar">
          <input type="date" id="date-filter">
          <select id="seller-filter">
            <option value="all">Tous les vendeurs</option>
          </select>
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>N Facture</th>
                <th>Date</th>
                <th>Vendeur</th>
                <th>Total</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ventes)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding:1rem;">Aucune vente enregistrée.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($ventes as $v): ?>
                  <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:0.75rem;"><?= htmlspecialchars($v['numero_facture']) ?></td>
                    <td style="padding:0.75rem;"><?= date('d/m/Y H:i', strtotime($v['date'])) ?></td>
                    <td style="padding:0.75rem;"><?= htmlspecialchars($v['nom_vendeur']) ?></td>
                    <td style="padding:0.75rem;"><strong><?= number_format($v['total'], 2) ?> Fc</strong></td>
                    <td style="padding:0.75rem;">
                      <button class="btn btn-ghost btn-small" onclick="viewSaleDetails(<?= $v['id'] ?>)" title="Voir les détails">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="12" y1="16" x2="12" y2="12"></line>
                          <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sale Details Modal -->
      <div id="sale-details-modal" class="modal">
        <div class="modal-content" style="max-width:500px;">
          <div class="modal-header">
            <h3 id="sale-details-title">Détails de la vente</h3>
            <button class="close-modal" onclick="document.getElementById('sale-details-modal').classList.remove('active')">&times;</button>
          </div>
          <div id="sale-details-content" style="padding:1rem;">
            <!-- Contenu généré par JavaScript -->
          </div>
        </div>
      </div>