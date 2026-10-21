<?php
/**
 * Page Hôtel > Chambres (gestion du catalogue de chambres).
 *
 * - Liste des chambres (numéro, type, prix, état, notes)
 * - Changement d'état rapide (manager uniquement)
 * - Création / édition via drawer latéral (manager uniquement)
 * - Réceptionniste et gouvernante : lecture seule (tous les contrôles sont désactivés)
 *
 * Visibilité : tous les rôles hôtel. Permissions gérées par
 * /api/hotel/chambres qui renvoie un flag "read_only".
 */
$page = 'hotel-chambres';
?>
<style>
  /* Conteneur large & spacieux (SaaS moderne), reste responsive. */
  .hotel-rooms-wrap {
    max-width: 1600px;
    width: 100%;
    margin: 1.25rem auto;
    padding: 0 1.25rem;
  }
  @media (max-width: 640px) {
    .hotel-rooms-wrap { padding: 0 .75rem; }
  }
  .hotel-rooms-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; }
  .hotel-rooms-table thead th { font-size: .7rem; text-transform: uppercase; color: var(--muted); }
  .hotel-rooms-empty { padding: 3rem; text-align: center; color: var(--muted); }

  /* Badges d'état (cohérents avec l'app) */
  .hotel-etat-disponible  { background: #dcfce7; color: #166534; }
  .hotel-etat-a_nettoyer  { background: #fef3c7; color: #92400e; }
  .hotel-etat-hors_service{ background: #fee2e2; color: #991b1b; }

  .hotel-rooms-type-simple { background: #dbeafe; color: #1e40af; }
  .hotel-rooms-type-double { background: #ede9fe; color: #5b21b6; }

  .hotel-etat-select { padding: 4px 8px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; font-size: .8rem; cursor: pointer; }
  .hotel-etat-select:disabled { cursor: not-allowed; opacity: .65; background: var(--background); }

  /* Réutilise les styles drawer de hotel-reservations si déjà chargés, sinon inline */
  .hotel-drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; display: none; }
  .hotel-drawer-backdrop.open { display: block; }
  .hotel-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 480px; max-width: 100vw; background: var(--card); box-shadow: var(--shadow-lg); transform: translateX(100%); transition: transform .25s ease; z-index: 9001; display: flex; flex-direction: column; }
  .hotel-drawer.open { transform: translateX(0); }
  .hotel-drawer-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .hotel-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground); }
  .hotel-drawer-close { background: transparent; border: 0; cursor: pointer; color: var(--muted); padding: 4px; border-radius: var(--radius); }
  .hotel-drawer-close:hover { background: var(--background); color: var(--foreground); }
  .hotel-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
  .hotel-drawer-foot { padding: .85rem 1.25rem; border-top: 1px solid var(--border); display: flex; gap: .5rem; justify-content: flex-end; background: var(--background); }

  .hotel-alert { padding: .65rem .85rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .85rem; }
  .hotel-alert.error { background: #fee2e2; color: #991b1b; }
  .hotel-alert.success { background: #dcfce7; color: #166534; }
  .hotel-alert.hidden { display: none; }

  .hotel-readonly-banner {
    background: #fef3c7; color: #92400e;
    padding: .65rem .85rem; border-radius: var(--radius);
    margin-bottom: 1rem; font-size: .82rem;
    display: flex; gap: .5rem; align-items: center;
  }
</style>

<main class="hotel-rooms-wrap">
  <div class="hotel-rooms-card">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div>
        <h2 style="margin:0;color:var(--foreground);display:flex;align-items:center;gap:.5rem;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary);">
            <path d="M2 17v-5a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v5"></path>
            <path d="M2 17h20"></path>
            <path d="M6 12V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"></path>
          </svg>
          Chambres
        </h2>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
          Catalogue des chambres de l'hôtel (numéro, type, prix, état, notes).
        </div>
      </div>
      <button type="button" class="btn btn-primary js-new" id="btn-new-room" style="display:none;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y1="19"></line>
          <line x1="5" y1="12" x2="19" y1="12"></line>
        </svg>
        Nouvelle chambre
      </button>
    </div>

    <div id="hotel-alert-page" class="hotel-alert hidden"></div>
    <div id="hotel-readonly-banner" class="hotel-readonly-banner hidden">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y1="12"></line><line x1="12" y1="16" x2="12.01" y1="16"></line>
      </svg>
      <span>Lecture seule — la création et la modification sont réservées au manager.</span>
    </div>

    <div class="table-container">
      <table class="data-table hotel-rooms-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Type</th>
            <th style="text-align:right;">Prix / nuit</th>
            <th style="text-align:right;">Taxe spéc. / nuit</th>
            <th>État</th>
            <th>Notes</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody id="hotel-rooms-tbody">
          <tr><td colspan="7" class="hotel-rooms-empty">Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Drawer Création / Édition -->
<div class="hotel-drawer-backdrop" id="hotel-room-drawer-backdrop"></div>
<aside class="hotel-drawer" id="hotel-room-drawer" role="dialog" aria-modal="true" aria-labelledby="hotel-room-drawer-title">
  <header class="hotel-drawer-head">
    <h3 id="hotel-room-drawer-title">Nouvelle chambre</h3>
    <button type="button" class="hotel-drawer-close" id="hotel-room-drawer-close" aria-label="Fermer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y1="18"></line><line x1="6" y1="6" x2="18" y1="18"></line>
      </svg>
    </button>
  </header>
  <div class="hotel-drawer-body">
    <div id="hotel-room-drawer-alert" class="hotel-alert hidden"></div>
    <form id="hotel-room-form" autocomplete="off">
      <input type="hidden" name="id" value="">
      <div class="form-group">
        <label for="room-numero">Numéro *</label>
        <input type="text" id="room-numero" name="numero" class="form-input" required maxlength="10">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label for="room-type">Type *</label>
          <select id="room-type" name="type" class="form-input" required>
            <option value="simple">Simple</option>
            <option value="double">Double</option>
          </select>
        </div>
        <div class="form-group">
          <label for="room-prix">Prix / nuit *</label>
          <input type="number" step="0.01" min="0" id="room-prix" name="prix_base" class="form-input" required>
        </div>
      </div>
      <div class="form-group">
        <label for="room-etat">État</label>
        <select id="room-etat" name="etat" class="form-input">
          <option value="disponible">Disponible</option>
          <option value="a_nettoyer">À nettoyer</option>
          <option value="hors_service">Hors service</option>
        </select>
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">
          L'état "occupée" est inféré depuis les séjours en cours — non modifiable ici.
        </div>
      </div>
      <div class="form-group">
        <label for="room-notes">Notes</label>
        <textarea id="room-notes" name="notes" class="form-input" rows="4"></textarea>
      </div>
      <!--
        Taxe spécifique — calquée sur le pattern produit (cf. posCart.saveProduct
        / ProductController). S'applique PAR NUITÉE lors de la facturation hôtel.
        - type : '%' (pourcentage du prix de la nuit) ou 'CDF' (montant fixe / nuit)
        - value : numérique ≥ 0 (CDF borné par le prix de la nuit côté JS)
      -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label for="room-taxe-specifique-type">Taxe spécifique (type)</label>
          <select id="room-taxe-specifique-type" name="taxe_specifique_type" class="form-input">
            <option value="%">% (pourcentage)</option>
            <option value="CDF">CDF (montant fixe)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="room-taxe-specifique-value">Taxe spécifique (valeur)</label>
          <input type="number" step="0.01" min="0" id="room-taxe-specifique-value" name="taxe_specifique_value" class="form-input" value="0">
          <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">
            Appliquée par nuitée lors de la facturation.
          </div>
        </div>
      </div>
      <!--
        Taxe de la chambre (Sprint 7 — 2026-10-19) : FK vers la table
        `taxes`. Permet à la facturation hôtel de récupérer directement
        le taux de TVA applicable (ex: B = 16% hôtellerie) via la
        jointure séjours → chambres → taxes, sans fetch supplémentaire
        côté front. Par défaut la liste est chargée via /api/taxes.
        Si la table n'a pas encore de ligne, le select sera vide et la
        chambre pourra être sauvegardée sans taxe (taxe_id=NULL).
      -->
      <div class="form-group">
        <label for="room-taxe-id">Taxe applicable (régime TVA)</label>
        <select id="room-taxe-id" name="taxe_id" class="form-input">
          <option value="">— Aucune (par défaut) —</option>
        </select>
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">
          Le taux de cette taxe est envoyé à la DGI lors de la facturation hôtel (régime TVA type B hôtellerie = 16%).
        </div>
      </div>
    </form>
  </div>
  <footer class="hotel-drawer-foot">
    <button type="button" class="btn btn-ghost" id="hotel-room-drawer-cancel">Annuler</button>
    <button type="submit" form="hotel-room-form" class="btn btn-primary" id="hotel-room-drawer-save">Enregistrer</button>
  </footer>
</aside>

<script src="/assets/js/hotel-chambres.js"></script>
