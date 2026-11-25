# Calculatrice de caisse — conversion au taux DGI du jour

> **Objet** : aide au vendeur au moment de l'encaissement. Le client doit
> 5 000 Fc et donne 10 USD : le vendeur tape `10` et lit directement ce que
> ça vaut en francs et la monnaie à rendre, au taux officiel de la DGI.

> **Projet** : `sys-pos/pos_systeme` (POS + PMS) · **Page** : `/caisse`
> **Date** : 2026-11-25

---

## 1. Ce que fait la calculatrice

Le bouton **« Calculatrice »** du panier (à gauche de « Valider la vente »)
ouvre une modale qui affiche :

| Élément | Source |
|---|---|
| Total à payer (TTC) | `posCart.currentTotals.total` — le montant affiché dans le panier |
| Taux de change | `GET /api/currency` (API DGI du jour, exposée par le proxy OSAT) |
| Montant donné par le client | saisie du vendeur, en USD ou en Fc |
| Montant reçu (en Fc) | `montant × taux` si USD, `montant` si Fc |
| Monnaie à rendre | `montant reçu − total à payer`, en Fc **et** en USD |
| Montant insuffisant | `total à payer − montant reçu`, en Fc (bandeau rouge) |

Le bouton **« Encaisser ce montant »** ferme la calculatrice, ouvre le modal
d'encaissement (`#invoice-info-modal`) et y **pré-remplit le montant donné par
le client** (converti en Fc). Le récapitulatif du modal d'encaissement
affiche alors la monnaie à rendre.

---

## 2. ⚠️ Ce que le bouton faisait AVANT

C'était une **case à cocher**, pas un bouton :

```html
<label id="calculator-toggle" class="calculator-radio">
  <input type="checkbox" id="calc-mode-checkbox" onchange="toggleCalcMode(this.checked)">
```

Cochée, elle activait `posCart.calcMode`, et `showPreview()` déviait alors
« Valider la vente » vers l'ancien modal de paiement (`#payment-modal`),
qui calculait la monnaie à rendre avec un **taux de change FIGÉ à 2 300
Fc** — alors que le taux officiel était à **2 260,88 Fc** le 25/11/2026.

C'est le constat n°10 de `docs/CALCUL_TTC_TS.md` (« le modal de paiement
utilise un taux de change fixe (2300) alors que le panier utilise le taux
dynamique de l'API : la monnaie à rendre peut être fausse »).

**Désormais** :
- le contrôle est un `<button>` qui ouvre la calculatrice ;
- « Valider la vente » ouvre directement le modal d'encaissement ;
- le taux est celui de l'API DGI du jour, jamais codé en dur.

---

## 3. Fichiers

| Fichier | Rôle |
|---|---|
| `public/assets/js/caisse-calculatrice-core.js` | **Noyau pur** (aucun DOM) : `convert()`, `computeChange()`. Testable sans navigateur. |
| `public/assets/js/caisse-calculatrice.js` | Interface : ouvre la modale, appelle les API, écrit dans le DOM. |
| `app/views/partials/caisse-calculatrice.php` | La modale (HTML + CSS namespacé `calcx-`). |
| `app/views/caisse.php` | Bouton du panier + inclusion du partial + chargement des scripts. |
| `tests/js/caisse-calculatrice-core.test.js` | 11 tests unitaires du noyau (montants attendus écrits à la main). |
| `e2e/caisse-calculatrice.spec.js` | 14 vérifications dans Chromium (bouton, taux, conversion, encaissement). |
| `e2e/capture-calculatrice.js` | Captures d'écran (contrôle visuel). |

### Pourquoi des fichiers séparés, et pas un ajout dans `app.js`

`public/assets/js/app.js` était en cours de modification par un autre
développeur sur ce dépôt. La calculatrice est donc **entièrement autonome** :
elle ne modifie pas `app.js`, elle lit `posCart.currentTotals` à l'ouverture
du modal (pas au chargement), et elle expose son propre point d'entrée
`window.openCaisseCalculatrice()`.

Toutes les classes CSS sont préfixées `calcx-` : aucune collision possible
avec `.btn`, `.modal`, `.modal-content`… du POS.

---

## 4. Formule

```
taux       = API DGI du jour            (1 USD = X Fc)
reçu (Fc)  = montant_saisi × taux       si l'unité est USD
             montant_saisi              si l'unité est Fc
change (Fc) = reçu − total_à_payer      (≥ 0 → monnaie à rendre)
change (USD) = change (Fc) ÷ taux
manque (Fc) = total_à_payer − reçu      (< 0 → montant insuffisant)
```

Arrondi à 2 décimales partout (convention du projet : `DECIMAL(10,2)`).

---

## 5. Vérification

```bash
# Tests unitaires du noyau (aucune dépendance)
node --test tests/js/caisse-calculatrice-core.test.js

# Vérification navigateur (nécessite un serveur + une session)
BASE_URL=http://localhost node e2e/caisse-calculatrice.spec.js
```

Résultats au 25/11/2026 (taux DGI relevé : 1 USD = 2 260,88 Fc) :

```
# tests 11   # pass 11   # fail 0

OK  #calculator-toggle est un <button> (plus une case à cocher)
OK  l'ancienne case à cocher a disparu
OK  total du panier repris : 5000.00 Fc
OK  taux DGI affiché : 1 USD = 2260.88 Fc · taux du 25/11/2026
OK  10 USD → 22608.80 Fc
OK  monnaie à rendre : 17608.80 Fc / ≈ $7.79 USD
OK  montant insuffisant → il manque 3869.56 Fc
OK  paiement exact en Fc → monnaie à rendre 0.00 Fc
OK  fermeture par Échap
OK  « Encaisser ce montant » → modal d'encaissement pré-rempli : 22608.80 Fc
OK  le modal d'encaissement affiche : Monnaie à rendre: 17608.80 Fc
OK  « Valider la vente » ouvre le modal d'encaissement (plus de détour)
OK  aucune erreur console
```

---

## 6. Points à connaître

- **L'unité est mémorisée** d'une ouverture à l'autre (`USD` par défaut).
  Le montant est remis à zéro à chaque ouverture — un ancien montant
  traîner induirait le vendeur en erreur.
- **Aucun appel réseau au chargement de la page** : `/api/currency` et
  `/api/taxes` ne sont appelés qu'à l'ouverture de la modale.
- **Le taux est mis en cache 30 minutes** dans `localStorage`
  (clé `caisse_calculatrice_taux_dgi`), comme le fait `app.js`.
- **Les réponses asynchrones sont protégées par un numéro de séquence** :
  sans lui, une réponse arrivée après une autre écrasait le résultat affiché
  (symptôme constaté pendant le développement : « 0.00 USD » malgré une
  saisie correcte).
- **La calculatrice ne modifie pas le total de la facture** : elle informe.
  Seul « Encaisser ce montant » reporte le montant reçu dans le modal
  d'encaissement, où le caissier valide.
- **Le taux n'est jamais figé** : il vient de l'API DGI. Si l'API est
  indisponible, la modale affiche « Taux DGI indisponible » et le bouton
  « Encaisser ce montant » refuse d'encaisser plutôt que de deviner un taux.

---

## 7. Suite possible (non fait, hors périmètre)

- Le modal de paiement historique (`#payment-modal`, taux figé à 2 300) est
  devenu inatteignable : il reste dans `app/views/caisse.php` et dans
  `app.js` (`showPaymentModal`, `calculateChange`, `toggleCalcMode`).
  **Il n'a pas été supprimé** — `app.js` est modifié par un autre
  développeur. Son retrait peut se faire dans un lot dédié.
- La calculatrice pourrait être réutilisée dans la facturation hôtel
  (`/hotel/facturation`), qui a son propre taux de change.
