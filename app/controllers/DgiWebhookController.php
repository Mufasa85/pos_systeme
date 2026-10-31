<?php

namespace App\Controllers;

use App\Services\DgiSyncService;

/**
 * DgiWebhookController
 *
 * Point d'entrée des mises à jour DGI différées.
 *
 * ROUTE PRINCIPALE — POST /api/dgi/update
 * -----------------------------------------
 * Appelée par OSAT quand la DGI redevient disponible et que la demande
 * qu'OSAT avait mise en attente est enfin résolue. OSAT envoie la même
 * structure de données que celle d'une réponse normale (éléments de sécurité
 * : codeDEFDGI, counters, nim, qrCode, dateDGI) plus les deux clés qui
 * permettent de retrouver la vente :
 *
 *   {
 *     "invoice_number": "2026/000123",
 *     "store_isf"     : "A900011T",
 *     "data": { "codeDEFDGI": "...", "counters": "...", "nim": "...",
 *               "qrCode": "...", "dateDGI": "...", "total": 0, "vtotal": 0 },
 *     "comment": null
 *   }
 *
 * AUTHENTIFICATION
 * ----------------
 * OSAT est un serveur, pas un navigateur : il n'y a pas de session. La route
 * est donc protégée par un secret partagé, transmis dans l'en-tête :
 *
 *   X-DGI-Token    : <secret simple, configuré chez OSAT et dans config.php>
 *   X-DGI-Signature: <HMAC-SHA256 du corps, même secret>  (variante forte)
 *
 * Le secret est défini dans config/config.php via la constante DGI_WEBHOOK_TOKEN.
 * S'il n'est pas configuré, la route refuse TOUT appel (fail-closed) : on
 * ne veut pas exposer une écriture en base sans authentification.
 *
 * RÉPONSES
 *   200 : mise à jour effectuée
 *   202 : demande reçue mais la vente n'existe pas encore (course possible :
 *         OSAT résout avant que notre INSERT ne soit fait) → le cron rattrape
 *   401 : secret absent ou invalide
 *   404 : vente introuvable pour cette clé
 */
class DgiWebhookController extends Controller
{
    /** @var DgiSyncService */
    private $service;

    public function __construct()
    {
        $this->service = new DgiSyncService();
    }

    /**
     * POST /api/dgi/update — OSAT pousse la résolution DGI.
     */
    public function update($params = [])
    {
        header('Content-Type: application/json');

        $rawBody = file_get_contents('php://input');

        if (!$this->isAuthorized($rawBody)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'Secret DGI invalide ou absent',
            ]);
            return;
        }

        $input = json_decode($rawBody, true);

        if (!is_array($input) || $input === []) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'invalid_payload',
                'message' => 'Corps de requête JSON invalide',
            ]);
            return;
        }

        // La clé peut arriver sous plusieurs noms : on accepte les variantes
        // pour ne pas casser le contrat si OSAT ajuste son nommage.
        $invoiceNumber = trim((string)(
            $input['invoice_number']
            ?? $input['dgi_invoice_number']
            ?? $input['numero_facture']
            ?? ''
        ));
        $storeIsf = trim((string)(
            $input['store_isf']
            ?? $input['isf']
            ?? ''
        ));

        if ($invoiceNumber === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'missing_invoice_number',
                'message' => 'Paramètre invoice_number requis',
            ]);
            return;
        }

        // Les données DGI peuvent être imbriquées (data) ou à la racine.
        $dgiData = $input['data'] ?? $input;

        $sale = $this->service->findSaleByDgiKey($invoiceNumber, $storeIsf !== '' ? $storeIsf : null);

        if (!$sale) {
            // Cas de la course : OSAT a résolu avant la création de la vente.
            // On ne peut rien écrire : le cron de rattrapage s'en chargera.
            http_response_code(202);
            echo json_encode([
                'success' => true,
                'updated' => false,
                'queued'  => true,
                'message' => 'Vente introuvable pour cette clé : relecture automatique programmée',
            ]);
            return;
        }

        $apply = $this->service->applyDgiData($sale, $dgiData);

        if (!$apply['done']) {
            // La réponse ne contient pas les éléments de sécurité : on laisse
            // la vente en 'pending' et on retentera via la relecture OSAT.
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'updated' => false,
                'status'  => 'incomplete',
                'message' => $apply['reason'],
            ]);
            return;
        }

        error_log('[DGI WEBHOOK] Facture ' . $invoiceNumber . ' synchronisée (vente #' . $sale['id'] . ')');

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'updated' => true,
            'vente_id' => (int)$sale['id'],
            'numero_facture' => $sale['numero_facture'] ?? '',
            'applied' => $apply['applied'],
            'message' => 'Facture mise à jour avec les éléments de sécurité DGI',
        ]);
    }

    /**
     * Vérifie le secret partagé transmis par OSAT.
     *
     * Deux modes acceptés :
     *   - X-DGI-Signature : HMAC-SHA256 du corps brut, en hexadécimal
     *   - X-DGI-Token     : le secret en clair
     */
    private function isAuthorized(string $rawBody): bool
    {
        $expected = $this->getWebhookToken();

        // Fail-closed : sans secret configuré, la route est fermée.
        if ($expected === '') {
            error_log('[DGI WEBHOOK] Refus : DGI_WEBHOOK_TOKEN non configuré dans config/config.php');
            return false;
        }

        $signature = trim((string)($_SERVER['HTTP_X_DGI_SIGNATURE'] ?? ''));
        if ($signature !== '') {
            $computed = hash_hmac('sha256', $rawBody, $expected);

            return hash_equals($computed, strtolower($signature));
        }

        $token = trim((string)($_SERVER['HTTP_X_DGI_TOKEN'] ?? ''));

        return $token !== '' && hash_equals($expected, $token);
    }

    /**
     * Secret partagé, lu depuis la configuration.
     */
    private function getWebhookToken(): string
    {
        if (defined('DGI_WEBHOOK_TOKEN')) {
            return trim((string)constant('DGI_WEBHOOK_TOKEN'));
        }

        $env = getenv('DGI_WEBHOOK_TOKEN');

        return $env !== false ? trim((string)$env) : '';
    }
}
