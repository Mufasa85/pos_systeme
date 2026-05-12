# API OSAT-Energie Integration

## Endpoint

```
POST https://osat-energie.com/json.php
```

## Payload

```json
{
  "compteur": "FEZ00000002A",
  "service": "SNEL" // ou "REGIDESO"
}
```

## Expected Response

```json
{
  "success": true,
  "deviceid": "FEZ00000002A",
  "results": {
    "2026": {
      "janvier": [{ "MONTANT": "0.00", "NUMERO_FACTURE": "..." }],
      "fevrier": [{ "MONTANT": "2500.00", "NUMERO_FACTURE": "..." }]
    },
    "2025": {
      "janvier": [{ "MONTANT": "9062.92", "NUMERO_FACTURE": "..." }]
    }
  }
}
```

## Implementation Notes

### File: `public/assets/js/recharges.js`

- `callProviderAPI()` - Fait l'appel POST à l'API
- `fetchBillInquiry()` - Extrait les années disponibles
- `loadYear()` - Parse `results[annee][mois]` pour afficher les cards
- `toggleMonth()` - Sélectionne uniquement les mois impayés (montant > 0)

### File: `app/views/recharges.php`

- `onSearchInvoice()` - Bouton "Rechercher" appelle `billPayment.fetchBillInquiry()`
- `filterByYear()` - Filtre année appelle `billPayment.loadYear()`
- `#months-section` + `#months-grid` - Conteneur pour les cards de mois

### File: `app/views/layout/footer.php`

- Ajout du script: `<script src="./assets/js/recharges.js?v=1.0.1"></script>`

## Flow

```
1. Sélectionner SNEL/REGIDESO + Entrer n° compteur
2. Cliquer "Rechercher" → POST https://osat-energie.com/json.php
3. API retourne results[annee][mois] avec montants
4. Afficher années dans filtre + cards de mois impayés
5. Sélectionner mois → Ajout au panier
6. Valider → Insert dans table `ventes` (type=bill_payment)
```

## Testing

1. Ouvrir `/recharges`
2. Choisir SNEL
3. Entrer: FEZ00000002A
4. Cliquer Rechercher
5. Voir les cards de mois s'afficher
6. Sélectionner les mois impayés
7. Voir dans le panier
