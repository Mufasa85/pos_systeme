/**
 * Page Chambres hôtel — liste + filtre + drawer + changement d'état rapide.
 *
 * Endpoints :
 *   GET    /api/hotel/chambres
 *   GET    /api/hotel/chambres/{id}
 *   POST   /api/hotel/chambres
 *   PUT    /api/hotel/chambres/{id}
 *   POST   /api/hotel/chambres/{id}/etat   (body: {etat: "..."})
 *
 * - L'API renvoie `read_only: true` si l'utilisateur courant n'a pas le droit d'écrire.
 *   On cache alors le bouton "Nouvelle chambre" et on désactive les contrôles d'édition.
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;
  const tbody   = document.getElementById('hotel-rooms-tbody');
  const pageAlert = document.getElementById('hotel-alert-page');
  const readonlyBanner = document.getElementById('hotel-readonly-banner');
  const btnNew  = document.getElementById('btn-new-room');

  const drawer    = document.getElementById('hotel-room-drawer');
  const backdrop  = document.getElementById('hotel-room-drawer-backdrop');
  const closeBtn  = document.getElementById('hotel-room-drawer-close');
  const cancelBtn = document.getElementById('hotel-room-drawer-cancel');
  const saveBtn   = document.getElementById('hotel-room-drawer-save');
  const form      = document.getElementById('hotel-room-form');
  const drawerTitle = document.getElementById('hotel-room-drawer-title');
  const drawerAlert  = document.getElementById('hotel-room-drawer-alert');

  if (!tbody) return; // on n'est pas sur la bonne page

  let readOnly = true;
  let currentId = null;

  function showAlert(msg, type) {
    if (!msg) { pageAlert.classList.add('hidden'); return; }
    pageAlert.textContent = msg;
    pageAlert.classList.remove('hidden');
    pageAlert.classList.toggle('error', type === 'error');
    pageAlert.classList.toggle('success', type === 'success');
  }
  function drawerMsg(msg, type) {
    if (!msg) { drawerAlert.classList.add('hidden'); return; }
    drawerAlert.textContent = msg;
    drawerAlert.classList.remove('hidden');
    drawerAlert.classList.toggle('error', type === 'error');
    drawerAlert.classList.toggle('success', type === 'success');
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtMoney(v) {
    const n = Number(v || 0);
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }
  async function jsonFetch(url, opts) {
    let res;
    try {
      res = await fetch(APP_URL + url, Object.assign(
        { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
        opts || {}
      ));
    } catch (networkErr) {
      // Crash réseau (serveur injoignable, CORS, etc.)
      console.error('[hotel-chambres] Network error fetching', url, networkErr);
      return { ok: false, status: 0, data: null, error: 'network', message: 'Impossible de joindre le serveur.' };
    }
    let data = null;
    let rawText = '';
    try {
      rawText = await res.text();
      data = rawText ? JSON.parse(rawText) : null;
    } catch(parseErr) {
      console.error('[hotel-chambres] JSON parse error', parseErr, 'raw:', rawText);
      return { ok: res.ok, status: res.status, data: null, error: 'parse', message: 'Réponse serveur illisible.' };
    }
    if (!res.ok) {
      console.error('[hotel-chambres] HTTP', res.status, url, data);
    }
    return {
      ok: res.ok,
      status: res.status,
      data: data,
      error: data && data.success === false ? 'api' : null,
      message: (data && (data.message || data.error)) || null,
    };
  }

  const ETATS = [
    { v: 'disponible',   label: 'Disponible' },
    { v: 'a_nettoyer',   label: 'À nettoyer' },
    { v: 'hors_service', label: 'Hors service' },
  ];

  function badge(c) {
    if (c === 'simple') return `<span class="badge hotel-rooms-type-simple">Simple</span>`;
    if (c === 'double') return `<span class="badge hotel-rooms-type-double">Double</span>`;
    return `<span class="badge">${escapeHtml(c)}</span>`;
  }
  function etatBadge(label) {
    return `<span class="badge hotel-etat-${label.v}">${label.label}</span>`;
  }

  // Libellé TS calqué sur getSpecificTaxLabel() dans app.js (ligne ~1029).
  // Retourne "" quand la TS n'est pas définie (value <= 0) : pas de bruit visuel.
  function tsLabel(row) {
    const t = row.taxe_specifique_type || '%';
    const v = parseFloat(row.taxe_specifique_value);
    if (!Number.isFinite(v) || v <= 0) return '';
    if (t === 'CDF') return `<span style="color:#b45309;font-weight:700;">[TS]</span> ${fmtMoney(v)} Fc`;
    return `<span style="color:#b45309;font-weight:700;">[TS]</span> ${v}%`;
  }

  function etatOptions(row) {
    const disabled = readOnly ? 'disabled' : '';
    return ETATS.map(e =>
      `<option value="${e.v}" ${e.v === row.etat ? 'selected' : ''} ${disabled}>${e.label}</option>`
    ).join('');
  }

  async function loadList() {
    showAlert(null);
    const r = await jsonFetch('/api/hotel/chambres');
    if (!r.ok || !r.data || !r.data.success) {
      // Message d'erreur détaillé pour aider au diagnostic.
      let msg = 'Erreur de chargement';
      let hint = '';
      if (r.status === 401 || r.status === 403) {
        msg = (r.data && r.data.message) || 'Accès refusé (403). Vérifiez votre session/rôle.';
        hint = 'Essayez de vous reconnecter, ou demandez à un manager de vous attribuer un rôle hôtel.';
      } else if (r.status === 404) {
        msg = 'Endpoint introuvable (404).';
      } else if (r.status === 500) {
        msg = 'Erreur serveur (500).';
        hint = 'Vérifiez que les migrations SQL hôtel ont été exécutées (colonnes taxe_specifique_*, taxe_id). Réessayez dans un instant.';
      } else if (r.error === 'network') {
        msg = r.message || 'Réseau indisponible.';
        hint = 'Vérifiez votre connexion internet ou que le serveur est démarré.';
      } else if (r.error === 'parse') {
        msg = r.message || 'Réponse serveur illisible.';
      } else if (r.message) {
        msg = r.message;
      }
      console.error('[hotel-chambres] loadList failed', r);
      tbody.innerHTML = `<tr><td colspan="7" class="hotel-rooms-empty">${escapeHtml(msg)}<br>${hint ? `<small style="color:var(--muted);">${escapeHtml(hint)}</small><br>` : ''}<small style="color:var(--muted);">HTTP ${r.status || '—'} — détails dans la console (F12).</small><br><button type="button" class="btn btn-ghost btn-small" onclick="location.reload()" style="margin-top:.5rem;">↻ Réessayer</button></td></tr>`;
      showAlert(msg, 'error');
      return;
    }
    readOnly = !!r.data.read_only;
    btnNew.style.display = readOnly ? 'none' : '';
    readonlyBanner.classList.toggle('hidden', !readOnly);

    const rows = (r.data && r.data.data) || [];
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="hotel-rooms-empty">Aucune chambre.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(row => `
      <tr data-id="${row.id}">
        <td style="font-weight:600;">N° ${escapeHtml(String(row.numero))}</td>
        <td>${badge(row.type)}</td>
        <td style="text-align:right;">${fmtMoney(row.prix_base)} Fc</td>
        <td style="text-align:right;font-size:.85rem;white-space:nowrap;">${tsLabel(row) || '<span style="color:var(--muted);">—</span>'}</td>
        <td>
          <span class="hotel-etat-current">
            ${etatBadge(ETATS.find(e => e.v === row.etat) || { v: row.etat, label: row.etat })}
          </span>
          ${!readOnly ? `
            <select class="hotel-etat-select js-etat" data-id="${row.id}" style="margin-left:.5rem;">
              ${etatOptions(row)}
            </select>
          ` : ''}
        </td>
        <td style="font-size:.8rem;color:var(--muted);max-width:240px;">${escapeHtml(row.notes || '')}</td>
        <td style="text-align:right;">
          ${!readOnly ? `
            <button type="button" class="btn btn-ghost btn-small js-edit" title="Modifier">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
              </svg>
            </button>
          ` : '<span style="font-size:.75rem;color:var(--muted);">—</span>'}
        </td>
      </tr>
    `).join('');

    // Wire change-etat rapide
    tbody.querySelectorAll('.js-etat').forEach(sel => {
      sel.addEventListener('change', async () => {
        const id = sel.dataset.id;
        const etat = sel.value;
        sel.disabled = true;
        const r = await jsonFetch('/api/hotel/chambres/' + encodeURIComponent(id) + '/etat', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ etat: etat }),
        });
        sel.disabled = false;
        if (r.ok && r.data && r.data.success) {
          showAlert('État mis à jour', 'success');
          // Mise à jour locale du badge
          const tr = sel.closest('tr');
          const badgeCell = tr.querySelector('.hotel-etat-current');
          const e = ETATS.find(e => e.v === etat) || { v: etat, label: etat };
          badgeCell.innerHTML = etatBadge(e);
        } else {
          showAlert((r.data && r.data.message) || 'Erreur', 'error');
          loadList();
        }
      });
    });

    tbody.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', () => openDrawerForEdit(parseInt(btn.closest('tr').dataset.id, 10)));
    });
  }

  // Drawer
  function open() { drawer.classList.add('open'); backdrop.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function close() { drawer.classList.remove('open'); backdrop.classList.remove('open'); document.body.style.overflow = ''; }
  // ── Cache des taxes pour le select #room-taxe-id ─────────────────────
  // On charge /api/taxes au démarrage de la page et on garde la liste en
  // mémoire. Cette liste est petite (~18 lignes dans la base seed) donc
  // c'est acceptable de la mettre en cache.
  let taxesCache = [];

  async function loadTaxesList() {
    if (taxesCache.length > 0) return taxesCache;
    const r = await jsonFetch('/api/taxes');
    if (r.ok && r.data && Array.isArray(r.data)) {
      taxesCache = r.data;
    } else if (r.ok && r.data && r.data.data && Array.isArray(r.data.data)) {
      // tolérance : si l'API renvoie un wrapper { data: [...] }
      taxesCache = r.data.data;
    } else {
      taxesCache = [];
    }
    return taxesCache;
  }

  function populateTaxeSelect(selectedId) {
    const sel = document.getElementById('room-taxe-id');
    if (!sel) return;
    // On garde la 1re option "— Aucune —"
    sel.innerHTML = '<option value="">— Aucune (par défaut) —</option>';
    taxesCache.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      const etq = (t.etiquette || '').trim();
      const grp = (t.groupe_taxe || '').trim();
      const taux = parseFloat(t.taux) || 0;
      // Affichage : "B (Groupe B — Taxable) — 16%" pour faciliter la
      // lecture. Le manager voit clairement le groupe et le taux.
      opt.textContent = grp
        ? `${etq} (${grp} — ${taux}%)`
        : `${etq} — ${taux}%`;
      if (selectedId && parseInt(selectedId, 10) === parseInt(t.id, 10)) {
        opt.selected = true;
      }
      sel.appendChild(opt);
    });
  }

  function resetForm() {
    form.reset();
    form.querySelector('input[name="id"]').value = '';
    currentId = null;
    drawerMsg(null);
    drawerTitle.textContent = 'Nouvelle chambre';
    // Réinit des champs taxe spécifique aux défauts produit ('%' et 0),
    // calqué sur posCart.saveProduct() (cf. gestion produit).
    const tsType = document.getElementById('room-taxe-specifique-type');
    const tsValue = document.getElementById('room-taxe-specifique-value');
    if (tsType)  tsType.value = '%';
    if (tsValue) tsValue.value = '0';
    // taxe_id : par défaut, on remet à "" (= aucune). On sélectionne
    // automatiquement la taxe B (16% hôtellerie) si elle existe, pour
    // rester cohérent avec le backfill de la migration. Si l'utilisateur
    // préfère une autre taxe, il la change manuellement.
    const taxeSel = document.getElementById('room-taxe-id');
    if (taxeSel) {
      const taxB = taxesCache.find(t => String(t.etiquette || '').trim().toUpperCase() === 'B');
      taxeSel.value = taxB ? String(taxB.id) : '';
    }
  }

  async function openDrawerForCreate() {
    // S'assurer que la liste des taxes est chargée pour le select
    await loadTaxesList();
    resetForm();
    open();
  }
  async function openDrawerForEdit(id) {
    await loadTaxesList();
    resetForm();
    const r = await jsonFetch('/api/hotel/chambres/' + encodeURIComponent(id));
    if (!r.ok || !r.data || !r.data.success) {
      showAlert((r.data && r.data.message) || 'Erreur', 'error');
      return;
    }
    const row = r.data.data;
    currentId = id;
    form.querySelector('input[name="id"]').value = id;
    document.getElementById('room-numero').value = row.numero;
    document.getElementById('room-type').value = row.type;
    document.getElementById('room-prix').value = row.prix_base;
    document.getElementById('room-etat').value = row.etat;
    document.getElementById('room-notes').value = row.notes || '';
    // Pré-remplir la TS depuis l'API (colonnes ajoutées par
    // migrations/2026_10_18_hotel_room_specific_tax.sql).
    const tsType = document.getElementById('room-taxe-specifique-type');
    const tsValue = document.getElementById('room-taxe-specifique-value');
    if (tsType)  tsType.value  = row.taxe_specifique_type || '%';
    if (tsValue) tsValue.value = (row.taxe_specifique_value !== null && row.taxe_specifique_value !== undefined)
      ? row.taxe_specifique_value : 0;
    // Pré-remplir la taxe de la chambre (Sprint 7) — null/undefined = aucune.
    // On peuple le select à partir du cache puis on sélectionne l'option
    // correspondant à row.taxe_id (ou '' si pas de taxe).
    populateTaxeSelect(row.taxe_id);
    drawerTitle.textContent = 'Modifier la chambre N° ' + row.numero;
    open();
  }

  btnNew.addEventListener('click', openDrawerForCreate);
  closeBtn.addEventListener('click', close);
  cancelBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    drawerMsg(null);
    saveBtn.disabled = true;

    const priceValue = parseFloat(document.getElementById('room-prix').value || 0);
    if (!Number.isFinite(priceValue) || priceValue <= 0) {
      drawerMsg('Le prix de la chambre doit être supérieur à 0 pour enregistrer.', 'error');
      saveBtn.disabled = false;
      return;
    }

    // ── Validation de la taxe spécifique (calquée sur posCart.saveProduct) ──
    // Bornes appliquées côté front, identique au pattern produit :
    //  - %      : ∈ [0, 100]
    //  - CDF    : ∈ [0, prix_base]   (une TS fixe > prix n'a pas de sens)
    const taxeSpecifiqueType  = document.getElementById('room-taxe-specifique-type').value || '%';
    const taxeSpecifiqueValue = parseFloat(document.getElementById('room-taxe-specifique-value').value) || 0;
    if (taxeSpecifiqueType === '%' && (taxeSpecifiqueValue < 0 || taxeSpecifiqueValue > 100)) {
      drawerMsg('La taxe spécifique en pourcentage doit être comprise entre 0 et 100.', 'error');
      saveBtn.disabled = false;
      return;
    }
    if (taxeSpecifiqueType === 'CDF' && (taxeSpecifiqueValue < 0 || taxeSpecifiqueValue > priceValue)) {
      drawerMsg('La taxe spécifique en montant fixe doit être comprise entre 0 et le prix de la chambre.', 'error');
      saveBtn.disabled = false;
      return;
    }

    const payload = {
      numero: document.getElementById('room-numero').value.trim(),
      type: document.getElementById('room-type').value,
      prix_base: priceValue,
      etat: document.getElementById('room-etat').value,
      notes: document.getElementById('room-notes').value || null,
      taxe_specifique_type:  taxeSpecifiqueType,
      taxe_specifique_value: taxeSpecifiqueValue,
      // taxe_id (FK `taxes`) — Sprint 7 — 2026-10-19. Si le select est
      // vide, on envoie null explicitement pour défaire la liaison.
      taxe_id: (() => {
        const v = document.getElementById('room-taxe-id').value;
        return (v === '' || v === null) ? null : parseInt(v, 10);
      })(),
    };

    let url = '/api/hotel/chambres';
    let method = 'POST';
    if (currentId) { url += '/' + encodeURIComponent(currentId); method = 'PUT'; }

    const r = await jsonFetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    saveBtn.disabled = false;

    if (r.ok && r.data && r.data.success) {
      drawerMsg(currentId ? 'Chambre mise à jour' : 'Chambre créée', 'success');
      setTimeout(() => { close(); loadList(); }, 500);
    } else {
      drawerMsg((r.data && r.data.message) || 'Erreur', 'error');
    }
  });

  loadList();
})();
