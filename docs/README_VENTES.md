# README - Flux des Données de Ventes

## 📡 Endpoint Principal

```
POST /api/vente
```

---

## 📥 Structure JSON Envoyée

```json
{
  "client_id": 1,
  "sous_total_ht": 1500.00,
  "tva": 180.00,
  "total": 1680.00,
  "dgi_data": {
    "dateDGI": "2026-05-10T13:19:00",
    "qrCode": "QR_CODE_STRING",
    "codeDEFDGI": "DEF_CODE",
    "counters": { "sign": 123, "c": 456 },
    "nim": "NIM_VALUE",
    "comment": "Commentaire optionnel"
  },
  "articles": [
    {
      "produit_id": 5,
      "quantite": 2,
      "prix": 750.00
    }
  ]
}
```

---

## 🗂️ Champs Détaillés

### Données Principales
| Champ | Type | Description | Requis |
|-------|------|-------------|--------|
| `client_id` | int | ID du client | ❌ Non (défaut: Particulier) |
| `sous_total_ht` | float | Sous-total hors taxes | ✅ Oui |
| `tva` | float | Montant TVA | ✅ Oui |
| `total` | float | Total TTC | ✅ Oui |

### Données DGI (Optionnel)
| Champ | Type | Description |
|-------|------|-------------|
| `dgi_data.dateDGI` | string | Date/heure certification DGI |
| `dgi_data.qrCode` | string | Code QR DGI |
| `dgi_data.codeDEFDGI` | string | Code DUI/DEF |
| `dgi_data.counters` | object | Compteurs DGI |
| `dgi_data.nim` | string | Numéro d'identification |
| `dgi_data.comment` | string | Commentaire libre |

### Articles (Requis)
| Champ | Type | Description |
|-------|------|-------------|
| `articles[].produit_id` | int | ID du produit |
| `articles[].quantite` | int | Quantité vendue |
| `articles[].prix` | float | Prix unitaire HT |

---

## 🔄 Flux de Traitement

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (app.js)                        │
│  1. Construit le panier                                     │
│  2. Ajoute client_id, totaux                                │
│  3. Envoie POST /api/vente avec JSON                       │
└──────────────────────────┬──────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              SaleController::create()                       │
│                                                             │
│  1. Vérifie authentification (SESSION['user_id'])           │
│  2. json_decode(php://input)                                │
│  3. Validation: panier non vide                             │
│  4. Client par défaut si non fourni (Particulier)          │
└──────────────────────────┬──────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Sale::create()                           │
│  → Table: `ventes`                                          │
│                                                             │
│  Champs stockés:                                            │
│  - numero_facture (auto: FAC-XXXXXX)                        │
│  - client_id                                                │
│  - sous_total_ht                                           │
│  - tva                                                      │
│  - total                                                    │
│  - vendeur_id (depuis SESSION)                             │
│  - date (NOW)                                               │
│  - dateDGI, qrCode, codeDEFDGI, counters, nim, comment     │
└──────────────────────────┬──────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              POUR CHAQUE ARTICLE                            │
│                                                             │
│  ┌─ SaleDetail::create()                                    │
│  │  → Table: `details_vente`                                │
│  │  Champs: vente_id, produit_id, quantite, prix             │
│  │                                                         │
│  └─ Product::updateStock()                                  │
│     → Table: `produits` (stock -= quantite)                 │
└──────────────────────────┬──────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                  RÉPONSE JSON                               │
│                                                             │
│  Succès:  {"success": true, "numero_facture": "FAC-000001", "vente_id": 1}
│  Erreur:  {"error": "Stock insuffisant..."}                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Tables de Base de Données

### Table `ventes`
```sql
INSERT INTO ventes (
    numero_facture, client_id, sous_total_ht, tva, total,
    vendeur_id, date, dateDGI, qrCode, codeDEFDGI, counters, nim, comment
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
```

### Table `details_vente`
```sql
INSERT INTO details_vente (
    vente_id, produit_id, quantite, prix
) VALUES (?, ?, ?, ?);
```

### Table `produits` (mise à jour stock)
```sql
UPDATE produits SET stock = stock - ? WHERE id = ?;
```

---

## 📝 Exemple Complet

### Requête
```bash
curl -X POST http://localhost/api/vente \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 3,
    "sous_total_ht": 5000,
    "tva": 600,
    "total": 5600,
    "dgi_data": {
      "dateDGI": "2026-05-10T13:19:00",
      "qrCode": "ABC123XYZ",
      "codeDEFDGI": "DEF001"
    },
    "articles": [
      {"produit_id": 5, "quantite": 2, "prix": 2500},
      {"produit_id": 12, "quantite": 1, "prix": 0}
    ]
  }'
```

### Réponse
```json
{
  "success": true,
  "numero_facture": "FAC-000012",
  "vente_id": 12
}
```

---

## ⚠️ Validation & Erreurs

| Code | Erreur | Cause |
|------|--------|-------|
| 400 | "Panier vide ou données invalides" | Pas d'articles |
| 400 | "Stock insuffisant pour le produit: X" | Stock insuffisant |
| 403 | "Non authentifié" | Non connecté |
| 500 | "Erreur lors de la vente" | Erreur SQL/BDD |

---

## 🔗 Routes Associées

| Méthode | Route | Handler |
|---------|-------|---------|
| POST | `/api/vente` | SaleController::create |
| GET | `/api/vente/[id]/details` | SaleController::details |
| GET | `/api/vente/next-invoice` | SaleController::nextInvoice |
| POST | `/api/delete/vente` | SaleController::delete |