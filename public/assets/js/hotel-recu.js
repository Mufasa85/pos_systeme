/**
 * Reçu de séjour hôtel — génération + impression (format A4).
 *
 * Ajouté 2026-10-30 : bouton « Imprimer le reçu » au check-in.
 *
 * ⚠️ STRUCTURE VOLONTAIREMENT DIFFÉRENTE DE LA FACTURE DGI
 * ────────────────────────────────────────────────────────────
 * Le reçu utilise SA PROPRE structure HTML (racine `.recu-hotel`) et son
 * PROPRE feuille de style A4. Il ne réutilise PAS :
 *   - le DOM `.receipt` / `.receipt-header` / `.receipt-table` de la caisse
 *     et de la facture hôtel (qui, elle, est transformée en « facture
 *     normalisée » par paper-type.js::transformReceiptToInvoice()),
 *   - le bloc « Éléments de sécurité de la facture normalisée »
 *     (CODE DEF/DGI, DEF NID, DEF Compteurs, QR code DGI, ISF),
 *   - le numéro de facture officiel.
 *
 * Conséquence : le reçu ne peut PAS être confondu avec une facture DGI.
 * C'est un document de suivi de séjour, non fiscal : il porte la référence
 * « SEJOUR n°X », n'affiche aucun bloc de conformité fiscale et ne génère
 * AUCUNE écriture en base (pas de `ventes`, pas de `hotel_factures`).
 *
 * L'impression A4 est assurée par `_printA4Html()` (plus bas) : le HTML est
 * écrit dans une iframe avec nos propres styles @page. On ne passe donc pas
 * par paper-type.js, qui masquerait notre document au profit de la
 * facture normalisée DGI.
 *
 * Le reçu n'utilise PAS le modal global `#receipt-modal` : il s'imprime
 * directement (bouton → aperçu + dialogue d'impression du navigateur).
 * L'aperçu est visible au client, et le ticket n'est jamais stocké.
 *
 * Endpoints :
 *   GET /api/hotel/sejours/{id}/recu  → agrégat du séjour (nuits, extras, acomptes)
 *
 * Dépend de (chargés globalement par le footer) :
 *   - app.js → STORE_INFO, CURRENT_USER, posCart.numberToFrenchWords
 */
(function () {
  'use strict';

  const APP_URL = window.location.origin;

  // ── Helpers de formatage ────────────────────────────────────────────────
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fmtFc(n) {
    return parseFloat(n || 0).toLocaleString('fr-FR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' Fc';
  }

  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return s;
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
  }

  function fmtDateTime(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return s;
    return fmtDate(s) + ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function round2(n) {
    return Math.round((parseFloat(n) || 0) * 100) / 100;
  }

  function num(v) {
    return parseFloat(v) || 0;
  }

  async function jsonFetch(url) {
    const res = await fetch(APP_URL + url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    return { ok: res.ok, status: res.status, data };
  }

  // ── STORE_INFO ───────────────────────────────────────────────────────────
  // app.js remplit STORE_INFO via loadStoreInfo() au DOMContentLoaded.
  let storeInfoPromise = null;
  function ensureStoreInfo() {
    if (typeof loadStoreInfo !== 'function') return Promise.resolve();
    if (!storeInfoPromise) {
      storeInfoPromise = Promise.resolve(loadStoreInfo()).catch(function () { /* non bloquant */ });
    }
    return storeInfoPromise;
  }

  // ── Libellés ────────────────────────────────────────────────────────────
  var MODE_PAIEMENT = {
    cash: 'Espèces',
    mobile_money: 'Mobile Money',
    carte: 'Carte bancaire',
    autre: 'Autre'
  };

  function modePaiementLabel(mode) {
    return MODE_PAIEMENT[mode] || mode || '';
  }
  // ── Calculs (miroir strict de hotel-facturation.js) ──────────────────────
  // Régime B hôtelier : la TVA n'est pas collectée sur le prix, mais la
  // ligne facturée au client = prix × qté + TS + TVA_sur_TS (cf. app.js
  // posCart.calculateItemTTC). On reproduit cette formule à l'identique
  // pour que le reçu tombe au centime près sur la facture émise ensuite.

  /**
   * TS unitaire d'un produit (miroir posCart.calculateSpecificTaxUnit).
   */
  function specificTaxUnit(price, tsType, tsValueRaw) {
    var type = tsType || '%';
    var value = parseFloat(tsValueRaw) || 0;
    if (value <= 0) return 0;
    return type === 'CDF' ? value : price * (value / 100);
  }

  /**
   * Totaux d'un produit (miroir posCart.calculateItemTTC).
   */
  function computeProductTTC(p) {
    p = p || {};
    var price = num(p.prix_unitaire);
    var qty = num(p.quantite) || 1;
    var taxRate = num(p.tax_rate);
    var tsUnit = specificTaxUnit(price, p.taxe_specifique_type, p.taxe_specifique_value);
    var vatOnPriceUnit = price * (taxRate / 100);
    var vatOnTsUnit = tsUnit * (taxRate / 100);
    var lineHT = (price * qty) + tsUnit + vatOnTsUnit; // TTC facturé client (régime B)
    return {
      quantite: qty,
      prix_unitaire: price,
      tax_rate: taxRate,
      tax_etiquette: p.taxe_etiquette || 'B',
      prod_service: p.prod_service || '',
      remise_type: p.remise_type || '%',
      remise_value: num(p.remise_value),
      ts_unitaire: tsUnit,
      line_ht: lineHT
    };
  }

  /**
   * Libellé TS brut (miroir posCart.getSpecificTaxLabel).
   */
  function tsLabel(rawValue, type) {
    var v = num(rawValue);
    if (v <= 0) return '';
    return (type === 'CDF')
      ? ' [TS] ' + fmtFc(v)
      : ' [TS] ' + v + '%';
  }

  /**
   * Libellé remise (miroir app.js ligne ~1483).
   */
  function remiseLabel(type, value) {
    var v = num(value);
    if (v <= 0) return '';
    return (type === 'CDF')
      ? ' − ' + v + ' Fc remise'
      : ' − ' + v + '% remise';
  }

  /**
   * Total TTC facturé au client (régime B) :
   *   total_avec_ts_et_tva (nuits) + Σ line_ht (extras)
   * Les extras manuels n'existent qu'au moment de la facturation (saisie
   * à la volée) : pour le reçu de check-in, ils sont vides.
   */
  function computeTotalTTC(data) {
    var n = data.nuits || {};
    var e = data.extras || {};
    var totalChambres = num(n.total_avec_ts_et_tva);
    var totalExtras = 0;
    (e.lignes || []).forEach(function (ligne) {
      (ligne.produits || []).forEach(function (p) {
        totalExtras += computeProductTTC(p).line_ht;
      });
    });
    return round2(totalChambres + totalExtras);
  }
  // ── Feuille de style A4 dédiée au reçu ──────────────────────────────────
  // Ensemble de règles propre au reçu. Volontairement SÉPARÉ des styles
  // `.receipt-*` (ticket POS) et `.invoice-classic` / `.inv-*` (facture DGI
  // normalisée) : aucun chevauchement, aucune confusion possible à l'écran
  // ni à l'impression.
  function buildA4Styles() {
    return `
      @page { size: A4 portrait; margin: 12mm 14mm; }
      * { box-sizing: border-box; margin: 0; padding: 0; }
      html, body {
        width: 100%;
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        font-size: 11px;
        line-height: 1.45;
        color: #1a1a2e;
        background: #fff;
        /* Marge à l'écran (aperçu dans l'iframe). En impression, c'est la
           règle @page qui prend le relais (cf. @media print plus bas). */
        padding: 12mm 14mm;
      }
      .recu-hotel { width: 100%; }

      /* ── Bandeau d'en-tête ── */
      .rcu-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 20px; border-bottom: 2px solid #0B5E88;
        padding-bottom: 8px; margin-bottom: 14px;
      }
      .rcu-head-left { flex: 1 1 auto; min-width: 0; }
      .rcu-logo { max-width: 130px; max-height: 64px; object-fit: contain; margin-bottom: 6px; }
      .rcu-shop-name {
        font-size: 17px; font-weight: 700; color: #0B5E88;
        text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px;
      }
      .rcu-shop-info { font-size: 9.5px; color: #555; line-height: 1.4; }
      .rcu-shop-info div { margin: 0; }
      .rcu-head-right { flex: 0 0 auto; text-align: right; }
      .rcu-doc-title {
        font-size: 19px; font-weight: 800; color: #0B5E88;
        text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
      }
      .rcu-doc-sub { font-size: 10px; color: #64748b; font-style: italic; }
      .rcu-ref {
        display: inline-block; margin-top: 6px; padding: 3px 9px;
        border: 1px solid #0B5E88; border-radius: 3px;
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        font-size: 12px; font-weight: 700; color: #0B5E88; letter-spacing: .5px;
      }

      /* ── Bandeau « document non fiscal » ── */
      .rcu-notice {
        border: 1px dashed #b45309; background: #fffbeb; color: #92400e;
        padding: 5px 9px; border-radius: 4px; font-size: 9.5px;
        margin-bottom: 12px; text-align: center;
      }

      /* ── Bloc informations séjour / client ── */
      .rcu-info {
        display: grid; grid-template-columns: 1fr 1fr; gap: 0 22px; margin-bottom: 14px;
      }
      .rcu-info-col { border: 1px solid #e2e8f0; border-radius: 5px; padding: 9px 11px; }
      .rcu-info-title {
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .8px; color: #0B5E88;
        border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 6px;
      }
      .rcu-kv { display: flex; justify-content: space-between; gap: 12px; padding: 2px 0; font-size: 10px; }
      .rcu-kv > span:first-child { color: #64748b; white-space: nowrap; }
      .rcu-kv > span:last-child { font-weight: 600; text-align: right; word-break: break-word; }
      /* ── Tableaux ── */
      .rcu-section {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .6px; color: #0B5E88;
        border-bottom: 1px solid #0B5E88; padding-bottom: 3px; margin: 14px 0 6px;
      }
      .rcu-table { width: 100%; border-collapse: collapse; font-size: 10px; }
      .rcu-table thead th {
        background: #eef4f8; text-align: left; padding: 5px 6px;
        border-bottom: 1px solid #cbd5e1; font-size: 9px;
        text-transform: uppercase; letter-spacing: .4px; color: #334155;
      }
      .rcu-table thead th.r { text-align: right; }
      .rcu-table tbody td { padding: 4px 6px; border-bottom: 1px solid #eef2f6; vertical-align: top; }
      .rcu-table tbody td.r { text-align: right; white-space: nowrap; }
      .rcu-table tbody tr.alt { background: #fafbfc; }
      .rcu-line-label { font-weight: 600; color: #1a1a2e; }
      .rcu-line-sub { font-size: 9px; color: #64748b; font-style: italic; }
      .rcu-badge {
        display: inline-block; font-size: 8px; border: 1px solid #94a3b8;
        border-radius: 2px; padding: 0 3px; margin-left: 4px;
        color: #475569; font-weight: 700; vertical-align: 1px;
      }

      /* ── Totaux ── */
      .rcu-totals { width: 46%; margin-left: auto; margin-top: 10px; }
      .rcu-tot-row {
        display: flex; justify-content: space-between; gap: 12px;
        padding: 3px 0; font-size: 10.5px;
      }
      .rcu-tot-row span:last-child { font-weight: 600; white-space: nowrap; }
      .rcu-tot-row.sub { color: #475569; }
      .rcu-tot-row.grand {
        border-top: 2px solid #0B5E88; border-bottom: 2px solid #0B5E88;
        margin-top: 5px; padding: 6px 0;
        font-size: 13.5px; font-weight: 800; color: #0B5E88;
      }
      .rcu-tot-row.balance {
        border-top: 1px solid #cbd5e1; margin-top: 5px; padding-top: 6px;
        font-size: 12px; font-weight: 700;
      }
      .rcu-tot-row.balance span:last-child { color: #b91c1c; font-weight: 800; }

      /* ── Montant en lettres ── */
      .rcu-words {
        margin-top: 10px; padding: 7px 9px; background: #f8fafc;
        border-left: 3px solid #0B5E88; font-size: 9.5px;
        font-style: italic; color: #334155;
      }

      /* ── Pied de page ── */
      .rcu-foot {
        margin-top: 18px; padding-top: 8px; border-top: 1px solid #cbd5e1;
        display: flex; justify-content: space-between; align-items: flex-end; gap: 20px;
      }
      .rcu-sign { text-align: center; min-width: 190px; }
      .rcu-sign-line { border-bottom: 1px solid #94a3b8; height: 42px; margin-bottom: 4px; }
      .rcu-sign-label { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
      .rcu-foot-left { font-size: 8.5px; color: #94a3b8; flex: 1 1 auto; }
      .rcu-foot-left div { margin: 1px 0; }

      @media print {
        /* En impression, les marges sont gérées par @page : on retire le
           padding de l'écran pour ne pas décaler le contenu. */
        html, body { padding: 0; width: auto; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      }
    `;
  }
  // ── Construction du HTML du reçu (structure A4 dédiée) ──────────────────

  /**
   * En-tête : identité de l'hôtel à gauche, nature du document à droite.
   */
  function buildHeader(data) {
    var s = data.sejour || {};
    var ref = data.recu_ref || ('SEJOUR n°' + (s.id || ''));
    var storeName = (typeof STORE_INFO !== 'undefined' && STORE_INFO.name) ? STORE_INFO.name : '';
    var logo = (typeof STORE_INFO !== 'undefined' && STORE_INFO.logo) ? STORE_INFO.logo : '';

    var info = '';
    if (typeof STORE_INFO !== 'undefined') {
      if (STORE_INFO.address) info += '<div>' + escapeHtml(STORE_INFO.address) + '</div>';
      if (STORE_INFO.phone)  info += '<div>Tél. ' + escapeHtml(STORE_INFO.phone) + '</div>';
      if (STORE_INFO.email)  info += '<div>' + escapeHtml(STORE_INFO.email) + '</div>';
      if (STORE_INFO.ice)    info += '<div>ID Nat (ICE) : ' + escapeHtml(STORE_INFO.ice) + '</div>';
      if (STORE_INFO.isf)    info += '<div>Impôt : ' + escapeHtml(STORE_INFO.isf) + '</div>';
    }

    return '<div class="rcu-head">'
      + '<div class="rcu-head-left">'
      + (logo ? '<img src="' + escapeHtml(logo) + '" class="rcu-logo" alt="Logo">' : '')
      + '<div class="rcu-shop-name">' + escapeHtml(storeName) + '</div>'
      + '<div class="rcu-shop-info">' + info + '</div>'
      + '</div>'
      + '<div class="rcu-head-right">'
      + '<div class="rcu-doc-title">Reçu de Séjour</div>'
      + '<div class="rcu-doc-sub">Document de suivi — non fiscal</div>'
      + '<div class="rcu-ref">' + escapeHtml(ref) + '</div>'
      + '</div>'
      + '</div>';
  }

  /**
   * Bandeau d'avertissement : distingue explicitement le reçu de la facture.
   */
  function buildNotice(data) {
    var date = data.emitted_at ? fmtDateTime(data.emitted_at) : fmtDateTime(new Date().toISOString());
    return '<div class="rcu-notice">'
      + 'Ce document est un <strong>reçu de séjour</strong> non fiscal. '
      + 'Il ne vaut pas facture et ne comporte pas d\'éléments de conformité DGI. '
      + 'Émis le ' + escapeHtml(date) + '.'
      + '</div>';
  }

  /**
   * Deux colonnes : identité du client / détails du séjour.
   */
  function buildInfoBlocks(data) {
    var s = data.sejour || {};
    var g = s.guest || {};
    var c = s.chambre || {};
    var n = data.nuits || {};

    var kv = function (label, value) {
      if (value === '' || value === null || value === undefined) return '';
      return '<div class="rcu-kv"><span>' + label + '</span><span>' + escapeHtml(value) + '</span></div>';
    };

    var guestCol = '<div class="rcu-info-col">'
      + '<div class="rcu-info-title">Client</div>'
      + kv('Nom', ((g.prenom || '') + ' ' + (g.nom || '')).trim())
      + kv('Téléphone', g.telephone || '')
      + kv('Code client', (s.client && s.client.code_client) || '')
      + '</div>';

    var stayCol = '<div class="rcu-info-col">'
      + '<div class="rcu-info-title">Séjour</div>'
      + kv('Chambre', 'N° ' + (c.numero || '') + (c.type ? ' (' + c.type + ')' : ''))
      + kv('Arrivée', fmtDateTime(s.date_arrivee))
      + kv('Départ prévu', fmtDate(s.date_depart_prevue))
      + kv('Nuitées', String(n.nb_nuits || 0))
      + kv('Prix / nuit', fmtFc(n.total_ht && n.nb_nuits ? n.total_ht / n.nb_nuits : 0))
      + '</div>';

    return '<div class="rcu-info">' + guestCol + stayCol + '</div>';
  }
  /**
   * Tableau des nuitées.
   */
  function buildNightsTable(data) {
    var n = data.nuits || {};
    var s = data.sejour || {};
    var c = s.chambre || {};
    var lignes = n.lignes || [];
    if (!lignes.length) return '';

    var taxLabel = n.tax_etiquette || 'B';
    var html = '<div class="rcu-section">Hébergement — nuitées</div>'
      + '<table class="rcu-table"><thead><tr>'
      + '<th>Date</th><th>Chambre</th><th class="r">Prix</th>'
      + '<th class="r">Taxe spéc.</th><th class="r">Total TTC</th>'
      + '</tr></thead><tbody>';

    lignes.forEach(function (l, i) {
      var prixNuit = num(l.prix);
      var tsUnit = num(l.ts_unitaire);
      var taxRate = num(n.tax_rate);
      var lineHT = prixNuit + tsUnit + (tsUnit * taxRate / 100);
      var tsTxt = tsUnit > 0 ? escapeHtml(tsLabel(n.taxe_specifique_value, n.taxe_specifique_type)) : '—';
      html += '<tr' + (i % 2 ? ' class="alt"' : '') + '>'
        + '<td>' + escapeHtml(fmtDate(l.date)) + '</td>'
        + '<td class="rcu-line-label">N° ' + escapeHtml(String(c.numero || '')) + '<span class="rcu-badge">' + escapeHtml(taxLabel) + '</span></td>'
        + '<td class="r">' + escapeHtml(fmtFc(prixNuit)) + '</td>'
        + '<td class="r">' + tsTxt + '</td>'
        + '<td class="r"><strong>' + escapeHtml(fmtFc(lineHT)) + '</strong></td>'
        + '</tr>';
    });

    return html + '</tbody></table>';
  }

  /**
   * Tableau des extras consommés.
   */
  function buildExtrasTable(data) {
    var e = data.extras || {};
    var lignes = e.lignes || [];
    if (!lignes.length) return '';

    var html = '<div class="rcu-section">Consommations &amp; extras</div>'
      + '<table class="rcu-table"><thead><tr>'
      + '<th>Article</th><th class="r">Qté</th>'
      + '<th class="r">P.U.</th><th class="r">Remise</th>'
      + '<th class="r">Taxe spéc.</th><th class="r">Total TTC</th>'
      + '</tr></thead><tbody>';

    var idx = 0;
    lignes.forEach(function (x) {
      var produits = x.produits || [];
      if (produits.length === 0) {
        var montant = num(x.montant);
        html += '<tr' + (idx++ % 2 ? ' class="alt"' : '') + '>'
          + '<td class="rcu-line-label">' + escapeHtml(x.description || 'Extra') + '<span class="rcu-badge">SER</span></td>'
          + '<td class="r">1</td>'
          + '<td class="r">' + escapeHtml(fmtFc(montant)) + '</td>'
          + '<td class="r">—</td><td class="r">—</td>'
          + '<td class="r"><strong>' + escapeHtml(fmtFc(montant)) + '</strong></td>'
          + '</tr>';
        return;
      }
      produits.forEach(function (p) {
        var calc = computeProductTTC(p);
        var badge = calc.prod_service ? '<span class="rcu-badge">' + escapeHtml(calc.prod_service) + '</span>' : '';
        var dLabel = remiseLabel(calc.remise_type, calc.remise_value);
        var sLabel = tsLabel(p.taxe_specifique_value, p.taxe_specifique_type);
        html += '<tr' + (idx++ % 2 ? ' class="alt"' : '') + '>'
          + '<td class="rcu-line-label">' + escapeHtml(p.nom || x.description || 'Produit') + badge
          + (dLabel ? '<div class="rcu-line-sub">' + escapeHtml(dLabel) + '</div>' : '')
          + (sLabel ? '<div class="rcu-line-sub">' + escapeHtml(sLabel) + '</div>' : '')
          + '</td>'
          + '<td class="r">' + escapeHtml(String(calc.quantite)) + '</td>'
          + '<td class="r">' + escapeHtml(fmtFc(calc.prix_unitaire)) + '</td>'
          + '<td class="r">' + (dLabel ? escapeHtml(dLabel) : '—') + '</td>'
          + '<td class="r">' + (sLabel ? escapeHtml(sLabel) : '—') + '</td>'
          + '<td class="r"><strong>' + escapeHtml(fmtFc(calc.line_ht)) + '</strong></td>'
          + '</tr>';
      });
    });

    return html + '</tbody></table>';
  }
  /**
   * Bloc totaux : sous-totaux, total TTC, acomptes, solde dû.
   */
  function buildTotals(data, totalTtc) {
    var n = data.nuits || {};
    var acompteTotal = num(data.acompte_total);
    var solde = round2(totalTtc - acompteTotal);
    var row = function (label, value, cls) {
      return '<div class="rcu-tot-row' + (cls ? ' ' + cls : '') + '">'
        + '<span>' + label + '</span><span>' + escapeHtml(value) + '</span></div>';
    };

    var html = '<div class="rcu-totals">';

    if (num(n.remise_pct) > 0) {
      html += row('Remise séjour (' + num(n.remise_pct) + ' %)', '− ' + fmtFc(n.remise_montant), 'sub');
    }
    html += row('Total TTC', fmtFc(totalTtc), 'grand');

    if (acompteTotal > 0) {
      html += row('Acompte versé', '− ' + fmtFc(acompteTotal), 'sub');
      html += row('Solde dû', fmtFc(solde), 'balance');
    }

    html += '</div>';

    // Montant en lettres (aligné sur le pattern de la caisse).
    var words = '';
    if (typeof posCart !== 'undefined' && posCart.numberToFrenchWords) {
      try { words = posCart.numberToFrenchWords(totalTtc); } catch (e) { words = ''; }
    }
    if (words) {
      html += '<div class="rcu-words">Arrêté à la somme de ' + escapeHtml(words)
        + ' congolais toutes taxes comprises.</div>';
    }

    return html;
  }

  /**
   * Pied de page : mentions + cadre de signature.
   */
  function buildFooter(data) {
    var s = data.sejour || {};
    var sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName)
      ? CURRENT_USER.fullName : (STORE_INFO.name || '');
    var agentCode = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode)
      ? CURRENT_USER.agentCode : '';
    var now = data.emitted_at ? fmtDateTime(data.emitted_at) : fmtDateTime(new Date().toISOString());

    return '<div class="rcu-foot">'
      + '<div class="rcu-foot-left">'
      + '<div>Reçu de séjour — document de suivi non fiscal, sans valeur de facture.</div>'
      + '<div>Séjour #' + escapeHtml(String(s.id || '')) + ' · Émis le ' + escapeHtml(now) + '</div>'
      + (sellerName ? '<div>Édité par : ' + escapeHtml(sellerName) + (agentCode ? ' (' + escapeHtml(agentCode) + ')' : '') + '</div>' : '')
      + '<div>---Powered By Osat---</div>'
      + '</div>'
      + '<div class="rcu-sign">'
      + '<div class="rcu-sign-line"></div>'
      + '<div class="rcu-sign-label">Signature du client</div>'
      + '</div>'
      + '</div>';
  }

  /**
   * Assemble le document complet (structure A4 dédiée).
   */
  function buildReceiptHtml(data) {
    var totalTtc = computeTotalTTC(data);
    return '<div class="recu-hotel">'
      + buildHeader(data)
      + buildNotice(data)
      + buildInfoBlocks(data)
      + buildNightsTable(data)
      + buildExtrasTable(data)
      + buildTotals(data, totalTtc)
      + buildFooter(data)
      + '</div>';
  }

  /**
   * Document HTML complet (prêt à être écrit dans l'iframe d'impression).
   */
  function buildFullDocument(data) {
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
      + '<title>Reçu de séjour #' + escapeHtml(String((data.sejour && data.sejour.id) || '')) + '</title>'
      + '<style>' + buildA4Styles() + '</style>'
      + '</head><body>'
      + buildReceiptHtml(data)
      + '</body></html>';
  }
  // ── Moteur d'impression A4 dédié ────────────────────────────────────────
  // On écrit le document dans une iframe hors écran puis on déclenche
  // window.print(). Contrairement à paper-type.js, on n'utilise NI la
  // structure `.receipt` NI la transformation en facture DGI : le reçu est
  // imprimé tel quel, avec ses propres styles A4.
  function printA4Html(fullHtml) {
    var oldFrame = document.getElementById('_recu-print-frame');
    if (oldFrame) oldFrame.remove();

    var iframe = document.createElement('iframe');
    iframe.id = '_recu-print-frame';
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:210mm;height:1px;border:none;overflow:hidden;';
    document.body.appendChild(iframe);

    var doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(fullHtml);
    doc.close();

    iframe.onload = function () {
      setTimeout(function () {
        try {
          if (iframe.contentWindow) {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
          }
        } catch (e) {
          console.error('[hotel-recu] Erreur impression A4:', e);
        }
        setTimeout(function () { if (iframe.parentNode) iframe.remove(); }, 2000);
      }, 350);
    };
  }

  /**
   * Point d'entrée public : charge le reçu d'un séjour et l'imprime en A4.
   *
   * @param {number}   sejourId  Id du séjour à imprimer.
   * @param {Function} onError   Callback optionnel (message d'erreur).
   */
  async function printRecu(sejourId, onError) {
    var id = parseInt(sejourId, 10);
    if (!id || id <= 0) {
      if (typeof onError === 'function') onError('Séjour invalide.');
      return;
    }
    // Les deux appels sont lancés en parallèle : l'un charge la note du
    // séjour, l'autre attend les infos du magasin pour l'en-tête du reçu.
    var res = await jsonFetch('/api/hotel/sejours/' + id + '/recu');
    await ensureStoreInfo();
    if (!res.ok || !res.data || !res.data.success) {
      var msg = (res.data && res.data.message) || 'Erreur lors de la génération du reçu.';
      if (typeof onError === 'function') onError(msg);
      else alert(msg);
      return;
    }
    printA4Html(buildFullDocument(res.data.data));
  }

  // ── Exposition globale ──────────────────────────────────────────────────
  window.HotelRecu = {
    print: printRecu,
    buildHtml: buildReceiptHtml,
    buildFullDocument: buildFullDocument,
    buildA4Styles: buildA4Styles
  };
})();
