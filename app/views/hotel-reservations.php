<?php
/**
 * Page Hôtel > Réservations.
 *
 * Visibilité : manager + réceptionniste (et super_admin).
 *
 * - Liste des réservations avec filtres par statut
 * - Bouton "Nouvelle réservation" → drawer latéral
 * - Edition et annulation depuis le drawer
 *
 * Le contrôleur alimente la page via fetch() sur /api/hotel/reservations.
 */
$page = 'hotel-reservations';

// helper : format date FR
if (!function_exists('hr_date')) {
    function hr_date($s) {
        if (!$s) return '';
        $dt = strtotime($s);
        return $dt ? date('d/m/Y H:i', $dt) : htmlspecialchars($s);
    }
}
?>
<style>
  .hotel-res-wrap { max-width: 1100px; margin: 1.5rem auto; padding: 0 1rem; }
  .hotel-res-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; }
  .hotel-res-toolbar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
  .hotel-res-toolbar select { padding: 6px 10px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; font-size: .85rem; }
  .hotel-res-filters { flex: 1; display: flex; gap: .5rem; }
  .hotel-res-table thead th { font-size: .7rem; text-transform: uppercase; color: var(--muted); }
  .hotel-res-empty { padding: 3rem; text-align: center; color: var(--muted); }
  .hotel-res-statut-reserve   { background: #dbeafe; color: #1e40af; }
  .hotel-res-statut-en_cours  { background: #dcfce7; color: #166534; }
  .hotel-res-statut-termine   { background: #f1f5f9; color: #475569; }
  .hotel-res-statut-annule    { background: #fee2e2; color: #991b1b; }
  .hotel-res-statut-no_show   { background: #fef3c7; color: #92400e; }

  /* Drawer latéral droit — utilise les variables de l'app, pas de redéfinition */
  .hotel-drawer-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.4);
    z-index: 9000; display: none;
  }
  .hotel-drawer-backdrop.open { display: block; }
  .hotel-drawer {
    position: fixed; top: 0; right: 0; height: 100vh;
    width: 480px; max-width: 100vw;
    background: var(--card);
    box-shadow: var(--shadow-lg);
    transform: translateX(100%);
    transition: transform .25s ease;
    z-index: 9001;
    display: flex; flex-direction: column;
  }
  .hotel-drawer.open { transform: translateX(0); }
  .hotel-drawer-head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
  }
  .hotel-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground); }
  .hotel-drawer-close {
    background: transparent; border: 0; cursor: pointer;
    color: var(--muted); padding: 4px; border-radius: var(--radius);
  }
  .hotel-drawer-close:hover { background: var(--background); color: var(--foreground); }
  .hotel-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
  .hotel-drawer-foot {
    padding: .85rem 1.25rem;
    border-top: 1px solid var(--border);
    display: flex; gap: .5rem; justify-content: flex-end;
    background: var(--background);
  }

  /* Recherche guest inline */
  .hotel-guest-search { position: relative; }
  .hotel-guest-results {
    position: absolute; top: 100%; left: 0; right: 0;
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow);
    max-height: 220px; overflow-y: auto; z-index: 10;
    display: none;
  }
  .hotel-guest-results.open { display: block; }
  .hotel-guest-item {
    padding: .55rem .75rem; cursor: pointer;
    border-bottom: 1px solid var(--border);
    font-size: .85rem;
  }
  .hotel-guest-item:last-child { border-bottom: 0; }
  .hotel-guest-item:hover { background: var(--background); }
  .hotel-guest-item .muted { color: var(--muted); font-size: .75rem; }

  .hotel-alert { padding: .65rem .85rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .85rem; }
  .hotel-alert.error { background: #fee2e2; color: #991b1b; }
  .hotel-alert.success { background: #dcfce7; color: #166534; }
  .hotel-alert.hidden { display: none; }
</style>

<main class="hotel-res-wrap">
  <div class="hotel-res-card">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div>
        <h2 style="margin:0;color:var(--foreground);">Réservations hôtel</h2>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
          Créez, modifiez ou annulez les réservations. Le check-in / check-out reste sur la page Réception.
        </div>
      </div>
      <button type="button" class="btn btn-primary" id="btn-new-reservation">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y1="19"></line>
          <line x1="5" y1="12" x2="19" y1="12"></line>
        </svg>
        Nouvelle réservation
      </button>
    </div>

    <div class="hotel-res-toolbar">
      <div class="hotel-res-filters">
        <label style="font-size:.8rem;color:var(--muted);align-self:center;">Statut :</label>
        <select id="filter-statut">
          <option value="">Toutes</option>
          <option value="reserve">Réservée</option>
          <option value="en_cours">En cours</option>
          <option value="termine">Terminée</option>
          <option value="annule">Annulée</option>
          <option value="no_show">No-show</option>
        </select>
        <label style="font-size:.8rem;color:var(--muted);align-self:center;margin-left:.5rem;">Du</label>
        <input type="date" id="filter-from" style="padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius);font-size:.85rem;">
        <label style="font-size:.8rem;color:var(--muted);align-self:center;">au</label>
        <input type="date" id="filter-to" style="padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius);font-size:.85rem;">
        <button type="button" class="btn btn-ghost btn-small" id="btn-clear-filters">Réinitialiser</button>
      </div>
    </div>

    <div id="hotel-alert" class="hotel-alert hidden"></div>

    <div class="table-container">
      <table class="data-table hotel-res-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th>Guest</th>
            <th>Chambre</th>
            <th>Arrivée</th>
            <th>Départ</th>
            <th>Prix / nuit</th>
            <th>Statut</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody id="hotel-res-tbody">
          <tr><td colspan="7" class="hotel-res-empty">Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Drawer Nouvelle/Édition de réservation -->
<div class="hotel-drawer-backdrop" id="hotel-drawer-backdrop"></div>
<aside class="hotel-drawer" id="hotel-drawer" role="dialog" aria-modal="true" aria-labelledby="hotel-drawer-title">
  <header class="hotel-drawer-head">
    <h3 id="hotel-drawer-title">Nouvelle réservation</h3>
    <button type="button" class="hotel-drawer-close" id="hotel-drawer-close" aria-label="Fermer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y1="18"></line><line x1="6" y1="6" x2="18" y1="18"></line>
      </svg>
    </button>
  </header>
  <div class="hotel-drawer-body">
    <div id="hotel-drawer-alert" class="hotel-alert hidden"></div>
    <form id="hotel-res-form" autocomplete="off">
      <input type="hidden" name="id" value="">

      <div class="form-group">
        <label for="res-guest-search">Guest *</label>
        <div class="hotel-guest-search">
          <input type="text" id="res-guest-search" class="form-input" placeholder="Rechercher par nom, téléphone, pièce d'identité…">
          <input type="hidden" name="guest_id" id="res-guest-id" value="">
          <div class="hotel-guest-results" id="hotel-guest-results"></div>
        </div>
        <div id="res-guest-selected" style="margin-top:.5rem;font-size:.8rem;color:var(--muted);" class="hidden">
          Guest sélectionné : <strong id="res-guest-label"></strong>
          <button type="button" class="btn btn-ghost btn-small" id="res-guest-clear" style="margin-left:.5rem;">Changer</button>
        </div>
        <div id="res-guest-new-fields" style="margin-top:.75rem;display:none;">
          <p style="font-size:.75rem;color:var(--muted);margin-bottom:.5rem;">Nouveau guest — remplissez les infos ci-dessous :</p>
          <div class="form-group">
            <label for="res-guest-nom">Nom *</label>
            <input type="text" id="res-guest-nom" name="guest_nom" class="form-input">
          </div>
          <div class="form-group">
            <label for="res-guest-prenom">Prénom</label>
            <input type="text" id="res-guest-prenom" name="guest_prenom" class="form-input">
          </div>
          <div class="form-group">
            <label for="res-guest-tel">Téléphone</label>
            <input type="text" id="res-guest-tel" name="guest_tel" class="form-input">
          </div>
          <div class="form-group">
            <label for="res-guest-piece">Pièce d'identité</label>
            <input type="text" id="res-guest-piece" name="guest_piece" class="form-input">
          </div>
        </div>
      </div>

      <hr style="margin:1rem 0;border:0;border-top:1px solid var(--border);">

      <div class="form-group">
        <label for="res-chambre">Chambre *</label>
        <select id="res-chambre" name="chambre_id" class="form-input" required>
          <option value="">— Sélectionner une période d'abord —</option>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label for="res-arrivee">Arrivée *</label>
          <input type="datetime-local" id="res-arrivee" name="date_arrivee" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="res-depart">Départ prévu *</label>
          <input type="date" id="res-depart" name="date_depart_prevue" class="form-input" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label for="res-prix">Prix / nuit *</label>
          <input type="number" step="0.01" min="0" id="res-prix" name="prix_par_nuit" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="res-remise">Remise %</label>
          <input type="number" step="0.01" min="0" max="100" id="res-remise" name="remise_pct" class="form-input" value="0">
        </div>
      </div>

      <div class="form-group">
        <label for="res-notes">Notes</label>
        <textarea id="res-notes" name="notes" class="form-input" rows="3"></textarea>
      </div>
    </form>
  </div>
  <footer class="hotel-drawer-foot">
    <button type="button" class="btn btn-ghost" id="hotel-drawer-cancel">Annuler</button>
    <button type="submit" form="hotel-res-form" class="btn btn-primary" id="hotel-drawer-save">Enregistrer</button>
  </footer>
</aside>

<script src="/assets/js/hotel-reservations.js"></script>
<script src="/assets/js/hotel-reservation-form.js"></script>
