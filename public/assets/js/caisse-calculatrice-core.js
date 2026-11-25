/**
 * Calculatrice de caisse — NOYAU PUR (aucun accès au DOM).
 *
 * Pourquoi un fichier séparé : les calculs de monnaie doivent être
 * vérifiables SANS navigateur, avec des montants attendus écrits à la main
 * (cf. tests/js/caisse-calculatrice-core.test.js). L'interface, elle, vit
 * dans `public/assets/js/caisse-calculatrice.js`.
 *
 * RÈGLE DE MONNAIE
 * ----------------
 * Le taux de change vient de l'API DGI du jour (1 USD = X Fc). Il n'est
 * JAMAIS codé en dur : l'ancien modal de paiement utilisait 2 300 Fc
 * figés, alors que le taux officiel était à 2 260,88 Fc le 25/11/2026
 * (cf. docs/CALCUL_TTC_TS.md, constat n°10 — le taux y était figé).
 *
 * Format : CommonJS si disponible (Node / tests), sinon global navigateur.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.CaisseCalculatriceCore = factory();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * Arrondi à 2 décimales (convention du projet : DECIMAL(10,2)).
     */
    function round2(value) {
        var n = Number(value);
        if (!isFinite(n)) return NaN;
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    /**
     * Conversion tolérante : "1 234,50" → 1234.5 ; valeur vide → 0.
     */
    function toNumber(value) {
        if (typeof value === 'number') return isFinite(value) ? value : 0;
        if (value === null || value === undefined || value === '') return 0;
        var cleaned = String(value).replace(/\s/g, '').replace(',', '.');
        var n = parseFloat(cleaned);
        return isNaN(n) ? 0 : n;
    }

    /**
     * Convertit un montant entre Fc et USD au taux DGI du jour.
     *
     * @param {number} amount Montant à convertir
     * @param {number} rate   Taux DGI : 1 USD = <rate> Fc
     * @param {string} unit   'usd' | 'fc' — unité du montant SAISI
     * @returns {{amountFc:number, amountUsd:number, rate:number, error:string|null}}
     */
    function convert(amount, rate, unit) {
        var value = toNumber(amount);
        var taux = toNumber(rate);

        if (!(taux > 0)) {
            return { amountFc: 0, amountUsd: 0, rate: taux, error: 'Taux DGI indisponible' };
        }

        var amountFc = (unit === 'usd') ? value * taux : value;
        var amountUsd = amountFc / taux;

        return {
            amountFc: round2(amountFc),
            amountUsd: round2(amountUsd),
            rate: taux,
            error: null
        };
    }

    /**
     * Calcule la monnaie à rendre (ou le manque à gagner) entre le montant
     * reçu par le client et le total à payer.
     *
     * @param {number} received Montant reçu par le client
     * @param {string} unit     'usd' | 'fc' — unité du montant reçu
     * @param {number} rate     Taux DGI : 1 USD = <rate> Fc
     * @param {number} totalFc  Total à payer, en Fc
     * @returns {{receivedFc:number, receivedUsd:number, totalFc:number,
     *           changeFc:number, changeUsd:number, missingFc:number,
     *           sufficient:boolean, error:string|null}}
     */
    function computeChange(received, unit, rate, totalFc) {
        var conv = convert(received, rate, unit);
        if (conv.error) {
            return {
                receivedFc: 0, receivedUsd: 0, totalFc: round2(toNumber(totalFc)),
                changeFc: 0, changeUsd: 0, missingFc: 0,
                sufficient: false, error: conv.error
            };
        }

        var total = toNumber(totalFc);
        var diff = conv.amountFc - total; // > 0 = à rendre, < 0 = insuffisant
        var sufficient = diff >= 0;

        return {
            receivedFc: conv.amountFc,
            receivedUsd: conv.amountUsd,
            totalFc: round2(total),
            // `changeFc` et `missingFc` ne sont jamais les deux non-nuls :
            // l'un vaut l'écart, l'autre vaut 0.
            changeFc: sufficient ? round2(Math.abs(diff)) : 0,
            changeUsd: sufficient ? round2(Math.abs(diff) / conv.rate) : 0,
            missingFc: sufficient ? 0 : round2(Math.abs(diff)),
            sufficient: sufficient,
            error: null
        };
    }

    return {
        round2: round2,
        toNumber: toNumber,
        convert: convert,
        computeChange: computeChange
    };
});
