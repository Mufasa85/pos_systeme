# 📋 API REGIDESO Response Parsing

## Structure de la réponse API

```json
{
    "success": true,
    "deviceid": "FEZ00000002A",
    "periode": {
        "debut": 2026,
        "fin": 2013
    },
    "results": {
        "2026": {
            "janvier": [ { "MONTANT": "0.00", ... } ],
            "fevrier": [ { "MONTANT": "0.00", ... } ],
            ...
        },
        "2025": {
            "janvier": [ { "MONTANT": "9062.92", ... } ],
            ...
        }
    }
}
```

## Extraction des données

```javascript
// Réponse API
const apiResponse = {
    results: {
        2026: { janvier: [...], fevrier: [...] },
        2025: { janvier: [...], fevrier: [...] }
    }
};

// Extraire les années disponibles
const availableYears = Object.keys(apiResponse.results).sort((a,b) => b - a);
// ["2026", "2025", "2024", ...]

// Pour une année donnée
const yearData = apiResponse.results[2025];
// { janvier: [...], fevrier: [...] }

// Pour un mois donné
const january2025 = yearData.janvier;
// [ { MONTANT: "9062.92", NUMERO_FACTURE: "...", COMMUNE: "...", ... } ]

// Montant du mois (prendre le premier ou sommer)
const montantJan = parseFloat(january2025[0]?.MONTANT) || 0;
```

## Déterminer si impayé

```javascript
function isUnpaid(monthData) {
  // Un impayé = montant > 0 ET pas encore payé
  // Dans cette API, tous les enregistrements semblent être des factures émises
  // On considere impayé si MONTANT > 0
  return parseFloat(monthData[0]?.MONTANT || 0) > 0;
}
```

## Mapping Mois

```javascript
const moisMapping = {
  janvier: 1,
  fevrier: 2,
  mars: 3,
  avril: 4,
  mai: 5,
  juin: 6,
  juillet: 7,
  aout: 8,
  septembre: 9,
  octobre: 10,
  novembre: 11,
  decembre: 12,
};
```

## Format pour le panier

```javascript
// Convertir en format standard pour le panier
function extractMonthData(year, monthName, monthRecords) {
  return {
    annee: parseInt(year),
    mois: moisMapping[monthName],
    mois_nom: monthName.charAt(0).toUpperCase() + monthName.slice(1),
    montant: parseFloat(monthRecords[0]?.MONTANT) || 0,
    numero_facture: monthRecords[0]?.NUMERO_FACTURE || "",
    commune: monthRecords[0]?.COMMUNE || "",
    est_impaye: parseFloat(monthRecords[0]?.MONTANT || 0) > 0,
  };
}
```
