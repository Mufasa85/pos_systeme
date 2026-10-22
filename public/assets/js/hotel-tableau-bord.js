/**
 * Page Tableau de bord hôtel (/hotel/tableau-bord)
 *
 * Sprint 4 — branchement sur la DB via GET /api/hotel/dashboard.
 *
 * La vue PHP (app/views/hotel-tableau-bord.php) fournit le squelette :
 *   - 4 cartes KPI avec des valeurs « — » et des IDs :
 *       #kpi-chambres-occupees, #kpi-chambres-total, #kpi-taux-occupation
 *       #kpi-a-nettoyer, #kpi-housekeeping
 *       #kpi-recettes, #kpi-sejours-factures
 *       #kpi-extras
 *   - 2 sections avec un état vide :
 *       #sejours-cours-body (+ #sejours-cours-count)
 *       #housekeeping-body (+ #housekeeping-count)
 *
 * Ce script :
 *   1. charge l'agrégat au chargement ;
 *   2. hydrate les 4 KPI + les 2 listes ;
 *   3. rafraîchit automatiquement toutes les 60 s (comme /hotel/calendrier) ;
 *   4. gère les états de chargement / erreur sans casser la page.
 *
 * Aucun accès base de données depuis le navigateur : tout passe par
 * l'API, qui applique les guards (manager + admin/super_admin).
 */
(function () {
  'use strict';

  const API_URL = '/api/hotel/dashboard';
  const REFRESH_MS = 60000; // 60 s — même cadence que le calendrier hôtel
  let refreshTimer = null;

  // ── Helpers ──────────────────────────────────────────────────────────
  const $ = (id) => document.getElementById(id);

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /** Montant en francs congolais, 2 décimales, séparateur de milliers. */
  function fmtFc(n) {
    return (parseFloat(n) || 0).toLocaleString('fr-FR', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  /** Date ISO/MySQL → JJ/MM/AAAA. */
  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(s);
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
  }

  /** Nombre de nuits entre maintenant et la date de départ prévue (>= 0). */
  function nightsUntil(dateStr) {
    if (!dateStr) return 0;
    const d = new Date(String(dateStr).replace(' ', 'T'));
    if (isNaN(d.getTime())) return 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diff = Math.round((target - today) / 86400000);
    return diff > 0 ? diff : 0;
  }

  /** Libellé lisible pour un type de tâche housekeeping. */
  function hkTypeLabel(t) {
    switch (t) {
      case 'nettoyage':   return 'Nettoyage';
      case 'inspection':  return 'Inspection';
      case 'maintenance': return 'Maintenance';
      default:            return t || 'Tâche';
    }
  }
  // ── Rendu des 4 cartes KPI ─────────────────────────────────────────
  // Les valeurs « — » présentes dans la vue PHP sont remplacées ici.
  function renderKpi(kpi) {
    kpi = kpi || {};

    // 1) Chambres occupées : « N / Total » + taux d'occupation.
    const occ = parseInt(kpi.chambres_occupees, 10) || 0;
    const total = parseInt(kpi.chambres_total, 10) || 0;
    const taux = parseFloat(kpi.taux_occupation) || 0;

    const elOcc = $('kpi-chambres-occupees');
    if (elOcc) {
      elOcc.innerHTML = occ + ' <span class="hotel-kpi__value-suffix">/ ' + total + '</span>';
    }
    const elTotal = $('kpi-chambres-total');
    if (elTotal) elTotal.textContent = total;

    const elTaux = $('kpi-taux-occupation');
    if (elTaux) elTaux.textContent = taux.toFixed(1).replace('.', ',') + ' %';

    // 2) « À nettoyer » = chambres en état a_nettoyer.
    //    Le hint affiche le nombre de TÂCHES housekeeping en attente
    //    (ce sont deux notions différentes : état de chambre vs tâche).
    const elNettoyer = $('kpi-a-nettoyer');
    if (elNettoyer) elNettoyer.textContent = (parseInt(kpi.chambres_a_nettoyer, 10) || 0);

    const elHk = $('kpi-housekeeping');
    if (elHk) elHk.textContent = (parseInt(kpi.housekeeping_attente, 10) || 0);

    // 3) Recettes du jour (TTC) + nb de séjours facturés.
    const elRec = $('kpi-recettes');
    if (elRec) {
      elRec.innerHTML = fmtFc(kpi.recettes_jour)
        + ' <span class="hotel-kpi__value-suffix">FC</span>';
    }
    const elFact = $('kpi-sejours-factures');
    if (elFact) elFact.textContent = (parseInt(kpi.sejours_factures_jour, 10) || 0);

    // 4) Extras & services du jour.
    const elExtras = $('kpi-extras');
    if (elExtras) {
      elExtras.innerHTML = fmtFc(kpi.extras_jour)
        + ' <span class="hotel-kpi__value-suffix">FC</span>';
    }
  }
  // ── Rendu : séjours en cours ────────────────────────────────────────
  // Remplace l'état vide de la vue par la liste réelle.
  function renderSejours(sejours) {
    const body = $('sejours-cours-body');
    const count = $('sejours-cours-count');
    if (!body) return;

    sejours = Array.isArray(sejours) ? sejours : [];
    if (count) count.textContent = sejours.length;

    if (sejours.length === 0) {
      // On conserve l'état vide déjà présent dans la vue (pas de re-render).
      body.innerHTML =
        '<div class="hotel-empty">'
        + '  <div class="hotel-empty__title">Aucun séjour en cours</div>'
        + '  <p class="hotel-empty__hint">'
        + '    Les check-ins effectués apparaîtront ici automatiquement.<br>'
        + '    Vue complète disponible sur <a href="/hotel/reception">Réception</a>.'
        + '  </p>'
        + '</div>';
      return;
    }

    // Une ligne par séjour : chambre + client + dates + nuits restantes.
    // On réutilise les classes déjà présentes dans la vue
    // (.hotel-task, .hotel-task__avatar, .hotel-task__body, ...) pour
    // ne pas introduire de nouveau CSS.
    body.innerHTML = sejours.map(function (s) {
      const nomComplet = ((s.guest_prenom || '') + ' ' + (s.guest_nom || '')).trim();
      const nuits = nightsUntil(s.date_depart_prevue);
      const initiales = ((s.guest_prenom || s.guest_nom || '?').trim().charAt(0) || '?').toUpperCase();
      const badge = '<span class="hotel-pill hotel-pill--info">'
        + 'Ch. ' + escapeHtml(s.chambre_numero || '?') + '</span>';
      const typeBadge = '<span class="hotel-pill hotel-pill--neutral">'
        + escapeHtml(s.chambre_type || '') + '</span>';
      return '<div class="hotel-task">'
        + '  <div class="hotel-task__avatar">' + escapeHtml(initiales) + '</div>'
        + '  <div class="hotel-task__body">'
        + '    <p class="hotel-task__title">' + escapeHtml(nomComplet) + '</p>'
        + '    <p class="hotel-task__sub">'
        + '      ' + badge + ' ' + typeBadge
        + '      <span style="color:#94a3b8;">Arrivée ' + fmtDate(s.date_arrivee)
        + ' · Départ ' + fmtDate(s.date_depart_prevue)
        + ' · ' + (nuits > 0 ? nuits + ' nuit(s) restante(s)' : 'Départ aujourd\'hui')
        + '</span>'
        + '    </p>'
        + '  </div>'
        + '</div>';
    }).join('');
  }
  // ── Rendu : housekeeping du jour ───────────────────────────────────
  function renderHousekeeping(tasks) {
    const body = $('housekeeping-body');
    const count = $('housekeeping-count');
    if (!body) return;

    tasks = Array.isArray(tasks) ? tasks : [];
    if (count) count.textContent = tasks.length;

    if (tasks.length === 0) {
      body.innerHTML =
        '<div class="hotel-empty">'
        + '  <div class="hotel-empty__title">Aucune tâche en attente</div>'
        + '  <p class="hotel-empty__hint">'
        + '    Les ménages à effectuer apparaîtront ici dès qu\'une chambre passe en '
        + '    <em>à nettoyer</em>.<br>'
        + '    Suivi détaillé sur <a href="/hotel/etages">Étages</a>.'
        + '  </p>'
        + '</div>';
      return;
    }

    body.innerHTML = tasks.map(function (t) {
      // Avatar : type de tâche (N = nettoyage, I = inspection, M = maintenance).
      const lettre = (hkTypeLabel(t.type).charAt(0) || 'T').toUpperCase();
      const enCours = (t.statut === 'en_cours');
      const badge = '<span class="hotel-pill ' + (enCours ? 'hotel-pill--info' : 'hotel-pill--neutral') + '">'
        + escapeHtml(hkTypeLabel(t.type)) + '</span>';
      return '<div class="hotel-task">'
        + '  <div class="hotel-task__avatar">' + escapeHtml(lettre) + '</div>'
        + '  <div class="hotel-task__body">'
        + '    <p class="hotel-task__title">Chambre ' + escapeHtml(t.chambre_numero || '?') + '</p>'
        + '    <p class="hotel-task__sub">' + badge
        + '      <span style="color:#94a3b8;">'
        + (enCours ? 'En cours' : 'À faire')
        + (t.notes ? ' · ' + escapeHtml(t.notes) : '')
        + '</span>'
        + '    </p>'
        + '  </div>'
        + '</div>';
    }).join('');
  }

  // ── Chargement ─────────────────────────────────────────────────────
  /** Affiche une erreur inline dans les deux sections sans casser la page. */
  function renderError(msg) {
    const html = '<div class="hotel-empty">'
      + '  <div class="hotel-empty__title">Données indisponibles</div>'
      + '  <p class="hotel-empty__hint">' + escapeHtml(msg) + '</p>'
      + '</div>';
    const a = $('sejours-cours-body');
    const b = $('housekeeping-body');
    if (a) a.innerHTML = html;
    if (b) b.innerHTML = html;
  }

  async function load() {
    try {
      const res = await fetch(API_URL, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const body = await res.json();

      if (!res.ok || !body || body.success !== true) {
        renderError((body && body.message) || ('Erreur ' + res.status));
        return;
      }

      const data = body.data || {};
      renderKpi(data.kpi);
      renderSejours(data.sejours);
      renderHousekeeping(data.housekeeping);
    } catch (e) {
      console.error('[hotel-dash] chargement impossible', e);
      renderError('Impossible de contacter le serveur. Vérifiez votre session.');
    }
  }

  function startAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(load, REFRESH_MS);
  }

  // ── Init ───────────────────────────────────────────────────────────
  // On n'expose rien sur window : ce script n'a pas de handler inline
  // dans le markup (contrairement à hotel-facturation.js).
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      load();
      startAutoRefresh();
    });
  } else {
    load();
    startAutoRefresh();
  }
})();