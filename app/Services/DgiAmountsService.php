<?php

namespace App\Services;

/**
 * DgiAmountsService
 *
 * Source de vérité des MONTANTS d'une facture : ce que la DGI a validé.
 *
 * CONTEXTE
 * --------
 * Jusqu'ici, `ventes.sous_total_ht` / `ventes.tva` / `ventes.total` étaient
 * persistés depuis les totaux CALCULÉS PAR LE FRONT (approximation locale :
 * prix × quantité + TS + TVA recalculée par le navigateur).
 *
 * Problèmes que cela causait :
 *   - le total encaissé et le total DGI divergeaient (arrondis, articles à
 *     taxe spécifique, remises, régime hôtelier…) ;
 *   - les rapports (CA HT, TVA collectée) ne reflétaient pas la réalité
 *     fiscale de la facture ;
 *   - la facture imprimée (elle) pouvait afficher un montant différent de
 *     celui enregistré en base.
 *
 * RÈGLE
 * -----
 * Après validation DGI, les 3 montants de la base doivent provenir de la
 * réponse de l'API DGI :
 *
 *     total         = data.total    (TTC  — montant réellement facturé)
 *     tva           = data.vtotal   (TVA  — total des ventilations vab/vac/…)
 *     sous_total_ht = total - vtotal (HT  — base HT de la facture)
 *
 * C'est exactement la décomposition demandée par la DGI, et elle est
 * AUTOCONTRÔLANTE : HT + TVA = TTC.
 *
 * SIGNES (avoir / annulation)
 * ----------------------------
 * Les factures d'avoir (FA) et d'avoir à l'exportation (EA) sont émises
 * avec des QUANTITÉS et des MONTANTS NÉGATIFS. Les montants renvoyés par
 * la DGI pour ce type de facture sont donc négatifs : on conserve tel quel
 * le signe renvoyé par l'API. Si l'API renvoyait des montants positifs
 * pour un avoir, on les re-negativise pour rester cohérent avec la
 * convention de stockage du projet (cf. `applyCreditNoteSign()`).
 *
 * FALLBACK OSAT (DGI hors service)
 * --------------------------------
 * Quand la DGI est indisponible, OSAT renvoie une facture « non assujettie » :
 * elle ne contient AUCUN élément de sécurité (compteur, code DEF/DGI,
 * NID, QR) et ses montants `total`/`vtotal` ne sont PAS la ventilation
 * fiscale réelle. Les appliquer écraserait le HT par `total - vtotal = 0`
 * et la TVA par un montant fantôme.
 *
 * On ne applique donc les montants DGI que si la réponse correspond à une
 * facture RÉELLEMENT ENREGISTRÉE chez la DGI (`hasSecurityFields()`).
 * Dans le cas du fallback, les montants du front sont conservés, et la
 * vente est mise en attente de résolution (cf. DgiSyncService) : les
 * montants DGI seront alors appliqués par la relève (callback OSAT ou
 * relecture automatique).
 *
 * Cette classe est PUR (aucun accès BDD) : elle est donc testable
 * unitairement, comme `DgiSyncService`.
 */
class DgiAmountsService
{
    /**
     * Éléments de sécurité d'une facture normalisée DGI.
     *
     * Si l'un d'eux est présent, la facture EST enregistrée chez la DGI et
     * ses montants sont fiables. Sinon, c'est un fallback « non assujettie »
     * (cf. le commentaire de classe).
     *
     * Liste volontairement identique à celle utilisée par
     * `DgiSyncService::hasSecurityFields()` (qui délègue désormais ici).
     */
    private const SECURITY_FIELDS = ['codeDEFDGI', 'counters', 'nim', 'qrCode', 'dateDGI'];

    /**
     * Types de facture qui sont des AVOIRS : leurs montants sont stockés en
     * négatif (convention du projet : cf. SaleController et app.js, qui
     * multiplient les totaux par -1 pour FA/EA).
     */
    private const CREDIT_NOTE_TYPES = ['FA', 'EA'];

    /**
     * Montants DGI normalisés.
     *
     * @return array{sous_total_ht:float, tva:float, total:float}|null
     *         null = aucune donnée DGI exploitable (→ garder les montants
     *         du front).
     */
    public function fromDgiPayload(?array $dgiData): ?array
    {
        if (!is_array($dgiData) || $dgiData === []) {
            return null;
        }

        // Les données peuvent être imbriquées (data) ou à la racine.
        $payload = isset($dgiData['data']) && is_array($dgiData['data'])
            ? $dgiData['data']
            : $dgiData;

        // `total` = TTC, `vtotal` = TVA cumulée.
        $total = $this->extractNumber($payload, ['total', 'total_ttc', 'ttc']);
        $vtotal = $this->extractNumber($payload, ['vtotal', 'tva', 'v_total']);

        if ($total === null || $vtotal === null) {
            return null;
        }

        // Le HT est la différence : c'est la décomposition DGI officielle.
        // On arrondit à 2 décimales (conventions du projet : DECIMAL(10,2)).
        $sousTotalHt = round($total - $vtotal, 2);

        return [
            'sous_total_ht' => $sousTotalHt,
            'tva'           => round($vtotal, 2),
            'total'         => round($total, 2),
        ];
    }

    /**
     * Applique les montants DGI à un jeu de montants existants.
     *
     * @param array       $current     Montants courants (ceux du front)
     * @param array|null  $dgiData     Réponse DGI (brute ou { data: {...} })
     * @param string|null $invoiceType Type de facture (FV, FA, EA, …) : sert à
     *                                 conserver le signe négatif des avoirs.
     *
     * @return array{sous_total_ht:float, tva:float, total:float, source:string}
     *         `source` = 'dgi' si les montants viennent de l'API,
     *         'original' si les montants entrants ont été conservés.
     */
    public function apply(array $current, ?array $dgiData, ?string $invoiceType = null): array
    {
        $fromDgi = $this->fromDgiPayload($dgiData);

        // Fallback « non assujettie » : les montants de la réponse ne sont pas
        // la ventilation fiscale réelle → on conserve les montants du front.
        if ($fromDgi === null || !self::hasSecurityFields($dgiData)) {
            return $this->keepCurrent($current);
        }

        // ── Garde-fou : ne jamais écraser un montant par un ZÉRO ─────
        // Le callback OSAT peut annoncer `"total": 0, "vtotal": 0` (cf. le
        // contrat CONTRAT_CALLBACK_DGI.md). Appliquer ces zéros
        // effacerait la comptabilité d'une facture réelle.
        // On ne remplace donc les montants que si la DGI annonce un total
        // NON NUL, ou si la vente est elle-même à zéro.
        if ($fromDgi['total'] == 0.0 && abs((float)($current['total'] ?? 0)) > 0.005) {
            return $this->keepCurrent($current);
        }

        return $this->applyCreditNoteSign($fromDgi, $invoiceType) + ['source' => 'dgi'];
    }

    /**
     * Conserve les montants entrants (repli).
     */
    private function keepCurrent(array $current): array
    {
        return [
            'sous_total_ht' => (float)($current['sous_total_ht'] ?? 0),
            'tva'           => (float)($current['tva'] ?? 0),
            'total'         => (float)($current['total'] ?? 0),
            'source'        => 'original',
        ];
    }

    /**
     * La réponse DGI contient-elle des montants exploitables ET
     * correspond-elle à une facture enregistrée ?
     */
    public function hasAmounts(?array $dgiData): bool
    {
        return $this->fromDgiPayload($dgiData) !== null
            && self::hasSecurityFields($dgiData);
    }

    /**
     * La réponse correspond-elle à une facture RÉELLEMENT enregistrée chez
     * la DGI (et non à un fallback « non assujettie ») ?
     *
     * Même test que celui utilisé par les vues (facture.php, facture-ticket.php)
     * et par `DgiSyncService::hasSecurityFields()`.
     */
    public static function hasSecurityFields(?array $dgiData): bool
    {
        if (!is_array($dgiData) || $dgiData === []) {
            return false;
        }

        $payload = isset($dgiData['data']) && is_array($dgiData['data'])
            ? $dgiData['data']
            : $dgiData;

        foreach (self::SECURITY_FIELDS as $field) {
            if (!empty($payload[$field]) && trim((string)$payload[$field]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Force le signe négatif des avoirs (FA / EA).
     *
     * La DGI peut renvoyer des montants déjà négatifs pour un avoir : on
     * conserve alors ce signe. Si elle renvoie des montants positifs, on
     * les met à nouveau en négatif pour rester cohérent avec la convention
     * de stockage des avoirs (montant négatif en base).
     */
    private function applyCreditNoteSign(array $amounts, ?string $invoiceType): array
    {
        $invoiceType = strtoupper(trim((string)$invoiceType));

        if (!in_array($invoiceType, self::CREDIT_NOTE_TYPES, true)) {
            return $amounts;
        }

        return [
            'sous_total_ht' => -abs($amounts['sous_total_ht']),
            'tva'           => -abs($amounts['tva']),
            'total'         => -abs($amounts['total']),
        ];
    }

    /**
     * Extrait un nombre d'un payload en essayant plusieurs noms de champ.
     *
     * ⚠️ On distingue « absent » (null) de « 0 » : un 0 est une valeur
     * LÉGITIME (facture exonérée : TVA = 0).
     */
    private function extractNumber(array $payload, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (float)$value;
            }
        }

        return null;
    }
}
