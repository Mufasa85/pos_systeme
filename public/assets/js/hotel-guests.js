/**
 * Page Guests hôtel — liste paginée + recherche + drawer create/edit/view.
 *
 * Endpoints :
 *   GET    /api/hotel/guests              (q, page, limit)
 *   GET    /api/hotel/guests/{id}
 *   POST   /api/hotel/guests
 *   PUT    /api/hotel/guests/{id}
 *
 * - API renvoie `read_only: true` si l'utilisateur courant n'a pas le droit d'écrire.
 *   On cache alors le bouton "Nouveau guest" et on désactive l'édition.
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;
  const tbody = document.getElementById('hotel-gn-tbody');
  const pageAlert = document.getElementById('hotel-alert-page');
  const readonlyBanner = document.getElementById('hotel-gn-readonly');
  const btnNew = document.getElementById('btn-new-guest');
  const btnNewFromClient = document.getElementById('btn-new-guest-from-client');

  const filterQ = document.getElementById('filter-q');
  const filterLimit = document.getElementById('filter-limit');
  const btnClear = document.getElementById('btn-clear');
  const pagerInfo = document.getElementById('pager-info');
  const pagerPrev = document.getElementById('pager-prev');
  const pagerNext = document.getElementById('pager-next');

  const drawer = document.getElementById('hotel-gn-drawer');
  const backdrop = document.getElementById('hotel-gn-backdrop');
  const closeBtn = document.getElementById('hotel-gn-drawer-close');
  const cancelBtn = document.getElementById('hotel-gn-drawer-cancel');
  const saveBtn = document.getElementById('hotel-gn-drawer-save');
  const drawerFoot = document.getElementById('hotel-gn-drawer-foot');
  const drawerTitle = document.getElementById('hotel-gn-drawer-title');
  const drawerContent = document.getElementById('hotel-gn-drawer-content');
  const drawerAlert = document.getElementById('hotel-gn-drawer-alert');

  if (!tbody) return;

  // Garde-fou : si une erreur runtime survient pendant l'init ou un handler,
  // on l'affiche en haut de la page plutôt que de mourir en silence (la
  // console navigateur n'est pas toujours ouverte).
  function showFatal(msg) {
    try {
      let box = document.getElementById('hotel-gn-fatal');
      if (!box) {
        box = document.createElement('div');
        box.id = 'hotel-gn-fatal';
        box.style.cssText = 'background:#fee2e2;color:#991b1b;padding:.65rem .85rem;border-radius:var(--radius);margin-bottom:1rem;font-size:.85rem;font-family:monospace;white-space:pre-wrap;';
        const card = document.querySelector('.hotel-gn-card');
        (card || document.body).insertBefore(box, card ? card.firstChild : null);
      }
      box.textContent = '⚠ Erreur JS : ' + (msg && msg.stack ? msg.stack : String(msg));
      console.error('[hotel-guests]', msg);
    } catch (_) { /* ignore */ }
  }
  window.addEventListener('error', e => showFatal(e.error || e.message));

  let readOnly = true;
  let currentPage = 1;
  let currentLimit = 25;
  let currentQ = '';
  let currentId = null;
  let drawerMode = 'view'; // 'view' | 'create' | 'edit'

  function showAlert(msg, type, where) {
    const el = (where === 'drawer') ? drawerAlert : pageAlert;
    if (!msg) { el.classList.add('hidden'); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
    el.classList.toggle('error',   type === 'error');
    el.classList.toggle('success', type === 'success');
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth()+1)).slice(-2) + '/' + d.getFullYear();
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

  function statutBadge(s) {
    const labels = {
      reserve:  ['Réservée',  'hotel-gn-statut-reserve'],
      en_cours: ['En cours',  'hotel-gn-statut-en_cours'],
      termine:  ['Terminée',  'hotel-gn-statut-termine'],
      annule:   ['Annulée',   'hotel-gn-statut-annule'],
      no_show:  ['No-show',   'hotel-gn-statut-no_show'],
    };
    const it = labels[s] || [s, 'hotel-gn-statut-termine'];
    return `<span class="hotel-gn-sejour-statut badge ${it[1]}">${it[0]}</span>`;
  }

  async function loadList() {
    showAlert(null);
    const params = new URLSearchParams();
    params.set('page',  currentPage);
    params.set('limit', currentLimit);
    if (currentQ) params.set('q', currentQ);

    const r = await jsonFetch('/api/hotel/guests?' + params.toString());
    if (!r.ok || !r.data || !r.data.success) {
      tbody.innerHTML = '<tr><td colspan="7" class="hotel-gn-empty">Erreur de chargement.</td></tr>';
      showAlert((r.data && r.data.message) || 'Erreur de chargement', 'error');
      return;
    }
    readOnly = !!r.data.read_only;
    btnNew.style.display = readOnly ? 'none' : '';
    btnNewFromClient.style.display = readOnly ? 'none' : '';
    readonlyBanner.classList.toggle('hidden', !readOnly);

    const rows = r.data.data || [];
    const meta = r.data.meta || { page: 1, limit: currentLimit, total: 0, pages: 1 };

    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="hotel-gn-empty">Aucun guest.</td></tr>';
    } else {
      tbody.innerHTML = rows.map(g => `
        <tr data-id="${g.id}">
          <td>
            <div style="font-weight:500;">${escapeHtml(g.prenom || '')} ${escapeHtml(g.nom || '')}</div>
            ${g.client_code ? `<div style="font-size:.7rem;color:var(--muted);margin-top:2px;">↳ Client <strong>${escapeHtml(g.client_code)}</strong></div>` : ''}
          </td>
          <td style="font-size:.85rem;">${escapeHtml(g.telephone || '')}</td>
          <td style="font-size:.85rem;">${escapeHtml(g.piece_identite || '')}</td>
          <td style="font-size:.85rem;">${escapeHtml(g.nationalite || '')}</td>
          <td style="text-align:right;"><span class="badge">${(g.nb_sejours || 0)}</span></td>
          <td style="font-size:.85rem;">${g.dernier_sejour ? fmtDate(g.dernier_sejour) : '—'}</td>
          <td style="text-align:right;">
            <button type="button" class="btn btn-ghost btn-small js-view" title="Voir le détail">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </td>
        </tr>
      `).join('');
    }

    // Pager
    pagerInfo.textContent = `${meta.total} guest(s) — page ${meta.page} / ${meta.pages}`;
    pagerPrev.disabled = meta.page <= 1;
    pagerNext.disabled = meta.page >= meta.pages;

    tbody.querySelectorAll('.js-view').forEach(b => {
      b.addEventListener('click', () => {
        const id = parseInt(b.closest('tr').dataset.id, 10);
        openViewDrawer(id);
      });
    });

    // Permet aussi de cliquer sur la ligne → ouvrir le détail
    tbody.querySelectorAll('tr[data-id]').forEach(tr => {
      tr.addEventListener('click', (e) => {
        if (e.target.closest('button')) return; // bouton a son propre handler
        const id = parseInt(tr.dataset.id, 10);
        openViewDrawer(id);
      });
    });
  }

  // Drawer helpers
  function openDrawer() {
    drawer.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeDrawer() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
    currentId = null;
    drawerMode = 'view';
    saveBtn.textContent = 'Enregistrer';
  }
  closeBtn.addEventListener('click', closeDrawer);
  cancelBtn.addEventListener('click', closeDrawer);
  backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  /**
   * Drawer : mode "view" = lecture seule avec liste séjours + bouton Éditer.
   */
  async function openViewDrawer(id) {
    drawerMode = 'view';
    showAlert(null, null, 'drawer');
    drawerFoot.style.display = 'none';
    drawerContent.innerHTML = 'Chargement…';
    openDrawer();
    const r = await jsonFetch('/api/hotel/guests/' + id);
    if (!r.ok || !r.data || !r.data.success) {
      drawerContent.innerHTML = '<div class="hotel-alert error">' + escapeHtml((r.data && r.data.message) || 'Erreur') + '</div>';
      return;
    }
    const g = r.data.data.guest;
    const sejours = r.data.data.sejours || [];
    readOnly = !!r.data.read_only;

    drawerTitle.textContent = `${g.prenom || ''} ${g.nom || ''}`.trim() || ('Guest #' + g.id);

    const statsHtml = `
      <div class="hotel-gn-stats">
        <div class="hotel-gn-stat">
          <strong>${sejours.length}</strong>
          <span>Séjour${sejours.length > 1 ? 's' : ''}</span>
        </div>
        <div class="hotel-gn-stat">
          <strong>${sejours.length > 0 ? fmtDate(sejours[0].date_arrivee) : '—'}</strong>
          <span>Dernier passage</span>
        </div>
      </div>`;

    const sejoursHtml = sejours.length === 0
      ? '<div style="font-size:.85rem;color:var(--muted);font-style:italic;">Aucun séjour enregistré.</div>'
      : sejours.map(s => `
          <div class="hotel-gn-sejour-row">
            <div>
              <div><strong>N° ${escapeHtml(String(s.chambre_numero))}</strong> · ${escapeHtml(s.chambre_type)}</div>
              <div style="font-size:.75rem;color:var(--muted);">${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
            </div>
            ${statutBadge(s.statut)}
          </div>`).join('');

    drawerContent.innerHTML = `
      ${statsHtml}
      ${g.client_id ? `
        <div class="hotel-gn-client-link" style="display:flex;align-items:center;gap:.5rem;padding:.55rem .75rem;background:#ecfdf5;color:#065f46;border-radius:var(--radius);font-size:.85rem;margin-bottom:.85rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
          </svg>
          <span>Lié au client <strong>${escapeHtml(g.client_code || '#'+g.client_id)}</strong> — ${escapeHtml(g.client_nom || '')}</span>
        </div>
      ` : ''}
      <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .85rem;font-size:.85rem;">
        ${g.telephone ? '<div style="color:var(--muted);">Téléphone</div><div>' + escapeHtml(g.telephone) + '</div>' : ''}
        ${g.piece_identite ? '<div style="color:var(--muted);">Pièce d\'identité</div><div>' + escapeHtml(g.piece_identite) + '</div>' : ''}
        ${g.nationalite ? '<div style="color:var(--muted);">Nationalité</div><div>' + escapeHtml(g.nationalite) + '</div>' : ''}
      </div>
      <div class="hotel-gn-sejours">
        <h4 style="margin:.85rem 0 .5rem;font-size:.85rem;color:var(--muted);text-transform:uppercase;">Séjours</h4>
        ${sejoursHtml}
      </div>
      ${readOnly ? '' : `
        <div style="margin-top:1rem;text-align:right;display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap;">
          ${g.client_id
            ? `<button type="button" class="btn btn-ghost js-unlink-client" data-id="${g.id}">Délier du client</button>`
            : `<button type="button" class="btn btn-ghost js-link-client" data-id="${g.id}">Lier à un client…</button>`}
          <button type="button" class="btn btn-primary js-edit-guest" data-id="${g.id}">Modifier</button>
        </div>
      `}
    `;

    const editBtn = drawerContent.querySelector('.js-edit-guest');
    if (editBtn) {
      editBtn.addEventListener('click', () => openEditDrawer(g, sejours));
    }
    const linkBtn = drawerContent.querySelector('.js-link-client');
    if (linkBtn) {
      linkBtn.addEventListener('click', () => openLinkClientDrawer(g));
    }
    const unlinkBtn = drawerContent.querySelector('.js-unlink-client');
    if (unlinkBtn) {
      unlinkBtn.addEventListener('click', async () => {
        if (!confirm('Délier ce guest du client ' + (g.client_code || '#'+g.client_id) + ' ?')) return;
        unlinkBtn.disabled = true;
        const r = await jsonFetch('/api/hotel/guests/' + g.id + '/link-client', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ client_id: null }),
        });
        if (r.ok && r.data && r.data.success) {
          showAlert('Guest délié du client', 'success', 'drawer');
          setTimeout(() => { closeDrawer(); loadList(); }, 400);
        } else {
          showAlert((r.data && r.data.message) || 'Erreur', 'error', 'drawer');
          unlinkBtn.disabled = false;
        }
      });
    }
  }

  /**
   * Drawer : mode "edit" = formulaire de modification.
   */
  function openEditDrawer(guest, sejours) {
    drawerMode = 'edit';
    currentId = guest.id;
    drawerFoot.style.display = 'flex';
    drawerTitle.textContent = 'Modifier — ' + ((guest.prenom || '') + ' ' + (guest.nom || '')).trim();

    drawerContent.innerHTML = `
      <form id="hotel-gn-form">
        <div class="form-group">
          <label for="gn-nom">Nom *</label>
          <input type="text" id="gn-nom" name="nom" class="form-input" required value="${escapeHtml(guest.nom || '')}">
        </div>
        <div class="form-group">
          <label for="gn-prenom">Prénom</label>
          <input type="text" id="gn-prenom" name="prenom" class="form-input" value="${escapeHtml(guest.prenom || '')}">
        </div>
        <div class="form-group">
          <label for="gn-tel">Téléphone</label>
          <input type="text" id="gn-tel" name="telephone" class="form-input" value="${escapeHtml(guest.telephone || '')}">
        </div>
        <div class="form-group">
          <label for="gn-piece">Pièce d'identité</label>
          <input type="text" id="gn-piece" name="piece_identite" class="form-input" value="${escapeHtml(guest.piece_identite || '')}">
        </div>
        <div class="form-group">
          <label for="gn-nat">Nationalité</label>
          <input type="text" id="gn-nat" name="nationalite" class="form-input" value="${escapeHtml(guest.nationalite || '')}">
        </div>
      </form>
    `;

    saveBtn.onclick = async () => {
      showAlert(null, null, 'drawer');
      saveBtn.disabled = true;
      const fd = new FormData(document.getElementById('hotel-gn-form'));
      const payload = {
        nom: fd.get('nom'),
        prenom: fd.get('prenom') || null,
        telephone: fd.get('telephone') || null,
        piece_identite: fd.get('piece_identite') || null,
        nationalite: fd.get('nationalite') || null,
      };
      const r = await jsonFetch('/api/hotel/guests/' + currentId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      saveBtn.disabled = false;
      if (r.ok && r.data && r.data.success) {
        showAlert('Mis à jour', 'success', 'drawer');
        setTimeout(() => { closeDrawer(); loadList(); }, 500);
      } else {
        showAlert((r.data && r.data.message) || 'Erreur', 'error', 'drawer');
      }
    };
  }

  /**
   * Drawer : mode "create" = formulaire de création.
   */
  function openCreateDrawer() {
    drawerMode = 'create';
    currentId = null;
    drawerFoot.style.display = 'flex';
    drawerTitle.textContent = 'Nouveau guest';
    openDrawer();

    drawerContent.innerHTML = `
      <div style="font-size:.8rem;color:var(--muted);margin-bottom:.85rem;">
        Si le guest existe déjà (même nom + téléphone, ou même pièce d'identité), on te le dira avant de créer un doublon.
      </div>
      <form id="hotel-gn-form">
        <div class="form-group">
          <label for="gn-nom">Nom *</label>
          <input type="text" id="gn-nom" name="nom" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="gn-prenom">Prénom</label>
          <input type="text" id="gn-prenom" name="prenom" class="form-input">
        </div>
        <div class="form-group">
          <label for="gn-tel">Téléphone</label>
          <input type="text" id="gn-tel" name="telephone" class="form-input">
        </div>
        <div class="form-group">
          <label for="gn-piece">Pièce d'identité</label>
          <input type="text" id="gn-piece" name="piece_identite" class="form-input">
        </div>
        <div class="form-group">
          <label for="gn-nat">Nationalité</label>
          <input type="text" id="gn-nat" name="nationalite" class="form-input">
        </div>
      </form>
    `;

    saveBtn.onclick = async () => {
      showAlert(null, null, 'drawer');
      saveBtn.disabled = true;
      const fd = new FormData(document.getElementById('hotel-gn-form'));
      const payload = {
        nom: fd.get('nom'),
        prenom: fd.get('prenom') || null,
        telephone: fd.get('telephone') || null,
        piece_identite: fd.get('piece_identite') || null,
        nationalite: fd.get('nationalite') || null,
      };
      const r = await jsonFetch('/api/hotel/guests', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      saveBtn.disabled = false;
      if (r.ok && r.data && r.data.success) {
        showAlert('Guest créé', 'success', 'drawer');
        setTimeout(() => { closeDrawer(); loadList(); }, 500);
      } else if (r.status === 409 && r.data && r.data.duplicate) {
        // Doublon détecté — on propose d'aller voir l'existant
        const d = r.data.duplicate;
        showAlert('Doublon probable : ' + (d.prenom || '') + ' ' + (d.nom || '') + ' (#' + d.id + '). Ouvre plutôt sa fiche pour le réutiliser.', 'error', 'drawer');
      } else {
        showAlert((r.data && r.data.message) || 'Erreur', 'error', 'drawer');
      }
    };
  }

  btnNew.addEventListener('click', () => {
    try {
      if (!readOnly) openCreateDrawer();
      else console.warn('[hotel-guests] click ignoré — readOnly=true');
    } catch (e) { showFatal(e); }
  });
  btnNewFromClient.addEventListener('click', () => {
    try {
      if (!readOnly) openAssignDrawer();
      else console.warn('[hotel-guests] click ignoré — readOnly=true');
    } catch (e) { showFatal(e); }
  });
  cancelBtn.addEventListener('click', closeDrawer);

  // ========================================================================
  // Modal "Depuis un client" — création d'un guest en le rattachant à un client POS
  // ========================================================================

  /**
   * Étape 1 : choisir le client POS. Étape 2 : pré-remplir le guest
   * et confirmer. À la validation : POST /api/hotel/guests avec client_id.
   */
  function openAssignDrawer() {
    drawerMode = 'create';
    currentId = null;
    drawerFoot.style.display = 'none';
    drawerTitle.textContent = 'Depuis un client POS';
    openDrawer();

    drawerContent.innerHTML = `
      <div style="font-size:.8rem;color:var(--muted);margin-bottom:.85rem;">
        Choisis le client POS existant qui séjourne. On créera le guest avec les infos du client pré-remplies — tu pourras corriger la pièce d'identité et la nationalité.
      </div>
      <div id="hotel-gn-assign-step1">
        <div class="form-group">
          <label for="gn-client-search">Rechercher un client</label>
          <input type="text" id="gn-client-search" class="form-input" placeholder="Nom, code client ou téléphone…" autocomplete="off">
        </div>
        <div id="gn-client-results" style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);"></div>
      </div>
      <div id="hotel-gn-assign-step2" style="display:none;">
        <div id="gn-client-selected" style="background:var(--background);padding:.65rem .85rem;border-radius:var(--radius);font-size:.85rem;margin-bottom:1rem;"></div>
        <div style="font-size:.8rem;color:var(--muted);margin-bottom:.5rem;">Les infos hôtel (pièce d'identité, nationalité) ne sont pas dans la fiche client — complète-les ici :</div>
        <form id="hotel-gn-form">
          <input type="hidden" id="gn-client-id" name="client_id">
          <div class="form-group">
            <label for="gn-nom">Nom *</label>
            <input type="text" id="gn-nom" name="nom" class="form-input" required>
          </div>
          <div class="form-group">
            <label for="gn-prenom">Prénom</label>
            <input type="text" id="gn-prenom" name="prenom" class="form-input">
          </div>
          <div class="form-group">
            <label for="gn-tel">Téléphone</label>
            <input type="text" id="gn-tel" name="telephone" class="form-input">
          </div>
          <div class="form-group">
            <label for="gn-piece">Pièce d'identité</label>
            <input type="text" id="gn-piece" name="piece_identite" class="form-input">
          </div>
          <div class="form-group">
            <label for="gn-nat">Nationalité</label>
            <input type="text" id="gn-nat" name="nationalite" class="form-input">
          </div>
        </form>
      </div>
    `;
    openDrawer();

    const search = document.getElementById('gn-client-search');
    const results = document.getElementById('gn-client-results');
    search.focus();

    let cTimer = null;
    search.addEventListener('input', () => {
      clearTimeout(cTimer);
      const q = search.value.trim();
      if (q.length < 1) { results.innerHTML = ''; return; }
      cTimer = setTimeout(() => doClientSearch(q), 220);
    });

    async function doClientSearch(q) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Recherche…</div>';
      const r = await jsonFetch('/api/hotel/clients/search?q=' + encodeURIComponent(q));
      if (!r.ok || !r.data || !r.data.success) {
        results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:#991b1b;">Erreur de recherche.</div>';
        return;
      }
      const list = r.data.data || [];
      if (list.length === 0) {
        results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Aucun client trouvé.</div>';
        return;
      }
      results.innerHTML = list.map(c => `
        <div class="gn-client-row" data-id="${c.id}" style="padding:.65rem .85rem;border-bottom:1px solid var(--border);cursor:pointer;${c.linked_guest_id ? 'background:#fef3c7;' : ''}">
          <div style="font-weight:500;">${escapeHtml(c.nom_client || '')}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">
            ${escapeHtml(c.code_client || '')} · ${escapeHtml(c.numero || '')}
            ${c.linked_guest_id ? ' · <span style="color:#92400e;">⚠ déjà guest #'+c.linked_guest_id+'</span>' : ''}
          </div>
        </div>
      `).join('');
      results.querySelectorAll('.gn-client-row').forEach(row => {
        row.addEventListener('click', () => {
          const id = parseInt(row.dataset.id, 10);
          const c = list.find(x => x.id === id);
          selectClient(c);
        });
      });
    }

    function selectClient(c) {
      if (c.linked_guest_id) {
        if (!confirm('Ce client a déjà un guest lié (#'+c.linked_guest_id+'). Veux-tu quand même créer un nouveau guest ? (En général non — préfère ouvrir le guest existant)')) {
          return;
        }
      }
      document.getElementById('hotel-gn-assign-step1').style.display = 'none';
      const step2 = document.getElementById('hotel-gn-assign-step2');
      step2.style.display = '';
      document.getElementById('gn-client-selected').innerHTML =
        `<strong>Client sélectionné :</strong> ${escapeHtml(c.nom_client)} (${escapeHtml(c.code_client || '')} — ${escapeHtml(c.numero || '')})`;
      document.getElementById('gn-client-id').value = c.id;
      // Pré-remplissage depuis le client (modifiables)
      const form = document.getElementById('hotel-gn-form');
      form.elements['nom'].value     = c.nom_client || '';
      form.elements['prenom'].value  = '';
      form.elements['telephone'].value = c.numero || '';
      drawerFoot.style.display = 'flex';
      drawerTitle.textContent = 'Nouveau guest — ' + (c.code_client || 'client #' + c.id);
      saveBtn.textContent = 'Créer le guest';

      saveBtn.onclick = async () => {
        showAlert(null, null, 'drawer');
        saveBtn.disabled = true;
        const fd = new FormData(form);
        const payload = {
          client_id:    parseInt(fd.get('client_id'), 10) || null,
          nom:          fd.get('nom'),
          prenom:       fd.get('prenom') || null,
          telephone:    fd.get('telephone') || null,
          piece_identite: fd.get('piece_identite') || null,
          nationalite:  fd.get('nationalite') || null,
        };
        const r = await jsonFetch('/api/hotel/guests', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        saveBtn.disabled = false;
        if (r.ok && r.data && r.data.success) {
          showAlert('Guest créé et lié au client', 'success', 'drawer');
          setTimeout(() => { closeDrawer(); loadList(); }, 500);
        } else if (r.status === 409 && r.data && r.data.duplicate) {
          const d = r.data.duplicate;
          showAlert('Doublon probable : ' + (d.prenom || '') + ' ' + (d.nom || '') + ' (#' + d.id + ').', 'error', 'drawer');
        } else {
          showAlert((r.data && r.data.message) || 'Erreur', 'error', 'drawer');
        }
      };
    }
  }

  // ========================================================================
  // Modal "Lier à un client" — depuis la fiche d'un guest déjà créé
  // ========================================================================

  function openLinkClientDrawer(guest) {
    drawerMode = 'link';
    drawerFoot.style.display = 'none';
    drawerTitle.textContent = 'Lier à un client POS';
    showAlert(null, null, 'drawer');

    drawerContent.innerHTML = `
      <div style="font-size:.8rem;color:var(--muted);margin-bottom:.85rem;">
        Recherche un client POS et lie-le au guest <strong>${escapeHtml(guest.prenom || '')} ${escapeHtml(guest.nom || '')}</strong> (#${guest.id}). Si tu en trouves un déjà rattaché, on te le dira.
      </div>
      <div class="form-group">
        <label for="gn-link-search">Rechercher un client</label>
        <input type="text" id="gn-link-search" class="form-input" placeholder="Nom, code client ou téléphone…" autocomplete="off">
      </div>
      <div id="gn-link-results" style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);"></div>
    `;
    openDrawer();

    const search = document.getElementById('gn-link-search');
    const results = document.getElementById('gn-link-results');
    search.focus();

    let lTimer = null;
    search.addEventListener('input', () => {
      clearTimeout(lTimer);
      const q = search.value.trim();
      if (q.length < 1) { results.innerHTML = ''; return; }
      lTimer = setTimeout(async () => {
        results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Recherche…</div>';
        const r = await jsonFetch('/api/hotel/clients/search?q=' + encodeURIComponent(q));
        if (!r.ok || !r.data || !r.data.success) {
          results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:#991b1b;">Erreur de recherche.</div>';
          return;
        }
        const list = r.data.data || [];
        if (list.length === 0) {
          results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Aucun client trouvé.</div>';
          return;
        }
        results.innerHTML = list.map(c => `
          <div class="gn-link-row" data-id="${c.id}" style="padding:.65rem .85rem;border-bottom:1px solid var(--border);cursor:pointer;${c.linked_guest_id && c.linked_guest_id !== guest.id ? 'background:#fef3c7;cursor:not-allowed;' : ''}">
            <div style="font-weight:500;">${escapeHtml(c.nom_client || '')}</div>
            <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">
              ${escapeHtml(c.code_client || '')} · ${escapeHtml(c.numero || '')}
              ${c.linked_guest_id && c.linked_guest_id !== guest.id ? ' · <span style="color:#92400e;">⚠ déjà guest #'+c.linked_guest_id+'</span>' : ''}
            </div>
          </div>
        `).join('');
        results.querySelectorAll('.gn-link-row').forEach(row => {
          const otherGuest = parseInt(row.dataset.id, 10);
          row.addEventListener('click', async () => {
            if (row.style.cursor === 'not-allowed') {
              showAlert('Ce client a déjà un guest lié — annule et choisis un autre client.', 'error', 'drawer');
              return;
            }
            row.style.opacity = '0.5';
            const rr = await jsonFetch('/api/hotel/guests/' + guest.id + '/link-client', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ client_id: otherGuest }),
            });
            if (rr.ok && rr.data && rr.data.success) {
              showAlert('Guest lié au client', 'success', 'drawer');
              setTimeout(() => { closeDrawer(); loadList(); }, 400);
            } else if (rr.status === 409 && rr.data && rr.data.duplicate) {
              showAlert('Ce client est déjà lié au guest #' + rr.data.duplicate.id, 'error', 'drawer');
              row.style.opacity = '';
            } else {
              showAlert((rr.data && rr.data.message) || 'Erreur', 'error', 'drawer');
              row.style.opacity = '';
            }
          });
        });
      }, 220);
    });
  }

  // Filtres
  let fTimer = null;
  filterQ.addEventListener('input', () => {
    clearTimeout(fTimer);
    fTimer = setTimeout(() => { currentQ = filterQ.value.trim(); currentPage = 1; loadList(); }, 220);
  });
  filterLimit.addEventListener('change', () => {
    currentLimit = parseInt(filterLimit.value, 10) || 25;
    currentPage = 1;
    loadList();
  });
  btnClear.addEventListener('click', () => {
    filterQ.value = '';
    currentQ = '';
    currentPage = 1;
    loadList();
  });

  pagerPrev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadList(); } });
  pagerNext.addEventListener('click', () => { currentPage++; loadList(); });

  loadList();
})();
