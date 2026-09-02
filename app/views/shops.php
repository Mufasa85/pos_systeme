<style>
  .shops-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:1.25rem; margin-top:1rem }
  .shop-card { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:var(--radius,10px); overflow:hidden; transition:box-shadow .2s,transform .15s }
  .shop-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); transform:translateY(-2px) }
  .shop-card-header { display:flex; align-items:center; gap:1rem; padding:1.25rem 1.25rem .75rem }
  .shop-card-icon { width:48px; height:48px; border-radius:12px; background:var(--primary,#0B5E88); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; flex-shrink:0 }
  .shop-card-title { font-weight:700; font-size:1.05rem; line-height:1.3 }
  .shop-card-code { font-size:.75rem; color:var(--muted,#94a3b8); font-family:'JetBrains Mono',monospace; letter-spacing:.03em }
  .shop-card-body { padding:0 1.25rem; display:grid; gap:.5rem }
  .shop-detail { display:flex; align-items:center; gap:.6rem; font-size:.85rem; color:var(--text-secondary,#475569) }
  .shop-detail svg { flex-shrink:0; color:var(--muted,#94a3b8) }
  .shop-card-footer { display:flex; gap:.5rem; padding:1rem 1.25rem; border-top:1px solid var(--border,#e2e8f0); margin-top:1rem }
  .shop-card-footer .btn { flex:1; justify-content:center; font-size:.8rem; padding:.45rem .6rem }
  .shop-badge-active { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px; background:#dcfce7; color:#16a34a }
  .shop-badge-inactive { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px; background:#fee2e2; color:#dc2626 }
  .shop-badge-dot { width:6px; height:6px; border-radius:50%; background:currentColor }
  .shop-badge-homologuee { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px; background:#dbeafe; color:#1d4ed8 }
  .shop-badge-non-homologuee { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px; background:#f1f5f9; color:#64748b }
  .homologation-toggle { display:flex; align-items:center; gap:.5rem; padding:.6rem .75rem; background:var(--background,#f8fafc); border:1px solid var(--border,#e2e8f0); border-radius:8px }
  .homologation-toggle input { width:auto; margin:0 }
  .shop-legal { display:flex; gap:.75rem; flex-wrap:wrap; padding:0 1.25rem; margin-top:.25rem }
  .shop-legal-item { font-size:.72rem; color:var(--muted,#94a3b8); background:var(--background,#f8fafc); padding:2px 8px; border-radius:4px }
  .shop-legal-item strong { color:var(--text-secondary,#475569); margin-right:3px }

  /* Responsive modal adjustments */
  #shop-modal .modal-content {
    width: min(100%, 560px);
    max-width: 100%;
    max-height: 90vh;
    overflow-y: auto;
  }
  #shop-modal .modal-content form { padding: 0; }
  #shop-modal .modal-header, #shop-modal .modal-actions { padding: 1rem 1.25rem; }
  #shop-modal .modal-actions { flex-wrap: wrap; justify-content: flex-end; gap: 0.75rem; }
  #shop-modal .modal-actions .btn { min-width: 140px; }
  #shop-modal .form-group input, #shop-modal .form-group select { width: 100%; }
  #shop-modal .modal-content > div:last-child { padding-bottom: 1rem; }

  @media (max-width: 680px) {
    #shop-modal .modal-content { width: calc(100% - 1.5rem); margin: 0 auto; }
    #shop-modal .modal-content form { padding: 0 1rem 1rem; }
    #shop-modal .modal-header, #shop-modal .modal-actions { padding: 1rem; }
    #shop-modal .modal-content > div:first-child { padding-top: 1rem; }
    #shop-modal .modal-content div[style*="display:flex"] { display: block !important; }
    #shop-modal .modal-content div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    #shop-modal .modal-content div[style*="display:flex;gap:.75rem"] > .form-group { width: 100%; }
  }
</style>

<div class="page-header">
  <div>
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
      </svg>
      Gestion des Boutiques
    </h2>
    <p style="font-size:.85rem;color:var(--muted,#64748b);margin-top:.25rem"><?= count($shops) ?> boutique<?= count($shops) > 1 ? 's' : '' ?> enregistrée<?= count($shops) > 1 ? 's' : '' ?></p>
  </div>
  <button class="btn btn-primary" onclick="openAddShopModal()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle boutique
  </button>
</div>

<?php if (empty($shops)): ?>
  <div class="empty-state" style="text-align:center;padding:3rem">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#94a3b8)" stroke-width="1.5" style="margin-bottom:1rem"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
    <p style="color:var(--muted,#64748b)">Aucune boutique enregistrée</p>
    <button class="btn btn-primary" onclick="openAddShopModal()" style="margin-top:1rem">Ajouter la première boutique</button>
  </div>
<?php else: ?>
<div class="shops-grid">
  <?php foreach ($shops as $shop): ?>
  <div class="shop-card">
    <div class="shop-card-header">
      <div class="shop-card-icon"><?= strtoupper(substr($shop['nom'], 0, 2)) ?></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
          <span class="shop-card-title"><?= htmlspecialchars($shop['nom']) ?></span>
          <?php if ($shop['actif']): ?>
            <span class="shop-badge-active"><span class="shop-badge-dot"></span>Active</span>
          <?php else: ?>
            <span class="shop-badge-inactive"><span class="shop-badge-dot"></span>Inactive</span>
          <?php endif; ?>
          <?php if (!empty($shop['homologation'])): ?>
            <span class="shop-badge-homologuee" title="Boutique homologuée DGI (RCCM/licence en règle)">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              Homologuée
            </span>
          <?php else: ?>
            <span class="shop-badge-non-homologuee">Non homologuée</span>
          <?php endif; ?>
        </div>
        <div class="shop-card-code"><?= htmlspecialchars($shop['code']) ?></div>
      </div>
    </div>
    <div class="shop-card-body">
      <?php if (!empty($shop['adresse'])): ?>
      <div class="shop-detail">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <?= htmlspecialchars($shop['adresse']) ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($shop['telephone'])): ?>
      <div class="shop-detail">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.11 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.7 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.76.34 1.54.57 2.35.7A2 2 0 0 1 22 16.92z"/></svg>
        <?= htmlspecialchars($shop['telephone']) ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($shop['email'])): ?>
      <div class="shop-detail">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <?= htmlspecialchars($shop['email']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($shop['ice']) || !empty($shop['rccm']) || !empty($shop['isf']) || !empty($shop['pdv']) || !empty($shop['nid']) || !empty($shop['token']) || !empty($shop['port'])): ?>
    <div class="shop-legal">
      <?php if (!empty($shop['ice'])): ?><span class="shop-legal-item"><strong>ICE</strong><?= htmlspecialchars($shop['ice']) ?></span><?php endif; ?>
      <?php if (!empty($shop['rccm'])): ?><span class="shop-legal-item"><strong>RCCM</strong><?= htmlspecialchars($shop['rccm']) ?></span><?php endif; ?>
      <?php if (!empty($shop['isf'])): ?><span class="shop-legal-item"><strong>ISF</strong><?= htmlspecialchars($shop['isf']) ?></span><?php endif; ?>
      <?php if (!empty($shop['pdv'])): ?><span class="shop-legal-item"><strong>PDV</strong><?= htmlspecialchars($shop['pdv']) ?></span><?php endif; ?>
      <?php if (!empty($shop['nid'])): ?><span class="shop-legal-item"><strong>NID</strong><?= htmlspecialchars($shop['nid']) ?></span><?php endif; ?>
      <?php if (!empty($shop['token'])): ?><span class="shop-legal-item"><strong>TOKEN</strong><?= htmlspecialchars($shop['token']) ?></span><?php endif; ?>
      <?php if (!empty($shop['port'])): ?><span class="shop-legal-item"><strong>PORT</strong><?= htmlspecialchars($shop['port']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="shop-card-footer">
      <button class="btn btn-secondary btn-small" onclick="openEditShopModal(<?= htmlspecialchars(json_encode($shop)) ?>)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"/></svg>
        Modifier
      </button>
      <button class="btn btn-small" onclick="deleteShop(<?= $shop['id'] ?>)" style="color:#e53e3e;border-color:#fecaca">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Supprimer
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Boutique -->
<div id="shop-modal" class="modal">
  <div class="modal-content" style="max-width:560px">
    <div class="modal-header">
      <h3 id="shop-modal-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Nouvelle boutique
      </h3>
      <button class="close-modal" onclick="closeShopModal()">&times;</button>
    </div>
    <form id="shop-form" onsubmit="saveShop(event)">
      <input type="hidden" id="shop-id" value="">
      <div style="padding:0 1.25rem 1.25rem">
        <!-- Section : Identité -->
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#94a3b8);margin-bottom:.5rem;margin-top:.25rem">Identité</div>
        <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
          <div class="form-group" style="flex:2;margin:0">
            <label style="font-size:.8rem;font-weight:600">Nom *</label>
            <input type="text" id="shop-nom" required placeholder="Nom de la boutique">
          </div>
          <div class="form-group" style="flex:1;margin:0">
            <label style="font-size:.8rem;font-weight:600">Code *</label>
            <input type="text" id="shop-code" required placeholder="SHOP01" style="text-transform:uppercase;font-family:'JetBrains Mono',monospace">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:.75rem">
          <label style="font-size:.8rem;font-weight:600">Adresse</label>
          <input type="text" id="shop-adresse" placeholder="Adresse complète">
        </div>

        <!-- Section : Contact -->
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#94a3b8);margin-bottom:.5rem;margin-top:1rem">Contact</div>
        <div style="display:flex;gap:.75rem;margin-bottom:.75rem">
          <div class="form-group" style="flex:1;margin:0">
            <label style="font-size:.8rem;font-weight:600">Téléphone</label>
            <input type="text" id="shop-telephone" placeholder="Ex: 0812345678">
          </div>
          <div class="form-group" style="flex:1;margin:0">
            <label style="font-size:.8rem;font-weight:600">Email</label>
            <input type="email" id="shop-email" placeholder="email@boutique.com">
          </div>
        </div>

        <!-- Section : Type de service -->
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#94a3b8);margin-bottom:.5rem;margin-top:1rem">Type de service</div>
        <div class="form-group" style="margin-bottom:.75rem">
          <label style="font-size:.8rem;font-weight:600">Type de service</label>
          <select id="shop-service-type" style="width:100%">
            <option value="">-- Sélectionner un type --</option>
          </select>
        </div>

        <!-- Section : Infos légales -->
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#94a3b8);margin-bottom:.5rem;margin-top:1rem">Informations légales</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">ICE</label>
            <input type="text" id="shop-ice" placeholder="Numéro ICE" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">RCCM</label>
            <input type="text" id="shop-rccm" placeholder="Numéro RCCM" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">ISF</label>
            <input type="text" id="shop-isf" placeholder="Numéro ISF" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">Point de vente (PDV)</label>
            <input type="text" id="shop-pdv" placeholder="Ex: Kinshasa Centre" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">NID</label>
            <input type="text" id="shop-nid" placeholder="Numéro NID" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">TOKEN</label>
            <input type="text" id="shop-token" placeholder="Clé API ou token" style="width:100%;box-sizing:border-box">
          </div>
          <div class="form-group" style="margin:0">
            <label style="font-size:.8rem;font-weight:600">PORT</label>
            <input type="text" id="shop-port" placeholder="Ex: 8080" style="width:100%;box-sizing:border-box">
          </div>
        </div>

        <label class="homologation-toggle" style="margin-bottom:.75rem;cursor:pointer">
          <input type="checkbox" id="shop-homologation">
          <span>
            <strong style="font-size:.8rem;display:block">Boutique homologuée (DGI)</strong>
            <span style="font-size:.72rem;color:var(--muted,#94a3b8)">À cocher si cette boutique possède une homologation valide (RCCM/licence). Cette information est transmise à la DGI lors de la validation des factures.</span>
          </span>
        </label>

        <!-- Statut -->
        <div class="form-group" style="margin-bottom:0;margin-top:1rem">
          <label style="font-size:.8rem;font-weight:600">Statut</label>
          <select id="shop-actif" style="max-width:200px">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div id="shop-error" style="color:#e53e3e;font-size:.85rem;padding:0 1.25rem;display:none;margin-bottom:.5rem"></div>
      <div class="modal-actions" style="padding:.75rem 1.25rem;border-top:1px solid var(--border,#e2e8f0)">
        <button type="button" class="btn btn-secondary" onclick="closeShopModal()">Annuler</button>
        <button type="submit" class="btn btn-primary" id="shop-submit-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const SHOPS_API = window.location.origin + '/api/shops';
const SERVICE_TYPES_API = window.location.origin + '/api/service-types';

let serviceTypes = [];

async function loadServiceTypes() {
  try {
    const res = await fetch(SERVICE_TYPES_API);
    serviceTypes = await res.json();
    const select = document.getElementById('shop-service-type');
    select.innerHTML = '<option value="">-- Sélectionner un type --</option>';
    serviceTypes.forEach(st => {
      const opt = document.createElement('option');
      opt.value = st.id;
      opt.textContent = st.name;
      select.appendChild(opt);
    });
  } catch (err) {
    console.error('Erreur chargement types de service:', err);
  }
}

function openAddShopModal() {
  document.getElementById('shop-modal-title').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>Nouvelle boutique';
  document.getElementById('shop-id').value = '';
  document.getElementById('shop-form').reset();
  document.getElementById('shop-error').style.display = 'none';
  document.getElementById('shop-modal').classList.add('active');
}

function openEditShopModal(shop) {
  document.getElementById('shop-modal-title').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"/></svg>Modifier la boutique';
  document.getElementById('shop-id').value = shop.id;
  document.getElementById('shop-nom').value = shop.nom || '';
  document.getElementById('shop-code').value = shop.code || '';
  document.getElementById('shop-adresse').value = shop.adresse || '';
  document.getElementById('shop-telephone').value = shop.telephone || '';
  document.getElementById('shop-email').value = shop.email || '';
  document.getElementById('shop-ice').value = shop.ice || '';
  document.getElementById('shop-rccm').value = shop.rccm || '';
  document.getElementById('shop-isf').value = shop.isf || '';
  document.getElementById('shop-pdv').value = shop.pdv || '';
  document.getElementById('shop-nid').value = shop.nid || '';
  document.getElementById('shop-token').value = shop.token || '';
  document.getElementById('shop-port').value = shop.port || '';
  document.getElementById('shop-service-type').value = shop.service_type_id || '';
  document.getElementById('shop-homologation').checked = !!(shop.homologation && shop.homologation !== '0');
  document.getElementById('shop-actif').value = shop.actif ?? '1';
  document.getElementById('shop-error').style.display = 'none';
  document.getElementById('shop-modal').classList.add('active');
}

function closeShopModal() {
  document.getElementById('shop-modal').classList.remove('active');
}

async function saveShop(e) {
  e.preventDefault();
  const errEl = document.getElementById('shop-error');
  errEl.style.display = 'none';
  const btn = document.getElementById('shop-submit-btn');
  btn.disabled = true;

  const id = document.getElementById('shop-id').value;
  const data = {
    nom: document.getElementById('shop-nom').value.trim(),
    code: document.getElementById('shop-code').value.trim().toUpperCase(),
    adresse: document.getElementById('shop-adresse').value.trim(),
    telephone: document.getElementById('shop-telephone').value.trim(),
    email: document.getElementById('shop-email').value.trim(),
    ice: document.getElementById('shop-ice').value.trim(),
    rccm: document.getElementById('shop-rccm').value.trim(),
    isf: document.getElementById('shop-isf').value.trim(),
    pdv: document.getElementById('shop-pdv').value.trim(),
    nid: document.getElementById('shop-nid').value.trim(),
    token: document.getElementById('shop-token').value.trim(),
    port: document.getElementById('shop-port').value.trim(),
    service_type_id: document.getElementById('shop-service-type').value || null,
    homologation: document.getElementById('shop-homologation').checked,
    actif: parseInt(document.getElementById('shop-actif').value)
  };

  const url = id ? `${SHOPS_API}/update/${id}` : SHOPS_API;
  const method = 'POST';

  try {
    const res = await fetch(url, {
      method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
      closeShopModal();
      window.location.reload();
    } else {
      errEl.textContent = result.error || 'Erreur lors de la sauvegarde';
      errEl.style.display = 'block';
    }
  } catch (err) {
    errEl.textContent = 'Erreur réseau';
    errEl.style.display = 'block';
  }
  btn.disabled = false;
}

loadServiceTypes();

async function deleteShop(id) {
  if (!confirm('Supprimer cette boutique ? Cette action est irréversible.')) return;
  try {
    const res = await fetch(`${SHOPS_API}/${id}`, { method: 'DELETE' });
    const result = await res.json();
    if (result.success) {
      window.location.reload();
    } else {
      alert(result.error || 'Erreur lors de la suppression');
    }
  } catch (err) {
    alert('Erreur réseau');
  }
}
</script>
