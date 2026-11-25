/**
 * Vérification E2E de la calculatrice de caisse.
 *
 * Lancement : BASE_URL=http://localhost node e2e/caisse-calculatrice.spec.js
 *
 * Le test utilise une session PHP écrite à la main (cookie PHPSESSID) :
 * il n'a besoin d'aucun mot de passe. Le panier est rempli par l'API.
 */
/* global posCart, APP_URL */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost';
const SESSION_ID = process.env.SESSION_ID || 'hermescaisse01';
const SESSION_DIR = process.env.SESSION_DIR || 'C:/xampp/tmp';

function resolveChromiumPath() {
  if (process.env.CHROMIUM_PATH) return process.env.CHROMIUM_PATH;
  const root = path.join(process.env.LOCALAPPDATA || '', 'ms-playwright');
  if (!fs.existsSync(root)) return null;
  const dirs = fs
    .readdirSync(root)
    .filter(d => d.startsWith('chromium-'))
    .sort((a, b) => parseInt(b.split('-')[1], 10) - parseInt(a.split('-')[1], 10));
  for (const dir of dirs) {
    const exe = path.join(root, dir, 'chrome-win64', 'chrome.exe');
    if (fs.existsSync(exe)) return exe;
  }
  return null;
}

function ok(msg) {
  console.log('OK  ' + msg);
}

async function main() {
  const sessionFile = path.join(SESSION_DIR, 'sess_' + SESSION_ID);
  if (!fs.existsSync(sessionFile)) throw new Error('Session PHP introuvable : ' + sessionFile);

  const executablePath = resolveChromiumPath();
  const browser = await chromium.launch(executablePath ? { executablePath } : {});
  const context = await browser.newContext({
    baseURL: BASE_URL,
    viewport: { width: 1440, height: 900 },
    extraHTTPHeaders: { Cookie: 'PHPSESSID=' + SESSION_ID },
  });
  const page = await context.newPage();

  const errors = [];
  page.on('console', m => {
    if (m.type() === 'error') errors.push(m.text());
  });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));

  await page.goto('/caisse', { waitUntil: 'domcontentloaded' });

  // 1) Le bouton Calculatrice du panier est un <button> (plus une case)
  const isButton = await page.evaluate(() => {
    const el = document.querySelector('#calculator-toggle');
    return el ? el.tagName : null;
  });
  if (isButton !== 'BUTTON')
    throw new Error('#calculator-toggle devrait être un BUTTON, trouvé : ' + isButton);
  ok('#calculator-toggle est un <button> (plus une case à cocher)');

  const oldCheckbox = await page.locator('#calc-mode-checkbox').count();
  if (oldCheckbox !== 0) throw new Error('La case #calc-mode-checkbox existe encore');
  ok("l'ancienne case à cocher a disparu");

  // 2) Remplir le panier via l'API produits + le panier du front
  await page.waitForFunction(
    () => typeof posCart !== 'undefined' && Array.isArray(posCart.items),
    null,
    { timeout: 15000 }
  );
  const panierRempli = await page.evaluate(async () => {
    const res = await fetch(APP_URL + '/api/produits/page?limit=5&offset=0');
    const data = await res.json();
    const list = data.rows || data.produits || data.data || (Array.isArray(data) ? data : []);
    if (!list.length) return { ok: false, raison: 'aucun produit' };
    // On ajoute un produit au panier pour disposer d'un total.
    posCart.items = [
      {
        produit_id: list[0].id,
        nom: list[0].nom,
        prix: parseFloat(list[0].prix),
        quantite: 1,
        tax_rate: parseFloat(list[0].tax_rate) || 0,
        tax_etiquette: list[0].tax_etiquette || '',
        product_type: 'unite',
        prod_service: list[0].prod_service || '',
        remise_type: '%',
        remise_value: 0,
        taxe_specifique_type: '%',
        taxe_specifique_value: 0,
      },
    ];
    posCart.renderCart();
    return { ok: true, total: posCart.currentTotals.total, nom: list[0].nom };
  });
  if (!panierRempli.ok) throw new Error('Panier non rempli : ' + panierRempli.raison);
  ok('panier rempli : ' + panierRempli.nom + ' → total ' + panierRempli.total.toFixed(2) + ' Fc');

  // Helper : impose un total de panier arbitraire (le panier réel contient
  // un produit à 1 000 000 Fc, hors échelle pour le cas d'usage visé).
  const setTotal = async montant =>
    page.evaluate(m => {
      posCart.items[0].prix = m;
      posCart.items[0].taxe_specifique_value = 0;
      posCart.items[0].taxe_specifique_type = '%';
      posCart.renderCart();
      return posCart.currentTotals.total;
    }, montant);

  const totalPanier = await setTotal(5000);
  ok('total de panier de travail : ' + totalPanier.toFixed(2) + ' Fc');

  // 3) Ouvrir la calculatrice depuis le bouton du panier
  await page.click('#calculator-toggle');
  await page.locator('#caisse-calculatrice.active').waitFor({ timeout: 10000 });
  ok('modale ouverte via le bouton du panier');

  // 4) Le total du panier est repris dans la modale
  const totalAffiche = await page.textContent('#calcx-total');
  if (!totalAffiche.includes(totalPanier.toFixed(2))) {
    throw new Error(
      'Total non repris dans la modale : ' + totalAffiche + ' vs ' + totalPanier.toFixed(2)
    );
  }
  ok('total du panier repris : ' + totalAffiche.trim());

  // 5) Le taux DGI du jour s'affiche
  await page.waitForFunction(
    () => {
      const t = document.querySelector('#calcx-rate-text');
      return t && /\d/.test(t.textContent) && !/Chargement|indisponible/i.test(t.textContent);
    },
    null,
    { timeout: 25000 }
  );
  const rateText = (await page.textContent('#calcx-rate-text')).trim();
  // ⚠️ On ne parse PAS le taux depuis le texte affiché : il contient la
  // date (« 1 USD = 2260.88 Fc · taux du 25/11/2026 ») et le nombre du
  // jour s'y glisserait. On lit la valeur exposée par le module.
  const rate = await page.evaluate(async () => {
    const payload = await window.CaisseCalculatrice.getRate();
    return payload ? payload.rate : 0;
  });
  if (!(rate > 0)) throw new Error('Taux DGI non exposé par le module');
  ok('taux DGI affiché : ' + rateText + ' (valeur utilisée : ' + rate + ')');

  // 6) Cas réel : le client donne 10 USD
  await page.fill('#calcx-received', '10');
  await page.waitForTimeout(800);

  const recu = (await page.textContent('#calcx-out-received')).trim();
  const changeFc = (await page.textContent('#calcx-out-change')).trim();
  const changeUsd = (await page.textContent('#calcx-out-change-usd')).trim();

  const attenduFc = 10 * rate;
  if (!recu.includes(attenduFc.toFixed(2))) {
    throw new Error('Montant reçu faux : ' + recu + ' (attendu ' + attenduFc.toFixed(2) + ' Fc)');
  }
  ok('10 USD → ' + recu);

  const attenduChange = attenduFc - totalPanier;
  if (!changeFc.includes(attenduChange.toFixed(2))) {
    throw new Error(
      'Monnaie à rendre fausse : ' + changeFc + ' (attendu ' + attenduChange.toFixed(2) + ' Fc)'
    );
  }
  ok('monnaie à rendre : ' + changeFc + ' / ' + changeUsd);

  // 7) Montant insuffisant → bandeau rouge
  await page.fill('#calcx-received', '0.5');
  await page.waitForTimeout(600);
  const missingVisible = await page.isVisible('#calcx-row-missing');
  if (!missingVisible) throw new Error("Le bandeau « montant insuffisant » ne s'affiche pas");
  const missing = (await page.textContent('#calcx-out-missing')).trim();
  ok('montant insuffisant → il manque ' + missing);

  // 8) Bascule en francs
  await page.click('#calcx-unit-fc');
  await page.fill('#calcx-received', String(totalPanier.toFixed(2)));
  await page.waitForTimeout(600);
  const changeZero = (await page.textContent('#calcx-out-change')).trim();
  if (!changeZero.startsWith('0.00'))
    throw new Error('Paiement exact en Fc : change = ' + changeZero);
  ok('paiement exact en Fc → monnaie à rendre ' + changeZero);

  // 9) Fermeture par Échap
  await page.keyboard.press('Escape');
  await page.waitForFunction(
    () => !document.querySelector('#caisse-calculatrice').classList.contains('active'),
    null,
    { timeout: 5000 }
  );
  ok('fermeture par Échap');

  // 9bis) « Encaisser ce montant » → le modal d'encaissement s'ouvre avec
  //        le montant donné par le client (en Fc), pas le total du panier.
  await page.click('#calculator-toggle');
  await page.locator('#caisse-calculatrice.active').waitFor({ timeout: 10000 });
  await page.waitForTimeout(800);
  // L'unité active est mémorisée d'une ouverture à l'autre : on repart
  // explicitement en dollars pour ce cas.
  await page.click('#calcx-unit-usd');
  await page.fill('#calcx-received', '10');
  await page.waitForTimeout(800);
  await page.click('#calcx-validate');
  await page.waitForTimeout(900);

  const modalEncaissement = await page.evaluate(() => ({
    actif: document.querySelector('#invoice-info-modal')?.classList.contains('active'),
    calculatriceFermee: !document
      .querySelector('#caisse-calculatrice')
      ?.classList.contains('active'),
    montant: document.querySelector('#modal-payments-list .modal-payment-amount')?.value,
    resume: document.querySelector('#modal-payment-summary-label')?.textContent,
    resumeMontant: document.querySelector('#modal-payment-summary-amount')?.textContent,
  }));
  if (!modalEncaissement.actif) throw new Error("Le modal d'encaissement ne s'est pas ouvert");
  if (!modalEncaissement.calculatriceFermee)
    throw new Error("La calculatrice ne s'est pas refermée");
  const attenduEncaisse = (10 * rate).toFixed(2);
  if (modalEncaissement.montant !== attenduEncaisse) {
    throw new Error(
      'Montant encaissé incorrect : ' +
        modalEncaissement.montant +
        ' (attendu ' +
        attenduEncaisse +
        ')'
    );
  }
  ok(
    "« Encaisser ce montant » → modal d'encaissement pré-rempli : " +
      modalEncaissement.montant +
      ' Fc'
  );
  ok(
    "le modal d'encaissement affiche : " +
      (modalEncaissement.resume || '—') +
      ' ' +
      (modalEncaissement.resumeMontant || '')
  );

  // On referme le modal d'encaissement pour la suite du test.
  await page.evaluate(() => {
    const m = document.querySelector('#invoice-info-modal');
    if (m) m.classList.remove('active');
  });

  // 10) « Valider la vente » ouvre directement le modal d'encaissement
  //      (l'ancien détour par le modal de paiement a disparu)
  await page.click('#show-preview');
  await page.waitForTimeout(700);
  const paymentModalActive = await page.evaluate(() =>
    document.querySelector('#payment-modal')?.classList.contains('active')
  );
  const invoiceModalActive = await page.evaluate(() =>
    document.querySelector('#invoice-info-modal')?.classList.contains('active')
  );
  if (paymentModalActive) {
    throw new Error("« Valider la vente » ouvre encore l'ancien modal de paiement");
  }
  if (!invoiceModalActive) {
    throw new Error("« Valider la vente » n'ouvre pas le modal d'encaissement");
  }
  ok("« Valider la vente » ouvre le modal d'encaissement (plus de détour)");

  if (errors.length) {
    console.log('--- erreurs console ---');
    errors.forEach(e => console.log('  ' + e));
    throw new Error(errors.length + ' erreur(s) console');
  }
  ok('aucune erreur console');

  await page.screenshot({ path: 'e2e-report/caisse-calculatrice.png' });
  await browser.close();
  console.log('\nTOUS LES CHECKS PASSENT');
}

main().catch(e => {
  console.error('ECHEC :', e.message);
  process.exit(1);
});
