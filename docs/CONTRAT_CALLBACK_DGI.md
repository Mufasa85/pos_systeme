# Contrat d'API — Mise à jour DGI différée (callback OSAT)

> Document à transmettre à l'équipe OSAT (OSAT Énergie).
> Rédigé le 2026-10-27.

---

## 1. Le contexte en une phrase

Quand la DGI est indisponible, l'API OSAT reçoit notre demande de facturation,
la met **en attente de son côté** et nous renvoie un fallback. Quand la DGI
redevient disponible, OSAT rejoue la demande, obtient la résolution — et doit
alors **venir nous la livrer** pour qu'on complète la ligne de vente
correspondante dans notre base.

Aujourd'hui, cette information reste bloquée chez OSAT : de notre côté, la
facture est enregistrée mais **sans les éléments de sécurité DGI** (compteur,
code DEF/DGI, NID, QR code), qui sont indispensables pour un ticket fiscal
conforme.

---

## 2. L'endpoint à appeler

```
POST https://<votre-domaine>/api/dgi/update
Content-Type: application/json
```

**URL exacte à nous communiquer** — on l'adaptera dans notre configuration.

---

## 3. L'authentification

OSAT appelle une route **sans session utilisateur** : la sécurité repose donc
sur un **secret partagé**, transmis dans un en-tête HTTP.

Deux modes acceptés, à votre convenance :

| Mode | En-tête | Calcul |
| ---- | ------- | ------ |
| Simple | `X-DGI-Token: <secret>` | le secret en clair |
| Fort (recommandé) | `X-DGI-Signature: <hex>` | `HMAC-SHA256(corps_requête, secret)` en hexadécimal |

Si les deux en-têtes sont présents, `X-DGI-Signature` est prioritaire.

**Le secret** vous sera communiqué par un canal privé. Il est stocké côté
serveur dans `config/config.php` (constante `DGI_WEBHOOK_TOKEN`).

> ⚠️ Tant que le secret n'est pas configuré de notre côté, la route refuse
> **tous** les appels (réponse `401`). C'est volontaire : une écriture en base
> ne doit jamais être exposée sans authentification.

---

## 4. Le corps de la requête

La structure est **celle d'une réponse DGI normale** — les mêmes champs que
ceux que vous nous avez déjà retournés lors de l'émission.

```json
{
  "invoice_number": "2026/000123",
  "store_isf": "A900011T",
  "data": {
    "codeDEFDGI": "…",
    "counters": "…",
    "nim": "…",
    "qrCode": "…",
    "dateDGI": "…",
    "dateTime": "…",
    "total": 12500.00,
    "vtotal": 14500.00
  },
  "comment": null
}
```

### Champs

| Champ | Requis | Description |
| ----- | ------ | ----------- |
| `invoice_number` | **Oui** | La clé de la facture — celle que nous vous avons envoyée dans le POST `/api/dgi` (`invoice_number`). C'est la **clé de correspondance** : elle nous permet de retrouver la ligne de vente à compléter. |
| `store_isf` | Recommandé | L'ISF de la boutique émettrice. Sert de garde-fou multi-boutique : la vente doit appartenir à la bonne boutique. |
| `data` | **Oui** | Les données DGI (mêmes champs que votre réponse d'émission). |
| `comment` | Optionnel | Commentaire éventuel. |

### Tolérance du parsing

Notre route accepte plusieurs variantes de nommage, pour ne pas casser le
contrat si vous ajustez :

- `invoice_number` | `dgi_invoice_number` | `numero_facture`
- `store_isf` | `isf`
- les données peuvent être **à la racine** du JSON ou imbriquées sous `data`.

Les champs DGI utilisés sont : `codeDEFDGI`, `counters`, `nim`, `qrCode`, et
`dateDGI` (ou `dateTime` en repli).

---

## 5. Nos réponses

| Code | Corps | Signification |
| ---- | ----- | ------------- |
| **200** | `{"success":true,"updated":true,…}` | Mise à jour effectuée. |
| **202** | `{"success":true,"updated":false,"queued":true}` | Reçu, mais la vente n'existe pas encore chez nous (vous avez résolu avant que nous n'ayons enregistré la vente). Nous la rattraperons automatiquement — **aucune action requise de votre part**. |
| **400** | `{"success":false,"error":"…"}` | Requête invalide (JSON malformé ou `invoice_number` absent). |
| **401** | `{"success":false,"error":"unauthorized"}` | Secret absent ou incorrect. |
| **404** | `{"success":false,"error":"…"}` | Aucune vente ne correspond à cette clé. |

> L'opération est **idempotente** : un rejeu du même callback écrase avec les
> mêmes valeurs, sans effet de bord. Vous pouvez donc réessayer sans risque.

---

## 6. Ce que nous faisons de notre côté (pour information)

Pour que la correspondance fonctionne, voici comment nous gérons la clé :

1. Lors de l'émission, nous envoyons `invoice_number` dans le POST `/api/dgi`.
   C'est **exactement** cette valeur que nous figeons en base (colonne
   `ventes.dgi_invoice_number`).
2. À la réception du callback, nous retrouvons la vente par
   `dgi_invoice_number = invoice_number`, avec `numero_facture` en repli.
3. **C'est pourquoi il est important que `invoice_number` soit renvoyé tel
   quel** — ne pas le reformater, ne pas le préfixer.

---

## 7. Filet de sécurité (de notre côté)

Si le callback n'aboutit pas (notre serveur hors ligne, coupure réseau, URL non
encore communiquée…), nous relisons nous-mêmes la facture auprès de votre
endpoint `GET /dgi/facture/` (celui que nous utilisons déjà pour afficher les
tickets). Un script de fond fait cette relecture toutes les 10 minutes.

→ **Le callback n'est donc pas bloquant** : il rend la mise à jour instantanée,
mais la fonctionnalité est opérationnelle même sans lui.

---

## 8. Questions à confirmer de votre côté

1. Pouvez-vous appeler une **URL publique** de notre application ? (notre app
   doit être exposée sur Internet, pas seulement en local/LAN)
2. Le `invoice_number` que vous mémorisez pour une demande en attente est-il
   **exactement** celui reçu dans le POST ? (y compris sa casse et ses
   séparateurs)
3. Le `store_isf` mémorisé est-il lui aussi **exactement** celui reçu ?
4. Lors de la résolution, pouvez-vous nous renvoyer la **structure complète**
   (c'est-à-dire la même que votre réponse d'émission) ? En particulier les
   champs `codeDEFDGI`, `counters`, `nim` et `qrCode`.
5. Sur quelle URL (ou quel endpoint) votre automation appelle-t-elle notre
   callback ? Pouvez-vous nous la communiquer pour qu'on la teste de notre côté ?
6. Le secret : préférez-vous le mode `X-DGI-Token` (plus simple à implémenter)
   ou `X-DGI-Signature` (HMAC, plus sûr) ?
