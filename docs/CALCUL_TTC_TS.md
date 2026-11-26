# Calcul de la TTC (TS + TVA) — analyse de `public/assets/js/app.js`

> **Objet** : document de référence expliquant **comment le TTC est calculé** dans la
> caisse (`/caisse`), la facturation hôtel (`/hotel/facturation`) et les recharges,
> **comment la Taxe Spécifique (TS)** s'articule avec la TVA, et **où les
> implémentations divergent** entre elles.
>
> **Source principale analysée** : `public/assets/js/app.js` (POS / caisse).
> **Fichiers miroirs analysés** : `public/assets/js/hotel-facturation.js`,
> `public/assets/js/hotel-recu.js`, `public/assets/js/recharges.js`,
> `app/views/facture-ticket.php`, `app/views/facture.php`,
> `app/models/Report.php`, `app/services/DgiAmountsService.php`,
> `app/controllers/SaleController.php`, `app/models/SaleDetail.php`.
>
> **Date d'analyse** : 2026-11-22 · **Projet** : `sys-pos/pos_systeme` (POS + PMS)

---

## 1. Résumé exécutif (TL;DR)

| # | Constat | Gravité |
|---|---------|---------|
| 1 | **Dans la caisse POS, le montant encaissé exclut la TVA sur le prix** : `lineTTC = lineHT` et `lineHT` ne contient pas `prix × taux`. La TVA sur le prix n'est calculée que dans `taxTotal` (champ `tva`), qui **n'est jamais utilisé pour le total**. | 🔴 Critique |
| 2 | **La TS est comptée UNE fois par ligne** dans `lineHT` (`prix×qté + TS + TVA_sur_TS`), alors que la TVA sur la TS est multipliée par la quantité. ⇒ Quantité > 1 ⇒ sous-facturation. | 🔴 Critique |
| 3 | **La remise n'est jamais déduite du total de la caisse** : elle est seulement transmise à la DGI (`remise_value` convertie en CDF). Le caissier affiche et encaisse un montant > TTC réel. | 🔴 Critique |
| 4 | **La remise envoyée à la DGI est un montant UNITAIRE** (ou du prix unitaire) : `prix × %/100` — la DGI la soustrait une seule fois alors que le prix est multiplié par la quantité. | 🟠 À confirmer |
| 5 | **La TS envoyée à la DGI est également unitaire** : même problème que la remise (une seule TS au lieu de `TS_unitaire × qté`). | 🟠 À confirmer |
| 6 | **6 implémentations différentes du même calcul** (app.js, hotel-facturation.js, hotel-recu.js, facture-ticket.php, facture.php, Report.php) avec des résultats divergents sur le même panier. | 🟠 Structurel |
| 7 | Le contrôle d'auto-cohérence `HT + TVA = TTC` **échoue** dans la caisse (écart = TVA sur le prix). | 🔴 Critique |
| 8 | La **source de vérité comptable** est la réponse de la DGI (`DgiAmountsService`), pas le front : les montants en base sont remplacés par `data.total` / `data.vtotal`. Le total encaissé peut donc ≠ total enregistré. | 🟠 Structurel |
| 9 | `posCart.taxRate = 16` (ligne 337) est un **champ mort**. `calculateTaxBreakdown()` (ligne 1245) est du **code mort**. | 🟢 Mineur |
| 10 | Le modal de paiement utilise un **taux de change fixe (2300)** alors que le panier utilise le **taux dynamique** de l'API : la monnaie à rendre peut être fausse. | 🟠 Majeur |
| 11 | Le preview **tronque** (`truncateDecimals`) le total, le reçu final **arrondit** → écart possible de 0,01 Fc entre l'aperçu et la facture. | 🟢 Mineur |

> **Règle d'or du projet (déjà énoncée dans `app.js` et `hotel-facturation.js`)** :
> la formule de `app.js` est la référence. Mais cette référence a été **dupliquée
> puis réinterprétée** dans 5 autres fichiers — c'est là que naissent les écarts.

---

## 2. Sources de vérité : qui calcule quoi

| Calculeur | Fichier : ligne | Rôle | Sortie |
|---|---|---|---|
| **Caisse (POS)** | `public/assets/js/app.js:906` `calculateItemTTC()` | Calcul de ligne + totaux panier + aperçu + payload DGI | `lineHT`, `lineTTC`, `unitTTC`, `taxTotal` |
| Caisse — TS | `public/assets/js/app.js:900` `calculateSpecificTaxUnit()` | TS **unitaire** | `specificTaxUnit` |
| Caisse — totaux | `public/assets/js/app.js:938` `calculateCartTotals()` | Somme du panier | `sous_total_ht`, `tva`, `total`, `taxe_specifique` |
| Caisse — DGI | `public/assets/js/app.js:1025` `validateWithDGI()` | Payload OSAT/DGI | `amount`, `articles[]` |
| Caisse — détail fiscal | `public/assets/js/app.js:1346` `getTaxBreakdownHtml()` | Ventilation HT/TS/TVA par groupe **lue dans la réponse DGI** | HTML ticket |
| **Hôtel** | `public/assets/js/hotel-facturation.js:126` `computeProductTTC()` | Miroir *corrigé* de `app.js` (régime B) | `line_ht`, `line_ttc`, `line_ts`, `line_vat_*` |
| Hôtel — reçu | `public/assets/js/hotel-recu.js:129` `computeProductTTC()` | Miroir du précédent | `line_ht` |
| **Ticket PHP** | `app/views/facture-ticket.php:136-141` | Impression serveur (recalcul) | `$itemHT`, `$itemTax`, `$itemTTC` |
| **Duplicata** | `public/assets/js/app.js:2913` | Relecture DGI (`/api/service-bill`) | `articleTotal` |
| **Rapports** | `app/models/Report.php:142-158` + `:274` `tsTotaleSql()` | Agrégats CA / TVA / TS | `total_ht`, `total_tax`, `total_ttc`, `total_ts` |
| **Écriture en base** | `app/services/DgiAmountsService.php:91` / `:133` | **Source de vérité comptable** | `sous_total_ht`, `tva`, `total` |
| Détail de ligne | `app/models/SaleDetail.php:21` | Snapshot des taxes sur la ligne | `tax_rate`, `tax_etiquette`, `tax_groupe`, `taxe_specifique_*` |

---

## 3. Données d'entrée

Une **ligne de panier** (`posCart.items[]`) est construite dans
`app.js:676-690` (`addToCart`) et `app.js:705-718` (`addPoidsToCart`).

| Champ | Type | Origine | Rôle |
|---|---|---|---|
| `produit_id` | int | catalogue | FK produit |
| `nom` | string | catalogue | libellé |
| `prix` | decimal(10,2) | `produits.prix` | **prix unitaire HT** de base |
| `quantite` | int / float | panier | quantité (float 3 déc. pour les produits au poids) |
| `tax_rate` | decimal(5,2) | `taxes.taux` via `produits.taxe_id` | taux de TVA **snapshot** |
| `tax_etiquette` | string | `taxes.etiquette` | étiquette du groupe (`A`..`P`) |
| `tax_groupe` | string | `taxes.groupe_taxe` | libellé du groupe (`Groupe B`) |
| `taxe_specifique_type` | `'%'` \| `'CDF'` | `produits.taxe_specifique_type` | mode de calcul de la TS |
| `taxe_specifique_value` | decimal(10,2) | `produits.taxe_specifique_value` | valeur de la TS |
| `remise_type` | `'%'` \| `'CDF'` | `produits.remise_type` | mode de calcul de la remise |
| `remise_value` | decimal(10,2) | `produits.remise_value` | valeur de la remise |
| `prod_service` | `BIE` \| `SER` \| `TAX` | produit | nature de l'opération (fiscal) |
| `product_type` | `unite` \| `coupe` \| `poids` | produit | conditionne la saisie de quantité |

Les bornes sont validées **côté serveur** à la création produit
(`app/controllers/ProductController.php:55-56`) :

- `remise_type` ∈ `['%', 'CDF']`
- TS `%` ∈ `[0, 100]` ; TS `CDF` ∈ `[0, prix]`
- Remise `%` ∈ `[0, 100]` ; Remise `CDF` ∈ `[0, prix]`

> ⚠️ Le contrôle `app.js:2177-2195` (`saveProduct`) applique les **mêmes bornes**
> mais uniquement pour les **types `%`/`CDF`** ; il est redondant avec le serveur
> (qui est l'autorité) — c'est de la validation dupliquée, pas un bug.


---

## 4. Les formules (implémentation réelle, code source)

### 4.1 Taxe spécifique unitaire — `app.js:900`

```js
calculateSpecificTaxUnit(item) {
    const price = parseFloat(item.prix) || 0;
    const value = parseFloat(item.taxe_specifique_value) || 0;
    return item.taxe_specifique_type === 'CDF' ? value : price * (value / 100);
}
```

| `taxe_specifique_type` | Formule | Exemple (prix 250, TS 10 %) |
|---|---|---|
| `CDF` | `TS_unitaire = valeur` | 250 → `20` si `value = 20` |
| `%` | `TS_unitaire = prix × valeur / 100` | 250 → `25` |

> La TS est calculée sur le **prix brut unitaire**, jamais sur le prix net de la
> remise. C'est un choix explicite et documenté
> (`hotel-facturation.js:106-124`, « BUG FIX 2026-10-20 »).

### 4.2 TVA

Deux composantes, calculées **unitairement** :

```
TVA_sur_prix_unitaire  = prix × (taux / 100)
TVA_sur_TS_unitaire    = TS_unitaire × (taux / 100)
```

La TVA est donc **assise sur le prix ET sur la TS** (TVA sur TS = taxe sur la
taxe). C'est le comportement attendu par la logique DGI « TS + TVA sur TS ».

### 4.3 Ligne — `app.js:906`

```js
calculateItemTTC(item, quantityOverride = null) {
    const price   = parseFloat(item.prix) || 0;
    const quantity = quantityOverride !== null ? quantityOverride : (parseFloat(item.quantite) || 0);
    const taxRate = parseFloat(item.tax_rate) || 0;
    const specificTaxUnit     = this.calculateSpecificTaxUnit(item);
    const vatOnPriceUnit      = price * (taxRate / 100);
    const vatOnSpecificTaxUnit= specificTaxUnit * (taxRate / 100);
    const lineHT   = price * quantity + specificTaxUnit + vatOnSpecificTaxUnit;
    const unitTTC  = price + specificTaxUnit + vatOnPriceUnit + vatOnSpecificTaxUnit;

    return {
        priceHT: price, quantity, specificTaxUnit,
        specificTaxTotal: specificTaxUnit,
        vatOnPriceTotal: vatOnPriceUnit * quantity,
        vatOnSpecificTaxTotal: vatOnSpecificTaxUnit * quantity,
        taxTotal: (vatOnPriceUnit + vatOnSpecificTaxUnit) * quantity,
        lineHT,
        lineTTC: lineHT,          // ⚠️ alias de lineHT
        unitTTC
    };
}
```

Décomposition lisible :

```
TS_unitaire            = TS(CDF) | prix×TS%/100
TVA_prix_unitaire      = prix × taux/100
TVA_TS_unitaire        = TS_unitaire × taux/100

lineHT  = prix×qté  +  TS_unitaire  +  TVA_TS_unitaire
lineTTC = lineHT                                   ← ⚠️ ALIAS
unitTTC = prix + TS_unitaire + TVA_prix_u + TVA_TS_u
taxTotal= (TVA_prix_unitaire + TVA_TS_unitaire) × qté
```

**Les 3 anomalies de cette fonction :**

| # | Anomalie | Conséquence |
|---|---|---|
| A1 | `lineTTC = lineHT` | Le TTC de ligne **ne contient pas** `TVA_prix × qté`. La TVA sur le prix n'est jamais encaissée. |
| A2 | `TS_unitaire` est ajouté **sans** `× qté` | La TS n'est facturée qu'une fois par ligne, alors que `TVA_TS × qté` l'est `qté` fois ⇒ incohérence interne. |
| A3 | `lineHT` exclut la remise | Le total affiché ne reflète aucune remise. |

> **Pourquoi A1 est « voulu » à l'origine** : en **régime B hôtelier**, la TVA
> sur le prix **n'est pas collectée** auprès du client : il paie
> `HT + TS + TVA_sur_TS`. C'est exactement ce que représente `lineHT`.
> Cf. `hotel-facturation.js:191-200` :
> *« En hôtellerie (régime B), la TVA sur prix n'est PAS collectée […] `line_ht`
> représente dans le pattern app.js […] Le `line_ttc` (qui inclut en plus la
> TVA sur prix × qty) correspond au TTC théorique avec TVA collectée »*.
>
> **⚠️ Ce raisonnement hôtelier a été appliqué à la caisse POS**, où la TVA
> **est** due par le client. `hotel-facturation.js:148-149` corrige d'ailleurs
> le tir (`line_ttc = line_ht + TVA_prix×qté`), mais **`app.js` n'a jamais reçu
> cette correction**.


### 4.4 Totaux du panier — `app.js:938`

```js
calculateCartTotals() {
    let sousTotalHT = 0, tva = 0, total = 0, taxeSpecifique = 0;
    for (const item of this.items) {
        const calc = this.calculateItemTTC(item);
        sousTotalHT += calc.lineHT;
        tva         += calc.taxTotal;
        total       += calc.lineTTC;
        taxeSpecifique += calc.specificTaxTotal;   // ⚠️ TS unitaire, pas × qté
    }
    return {
        sous_total_ht:   Math.round(sousTotalHT * 100) / 100,
        tva:             Math.round(tva * 100) / 100,
        total:           Math.round(total * 100) / 100,
        taxe_specifique: Math.round(taxeSpecifique * 100) / 100
    };
}
```

| Champ | Signification **réelle** | Utilisé par |
|---|---|---|
| `sous_total_ht` | HT cumulé + TS + TVA sur TS (PAS du HT pur) | `#subtotal` (`app.js:1012`), `POST /api/vente` (`app.js:1879`) |
| `tva` | `TVA_prix×qté + TVA_TS×qté` (**jamais** dans le total) | `POST /api/vente` (`app.js:1880`) |
| `total` | **identique** à `sous_total_ht` | `#total`, paiement, montant DGI `amount`, multi-paiements |
| `taxe_specifique` | Σ TS **unitaires** (non × qté) | jamais affiché |

> **Contrôle d'auto-cohérence :**
> `sous_total_ht + tva ≠ total` dès qu'une TVA > 0 existe.
> Écart mesuré sur le panier de test (§5) : **+162,00 Fc**.

### 4.5 Ce qui part réellement à la DGI — `app.js:1118-1145`

```js
articles: this.items.map(item => {
    const itemPrice = parseFloat(item.prix) || 0;
    const remiseValue = item.remise_type === '%'
        ? itemPrice * (rawRemiseValue / 100)      // ⚠️ unitaire
        : rawRemiseValue;                          // ⚠️ unitaire
    const specificTaxValue = item.taxe_specifique_type === '%'
        ? itemPrice * (rawSpecificTaxValue / 100) // ⚠️ unitaire
        : rawSpecificTaxValue;                     // ⚠️ unitaire
    return { name, quantity, price, tax_rate, tax_etiquette,
             prod_service, remise_type, remise_value,
             taxe_specifique_type, taxe_specifique_value };
})
```

Et l'en-tête : `amount: this.currentTotals.total` (`app.js:1101`) = le total
**front** (donc sans TVA sur le prix, sans remise, TS une seule fois).

Le commentaire de `hotel-facturation.js:1674-1678` documente la formule
**attendue par la DGI** :

```
ttc_article = price × quantity − remise_value + taxe_specifique_value + TVA
```

⇒ La DGI fait `prix × qté` mais soustrait une remise **unitaire** et ajoute une
TS **unitaire** : les deux écarts ci-dessus sont **transmis tels quels** à la DGI.

> ⚠️ **À confirmer avec la spécification OSAT/DGI** : la DGI applique-t-elle la
> remise et la TS **par article** (donc ×qté) ou **par ligne** (donc une fois) ?
> La formule documentée suggère « par ligne », ce qui est cohérent avec des
> `remise_value`/`taxe_specifique_value` unitaires… mais alors le
> `price × quantity` de la même formule est incohérent.

### 4.6 Ce qui est écrit en base — `DgiAmountsService`

`app/services/DgiAmountsService.php:91-119` :

```
total         = data.total     (TTC  — montant réellement facturé par la DGI)
tva           = data.vtotal    (TVA  — total des ventilations vab/vac/…)
sous_total_ht = total − vtotal  (HT  — base HT de la facture)
```

`apply()` (`:133-154`) applique ces montants **seulement si** la réponse DGI
contient des **éléments de sécurité** (`codeDEFDGI`, `counters`, `nim`, `qrCode`,
`dateDGI`). Sinon (DGI hors service, boutique non homologuée, extra hôtel) les
montants **du front** sont conservés (`keepCurrent()`), et la vente passe en
statut `pending` pour relecture ultérieure (`DgiSyncService`).

Pour les avoirs (`FA`, `EA`), `applyCreditNoteSign()` (`:213-226`) force le
signe négatif.

> **Conséquence** : `ventes.total` = montant **DGI**, alors que le montant
> **encaissé** = `posCart.currentTotals.total` (front). Si les deux diffèrent,
> l'écart n'est tracé nulle part.

### 4.7 Snapshot sur la ligne de détail — `SaleController.php:382-400`

```php
'tax_rate'      => (float)($product['tax_rate'] ?? 0),       // taxes.taux
'tax_etiquette' => (string)($product['tax_etiquette'] ?? ''), // taxes.etiquette
'tax_groupe'    => (string)($product['tax_groupe'] ?? ''),    // taxes.groupe_taxe
'remise_type'   => $item['remise_type'] ?? '%',
'remise_value'  => $item['remise_value'] ?? 0,
'taxe_specifique_type'  => $item['taxe_specifique_type'] ?? '%',
'taxe_specifique_value' => $item['taxe_specifique_value'] ?? 0,
```

⇒ Le taux est **figé au moment de la vente** (migration `2026_11_06`) : un
re-tauxage du produit après coup ne modifie pas la facture émise.
`SaleDetail::getBySaleId()` (`app/models/SaleDetail.php:79-94`) applique la
règle de repli : snapshot > 0 → snapshot ; snapshot = 0 **avec** étiquette → 0
(exonéré réel) ; sinon → taux du produit (ligne legacy).


---

## 5. Exemple chiffré vérifié (exécuté en Node)

Panier de test :

| # | Article | `prix` | `qté` | TVA | TS | Remise |
|---|---|---|---|---|---|---|
| 1 | Coca 33 cl | 100 | 3 | 16 % | 0 % | 0 % |
| 2 | Pilon | 250 | 2 | 16 % | 10 % | 0 % |
| 3 | Eau 1,5 L | 500 | 1 | 5 % | 20 Fc | 50 Fc |
| 4 | Riz parfumé | 1 000 | 4 | 0 % | 5 % | 10 % |

### 5.1 Sortie de `app.js` (`calculateItemTTC`)

| Article | TS_u | TVA_prix_u | TVA_TS_u | `lineHT` = `lineTTC` | `taxTotal` | `unitTTC` |
|---|---|---|---|---|---|---|
| 1 — Coca | 0,00 | 16,00 | 0,00 | **300,00** | 48,00 | 116,00 |
| 2 — Pilon | 25,00 | 40,00 | 4,00 | **529,00** | 88,00 | 319,00 |
| 3 — Eau | 20,00 | 25,00 | 1,00 | **521,00** | 26,00 | 546,00 |
| 4 — Riz | 50,00 | 0,00 | 0,00 | **4 050,00** | 0,00 | 1 050,00 |

### 5.2 Totaux `calculateCartTotals()`

```
sous_total_ht   = 5 400,00   (affiché dans #subtotal)
tva             =   162,00   (calculé, jamais ajouté au total)
total           = 5 400,00   (affiché dans #total, encaissé, envoyé à la DGI)
taxe_specifique =    95,00   (Σ TS unitaires — non × qté)
```

**Contrôle d'auto-cohérence :**

```
sous_total_ht + tva = 5 400,00 + 162,00 = 5 562,00
total               =                    5 400,00
ÉCART               =                      162,00 Fc   ❌
```

Décomposition de l'écart : TVA sur le prix `(16×3) + (40×2) + (25×1) + 0 = 153`,
plus TVA sur TS `4×2 + 1×1 = 9` ⇒ **162** ✓ (cohérent avec `taxTotal`).

### 5.3 Comparaison avec une formule « canonique » (TTC réel)

```
TTC_récel = (prix×qté − remise_ligne + TS_ligne) + TVA_sur(base)
```

| Article | Remise | TS ligne | Base HT | TVA | **TTC réel** | TTC `app.js` | Écart |
|---|---|---|---|---|---|---|---|
| 1 — Coca | 0,00 | 0,00 | 300,00 | 48,00 | **348,00** | 300,00 | −48,00 |
| 2 — Pilon | 0,00 | 50,00 | 550,00 | 88,00 | **638,00** | 529,00 | −109,00 |
| 3 — Eau | 50,00 | 20,00 | 470,00 | 23,50 | **493,50** | 521,00 | +27,50 |
| 4 — Riz | 400,00 | 200,00 | 3 800,00 | 0,00 | **3 800,00** | 4 050,00 | +250,00 |
| **TOTAL** | **450,00** | **270,00** | **5 120,00** | **159,50** | **5 279,50** | **5 400,00** | **+120,50** |

**Lecture** :
- Article **sans remise et sans TS** (n° 1) : `app.js` sous-facture de **toute la TVA**.
- Article **avec TS** (n° 2) : sous-facture de la TVA **et** de 1 TS manquante (TS comptée 1× au lieu de 2×).
- Article **avec remise CDF** (n° 3) : `app.js` **sur-facture** (la remise n'est pas déduite).
- Article **exonéré avec remise %** (n° 4) : `app.js` sur-facture de 250,00 (remise non déduite + 3 TS manquantes).

### 5.4 Script de reproduction

```js
// node verify.js  — réplique les formules de app.js et la formule canonique
const num = v => parseFloat(v) || 0;
const tsUnit = (price, type, value) =>
    type === 'CDF' ? num(value) : price * (num(value) / 100);

function appJs(item) {                       // app.js:906
    const price = num(item.prix), qty = num(item.quantite), rate = num(item.tax_rate);
    const ts = tsUnit(price, item.taxe_specifique_type, item.taxe_specifique_value);
    const vatPrice = price * (rate / 100), vatTs = ts * (rate / 100);
    const lineHT = price * qty + ts + vatTs;      // TS ×1  ⚠️
    return { lineHT, lineTTC: lineHT, taxTotal: (vatPrice + vatTs) * qty, ts };
}
function canon(item) {                      // TTC « réelle »
    const price = num(item.prix), qty = num(item.quantite), rate = num(item.tax_rate);
    const ts = tsUnit(price, item.taxe_specifique_type, item.taxe_specifique_value) * qty;
    const rv = num(item.remise_value);
    const remise = item.remise_type === 'CDF' ? rv * qty : price * qty * rv / 100;
    const base = price * qty - remise + ts;
    return { remise, ts, base, tva: base * rate / 100, ttc: base + base * rate / 100 };
}
```


---

## 6. Tableau de divergence entre implémentations

Même ligne de test : **prix 100, qté 2, TVA 16 %, TS 10 %** (TS unitaire = 10,00).

| Implémentation | Fichier: ligne | TS retenue | HT | TVA | **TTC** |
|---|---|---|---|---|---|
| **Caisse POS** | `app.js:906` | 10,00 (×1) | 211,60 | 35,20 | **211,60** |
| **Hôtel facturation** | `hotel-facturation.js:126` | 10,00 (×1) | 211,60 | 35,20 | **243,60** (`line_ttc`) |
| **Hôtel reçu** | `hotel-recu.js:129` | 10,00 (×1) | 211,60 | — | **211,60** |
| **Ticket PHP** | `facture-ticket.php:136` | 20,00 (×qté) | 220,00 | 35,20 | **255,20** |
| **Rapports CA** | `Report.php:146-148` | 20,00 (×qté, `tsTotaleSql`) | 200,00 | 32,00 | **232,00** |
| **Duplicata** | `app.js:2913` | 10,00 (×1) | 211,60 | — | **211,60** |
| **Canonique** | — | 20,00 (×qté) | 220,00 | 35,20 | **255,20** |

→ **7 résultats différents pour une ligne identique.** L'écart maximal observé :
**43,60 Fc** (211,60 vs 255,20), soit **20,6 %** du montant.

### 6.1 Rôle par rôle

| Rôle | Fichier | Comportement |
|---|---|---|
| Panier / preview / ticket POS | `app.js` | TS ×1, pas de remise, **TVA sur le prix absente du total** |
| Facture hôtel | `hotel-facturation.js` | TS ×1 dans `line_ht`, `line_ttc` **avec** TVA prix ⇒ 2 totaux distincts |
| Reçu hôtel | `hotel-recu.js` | TS ×1, `line_ht` sans TVA prix |
| Ticket imprimé PHP | `facture-ticket.php` | TS ×qté, **avec** TVA sur (prix+TS) ⇒ diverge du front |
| Rapports / analytique | `Report.php` | TS ×qté **hors** du TTC (`total_ttc = qté×prix×(1+taux)`) ⇒ TTC **sans** TS |
| Base de données | `DgiAmountsService` | **Montants DGI** (TTC et TVA officiels) |

> Le rapport « Par groupe de taxation » (`Report.php`) affiche donc un
> **TTC différent du TTC de la facture** : la TS y est ventilée à part
> (`total_ts`) et n'est pas incluse dans `total_ttc`. C'est **voulu** (la TS
> est une taxe additionnelle à la TVA) mais il faut le savoir quand on compare
> un rapport à une facture.

---

## 7. La source de vérité comptable

```
Payload DGI (app.js:1025)
        │
        ▼
OSAT / DGI  ──►  data.total (TTC) + data.vtotal (TVA) + ht_tva_group (ventilation)
        │
        ▼
DgiAmountsService::apply()   ── si éléments de sécurité présents ──►  base
        │                                                              │
        └──── sinon (DGI KO / non homologué) ──► montants du front ────┘
                                                                       │
                                                    ventes.sous_total_ht / tva / total
```

**Règles à retenir :**

1. **La base** (ventes) reflète la **DGI**, pas le front.
2. **L'encaissement** (paiements) reflète le **front** ⇒ **écart possible non tracé**.
3. Le **fallback** (DGI indisponible) conserve les montants du front ⇒ la base
   contient alors un TTC qui **n'inclut pas** la TVA sur le prix.
4. Pour les **avoirs** (`FA`, `EA`) : quantités et montants **négatifs**
   (`app.js:1067`, `:1353`, `:1879-1881`), et `applyCreditNoteSign()` force le
   signe côté serveur.

---

## 8. Détail des anomalies et correctifs recommandés

### 🔴 A1 — La TVA sur le prix n'est pas dans le total encaissé

**Où** : `app.js:913` (`lineHT`) + `app.js:924` (`lineTTC: lineHT`).

**Effet** : pour le panier de test, la caisse encaisse **5 400,00** au lieu de
**5 279,50** — soit **120,50** de TVA non collectée (2,3 % du panier).
En régime hôtelier c'est **voulu** (régime B), en POS c'est une **sous-facture
de TVA**.

**Correctif recommandé** (caisse uniquement) — rendre `lineTTC` explicite :

```js
// app.js:906 — distinguer "HT facturé" (régime B) du TTC réellement dû
const lineHTB   = price * quantity + specificTaxUnit + vatOnSpecificTaxUnit; // régime B
const lineTTC   = price * quantity + specificTaxUnit
                 + vatOnPriceUnit * quantity + vatOnSpecificTaxUnit * quantity; // TTC complet
```

⚠️ **Ne pas** appliquer ce correctif à l'hôtel : `line_ht` y est **délibérément**
le montant facturé en régime B (`hotel-facturation.js:191-200`).

### 🔴 A2 — La TS n'est pas multipliée par la quantité

**Où** : `app.js:913` (`+ specificTaxUnit` sans `× quantity`).

**Effet** : `qté = 2` ⇒ 1 TS facturée au lieu de 2 (TS ×1 manquante), alors que
la **TVA sur la TS** est bien calculée `× qté` ⇒ incohérence interne visible
dans `taxTotal`.

**Correctif** : `+ specificTaxUnit * quantity` (et aligner
`hotel-facturation.js:146` et `hotel-recu.js:137`).

### 🔴 A3 — La remise n'est pas déduite du total

**Où** : nulle part dans `calculateItemTTC()` ; la remise n'apparaît que dans le
label affiché (`app.js:980-984`) et dans le payload DGI (`app.js:1121-1125`).

**Effet** : le caissier affiche et encaisse un montant **supérieur** au TTC
réel. La remise n'est honorée que par la DGI sur la facture finale.

**Correctif** : intégrer la remise **avant** la TS et la TVA :

```js
const remiseUnit = item.remise_type === 'CDF'
    ? (parseFloat(item.remise_value) || 0)
    : price * ((parseFloat(item.remise_value) || 0) / 100);
const netPrice = price - remiseUnit;   // base de la TS et de la TVA
```

> ⚠️ **Décision à arbitrer** : la TS doit-elle être calculée sur le **prix brut**
> (comportement actuel, documenté) ou sur le **prix net** de la remise ?
> Le projet a tranché pour le **brut** (`hotel-facturation.js:106-124`,
> BUG FIX 2026-10-20). **Ne pas inverser cette décision sans validation.**

### 🟠 A4/A5 — Remise et TS envoyées à la DGI en valeur unitaire

**Où** : `app.js:1121-1131`.

**Effet** : la DGI applique `prix×qté − remise_unitaire + TS_unitaire`.
Pour `qté = 3` et remise 10 % sur 100 ⇒ elle déduit 10,00 au lieu de 30,00.

**Action** : **confirmer la spécification OSAT/DGI** (par ligne vs par article)
avant de modifier. C'est le seul point du document qui dépend d'une
spécification externe.


### 🟠 A6 — 6 implémentations divergentes

**Correctif structurel** : extraire **un seul** calculateur partagé, par exemple
`public/assets/js/tax-calculator.js`, exposant :

```js
window.TaxCalculator = {
    specificTaxUnit(item),          // TS unitaire
    computeLine(item, qty),         // { ts, tva_prix, tva_ts, ht_brut, ht_net, ttc }
    computeCart(items, opts),       // { ht, ts, tva, remise, ttc } + ventilation par groupe
    computeCartRegimeB(items),      // hôtel : TVA sur prix non collectée
};
```

puis faire **réutiliser** ce module par `app.js`, `hotel-facturation.js`,
`hotel-recu.js`, `recharges.js` — et faire **réimplémenter la même formule** en
PHP (`app/views/facture-ticket.php`, `app/views/facture.php`) pour que le ticket
imprimé et le ticket écran soient identiques.

> Contrainte AGENT.md : le projet est en **JS vanilla** (pas de framework) et
> refuse les helpers globiques magiques côté PHP — d'où un module JS
> namespace + une classe PHP (`App\Services\TaxCalculator`) symétrique.

### 🟠 A8 — Écart encaissement ↔ base

**Correctif recommandé** : après la réponse DGI, **comparer** `data.total` et le
total encaissé ; si l'écart dépasse 0,01 Fc, **alerter le caissier** (la
monnaie rendue est déjà calculée sur le total front) et tracer l'écart
(`ventes.comment` ou une ligne d'audit).

### 🟠 A10 — Taux de change fixe vs dynamique

**Où** : `app.js:1706` (`const tauxChange = 2300`) vs `app.js:70` (`USD_RATE`
dynamique, cache 30 min).

**Effet** : la **monnaie à rendre** (`calculateChange()`, `app.js:1758`) est
calculée à 2300 alors que l'**équivalent USD affiché** sur le ticket utilise le
taux dynamique ⇒ le ticket et la monnaie rendue peuvent diverger.

**Correctif** : utiliser `USD_RATE` partout, avec repli sur 2300 si `USD_RATE <= 0`.

### 🟢 A9 — Code mort

| Élément | Ligne | Traitement |
|---|---|---|
| `posCart.taxRate = 16` | `app.js:337` | Champ jamais lu (`calculateItemTTC` utilise `item.tax_rate`) ⇒ supprimer |
| `posCart.calculateTaxBreakdown()` | `app.js:1245` | Jamais appelé (le rendu réel est `getTaxBreakdownHtml`, `app.js:1346`) ⇒ supprimer ou brancher sur un écran de debug |
| Taux par défaut 16 | `app.js:337` | Source de confusion : le taux par défaut **réel** est celui du groupe (`taxes.taux`) |

### 🟢 A11 — Troncature vs arrondi

- Preview : `truncateDecimals(this.currentTotals.total * previewSign).toFixed(2)` — `app.js:1659` ⇒ **troncature**.
- Reçu final : `(dgiResponse.data.total).toFixed(2)` — `app.js:2117` ⇒ **arrondi**.

⇒ Un total de `100,999` s'affiche **100,99** en aperçu et **101,00** sur la
facture. Trancher : **arrondir** partout (conforme `DECIMAL(10,2)` MySQL).

### 🟢 A12 — Division par zéro sur `USD_RATE`

`app.js:1016` et `app.js:1497` : `this.currentTotals.total / USD_RATE`.
`USD_RATE` vaut **0** par défaut (`app.js:70`) et n'est mis à jour que si
`rate > 1` (`app.js:129`) ⇒ en cas d'échec API, le ticket affiche
`Infinity $`. **Correction** : `USD_RATE > 0 ? (total / USD_RATE).toFixed(2) : '-'`
(comme le fait déjà `renderServiceBillContent`, `app.js:2947`).

---

## 9. Formules de référence (proposition de convergence)

### 9.1 Règles fiscalement cohérentes

```
TS_unitaire   = (type = 'CDF') ? valeur : prix_unitaire × valeur/100
TS_ligne      = TS_unitaire × quantité

Remise_ligne  = (type = 'CDF') ? valeur × quantité
                              : prix_unitaire × quantité × valeur/100

Base_HT       = prix_unitaire × quantité − Remise_ligne + TS_ligne
TVA_ligne     = Base_HT × taux/100            (TVA sur le prix ET sur la TS)
TTC_ligne     = Base_HT + TVA_ligne
```

### 9.2 Cas particuliers

| Cas | Règle |
|---|---|
| **Groupe A (exonéré)** | `taux = 0` ⇒ `TTC = Base_HT`. Magasin non homologué ⇒ `applyTaxGroupHomologationLock()` (`app.js:2163`) **fige** le groupe A (`app.js:2183`) |
| **Régime B hôtelier** | TVA sur le prix **non collectée** ⇒ montant client = `Base_HT + TVA_sur_TS` (= `line_ht` de `hotel-facturation.js`) |
| **Exportation (EV/EA/ET)** | Groupe E ⇒ exonéré (`taux = 0`) |
| **TVA spécifique (groupes M/N)** | `TS` avec `TVA` sur la TS : cf. `taxes.etiquette` |
| **Avoir (FA/EA)** | Quantités **et** montants négatifs (`app.js:1067`, `:1353`, `:1879`) |
| **Brouillon** | Aucun effet sur la facturation : la facturation passe **exclusivement** par `SaleController::create` (cf. AGENT.md §5bis) |

### 9.3 Contrôle de cohérence recommandé

À afficher dans le panier (mode debug / super_admin) :

```
TTC attendu = Σ (Base_HT + TVA_ligne)
Contrôle    : |(sous_total_ht + tva) − total| ≤ 0,01
```

Ce contrôle est **aujourd'hui en échec** (cf. §5.2). Il doit devenir un
`console.warn` en environnement de test, et un garde-fou bloquant en
production une fois la formule convergée.


---

## 10. Groupes de taxation DGI (référence)

`app.js:1365-1382` (`TAX_CATEGORIES`) et `app.js:2745-2761`
(`PROFORMA_TAX_CATEGORIES`) — **doublon à factoriser**.

| Étiquette | Taux | Libellé | Clé HT | Clé TVA | Clé TS |
|---|---|---|---|---|---|
| A | 0 % | EXONERE ET HORS CHAMP | `haa` | `vaa` | `tsa` |
| B | 16 % | Taxable | `hab` | `vab` | `tsb` |
| C | 5 % | Taxable | `hac` | `vac` | `tsc` |
| D | 0 % | Régimes dérogatoires TVA | `had` | `vad` | `tsd` |
| E | 0 % | Exportation et opérations assimilées | `hae` | `vae` | `tse` |
| F | 16 % | TVA marché public à financement extérieur | `haf` | `vaf` | `tsf` |
| G | 5 % | TVA marché public à financement extérieur | `hag` | `vag` | `tsg` |
| H | 0 % | Consignation/déconsignation emballage | `hah` | `vah` | `tsh` |
| I | 0 % | Garantie et caution | `hai` | `vai` | `tsi` |
| J | 0 % | Débours | `haj` | `vaj` | `tsj` |
| K | 0 % | Opérations réalisées par les non-assujettis | `hak` | `vak` | `tsk` |
| L | 0 % | Prélèvements sur les ventes | `hal` | `val` | `tsl` |
| M | 0 % | Ventes réglementées TVA spécifique | `ham` | `vam` | `tsm` |
| N | 0 % | TVA spécifique | `han` | `van` | `tsn` |
| O | 1 % | Taxable | `hao` | `vao` | `tso` |
| P | 1 % | TVA marché public à financement extérieur | `hap` | `vap` | `tsp` |

> La réponse DGI (`data.ht_tva_group`) contient `ha` (HT par groupe), `va` (TVA
> par groupe) et `ts` (TS par groupe) — c'est **la** ventilation fiscale de la
> facture. Le ticket l'affiche (`app.js:1400-1430`).
>
> **Relation vérifiée côté provider** (`app/services/DgiReportService.php:839-881`) :
> `Σ(ha) = TTC` et `Σ(ts) = TS_TOTAL`. En revanche `Σ(va) ≠ TVA total`
> (écart mesuré de 6 800 sur le payload de référence) — la relation n'est donc
> **pas** contrôlée, pour éviter un faux positif systématique.

---

## 11. Types de facture et signe des montants

`app.js:22-28` :

| Code | Libellé | Signe |
|---|---|---|
| `FV` | Facture de Vente | + |
| `EV` | Fac de Vente à l'exportation | + |
| `FT` | Facture d'acompte | + |
| `FA` | Facture d'avoir | **−** |
| `EA` | Fac d'avoir à l'exportation | **−** |
| `ET` | Fac d'acompte à l'exportation | + |

Le signe `−1` est appliqué **à 4 endroits** (à garder synchronisés) :

| Endroit | Ligne | Effet |
|---|---|---|
| `validateWithDGI()` | `app.js:1067` | `sign` calculé, puis **non appliqué** au payload → incohérence mineure |
| `getTaxBreakdownHtml()` | `app.js:1352-1353` | signe du détail des taxes |
| `showPreview()` | `app.js:1617-1619` | quantités et total du preview |
| `confirmSale()` | `app.js:1866-1868` | quantités + `sous_total_ht` / `tva` / `total` envoyés à `POST /api/vente` |

> ⚠️ `showPreview()` et `confirmSale()` recalculent `itemCalc` avec la quantité
> **négative** (`app.js:1563`, `app.js:2051`) : `lineHT` devient négatif, mais
> `taxTotal` suit la quantité négative ⇒ le ticket d'un avoir peut afficher un
> HT négatif et une TVA de même signe, sans cohérence avec la ventilation DGI.

---

## 12. Plan de convergence proposé

| Étape | Contenu | Fichiers | Risque |
|---|---|---|---|
| 1 | **Spécifier** la formule canonique (TVA sur TS ? remise dans la base ? TS ×qté ?) avec la DGI/OSAT | doc | 🟢 |
| 2 | Créer `public/assets/js/tax-calculator.js` + `App\Services\TaxCalculator` (PHP) | nouveau | 🟢 |
| 3 | Faire réutiliser le module par `app.js`, `hotel-facturation.js`, `hotel-recu.js`, `recharges.js` | 4 fichiers | 🟠 |
| 4 | Aligner `facture-ticket.php` / `facture.php` sur la même formule (via le service PHP) | 2 vues | 🟠 |
| 5 | Corriger A1 (TVA prix dans le TTC **caisse seulement**), A2 (TS ×qté), A3 (remise déduite) | `app.js` | 🔴 |
| 6 | Supprimer le code mort (A9), corriger `USD_RATE` (A12), unifier le taux de change (A10) | `app.js` | 🟢 |
| 7 | Ajouter le **contrôle de cohérence** `HT + TVA = TTC` (avertissement en dev, blocage en prod) | `app.js` + PHP | 🟠 |
| 8 | Tracer l'écart **encaissement ↔ montant DGI** quand ils diffèrent | `SaleController` / `DgiAmountsService` | 🟠 |
| 9 | Tests : PHPUnit (PHP) + script Node (JS) sur un panier de référence | `tests/` | 🟢 |

> ⚠️ **Ne pas** engager les étapes 5 (A1) avant d'avoir arbitré le régime hôtelier :
> `line_ht` est **partagé** entre la caisse et l'hôtel. Toute correction doit
> être **paramétrée par le contexte** (POS vs Régime B).

---

## 13. Annexes

### 13.1 Glossaire

| Terme | Signification |
|---|---|
| **HT** | Hors Taxes — base sans TVA (mais **avec** TS dans l'implémentation actuelle) |
| **TTC** | Toutes Taxes Comprises — ce que le client paie |
| **TVA** | Taxe sur la Valeur Ajoutée — taux porté par `taxes.taux` |
| **TS** | Taxe Spécifique — taxe additionnelle, par produit (`%` ou `CDF`) |
| **Étiquette** | Lettre du groupe de taxation (`A`..`P`) figée sur la ligne |
| **Groupe** | Libellé complet (`Groupe B`) figée sur la ligne |
| **Remise** | Réduction commerciale, ne modifie **pas** le total front (cf. A3) |
| **Régime B** | Régime hôtelier : TVA sur le prix non collectée |
| **Facture d'avoir** | `FA` / `EA` : montants négatifs |
| **Fallback OSAT** | DGI indisponible ⇒ montants du front conservés, vente en `pending` |

### 13.2 Fichiers à relire en priorité

| Priorité | Fichier | Raison |
|---|---|---|
| 🔴 | `public/assets/js/app.js:900-958` | Source du calcul (TS, TVA, HT, TTC, totaux) |
| 🔴 | `public/assets/js/app.js:1118-1145` | Payload DGI (remise + TS unitaires) |
| 🟠 | `public/assets/js/hotel-facturation.js:126-177` | Miroir + correctif `line_ttc` |
| 🟠 | `app/views/facture-ticket.php:132-164` | Recalcul PHP divergent |
| 🟠 | `app/models/Report.php:128-158` + `:274-281` | Agrégats rapport (TS hors TTC) |
| 🟠 | `app/services/DgiAmountsService.php` | Source de vérité comptable |
| 🟢 | `public/assets/js/app.js:1346-1445` | Rendu de la ventilation DGI |

### 13.3 Commandes de vérification

```bash
# Script de reproduction du calcul (§5.4)
node verify.js

# Tests du projet
composer test
npm run test:e2e

# Lint
composer cs:check && composer static && npm run lint
```

---

*Fin du document — toute modification de `app.js` sur le calcul TTC doit mettre
à jour ce fichier et le test de référence associé.*

