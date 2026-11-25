/**
 * Tests du NOYAU PUR de la calculatrice de caisse.
 *
 * Lancement :  node --test tests/js/caisse-calculatrice-core.test.js
 * (aucune dépendance externe : le noyau est un module CommonJS sans DOM)
 */
const test = require('node:test');
const assert = require('node:assert');
const path = require('node:path');

const core = require(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'caisse-calculatrice-core.js'));

// Taux DGI officiel du 25/11/2026 (relevé sur l'API DGI via OSAT).
const TAUX = 2260.88;

test('round2 arrondit à 2 décimales (convention DECIMAL(10,2))', () => {
    assert.strictEqual(core.round2(1.005), 1.01);
    assert.strictEqual(core.round2(22608.8), 22608.8);
    assert.strictEqual(core.round2(1234.5678), 1234.57);
});

test('toNumber tolère la virgule, les espaces et les valeurs vides', () => {
    assert.strictEqual(core.toNumber('1 234,50'), 1234.5);
    assert.strictEqual(core.toNumber(''), 0);
    assert.strictEqual(core.toNumber(null), 0);
    assert.strictEqual(core.toNumber('abc'), 0);
});

// ── Conversion ───────────────────────────────────────────────────────
test('convert : 10 USD valent 22 608,80 Fc au taux DGI du jour', () => {
    const r = core.convert(10, TAUX, 'usd');
    assert.strictEqual(r.amountFc, 22608.8);
    assert.strictEqual(r.amountUsd, 10);
    assert.strictEqual(r.error, null);
});

test('convert : un montant en Fc reste en Fc', () => {
    const r = core.convert(5000, TAUX, 'fc');
    assert.strictEqual(r.amountFc, 5000);
    assert.strictEqual(r.amountUsd, 2.21);
});

test('convert : taux indisponible → erreur explicite, pas de division par zéro', () => {
    const r = core.convert(10, 0, 'usd');
    assert.strictEqual(r.amountFc, 0);
    assert.match(r.error, /indisponible/i);
});

// ── Monnaie à rendre ─────────────────────────────────────────────────
// Cas réel : le client doit 5 000 Fc et donne 10 USD.
test('computeChange : 10 USD pour 5 000 Fc → monnaie à rendre', () => {
    const r = core.computeChange(10, 'usd', TAUX, 5000);
    assert.strictEqual(r.receivedFc, 22608.8);
    assert.strictEqual(r.totalFc, 5000);
    assert.strictEqual(r.changeFc, 17608.8);
    assert.strictEqual(r.sufficient, true);
    assert.strictEqual(r.missingFc, 0);
});

test('computeChange : la monnaie à rendre est aussi donnée en dollars', () => {
    const r = core.computeChange(10, 'usd', TAUX, 5000);
    assert.strictEqual(r.changeUsd, 7.79);
});

test('computeChange : montant insuffisant → manque à gagner', () => {
    const r = core.computeChange(1, 'usd', TAUX, 5000);
    assert.strictEqual(r.receivedFc, 2260.88);
    assert.strictEqual(r.sufficient, false);
    assert.strictEqual(r.missingFc, 2739.12);
    assert.strictEqual(r.changeFc, 0, 'la monnaie à rendre est nulle quand le montant manque');
    assert.strictEqual(r.changeUsd, 0);
});

test('computeChange : paiement en francs, montant exact', () => {
    const r = core.computeChange(5000, 'fc', TAUX, 5000);
    assert.strictEqual(r.sufficient, true);
    assert.strictEqual(r.changeFc, 0);
});

test('computeChange : paiement en francs, léger excédent', () => {
    const r = core.computeChange(5200, 'fc', TAUX, 5000);
    assert.strictEqual(r.sufficient, true);
    assert.strictEqual(r.changeFc, 200);
    assert.strictEqual(r.changeUsd, 0.09);
});

test('computeChange : taux indisponible → résultat vide et erreur', () => {
    const r = core.computeChange(10, 'usd', 0, 5000);
    assert.strictEqual(r.error, 'Taux DGI indisponible');
    assert.strictEqual(r.sufficient, false);
    assert.strictEqual(r.changeFc, 0);
});
