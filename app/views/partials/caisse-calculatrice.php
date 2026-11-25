<?php
/**
 * MODAL CALCULATRICE DE LA CAISSE (partial)
 * -----------------------------------------
 * Outil d'aide au vendeur au moment de l'encaissement.
 *
 * Cas d'usage réel : le client doit 5 000 Fc et donne 10 USD. Le vendeur
 * tape 10 USD dans la calculatrice et lit directement :
 *   - le montant en francs que représente ce 10 USD (au taux DGI du jour) ;
 *   - la monnaie à rendre, en francs ET en dollars.
 *
 * Sources de vérité :
 *   - le total à payer : `posCart.currentTotals.total` (le TTC affiché dans
 *     le panier) — jamais recalculé ici ;
 *   - le taux de change : l'API DGI du jour (`GET /api/currency`, exposée
 *     par le proxy OSAT) — jamais codé en dur (l'ancien modal de paiement
 *     utilisait 2 300 Fc figés, cf. docs/CALCUL_TTC_TS.md, constat n°10).
 *
 * Toutes les classes sont préfixées `calcx-` : aucune collision possible
 * avec `.btn`, `.modal`, `.modal-content`… du POS.
 */
?>
<style>
  /* ── Bouton « Calculatrice » du panier ──────────────────────────────
     ⚠️ Ce contrôle était une <label> contenant une case à cocher ; c'est
     désormais un <button>. On neutralise donc les styles par défaut du
     navigateur (fond gris, bordure, padding) pour qu'il garde exactement
     l'apparence de l'ancien label.

     Sélecteur volontairement plus spécifique que `.calculator-radio`
     (dans public/assets/css/styles.css) : il gagne quelle que soit
     l'ordre de chargement des feuilles de style, et le fichier partagé
     n'a pas besoin d'être touché. */
  #calculator-toggle.calculator-radio {
    background: transparent;
    border: 0;
    padding: 0;
    font: inherit;
    color: inherit;
  }

  /* ── Modale ───────────────────────────────────────────────────────── */
  #caisse-calculatrice .modal-content {
    max-width: 560px;
    padding: 0;
  }

  .calcx-recap {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    text-align: center;
    margin-bottom: 1rem;
  }
  .calcx-recap-label {
    font-size: 0.72rem;
    color: var(--muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.35rem;
  }
  .calcx-recap-total {
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--primary, #0B5E88);
    font-family: 'JetBrains Mono', monospace;
    line-height: 1.15;
  }
  .calcx-recap-usd {
    font-size: 0.85rem;
    color: var(--muted, #64748b);
    margin-top: 0.25rem;
  }

  .calcx-rate {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 0.55rem 0.75rem;
    font-size: 0.8rem;
    color: #856404;
    margin-bottom: 1rem;
    flex-wrap: wrap;
  }
  .calcx-rate button {
    border: 1px solid #ffc107;
    background: #fff;
    color: #856404;
    border-radius: 6px;
    padding: 0.2rem 0.55rem;
    font-size: 0.75rem;
    cursor: pointer;
    white-space: nowrap;
  }
  .calcx-rate button:hover { background: #fffbeb; }

  .calcx-field { margin-bottom: 0.9rem; }
  .calcx-field label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--muted, #64748b);
    margin-bottom: 4px;
  }
  .calcx-field input[type="number"] {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border, #e2e8f0);
    border-radius: 10px;
    font-size: 1.25rem;
    font-weight: 600;
    text-align: center;
    outline: none;
    background: var(--card, #fff);
    color: var(--text, #1a1a2e);
  }
  .calcx-field input[type="number"]:focus { border-color: var(--primary, #0B5E88); }
  .calcx-field input[type="number"]:disabled { background: #f1f5f9; color: #94a3b8; }

  .calcx-units {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.9rem;
  }
  .calcx-unit {
    flex: 1;
    padding: 0.5rem;
    border: 2px solid var(--border, #e2e8f0);
    border-radius: 8px;
    background: var(--card, #fff);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted, #64748b);
    cursor: pointer;
    transition: all 0.15s;
  }
  .calcx-unit.active {
    border-color: var(--primary, #0B5E88);
    color: var(--primary, #0B5E88);
    background: rgba(11, 94, 136, 0.08);
  }

  .calcx-result {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border, #e2e8f0);
    margin-bottom: 1rem;
  }
  .calcx-result-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.9rem;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
  }
  .calcx-result-row:last-child { border-bottom: 0; }
  .calcx-result-row > span:first-child { color: var(--muted, #64748b); }
  .calcx-result-row > span:last-child {
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    text-align: right;
  }
  .calcx-result-row.calcx-change { background: #e8f5e9; }
  .calcx-result-row.calcx-change > span { color: #2e7d32; font-weight: 800; }
  .calcx-result-row.calcx-change > span:last-child { font-size: 1.1rem; }
  .calcx-result-row.calcx-missing { background: #ffebee; }
  .calcx-result-row.calcx-missing > span { color: #c62828; font-weight: 800; }
  .calcx-result-row.calcx-missing > span:last-child { font-size: 1.1rem; }

  .calcx-empty {
    text-align: center;
    padding: 1.5rem 1rem;
    color: var(--muted, #64748b);
    font-size: 0.85rem;
  }
</style>

<div id="caisse-calculatrice" class="modal" role="dialog" aria-modal="true" aria-labelledby="calcx-title">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="calcx-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
          <rect x="4" y="2" width="16" height="20" rx="2"></rect>
          <line x1="8" y1="6" x2="16" y2="6"></line>
          <line x1="8" y1="10" x2="16" y2="10"></line>
          <line x1="8" y1="14" x2="12" y2="14"></line>
          <line x1="8" y1="18" x2="10" y2="18"></line>
        </svg>
        CALCULATRICE
      </h3>
      <button id="calcx-close" class="close-modal">&times;</button>
    </div>

    <div style="padding: 1.25rem;">

      <!-- Total à payer (le TTC du panier) -->
      <div class="calcx-recap">
        <div class="calcx-recap-label">Total à payer</div>
        <div class="calcx-recap-total" id="calcx-total">0.00 Fc</div>
        <div class="calcx-recap-usd" id="calcx-total-usd">—</div>
      </div>

      <!-- Taux DGI du jour -->
      <div class="calcx-rate">
        <span id="calcx-rate-text">Chargement du taux DGI…</span>
        <button type="button" id="calcx-rate-refresh" title="Recharger le taux officiel">Actualiser</button>
      </div>

      <!-- Montant reçu du client -->
      <div class="calcx-field">
        <label for="calcx-received">Montant donné par le client</label>
        <input type="number" id="calcx-received" value="0" min="0" step="0.01" placeholder="Ex: 10">
      </div>

      <div class="calcx-units">
        <button type="button" class="calcx-unit active" id="calcx-unit-usd" data-unit="usd">Dollars (USD)</button>
        <button type="button" class="calcx-unit" id="calcx-unit-fc" data-unit="fc">Francs (Fc)</button>
      </div>

      <!-- Résultat -->
      <div class="calcx-result" id="calcx-result">
        <div class="calcx-result-row">
          <span>Montant reçu</span>
          <span id="calcx-out-received">0.00 Fc</span>
        </div>
        <div class="calcx-result-row">
          <span>Total à payer</span>
          <span id="calcx-out-total">0.00 Fc</span>
        </div>
        <div class="calcx-result-row calcx-change" id="calcx-row-change">
          <span>MONNAIE À RENDRE</span>
          <span id="calcx-out-change">0.00 Fc</span>
        </div>
        <div class="calcx-result-row" id="calcx-row-change-usd" style="display: none;">
          <span>Soit en dollars</span>
          <span id="calcx-out-change-usd">0.00 USD</span>
        </div>
        <div class="calcx-result-row calcx-missing" id="calcx-row-missing" style="display: none;">
          <span>MONTANT INSUFFISANT — il manque</span>
          <span id="calcx-out-missing">0.00 Fc</span>
        </div>
      </div>

      <div class="calcx-empty" id="calcx-empty" style="display: none;">
        Le panier est vide : ajoutez des articles avant d'utiliser la calculatrice.
      </div>

      <div style="display: flex; gap: 0.75rem;">
        <button type="button" id="calcx-reset" class="btn btn-secondary" style="flex: 1; padding: 0.875rem;">Remettre à zéro</button>
        <button type="button" id="calcx-validate" class="btn btn-primary" style="flex: 2; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
          Encaisser ce montant
        </button>
      </div>
    </div>
  </div>
</div>
