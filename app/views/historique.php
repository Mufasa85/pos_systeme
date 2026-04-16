      <!-- History Page -->
      <div class="page active" style="display:block;">
        <div class="page-header">
          <h2>Historique des ventes</h2>
        </div>
        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead style="background:var(--background); text-align:left;">
              <tr>
                <th style="padding:0.75rem;">N° Facture</th>
                <th style="padding:0.75rem;">Date</th>
                <th style="padding:0.75rem;">Vendeur</th>
                <th style="padding:0.75rem;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($ventes)): ?>
               <tr><td colspan="4" style="text-align:center; padding:1rem;">Aucune vente enregistrée.</td></tr>
              <?php else: ?>
                <?php foreach($ventes as $v): ?>
                <tr style="border-bottom:1px solid #eee;">
                  <td style="padding:0.75rem;"><?= htmlspecialchars($v['numero_facture']) ?></td>
                  <td style="padding:0.75rem;"><?= date('d/m/Y H:i', strtotime($v['date'])) ?></td>
                  <td style="padding:0.75rem;"><?= htmlspecialchars($v['nom_vendeur']) ?></td>
                  <td style="padding:0.75rem;"><strong><?= number_format($v['total'], 2) ?> Fc</strong></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
