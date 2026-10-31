# Fonction de callback DGI — à intégrer côté OSAT

> Document destiné à l'équipe OSAT (OSAT Énergie).
> Date : 2026-10-27

---

## 1. Ce que fait cette fonction

Quand la DGI redevient disponible et qu'OSAT a résolu une demande de
facturation mise en attente, cette fonction envoie la mise à jour à notre
application pour compléter la ligne de vente correspondante.

**Elle ne réémet rien** : OSAT a déjà joué le rôle auprès de la DGI, cette
fonction ne fait que livrer le résultat.

---

## 2. La fonction PHP (à copier-coller)

```php
<?php

/**
 * Envoie la résolution DGI d'une facture à l'application POS.
 *
 * @param string $invoiceNumber Clé de la facture (celle reçue dans le POST
 *                               /api/dgi — à renvoyer TEL QUELLE, sans
 *                               reformater ni préfixer).
 * @param string $storeIsf       ISF de la boutique émettrice.
 * @param array  $dgiData        Données DGI de la facture résolue :
 *                               codeDEFDGI, counters, nim, qrCode, dateDGI,
 *                               total, vtotal, …
 * @param string $baseUrl        URL de l'application POS.
 * @param string $token          Secret partagé (X-DGI-Token).
 *
 * @return array{success:bool, http_code:int, message:string}
 */
function notifyPosDgiResolved(
    string $invoiceNumber,
    string $storeIsf,
    array $dgiData,
    string $baseUrl = 'http://localhost:8000',
    string $token = 'ed2954474cb6c5b62d1dddb2df64efd4'
): array {
    // URL cible
    $url = rtrim($baseUrl, '/') . '/api/dgi/update';

    // Corps de la requête : la structure d'une réponse DGI normale
    $payload = [
        'invoice_number' => $invoiceNumber,
        'store_isf'      => $storeIsf,
        'data'           => $dgiData,
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return [
            'success'    => false,
            'http_code'  => 0,
            'message'    => 'Erreur encodage JSON : ' . json_last_error_msg(),
        ];
    }

    // Appel HTTP
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-DGI-Token: ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // ── Erreur réseau ──────────────────────────────────────────────
    if ($response === false || trim((string)$response) === '') {
        return [
            'success'   => false,
            'http_code' => (int)$httpCode,
            'message'   => 'Erreur connexion au POS : ' . $curlError,
        ];
    }

    // ── Interprétation de la réponse ───────────────────────────────
    $data = json_decode($response, true);

    // 200 = mise à jour effectuée
    if ((int)$httpCode === 200 && !empty($data['success'])) {
        return [
            'success'   => true,
            'http_code' => 200,
            'message'   => 'Facture mise à jour côté POS',
            'response'  => $data,
        ];
    }

    // 202 = reçu, mais la vente n'existe pas encore chez le POS.
    // Ce n'est PAS une erreur : le POS la rattrapera tout seul.
    if ((int)$httpCode === 202) {
        return [
            'success'   => true,
            'http_code' => 202,
            'message'   => 'Reçu — vente pas encore créée côté POS, rattrapage automatique prévu',
            'response'  => $data,
        ];
    }

    // 401 = secret invalide → à corriger une seule fois côté configuration
    if ((int)$httpCode === 401) {
        return [
            'success'   => false,
            'http_code' => 401,
            'message'   => 'Secret DGI refusé par le POS (X-DGI-Token incorrect)',
            'response'  => $data,
        ];
    }

    // 404 = aucune vente ne correspond à cette clé
    if ((int)$httpCode === 404) {
        return [
            'success'   => false,
            'http_code' => 404,
            'message'   => 'Aucune vente ne correspond à cette clé (invoice_number / store_isf)',
            'response'  => $data,
        ];
    }

    // Tout le reste
    return [
        'success'   => false,
        'http_code' => (int)$httpCode,
        'message'   => 'Réponse inattendue du POS : ' . substr((string)$response, 0, 200),
        'response'  => $data,
    ];
}
```

---

## 3. Exemple d'appel

```php
$result = notifyPosDgiResolved(
    invoiceNumber: '2026/001000',
    storeIsf: 'A1720894F',
    dgiData: [
        'codeDEFDGI' => 'TEST-123',
        'counters'   => '9999/9999 FV',
        'nim'        => 'CD-TEST',
        'qrCode'     => 'TEST-QR',
        'dateDGI'    => '2026-10-27 17:40:00',
    ]
);

if ($result['success']) {
    echo "OK : " . $result['message'] . PHP_EOL;
} else {
    error_log('[DGI] Échec callback (' . $result['http_code'] . ') : ' . $result['message']);
    // → reprogrammer une nouvelle tentative plus tard
}
```

---

## 4. Variante : version signée (HMAC) — plus sûre

Si vous préférez authentifier par signature plutôt que par secret en clair,
remplacez l'en-tête `X-DGI-Token` par :

```php
// Dans la fonction, remplacer :
//   'X-DGI-Token: ' . $token,
// par :
$signature = hash_hmac('sha256', $body, $token);

// et ajouter :
//   'X-DGI-Signature: ' . $signature,
```

---

## 5. Variante : appel cURL en ligne de commande (test rapide)

```bash
curl -X POST "http://localhost:8000/api/dgi/update" \
  -H "Content-Type: application/json" \
  -H "X-DGI-Token: ed2954474cb6c5b62d1dddb2df64efd4" \
  -d '{
    "invoice_number": "2026/001000",
    "store_isf": "A1720894F",
    "data": {
      "codeDEFDGI": "TEST-123",
      "counters": "9999/9999 FV",
      "nim": "CD-TEST",
      "qrCode": "TEST-QR",
      "dateDGI": "2026-10-27 17:40:00"
    }
  }'
```

---

## 6. Le contrat

### Requête

```
POST <URL_APPLICATION>/api/dgi/update
Content-Type: application/json
X-DGI-Token: ed2954474cb6c5b62d1dddb2df64efd4
```

```json
{
  "invoice_number": "2026/001000",
  "store_isf": "A1720894F",
  "data": {
    "codeDEFDGI": "TEST-123",
    "counters": "9999/9999 FV",
    "nim": "CD-TEST",
    "qrCode": "TEST-QR",
    "dateDGI": "2026-10-27 17:40:00"
  }
}
```

| Champ | Requis | Rôle |
|---|---|---|
| `invoice_number` | **Oui** | La clé de la facture — celle reçue dans le POST `/api/dgi`. **À renvoyer à l'identique.** |
| `store_isf` | Recommandé | ISF de la boutique émettrice (garde-fou multi-boutique). |
| `data` | **Oui** | Les données DGI. Au moins un de : `codeDEFDGI`, `counters`, `nim`, `qrCode`, `dateDGI`. |

### Réponses

| Code | Signification | Action requise |
|---|---|---|
| **200** | Mise à jour effectuée | ✅ aucune |
| **202** | Reçu, vente pas encore créée | ✅ aucune (rattrapage auto) |
| **400** | Requête invalide | ❌ corriger le corps |
| **401** | Secret invalide | ❌ vérifier `X-DGI-Token` |
| **404** | Aucune vente pour cette clé | ❌ vérifier `invoice_number` / `store_isf` |

L'opération est **idempotente** : un rejeu est sans risque.

---

## 7. À faire avant la mise en production

1. **Remplacer l'URL** `http://localhost:8000` par l'URL publique de notre
   application (l'application doit être accessible depuis Internet).
2. **Remplacer le secret** `ed2954474cb6c5b62d1dddb2df64efd4` par un secret
   définitif, communiqué par un canal privé.
3. **Vérifier** que `invoice_number` est renvoyé **exactement** comme reçu.
4. **Tester** avec une vraie facture résolue (une qui a un `codeDEFDGI` et un
   `counters` réels).

---

## 8. En cas de problème

| Symptôme | Cause probable | Correction |
|---|---|---|
| `401` systématique | Secret non configuré côté POS, ou différent | Vérifier le secret partagé |
| `404` systématique | `invoice_number` reformaté (préfixe `FACTURE n°`, trim…) | Renvoyer la valeur **telle quelle** |
| `202` répété | La vente n'existe pas encore côté POS | Aucun : le POS rattrape automatiquement |
| Timeout réseau | L'URL n'est pas accessible depuis Internet | Utiliser l'URL publique + HTTPS |
