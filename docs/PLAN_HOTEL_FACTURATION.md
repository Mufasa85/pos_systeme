# Plan d'implémentation — Facturation hôtel

**Date** : 2026-10-17
**Branche** : `features/hotel`
**Module** : PMS — facturation des séjours et génération de factures officielles

---

## 1. Objectif métier

Permettre au réceptionniste de générer la facture officielle d'un séjour hôtelier
**à n'importe quel moment** (avant le séjour / pendant / au check-out), en agrégeant :

- les nuits du séjour (calcul auto depuis `hotel_sejours`)
- les **extras** consommés en cours de séjour (saisis depuis `/caisse` par la caissière)
- les paiements partiels / acomptes déjà encaissés
- → solde restant dû

La facture suit le **régime fiscal TVA type B** (mentionnée par l'utilisateur comme étant
le régime hôtelier RDC), avec numérotation officielle, QR code DGI et impression PDF —
identique à une facture POS classique.

Le bouton de saisie des extras vit dans `/caisse` (option 2.b choisie par l'utilisateur) :
quand la caissière scanne un produit, elle peut dire « imputer au séjour #42 » au lieu
de « vendre à un client » — la vente est créée avec `invoice_type='EX'` et
`sejour_id=<id>`. Ces ventes seront ensuite remontées automatiquement comme lignes
d'extras dans la facture finale du séjour.

---

## 2. Décisions verrouillées

| # | Question | Réponse |
|---|----------|---------|
| 1 | Table facture hôtel | **a** : nouvelle table `hotel_factures` (1 facture = 1 séjour, agrège les extras) |
| 2 | Saisie des extras | **b** : via `/caisse`, option « imputer au séjour » |
| 3 | Quand facturer | à tout moment : avant / pendant / au check-out |
| 4 | TVA | type B (mentionnée par l'utilisateur comme étant le régime hôtelier) |

**Note TVA type B** : en RDC le type B correspond à une facture « bon de livraison »
sans TVA collectée par l'assujetti (régime des entreprises non-assujetties ou des
activités exonérées). On respecte la nomenclature DGI déjà utilisée (`FV, FA, FT,
EA, EV, ET`) en ajoutant **`HB` = Hotel Bill type B** dans la liste des
`invoice_type` acceptés. À confirmer avec l'utilisateur si la nomenclature officielle
DGI utilise une autre lettre ; à défaut on documente la décision et on reste sur `HB`.

---

## 3. Architecture cible

```
┌─────────────────────────────────────────────────────────────────────┐
│ /caisse (POS existant, modification minimale)                        │
│   └─ Panier : ajout produit                                         │
│   └─ Au moment de "Choisir le client" :                             │
│        ┌──────────────────────────────────────────┐                 │
│        │ [ Client POS classique ▼ ]              │                 │
│        │ OU                                      │                 │
│        │ [ Séjour hôtelier #42 — Chambre 201 ▼ ] │  ← NOUVEAU      │
│        └──────────────────────────────────────────┘                 │
│   Si séjour sélectionné → invoice_type='EX', sejour_id=42          │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                  ventes (table existante)
                  ┌──────────────────────────┐
                  │ invoice_type = 'EX'      │ ← extras
                  │ sejour_id = 42           │ ← FK ajoutée
                  └──────────────────────────┘
                              │
                              ▼ (agrégation à la facturation)
┌─────────────────────────────────────────────────────────────────────┐
│ /hotel/facturation (NOUVEAU) — page dédiée, layout même que /caisse  │
│   URL : /hotel/facturation?sejour_id=42                              │
│                                                                     │
│   1. Réceptionniste sélectionne le séjour                            │
│   2. Le système pré-remplit :                                       │
│        - guest + client POS lié                                     │
│        - chambre + dates + prix_par_nuit × nuits − remise            │
│        - tous les extras (ventes WHERE sejour_id=42 AND type='EX')   │
│        - paiements partiels (hotel_paiements)                       │
│   3. Réceptionniste peut :                                          │
│        - ajouter manuellement un extra oublié (minibar, casse…)       │
│        - choisir les modes de paiement (cash, mobile, carte)         │
│        - ajouter une remise globale                                  │
│   4. Valide → INSERT hotel_factures + INSERT ventes (type='HB')     │
│   5. Bouton "Imprimer" → /facture/<id> (InvoiceController existant) │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                  hotel_factures (NOUVELLE table)
                  ┌──────────────────────────────┐
                  │ sejour_id (FK unique)        │
                  │ vente_id (FK vers ventes)    │
                  │ total_nuits, total_extras   │
                  │ remise_pct, tva_type='B'     │
                  │ paiements_json, solde_du     │
                  │ statut (brouillon/émise)     │
                  └──────────────────────────────┘
```

---

## 4. Modifications SQL

### 4.1 Migration `migrations/2026_10_17_hotel_facturation.sql`

```sql
-- 1. Ajouter sejour_id (nullable FK) à ventes pour le pont /caisse
ALTER TABLE ventes
  ADD COLUMN sejour_id INT NULL COMMENT 'Si non-null, vente imputée à un séjour hôtel' AFTER client_id,
  ADD KEY ventes_sejour_idx (sejour_id),
  ADD CONSTRAINT ventes_sejour_fk
    FOREIGN KEY (sejour_id) REFERENCES hotel_sejours(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 2. Nouvelle table hotel_factures (1 facture = 1 séjour)
CREATE TABLE hotel_factures (
  id INT NOT NULL AUTO_INCREMENT,
  sejour_id INT NOT NULL,
  vente_id INT NOT NULL COMMENT 'Facture officielle générée dans ventes',
  numero_facture VARCHAR(50) NOT NULL,
  invoice_type VARCHAR(5) NOT NULL DEFAULT 'HB' COMMENT 'HB = Hotel Bill type B',

  total_nuits DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_extras DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remise_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  remise_montant DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tva_type ENUM('A','B','C') NOT NULL DEFAULT 'B' COMMENT 'Régime fiscal B pour hôtellerie',
  tva_montant DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_final DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paiements_json JSON NULL COMMENT 'Modes de paiement encaissés',
  acompte_precedent DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Paiements partiels déjà versés',
  solde_du DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  statut ENUM('brouillon','emise','annulee') NOT NULL DEFAULT 'brouillon',
  user_id INT NOT NULL,
  shop_id INT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY hotel_factures_sejour_unique (sejour_id),
  KEY hotel_factures_vente_idx (vente_id),
  KEY hotel_factures_statut_idx (statut),
  CONSTRAINT hotel_factures_sejour_fk FOREIGN KEY (sejour_id) REFERENCES hotel_sejours(id),
  CONSTRAINT hotel_factures_vente_fk FOREIGN KEY (vente_id) REFERENCES ventes(id),
  CONSTRAINT hotel_factures_user_fk FOREIGN KEY (user_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Une facture officielle par séjour (régime TVA type B hôtellerie)';
```

**Note** : `UNIQUE (sejour_id)` empêche 2 factures officielles sur le même séjour. Si on
veut annuler et refacturer, on passe `statut='annulee'` puis on en crée une nouvelle.

---

## 5. Modifications PHP — backend

### 5.1 `app/models/Sale.php` (modifier)

Ajouter dans le `INSERT` le support optionnel de `sejour_id` :

```php
':sejour_id'        => $data['sejour_id'] ?? null,
```

Aucune autre modification : le reste de la table `ventes` reste identique, on ne touche
pas à la caisse POS.

### 5.2 `app/controllers/SaleController.php` (modifier)

Dans `create()` (ou équivalent) : si le payload contient `sejour_id`, forcer
`invoice_type='EX'` (extra imputé à un séjour) au lieu de `'FV'`. La validation doit
vérifier que le séjour existe et qu'il n'est pas annulé.

### 5.3 `app/controllers/HotelFactureController.php` (créer)

Nouveau contrôleur :

```
public function index($params)         // GET /hotel/facturation?sejour_id=X
public function preview($params)       // GET /api/hotel/factures/preview?sejour_id=X
                                       //   → agrège séjour + extras + paiements
public function store()                // POST /api/hotel/factures
                                       //   → crée hotel_factures + ventes (type HB)
public function show($params)          // GET /api/hotel/factures/[i:id]
public function cancel($params)        // POST /api/hotel/factures/[i:id]/cancel
```

#### Méthode `preview()` — cœur de la logique

Reçoit `?sejour_id=X` et renvoie un JSON agrégé :

```json
{
  "sejour": {
    "id": 42,
    "guest": { "id": 17, "nom": "Doe", "prenom": "John", "telephone": "+243..." },
    "client": { "id": 5, "code_client": "CLI-005", "nom_client": "John Doe" },
    "chambre": { "numero": "201", "type": "double" },
    "date_arrivee": "2026-10-15 14:00:00",
    "date_depart_prevue": "2026-10-18",
    "nb_nuits": 3,
    "prix_par_nuit": 50000.00,
    "remise_pct": 0.00
  },
  "nuits": {
    "total_ht": 150000.00,
    "lignes": [
      { "date": "2026-10-15", "prix": 50000.00 },
      { "date": "2026-10-16", "prix": 50000.00 },
      { "date": "2026-10-17", "prix": 50000.00 }
    ]
  },
  "extras": {
    "total_ht": 12500.00,
    "lignes": [
      { "vente_id": 1024, "date": "2026-10-16 18:30", "description": "Coca-Cola", "montant": 1500 },
      { "vente_id": 1037, "date": "2026-10-17 12:00", "description": "Plat du jour", "montant": 11000 }
    ]
  },
  "acomptes": [
    { "date": "2026-10-15 14:05", "montant": 50000, "mode": "cash" }
  ],
  "total_brut": 162500.00,
  "remise_applicable": 0.00,
  "tva": { "type": "B", "montant": 0.00, "note": "Régime hôtelier — TVA non collectée" },
  "total_final": 162500.00,
  "acompte_total": 50000.00,
  "solde_du": 112500.00
}
```

#### Méthode `store()`

Reçoit le payload complet (lignes d'extras ajoutées manuellement, remise globale,
modes de paiement) et :

1. Vérifie qu'il n'existe pas déjà une `hotel_factures` non-annulée pour ce séjour
2. Calcule les totaux serveur (re-validation, on ne fait pas confiance au client)
3. Génère un `numero_facture` unique (réutilise le pattern existant de
   `BillPaymentController::generateNumeroFacture` ou crée un helper)
4. INSERT dans `ventes` avec `invoice_type='HB'`, `client_id=sejour.client_id`,
   `sejour_id=NULL` (la facture officielle n'est PAS un extra du séjour — c'est
   l'inverse)
5. INSERT dans `hotel_factures` avec le `vente_id` retourné
6. Met à jour `hotel_sejours.statut='termine'` **uniquement si le payload contient
   `check_out: true`** — sinon le séjour reste `en_cours` et on peut réimprimer la
   même facture plus tard

### 5.4 Permissions

`guardWrite` du contrôleur facture : **manager + réceptionniste** (comme les guests).
Gouvernante = lecture seule (elle voit les factures des séjours mais ne peut pas
en émettre).

### 5.5 Routes à ajouter — `routes/web.php`

```php
// Page de facturation hôtel
Router::get('/hotel/facturation', [PageController::class, 'hotelFacturation']);
// API : page pré-remplie depuis séjour
Router::get('/api/hotel/factures/preview', [HotelFactureController::class, 'preview']);
// API : créer / lire / annuler
Router::post('/api/hotel/factures', [HotelFactureController::class, 'store']);
Router::get('/api/hotel/factures/[i:id]', [HotelFactureController::class, 'show']);
Router::post('/api/hotel/factures/[i:id]/cancel', [HotelFactureController::class, 'cancel']);
```

Et dans `PageController::hotelFacturation`, rendre la vue `app/views/hotel-facturation.php`.

---

## 6. Modifications PHP — frontend

### 6.1 `app/views/hotel-facturation.php` (créer)

Page dédiée, layout copié structurellement de `caisse.php` et `recharges.php`
(header, sidebar, zones de recherche client + panier + totaux + paiement).

Particularités :
- Bandeau en haut : « Séjour #42 — Chambre 201 — John DOE (CLI-005) — Arrivée 15/10 — Départ 18/10 »
- Tableau des nuits (auto, non éditable)
- Tableau des extras (éditable : ajout/suppression de lignes manuelles)
- Bloc acompte : lecture seule, affiche `hotel_paiements` déjà encaissés
- Bloc remise globale (input %)
- Bloc paiement : sélection modes (cash / mobile_money / carte / autre) avec montants
- Bouton « Émettre la facture » → POST → redirige vers `/facture/<vente_id>`
- Bouton « Check-out + facturer » → POST avec `check_out: true`

### 6.2 `app/views/hotel-reception.php` (modifier)

Ajouter dans la fiche séjour (drawer de détail) :

- Bouton « **Facturer** » → ouvre `/hotel/facturation?sejour_id=<id>`
- Si une `hotel_factures` existe déjà pour ce séjour : bouton « **Voir la facture** » à la place

### 6.3 `app/views/caisse.php` (modifier)

Dans la zone « Choisir le client » de la caisse :

- Ajouter un 2ème mode « Imputer à un séjour »
- Si sélectionné : champ autocomplete sur `hotel_sejours` actifs (chambre, nom guest, dates)
- Au submit : la vente est créée avec `sejour_id=<X>`, `invoice_type='EX'`
- **Le panier et le ticket restent strictement identiques** — la caissière ne change pas d'écran

### 6.4 `public/assets/js/hotel-facturation.js` (créer)

Logique JS de la page :
- Charge `preview()` au mount → remplit les blocs
- Gère l'ajout/suppression d'extras manuels (UI simple)
- Submit → POST avec tous les champs
- Affiche erreurs de validation (doublon facture, séjour clôturé, etc.)

### 6.5 `public/assets/js/caisse.js` (modifier)

Ajouter le toggle « Client POS / Séjour hôtelier » dans le sélecteur de facturation.
Si mode séjour sélectionné → champ de recherche → appel API
`/api/hotel/sejours/lookup?q=...` → l'utilisateur choisit → champ `sejour_id`
rempli → au submit, envoyé avec la vente.

### 6.6 `public/assets/js/hotel-reception.js` (modifier)

Brancher le bouton « Facturer » dans la fiche séjour → window.location vers
`/hotel/facturation?sejour_id=<id>`.

---

## 7. Tests à faire passer avant de merger

| # | Scénario | Attendu |
|---|----------|---------|
| 1 | Caissière : vendre un Coca depuis `/caisse` en mode « Imputer au séjour #42 » | Vente créée, `invoice_type='EX'`, `sejour_id=42`, ticket identique |
| 2 | Réceptionniste : ouvrir `/hotel/facturation?sejour_id=42` | Toutes les ventes `EX` du séjour apparaissent comme extras |
| 3 | Ajouter manuellement un extra « minibar 5000 FC » | Visible dans le tableau des extras |
| 4 | Valider la facture sans check-out | Facture émise, séjour reste `en_cours`, `statut='emise'` |
| 5 | Valider la facture avec check-out | Séjour passe `termine`, chambre passe `a_nettoyer` |
| 6 | Tenter de créer une 2ème facture sur le même séjour | 409 « facture déjà émise » |
| 7 | Annuler une facture → en créer une nouvelle | OK : ancienne en `statut='annulee'`, nouvelle créée |
| 8 | Gouvernante ouvre `/hotel/facturation` | 403 (lecture seule) |
| 9 | Facture d'un séjour lié à un client POS | `ventes.client_id` rempli, la facture apparaît dans l'historique du client |
| 10 | Acompte déjà versé de 50 000 FC | `solde_du = total_final − 50000` correctement calculé |

---

## 8. Fichiers touchés (récap)

### Créés
- `migrations/2026_10_17_hotel_facturation.sql`
- `app/controllers/HotelFactureController.php`
- `app/views/hotel-facturation.php`
- `public/assets/js/hotel-facturation.js`
- `docs/PLAN_HOTEL_FACTURATION.md` ← (ce fichier)

### Modifiés
- `app/models/Sale.php` (ajout `sejour_id` au payload INSERT)
- `app/controllers/SaleController.php` (validation + invoice_type='EX' si sejour_id)
- `routes/web.php` (5 nouvelles routes)
- `app/views/hotel-reception.php` (bouton « Facturer » dans la fiche séjour)
- `app/views/caisse.php` (toggle « Client / Séjour »)
- `public/assets/js/caisse.js` (toggle + autocomplete séjours)
- `public/assets/js/hotel-reception.js` (bouton Facturer)
- `app/controllers/PageController.php` (ajout `hotelFacturation()`)

### Non touchés (volontairement)
- `InvoiceController.php` : on **réutilise** `/facture/<id>` tel quel — la facture hôtel
  est une `vente` comme une autre, l'impression fonctionne out-of-the-box
- `Facture.php` / templates d'impression : on ne crée pas de template séparé,
  le template POS existant affiche déjà les extras, le client, les modes de paiement
- `HotelReservation.php` : on étend juste avec un helper `factureDuSejour()`,
  pas de refactor du modèle séjour

---

## 9. Hors périmètre (à faire plus tard)

- Reçu séparé pour acompte seul (le cas « facture finale nulle après acompte intégral » est
  couvert, mais pas l'impression d'un reçu d'acompte pré-séjour dédié)
- Export comptable des factures hôtel vers un logiciel tiers
- Relance automatique pour les séjours à `termine` sans facture au bout de 24h
- Modification d'une facture émise (on ne supporte que l'annulation + re-création)

---

## 10. Question ouverte à confirmer avant de coder

**Le code `HB` pour invoice_type** : à valider avec l'utilisateur. La nomenclature
DGI RDC officielle utilisée dans le projet est `FV/FA/FT/EA/EV/ET`. Si l'hôtellerie
a un code officiel distinct, il faut l'utiliser ; sinon on documente `HB` comme
convention interne. **Aucun impact technique**, juste conformité fiscale.