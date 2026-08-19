<style>
  .ps-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem; margin-top:1rem }
  .ps-card { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:1rem }
  .ps-card-top { display:flex; justify-content:space-between; align-items:center }
  .ps-numero { font-weight:700; font-family:'JetBrains Mono',monospace; font-size:.9rem }
  .ps-client { font-size:.82rem; color:var(--muted,#64748b); margin-top:.2rem }
  .ps-steps { display:flex; align-items:center; margin-top:1rem }
  .ps-step { flex:1; text-align:center; position:relative }
  .ps-step-dot { width:16px; height:16px; border-radius:50%; background:#e2e8f0; margin:0 auto; position:relative; z-index:1 }
  .ps-step.done .ps-step-dot { background:#16a34a }
  .ps-step.current .ps-step-dot { background:#0B5E88; box-shadow:0 0 0 4px rgba(11,94,136,.2) }
  .ps-step-line { position:absolute; top:8px; left:-50%; width:100%; height:2px; background:#e2e8f0; z-index:0 }
  .ps-step:first-child .ps-step-line { display:none }
  .ps-step.done .ps-step-line, .ps-step.current .ps-step-line { background:#16a34a }
  .ps-step-label { font-size:.62rem; margin-top:.3rem; color:var(--muted,#64748b) }
  .ps-actions { margin-top:1rem; display:flex; gap:.5rem }
  .ps-actions select { flex:1; padding:.4rem; border:1px solid #ddd; border-radius:4px; font-size:.82rem }
  .ps-paid-badge { font-size:.68rem; font-weight:700; padding:2px 8px; border-radius:20px; margin-left:.5rem }
  .ps-paid-badge.oui { background:#dcfce7; color:#16a34a }
  .ps-paid-badge.non { background:#fee2e2; color:#dc2626 }
  .ps-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center }
  .ps-modal-content { background:#fff; border-radius:10px; width:600px; max-width:92%; max-height:90vh; overflow-y:auto; padding:1.5rem }
  .ps-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem }
  .ps-modal h4 { margin:1rem 0 .5rem; font-size:.95rem; border-bottom:1px solid #f1f5f9; padding-bottom:.35rem }
  .ps-timeline { list-style:none; padding:0; margin:0 }
  .ps-timeline li { padding:.45rem 0; border-bottom:1px solid #f1f5f9; font-size:.85rem; color:#334155 }
  .ps-timeline li:last-child { border-bottom:none }
  .ps-photos { display:flex; gap:.5rem; flex-wrap:wrap }
  .ps-photos img { width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #ddd }
</style>

<div id="page-pressing-suivi" class="page <?= $page == 'pressing-suivi' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M6 2v6a6 6 0 0 0 12 0V2"></path><line x1="4" y1="22" x2="20" y2="22"></line><path d="M6 22v-6a6 6 0 0 1 12 0v6"></path>
      </svg>
      Pressing — Suivi des dépôts
    </h2>
  </div>

  <?php
  $psSteps = ['recu' => 'Reçu', 'en_lavage' => 'Lavage', 'en_sechage' => 'Séchage', 'en_repassage' => 'Repassage', 'pret' => 'Prêt', 'livre' => 'Livré'];
  $psStepKeys = array_keys($psSteps);
  ?>

  <?php if (empty($depots)): ?>
    <div class="empty-state" style="text-align:center;padding:3rem"><p style="color:var(--muted,#64748b)">Aucun dépôt en cours</p></div>
  <?php else: ?>
    <div class="ps-grid">
      <?php foreach ($depots as $d): ?>
        <?php $currentIdx = array_search($d['statut'], $psStepKeys); ?>
        <div class="ps-card" data-id="<?= $d['id'] ?>">
          <div class="ps-card-top">
            <span class="ps-numero"><?= htmlspecialchars($d['numero']) ?></span>
            <span class="ps-paid-badge <?= !empty($d['vente_id']) ? 'oui' : 'non' ?>"><?= !empty($d['vente_id']) ? 'Payé' : 'Non payé' ?></span>
          </div>
          <div class="ps-client">
            <?= htmlspecialchars($d['nom_client'] ?? 'N/A') ?> · Total <?= number_format($d['total'], 2) ?> Fc
            <?= (!empty($d['vente_id']) || (float)$d['paid_amount'] >= (float)$d['total']) ? '· <span style="color:#16a34a;font-weight:600">Payé</span>' : '· Solde <span style="color:#dc2626;font-weight:600">' . number_format(max(0, (float)$d['total'] - (float)$d['paid_amount']), 2) . ' Fc</span>' ?>
          </div>

          <div class="ps-steps">
            <?php foreach ($psSteps as $key => $label): $idx = array_search($key, $psStepKeys); ?>
              <div class="ps-step <?= $idx < $currentIdx ? 'done' : ($idx == $currentIdx ? 'current' : '') ?>">
                <div class="ps-step-line"></div>
                <div class="ps-step-dot"></div>
                <div class="ps-step-label"><?= $label ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="ps-actions" style="justify-content:space-between">
            <button class="btn btn-ghost btn-small" onclick="showPsDetails(<?= $d['id'] ?>)">Détails</button>
            <?php if ($d['statut'] !== 'livre'): ?>
              <select onchange="updatePsStatus(<?= $d['id'] ?>, this.value)" style="flex:1;max-width:160px;margin-left:.5rem">
                <?php foreach ($psSteps as $key => $label): ?>
                  <?php if ($key === 'livre') continue; // livre uniquement via /pressing/retrait ?>
                  <option value="<?= $key ?>" <?= $d['statut'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Détails -->
<div id="ps-detail-modal" class="ps-modal" style="display:none" onclick="if(event.target===this) closePsDetails()">
  <div class="ps-modal-content">
    <div class="ps-modal-header">
      <h3 id="ps-detail-title" style="margin:0">Détails du dépôt</h3>
      <button style="background:none;border:none;font-size:1.5rem;cursor:pointer" onclick="closePsDetails()">&times;</button>
    </div>
    <div id="ps-detail-body"></div>
  </div>
</div>

<script>
async function updatePsStatus(id, statut) {
  try {
    const res = await fetch(`/api/pressing/depots/${id}/statut`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ statut })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function showPsDetails(id) {
  try {
    const res = await fetch(`/api/pressing/depots/${id}`);
    const d = await res.json();
    if (!res.ok || d.error) {
      alert(d.error || 'Erreur');
      return;
    }

    const timeline = (d.historique || []).map(h =>
      `<li><strong>${h.nouveau_statut}</strong> <em style="color:#64748b">(${h.created_at})</em> — par ${h.changed_by_name || '—'}</li>`
    ).join('') || '<li>Aucun historique</li>';

    const payments = (d.paiements || []).map(p =>
      `<li>${p.mode_paiement} : ${parseFloat(p.montant).toFixed(2)} Fc <em style="color:#64748b">(${p.created_at})</em></li>`
    ).join('') || '<li>Aucun paiement</li>';

    const photos = (d.photos || []).map(ph =>
      `<img src="/${ph.chemin}" alt="">`
    ).join('') || '<p style="color:#64748b;font-size:.85rem">Aucune photo</p>';

    document.getElementById('ps-detail-title').textContent = `Dépôt ${d.numero}`;
    document.getElementById('ps-detail-body').innerHTML = `
      <p><strong>Client:</strong> ${d.nom_client || 'N/A'} · <strong>Tel:</strong> ${d.client_numero || '—'}</p>
      <p><strong>Total:</strong> ${parseFloat(d.total).toFixed(2)} Fc · <strong>Payé:</strong> ${parseFloat(d.paid_amount || 0).toFixed(2)} Fc · <strong>Solde:</strong> ${parseFloat(d.solde || 0).toFixed(2)} Fc</p>
      <p><strong>Adresse livraison:</strong> ${d.adresse_livraison || '—'}</p>
      <p><strong>Retour prévu:</strong> ${d.date_retour_prevue ? new Date(d.date_retour_prevue).toLocaleDateString('fr-FR') : '—'}</p>

      <div style="margin:1rem 0">
        <button class="btn btn-secondary" onclick="window.open('/pressing/ticket?numero=' + encodeURIComponent('${d.numero}'), '_blank')">Imprimer le ticket</button>
      </div>

      <h4>Historique des statuts</h4>
      <ul class="ps-timeline">${timeline}</ul>

      <h4>Paiements</h4>
      <ul class="ps-timeline">${payments}</ul>

      <h4>Photos</h4>
      <div class="ps-photos">${photos}</div>
    `;
    document.getElementById('ps-detail-modal').style.display = 'flex';
  } catch (e) {
    alert('Erreur réseau');
  }
}

function closePsDetails() {
  document.getElementById('ps-detail-modal').style.display = 'none';
}
</script>
