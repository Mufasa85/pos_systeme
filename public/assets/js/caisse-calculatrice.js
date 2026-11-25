/**
 * Calculatrice de caisse — COUCHE INTERFACE.
 *
 * But : aider le vendeur à l'encaissement. Cas d'usage : le client doit
 * 5 000 Fc et donne 10 USD. Le vendeur tape 10 USD et lit :
 *   - ce que ça vaut en francs (au taux DGI du jour) ;
 *   - la monnaie à rendre, en francs et en dollars.
 *
 * Sources de vérité :
 *   - total à payer : `posCart.currentTotals.total` (le TTC du panier) ;
 *   - taux de change : `GET /api/currency` (API DGI du jour via OSAT).
 *
 * ⚠️ FICHIER AUTONOME : `public/assets/js/app.js` est modifié par un autre
 * développeur, on ne le touche pas. Le total du panier est lu à l'ouverture
 * (et non capturé au chargement), donc le module survit à un rechargement
 * de app.js.
 */
(function () {
    'use strict';

    var core = (typeof window !== 'undefined' && window.CaisseCalculatriceCore)
        ? window.CaisseCalculatriceCore
        : (typeof require === 'function' ? require('./caisse-calculatrice-core.js') : null);

    if (!core) {
        console.error('[Calculatrice] Noyau de calcul introuvable (caisse-calculatrice-core.js)');
        return;
    }

    var RATE_CACHE_KEY = 'caisse_calculatrice_taux_dgi';
    var RATE_CACHE_TTL = 30 * 60 * 1000; // 30 min — même durée que app.js

    // État local
    var state = {
        taux: null,      // { rate, date, timestamp }
        unit: 'usd',     // unité du montant saisi
        ratePromise: null
    };

    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return document.querySelectorAll(sel); }

    function fmt(amount) {
        var n = Number(amount);
        if (!isFinite(n)) return '0.00';
        return n.toFixed(2);
    }

    function setText(sel, text) {
        var el = $(sel);
        if (el) el.textContent = text;
    }

    // ── Taux DGI du jour ───────────────────────────────────────────────
    function readCachedRate() {
        try {
            var raw = localStorage.getItem(RATE_CACHE_KEY);
            if (!raw) return null;
            var cached = JSON.parse(raw);
            if (!cached || !cached.rate || !cached.timestamp) return null;
            if (Date.now() - cached.timestamp > RATE_CACHE_TTL) return null;
            return cached;
        } catch (e) {
            return null;
        }
    }

    function writeCachedRate(payload) {
        try {
            localStorage.setItem(RATE_CACHE_KEY, JSON.stringify(payload));
        } catch (e) { /* cache indisponible : on continue */ }
    }

    /**
     * Taux DGI (1 USD = X Fc). Requête dédupliquée : deux appels concurrents
     * partagent la même promesse, sinon la réponse la plus ancienne peut
     * arriver après la plus récente et l'écraser.
     */
    function getDgiRate(force) {
        if (!force) {
            if (state.taux) return Promise.resolve(state.taux);
            var cached = readCachedRate();
            if (cached) {
                state.taux = cached;
                return Promise.resolve(cached);
            }
            if (state.ratePromise) return state.ratePromise;
        }

        state.ratePromise = fetch(APP_URL + '/api/currency', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var row = (data && data.data && data.data[0]) ? data.data[0] : null;
                var rate = row ? parseFloat(row.rate) : 0;
                if (!rate || rate <= 1) throw new Error('Taux DGI indisponible');
                var payload = { rate: rate, date: row.date || null, timestamp: Date.now() };
                state.taux = payload;
                writeCachedRate(payload);
                return payload;
            })
            .catch(function (e) {
                console.warn('[Calculatrice] Taux DGI indisponible:', e);
                return null;
            })
            .finally(function () { state.ratePromise = null; });

        return state.ratePromise;
    }

    function formatDate(iso) {
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch (e) {
            return iso;
        }
    }

    // ── Calcul ─────────────────────────────────────────────────────────
    /**
     * Garde de séquence : plusieurs appels asynchrones peuvent se répondre
     * dans le désordre ; seul le dernier calcul en cours écrit dans le DOM.
     */
    var calcSeq = 0;

    function updateResults(rate) {
        var totalFc = (typeof posCart !== 'undefined' && posCart.currentTotals)
            ? (parseFloat(posCart.currentTotals.total) || 0)
            : 0;

        var received = core.toNumber($('#calcx-received') ? $('#calcx-received').value : 0);
        var seq = ++calcSeq;

        // Total à payer
        setText('#calcx-total', fmt(totalFc) + ' Fc');

        var hasCart = totalFc > 0;
        var emptyEl = $('#calcx-empty');
        var resultEl = $('#calcx-result');
        if (emptyEl) emptyEl.style.display = hasCart ? 'none' : 'block';
        if (resultEl) resultEl.style.display = hasCart ? 'block' : 'none';

        if (!hasCart) {
            setText('#calcx-total-usd', '—');
            return;
        }

        getDgiRate(false).then(function (payload) {
            if (seq !== calcSeq) return; // un calcul plus récent a pris la main

            var rateLabel = $('#calcx-rate-text');
            if (rateLabel) {
                rateLabel.textContent = payload
                    ? ('1 USD = ' + fmt(payload.rate) + ' Fc' +
                       (payload.date ? '  ·  taux du ' + formatDate(payload.date) : '  ·  taux DGI du jour'))
                    : 'Taux DGI indisponible — vérifiez la connexion internet.';
            }

            if (!payload) {
                setText('#calcx-total-usd', '—');
                setText('#calcx-out-received', '—');
                setText('#calcx-out-total', fmt(totalFc) + ' Fc');
                setText('#calcx-out-change', '—');
                setText('#calcx-out-change-usd', '—');
                setText('#calcx-out-missing', '—');
                return;
            }

            setText('#calcx-total-usd', '≈ $' + fmt(totalFc / payload.rate) + ' USD');

            var r = core.computeChange(received, state.unit, payload.rate, totalFc);

            setText('#calcx-out-received',
                state.unit === 'usd' ? (fmt(r.receivedFc) + ' Fc') : (fmt(r.receivedFc) + ' Fc'));
            setText('#calcx-out-total', fmt(r.totalFc) + ' Fc');

            var rowChange = $('#calcx-row-change');
            var rowChangeUsd = $('#calcx-row-change-usd');
            var rowMissing = $('#calcx-row-missing');

            if (r.sufficient) {
                if (rowChange) rowChange.style.display = '';
                if (rowChangeUsd) rowChangeUsd.style.display = '';
                if (rowMissing) rowMissing.style.display = 'none';
                setText('#calcx-out-change', fmt(r.changeFc) + ' Fc');
                setText('#calcx-out-change-usd', '≈ $' + fmt(r.changeUsd) + ' USD');
            } else {
                if (rowChange) rowChange.style.display = 'none';
                if (rowChangeUsd) rowChangeUsd.style.display = 'none';
                if (rowMissing) rowMissing.style.display = '';
                setText('#calcx-out-missing', fmt(r.missingFc) + ' Fc');
            }
        });
    }

    // ── Unité saisie ───────────────────────────────────────────────────
    function setUnit(unit) {
        state.unit = (unit === 'fc') ? 'fc' : 'usd';

        var usdBtn = $('#calcx-unit-usd');
        var fcBtn = $('#calcx-unit-fc');
        if (usdBtn) usdBtn.classList.toggle('active', state.unit === 'usd');
        if (fcBtn) fcBtn.classList.toggle('active', state.unit === 'fc');

        var input = $('#calcx-received');
        if (input) {
            input.placeholder = (state.unit === 'usd') ? 'Ex: 10' : 'Ex: 5000';
        }

        updateResults();
    }

    // ── Ouverture / fermeture ──────────────────────────────────────────
    var previousBodyOverflow = null;

    function openCalculatrice() {
        var modal = $('#caisse-calculatrice');
        if (!modal) return;

        modal.classList.add('active');
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // Le montant est saisi à chaque ouverture : on ne laisse pas un
        // ancien montant traîner (il induirait le vendeur en erreur).
        var input = $('#calcx-received');
        if (input) input.value = '';

        setUnit(state.unit);
        updateResults();

        setTimeout(function () {
            if (input) input.focus();
        }, 120);
    }

    function closeCalculatrice() {
        var modal = $('#caisse-calculatrice');
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = previousBodyOverflow === null ? '' : previousBodyOverflow;
        previousBodyOverflow = null;
    }

    // ── Initialisation ─────────────────────────────────────────────────
    function init() {
        var modal = $('#caisse-calculatrice');
        if (!modal) return; // pas sur /caisse

        // Bouton « Calculatrice » du panier.
        // ⚠️ `#calculator-toggle` est un <button> : il ne porte plus
        // d'attribut onchange (c'était une case à cocher, qui détournait
        // « Valider la vente » vers l'ancien modal de paiement à taux figé).
        var toggle = $('#calculator-toggle');
        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                openCalculatrice();
            });
        }

        var received = $('#calcx-received');
        if (received) received.addEventListener('input', function () { updateResults(); });

        var usdBtn = $('#calcx-unit-usd');
        if (usdBtn) usdBtn.addEventListener('click', function () { setUnit('usd'); });

        var fcBtn = $('#calcx-unit-fc');
        if (fcBtn) fcBtn.addEventListener('click', function () { setUnit('fc'); });

        var refresh = $('#calcx-rate-refresh');
        if (refresh) {
            refresh.addEventListener('click', function () {
                state.taux = null;
                getDgiRate(true).then(function () { updateResults(); });
            });
        }

        var reset = $('#calcx-reset');
        if (reset) {
            reset.addEventListener('click', function () {
                var input = $('#calcx-received');
                if (input) input.value = '';
                updateResults();
                if (input) input.focus();
            });
        }

        var validate = $('#calcx-validate');
        if (validate) {
            validate.addEventListener('click', function () {
                var totalFc = (typeof posCart !== 'undefined' && posCart.currentTotals)
                    ? (parseFloat(posCart.currentTotals.total) || 0)
                    : 0;
                if (totalFc <= 0) {
                    alert('Le panier est vide : ajoutez des articles avant d\'encaisser.');
                    return;
                }

                var amount = core.toNumber($('#calcx-received') ? $('#calcx-received').value : 0);
                if (amount <= 0) {
                    alert('Saisissez le montant donné par le client.');
                    return;
                }

                // Le montant encaissé est exprimé en FONDS (c'est l'unité de
                // la facture) : un client qui paie en dollars donne
                // receivedFc, pas receivedUsd.
                var rate = state.taux ? state.taux.rate : 0;
                var amountFc = core.convert(amount, rate, state.unit).amountFc;
                if (!(amountFc > 0)) {
                    alert('Taux DGI indisponible : impossible de calculer le montant encaissé.');
                    return;
                }

                closeCalculatrice();

                // On ouvre le modal d'encaissement et on y pré-remplit le
                // montant donné par le client (en Fc). Le récapitulatif du
                // modal affichera alors la monnaie à rendre.
                if (typeof openInvoiceInfoModal === 'function') {
                    openInvoiceInfoModal();

                    // openInvoiceInfoModal() est synchrone : la ligne de
                    // paiement existe déjà quand il rend la main. On
                    // remplace le montant (le total du panier) par le
                    // montant réellement reçu.
                    var amountInput = document.querySelector('#modal-payments-list .modal-payment-amount');
                    if (amountInput) {
                        amountInput.value = amountFc.toFixed(2);
                        amountInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            });
        }

        var closeBtn = $('#calcx-close');
        if (closeBtn) closeBtn.addEventListener('click', closeCalculatrice);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeCalculatrice();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeCalculatrice();
            }
        });
    }

    // API publique
    window.CaisseCalculatrice = {
        open: openCalculatrice,
        close: closeCalculatrice,
        getRate: function (force) { return getDgiRate(force); }
    };
    window.openCaisseCalculatrice = openCalculatrice;
    window.closeCaisseCalculatrice = closeCalculatrice;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
