<?php
/**
 * Page Hôtel > Étages — Vue mobile Gouvernante (Sprint 3).
 *
 * Ce que la gouvernante voit, concrètement :
 *   - En haut : ses stats du jour (à faire, en cours, bloquées, faites).
 *   - Une liste de cartes : chaque carte = une tâche de ménage.
 *     - Tâche "nettoyage" ou "inspection" non assignée : bouton "Réclamer".
 *     - Tâche qui lui est assignée : gros bouton vert "✓ FAIT"
 *       + bouton secondaire "⚠ Problème".
 *     - Tâche bloquée : la note du problème est visible, pas d'action possible
 *       (c'est au manager de la débloquer).
 *
 * Auto-refresh toutes les 30s.
 *
 * Design : aligné sur l'app mobile (cartes blanches, --card, --border, --primary),
 * gros boutons tactiles (≥ 48px), responsive mobile-first.
 */
$page = 'hotel-etages';

// Helper local d'icônes SVG (Feather-style 24×24, fill none, stroke 2).
// Utilisé uniquement pour le rendu serveur (les emojis côté JS sont remplacés
// par du texte/markup compatible mobile dans le script plus bas).
if (!function_exists('hotel_icon_etages')) {
    function hotel_icon_etages(string $name, int $size = 18, string $extra = ''): string
    {
        $common = "width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 24 24\" fill=\"none\" "
                . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
                . "stroke-linejoin=\"round\" {$extra}";
        $paths = [
            'broom'     => '<path d="M19 4l-7 7M5 13l4 4 7-7-4-4z"></path>'
                         . '<path d="M3 21l4-4"></path>',
            'hand'      => '<path d="M9 11V6.5a1.5 1.5 0 0 1 3 0V11"></path>'
                         . '<path d="M12 11V5.5a1.5 1.5 0 0 1 3 0V11"></path>'
                         . '<path d="M15 11V7.5a1.5 1.5 0 0 1 3 0V13"></path>'
                         . '<path d="M18 9.5a1.5 1.5 0 0 1 3 0V15a6 6 0 0 1-6 6h-2a6 6 0 0 1-6-6v-1.5a1.5 1.5 0 0 1 3 0"></path>',
            'check'     => '<polyline points="20 6 9 17 4 12"></polyline>',
            'alert'     => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>'
                         . '<line x1="12" y1="9" x2="12" y2="13"></line>'
                         . '<line x1="12" y1="17" x2="12.01" y2="17"></line>',
            'clock'     => '<circle cx="12" cy="12" r="9"></circle>'
                         . '<polyline points="12 7 12 12 15 14"></polyline>',
            'sparkle'   => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"></path>'
                         . '<circle cx="12" cy="12" r="3"></circle>',
            'bed'       => '<path d="M2 17v-5a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v5"></path>'
                         . '<path d="M2 17h20"></path>'
                         . '<path d="M6 12V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"></path>',
        ];
        $path = $paths[$name] ?? '<circle cx="12" cy="12" r="9"></circle>';
        return "<svg {$common}>{$path}</svg>";
    }
}
?>

<style>
  .etages-wrap {
    max-width: 720px;
    margin: 1rem auto;
    padding: 0 1rem 4rem 1rem;
  }

  .etages-title {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: 1.4rem;
    margin: 0 0 1rem 0;
    color: var(--foreground, #1e293b);
  }
  .etages-title .subtitle {
    font-size: .85rem;
    font-weight: 400;
    color: var(--muted, #64748b);
  }

  /* Bandeau de stats (4 tuiles, scroll horizontal sur très petit écran) */
  .etages-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .5rem;
    margin-bottom: 1.25rem;
  }
  @media (max-width: 480px) {
    .etages-stats { grid-template-columns: repeat(2, 1fr); }
  }
  .etages-stat {
    background: var(--card, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius, 8px);
    padding: .75rem;
    text-align: center;
  }
  .etages-stat .num {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--foreground, #1e293b);
  }
  .etages-stat .lbl {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted, #64748b);
    margin-top: .15rem;
  }
  .etages-stat.a-faire   .num { color: #d97706; }
  .etages-stat.en-cours  .num { color: #2563eb; }
  .etages-stat.bloque    .num { color: #dc2626; }
  .etages-stat.fait      .num { color: #16a34a; }

  /* Liste des tâches */
  .etages-list { display: flex; flex-direction: column; gap: .85rem; }

  .etages-card {
    background: var(--card, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    padding: 1rem;
    transition: box-shadow .15s, transform .12s;
  }
  .etages-card.is-bloque {
    border-left: 4px solid #dc2626;
  }
  .etages-card.is-en-cours {
    border-left: 4px solid #2563eb;
  }
  .etages-card.is-fait {
    opacity: .55;
  }

  .etages-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .75rem;
  }
  .etages-card-head .room-num {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--foreground, #1e293b);
    line-height: 1.1;
  }
  .etages-card-head .room-type {
    font-size: .72rem;
    text-transform: uppercase;
    color: var(--muted, #64748b);
    letter-spacing: .04em;
  }
  .etages-card-head .badge {
    flex-shrink: 0;
    padding: .25rem .55rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .etages-card-head .badge.nettoyage   { background: #dbeafe; color: #1e40af; }
  .etages-card-head .badge.inspection { background: #ede9fe; color: #5b21b6; }
  .etages-card-head .badge.maintenance{ background: #fef3c7; color: #92400e; }

  .etages-card-meta {
    display: flex;
    gap: .75rem;
    font-size: .78rem;
    color: var(--muted, #64748b);
    margin-bottom: .85rem;
    flex-wrap: wrap;
  }
  .etages-card-meta .statut-pill {
    padding: .15rem .55rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: .7rem;
  }
  .statut-pill.a_faire   { background: #fef3c7; color: #92400e; }
  .statut-pill.en_cours  { background: #dbeafe; color: #1e40af; }
  .statut-pill.bloque    { background: #fee2e2; color: #991b1b; }
  .statut-pill.fait      { background: #dcfce7; color: #166534; }

  .etages-card-notes {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: .5rem .65rem;
    margin-bottom: .85rem;
    font-size: .82rem;
    color: #7f1d1d;
  }

  .etages-card-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
  }
  .etages-btn {
    flex: 1 1 140px;
    min-height: 48px;
    padding: .65rem 1rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    transition: background .15s, transform .1s;
    -webkit-tap-highlight-color: transparent;
  }
  .etages-btn:active { transform: scale(.98); }
  .etages-btn.primary {
    background: #16a34a;
    color: #fff;
  }
  .etages-btn.primary:hover { background: #15803d; }
  .etages-btn.secondary {
    background: var(--card, #fff);
    color: var(--foreground, #1e293b);
    border-color: var(--border, #e2e8f0);
  }
  .etages-btn.secondary:hover { background: var(--background, #f8fafc); }
  .etages-btn.warning {
    background: #fff;
    color: #d97706;
    border-color: #fcd34d;
  }
  .etages-btn.warning:hover { background: #fffbeb; }
  .etages-btn[disabled] {
    opacity: .55;
    cursor: not-allowed;
  }

  /* Empty state */
  .etages-empty {
    background: var(--card, #fff);
    border: 1px dashed var(--border, #e2e8f0);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    color: var(--muted, #64748b);
  }
  .etages-empty .emoji {
    font-size: 2.5rem; display: inline-flex; align-items: center; justify-content: center;
    margin: 0 auto .5rem; width: 64px; height: 64px;
    border-radius: 50%; background: #f1f5f9; color: #64748b;
  }
  .etages-empty .emoji svg { width: 28px; height: 28px; }

  /* Boutons avec icône SVG inline (injectée par hotel-etages.renderTache). */
  .etages-btn {
    display: inline-flex; align-items: center; justify-content: center;
    gap: .5rem;
  }
  .etages-btn svg { width: 1.05em; height: 1.05em; flex-shrink: 0; }

  /* Toast notification */
  .etages-toast {
    position: fixed;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%) translateY(120%);
    background: #1e293b;
    color: #fff;
    padding: .7rem 1.1rem;
    border-radius: 10px;
    font-size: .88rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.25);
    z-index: 9999;
    transition: transform .25s ease;
    max-width: 90vw;
    text-align: center;
  }
  .etages-toast.show { transform: translateX(-50%) translateY(0); }
  .etages-toast.error  { background: #dc2626; }
  .etages-toast.success{ background: #16a34a; }

  /* Modal signalement */
  .etages-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
  }
  .etages-modal-backdrop.show { display: flex; }
  .etages-modal {
    background: var(--card, #fff);
    border-radius: 14px;
    width: 100%;
    max-width: 460px;
    padding: 1.25rem;
    box-shadow: 0 16px 48px rgba(0,0,0,.25);
  }
  .etages-modal h3 {
    margin: 0 0 .25rem 0;
    font-size: 1.1rem;
    color: var(--foreground, #1e293b);
  }
  .etages-modal .room-line {
    font-size: .85rem;
    color: var(--muted, #64748b);
    margin-bottom: .85rem;
  }
  .etages-modal textarea {
    width: 100%;
    min-height: 110px;
    padding: .65rem .75rem;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    font: inherit;
    font-size: .95rem;
    resize: vertical;
    box-sizing: border-box;
    color: var(--foreground, #1e293b);
    background: var(--card, #fff);
  }
  .etages-modal textarea:focus {
    outline: 2px solid var(--primary, #0B5E88);
    outline-offset: -1px;
  }
  .etages-modal-actions {
    display: flex;
    gap: .5rem;
    margin-top: 1rem;
    justify-content: flex-end;
  }
</style>

<main class="etages-wrap">
  <h1 class="etages-title">
    <span style="display:inline-flex; align-items:center; gap:.5rem;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0B5E88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M19 4l-7 7M5 13l4 4 7-7-4-4z"></path>
        <path d="M3 21l4-4"></path>
      </svg>
      Étages
    </span>
    <span class="subtitle">Mes tâches du jour</span>
  </h1>

  <!-- Stats -->
  <div class="etages-stats" id="etages-stats">
    <div class="etages-stat a-faire"><span class="num" id="stat-a-faire">–</span><span class="lbl">À faire</span></div>
    <div class="etages-stat en-cours"><span class="num" id="stat-en-cours">–</span><span class="lbl">En cours</span></div>
    <div class="etages-stat bloque"><span class="num" id="stat-bloque">–</span><span class="lbl">Bloquées</span></div>
    <div class="etages-stat fait"><span class="num" id="stat-fait">–</span><span class="lbl">Faites</span></div>
  </div>

  <!-- Liste -->
  <div class="etages-list" id="etages-list">
    <div class="etages-empty"><span class="emoji" aria-hidden="true"><?= hotel_icon_etages('clock', 32) ?></span>Chargement…</div>
  </div>
</main>

<!-- Modal signalement problème -->
<div class="etages-modal-backdrop" id="etages-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="etages-modal-title">
  <div class="etages-modal">
    <h3 id="etages-modal-title" style="display:flex; align-items:center; gap:.5rem;">
      <span style="color:#dc2626; display:inline-flex;" aria-hidden="true"><?= hotel_icon_etages('alert', 18) ?></span>
      Signaler un problème
    </h3>
    <div class="room-line" id="etages-modal-room">Chambre</div>
    <textarea id="etages-modal-msg" placeholder="Décrivez le problème (ex : baignoire cassée, drap taché, fuite…)" maxlength="500"></textarea>
    <div class="etages-modal-actions">
      <button type="button" class="etages-btn secondary" id="etages-modal-cancel" style="flex:0 0 auto;">Annuler</button>
      <button type="button" class="etages-btn primary"   id="etages-modal-submit" style="flex:0 0 auto; background:#dc2626;">Envoyer</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="etages-toast" id="etages-toast"></div>

<script>
(function() {
  const APP_URL = window.location.origin;

  // Helpers
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg, type) {
    const el = document.getElementById('etages-toast');
    el.textContent = msg;
    el.className = 'etages-toast show ' + (type || '');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { el.className = 'etages-toast'; }, 2400);
  }

  function typeLabel(t) {
    return { nettoyage: 'Nettoyage', inspection: 'Inspection', maintenance: 'Maintenance' }[t] || t;
  }
  function statutLabel(s) {
    return { a_faire: 'À faire', en_cours: 'En cours', bloque: 'Bloquée', fait: 'Faite' }[s] || s;
  }

  // ── Rendu ───────────────────────────────────────────────────────
  function renderTache(t) {
    const isMine = !!t.assigne_a; // si filtré serveur, assigne_a !== null signifie qu'on la voit (mais pas forcément à moi)
    // Note : le filtre serveur rend les tâches visibles selon les règles,
    // on n'a pas l'id utilisateur ici, mais on s'appuie sur le statut.

    const card = document.createElement('div');
    card.className = 'etages-card is-' + t.statut;
    if (t.statut === 'fait') card.classList.add('is-fait');

    const head = `
      <div class="etages-card-head">
        <div>
          <div class="room-num">Chambre ${esc(t.chambre_numero)}</div>
          <div class="room-type">${esc(t.chambre_type || '')}</div>
        </div>
        <span class="badge ${esc(t.type_tache)}">${esc(typeLabel(t.type_tache))}</span>
      </div>`;

    const statutPill = `<span class="statut-pill ${esc(t.statut)}">${esc(statutLabel(t.statut))}</span>`;
    const dateTxt = t.date_creation ? new Date(t.date_creation.replace(' ', 'T')).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
    const meta = `<div class="etages-card-meta">${statutPill}${dateTxt ? `<span>Créée le ${esc(dateTxt)}</span>` : ''}</div>`;

    const notesBlock = (t.notes && t.statut === 'bloque')
      ? `<div class="etages-card-notes"><strong>Problème signalé :</strong><br>${esc(t.notes)}</div>`
      : '';

    // Actions selon le statut
    let actions = '';
    const chambreId = t.chambre_id;
    const taskId = t.id; // null pour les chambres orphelines
    const isOrphan = !taskId;

    if (t.statut === 'a_faire' && !t.assigne_a) {
      actions = `
        <div class="etages-card-actions">
          <button type="button" class="etages-btn primary"   data-action="claim" data-task-id="${taskId ?? ''}" data-chambre-id="${chambreId}" data-room="${esc(t.chambre_numero)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11V6.5a1.5 1.5 0 0 1 3 0V11"/><path d="M12 11V5.5a1.5 1.5 0 0 1 3 0V11"/><path d="M15 11V7.5a1.5 1.5 0 0 1 3 0V13"/><path d="M18 9.5a1.5 1.5 0 0 1 3 0V15a6 6 0 0 1-6 6h-2a6 6 0 0 1-6-6v-1.5a1.5 1.5 0 0 1 3 0"/></svg>Réclamer</button>
          <button type="button" class="etages-btn warning"   data-action="issue" data-task-id="${taskId ?? ''}" data-chambre-id="${chambreId}" data-room="${esc(t.chambre_numero)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Problème</button>
        </div>`;
    } else if (t.statut === 'a_faire' || t.statut === 'en_cours') {
      actions = `
        <div class="etages-card-actions">
          <button type="button" class="etages-btn primary"   data-action="done"  data-task-id="${taskId ?? ''}" data-chambre-id="${chambreId}" data-room="${esc(t.chambre_numero)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>Fait</button>
          <button type="button" class="etages-btn warning"   data-action="issue" data-task-id="${taskId ?? ''}" data-chambre-id="${chambreId}" data-room="${esc(t.chambre_numero)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Problème</button>
        </div>`;
    } else if (t.statut === 'bloque') {
      actions = `
        <div class="etages-card-actions">
          <button type="button" class="etages-btn secondary" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>En attente du manager</button>
        </div>`;
    } else {
      actions = ''; // fait : pas d'actions
    }

    card.innerHTML = head + meta + notesBlock + actions;
    return card;
  }

  function renderAll(data) {
    // Stats
    const s = data.stats || {};
    document.getElementById('stat-a-faire').textContent  = s.a_faire     ?? 0;
    document.getElementById('stat-en-cours').textContent = s.en_cours    ?? 0;
    document.getElementById('stat-bloque').textContent   = s.bloque      ?? 0;
    document.getElementById('stat-fait').textContent     = s.faites_jour ?? 0;

    // Liste
    const list = document.getElementById('etages-list');
    list.innerHTML = '';
    if (!data.taches || data.taches.length === 0) {
      list.innerHTML = '<div class="etages-empty"><span class="emoji" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="3"/></svg></span>Aucune chambre à nettoyer pour le moment.<br>Vous êtes à jour, merci !</div>';
      return;
    }
    data.taches.forEach(t => list.appendChild(renderTache(t)));
  }

  // ── Appels API ──────────────────────────────────────────────────
  async function loadBoard() {
    try {
      const res = await fetch(APP_URL + '/api/hotel/etages/board', { credentials: 'same-origin', cache: 'no-store' });
      if (res.status === 401 || res.status === 403) {
        window.location.href = '/?reason=session_expired';
        return;
      }
      const json = await res.json();
      if (json && json.success) {
        renderAll(json.data);
      } else {
        toast(json.message || 'Erreur de chargement', 'error');
      }
    } catch (e) {
      console.error('[etages] load error', e);
      toast('Connexion perdue — réessai…', 'error');
    }
  }

  async function callAction(action, id, extra) {
    const body = new FormData();
    if (id && id.taskId) body.append('id', id.taskId);
    if (id && id.chambreId) body.append('chambre_id', id.chambreId);
    if (extra) Object.entries(extra).forEach(([k, v]) => body.append(k, v));
    // URL : on a besoin d'au moins un des deux (id ou chambre_id) — l'API tolère 0.
    const urlKey = id && id.taskId ? id.taskId : 0;
    const urlMap = {
      claim: '/api/hotel/housekeeping/' + urlKey + '/claim',
      done : '/api/hotel/housekeeping/' + urlKey + '/done',
      issue: '/api/hotel/housekeeping/' + urlKey + '/issue',
    };
    try {
      const res = await fetch(APP_URL + urlMap[action], {
        method: 'POST',
        credentials: 'same-origin',
        body,
      });
      const json = await res.json().catch(() => ({}));
      if (res.ok && json.success) {
        const msg = { claim: 'Tâche réclamée', done: 'Chambre marquée faite', issue: 'Problème signalé au manager' }[action];
        toast(msg, 'success');
        loadBoard();
      } else {
        toast(json.message || ('Erreur ' + res.status), 'error');
      }
    } catch (e) {
      console.error('[etages] action error', e);
      toast('Connexion perdue — réessai…', 'error');
    }
  }

  // ── Modal signalement ───────────────────────────────────────────
  const modal      = document.getElementById('etages-modal-backdrop');
  const modalRoom  = document.getElementById('etages-modal-room');
  const modalMsg   = document.getElementById('etages-modal-msg');
  const modalSubmit= document.getElementById('etages-modal-submit');
  const modalCancel= document.getElementById('etages-modal-cancel');
  let modalCurrent = null; // { taskId, chambreId }

  function openModal(target, roomLabel) {
    modalCurrent = target;
    modalRoom.textContent = 'Chambre ' + roomLabel;
    modalMsg.value = '';
    modal.classList.add('show');
    setTimeout(() => modalMsg.focus(), 50);
  }
  function closeModal() {
    modal.classList.remove('show');
    modalCurrent = null;
  }
  modalCancel.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
  modalSubmit.addEventListener('click', () => {
    const msg = modalMsg.value.trim();
    if (!msg) { toast('Le message est vide', 'error'); return; }
    if (!modalCurrent) return;
    callAction('issue', modalCurrent, { message: msg });
    closeModal();
  });

  // ── Délégation des clics sur la liste ──────────────────────────
  document.getElementById('etages-list').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const taskId = btn.dataset.taskId ? parseInt(btn.dataset.taskId, 10) : 0;
    const chambreId = btn.dataset.chambreId ? parseInt(btn.dataset.chambreId, 10) : 0;
    if (action === 'issue') {
      openModal({ taskId, chambreId }, btn.dataset.room || '');
    } else {
      callAction(action, { taskId, chambreId });
    }
  });

  // ── Démarrage ───────────────────────────────────────────────────
  loadBoard();
  setInterval(loadBoard, 30000);
})();
</script>