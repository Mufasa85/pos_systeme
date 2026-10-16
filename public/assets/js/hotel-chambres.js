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
    const res = await fetch(APP_URL + url, Object.assign(
      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
      opts || {}
    ));
    let data = null;
    try { data = await res.json(); } catch(e) { data = null; }
    return { ok: res.ok, status: res.status, data };
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
      tbody.innerHTML = '<tr><td colspan="6" class="hotel-rooms-empty">Erreur de chargement.</td></tr>';
      showAlert((r.data && r.data.message) || 'Erreur de chargement', 'error');
      return;
    }
    readOnly = !!r.data.read_only;
    btnNew.style.display = readOnly ? 'none' : '';
    readonlyBanner.classList.toggle('hidden', !readOnly);

    const rows = r.data.data || [];
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="hotel-rooms-empty">Aucune chambre.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(row => `
      <tr data-id="${row.id}">
        <td style="font-weight:600;">N° ${escapeHtml(String(row.numero))}</td>
        <td>${badge(row.type)}</td>
        <td style="text-align:right;">${fmtMoney(row.prix_base)}</td>
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
  function resetForm() {
    form.reset();
    form.querySelector('input[name="id"]').value = '';
    currentId = null;
    drawerMsg(null);
    drawerTitle.textContent = 'Nouvelle chambre';
  }

  async function openDrawerForCreate() {
    resetForm();
    open();
  }
  async function openDrawerForEdit(id) {
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

    const payload = {
      numero: document.getElementById('room-numero').value.trim(),
      type: document.getElementById('room-type').value,
      prix_base: parseFloat(document.getElementById('room-prix').value || 0),
      etat: document.getElementById('room-etat').value,
      notes: document.getElementById('room-notes').value || null,
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
