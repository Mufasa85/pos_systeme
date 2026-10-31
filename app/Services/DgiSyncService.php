<?php

namespace App\Services;

use App\Core\Database;
use App\Models\CompanyInfo;
use App\Models\Shop;

/**
 * DgiSyncService
 *
 * Gère la mise à jour différée d'une vente auprès de la DGI.
 *
 * CONTEXTE
 * --------
 * Quand la DGI est indisponible, l'API OSAT renvoie un fallback : la réponse
 * a la forme d'une facture "non assujettie" et porte le drapeau
 * `store_homologation = 2` qui signifie « cas d'update ». OSAT conserve la
 * demande en attente de son côté, la rejoue auprès de la DGI quand celle-ci
 * revient, puis vient chercher la mise à jour chez nous.
 *
 * Deux chemins alimentent ce service :
 *   1. POST /api/dgi/update  → OSAT pousse la résolution (instantané).
 *   2. GET  /dgi/facture/    → relecture par nos soins (filet de sécurité
 *      quand le push n'a pas abouti, ou quand OSAT n'a pas notre URL).
 *
 * RÈGLES
 * ------
 * - On ne réémet JAMAIS une facture : OSAT s'en charge. Notre rôle se limite
 *   à écrire les éléments de sécurité dans la base.
 * - On n'écrase jamais `ventes.tva` / `ventes.total` : ce sont les montants
 *   que le client a réellement payés, notre référence comptable.
 * - On n'écrase jamais `ventes.comment` : la colonne est aussi utilisée par le
 *   POS et par le module Hôtel (cf. HotelFactureController::store).
 */
class DgiSyncService
{
    /** Statuts possibles de la colonne `ventes.dgi_status`. */
    public const STATUS_SYNCED         = 'synced';
    public const STATUS_PENDING        = 'pending';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /** URL de l'API OSAT qui renvoie une facture déjà enregistrée. */
    private const OSAT_FACTURE_URL = 'https://osat-energie.com/dgi/facture/';

    /** Nombre max de tentatives avant d'abandonner une vente. */
    private const MAX_ATTEMPTS = 20;

    /** @var \App\Core\Database|null */
    private $db;

    /**
     * La connexion est résolue à la demande (lazy) : les méthodes purement
     * logiques (détection du fallback, statut initial) restent utilisables
     * sans base de données — ce qui les rend testables unitairement.
     */
    public function __construct()
    {
        $this->db = null;
    }

    /**
     * Connexion à la base, résolue au premier besoin.
     */
    private function db()
    {
        if ($this->db === null) {
            $this->db = Database::getInstance();
        }

        return $this->db;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Détection du fallback
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Le fallback OSAT a exactement la forme d'une facture "non assujettie" :
     * les éléments de sécurité (compteur DGI, code DEF/DGI, NID, QR) sont
     * absents. C'est le même test que celui utilisé par les vues
     * (facture.php:6, facture-ticket.php:7).
     */
    public function hasSecurityFields(?array $dgiData): bool
    {
        if (!is_array($dgiData) || $dgiData === []) {
            return false;
        }

        foreach (['codeDEFDGI', 'counters', 'nim', 'qrCode', 'dateDGI'] as $field) {
            if (!empty($dgiData[$field]) && trim((string)$dgiData[$field]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Interprète le drapeau `store_homologation` renvoyé par OSAT.
     *
     *   1 → boutique homologuée, émission normale
     *   2 → CAS D'UPDATE : la demande est en attente de résolution DGI
     *   0 → boutique non homologuée (structurel : rien n'arrivera jamais)
     *
     * Le front applique déjà la même convention
     * (app.js:1849, recharges.js:1476, facture.php:681) : la valeur 2 est
     * traitée comme « homologué », ce qui est correct — une facture en attente
     * d'update reste une vraie facture, pas une proforma.
     */
    public function isUpdateCase($rawHomologation): bool
    {
        if ($rawHomologation === null || $rawHomologation === '') {
            return false;
        }

        return (string)$rawHomologation === '2';
    }

    /**
     * Détermine le statut initial d'une vente à sa création.
     *
     * @param array       $dgiData      Données DGI renvoyées par OSAT (dgi_data)
     * @param string|null $invoiceType  Type de facture (FV, FA, HB, EX…)
     * @param bool        $homologation Statut d'homologation de la boutique
     */
    public function resolveInitialStatus(array $dgiData, ?string $invoiceType, bool $homologation): string
    {
        // 1. Extra d'hôtel : aucune facture fiscale n'est émise (EX).
        //    CE CAS EST TESTÉ EN PREMIER : un extra ne transite pas par la DGI,
        //    donc même si OSAT signale un cas d'update (store_homologation = 2),
        //    il n'y a rien à récupérer. Sans ce test en tête, un extra serait
        //    marqué 'pending' et tourné en boucle par la relecture automatique.
        if ($invoiceType === 'EX') {
            return self::STATUS_NOT_APPLICABLE;
        }

        // 2. Les éléments de sécurité sont là => la facture est enregistrée.
        if ($this->hasSecurityFields($dgiData)) {
            return self::STATUS_SYNCED;
        }

        // 3. Pas d'éléments de sécurité, mais OSAT a signalé un cas d'update.
        if ($this->isUpdateCase($dgiData['store_homologation'] ?? null)) {
            return self::STATUS_PENDING;
        }

        // 4. Boutique non homologuée : structurel, aucune donnée ne viendra.
        if (!$homologation) {
            return self::STATUS_NOT_APPLICABLE;
        }

        // 5. Boutique homologuée mais pas d'éléments de sécurité : la DGI était
        //    probablement hors service au moment de l'émission => on attend.
        return self::STATUS_PENDING;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Recherche de la vente
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retrouve la ligne `ventes` à mettre à jour à partir de la clé OSAT.
     *
     * OSAT fournit `invoice_number` + `store_isf`. La clé `invoice_number` est
     * celle qu'OSAT a mémorisée lors du POST /api/dgi : elle est figée dans
     * `ventes.dgi_invoice_number` (cf. SaleController::create). On retient
     * `numero_facture` comme repli pour les ventes créées avant la migration.
     *
     * @return array|null La ligne `ventes`, ou null si introuvable
     */
    public function findSaleByDgiKey(string $invoiceNumber, ?string $storeIsf = null): ?array
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            return null;
        }

        // ⚠️ PDO en mode natif (ATTR_EMULATE_PREPARES = false, cf.
        // app/core/Database.php) n'accepte pas qu'un même paramètre nommé
        // soit utilisé DEUX FOIS dans la requête → on utilise deux
        // marqueurs distincts pour la même valeur.
        $params = [':key' => $invoiceNumber, ':key2' => $invoiceNumber];
        $sql = 'SELECT v.* FROM ventes v
                WHERE (v.dgi_invoice_number = :key OR v.numero_facture = :key2)';

        if ($storeIsf !== null && trim($storeIsf) !== '') {
            // Garde-fou multi-boutique : la vente doit appartenir à la
            // boutique correspondant à l'ISF transmis.
            $sql .= ' AND (v.store_isf = :isf OR v.store_isf IS NULL OR v.store_isf = \'\')';
            $params[':isf'] = trim($storeIsf);
        }

        $sql .= ' ORDER BY v.id ASC LIMIT 1';

        $sale = $this->db()->fetch($sql, $params);

        return $sale ?: null;
    }

    /**
     * Liste les ventes en attente de résolution DGI.
     *
     * @param int      $limit    Nombre max de ventes retournées
     * @param int|null $shopId   Restreint à une boutique (null = toutes)
     */
    public function findPendingSales(int $limit = 50, ?int $shopId = null): array
    {
        $params = [];
        $sql = "SELECT v.* FROM ventes v WHERE v.dgi_status = 'pending'";

        if ($shopId !== null) {
            $sql .= ' AND v.shop_id = ?';
            $params[] = $shopId;
        }

        $sql .= ' ORDER BY v.id ASC LIMIT ?';
        $params[] = $limit;

        return $this->db()->fetchAll($sql, $params);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Écriture en base
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Applique les données DGI reçues à la ligne `ventes`.
     *
     * @param array $sale  La ligne `ventes` à mettre à jour
     * @param array $data  Les données DGI (forme : { success, data: {...} })
     *
     * @return array{done:bool, applied:array, reason:string}
     */
    public function applyDgiData(array $sale, array $data): array
    {
        // On accepte aussi bien { data: {...} } que {...} directement.
        $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        if (!$this->hasSecurityFields($payload)) {
            return [
                'done'    => false,
                'applied' => [],
                'reason'  => 'Aucun élément de sécurité DGI dans la réponse',
            ];
        }

        $applied = [];

        // dateDGI peut arriver sous deux noms différents selon l'API.
        $dateDgi = $payload['dateDGI'] ?? ($payload['dateTime'] ?? null);
        if ($dateDgi !== null && trim((string)$dateDgi) !== '') {
            $applied['dateDGI'] = (string)$dateDgi;
        }

        foreach (['codeDEFDGI', 'counters', 'nim'] as $field) {
            if (!empty($payload[$field])) {
                $applied[$field] = (string)$payload[$field];
            }
        }

        if (!empty($payload['qrCode'])) {
            $applied['qrCode'] = (string)$payload['qrCode'];
        }

        if ($applied === []) {
            return [
                'done'    => false,
                'applied' => [],
                'reason'  => 'Rien à écrire',
            ];
        }

        // On conserve la réponse brute : elle contient des informations que
        // la base ne stocke pas ailleurs (ventilation HT/TVA par catégorie DGI,
        // articles, total/vtotal). Elle permet de re-rendre un ticket enrichi
        // sans rappeler l'API, et de survivre à une panne OSAT.
        $applied['dgi_response'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $applied['dgi_status'] = self::STATUS_SYNCED;
        $applied['dgi_synced_at'] = date('Y-m-d H:i:s');
        $applied['dgi_last_error'] = null;

        $this->updateSale((int)$sale['id'], $applied);

        return [
            'done'    => true,
            'applied' => array_keys($applied),
            'reason'  => '',
        ];
    }

    /**
     * Écrit les champs DGI sur une ligne `ventes`.
     */
    private function updateSale(int $saleId, array $fields): void
    {
        $allowed = [
            'dateDGI', 'qrCode', 'codeDEFDGI', 'counters', 'nim',
            'dgi_response', 'dgi_status', 'dgi_synced_at', 'dgi_last_error',
            'dgi_attempts', 'dgi_invoice_number',
        ];

        $sets = [];
        $params = [':id' => $saleId];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            $sets[] = "`$field` = :$field";
            $params[":$field"] = $value;
        }

        if ($sets === []) {
            return;
        }

        $sql = 'UPDATE ventes SET ' . implode(', ', $sets) . ' WHERE id = :id';

        try {
            $this->db()->execute($sql, $params);
        } catch (\Exception $e) {
            error_log('[DGI SYNC] Erreur UPDATE ventes id=' . $saleId . ' : ' . $e->getMessage());
        }
    }

    /**
     * Marque une vente comme « toujours en attente » et journalise l'erreur.
     */
    public function markPending(int $saleId, string $error): void
    {
        $this->updateSale($saleId, [
            'dgi_status'     => self::STATUS_PENDING,
            'dgi_last_error' => mb_substr($error, 0, 255),
            'dgi_attempts'   => $this->getAttempts($saleId) + 1,
        ]);
    }

    /**
     * Marque une vente comme hors périmètre (plus rien n'arrivera).
     */
    public function markNotApplicable(int $saleId, string $reason = ''): void
    {
        $this->updateSale($saleId, [
            'dgi_status'     => self::STATUS_NOT_APPLICABLE,
            'dgi_last_error' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
        ]);
    }

    private function getAttempts(int $saleId): int
    {
        $row = $this->db()->fetch('SELECT dgi_attempts FROM ventes WHERE id = ?', [$saleId]);

        return (int)($row['dgi_attempts'] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relecture auprès d'OSAT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Relit une facture auprès d'OSAT (GET /dgi/facture/).
     *
     * C'est le même endpoint que celui déjà utilisé par les pages facture
     * pour afficher le ticket (cf. /api/service-bill), mais ici on écrit le
     * résultat en base au lieu de se contenter de le renvoyer au navigateur.
     *
     * @return array{success:bool, status:string, data:array|null, message:string}
     */
    public function rereadFromOsat(array $sale): array
    {
        $invoiceNumber = trim((string)($sale['dgi_invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = trim((string)($sale['numero_facture'] ?? ''));
        }

        $storeIsf = trim((string)($sale['store_isf'] ?? ''));
        if ($storeIsf === '') {
            $storeIsf = $this->resolveIsfFromShop($sale);
        }

        if ($invoiceNumber === '' || $storeIsf === '') {
            return [
                'success' => false,
                'status'  => 'missing_key',
                'data'    => null,
                'message' => 'Clé DGI incomplète (invoice_number / store_isf)',
            ];
        }

        $token = $this->resolveToken($storeIsf, $sale);
        if ($token === '') {
            return [
                'success' => false,
                'status'  => 'missing_token',
                'data'    => null,
                'message' => 'Token DGI non configuré pour cette boutique',
            ];
        }

        $url = self::OSAT_FACTURE_URL
            . '?store_isf=' . urlencode($storeIsf)
            . '&invoice_number=' . urlencode($invoiceNumber)
            . '&token=' . urlencode($token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || trim((string)$response) === '') {
            return [
                'success' => false,
                'status'  => 'network_error',
                'data'    => null,
                'message' => 'Erreur connexion API DGI: ' . $curlError,
            ];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'status'  => 'invalid_response',
                'data'    => null,
                'message' => 'Réponse DGI invalide (HTTP ' . $httpCode . ')',
            ];
        }

        if (empty($data['success'])) {
            // OSAT répond { success: false, message: "Facture introuvable" }
            // tant que la demande n'est pas résolue (ou qu'elle n'existe pas).
            return [
                'success' => false,
                'status'  => 'not_found',
                'data'    => $data,
                'message' => (string)($data['message'] ?? 'Facture introuvable'),
            ];
        }

        return [
            'success' => true,
            'status'  => 'ok',
            'data'    => $data,
            'message' => '',
        ];
    }

    /**
     * Resynchronise une vente : relit auprès d'OSAT et écrit le résultat.
     *
     * @param int  $saleId
     * @param bool $force  Force même si la vente est déjà marquée 'synced'
     *
     * @return array{success:bool, status:string, message:string, applied:array}
     */
    public function syncSale(int $saleId, bool $force = false): array
    {
        $sale = $this->db()->fetch('SELECT * FROM ventes WHERE id = ?', [$saleId]);

        if (!$sale) {
            return [
                'success' => false,
                'status'  => 'sale_not_found',
                'message' => 'Vente introuvable',
                'applied' => [],
            ];
        }

        $status = (string)($sale['dgi_status'] ?? '');

        if ($status === self::STATUS_SYNCED && !$force) {
            return [
                'success' => true,
                'status'  => 'already_synced',
                'message' => 'Vente déjà synchronisée',
                'applied' => [],
            ];
        }

        if ($status === self::STATUS_NOT_APPLICABLE && !$force) {
            return [
                'success' => true,
                'status'  => 'not_applicable',
                'message' => 'Vente hors périmètre DGI',
                'applied' => [],
            ];
        }

        // Boutique non homologuée : rien n'arrivera, inutile de marteler l'API.
        $homologation = $this->isShopHomologated($sale);
        if (!$homologation && !$force) {
            $this->markNotApplicable($saleId, 'Boutique non homologuée');

            return [
                'success' => true,
                'status'  => 'not_applicable',
                'message' => 'Boutique non homologuée',
                'applied' => [],
            ];
        }

        $result = $this->rereadFromOsat($sale);

        if (!$result['success']) {
            // Abandon après trop de tentatives : on arrête de marteler l'API.
            if ($this->getAttempts($saleId) + 1 >= self::MAX_ATTEMPTS) {
                $this->markNotApplicable($saleId, 'Abandon après ' . self::MAX_ATTEMPTS . ' tentatives : ' . $result['message']);
            } else {
                $this->markPending($saleId, $result['message']);
            }

            return [
                'success' => false,
                'status'  => $result['status'],
                'message' => $result['message'],
                'applied' => [],
            ];
        }

        $apply = $this->applyDgiData($sale, $result['data']);

        if (!$apply['done']) {
            $this->markPending($saleId, $apply['reason']);

            return [
                'success' => false,
                'status'  => 'incomplete',
                'message' => $apply['reason'],
                'applied' => [],
            ];
        }

        return [
            'success' => true,
            'status'  => 'synced',
            'message' => 'Facture synchronisée avec la DGI',
            'applied' => $apply['applied'],
        ];
    }

    /**
     * Resynchronise toutes les ventes en attente (traitement par lot).
     */
    public function syncPending(int $limit = 50, ?int $shopId = null): array
    {
        $sales = $this->findPendingSales($limit, $shopId);

        $summary = [
            'total'   => count($sales),
            'synced'  => 0,
            'pending' => 0,
            'failed'  => 0,
            'details' => [],
        ];

        foreach ($sales as $sale) {
            $result = $this->syncSale((int)$sale['id']);

            if ($result['success'] && $result['status'] === 'synced') {
                $summary['synced']++;
            } elseif ($result['success']) {
                $summary['pending']++;
            } else {
                $summary['failed']++;
            }

            $summary['details'][] = [
                'vente_id'       => (int)$sale['id'],
                'numero_facture' => $sale['numero_facture'] ?? '',
                'status'         => $result['status'],
                'message'        => $result['message'],
            ];
        }

        return $summary;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Résolution boutique / ISF / token
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Statut d'homologation DGI de la boutique émettrice de la vente.
     */
    public function isShopHomologated(array $sale): bool
    {
        $shopId = $sale['shop_id'] ?? null;
        if (!$shopId) {
            return false;
        }

        $shop = (new Shop())->findById($shopId);
        if (!$shop) {
            return false;
        }

        return (bool)($shop['homologation'] ?? false);
    }

    /**
     * ISF de repli : boutique émettrice, puis société.
     */
    private function resolveIsfFromShop(array $sale): string
    {
        $shopId = $sale['shop_id'] ?? null;
        if ($shopId) {
            $shop = (new Shop())->findById($shopId);
            if ($shop && !empty($shop['isf'])) {
                return trim((string)$shop['isf']);
            }
        }

        $companyInfo = (new CompanyInfo())->get();

        return trim((string)($companyInfo['isf'] ?? ''));
    }

    /**
     * Résout le token DGI à partir de l'ISF interrogé.
     *
     * Le token doit appartenir au même émetteur que l'ISF : on le résout donc
     * à partir de l'ISF, pas de la session (sinon un super_admin, qui n'a pas
     * de boutique, ne peut pas relire les factures des autres boutiques).
     * Même cascade que celle utilisée par /api/service-bill.
     */
    public function resolveToken(string $storeIsf, array $sale = []): string
    {
        $storeIsf = trim($storeIsf);
        if ($storeIsf === '') {
            return '';
        }

        $companyInfo = (new CompanyInfo())->get();

        if (trim((string)($companyInfo['isf'] ?? '')) === $storeIsf) {
            $token = trim((string)($companyInfo['token'] ?? ''));
            if ($token !== '') {
                return $token;
            }
        }

        $shopModel = new Shop();
        $isfShop = $shopModel->findByIsf($storeIsf);
        if ($isfShop && !empty($isfShop['token'])) {
            return trim((string)$isfShop['token']);
        }

        // Repli : le token de la boutique émettrice de la vente.
        $shopId = $sale['shop_id'] ?? null;
        if ($shopId) {
            $shop = $shopModel->findById($shopId);
            if ($shop && !empty($shop['token'])) {
                return trim((string)$shop['token']);
            }
        }

        return trim((string)($companyInfo['token'] ?? ''));
    }
}
