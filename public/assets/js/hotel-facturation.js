/**
 * Page Facturation hôtel (/hotel/facturation)
 *
 * Pattern aligné sur /caisse (app.js : posCart.showPreview / confirmSale)
 * et /recharges (recharges.js : billPayment.showPreviewFinal / generateTicket) :
 *
 *  1. À l'ouverture, on lit ?sejour_id=X dans l'URL.
 *     - Si présent → on masque le picker et on appelle loadSejour()
 *     - Si absent  → on affiche le picker pour choisir un séjour
 *  2. loadSejour() remplit les blocs (séjour, nuits, extras, acomptes, totaux)
 *     et autorise l'ajout d'extras manuels.
 *  3. Bouton "Émettre la facture" → modal Preview (#hotel-ft-preview-modal)
 *     avec récap + multi-paiements, comme dans /caisse et /recharges.
 *  4. Click sur "Valider" → POST /api/dgi (validation DGI)
 *     puis POST /api/hotel/factures (persist + dgi_data)
 *     → modal Receipt final (#hotel-ft-receipt-modal) avec QR code DGI.
 *
 *  Les helpers `generateDGIQRCode`, `numberToFrenchWords`, `getPaymentTypeLabel`
 *  et `_printReceiptContent` sont factorisés dans /assets/js/app.js et utilisés
 *  tels quels (déjà chargés via le footer global : <script src="/assets/js/app.js">).
 */
(function () {
  'use strict';

  const APP_URL = window.location.origin;
  const $ = (id) => document.getElementById(id);
  const pageAlert = $('hotel-ft-alert-page');
  const picker = $('hotel-ft-sejours-picker');
  const bloc = $('hotel-ft-bloc');
  const search = $('hotel-ft-sejour-search');
  const results = $('hotel-ft-sejour-results');

  let currentSejourId = null;
  let previewData = null;
  let extrasManuels = []; // [{ description, montant }]
  let paiements = [];     // [{ mode, montant }]

  // ── Helpers ───────────────────────────────────────────────────────────
  function showAlert(msg, type) {
    if (!pageAlert) return;
    if (!msg) { pageAlert.style.display = 'none'; return; }
    pageAlert.className = 'hotel-ft-banner ' + (type === 'error' ? 'warn' : '');
    pageAlert.textContent = msg;
    pageAlert.style.display = '';
  }

  // ── Miroir EXACT de posCart.calculateSpecificTaxUnit (public/assets/js/app.js) ──
  // BUG FIX 2026-10-20 : cette fonction standalone a été supprimée car elle
  // n'est plus utilisée — `computeProductTTC` (introduite juste en dessous)
  // implémente désormais la même formule en interne pour chaque produit.
  // On garde l'algo documenté ici pour référence historique :
  //
  //     calculateSpecificTaxUnit(item) {
  //         const price = parseFloat(item.prix) || 0;
  //         const value = parseFloat(item.taxe_specifique_value) || 0;
  //         return item.taxe_specifique_type === 'CDF' ? value : price * (value / 100);
  //     }

  // ── Lit le taux de TVA B (régime hôtelier) depuis le previewData ──
  // Plus besoin de fetch /api/taxes : la valeur est portée par la chambre
  // (chambre.taxe_id → table taxes → taux) et est ramenée par le
  // contrôleur dans previewData.sejour.chambre.taxe_taux. Cela évite
  // un round-trip réseau et garantit la cohérence avec le backend.
  // Renvoie 0 si pas de taxe (NULL) ou si le previewData n'est pas chargé.
  function getTaxBValueFromPreview() {
    if (!previewData || !previewData.sejour || !previewData.sejour.chambre) return 0;
    var ch = previewData.sejour.chambre;
    // Plusieurs noms possibles selon comment le contrôleur renvoie la
    // donnée jointe (taxe_taux direct, ou via les alias chambre_taxe_*).
    var raw = (ch.taxe_taux !== undefined && ch.taxe_taux !== null)
      ? ch.taxe_taux
      : (ch.chambre_taxe_taux !== undefined && ch.chambre_taxe_taux !== null)
        ? ch.chambre_taxe_taux
        : 0;
    var n = parseFloat(raw);
    return isNaN(n) ? 0 : n;
  }

  // ── Calcul TS / TVA par produit — MIRROIR EXACT de posCart.calculateItemTTC ──
  // Réplique la formule de `public/assets/js/app.js` lignes 836-862 :
  //
  //   calculateSpecificTaxUnit(item) {
  //       const price = parseFloat(item.prix) || 0;
  //       const value = parseFloat(item.taxe_specifique_value) || 0;
  //       return item.taxe_specifique_type === 'CDF' ? value : price * (value / 100);
  //   }
  //
  //   calculateItemTTC(item, quantityOverride = null) {
  //       const price    = parseFloat(item.prix) || 0;
  //       const quantity = quantityOverride !== null ? quantityOverride : (parseFloat(item.quantite) || 0);
  //       const taxRate  = parseFloat(item.tax_rate) || 0;
  //       const specificTaxUnit       = this.calculateSpecificTaxUnit(item);
  //       const vatOnPriceUnit        = price * (taxRate / 100);
  //       const vatOnSpecificTaxUnit  = specificTaxUnit * (taxRate / 100);
  //       const lineHT  = price * quantity + specificTaxUnit + vatOnSpecificTaxUnit;
  //       const unitTTC = price + specificTaxUnit + vatOnPriceUnit + vatOnSpecificTaxUnit;
  //       return {
  //           priceHT, quantity, specificTaxUnit, specificTaxTotal,
  //           vatOnPriceTotal: vatOnPriceUnit * quantity,
  //           vatOnSpecificTaxTotal: vatOnSpecificTaxUnit * quantity,
  //           taxTotal: (vatOnPriceUnit + vatOnSpecificTaxUnit) * quantity,
  //           lineHT, lineTTC: lineHT, unitTTC
  //       };
  //   }
  //
  // NOTE IMPORTANTE — convention BUG FIX 2026-10-20 :
  // Avant, ce fichier calculait prixHtNet = price - remise puis appliquait
  // TS et TVA sur ce prix net. Ça NE CORRESPONDAIT PAS au pattern app.js
  // (qui ne soustrait PAS la remise dans lineHT — la remise est gérée à
  // part via remise_value envoyé à la DGI). De plus, la TS était appliquée
  // sur prixHtNet au lieu de price, ce qui sous-évaluait le TTC.
  //
  // On reproduit maintenant fidèlement la formule app.js :
  //   1. specificTaxUnit = (type === 'CDF') ? value : price * (value / 100)
  //      → TS calculée sur le PRIX BRUT (price), pas sur le prix net.
  //   2. vatOnPriceUnit       = price * (taxRate / 100)
  //   3. vatOnSpecificTaxUnit = specificTaxUnit * (taxRate / 100)
  //   4. lineHT  = price * qty + specificTaxUnit + vatOnSpecificTaxUnit
  //                (la remise n'est PAS incluse ici ; alignée app.js)
  //   5. lineTTC = lineHT + vatOnPriceUnit * qty
  //
  // La remise est EXPOSÉE séparément (`remise_montant`, `remise_value`) pour
  // info / DGI, mais ne réduit PAS le lineHT côté front — c'est cohérent
  // avec ce que fait app.js (cf. validateWithDGI() qui envoie remise_value
  // tel quel sans impacter lineHT).
  function computeProductTTC(product) {
    var p         = product || {};
    var price     = parseFloat(p.prix_unitaire) || 0;
    var qty       = parseFloat(p.quantite) || 1;
    var taxRate   = parseFloat(p.tax_rate) || 0;
    var rType     = p.remise_type || '%';
    var rValueRaw = parseFloat(p.remise_value) || 0;
    var tsType    = p.taxe_specifique_type || '%';
    var tsValueRaw= parseFloat(p.taxe_specifique_value) || 0;

    // 1) TS unitaire — miroir exact de posCart.calculateSpecificTaxUnit
    var specificTaxUnit = (tsType === 'CDF')
      ? tsValueRaw
      : price * (tsValueRaw / 100);

    // 2) TVA unitaire sur prix + sur TS — miroir exact de posCart.calculateItemTTC
    var vatOnPriceUnit       = price * (taxRate / 100);
    var vatOnSpecificTaxUnit = specificTaxUnit * (taxRate / 100);

    // 3) lineHT (= HT cumulé = prix × qty + TS + TVA_sur_TS)
    var lineHT = (price * qty) + specificTaxUnit + vatOnSpecificTaxUnit;

    // 4) lineTTC (= lineHT + TVA_sur_prix × qty)
    var lineTTC = lineHT + vatOnPriceUnit * qty;

    // Remise exposée pour information (montant CDF après conversion si %).
    // NE MODIFIE PAS lineHT ni lineTTC — alignement app.js (cf. note ci-dessus).
    var remiseMontant = (rType === 'CDF') ? rValueRaw : (price * rValueRaw / 100);

    return {
      // Données sources normalisées
      price_unitaire:     price,
      quantite:           qty,
      tax_rate:           taxRate,
      tax_etiquette:      p.taxe_etiquette || 'B',
      // Résultats unitaires (par unité)
      specific_tax_unit:  specificTaxUnit,
      vat_on_price_unit:  vatOnPriceUnit,
      vat_on_ts_unit:     vatOnSpecificTaxUnit,
      // Résultats ligne (cumulés avec quantité)
      line_ht:            lineHT,
      line_ts:            specificTaxUnit * qty,
      line_vat_price:     vatOnPriceUnit * qty,
      line_vat_ts:        vatOnSpecificTaxUnit * qty,
      line_vat_total:     (vatOnPriceUnit + vatOnSpecificTaxUnit) * qty,
      line_ttc:           lineTTC,
      // Remise (info uniquement — non déduite des line_* ci-dessus)
      remise_type:        rType,
      remise_value_raw:   rValueRaw,
      remise_montant:     remiseMontant,
    };
  }

  // Wrapper rétro-compatible — certains call-sites historiques utilisent
  // encore `computeItemTTC`. On garde l'API exacte mais on la fait pointer
  // sur le nouveau calculateur. (Le nom `computeItemTTC` reste pour ne pas
  // casser les call-sites dans renderBloc / buildHotelReceiptTotals.)
  function computeItemTTC(item) {
    return computeProductTTC(item);
  }

  // Agrège les totaux pour TOUS les extras d'un séjour (toutes lignes confondues).
  // Renvoie : { total_ht, total_ts, total_tva, total_ttc } + détail par taux
  // et par étiquette de TVA.
  //
  // BUG FIX 2026-10-20 (round 2) — calcul TTC adapté au régime B hôtelier :
  // En hôtellerie (régime B), la TVA sur prix n'est PAS collectée auprès du
  // client : il paie uniquement HT + TS + TVA_sur_TS. C'est exactement ce que
  // `line_ht` représente dans le pattern app.js (cf. posCart.calculateItemTTC
  // ligne 849). Le `line_ttc` (qui inclut en plus la TVA_sur_prix × qty)
  // correspond au TTC théorique avec TVA collectée — utile pour la ventilation
  // DGI mais pas pour le montant facturé en régime B.
  //
  // Exemple : prix 2000, qty 1, TVA 5%, TS 10% (en %), remise 40%
  //   specificTaxUnit = 200 (TS)
  //   vatOnPriceUnit  = 100 (TVA collectée — non facturée en B)
  //   vatOnTSUnit     = 10  (TVA sur TS)
  //   lineHT          = 2000 + 200 + 10 = 2210   ← TTC facturé au client
  //   lineTTC         = 2210 + 100       = 2310   ← TTC théorique DGI
  //
  // On expose ici :
  //   total_ht  = sum(line_ht)         (= HT + TS + TVA_sur_TS — régime B)
  //   total_ttc = sum(line_ht)         (= idem — TTC facturé au client)
  //   total_ts  = sum(line_ts)         (info)
  //   total_tva = sum(line_vat_total)  (info : TVA_sur_prix + TVA_sur_TS)
  //   by_rate / by_etiq : idem avec cumul par catégorie de TVA
  function computeExtrasTotals(extrasLignes) {
    var lines = extrasLignes || [];
    var totalHT = 0, totalTS = 0, totalVAT = 0, totalTTC = 0;
    var byRate = {}; // { '16': { ht, ts, vat, ttc }, ... }         (par taux de TVA)
    var byEtiq = {}; // { 'B'|'C'|... : { ht, ts, vat, ttc }, ... } (par étiquette DGI)

    lines.forEach(function (ligne) {
      (ligne.produits || []).forEach(function (p) {
        var calc = computeProductTTC(p);
        totalHT  += calc.line_ht;
        totalTS  += calc.line_ts;
        totalVAT += calc.line_vat_total;
        // TTC facturé client en régime B = line_ht (= HT + TS + TVA_sur_TS).
        // Note : si on voulait le TTC théorique avec TVA collectée, ce serait
        // calc.line_ttc (= lineHT + TVA_sur_prix × qty).
        totalTTC += calc.line_ht;
        // Bucket par taux de TVA (info UI)
        var r = String(calc.tax_rate || 0);
        if (!byRate[r]) byRate[r] = { ht: 0, ts: 0, vat: 0, ttc: 0 };
        byRate[r].ht  += calc.line_ht;
        byRate[r].ts  += calc.line_ts;
        byRate[r].vat += calc.line_vat_total;
        byRate[r].ttc += calc.line_ht;
        // Bucket par étiquette DGI (A/B/C/...) — utile pour le détail
        // tax breakdown envoyé par la DGI dans buildHotelReceiptTaxBreakdown.
        var e = String(calc.tax_etiquette || 'B');
        if (!byEtiq[e]) byEtiq[e] = { ht: 0, ts: 0, vat: 0, ttc: 0 };
        byEtiq[e].ht  += calc.line_ht;
        byEtiq[e].ts  += calc.line_ts;
        byEtiq[e].vat += calc.line_vat_total;
        byEtiq[e].ttc += calc.line_ht;
      });
    });
    return {
      total_ht:  totalHT,
      total_ts:  totalTS,
      total_tva: totalVAT,
      total_ttc: totalTTC,
      by_rate:   byRate,
      by_etiq:   byEtiq,
    };
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtFc(n) {
    return parseFloat(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Fc';
  }
  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return s;
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
  }
  async function jsonFetch(url, opts) {
    const res = await fetch(APP_URL + url, Object.assign(
      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
      opts || {}
    ));
    let data = null;
    let parseError = null;
    try {
      data = await res.json();
    } catch (e) {
      // Réponse reçue mais non-JSON : c'est soit une page HTML d'erreur PHP
      // (PDOException non interceptée, fatal error, etc.), soit un 502/504
      // du proxy. On capture l'info pour ne plus afficher "Erreur 200"
      // quand le vrai problème est une exception backend.
      parseError = e && e.message ? e.message : 'Réponse non-JSON';
    }
    // Si le serveur a renvoyé un body non-JSON (ex: page HTML d'erreur fatale
    // PHP qui passe parfois avec status 200 sous WAMP/XAMPP), on force ok=false
    // pour que les callers affichent un message utile au lieu de "Erreur 200".
    const ok = res.ok && data !== null && data && typeof data === 'object' && !parseError;
    return { ok, status: res.status, data, parseError };
  }

  // Mapping modes de paiement UI hôtel → nomenclature DGI
  const PAY_MODE_DGI = {
    cash: 'ESPECES',
    mobile_money: 'MOBILEMONEY',
    carte: 'CARTEBANCAIRE',
    autre: 'AUTRE',
  };
  // Numéro de facture envoyé à la DGI : on garde la nomenclature existante
  // (FV = Facture de Vente). Le DGI ne connaît pas 'HB' ; le backend transcrit
  // 'HB' dans ventes.invoice_type (cf. HotelFactureController::store()).
  const DGI_INVOICE_TYPE = 'FV';

  function paymentLabel(mode) {
    if (typeof getPaymentTypeLabel === 'function') {
      return getPaymentTypeLabel(PAY_MODE_DGI[mode] || 'AUTRE');
    }
    const m = { cash: 'Espèces', mobile_money: 'Mobile Money', carte: 'Carte', autre: 'Autre' };
    return m[mode] || mode;
  }

  // ── Picker de séjour ─────────────────────────────────────────────────
  let sTimer = null;
  if (search) {
    search.addEventListener('input', () => {
      clearTimeout(sTimer);
      const q = search.value.trim();
      if (q.length < 1) { results.innerHTML = ''; return; }
      sTimer = setTimeout(() => doSejourSearch(q), 220);
    });
  }
  async function doSejourSearch(q) {
    results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Recherche…</div>';
    const r = await jsonFetch('/api/hotel/reservations?q=' + encodeURIComponent(q) + '&limit=20');
    if (!r.ok || !r.data) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:#991b1b;">Erreur.</div>';
      return;
    }
    const list = (r.data.data || []).filter(s => s.statut === 'en_cours' || s.statut === 'reserve');
    if (list.length === 0) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Aucun séjour actif.</div>';
      return;
    }
    results.innerHTML = list.map(s => `
      <div class="hotel-ft-sejour-row" data-id="${s.id}" style="padding:.55rem .85rem;border-bottom:1px solid var(--border);cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:500;">${escapeHtml(s.guest_nom || '')} ${escapeHtml(s.guest_prenom || '')}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">Chambre ${escapeHtml(s.chambre_numero)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
        </div>
        <span class="badge">${escapeHtml(s.statut)}</span>
      </div>`).join('');
    results.querySelectorAll('.hotel-ft-sejour-row').forEach(row => {
      row.addEventListener('click', () => {
        const id = parseInt(row.dataset.id, 10);
        if (id > 0) {
          const u = new URL(window.location.href);
          u.searchParams.set('sejour_id', String(id));
          window.history.replaceState({}, '', u.toString());
          loadSejour(id);
        }
      });
    });
  }

  // ── Chargement du séjour + preview ───────────────────────────────────
  async function loadSejour(sejourId) {
    currentSejourId = sejourId;
    if (picker) picker.style.display = 'none';
    if (bloc) bloc.style.display = '';
    if (bloc) bloc.innerHTML = '<div class="hotel-ft-empty">Chargement…</div>';
    extrasManuels = [];
    paiements = [];
    showAlert(null);

    const r = await jsonFetch('/api/hotel/factures/preview?sejour_id=' + sejourId);
    if (!r.ok || !r.data || !r.data.success) {
      // Ordre de préférence du message :
      //  1. message métier du backend (r.data.message)
      //  2. indication de réponse non-JSON (typiquement: page HTML d'erreur
      //     fatale PHP renvoyée avec status 200 — ne plus écrire "Erreur 200")
      //  3. statut HTTP seul en fallback
      let msg;
      if (r.data && r.data.message) {
        msg = r.data.message;
      } else if (r.parseError) {
        msg = 'Réponse serveur invalide (non-JSON). Vérifiez les logs PHP — probable exception PDO/fatale côté backend.';
      } else {
        msg = 'Erreur ' + r.status;
      }
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">' + escapeHtml(msg) + '</div>';
      return;
    }
    previewData = r.data.data;
    if (!previewData) {
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Réponse serveur : donnée de prévisualisation vide.</div>';
      return;
    }
    try {
      renderBloc();
    } catch (err) {
      console.error('[hotel-ft] renderBloc crashed', err);
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Erreur de rendu : ' + escapeHtml(err && err.message || String(err)) + '</div>';
    }
  }

  // ── renderBloc : récap + formulaire ──────────────────────────────────
  function renderBloc() {
    if (!previewData) return;
    const s = previewData.sejour;
    const n = previewData.nuits;
    const e = previewData.extras;
    const ex = previewData.existing_facture;
    const alreadyFactured = ex && ex.statut !== 'annulee';

    const nightsRows = (n.lignes || []).map(l => '<tr><td>' + fmtDate(l.date) + '</td><td class="num">' + fmtFc(l.prix) + '</td></tr>').join('');
    const remiseRow = n.remise_montant > 0
      ? '<tr><td>Remise séjour (' + n.remise_pct + '%)</td><td class="num" style="color:#991b1b;">− ' + fmtFc(n.remise_montant) + '</td></tr>'
      : '';

    // Calcul détaillé des extras (qte × prix_unitaire × TS × TVA).
    // BUG FIX 2026-10-20 (round 3) : on aligne l'affichage du récap de page
    // sur le pattern de la caisse POS :
    //   <nom> <tax_etiquette> [prod_service] - <remise> [TS] <valeur TS>
    //   <qty> × <prix>                                      <total TTC>
    const extrasTotals = computeExtrasTotals(e.lignes);
    const extrasDetailRows = [];
    (e.lignes || []).forEach(function (x) {
      var dateCell = '<td>' + fmtDate(x.date) + '</td>';
      var descCell = '<td>' + escapeHtml(x.description) + '</td>';
      if (x.produits && x.produits.length > 0) {
        var prodsHtml = '<table style="width:100%;font-size:.75rem;margin-top:.25rem;color:#475569;">';
        x.produits.forEach(function (p) {
          var calc = computeProductTTC(p);
          // Étiquette de taxe (ex: "B", "C")
          var taxLabel = calc.tax_etiquette || (calc.tax_rate > 0 ? 'TVA ' + calc.tax_rate + '%' : '');
          // prod_service (ex: 'BIE' ou 'SER')
          var prodServiceBadge = p.prod_service ? '[' + escapeHtml(p.prod_service) + ']' : '';
          // Libellé remise
          var remiseLabel = (p.remise_value && p.remise_value > 0)
            ? ' - ' + (p.remise_type === 'CDF'
              ? p.remise_value + ' Fc'
              : p.remise_value + '%') + ' remise'
            : '';
          // Libellé TS
          var tsLabel = (p.taxe_specifique_value && p.taxe_specifique_value > 0)
            ? ' [TS] ' + (p.taxe_specifique_type === 'CDF'
              ? p.taxe_specifique_value + ' Fc'
              : p.taxe_specifique_value + '%')
            : '';
          prodsHtml += '<tr>'
            + '<td style="padding:1px 4px 1px 16px;">'
            +   escapeHtml(p.nom || 'Produit')
            +   (taxLabel ? ' <span style="font-weight:600;">' + taxLabel + '</span>' : '')
            +   (prodServiceBadge ? ' <span style="font-weight:600;">' + prodServiceBadge + '</span>' : '')
            +   (remiseLabel ? ' <span style="color:#16a34a;">' + remiseLabel + '</span>' : '')
            +   (tsLabel ? ' <span style="color:#b45309;">' + tsLabel + '</span>' : '')
            + '</td>'
            + '<td style="padding:1px 4px;text-align:right;">'
            +   formatQty(calc.quantite, false) + ' × ' + fmtFc(p.prix_unitaire || 0)
            +   '<br><span style="color:#0B5E88;">HT ' + fmtFc(calc.line_ht) + '</span>'
            + '</td>'
            + '</tr>';
        });
        prodsHtml += '</table>';
        extrasDetailRows.push(
          '<tr>' + dateCell + '<td>' + escapeHtml(x.description)
          + '<div style="font-size:.72rem;color:#94a3b8;">'
          + (x.numero ? 'Vente ' + escapeHtml(x.numero) : 'Extras caisse')
          + '</div>' + prodsHtml + '</td>'
          + '<td class="num" style="vertical-align:top;">' + fmtFc(x.montant) + '</td></tr>'
        );
      } else {
        extrasDetailRows.push(
          '<tr>' + dateCell + descCell + '<td class="num">' + fmtFc(x.montant) + '</td></tr>'
        );
      }
    });
    const extrasTable = extrasDetailRows.length === 0
      ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun extra saisi depuis la caisse pour ce séjour.</div>'
      : '<table class="hotel-ft-table"><thead><tr><th>Date</th><th>Description</th><th class="num">Montant</th></tr></thead><tbody>'
      + extrasDetailRows.join('') + '</tbody></table>';
    const acomptesRows = (previewData.acomptes || []).map(a => '<tr><td>' + fmtDate(a.date) + '</td><td>' + escapeHtml(a.mode) + '</td><td class="num">' + fmtFc(a.montant) + '</td></tr>').join('');
    const acomptesTable = (previewData.acomptes || []).length === 0
      ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun acompte.</div>'
      : '<table class="hotel-ft-table"><thead><tr><th>Date</th><th>Mode</th><th class="num">Montant</th></tr></thead><tbody>' + acomptesRows + '</tbody></table>';

    // Totaux RÉELS chambre + extras (régime B hôtelier : TVA collectée = 0).
    //
    // BUG FIX 2026-10-20 — alignement strict sur posCart.calculateItemTTC :
    // Avant cette correction, on additionnait ici
    //   totalFinalB = totalNuitesAvecTSetTVA + totalExtrasHT + totalExtrasTS + totalExtrasTVATheorique
    // ce qui COMPTAIT 3 FOIS la composante extras :
    //   - totalExtrasHT (line_ht) inclut déjà TS + TVA_sur_TS (cf. computeProductTTC)
    //   - totalExtrasTS  = la TS une seconde fois
    //   - totalExtrasTVATheorique = la TVA une seconde fois
    // Résultat : le total facturé était surévalué d'un facteur (TS + TVA).
    //
    // Formule correcte (pattern app.js) :
    //   totalFacture = sum(lineTTC_extras) + total_avec_ts_et_tva_chambre + extrasManuels
    //
    // Pour les chambres, le backend expose `total_avec_ts_et_tva`
    // (= lineHT cumulé chambres = HT + TS + TVA_sur_TS, sans TVA_sur_prix).
    // Pour les extras, on utilise `line_ttc` (miroir app.js) :
    //   line_ttc = price*qty + TS + TVA_sur_prix + TVA_sur_TS
    // Ce qui correspond au calcul DGI attendu pour la rubrique TTC.
    const totalNuitesAvecTS = parseFloat(n.total_avec_ts) || 0;
    const tvaSurTS = parseFloat(n.tva_sur_ts) || 0;
    const totalNuitesAvecTSetTVA = parseFloat(n.total_avec_ts_et_tva) || (totalNuitesAvecTS + tvaSurTS);
    // Total extras TTC (aligné pattern app.js : lineTTC cumulé)
    const totalExtrasTTC = extrasTotals.total_ttc || 0;
    // Total facturé (cumul chambres + extras, aligné pattern app.js)
    const totalFinalB = totalNuitesAvecTSetTVA + totalExtrasTTC;

    const totalsAndEmit = alreadyFactured ? '' : `
      <div class="hotel-ft-totals">
        <div class="row total"><span>Total chambres + extras TTC</span><span>${fmtFc(totalFinalB)}</span></div>
        <div class="row"><span>Acomptes déjà versés</span><span>− ${fmtFc(previewData.acompte_total)}</span></div>
        <div class="row total"><span>Solde dû</span><span>${fmtFc(totalFinalB - (previewData.acompte_total || 0))}</span></div>
      </div>
      <div style="margin-top:1rem;">
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;">
          <input type="checkbox" id="hotel-ft-checkout">
          Clôturer le séjour (check-out) en même temps
        </label>
      </div>
      <div class="hotel-ft-actions">
        <button type="button" class="btn btn-ghost" id="hotel-ft-back">← Choisir un autre séjour</button>
        <button type="button" class="btn btn-primary" id="hotel-ft-emit">Émettre la facture</button>
      </div>`;

    const existingBanner = alreadyFactured ? `
      <div class="hotel-ft-banner warn" style="margin-top:1rem;">
        ⚠ Facture existante : <strong>${escapeHtml(ex.numero_facture)}</strong> (${fmtFc(ex.total_final)}) — ${escapeHtml(ex.statut)}.
        <div style="margin-top:.5rem;display:flex;gap:.5rem;justify-content:flex-end;">
          <a href="/facture/${ex.vente_id}" target="_blank" class="btn btn-primary btn-small">Voir / Imprimer</a>
          <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-cancel-existing">Annuler</button>
        </div>
      </div>` : '';

    bloc.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.5rem;">
        <div>
          <h3 style="margin:0;">${escapeHtml(s.guest.prenom || '')} ${escapeHtml(s.guest.nom || '')}</h3>
          <div style="font-size:.82rem;color:var(--muted);margin-top:2px;">Chambre ${escapeHtml(s.chambre.numero)} · ${escapeHtml(s.chambre.type)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
          ${s.client ? '<div style="font-size:.82rem;color:var(--muted);margin-top:2px;">Client lié : <strong>' + escapeHtml(s.client.code_client) + '</strong></div>' : ''}
        </div>
        <div style="text-align:right;">
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Séjour #${s.id}</div>
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);margin-top:4px;">Statut : <strong>${escapeHtml(s.statut)}</strong></div>
        </div>
      </div>
      ${existingBanner}
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Nuits (${n.nb_nuits})</h4>
        <table class="hotel-ft-table"><thead><tr><th>Date</th><th class="num">Prix</th></tr></thead><tbody>${nightsRows}${remiseRow}</tbody></table>
      </div>
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Extras (${(e.lignes || []).length} saisie${(e.lignes || []).length > 1 ? 's' : ''})</h4>
        ${extrasTable}
        <div style="margin-top:.85rem;font-size:.82rem;color:var(--muted);">Ajout manuel (minibar, casse, pourboire…) :</div>
        <div class="hotel-ft-extras-add">
          <input type="text" id="hotel-ft-extra-desc" placeholder="Description">
          <input type="number" id="hotel-ft-extra-montant" placeholder="Montant FC" min="0" step="0.01">
          <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-extra-add">+ Ajouter</button>
        </div>
        <div id="hotel-ft-extras-manuels-list"></div>
      </div>
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Acomptes déjà versés</h4>
        ${acomptesTable}
      </div>
      ${totalsAndEmit}
    `;

    const $extraAdd = $('hotel-ft-extra-add');
    if ($extraAdd) {
      $extraAdd.addEventListener('click', () => {
        const desc = $('hotel-ft-extra-desc').value.trim();
        const montant = parseFloat($('hotel-ft-extra-montant').value);
        if (!desc || !(montant > 0)) return;
        extrasManuels.push({ description: desc, montant });
        $('hotel-ft-extra-desc').value = '';
        $('hotel-ft-extra-montant').value = '';
        renderExtrasManuels();
      });
    }
    const $emit = $('hotel-ft-emit');
    if ($emit) $emit.addEventListener('click', openHotelInvoiceInfoModal);
    const $back = $('hotel-ft-back');
    if ($back) $back.addEventListener('click', () => {
      currentSejourId = null;
      previewData = null;
      extrasManuels = [];
      paiements = [];
      bloc.innerHTML = '';
      bloc.style.display = 'none';
      picker.style.display = '';
      const u = new URL(window.location.href);
      u.searchParams.delete('sejour_id');
      window.history.replaceState({}, '', u.toString());
    });
    const $cancelExisting = $('hotel-ft-cancel-existing');
    if ($cancelExisting) $cancelExisting.addEventListener('click', cancelExistingFacture);

    renderExtrasManuels();
  }

  function renderExtrasManuels() {
    const $list = $('hotel-ft-extras-manuels-list');
    if (!$list) return;
    if (extrasManuels.length === 0) { $list.innerHTML = ''; return; }
    const rows = extrasManuels.map((x, i) => '<tr><td>' + escapeHtml(x.description) + '</td><td class="num">' + fmtFc(x.montant) + '</td><td><button type="button" class="btn btn-ghost btn-small" data-rm="' + i + '">×</button></td></tr>').join('');
    $list.innerHTML =
      '<table class="hotel-ft-table" style="margin-top:.5rem;"><thead><tr><th>Description</th><th class="num">Montant</th><th></th></tr></thead><tbody>' +
      rows + '</tbody></table>' +
      '<div style="text-align:right;font-size:.85rem;color:var(--muted);margin-top:.25rem;">Sous-total : <strong>' +
      fmtFc(extrasManuels.reduce((s, x) => s + x.montant, 0)) + '</strong></div>';
    $list.querySelectorAll('[data-rm]').forEach(b => {
      b.addEventListener('click', () => {
        const i = parseInt(b.dataset.rm, 10);
        extrasManuels.splice(i, 1);
        renderExtrasManuels();
      });
    });
  }

  // ── CALCUL SOLDE COURANT ──────────────────────────────────────────────
  // Sprint 7 — 2026-10-19 : alignement sur le pattern app.js
  // (posCart.calculateItemTTC) — la valeur exposée au front inclut
  // maintenant la TVA_sur_TS (= `lineHT` cumulé). On reproduit ici le
  // même calcul pour que la preview/receipt soit cohérente avec la
  // payload DGI et la persistance DB.
  //
  //   total = (HT_nuit + TS_nuit + TVA_sur_TS_nuit)         // total_avec_ts_et_tva (backend)
  //         + extras_HT + extras_TS + extras_TVA_sur_TS     // cumul aligné pattern app.js
  //         + extras_manuels
  //         − acomptes (déjà déduits de solde_du par le backend)
  // BUG FIX 2026-10-20 — alignement strict sur posCart.calculateItemTTC :
  // Le solde dû exposé à l'UI et envoyé à la DGI doit être :
  //
  //   totalFacture = lineTTC_chambres + lineTTC_extras_caisse + extras_manuels
  //   soldeDu     = totalFacture − acompteTotal
  //
  // AVANT (buggé) :
  //   - Cette fonction lisait `e.total_ht`, `e.total_ts`, `e.total_tva`
  //     depuis la réponse backend, mais le contrôleur PHP ne calcule PAS
  //     ces champs pour les extras : il expose juste `extras.total_ht`
  //     (= sum des montants = qty × prix, SANS TS ni TVA).
  //   - `e.total_ts` et `e.total_tva` étaient donc `undefined` → 0.
  //   - Du coup, le totalTTC n'incluait PAS la TS ni la TVA des extras.
  //   - On sous-évaluait massivement le TTC facturé.
  //
  // MAINTENANT :
  //   - On appelle `computeExtrasTotals(e.lignes)` qui ré-applique la formule
  //     `posCart.calculateItemTTC` sur chaque produit détaillé.
  //   - `extrasTotals.total_ttc` = TTC facturé au client en régime B hôtelier
  //     (= sum(line_ht) = HT + TS + TVA_sur_TS, sans TVA_sur_prix qui n'est
  //      PAS collectée — cf. commentaire dans computeExtrasTotals).
  //   - `totalChambresTTC` (= `total_avec_ts_et_tva` du backend) reste la
  //     source de vérité pour les chambres (= lineHT cumulé chambres, déjà
  //     cohérent régime B).
  //   - Le résultat est donc EXACTEMENT :
  //        TTC_chambres + TTC_extras + extras_manuels − acompte_total
  //     où chaque TTC est en régime B (HT + TS + TVA_sur_TS).
  function computeSoldeCourant() {
    if (!previewData) return 0;
    var n = previewData.nuits || {};
    var e = previewData.extras || {};
    var extrasManualTotal = extrasManuels.reduce(function (s, x) { return s + x.montant; }, 0);

    // 1) Total chambres TTC — exposé par le backend
    //    (= HT + TS + TVA_sur_TS pour le séjour, sans TVA_sur_prix
    //      car régime B hôtelier)
    var totalChambresTTC = parseFloat(n.total_avec_ts_et_tva) || 0;

    // 2) Total extras caisse TTC — calculé ICI via computeExtrasTotals
    //    (= sum(line_ht) des produits = HT + TS + TVA_sur_TS, régime B)
    var extrasTotals = computeExtrasTotals(e.lignes);
    var totalExtrasTTC = extrasTotals.total_ttc || 0;

    // 3) Acomptes
    var acompteTotal = parseFloat(previewData.acompte_total) || 0;

    // 4) Total facturé TTC
    var totalFactureTTC = totalChambresTTC + totalExtrasTTC + extrasManualTotal;

    // 5) Solde dû = total − acomptes
    return totalFactureTTC - acompteTotal;
  }
  function getTotalPaiements() {
    return paiements.reduce((s, p) => s + (parseFloat(p.montant) || 0), 0);
  }


  // Helpers de rendu ticket (réutilisés par preview et receipt)
  // Sprint 7 — 2026-10-19 : alignement sur le pattern app.js
  // (posCart.showPreview / calculateItemTTC). On affiche le détail
  // TS + TVA par ligne, comme dans la caisse. Le total final est
  // calculé via computeSoldeCourant() ci-dessus, aligné sur `lineHT`
  // cumulé (= HT + TS + TVA_sur_TS, sans TVA sur prix car régime B
  // hôtelier TVA non collectée).
  // Helper pour afficher le libellé TS (aligné sur posCart.getSpecificTaxLabel
  // de app.js ligne ~874). Renvoie '[TS] X Fc' pour type='CDF' ou
  // '[TS] X%' pour type='%', ou '' si valeur ≤ 0.
  function getHotelSpecificTaxLabel(rawValue, type) {
    var v = parseFloat(rawValue) || 0;
    if (v <= 0) return '';
    return (type === 'CDF')
      ? ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' + fmtFc(v) + ' '
      : ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' + v + '% ';
  }

  function buildHotelReceiptItems(n, e, manuels) {
    var s = previewData.sejour;
    var chambreTaxLabel = (n && n.tax_etiquette) || 'B';
    var chambreTsType = (n && n.taxe_specifique_type) || '%';
    var chambreTsRawValue = parseFloat(n && n.taxe_specifique_value) || 0;
    // Helper local pour générer le libellé TS brut (aligné sur
    // posCart.getSpecificTaxLabel dans app.js ligne 866-872) : renvoie
    // " [TS] X Fc" ou " [TS] X%" selon le type, ou "" si valeur <= 0.
    function rawTsLabel(rawValue, type) {
      var v = parseFloat(rawValue) || 0;
      if (v <= 0) return '';
      return (type === 'CDF')
        ? ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' + v + ' Fc '
        : ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' + v + '% ';
    }
    // Helper local pour générer le libellé remise (aligné sur app.js ligne
    // 1483-1487) : renvoie " - X Fc remise" ou " - X% remise", ou "" si
    // remise_value <= 0.
    function rawDiscountLabel(remiseType, remiseValue) {
      var v = parseFloat(remiseValue) || 0;
      if (v <= 0) return '';
      return (remiseType === 'CDF')
        ? ' - ' + v + ' Fc remise'
        : ' - ' + v + '% remise';
    }
    // En-tête de table — on garde "Total TTC" pour l'hôtel (vs "Total HT"
    // dans la caisse) parce qu'on est en régime B non collecté et le total
    // affiché correspond effectivement au TTC facturé client.
    var html = '<table class="receipt-table"><thead><tr><th>Article</th><th style="text-align:right">Total TTC</th></tr></thead><tbody>';

    // ── Nuits : format aligné sur app.js ligne 1491-1499 ────────────────
    // BUG FIX 2026-10-20 (round 3) : on reproduit exactement le format
    // d'affichage de la caisse POS :
    //   <nom chambre> [SER] [TS] <valeur TS>
    //   <qty> × <prix>                                  <total>
    (n.lignes || []).forEach(function (l) {
      var prixNuit = parseFloat(l.prix) || 0;
      var tsUnit = parseFloat(l.ts_unitaire) || 0;
      // lineHT (app.js) = prix × qty + TS + TVA_sur_TS
      var lineHT = prixNuit * 1 + tsUnit + (tsUnit * (parseFloat(n.tax_rate) || 0) / 100);
      // Libellé TS avec la valeur brute (pas en CDF calculé)
      var tsLabelLine = rawTsLabel(chambreTsRawValue, chambreTsType);
      // prod_service chambre = 'SER' (service hôtellerie)
      html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">Nuit ' + fmtDate(l.date) + ' — Chambre ' + escapeHtml(s.chambre.numero) + '<span class="item-tax-badge">' + chambreTaxLabel + '</span><span class="item-prod-service">[SER]</span><small style="color:#b45309;font-weight:600;">' + tsLabelLine + '</small></span></td></tr>'
        + '<tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(prixNuit) + ' </td><td class="item-total" style="text-align:right;">' + fmtFc(lineHT) + ' Fc</td></tr>';
    });
    // ── Extras caisse : affiche les produits avec leur lineTTC ──────────
    // ── Extras caisse : format aligné sur app.js ligne 1491-1499 ──────────
    // Affiche pour chaque produit :
    //   <nom> <tax_etiquette> [prod_service] - <remise> [TS] <valeur TS>
    //   <qty> × <prix>                                  <total TTC>
    // BUG FIX 2026-10-20 (round 3) : on aligne sur le pattern d'affichage
    // de la caisse POS pour la lisibilité (badge étiquette, badge prod_service,
    // libellé remise, libellé TS).
    (e.lignes || []).forEach(function (x) {
      var produits = x.produits || [];
      if (produits.length > 0) {
        produits.forEach(function (p) {
          var calc = computeProductTTC(p);
          // Étiquette de taxe (ex: "B", "C")
          var taxLabel = calc.tax_etiquette || (calc.tax_rate > 0 ? 'TVA ' + calc.tax_rate + '%' : 'Exonere');
          // prod_service (ex: 'BIE' ou 'SER') — lu depuis le backend (cf.
          // HotelFactureController::buildPreviewData)
          var prodServiceBadge = p.prod_service ? '<span class="item-prod-service">[' + escapeHtml(p.prod_service) + ']</span>' : '';
          // Libellé remise (aligné sur app.js ligne 1483-1487)
          var discountLabel = rawDiscountLabel(p.remise_type, p.remise_value);
          // Libellé TS (aligné sur app.js ligne 866-872, valeur brute)
          var specificTaxLabel = rawTsLabel(p.taxe_specifique_value, p.taxe_specifique_type);
          html += '<tr class="item-name-row">'
            + '<td colspan="2"><span class="item-name">'
            + escapeHtml(p.nom || x.description || 'Produit')
            + '<span class="item-tax-badge">' + escapeHtml(taxLabel) + '</span>'
            + prodServiceBadge
            + (discountLabel ? '<small style="color:var(--success);font-weight:600;">' + discountLabel + '</small>' : '')
            + (specificTaxLabel ? '<small style="color:#b45309;font-weight:600;">' + specificTaxLabel + '</small>' : '')
            + '</span></td></tr>'
            + '<tr class="item-detail-row"><td class="item-qty">'
            + formatQty(calc.quantite, false) + ' × ' + fmtFc(p.prix_unitaire || 0) + ' '
            // BUG FIX 2026-10-20 (round 2) : on affiche line_ht (= HT + TS +
            // TVA_sur_TS) qui correspond au TTC facturé au client en régime B
            // hôtelier (la TVA_sur_prix n'est PAS collectée).
            + '</td><td class="item-total" style="text-align:right;">' + fmtFc(calc.line_ht) + ' Fc</td></tr>';
        });
      } else {
        // Ligne legacy : pas de détails produits, on affiche le montant brut
        var montant = parseFloat(x.montant) || 0;
        html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">' + escapeHtml(x.description || 'Extra') + '<span class="item-tax-badge">B</span><span class="item-prod-service">[SER]</span></span></td></tr><tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(montant) + ' </td><td class="item-total" style="text-align:right;">' + fmtFc(montant) + ' Fc</td></tr>';
      }
    });
    // ── Extras manuels : format aligné sur app.js ─────────────────────────
    (manuels || []).forEach(function (x) {
      var montant = parseFloat(x.montant) || 0;
      html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">'
        + escapeHtml(x.description)
        + ' <small style="color:#b45309">(extra manuel)</small>'
        + '<span class="item-tax-badge">B</span>'
        + '<span class="item-prod-service">[SER]</span>'
        + '</span></td></tr>'
        + '<tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(montant) + ' </td><td class="item-total" style="text-align:right;">' + fmtFc(montant) + ' Fc</td></tr>';
    });
    return html + '</tbody></table>';
  }

  function buildHotelReceiptInfoSection() {
    var s = previewData.sejour;
    var sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : (STORE_INFO.name || '');
    var agentCode = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '';
    return '<div class="client-vendor-info" style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:11px; line-height:1.5;">'
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>VENDEUR:</strong></span><span>' + escapeHtml(sellerName) + '</span></div>'
      + (agentCode ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>NUM AGENT:</strong></span><span>' + escapeHtml(agentCode) + '</span></div>' : '')
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>CLIENT:</strong></span><span>' + escapeHtml(((s.guest.prenom || '') + ' ' + (s.guest.nom || '')).trim()) + '</span></div>'
      + (s.guest.telephone ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>TEL:</strong></span><span>' + escapeHtml(s.guest.telephone) + '</span></div>' : '')
      + (s.client ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>CODE CLIENT:</strong></span><span>' + escapeHtml(s.client.code_client) + '</span></div>' : '')
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>SEJOUR:</strong></span><span>#' + s.id + ' — Chambre ' + escapeHtml(s.chambre.numero) + '</span></div>'
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>SERVICE:</strong></span><span>Hôtellerie</span></div>'
      + '</div>';
  }

  function buildHotelReceiptTotals(total) {
    var manualTotal = extrasManuels.reduce(function (a, x) { return a + x.montant; }, 0);
    var usdRate = (typeof USD_RATE !== 'undefined' && USD_RATE > 0) ? USD_RATE : 0;
    var amountInWords = (typeof posCart !== 'undefined' && posCart.numberToFrenchWords)
      ? posCart.numberToFrenchWords(total)
      : '';

    // BUG FIX 2026-10-20 — alignement strict sur pattern app.js :
    //   - Avant : on lisait `e.total_ht`, `e.total_ts`, `e.total_tva`
    //     depuis la réponse backend, mais le contrôleur PHP ne calcule
    //     PAS ces champs pour les extras. → ils étaient toujours à 0.
    //   - Maintenant : on appelle `computeExtrasTotals(e.lignes)` pour
    //     ré-appliquer la formule complète (miroir app.js) sur chaque
    //     produit détaillé (HT + TS + TVA_sur_prix + TVA_sur_TS).
    //   - L'affichage détaillé a été simplifié (cf. nettoyage 2026-10-20)
    //     pour ne montrer que le TOTAL TTC + extras manuels — la
    //     décomposition HT/TS/TVA est disponible via le `tax_b_value`
    //     retourné par la DGI dans `buildHotelReceiptTaxBreakdown`.
    var html = '';
    // ── Bloc EXTRAS MANUELS ──
    if (manualTotal > 0) {
      html += '<div class="receipt-total-row"><span>Total extras manuels</span><span>' + fmtFc(manualTotal) + '</span></div>';
    }
    // ── Total aligné pattern app.js (= lineTTC cumulé, nuits + extras) ──
    html += '<div class="receipt-total-row grand-total"><span>TOTAL TTC :</span><span>' + fmtFc(total) + '</span></div>';
    if (usdRate) {
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>TAUX DU JOUR :</span><span>' + usdRate + ' Fc/USD</span></div>';
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>Equivalent en USD :</span><span>' + (total / usdRate).toFixed(2) + ' $</span></div>';
    }
    if (amountInWords) {
      html += '<div style="text-align:center; font-size:12px; color:#888; font-style:italic; margin-top:2px;">Arrêtée la présente facture à la somme de ' + escapeHtml(amountInWords) + ' congolais toutes taxes comprises</div>';
    }
    return html;
  }
  function buildHotelReceiptPayments() {
    if (!paiements || paiements.length === 0) return '';
    var html = '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px;">';
    paiements.forEach(function (p) {
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>' + escapeHtml(paymentLabel(p.mode)) + ' :</span><span>' + fmtFc(p.montant) + '</span></div>';
    });
    return html + '</div>';
  }


  // ===============================================================
  // PANEL PAIEMENT/REFS — équivalent invoice-info-modal de /caisse
  //   Mêmes ids de DOM que /caisse, mêmes handlers, mêmes règles de
  //   validation (PHONE_NUMBER_REGEX, type client PP/PM/PL/PC/AO,
  //   ref obligatoire pour AO, NIF obligatoire sauf PP, etc.).
  // ===============================================================

  // Mapping mode hôtel (UI) → nomenclature DGI (cf. PAY_MODE_DGI).
  // Paiements UI: 'cash' | 'mobile_money' | 'carte' | 'autre'
  // Paiements DGI: 'ESPECES' | 'MOBILEMONEY' | 'CARTEBANCAIRE' | 'AUTRE'

  function addHotelModalPaymentLine(type, amount) {
    if (typeof type === 'undefined') type = 'ESPECES';
    if (typeof amount === 'undefined') amount = 0;
    var list = $('modal-payments-list');
    if (!list) return;
    var maxLines = 6;
    if (list.children.length >= maxLines) return;
    var line = document.createElement('div');
    line.className = 'modal-payment-line';
    line.style.cssText = 'display: grid; grid-template-columns: 1fr 100px 28px; gap: 8px; align-items: end;';
    line.innerHTML =
      '<div>' +
      '  <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Type</label>' +
      '  <select class="modal-payment-type client-number-input" style="width: 100%; background: #fff;" onchange="hotelCalculateModalPayments()">' +
      '    <option value="ESPECES"' + (type === 'ESPECES' ? ' selected' : '') + '>Espèces</option>' +
      '    <option value="MOBILEMONEY"' + (type === 'MOBILEMONEY' ? ' selected' : '') + '>Mobile Money</option>' +
      '    <option value="CARTEBANCAIRE"' + (type === 'CARTEBANCAIRE' ? ' selected' : '') + '>Carte Bancaire</option>' +
      '    <option value="VIREMENT"' + (type === 'VIREMENT' ? ' selected' : '') + '>Virement</option>' +
      '    <option value="CREDIT"' + (type === 'CREDIT' ? ' selected' : '') + '>Crédit</option>' +
      '    <option value="CHEQUES"' + (type === 'CHEQUES' ? ' selected' : '') + '>Chèques</option>' +
      '    <option value="AUTRE"' + (type === 'AUTRE' ? ' selected' : '') + '>Autres</option>' +
      '  </select>' +
      '</div>' +
      '<div>' +
      '  <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Montant</label>' +
      '  <input type="number" class="modal-payment-amount client-number-input" step="0.01" min="0" placeholder="0.00" value="' + (amount > 0 ? amount.toFixed(2) : '') + '" style="width: 100%;" oninput="hotelCalculateModalPayments()">' +
      '</div>' +
      '<button type="button" onclick="removeHotelModalPaymentLine(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>';
    list.appendChild(line);
    hotelUpdateModalPaymentRemoveButtons();
    var addBtn = $('add-payment-line-btn');
    if (addBtn) addBtn.style.display = list.children.length >= maxLines ? 'none' : 'flex';
    hotelCalculateModalPayments();
  }

  function removeHotelModalPaymentLine(btn) {
    var line = btn.closest('.modal-payment-line');
    if (line) line.remove();
    hotelUpdateModalPaymentRemoveButtons();
    var list = $('modal-payments-list');
    var addBtn = $('add-payment-line-btn');
    if (addBtn && list) addBtn.style.display = list.children.length >= 6 ? 'none' : 'flex';
    hotelCalculateModalPayments();
  }

  function hotelUpdateModalPaymentRemoveButtons() {
    var lines = document.querySelectorAll('.modal-payment-line');
    lines.forEach(function (line) {
      var btn = line.querySelector('button');
      if (btn) btn.style.display = lines.length > 1 ? 'flex' : 'none';
    });
  }

  function hotelCalculateModalPayments() {
    var lines = document.querySelectorAll('.modal-payment-line');
    var total = 0;
    var payments = [];
    lines.forEach(function (line) {
      var type = line.querySelector('.modal-payment-type') ? line.querySelector('.modal-payment-type').value : 'ESPECES';
      var amount = parseFloat(line.querySelector('.modal-payment-amount') ? line.querySelector('.modal-payment-amount').value : 0) || 0;
      total += amount;
      payments.push({ type: type, amount: amount });
    });
    var cartTotal = computeSoldeCourant();
    var diff = total - cartTotal;
    var summary = $('modal-payment-summary');
    var summaryLabel = $('modal-payment-summary-label');
    var summaryAmount = $('modal-payment-summary-amount');
    if (summary && summaryLabel && summaryAmount) {
      if (Math.abs(diff) < 0.001) {
        summary.style.display = 'none';
      } else if (diff < 0) {
        summary.style.display = 'block';
        summaryLabel.textContent = 'Reste à payer :';
        summaryLabel.style.color = '#c62828';
        summaryAmount.textContent = Math.abs(diff).toFixed(2) + ' Fc';
        summaryAmount.style.color = '#c62828';
      } else {
        summary.style.display = 'block';
        summaryLabel.textContent = 'Monnaie à rendre :';
        summaryLabel.style.color = '#166534';
        summaryAmount.textContent = diff.toFixed(2) + ' Fc';
        summaryAmount.style.color = '#0B5E88';
      }
    }
    var hiddenType = $('modal-payment-type');
    if (hiddenType && payments.length > 0) hiddenType.value = payments[0].type;
    return payments;
  }

  function getHotelModalPayments() {
    var lines = document.querySelectorAll('.modal-payment-line');
    var payments = [];
    lines.forEach(function (line) {
      var type = line.querySelector('.modal-payment-type') ? line.querySelector('.modal-payment-type').value : 'ESPECES';
      var amount = parseFloat(line.querySelector('.modal-payment-amount') ? line.querySelector('.modal-payment-amount').value : 0) || 0;
      if (amount > 0) payments.push({ type: type, amount: amount });
    });
    return payments;
  }

  // ── REF DOCS (jusqu'à 8) — pattern /caisse
  function addHotelRefDocLine(value) {
    if (typeof value === 'undefined') value = '';
    var list = $('modal-ref-docs-list');
    if (!list) return;
    var maxLines = 8;
    var mainInput = $('modal-invoice-ref');
    var totalLines = (mainInput ? 1 : 0) + list.children.length;
    if (totalLines >= maxLines) return;
    var line = document.createElement('div');
    line.className = 'modal-ref-doc-line';
    line.style.cssText = 'display: flex; gap: 8px; align-items: center;';
    line.innerHTML =
      '<input type="text" class="modal-ref-doc-input client-number-input" placeholder="Réf..." value="' + value + '" style="width: 100%;" oninput="syncHotelRefDocsCount()">' +
      '<button type="button" onclick="removeHotelRefDocLine(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>';
    list.appendChild(line);
    hotelUpdateAddRefDocButton();
    syncHotelRefDocsCount();
  }

  function removeHotelRefDocLine(btn) {
    var line = btn.closest('.modal-ref-doc-line');
    if (line) line.remove();
    hotelUpdateAddRefDocButton();
    syncHotelRefDocsCount();
  }

  function hotelUpdateAddRefDocButton() {
    var list = $('modal-ref-docs-list');
    var btn = $('add-ref-doc-btn');
    var mainInput = $('modal-invoice-ref');
    if (!list || !btn) return;
    var maxLines = 8;
    var totalLines = (mainInput ? 1 : 0) + list.children.length;
    btn.style.display = totalLines >= maxLines ? 'none' : 'flex';
  }

  function syncHotelRefDocsCount() {
    var list = $('modal-ref-docs-list');
    var countInput = $('modal-ref-docs-count');
    var mainInput = $('modal-invoice-ref');
    if (countInput) {
      var totalLines = (mainInput ? 1 : 0) + (list ? list.children.length : 0);
      countInput.value = totalLines;
    }
  }

  function getHotelModalRefDocs() {
    var refs = [];
    var mainInput = $('modal-invoice-ref');
    if (mainInput && mainInput.value && mainInput.value.trim()) refs.push(mainInput.value.trim());
    var list = $('modal-ref-docs-list');
    if (list) {
      list.querySelectorAll('.modal-ref-doc-input').forEach(function (input) {
        if (input.value && input.value.trim()) refs.push(input.value.trim());
      });
    }
    return refs;
  }

  // Highlight un champ en rouge (style /caisse)
  function hotelHighlightModalField(fieldId) {
    var el = $('hotel-' + fieldId) || $(fieldId);
    if (!el) return;
    var originalBoxShadow = el.style.boxShadow;
    var originalBorderColor = el.style.borderColor;
    el.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.5)';
    el.style.borderColor = '#dc2626';
    var clear = function () {
      el.style.boxShadow = originalBoxShadow;
      el.style.borderColor = originalBorderColor;
      el.removeEventListener('input', clear);
      el.removeEventListener('change', clear);
      el.removeEventListener('focus', clear);
    };
    el.addEventListener('input', clear, { once: true });
    el.addEventListener('change', clear, { once: true });
    el.addEventListener('focus', clear, { once: true });
    el.focus();
  }

  // Charge les types de clients dans #modal-client-type (appels API)
  async function hotelLoadClientTypes() {
    var typeSelect = $('modal-client-type');
    if (!typeSelect) return;
    try {
      var res = await fetch(APP_URL + '/api/client/types');
      var types = await res.json();
      if (Array.isArray(types) && types.length > 0) {
        typeSelect.innerHTML = '';
        var defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = 'Type client';
        typeSelect.appendChild(defOpt);
        types.forEach(function (t) {
          var o = document.createElement('option');
          o.value = t.id;
          o.textContent = (t.code || '') + ' - ' + (t.description || t.name || '');
          o.dataset.code = t.code || '';
          typeSelect.appendChild(o);
        });
      }
    } catch (e) {
      console.warn('[hotel-ft] loadClientTypes:', e);
    }
  }

  // ── Récupère la valeur de la taxe B depuis le previewData ──
  // (helper historique — voir getTaxBValueFromPreview() défini plus haut).
  // Conservé ici comme wrapper synchrone pour les call-sites qui
  // s'attendaient à un résultat synchrone après l'ouverture du modal
  // d'info facture. Retourne simplement la valeur déjà calculée.
  function getTaxBValue() {
    return getTaxBValueFromPreview();
  }

  // ===============================================================
  // OUVERTURE + VALIDATION DU PANEL INFO FACTURE (ÉTAPE 1/3)
  //   Le seul point de divergence avec /caisse : le client est pré-rempli
  //   avec les infos du GUEST (previewData.sejour.guest) au lieu d'être
  //   saisi par l'utilisateur.
  // ===============================================================

  // ── CALCUL TTC LIGNE PRODUIT (alias rétro-compatible)
  // BUG FIX 2026-10-20 : cette fonction existait en double du calculateur
  // principal `computeProductTTC` (cf. plus haut). Plutôt que de maintenir
  // deux implémentations parallèles (avec le risque de drift entre
  // preview / preview extras / DGI), on redirige vers `computeProductTTC`.
  //
  // Historique : reprenait exactement la même logique que public/assets/js/app.js
  // lignes 836-862 pour que les calculs TS/TVA soient cohérents entre la
  // facture hôtel et la facture POS. Champs du payload : prix_unitaire,
  // quantite, tax_rate, taxe_specifique_type, taxe_specifique_value.
  function calcItemTTCMirror(p) {
    return computeProductTTC(p);
  }

  // Rendu des extras caisse (produits détaillés) + extras manuels dans le bloc
  // #hotel-ft-preview-extras. Les extras caisse viennent de
  // previewData.extras.lignes (enrichi par HotelFactureController::preview
  // avec le détail produit_id/nom/quantite/prix_unitaire/TS).
  function renderPreviewExtras() {
    var exWrap = $('hotel-ft-preview-extras');
    if (!exWrap) return;

    var lignes = (previewData && previewData.extras && Array.isArray(previewData.extras.lignes))
      ? previewData.extras.lignes
      : [];

    var html = '';

    // 1) Extras caisse : une card par vente, liste de produits dépliable
    if (lignes.length > 0) {
      html += '<div style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:.35rem;">Extras consommés (caisse)</div>';
      lignes.forEach(function (vente, idx) {
        var produits = vente.produits || [];
        var totalCalcule = 0;
        var rowsHtml = produits.map(function (p) {
          // BUG FIX 2026-10-20 (round 2) — régime B hôtelier : on utilise
          // line_ht (= HT + TS + TVA_sur_TS, sans TVA_sur_prix) qui
          // correspond au TTC facturé au client (cf. commentaire dans
          // computeExtrasTotals). Avant : lineTTC surévaluait la facture.
          var calc = calcItemTTCMirror(p);
          var tot = calc.line_ht;
          totalCalcule += tot;
          var qtyStr = (p.quantite === Math.floor(p.quantite))
            ? String(p.quantite)
            : p.quantite.toFixed(3);
          // BUG FIX 2026-10-20 (round 3) : on aligne l'affichage sur le pattern
          // de la caisse POS (cf. app.js ligne 1491-1499) — étiquettes,
          // prod_service, libellé remise et TS dans la même ligne que le nom.
          var taxLabel = calc.tax_etiquette || (calc.tax_rate > 0 ? 'TVA ' + calc.tax_rate + '%' : '');
          var prodServiceBadge = p.prod_service ? '[' + escapeHtml(p.prod_service) + ']' : '';
          var remiseLabel = (p.remise_value && p.remise_value > 0)
            ? ' - ' + (p.remise_type === 'CDF'
              ? p.remise_value + ' Fc'
              : p.remise_value + '%') + ' remise'
            : '';
          var tsLabel = (p.taxe_specifique_value && p.taxe_specifique_value > 0)
            ? ' [TS] ' + (p.taxe_specifique_type === 'CDF'
              ? p.taxe_specifique_value + ' Fc'
              : p.taxe_specifique_value + '%')
            : '';
          return '<tr>'
            + '<td>'
            +   escapeHtml(p.nom)
            +   (taxLabel ? ' <span style="font-weight:600;">' + taxLabel + '</span>' : '')
            +   (prodServiceBadge ? ' <span style="font-weight:600;">' + prodServiceBadge + '</span>' : '')
            +   (remiseLabel ? ' <span style="color:#16a34a;">' + remiseLabel + '</span>' : '')
            +   (tsLabel ? ' <span style="color:#b45309;">' + tsLabel + '</span>' : '')
            + '</td>'
            + '<td class="num">' + qtyStr + '</td>'
            + '<td class="num">' + fmtFc(p.prix_unitaire) + '</td>'
            + '<td class="num">' + fmtFc(tot) + '</td>'
            + '</tr>';
        }).join('');

        html += '<details style="margin-bottom:.5rem;border:1px solid var(--border,#e2e8f0);border-radius:8px;background:#f8fafc;"' + (idx === 0 ? ' open' : '') + '>'
          + '<summary style="padding:.5rem .65rem;cursor:pointer;font-size:.85rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem;">'
          + '<span><strong>' + escapeHtml(vente.numero || ('Vente #' + vente.vente_id)) + '</strong>'
          + ' <span style="color:var(--muted);font-size:.75rem;">· ' + escapeHtml(vente.date || '') + '</span>'
          + '</span>'
          + '<span style="font-weight:700;color:#0B5E88;">' + fmtFc(vente.montant) + '</span>'
          + '</summary>'
          + (produits.length > 0
            ? '<div style="padding:.25rem .65rem .65rem .65rem;">'
            + '<table class="hotel-ft-table" style="font-size:.78rem;">'
            + '<thead><tr><th>Produit</th><th class="num">Qté</th><th class="num">PU</th><th class="num">Total</th></tr></thead>'
            + '<tbody>' + rowsHtml + '</tbody>'
            + '<tfoot><tr><td colspan="3" style="text-align:right;font-weight:600;">Sous-total vente</td><td class="num" style="font-weight:700;">' + fmtFc(totalCalcule) + '</td></tr></tfoot>'
            + '</table>'
            + '</div>'
            : '<div style="padding:.25rem .65rem .65rem .65rem;font-style:italic;color:var(--muted);font-size:.78rem;">Aucun détail produit (vente legacy).</div>')
          + '</details>';
      });
    }

    // 2) Extras manuels : conservés à leur place, format inchangé
    if (extrasManuels.length > 0) {
      html += '<div style="font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;margin:.75rem 0 .35rem 0;">Extras manuels (saisis ici)</div>';
      var rowsM = extrasManuels.map(function (x) {
        return '<tr><td>' + escapeHtml(x.description) + '</td><td class="num">' + fmtFc(x.montant) + '</td></tr>';
      }).join('');
      html += '<table class="hotel-ft-table"><thead><tr><th>Description</th><th class="num">Montant</th></tr></thead><tbody>' + rowsM + '</tbody></table>';
    }

    // 3) Aucun extra du tout
    if (!html) {
      html = '<div style="font-size:.85rem;color:var(--muted);font-style:italic;">Aucun extra enregistré pour ce séjour.</div>';
    }

    exWrap.innerHTML = html;
  }

  async function openHotelInvoiceInfoModal() {
    var modal = $('invoice-info-modal');
    if (!modal) { console.error('[hotel-ft] invoice-info-modal absent'); return; }
    if (!previewData) { console.error('[hotel-ft] previewData manquant'); return; }

    var s = previewData.sejour;

    // Rendu détaillé des extras caisse (produits détaillés via renderPreviewExtras)
    // + extras manuels dans le bloc #hotel-ft-preview-extras.
    renderPreviewExtras();

    // Type de facture par défaut : FV (Facture de Vente).
    // Sprint 7 — 2026-10-19 : HB a été retiré du select (cf. hotel-facturation.php)
    // pour laisser l'utilisateur choisir parmi les types standards (FV/EV/FT/FA/EA/ET).
    // Le type par défaut reste "FV" (Facture de Vente) — cohérent avec /caisse
    // et /recharges.
    var invoiceTypeSelect = $('modal-invoice-type');
    if (invoiceTypeSelect && !invoiceTypeSelect.value) {
      invoiceTypeSelect.value = 'FV';
    }

    // Références documents (vide au départ — max 8 cf. /caisse)
    var refList = $('modal-ref-docs-list');
    if (refList) refList.innerHTML = '';
    syncHotelRefDocsCount();
    hotelUpdateAddRefDocButton();

    // Modes de paiement : 1 ligne pré-remplie avec le solde dû en Espèces
    var solde = computeSoldeCourant();
    paiements = [{ mode: 'cash', montant: solde }];
    // On travaille avec les paiements DGI (la conversion cash→ESPECES est faite plus tard)
    $('modal-payments-list').innerHTML = '';
    addHotelModalPaymentLine('ESPECES', solde);
    // Stocke le mapping pour la conversion finale (UI → DGI)
    paiements = [{ mode: 'cash', dgiType: 'ESPECES', montant: solde }];

    // ─── Pré-remplissage info client (guest) ──────────────────────────
    $('modal-client-name').value = ((s.guest.prenom || '') + ' ' + (s.guest.nom || '')).trim();
    $('modal-client-number').value = (s.guest.telephone || '').replace(/[^0-9]/g, '');
    $('modal-client-nif').value = '';
    $('modal-client-address').value = '';
    $('modal-checkout').value = (typeof $('hotel-ft-checkout') !== 'undefined' && $('hotel-ft-checkout') && $('hotel-ft-checkout').checked) ? '1' : '0';

    // Type de client par défaut : PP (personne physique)
    // Si on a un code client POS lié, on tente de mapper : PM/PC/PL/AO (cf. /caisse)
    var clientTypeCode = 'PP';
    if (s.client && s.client.code_client) {
      var head = (s.client.code_client || '').toUpperCase().slice(0, 2);
      if (['PM', 'PC', 'PL', 'AO'].indexOf(head) >= 0) clientTypeCode = head;
    }

    // Charge les types de clients (pour le select #modal-client-type).
    // La valeur de la taxe B est déjà disponible via previewData.sejour.chambre
    // (héritée de la chambre via la jointure FK taxe_id → taxes.taux), donc
    // plus besoin de fetch séparé vers /api/taxes.
    await hotelLoadClientTypes();
    var typeSelect = $('modal-client-type');
    if (typeSelect && clientTypeCode) {
      var matchedOpt = null;
      for (var i = 0; i < typeSelect.options.length; i++) {
        var o = typeSelect.options[i];
        if ((o.dataset && o.dataset.code || '').toUpperCase() === clientTypeCode) { matchedOpt = o; break; }
      }
      if (matchedOpt) {
        typeSelect.value = matchedOpt.value;
      } else {
        // fallback : on tente un match par texte
        for (var j = 0; j < typeSelect.options.length; j++) {
          if (typeSelect.options[j].textContent.toUpperCase().indexOf(clientTypeCode) === 0) {
            typeSelect.value = typeSelect.options[j].value;
            break;
          }
        }
      }
    }

    // Affichage
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeHotelInvoiceInfoModal() {
    var modal = $('invoice-info-modal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Validation des champs + constitution de paiements[] (mode UI 'cash' etc.)
  // puis bascule vers le modal preview global. Cette fonction est le
  // miroir de confirmInvoiceInfo() de app.js, adaptée à l'hôtel.
  function confirmHotelInvoiceInfo() {
    var errBox = $('modal-invoice-error');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

    // 1. Type de facture — on remplit la variable globale invoiceType
    // Sprint 7 — 2026-10-19 : HB n'est plus une option (cf. hotel-facturation.php),
    // on utilise le type sélectionné par l'utilisateur (FV/EV/FT/FA/EA/ET).
    // Par défaut : FV (Facture de Vente). La logique d'assujétissement
    // n'est plus nécessaire ici car le select est verrouillé sur FV si la
    // boutique n'est pas assujettie (cf. lockInvoiceTypeIfNonAssujeti).
    var isAdmin = typeof CURRENT_USER !== 'undefined' && CURRENT_USER !== null && ['admin', 'super_admin'].indexOf(CURRENT_USER.role) >= 0;
    var invoiceType = $('modal-invoice-type') && isAdmin ? ($('modal-invoice-type').value || 'FV') : 'FV';
    window.hotelInvoiceType = invoiceType;

    // REF FACTURE + EXONÉRATION (RAM/RRR/RAN/COR) — pattern /caisse
    window.hotelRefFacture = ($('modal-invoice-num') && $('modal-invoice-num').value) ? $('modal-invoice-num').value.trim() : '';
    window.hotelExoneration = ($('modal-exoneration') && $('modal-exoneration').value) ? $('modal-exoneration').value : '';

    var invoiceRefs = getHotelModalRefDocs();
    var invoiceRef = invoiceRefs[0] || '';

    var clientName = $('modal-client-name') ? $('modal-client-name').value : '';
    var clientNumber = $('modal-client-number') ? ($('modal-client-number').value || '').trim() : '';
    var clientTypeId = $('modal-client-type') ? ($('modal-client-type').value || '') : '';
    var clientNif = $('modal-client-nif') ? $('modal-client-nif').value : '';
    var clientAddress = $('modal-client-address') ? $('modal-client-address').value : '';

    // 2. Validation téléphone (PHONE_NUMBER_REGEX = /^0[89]\d{8}$/, déclaré
    //    const en haut de app.js → global au scope du script, PAS sur window).
    if (clientNumber && !PHONE_NUMBER_REGEX.test(clientNumber)) {
      alert('Le numéro de téléphone doit respecter le format 08xxxxxxxx ou 09xxxxxxxx.');
      hotelHighlightModalField('modal-client-number');
      return;
    }

    // 3. Code type client (extrait depuis le texte "PP - Particulier")
    var clientTypeText = '';
    var typeSel = $('modal-client-type');
    if (typeSel && typeSel.selectedIndex >= 0) {
      clientTypeText = (typeSel.options[typeSel.selectedIndex].textContent || '').trim();
    }
    var clientTypeCode = (clientTypeText.split(' - ')[0] || '').trim().toUpperCase();

    // 4. Adresse obligatoire si PM/PL/PC/AO
    if (['PM', 'PL', 'PC', 'AO'].indexOf(clientTypeCode) >= 0 && !clientAddress.trim()) {
      alert("L'adresse est obligatoire pour ce type de client.");
      hotelHighlightModalField('modal-client-address');
      return;
    }
    // 5. NIF obligatoire sauf PP
    if (clientTypeCode !== 'PP' && clientTypeCode !== '' && !clientNif.trim()) {
      alert("Le NIF est obligatoire pour ce type de client.");
      hotelHighlightModalField('modal-client-nif');
      return;
    }
    // 6. Pour AO : au moins 1 référence document non vide
    if (clientTypeCode === 'AO' && (invoiceRefs.length === 0 || invoiceRefs.every(function (r) { return !r.trim(); }))) {
      alert("La référence document est obligatoire pour les ambassades et organisations internationales.");
      hotelHighlightModalField('modal-invoice-ref');
      return;
    }

    // 7. Paiements : on récupère depuis #modal-payments-list (format DGI)
    var dgiPayments = getHotelModalPayments();
    var totalSolde = computeSoldeCourant();
    var dgiTotal = dgiPayments.reduce(function (s, p) { return s + p.amount; }, 0);
    if (dgiPayments.length === 0 || dgiTotal < totalSolde - 0.001) {
      alert('Le montant total des paiements est insuffisant par rapport au solde de la facture (' + fmtFc(totalSolde) + ').');
      var firstAmt = document.querySelector('#modal-payments-list .modal-payment-amount');
      if (firstAmt) firstAmt.focus();
      return;
    }

    // 8. Mémorise les paiements pour processEmission (mapping DGI->UI)
    paiements = dgiPayments.map(function (p) {
      var uiMode = 'cash';
      if (p.type === 'MOBILEMONEY') uiMode = 'mobile_money';
      else if (p.type === 'CARTEBANCAIRE') uiMode = 'carte';
      else if (p.type === 'AUTRE') uiMode = 'autre';
      return { mode: uiMode, dgiType: p.type, montant: p.amount };
    });

    // 9. Ferme le panel, ouvre le preview
    closeHotelInvoiceInfoModal();
    showHotelPreviewTicket();
  }

  // ===============================================================
  // ETAPE 2/3 : MODAL PREVIEW TICKET (global #preview-modal)
  // Réutilise les helpers buildHotelReceipt* posés plus haut dans le fichier.
  // Réécrit le footer du modal preview pour pointer sur nos handlers.
  // ===============================================================
  async function showHotelPreviewTicket() {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var total = computeSoldeCourant();

    // Récupère le VRAI n° de facture depuis la base (même pattern que
    // app.js:showPreview() pour /caisse) — sinon la preview affiche "(à
    // valider)" et il faut attendre la confirmation. On rend tout le ticket
    // APRÈS la résolution de la promesse pour que le n° soit visible
    // dès l'ouverture de la preview.
    var previewInvoiceNum = 'HB-' + Date.now();
    try {
      var invRes = await fetch(APP_URL + '/api/vente/next-invoice');
      var invData = await invRes.json();
      if (invData && invData.invoice_number) previewInvoiceNum = invData.invoice_number;
    } catch (_) { /* tolé rant : on garde le placeholder */ }
    window.hotelPreviewInvoiceNum = previewInvoiceNum;

    var itemsHtml = buildHotelReceiptItems(n, e, extrasManuels);
    var infoSection = buildHotelReceiptInfoSection();
    var totalsHtml = buildHotelReceiptTotals(total);
    var paymentsHtml = buildHotelReceiptPayments();
    // Sprint 7 — 2026-10-19 : on utilise le type de facture sélectionné
    // par l'utilisateur dans le modal "Informations Facture" (variable
    // globale window.hotelInvoiceType, posée par confirmHotelInvoiceInfo()).
    // Avant : titre hard-codé "Facture Hôtel — Régime type B (HB)" qui ne
    // reflétait pas le choix de l'utilisateur. Maintenant on affiche le
    // label complet via getInvoiceTypeLabel() (cf. app.js ligne 43), comme
    // dans la caisse POS.
    var typeLabel = (typeof getInvoiceTypeLabel === 'function' && window.hotelInvoiceType)
      ? getInvoiceTypeLabel(window.hotelInvoiceType)
      : 'Facture de Vente';
    var now = new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    var previewHtml =
      '<div class="receipt"><div class="receipt-header">' +
      (STORE_INFO.logo ? '<img src="' + STORE_INFO.logo + '" class="receipt-logo" alt="Logo">' : '') +
      '<div class="store-name">' + escapeHtml(STORE_INFO.name || '') + '</div>' +
      '<div class="store-info">' +
      '<div><strong>Point de vente :</strong> ' + escapeHtml(STORE_INFO.pdv || '') + '</div>' +
      '<div>Adresse : ' + escapeHtml(STORE_INFO.address || '') + '</div>' +
      '<div>Tel: ' + escapeHtml(STORE_INFO.phone || '') + '</div>' +
      (STORE_INFO.email ? '<div>Email: ' + escapeHtml(STORE_INFO.email) + '</div>' : '') +
      '<div>ID Nat: ' + escapeHtml(STORE_INFO.ice || '') + '</div>' +
      (STORE_INFO.nid ? '<div>NID: ' + escapeHtml(STORE_INFO.nid) + '</div>' : '') +
      (STORE_INFO.rccm ? '<div>RCCM: ' + escapeHtml(STORE_INFO.rccm) + '</div>' : '') +
      (STORE_INFO.isf ? '<div>Numero Impot: ' + escapeHtml(STORE_INFO.isf) + '</div>' : '') +
      '</div>' + infoSection +
      '</div>' +
      '<div class="receipt-meta" style="justify-content:center; font-size:14px; font-weight:600;">' + escapeHtml(typeLabel) + '</div>' +
      (window.hotelRefFacture ? '<div style="text-align:center; font-size:12px; color:#666;">Réf : ' + escapeHtml(window.hotelRefFacture) + '</div>' : '') +
      (window.hotelExoneration ? '<div style="text-align:center; font-size:11px; color:#888; font-style:italic;">Exonération : ' + escapeHtml(getExonerationLabel(window.hotelExoneration)) + '</div>' : '') +
      '<div class="receipt-items">' + itemsHtml + '</div>' +
      '<div class="receipt-totals">' + totalsHtml + paymentsHtml + '</div>' +
      '<div class="receipt-footer">' +
      '<div class="barcode">FACTURE n°' + escapeHtml(previewInvoiceNum) + '</div>' +
      '<div style="font-size:11px; color:#333; margin-top:4px;">Date : ' + now + '</div>' +
      '<div class="thank-you">Merci de votre séjour !</div>' +
      '<div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div>' +
      '</div></div>';

    var previewContent = $('preview-content');
    if (previewContent) previewContent.innerHTML = previewHtml;

    var previewFooter = document.querySelector('#preview-modal .modal-actions');
    if (previewFooter) {
      previewFooter.innerHTML =
        '<button type="button" class="btn btn-secondary" id="hotel-ft-preview-cancel">Annuler</button>' +
        '<button type="button" id="hotel-ft-preview-validate" class="btn btn-primary">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<polyline points="20 6 9 17 4 12"></polyline>' +
        '</svg>' +
        ' Valider la facture</button>';
      var c = $('hotel-ft-preview-cancel');
      if (c) c.onclick = function () {
        var m = $('preview-modal'); if (m) m.classList.remove('active');
        document.body.style.overflow = '';
      };
      var v = $('hotel-ft-preview-validate');
      if (v) v.onclick = function () {
        var m = $('preview-modal'); if (m) m.classList.remove('active');
        processEmission();
      };
    }
    var modal = $('preview-modal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
  }

  // ===============================================================
  // ETAPE 3/3 : VALIDATION DGI -> POST BACKEND -> MODAL RECEIPT
  // ===============================================================
  async function processEmission() {
    if (typeof showLoading === 'function') showLoading('Validation DGI…');
    else if ($('hotel-ft-overlay-loader')) $('hotel-ft-overlay-loader').style.display = 'flex';

    var dgiInvoiceNum = 'FAC-' + Date.now();
    try {
      var invRes = await fetch(APP_URL + '/api/vente/next-invoice');
      var invData = await invRes.json();
      if (invData && invData.invoice_number) dgiInvoiceNum = invData.invoice_number;
    } catch (_) { }

    // Plus besoin d'await loadTaxBRate() ici : la valeur est lue
    // directement depuis previewData.sejour.chambre.taxe_taux (jointure
    // chambre → taxes). Voir getTaxBValueFromPreview().

    var dgiPayload = buildDgiPayload(dgiInvoiceNum, 'FV');
    var dgiResp = await postToDgi(dgiPayload);
    if (!dgiResp || !dgiResp.success) {
      var msg = (dgiResp && dgiResp.message) || 'Échec validation DGI';
      if (typeof hideLoading === 'function') hideLoading();
      alert('DGI : ' + msg);
      return;
    }
    try {
      await postHotelFacture(dgiResp);
    } catch (e) {
      console.error('[hotel-ft] emission error', e);
      alert('Erreur : ' + e.message);
    } finally {
      if (typeof hideLoading === 'function') hideLoading();
      else if ($('hotel-ft-overlay-loader')) $('hotel-ft-overlay-loader').style.display = 'none';
    }
  }

  async function postToDgi(payload) {
    if (typeof DGI_API_URL === 'undefined') return { success: false, message: 'DGI_API_URL non défini (app.js doit être chargé)' };
    try {
      var res = await fetch(DGI_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      var text = await res.text();
      if (!text) return { success: false, message: 'Réponse vide du serveur DGI' };
      try { return JSON.parse(text); }
      catch (_) { return { success: false, message: 'Réponse DGI non-JSON' }; }
    } catch (e) {
      return { success: false, message: 'Connexion DGI impossible : ' + e.message };
    }
  }

  function buildDgiPayload(invoiceNum, invoiceType) {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var articles = [];

    // ── Nuits : une ligne par nuitée (cf. pattern app.js : "Nuit {date} - Chambre X").
    //    BUG FIX 2026-10-19 : avant on envoyait TOUJOURS tax_rate=0 et
    //    taxe_specifique_value=0, ce qui envoyait à la DGI une facture
    //    incohérente quand la chambre avait une TS et/ou appartenait à
    //    un groupe de taxation TVA (B, C, etc.). Le calcul `ht_tva_group`
    //    / `va` côté DGI était alors impossible à réconcilier avec
    //    `amount`. On utilise désormais les vraies valeurs de la chambre :
    //      - `tax_rate`        : taux de TVA de la chambre (lu via la FK
    //                            chambre.taxe_id → taxes.taux)
    //      - `tax_etiquette`   : étiquette de la chambre (B par défaut)
    //      - `taxe_specifique_type/value` : TS de la chambre appliquée par
    //                            nuitée. La valeur est déjà calculée en
    //                            CDF côté PHP (`l.ts_unitaire`) — pas
    //                            besoin de la reconvertir.
    //    Note : le `tax_b_value` au niveau de la payload est calculé par
    //    getTaxBValueFromPreview() (cf. ci-dessous).
    var chambreTaxRate = parseFloat(n.tax_rate) || 0;
    var chambreTaxLabel = n.tax_etiquette || 'B';
    var chambreTsType = n.taxe_specifique_type || '%';
    (n.lignes || []).forEach(function (l) {
      var prixNuit = parseFloat(l.prix) || 0;
      // l.ts_unitaire = valeur CDF déjà calculée par le backend (cf.
      // HotelFactureController::buildPreviewData — computeTaxeSpecifiqueUnit).
      // On l'utilise directement, c'est la source de vérité côté serveur.
      var tsValueCdf = parseFloat(l.ts_unitaire) || 0;
      articles.push({
        name: 'Nuit ' + fmtDate(l.date) + ' — Chambre ' + (s.chambre.numero || ''),
        quantity: 1,
        price: prixNuit,
        tax_rate: chambreTaxRate,
        tax_etiquette: chambreTaxLabel,
        prod_service: 'SER',
        remise_type: '%',
        remise_value: 0,
        taxe_specifique_type: chambreTsType,
        taxe_specifique_value: tsValueCdf,
      });
    });

    // ── Extras caisse : on décompose ligne par ligne + produit par produit
    //    pour que la DGI puisse recalculer le TTC de chaque article via :
    //      ttc = price * quantity - remise_value + taxe_specifique_value + TVA
    //    On suit exactement le pattern de app.js (cf. posCart.validateWithDGI).
    //    Les valeurs envoyées sont les valeurs CDF effectives (pas en %).
    //
    // BUG FIX 2026-10-20 :
    //   1. `tax_etiquette` était hardcodé à 'B' pour tous les extras → on
    //      envoyait à tort au régime B des produits appartenant à d'autres
    //      groupes (C=5%, F=16%, etc.), ce qui faussait le calcul de TVA
    //      côté DGI. On lit maintenant la vraie étiquette depuis
    //      `p.taxe_etiquette` (fournie par HotelFactureController::preview
    //      via la jointure produits.taxe_id → taxes).
    //   2. `taxe_specifique_value` est convertie via `computeProductTTC`
    //      pour garantir la cohérence avec le calcul front (`lineTTC`).
    (e.lignes || []).forEach(function (x) {
      if (x.produits && x.produits.length > 0) {
        x.produits.forEach(function (p) {
          // Calcul TTC aligné pattern app.js — réutilise computeProductTTC
          // pour la SPECIFIC (TS convertie en CDF) et la REMISE (CDF ou %).
          var calc = computeProductTTC(p);
          articles.push({
            name: (x.description ? (x.description + ' — ') : '') + (p.nom || 'Produit'),
            quantity: calc.quantite,
            price: calc.price_unitaire,
            // BUG FIX 2026-10-20 (round 2) : on envoie le VRAI tax_rate du
            // produit (ex: 5% pour ceoe) à la DGI pour qu'elle puisse ventiler
            // correctement la TVA. Le `amount` (top-level) envoyé par la suite
            // est volontairement différent du `vtotal` que la DGI recalcule :
            //   - vtotal = sum(lineTTC) = HT + TS + TVA_sur_prix + TVA_sur_TS
            //   - amount = sum(lineHT)  = HT + TS + TVA_sur_TS (= TTC facturé
            //                              au client en régime B hôtelier,
            //                              TVA_sur_prix non collectée)
            // Le client paie `amount` (régime B), la DGI ventile selon `vtotal`.
            tax_rate: calc.tax_rate,
            tax_etiquette: calc.tax_etiquette,
            prod_service: 'SER',
            remise_type: calc.remise_type,
            remise_value: calc.remise_montant,
            taxe_specifique_type: p.taxe_specifique_type || '%',
            taxe_specifique_value: calc.specific_tax_unit,
          });
        });
      } else {
        // Ligne legacy : pas de détails produits, on envoie juste le montant
        // comme un article simple (le calcul DGI sera cohérent).
        articles.push({
          name: x.description || ('Extra #' + x.numero),
          quantity: 1,
          price: parseFloat(x.montant) || 0,
          tax_rate: 0,
          tax_etiquette: 'B',
          prod_service: 'SER',
          remise_type: '%',
          remise_value: 0,
          taxe_specifique_type: '%',
          taxe_specifique_value: 0,
        });
      }
    });

    // ── Extras manuels : pas de détails produit, on envoie le libellé + montant.
    extrasManuels.forEach(function (x) {
      articles.push({
        name: x.description,
        quantity: 1,
        price: parseFloat(x.montant) || 0,
        tax_rate: 0,
        tax_etiquette: 'B',
        prod_service: 'SER',
        remise_type: '%',
        remise_value: 0,
        taxe_specifique_type: '%',
        taxe_specifique_value: 0,
      });
    });

    // ── Total envoyé à la DGI : aligné sur le pattern app.js ──────────
    // On reproduit ici EXACTEMENT la décomposition de posCart.calculateItemTTC
    // (public/assets/js/app.js lignes 836-862) :
    //
    //   vatOnPriceUnit       = price × (taxRate / 100)         // TVA sur le prix
    //   vatOnSpecificTaxUnit = specificTaxUnit × (taxRate/100) // TVA sur la TS
    //   lineHT               = price × qty + specificTaxUnit + vatOnSpecificTaxUnit
    //   lineTTC              = lineHT + vatOnPriceUnit × qty   // = app.js
    //
    // Le `tax_etiquette` (ex: "B") porte la valeur du taux via `tax_rate`
    // (= chambre.taxe_id → taxes.taux, jointure Sprint 7). On utilise donc
    // `tax_rate` (la valeur de l'étiquette) dans le calcul.
    //
    // BUG FIX 2026-10-20 (round 2) — régime B hôtelier :
    //   - Le `amount` (top-level) envoyé à la DGI = sum(lineHT) = HT + TS +
    //     TVA_sur_TS (TTC que le client paie en régime B, sans TVA_sur_prix).
    //   - Le `vtotal` recalculé par la DGI = sum(lineTTC) = HT + TS +
    //     TVA_sur_prix + TVA_sur_TS (TTC théorique, info fiscale).
    //   - Les deux divergent en régime B : c'est NORMAL et CONFORME — le
    //     client paie `amount` (= lineHT), la DGI ventile selon son
    //     `vtotal` théorique.
    //
    // BUG FIX 2026-10-20 (initial) : l'ancien calcul faisait
    // `lineHT = prix*qty - remiseVal + tsVal + VAT_sur_TS`. Or dans app.js
    // (cf. posCart.calculateItemTTC ligne 849), la remise n'est PAS déduite
    // dans lineHT — elle est appliquée en aval par la DGI via le champ
    // remise_value. On aligne maintenant sur app.js :
    //   lineHT  = prix*qty + TS + VAT_sur_TS   (PAS de remise dans lineHT)
    //   lineTTC = lineHT + VAT_sur_prix * qty
    // L'impact sur le total n'est pas nul car l'ancienne formule sous-évaluait
    // lineHT pour les produits remisés (la remise_value était appliquée 2 fois :
    // une fois ici, une fois par la DGI).
    var totalHT = 0;       // HT facturé (= lineHT * 1, aligné app.js)
    var totalTTC = 0;      // TTC théorique (HT + TS + TVA, comme app.js)
    var totalTVA = 0;      // TVA théorique (cumul vatOnPrice + vatOnTS)
    var totalTS = 0;       // TS cumulée
    articles.forEach(function (a) {
      var prixUnit = parseFloat(a.price) || 0;
      var qty = parseFloat(a.quantity) || 1;
      var tRate = parseFloat(a.tax_rate) || 0;
      var tsVal = parseFloat(a.taxe_specifique_value) || 0;
      // ── Miroir EXACT de posCart.calculateItemTTC (app.js) ──
      var vatOnPriceUnit = prixUnit * (tRate / 100);
      var vatOnTSUnit = tsVal * (tRate / 100);
      // lineHT (app.js) = prix × qty + TS + TVA_sur_TS — la TVA sur prix N'EST PAS dedans
      //                    la remise_value est envoyée séparément et appliquée par la DGI
      var lineHT = prixUnit * qty + tsVal + vatOnTSUnit;
      // lineTTC (app.js) = lineHT + TVA_sur_prix × qty (la TVA sur prix est dans le TTC)
      var lineTTC = lineHT + vatOnPriceUnit * qty;
      totalHT += lineHT;   // somme des HT (= TTC facturé client, régime B)
      totalTTC += lineTTC; // somme des TTC (= vtotal DGI, info fiscale)
      totalTVA += (vatOnPriceUnit + vatOnTSUnit) * qty;
      totalTS += tsVal;
    });
    // amount envoyé à la DGI = sum(lineHT) (= HT + TS + TVA_sur_TS — régime B,
    // TVA non collectée). C'est le TTC que le client paie réellement, aligné
    // sur $totalFinal persisté par HotelFactureController::store.
    // Note : le `vtotal` recalculé par la DGI sera = sum(lineTTC), qui inclut
    // la TVA_sur_prix — c'est attendu en régime B (info fiscale distincte du
    // montant facturé au client).
    var total = totalHT;

    var sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : (STORE_INFO.name || '');
    var agentCode = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '';
    var clientTypeCode = 'PP';
    if (s.client && s.client.code_client) {
      var head = (s.client.code_client || '').toUpperCase().slice(0, 2);
      if (['PM', 'PC', 'PL', 'AO'].indexOf(head) >= 0) clientTypeCode = head;
    }
    var dgiPayments = (paiements || []).map(function (p) { return { type: p.dgiType || 'ESPECES', amount: parseFloat(p.montant) || 0 }; });

    // Valeur de la taxe B (TVA type B hôtelier) lue depuis le previewData
    // via getTaxBValueFromPreview(). Le séjour hérite de cette valeur par
    // jointure chambre.taxe_id → taxes.taux lors de l'appel à
    // /api/hotel/factures/preview. C'est le taux de TVA en % appliqué
    // au groupe B (en général 16). 0 si pas de taxe rattachée à la
    // chambre — la DGI peut alors retomber sur son propre taux par défaut.
    var bRate = getTaxBValueFromPreview();

    return {
      store_phone: (STORE_INFO.phone || ''),
      store_address: (STORE_INFO.address || ''),
      store_email: (STORE_INFO.email || ''),
      store_ice: (STORE_INFO.ice || ''),
      store_isf: (STORE_INFO.isf || ''),
      store_nid: (STORE_INFO.nid || ''),
      store_rccm: (STORE_INFO.rccm || ''),
      store_homologation: !!(STORE_INFO.homologation),
      store_pdv: (STORE_INFO.pdv || ''),
      store_name: (STORE_INFO.name || ''),
      seller_name: sellerName,
      seller_agent_code: agentCode,
      amount: total,
      client_number: (s.guest.telephone || ''),
      client_name: ((s.guest.prenom || '') + ' ' + (s.guest.nom || '')).trim() || 'Guest',
      client_type: clientTypeCode,
      client_type_val: '',
      client_nif: '',
      client_address: '',
      invoice_number: invoiceNum,
      invoice_type: invoiceType,
      invoice_refs: [],
      invoice_ref: '',
      ref_facture: (typeof window.hotelRefFacture === 'string') ? window.hotelRefFacture : '',
      exoneration: (typeof window.hotelExoneration === 'string') ? window.hotelExoneration : '',
      articles: articles,
      payments: dgiPayments,
      rate: (typeof USD_RATE !== 'undefined') ? USD_RATE : 0,
      providerService: '',
      // Valeur de la taxe B (TVA type B hôtelier) — lue localement depuis
      // la DB (cf. loadTaxBRate() / taxBValue). Envoyée à la DGI pour
      // garantir la cohérence du calcul de TVA B côté serveur.
      tax_b_value: bRate,
    };
  }

  async function postHotelFacture(dgiResp) {
    var dgiData = (dgiResp && dgiResp.data) ? dgiResp.data : {};
    var payload = {
      sejour_id: currentSejourId,
      extras_manuels: extrasManuels,
      paiements: (paiements || []).map(function (p) { return { mode: p.mode, montant: parseFloat(p.montant) || 0 }; }),
      check_out: ($('hotel-ft-checkout') && $('hotel-ft-checkout').checked) ? true : false,
      dgi_data: {
        dateDGI: dgiData.dateDGI || null,
        qrCode: dgiData.qrCode || '',
        codeDEFDGI: dgiData.codeDEFDGI || '',
        counters: dgiData.counters || null,
        nim: dgiData.nim || null,
        total: dgiData.total || null,
        vtotal: dgiData.vtotal || null,
        isf: dgiData.isf || null,
        comment: dgiResp.comment || dgiData.comment || null,
      },
    };
    var r = await jsonFetch('/api/hotel/factures', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (!r.ok || !r.data || !r.data.success) {
      throw new Error((r.data && r.data.message) || ('Erreur ' + r.status));
    }
    openReceiptModal(r.data, dgiResp);
  }


  // ===============================================================
  // TAX BREAKDOWN POST-DGI
  // Réutilise le format retourné par l'API DGI dans dgiResponse.data :
  //   data.ht_tva = JSON string {"data":{"ha":{"haa":...,"hab":...,"hac":...,"hap":...},
  //                                  "va":{"vaa":...,"vab":...,"vac":...,"vap":...},
  //                                  "ts":{"tsa":...,"tsb":...,"tsc":...,"tsp":...}}}
  //   data.ts     = idem niveau top
  // On boucle sur les catégories présentes et on rend HT/TS/TVA par
  // catégorie, comme dans renderServiceBillContent() de app.js.
  // ===============================================================
  function parseHtTva(dgiData) {
    var haData = {}, vaData = {}, tsData = {}, tsDataFromGroup = {};
    if (!dgiData) return { ha: {}, va: {}, ts: {}, tsGroup: {} };
    // Top-level ts (chaine JSON ou objet deja parse)
    try {
      if (dgiData.ts) {
        tsData = typeof dgiData.ts === 'string' ? JSON.parse(dgiData.ts) : dgiData.ts;
      }
    } catch (e) { }
    // ht_tva_group : wrapper 3 niveaux {success, message, data:{ha,va,ts}}
    // (fallback sur ht_tva au cas ou certains proxies DGI exposent l'autre cle)
    var rawGroup = dgiData.ht_tva_group || dgiData.ht_tva;
    try {
      if (rawGroup) {
        var parsed = typeof rawGroup === 'string' ? JSON.parse(rawGroup) : rawGroup;
        // parsed peut etre {data: {ha, va, ts}} OU {success, message, data: {ha, va, ts}}
        var inner = (parsed && parsed.data && parsed.data.ha) ? parsed.data : parsed;
        haData = (inner && inner.ha) || {};
        vaData = (inner && inner.va) || {};
        tsDataFromGroup = (inner && inner.ts) || {};
      }
    } catch (e) { }
    // Le TS du top-level prend le pas sur celui du groupe (le plus exhaustif),
    // sinon on garde celui du groupe.
    var finalTs = (tsData && Object.keys(tsData).length) ? tsData : tsDataFromGroup;
    return { ha: haData, va: vaData, ts: finalTs };
  }

  // Catégories DGI (A=exonéré, B=16, C=5, F=16 marché public ext, ...).
  // On n'affiche QUE celles qui ont une valeur non nulle.
  var HOTEL_TAX_CATEGORIES = [
    { key: 'haa', label: 'A', tax: 0, description: 'EXONERE ET HORS CHAMP', ts: 'tsa' },
    { key: 'hab', label: 'B', tax: 16, description: 'Taxable', ts: 'tsb' },
    { key: 'hac', label: 'C', tax: 5, description: 'Taxable', ts: 'tsc' },
    { key: 'had', label: 'D', tax: 0, description: 'Regimes derogatoires TVA', ts: 'tsd' },
    { key: 'hae', label: 'E', tax: 0, description: 'Exportation et operation assimilees', ts: 'tse' },
    { key: 'haf', label: 'F', tax: 16, description: 'TVA marche public a financement exterieur', ts: 'tsf' },
    { key: 'hag', label: 'G', tax: 5, description: 'TVA marche public a financement exterieur', ts: 'tsg' },
    { key: 'hap', label: 'P', tax: 1, description: 'TVA marche public a financement exterieur', ts: 'tsp' },
  ];

  function buildHotelReceiptTaxBreakdown(dgiData) {
    if (!dgiData) return '';
    var parsed = parseHtTva(dgiData);
    var ha = parsed.ha, va = parsed.va, ts = parsed.ts;
    var html = '';
    var totalTva = 0;
    HOTEL_TAX_CATEGORIES.forEach(function (cat) {
      var ht = parseFloat(ha[cat.key]) || 0;
      // la TVA s'appelle vaa/vab/vac/... — on récupère la lettre de fin
      var vaKey = 'va' + cat.key.slice(-1);
      var vat = parseFloat(va[vaKey]) || 0;
      var tsValue = parseFloat(ts[cat.ts]) || 0;
      if (Math.abs(ht) > 0 || Math.abs(vat) > 0 || Math.abs(tsValue) > 0) {
        var htLabel = cat.tax === 0 ? 'TOTAL' : 'HT';
        var rateTxt = cat.tax === 0 ? '' : (' ' + cat.tax + ' %');
        html += '<div class="receipt-total-row" style="font-size:11px; color:#333; padding-left:10px;">'
          + '<span>' + htLabel + '[' + cat.label + '] ' + cat.description + rateTxt + ' :</span>'
          + '<span>' + ht.toFixed(2) + ' Fc</span>'
          + '</div>';
        if (Math.abs(tsValue) > 0) {
          html += '<div class="receipt-total-row" style="font-size:11px; color:#666; padding-left:10px;">'
            + '<span>TS[' + cat.label + '] ' + cat.description + rateTxt + ' :</span>'
            + '<span>' + tsValue.toFixed(2) + ' Fc</span>'
            + '</div>';
        }
        if (Math.abs(vat) > 0) {
          html += '<div class="receipt-total-row" style="font-size:11px; color:#666; padding-left:10px;">'
            + '<span>TVA[' + cat.label + '] ' + cat.description + rateTxt + ' :</span>'
            + '<span>' + vat.toFixed(2) + ' Fc</span>'
            + '</div>';
          totalTva += vat;
        }
      }
    });
    if (totalTva > 0.01) {
      html += '<div class="receipt-total-row" style="font-size:11px; color:#000; font-weight:600; margin-top:6px; padding-top:6px; border-top:1px dashed #ccc;">'
        + '<span>Total TVA :</span>'
        + '<span>' + totalTva.toFixed(2) + ' Fc</span>'
        + '</div>';
    }
    return html;
  }

  function countHotelArticles(n, e, manuels) {
    return (n && n.lignes ? n.lignes.length : 0)
      + (e && e.lignes ? e.lignes.length : 0)
      + (manuels ? manuels.length : 0);
  }
  function openReceiptModal(saleData, dgiResp) {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var dgiData = (dgiResp && dgiResp.data) ? dgiResp.data : {};
    var isHomologuee = !(STORE_INFO.homologation === 0 || STORE_INFO.homologation === '0' || STORE_INFO.homologation === false);
    var total = saleData.total_final || previewData.total_final || 0;

    var itemsHtml = buildHotelReceiptItems(n, e, extrasManuels);
    var infoSection = buildHotelReceiptInfoSection();
    var totalsHtml = buildHotelReceiptTotals(total);
    var paymentsHtml = buildHotelReceiptPayments();
    // Sprint 7 — 2026-10-19 : titre dynamique basé sur le type sélectionné
    // par l'utilisateur (cf. window.hotelInvoiceType). Voir showPreview()
    // pour la version preview.
    var typeLabel = (typeof getInvoiceTypeLabel === 'function' && window.hotelInvoiceType)
      ? getInvoiceTypeLabel(window.hotelInvoiceType)
      : 'Facture de Vente';
    var now = new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    var dgiInfoHtml = '';
    if (isHomologuee && dgiData && (dgiData.codeDEFDGI || dgiData.counters || dgiData.nim)) {
      dgiInfoHtml =
        '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">' +
        '<div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite de la facture normalisee ---</div>' +
        '<div style="font-size:11px; color:#555; margin-top:4px;">' +
        (dgiData.codeDEFDGI ? 'CODE DEF/DGI: ' + escapeHtml(dgiData.codeDEFDGI) : '') +
        (dgiData.nim ? '<br>DEF NID : ' + escapeHtml(dgiData.nim) : '') +
        (dgiData.counters ? '<br>DEF Compteurs: ' + escapeHtml(dgiData.counters) : '') +
        (dgiData.dateDGI ? '<br>DEF Heure : ' + escapeHtml(dgiData.dateDGI) : '') +
        '</div></div>';
    }

    var receiptHtml =
      '<div class="receipt"><div class="receipt-header">' +
      (STORE_INFO.logo ? '<img src="' + STORE_INFO.logo + '" class="receipt-logo" alt="Logo">' : '') +
      '<div class="store-name">' + escapeHtml(STORE_INFO.name || '') + '</div>' +
      '<div class="store-info">' +
      '<div><strong>Point de vente :</strong> ' + escapeHtml(STORE_INFO.pdv || '') + '</div>' +
      '<div>Adresse : ' + escapeHtml(STORE_INFO.address || '') + '</div>' +
      '<div>Tel: ' + escapeHtml(STORE_INFO.phone || '') + '</div>' +
      (STORE_INFO.email ? '<div>Email: ' + escapeHtml(STORE_INFO.email) + '</div>' : '') +
      '<div>ID Nat: ' + escapeHtml(STORE_INFO.ice || '') + '</div>' +
      (STORE_INFO.nid ? '<div>NID: ' + escapeHtml(STORE_INFO.nid) + '</div>' : '') +
      (STORE_INFO.rccm ? '<div>RCCM: ' + escapeHtml(STORE_INFO.rccm) + '</div>' : '') +
      (STORE_INFO.isf ? '<div>Numero Impot: ' + escapeHtml(STORE_INFO.isf) + '</div>' : '') +
      '</div>' + infoSection +
      '</div>' +
      '<div class="receipt-meta" style="justify-content:center; font-size:14px; font-weight:600;">' + escapeHtml(typeLabel) + '</div>' +
      (window.hotelRefFacture ? '<div style="text-align:center; font-size:12px; color:#666;">Réf : ' + escapeHtml(window.hotelRefFacture) + '</div>' : '') +
      (window.hotelExoneration ? '<div style="text-align:center; font-size:11px; color:#888; font-style:italic;">Exonération : ' + escapeHtml(getExonerationLabel(window.hotelExoneration)) + '</div>' : '') +
      '<div class="receipt-items">' + itemsHtml + '</div>' +
      '<div class="receipt-totals">' + totalsHtml + paymentsHtml + buildHotelReceiptTaxBreakdown(dgiData) +
      (isHomologuee && STORE_INFO.isf ? '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:center;">ISF : ' + escapeHtml(dgiData.isf || STORE_INFO.isf) + '</div>' : '') +
      '<div class="receipt-total-row" style="font-size:11px; color:#000; font-weight:600;"><span>Nombre d\'article(s) :</span><span>' + countHotelArticles(n, e, extrasManuels) + '</span></div>' +
      '</div>' +
      dgiInfoHtml +
      '<div class="receipt-footer">' +
      '<div id="hotel-ft-qrcode-container" class="qrcode-container"></div>' +
      '<div class="barcode">FACTURE n°' + escapeHtml(saleData.numero_facture || '') + '</div>' +
      '<div style="font-size:11px; color:#333; margin-top:4px;">Date : ' + now + '</div>' +
      '<div class="thank-you">Merci de votre séjour !</div>' +
      '<div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div>' +
      '</div></div>';

    var receiptContent = $('receipt-content');
    if (receiptContent) receiptContent.innerHTML = receiptHtml;
    var modal = $('receipt-modal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }

    var qrContent = (dgiData && dgiData.qrCode) ? dgiData.qrCode : (saleData.numero_facture || '');
    if (typeof posCart !== 'undefined' && posCart.generateDGIQRCode && qrContent) {
      setTimeout(function () { posCart.generateDGIQRCode(qrContent, 'hotel-ft-qrcode-container'); }, 80);
    }
    var printBtn = $('print-receipt');
    if (printBtn) {
      printBtn.onclick = function () {
        var content = $('receipt-content') ? $('receipt-content').innerHTML : '';
        if (typeof _printReceiptContent === 'function') _printReceiptContent(content);
        else window.print();
      };
    }
  }
  function closeReceiptModal() {
    var modal = $('receipt-modal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  async function cancelExistingFacture() {
    if (!previewData || !previewData.existing_facture) return;
    var ex = previewData.existing_facture;
    if (!confirm('Annuler la facture ' + ex.numero_facture + ' ?\nCette opération est réservée aux managers.')) return;
    var r = await jsonFetch('/api/hotel/factures/' + ex.id + '/cancel', { method: 'POST' });
    if (r.ok && r.data && r.data.success) {
      loadSejour(currentSejourId);
    } else {
      alert((r.data && r.data.message) || 'Erreur');
    }
  }

  // Init
  var urlSejourId = parseInt(new URL(window.location.href).searchParams.get('sejour_id') || '0', 10);
  if (urlSejourId > 0) loadSejour(urlSejourId);

  // EXPOSITION GLOBALE (window.*) — requise car les onclick="..." du
  // markup PHP appellent ces fonctions. Doit rester DANS la closure
  // IIFE pour que les noms soient en scope.
  if (typeof window !== 'undefined') {
    window.openHotelInvoiceInfoModal = openHotelInvoiceInfoModal;
    window.closeHotelInvoiceInfoModal = closeHotelInvoiceInfoModal;
    window.confirmHotelInvoiceInfo = confirmHotelInvoiceInfo;
    window.addHotelModalPaymentLine = addHotelModalPaymentLine;
    window.removeHotelModalPaymentLine = removeHotelModalPaymentLine;
    window.addHotelRefDocLine = addHotelRefDocLine;
    window.removeHotelRefDocLine = removeHotelRefDocLine;
    window.syncHotelRefDocsCount = syncHotelRefDocsCount;
    window.cancelExistingFacture = cancelExistingFacture;
    window.closeReceiptModal = closeReceiptModal;
  }
})();

