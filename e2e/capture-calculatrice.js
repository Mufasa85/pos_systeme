/**
 * Capture de la calculatrice de caisse (contrôle visuel).
 * Lancement : BASE_URL=http://localhost node e2e/capture-calculatrice.js
 */
/* global posCart, APP_URL */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost';
const SESSION_ID = process.env.SESSION_ID || 'hermescaisse01';
const OUT_DIR = process.env.OUT_DIR || 'e2e-report';

function resolveChromiumPath() {
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

async function main() {
  const executablePath = resolveChromiumPath();
  const browser = await chromium.launch(executablePath ? { executablePath } : {});
  const context = await browser.newContext({
    baseURL: BASE_URL,
    viewport: { width: 1440, height: 900 },
    extraHTTPHeaders: { Cookie: 'PHPSESSID=' + SESSION_ID },
  });
  const page = await context.newPage();
  await page.goto('/caisse', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof posCart !== 'undefined', null, { timeout: 15000 });

  // Panier à 5 000 Fc (le cas d'usage : le client donne 10 USD)
  await page.evaluate(async () => {
    const res = await fetch(APP_URL + '/api/produits/page?limit=1&offset=0');
    const data = await res.json();
    const list = data.rows || data.produits || [];
    posCart.items = [
      {
        produit_id: list[0].id,
        nom: list[0].nom,
        prix: 4348.25,
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
  });

  await page.click('#calculator-toggle');
  await page.locator('#caisse-calculatrice.active').waitFor();
  await page.waitForTimeout(1500);
  await page.fill('#calcx-received', '10');
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(OUT_DIR, 'calcx-usd.png') });

  await page.click('#calcx-unit-fc');
  await page.fill('#calcx-received', '5000');
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(OUT_DIR, 'calcx-fc.png') });

  const mesure = await page.evaluate(() => {
    const m = document.querySelector('#caisse-calculatrice .modal-content');
    const r = m.getBoundingClientRect();
    return {
      modalWidth: Math.round(r.width),
      modalRight: Math.round(r.right),
      viewport: window.innerWidth,
      bodyScrollWidth: document.body.scrollWidth,
    };
  });
  console.log('MESURE ' + JSON.stringify(mesure));

  await browser.close();
  console.log('captures écrites dans ' + OUT_DIR);
}

main().catch(e => {
  console.error('ECHEC :', e.message);
  process.exit(1);
});
