<style>
  .pw-search-box { max-width:500px; margin-top:1rem; display:flex; gap:.5rem }
  .pw-search-box input { flex:1; padding:.6rem; border:1px solid #ddd; border-radius:6px }
  .pw-result { max-width:500px; margin-top:1.5rem; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:1.25rem }
  .pw-result-row { display:flex; justify-content:space-between; padding:.4rem 0; font-size:.88rem; border-bottom:1px solid #f1f5f9 }
  .pw-articles { margin-top:.75rem; font-size:.82rem }
  .pw-badge { display:inline-block; font-size:.7rem; font-weight:700; padding:2px 10px; border-radius:20px; margin-top:.5rem }
  .pw-badge.paye { background:#dcfce7; color:#16a34a }
  .pw-badge.impaye { background:#fee2e2; color:#dc2626 }
</style>

<div id="page-pressing-retrait" class="page <?= $page == 'pressing-retrait' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M6 2v6a6 6 0 0 0 12 0V2"></path><line x1="4" y1="22" x2="20" y2="22"></line><path d="M6 22v-6a6 6 0 0 1 12 0v6"></path>
      </svg>
      Pressing — Retrait
    </h2>
  </div>

  <div class="pw-search-box">
    <input type="text" id="pw-numero" placeholder="Scanner le QR code ou saisir le numéro (ex: PR-20260809-0001)">
    <button class="btn btn-primary" onclick="searchPwDepot()">Rechercher</button>
  </div>

  <div id="pw-result" class="pw-result" style="display:none"></div>
</div>

<script>
let pwCurrentDepot = null;

async function searchPwDepot() {
  const numero = document.getElementById('pw-numero').value.trim();
  if (!numero) return;

  try {
    const res = await fetch(`/api/pressing/depots/search?numero=${encodeURIComponent(numero)}`);
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Dépôt introuvable');
      document.getElementById('pw-result').style.display = 'none';
      return;
    }
    pwCurrentDepot = data;
    renderPwResult(data);
  } catch (e) {
    alert('Erreur réseau');
  }
}

function renderPwResult(d) {
  const total = parseFloat(d.total) || 0;
  const paid = parseFloat(d.paid_amount || d.paid || 0) || 0;
  const solde = parseFloat(d.solde) || Math.max(0, total - paid);
  const isPaid = paid >= total;
  const isDelivered = d.statut === 'livre';

  const articlesHtml = (d.articles || []).map(a =>
    `<div>${a.quantite}x ${a.nom_article} (${a.service}) — ${parseFloat(a.prix_total).toFixed(2)} Fc</div>`
  ).join('');

  document.getElementById('pw-result').innerHTML = `
    <div class="pw-result-row"><strong>${d.numero}</strong><span class="pw-badge ${isPaid ? 'paye' : 'impaye'}">${isPaid ? 'Payé' : 'Solde ' + solde.toFixed(2) + ' Fc'}</span></div>
    <div class="pw-result-row"><span>Client</span><span>${d.nom_client || 'N/A'}</span></div>
    <div class="pw-result-row"><span>Statut</span><span>${d.statut}</span></div>
    <div class="pw-result-row"><span>Total</span><span>${total.toFixed(2)} Fc</span></div>
    <div class="pw-result-row"><span>Payé</span><span style="color:${paid>=total?'#16a34a':'#dc2626'}">${paid.toFixed(2)} Fc</span></div>
    <div class="pw-result-row"><span>Solde</span><span>${solde.toFixed(2)} Fc</span></div>
    <div class="pw-result-row"><span>Retour prévu</span><span>${d.date_retour_prevue ? new Date(d.date_retour_prevue).toLocaleDateString('fr-FR') : '—'}</span></div>
    <div class="pw-result-row"><span>Adresse livraison</span><span>${d.adresse_livraison || '—'}</span></div>
    <div class="pw-articles">${articlesHtml}</div>
    <div style="margin-top:1.25rem; display:flex; gap:.5rem; flex-wrap:wrap">
      ${isDelivered
        ? '<span style="color:#64748b">Ce dépôt a déjà été livré.</span>'
        : (isPaid
          ? `<button class="btn btn-primary" onclick="validatePwWithdraw(${d.id})">Valider le retrait</button>`
          : `<button class="btn btn-primary" onclick="openPwPaymentModal(${d.id})">Encaisser</button>`)
      }
      ${isPaid ? `<button class="btn btn-secondary" onclick="window.open('/pressing/ticket?numero=' + encodeURIComponent('${d.numero}'), '_blank')">Imprimer ticket</button>` : ''}
    </div>
    <div id="pw-payment-form" style="display:none; margin-top:1rem; border-top:1px solid #f1f5f9; padding-top:1rem">
      <div style="display:grid; gap:.5rem">
        <label style="font-weight:500">Montant à encaisser</label>
        <input type="number" step="0.01" id="pw-payment-montant" value="${solde.toFixed(2)}" style="padding:.5rem;border:1px solid #ddd;border-radius:4px" placeholder="Montant">
        <label style="font-weight:500">Mode de paiement</label>
        <select id="pw-payment-mode" style="padding:.5rem;border:1px solid #ddd;border-radius:4px">
          <option value="cash">Espèces</option>
          <option value="carte">Carte</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="virement">Virement</option>
          <option value="autre">Autre</option>
        </select>
        <label style="font-weight:500">Référence</label>
        <input type="text" id="pw-payment-reference" placeholder="Référence (optionnel)" style="padding:.5rem;border:1px solid #ddd;border-radius:4px">
        <button class="btn btn-primary" onclick="confirmPwPayment(${d.id})" style="margin-top:.5rem">Confirmer le paiement</button>
      </div>
    </div>
  `;
  document.getElementById('pw-result').style.display = 'block';
}

function openPwPaymentModal(id) {
  document.getElementById('pw-payment-form').style.display = 'block';
}

async function confirmPwPayment(id) {
  const montant = parseFloat(document.getElementById('pw-payment-montant').value) || 0;
  const mode = document.getElementById('pw-payment-mode').value;
  const reference = document.getElementById('pw-payment-reference').value;

  if (montant <= 0) {
    alert('Montant invalide');
    return;
  }

  try {
    const res = await fetch(`/api/pressing/depots/${id}/paiements`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ montant, mode_paiement: mode, reference })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors du paiement');
      return;
    }
    const solde = parseFloat(data.solde || 0);
    if (solde <= 0.001) {
      window.open('/pressing/ticket?numero=' + encodeURIComponent(pwCurrentDepot.numero), '_blank');
    } else {
      alert('Paiement enregistré. Solde restant : ' + solde.toFixed(2) + ' Fc');
    }
    searchPwDepot();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function validatePwWithdraw(id) {
  if (!confirm('Confirmer le retrait de cette commande ?')) return;
  try {
    const res = await fetch(`/api/pressing/depots/${id}/retrait`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors du retrait');
      return;
    }
    alert('Retrait validé !');
    searchPwDepot();
  } catch (e) {
    alert('Erreur réseau');
  }
}

document.getElementById('pw-numero').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') searchPwDepot();
});
</script>
