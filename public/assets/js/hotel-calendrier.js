/**
 * /hotel/calendrier — vue planning manager (lecture seule).
 *
 * Responsabilités :
 *   - Rendre les bandeaux de séjours par-dessus la grille (chambre × jour).
 *     Le rendu serveur hydrate la grille de cellules ; ici on positionne
 *     chaque séjour en absolute sur la cellule du jour d'arrivée, avec
 *     une largeur couvrant N colonnes (1 col = 1 nuit).
 *   - Navigation Précédent / Aujourd'hui / Suivant : recharge la page avec
 *     ?from=YYYY-MM-DD ± 7 jours.
 *   - Auto-refresh toutes les 60 s via /api/hotel/calendrier?from=...
 *   - Clic sur bandeau (desktop) ou carte (mobile) → drawer de détail.
 *   - Vue mobile : la liste verticale est déjà rendue côté serveur.
 */
(function () {
  'use strict';

  const APP_URL = window.location.origin;
  const STATE = window.HOTEL_CAL;
  if (!STATE) return;

  const STATUS_COLORS = STATE.statusColors || {};
  const REFRESH_MS = 60 * 1000;

  // Helpers --------------------------------------------------------------

  function fmtDate(s) {
    if (!s) return '—';
    // date_depart_prevue = 'YYYY-MM-DD' ; date_arrivee = 'YYYY-MM-DD HH:MM:SS'
    if (s.length === 10) return s.split('-').reverse().join('/');
    const [d, t] = s.split(' ');
    return d.split('-').reverse().join('/') + ' ' + (t ? t.substring(0, 5) : '');
  }

  function guestLabel(s) {
    const n = ((s.guest_prenom || '') + ' ' + (s.guest_nom || '')).trim();
    return n || ('Guest #' + s.guest_id);
  }

  function nightsOf(s) {
    const arr = new Date(s.date_arrivee.substring(0, 10));
    const dep = new Date(s.date_depart_prevue);
    const ms = dep - arr;
    const n = Math.max(1, Math.round(ms / 86400000));
    return n;
  }

  function moneyFmt(v) {
    return Number(v || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function statusLabel(st) {
    return (STATUS_COLORS[st] && STATUS_COLORS[st].label) || st;
  }

  function statusColors(st) {
    return STATUS_COLORS[st] || { bg: '#f3f4f6', border: '#9ca3af', text: '#1f2937', label: st };
  }

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = (s == null ? '' : String(s));
    return d.innerHTML;
  }

  // Horloge affichée en haut
  function startClock() {
    const el = document.getElementById('hotel-cal-clock');
    if (!el) return;
    function tick() {
      const d = new Date();
      const pad = (n) => (n < 10 ? '0' + n : '' + n);
      el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
  }

  // Rendu des bandeaux dans la grille desktop ------------------------------

  function renderBands() {
    const grid = document.getElementById('hotel-cal-grid');
    if (!grid) return;

    // Nettoyer d'éventuels bandeaux précédents
    grid.querySelectorAll('.hotel-cal-band').forEach((el) => el.remove());

    // Pour chaque séjour : positionner sur la ligne de sa chambre, à cheval
    // sur les colonnes correspondant à [arrivee, depart_prevue).
    // La 1re colonne (chambre) = colonne 1 du grid. Les jours = colonnes 2..8.
    STATE.sejours.forEach((s) => {
      const col = statusColors(s.statut);

      // Date d'arrivée (normalisée à minuit) et date de départ
      const arrYmd = s.date_arrivee.substring(0, 10);
      const depYmd = s.date_depart_prevue; // déjà YYYY-MM-DD

      // Indice du jour d'arrivée dans la liste STATE.joursLabels
      const startIdx = STATE.joursLabels.indexOf(arrYmd);
      // Indice du jour de départ (= exclusif : on colorie jusqu'à depYmd - 1 jour)
      let endExclusiveIdx = STATE.joursLabels.indexOf(depYmd);
      if (endExclusiveIdx < 0) {
        // séjour dépasse la fin de semaine → on colorie jusqu'au dernier jour + 1
        endExclusiveIdx = STATE.joursLabels.length;
      }
      if (startIdx < 0) {
        // séjour a commencé avant la semaine → démarre à la colonne 0
        // (= premier jour visible)
      }

      const fromIdx = Math.max(0, startIdx);
      const toIdx = Math.min(STATE.joursLabels.length, endExclusiveIdx);
      if (toIdx <= fromIdx) return; // rien à colorier sur cette semaine

      const cellStart = grid.querySelector(
        '.hotel-cal-cell[data-room-id="' + s.chambre_id + '"][data-day="' + STATE.joursLabels[fromIdx] + '"]'
      );
      const cellEnd = grid.querySelector(
        '.hotel-cal-cell[data-room-id="' + s.chambre_id + '"][data-day="' + STATE.joursLabels[toIdx - 1] + '"]'
      );
      if (!cellStart || !cellEnd) return;

      const gridRect = grid.getBoundingClientRect();
      const a = cellStart.getBoundingClientRect();
      const b = cellEnd.getBoundingClientRect();

      const band = document.createElement('div');
      band.className = 'hotel-cal-band';
      band.setAttribute('data-sejour-id', String(s.id));
      band.style.position = 'absolute';
      band.style.background = col.bg;
      band.style.borderColor = col.border;
      band.style.color = col.text;
      band.style.left = (a.left - gridRect.left + 2) + 'px';
      band.style.top  = (a.top  - gridRect.top  + 4) + 'px';
      band.style.width = Math.max(40, (b.right - a.left - 4)) + 'px';
      band.style.height = (a.height - 8) + 'px';
      band.style.zIndex = '5';
      band.innerHTML =
        '<span class="name">' + escHtml(guestLabel(s)) + '</span>' +
        '<span class="nights">' + nightsOf(s) + ' nuit' + (nightsOf(s) > 1 ? 's' : '') + '</span>';

      band.addEventListener('click', function (e) {
        e.stopPropagation();
        openSejour(s.id);
      });

      grid.appendChild(band);
    });
  }

  // Drawer détail ---------------------------------------------------------

  function openSejour(id) {
    const drawer = document.getElementById('hotel-cal-drawer');
    const backdrop = document.getElementById('hotel-cal-backdrop');
    const body = document.getElementById('hotel-cal-drawer-content');
    const title = document.getElementById('hotel-cal-drawer-title');

    body.innerHTML = 'Chargement…';
    drawer.classList.add('open');
    backdrop.classList.add('open');

    // 1) affichage immédiat depuis STATE.sejours si dispo (pas de fetch inutile)
    const local = STATE.sejours.find((x) => Number(x.id) === Number(id));
    if (local) {
      title.textContent = 'Séjour #' + local.id + ' — Chambre ' + local.chambre_numero;
      renderDetail(local);
    }

    // 2) puis on tente un fetch frais pour avoir les prix/départ réel à jour
    fetch(APP_URL + '/api/hotel/reservations/' + encodeURIComponent(id), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (json) {
        if (json && json.success && json.data) {
          title.textContent = 'Séjour #' + json.data.id + ' — Chambre ' + json.data.chambre_numero;
          renderDetail({
            id: json.data.id,
            statut: json.data.statut,
            guest_nom: json.data.guest_nom,
            guest_prenom: json.data.guest_prenom,
            guest_tel: json.data.guest_tel,
            chambre_numero: json.data.chambre_numero,
            chambre_type: json.data.chambre_type,
            date_arrivee: json.data.date_arrivee,
            date_depart_prevue: json.data.date_depart_prevue,
            date_depart_reelle: json.data.date_depart_reelle,
            prix_par_nuit: Number(json.data.prix_par_nuit || 0),
            remise_pct: Number(json.data.remise_pct || 0),
            notes: json.data.notes || '',
          });
        }
      })
      .catch(function () {
        if (!local) {
          body.innerHTML = '<div class="hotel-cal-error">Détail indisponible. Réessayez plus tard.</div>';
        }
      });
  }

  function renderDetail(s) {
    const body = document.getElementById('hotel-cal-drawer-content');
    const col = statusColors(s.statut);
    const nights = nightsOf(s);
    const total = nights * (Number(s.prix_par_nuit) || 0) * (1 - (Number(s.remise_pct) || 0) / 100);

    body.innerHTML =
      '<dl class="hotel-cal-dl">' +
        '<dt>Statut</dt>' +
        '<dd><span class="hotel-cal-status" style="background:' + col.bg + ';border-color:' + col.border + ';color:' + col.text + ';">' + escHtml(statusLabel(s.statut)) + '</span></dd>' +
        '<dt>Chambre</dt>' +
        '<dd>N° ' + escHtml(s.chambre_numero) + ' (' + escHtml(s.chambre_type) + ')</dd>' +
        '<dt>Guest</dt>' +
        '<dd>' + escHtml(((s.guest_prenom || '') + ' ' + (s.guest_nom || '')).trim() || '—') +
          (s.guest_tel ? ' · <a href="tel:' + escHtml(s.guest_tel) + '">' + escHtml(s.guest_tel) + '</a>' : '') +
        '</dd>' +
        '<dt>Arrivée</dt>' +
        '<dd>' + escHtml(fmtDate(s.date_arrivee)) + '</dd>' +
        '<dt>Départ prévu</dt>' +
        '<dd>' + escHtml(fmtDate(s.date_depart_prevue)) + ' (' + nights + ' nuit' + (nights > 1 ? 's' : '') + ')</dd>' +
        (s.date_depart_reelle ? '<dt>Départ réel</dt><dd>' + escHtml(fmtDate(s.date_depart_reelle)) + '</dd>' : '') +
        '<dt>Prix / nuit</dt>' +
        '<dd>' + moneyFmt(s.prix_par_nuit) + ' ' + (s.remise_pct ? '(-' + escHtml(s.remise_pct) + '%)' : '') + '</dd>' +
        '<dt>Total estimé</dt>' +
        '<dd>' + moneyFmt(total) + '</dd>' +
        (s.notes ? '<dt>Notes</dt><dd>' + escHtml(s.notes) + '</dd>' : '') +
      '</dl>' +
      '<div style="margin-top:1.25rem;font-size:.75rem;color:var(--muted);">Lecture seule — utilisez la page Réservations pour modifier.</div>';
  }

  function closeDrawer() {
    document.getElementById('hotel-cal-drawer').classList.remove('open');
    document.getElementById('hotel-cal-backdrop').classList.remove('open');
  }

  function bindDrawer() {
    document.getElementById('hotel-cal-drawer-close').addEventListener('click', closeDrawer);
    document.getElementById('hotel-cal-backdrop').addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
    });
  }

  // Vue mobile : bandeaux cliquables --------------------------------------
  function bindMobileCards() {
    document.querySelectorAll('#hotel-cal-mobile .mob-sejour').forEach(function (el) {
      el.addEventListener('click', function () {
        const id = el.getAttribute('data-sejour-id');
        if (id) openSejour(id);
      });
    });
  }

  // Auto-refresh ---------------------------------------------------------

  function refresh() {
    fetch(APP_URL + '/api/hotel/calendrier?from=' + encodeURIComponent(STATE.from), {
      credentials: 'same-origin',
      cache: 'no-store',
    })
      .then(function (r) {
        if (!r.ok) return null;
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.success || !json.data) return;
        STATE.sejours = json.data.sejours || [];
        renderBands();
      })
      .catch(function () {
        /* silencieux — on retente dans 60 s */
      });
  }

  // Init -----------------------------------------------------------------

  function init() {
    startClock();
    bindDrawer();
    renderBands();
    bindMobileCards();

    // Re-render des bandes au resize (positions absolues)
    let resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(renderBands, 80);
    });

    setInterval(refresh, REFRESH_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();