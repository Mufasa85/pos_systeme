<style>
  .ph-filters { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem }
  .ph-filters select, .ph-filters input { padding:.5rem; border:1px solid #ddd; border-radius:6px; font-size:.85rem }
  .ph-table { width:100%; border-collapse:collapse; margin-top:1rem; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; overflow:hidden }
  .ph-table th, .ph-table td { padding:.6rem .9rem; text-align:left; font-size:.85rem; border-bottom:1px solid #f1f5f9 }
  .ph-table th { background:#f8fafc; font-weight:600; color:var(--muted,#64748b); font-size:.75rem; text-transform:uppercase }
  .ph-badge { display:inline-block; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px }
  .ph-badge.recu { background:#e0e7ff; color:#4338ca }
  .ph-badge.en_lavage, .ph-badge.en_sechage, .ph-badge.en_repassage { background:#dbeafe; color:#1d4ed8 }
  .ph-badge.pret { background:#fef3c7; color:#b45309 }
  .ph-badge.livre { background:#dcfce7; color:#16a34a }
</style>

<div id="page-pressing-historique" class="page <?= $page == 'pressing-historique' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
      </svg>
      Pressing — Historique des dépôts
    </h2>
  </div>

  <form class="ph-filters" method="get" action="/pressing/historique">
    <select name="statut">
      <option value="">Tous les statuts</option>
      <?php foreach (['recu' => 'Reçu', 'en_lavage' => 'Lavage', 'en_sechage' => 'Séchage', 'en_repassage' => 'Repassage', 'pret' => 'Prêt', 'livre' => 'Livré'] as $key => $label): ?>
        <option value="<?= $key ?>" <?= ($filters['statut'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" placeholder="Du">
    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" placeholder="Au">
    <button class="btn btn-secondary" type="submit">Filtrer</button>
    <a href="/pressing/historique" class="btn btn-ghost">Réinitialiser</a>
  </form>

  <table class="ph-table">
    <thead>
      <tr><th>Numéro</th><th>Client</th><th>Date réception</th><th>Date prévue</th><th>Retour prévu</th><th>Statut</th><th>Total</th><th>Payé</th><th>Solde</th></tr>
    </thead>
    <tbody>
      <?php if (empty($depots)): ?>
        <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--muted,#64748b)">Aucun dépôt trouvé</td></tr>
      <?php else: ?>
        <?php foreach ($depots as $d):
          $paid = (float)($d['paid_amount'] ?? 0);
          $total = (float)$d['total'];
          $isPaid = !empty($d['vente_id']) || $paid >= $total;
        ?>
          <tr>
            <td><code><?= htmlspecialchars($d['numero']) ?></code></td>
            <td><?= htmlspecialchars($d['nom_client'] ?? 'N/A') ?></td>
            <td><?= date('d/m/Y H:i', strtotime($d['date_reception'])) ?></td>
            <td><?= !empty($d['date_prevue']) ? date('d/m/Y', strtotime($d['date_prevue'])) : '—' ?></td>
            <td><?= !empty($d['date_retour_prevue']) ? date('d/m/Y', strtotime($d['date_retour_prevue'])) : '—' ?></td>
            <td><span class="ph-badge <?= $d['statut'] ?>"><?= htmlspecialchars($d['statut']) ?></span></td>
            <td><?= number_format($d['total'], 2) ?> Fc</td>
            <td style="color:<?= $isPaid ? '#16a34a' : '#dc2626' ?>"><?= $isPaid ? '✅' : number_format($paid, 2) . ' Fc' ?></td>
            <td style="color:<?= $isPaid ? '#16a34a' : '#dc2626' ?>"><?= number_format(max(0, $total - $paid), 2) ?> Fc</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
