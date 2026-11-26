      <!-- Alertes de péremption — liste complète paginée -->
      <div id="page-alertes-lots" class="page <?= ($page ?? '') == 'alertes-lots' ? 'active' : '' ?>">
        <div class="page-header">
          <h2>Alertes de péremption</h2>
        </div>

        <div class="filters-bar">
          <?php
          /*
           * Le filtre est un simple lien (pas de JS) : la pagination
           * se fait côté serveur, le paramètre est dans l'URL, donc la
           * page est partageable et le bouton « Précédent » du
           * navigateur reste cohérent.
           */
          $qsAlertes = function (array $overrides = []) use ($filtre, $days) {
              $params = array_merge(['filtre' => $filtre, 'days' => $days, 'page' => 1], $overrides);
              if (empty($params['days'])) {
                  unset($params['days']);
              }
              return '/alertes-lots?' . http_build_query($params);
          };
          ?>
          <a class="btn <?= $filtre === 'all' ? 'btn-primary' : 'btn-ghost' ?> btn-small"
             href="<?= htmlspecialchars($qsAlertes(['filtre' => 'all'])) ?>">Tous</a>
          <a class="btn <?= $filtre === 'expired' ? 'btn-primary' : 'btn-ghost' ?> btn-small"
             href="<?= htmlspecialchars($qsAlertes(['filtre' => 'expired'])) ?>">Déjà périmés</a>
          <a class="btn <?= $filtre === 'soon' ? 'btn-primary' : 'btn-ghost' ?> btn-small"
             href="<?= htmlspecialchars($qsAlertes(['filtre' => 'soon'])) ?>">Périment bientôt</a>

          <label style="display:flex; align-items:center; gap:6px; margin-left:12px; font-size:0.9rem;">
            Fenêtre :
            <select onchange="window.location.href=this.value">
              <option value="<?= htmlspecialchars($qsAlertes(['filtre' => 'soon', 'days' => 7])) ?>" <?= (int)$days === 7 ? 'selected' : '' ?>>7 jours</option>
              <option value="<?= htmlspecialchars($qsAlertes(['filtre' => 'soon', 'days' => 30])) ?>" <?= (int)$days === 30 ? 'selected' : '' ?>>30 jours</option>
              <option value="<?= htmlspecialchars($qsAlertes(['filtre' => 'soon', 'days' => 90])) ?>" <?= (int)$days === 90 ? 'selected' : '' ?>>90 jours</option>
            </select>
          </label>
        </div>

        <div class="table-container">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Code-barres</th>
                <th>Lot</th>
                <th>Stock</th>
                <th>Péremption</th>
                <th>État</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($lots)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding:1rem;">Aucune alerte de péremption.</td>
                </tr>
              <?php else: ?>
                <?php
                $today = strtotime(date('Y-m-d'));
                foreach ($lots as $lot):
                    $exp = $lot['date_expiration'] ? strtotime($lot['date_expiration']) : null;
                    $daysLeft = $exp ? (int) floor(($exp - $today) / 86400) : null;
                    $expired = $exp !== null && $exp < $today;
                ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($lot['product_name'] ?? '') ?></strong></td>
                    <td><code><?= htmlspecialchars($lot['code_barres'] ?? '') ?></code></td>
                    <td><?= htmlspecialchars($lot['batch_number'] ?? '—') ?></td>
                    <td><?= number_format((float)($lot['stock'] ?? 0), 2) ?></td>
                    <td><?= $lot['date_expiration'] ? date('d/m/Y', $exp) : '—' ?></td>
                    <td>
                      <?php if ($expired): ?>
                        <span class="badge badge-danger">Périmé</span>
                      <?php else: ?>
                        <span class="badge badge-warning"><?= (int)$daysLeft ?>j</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php
        $nbLignes  = count($lots);
        $debut     = $nbLignes > 0 ? (($pageNum - 1) * $perPage) + 1 : 0;
        $fin       = ($pageNum - 1) * $perPage + $nbLignes;
        ?>
        <div class="products-pagination">
          <div class="pagination-info">
            <?php if ($total > 0): ?>
              <?= number_format($debut) ?>–<?= number_format($fin) ?>
              sur <?= number_format($total) ?> lot<?= $total > 1 ? 's' : '' ?>
            <?php else: ?>
              Aucun lot
            <?php endif; ?>
          </div>
          <?php if ($pages > 1): ?>
            <div class="pagination-buttons">
              <a class="btn btn-small"
                 href="<?= htmlspecialchars($qsAlertes(['page' => 1])) ?>"
                 title="Première page">&laquo; Première</a>
              <a class="btn btn-small<?= $pageNum <= 1 ? ' is-disabled' : '' ?>"
                 href="<?= htmlspecialchars($qsAlertes(['page' => max(1, $pageNum - 1)])) ?>"
                 title="Page précédente"
                 <?= $pageNum <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&lsaquo; Précédent</a>
              <span class="pagination-page">Page <?= (int)$pageNum ?> / <?= (int)$pages ?></span>
              <a class="btn btn-small<?= $pageNum >= $pages ? ' is-disabled' : '' ?>"
                 href="<?= htmlspecialchars($qsAlertes(['page' => min($pages, $pageNum + 1)])) ?>"
                 title="Page suivante"
                 <?= $pageNum >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Suivant &rsaquo;</a>
              <a class="btn btn-small"
                 href="<?= htmlspecialchars($qsAlertes(['page' => $pages])) ?>"
                 title="Dernière page">Dernière &raquo;</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
