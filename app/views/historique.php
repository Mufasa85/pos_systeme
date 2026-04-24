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
        <div class="modal-content receipt-modal">
          <div class="receipt-scrollable">
            <div id="sale-details-content">
              <!-- Contenu généré par JavaScript -->
            </div>
          </div>
          <div class="receipt-actions">
            <button class="btn btn-secondary" onclick="document.getElementById('sale-details-modal').classList.remove('active')">
              Annuler
            </button>
            <button class="btn btn-primary" id="print-sale-btn">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
              Imprimer
            </button>
          </div>
        </div>
      </div>