<?php

namespace App\Controllers;

use App\Services\BarcodeRepairService;

/**
 * ProductRepairController — réparation des codes-barres corrompus.
 *
 * ROUTES
 *   GET  /api/produit/repair-barcodes  → rapport (dry-run, aucune écriture)
 *   POST /api/produit/repair-barcodes  → applique la réparation
 *
 * ── Pourquoi cette route ? ──────────────────────────────────────────────
 * Le script CLI `scripts/repair_barcodes_importes.php` ne peut pas être
 * exécuté sur un hébergement mutualisé (pas d'accès SSH). Cette route
 * expose exactement la même logique côté web.
 *
 * ── Sécurité ───────────────────────────────────────────────────────────
 * La route écrit en base : elle est donc réservée au SUPER_ADMIN
 * (requireSuperAdmin()), qui est le seul rôle à voir l'ensemble des
 * boutiques. Un admin simple ne peut pas réparer le catalogue d'une autre
 * boutique.
 *
 * Par défaut, POST ne fait QUE du dry-run : il faut envoyer
 * `confirm=1` pour déclencher l'écriture. Cela évite qu'un double-clic
 * ou un appel accidentel modifie le catalogue.
 *
 * ── Paramètres ─────────────────────────────────────────────────────────
 *   GET / POST
 *   shop_id      : int   boutique cible (super_admin) — absent = toutes
 *   confirm      : 1     POST uniquement : applique réellement
 *   resolve      : 1     autorise la résolution des collisions
 *   delete_ids   : CSV   ids de produits à supprimer pour libérer un
 *                          code-barres (nécessite resolve=1)
 */
class ProductRepairController extends Controller
{
    /** @var BarcodeRepairService */
    private $service;

    public function __construct()
    {
        $this->service = new BarcodeRepairService();
    }

    /**
     * GET /api/produit/repair-barcodes — rapport (dry-run).
     */
    public function index()
    {
        if (!$this->requireSuperAdmin()) {
            return;
        }

        $this->json($this->run(false));
    }

    /**
     * POST /api/produit/repair-barcodes — applique la réparation.
     */
    public function repair()
    {
        if (!$this->requireSuperAdmin()) {
            return;
        }

        // ⚠️ `php://input` ne peut être lu qu'UNE SEULE fois en PHP.
        // On le lit donc ici et on le passe à run() : sinon le second
        // `readInput()` recevrait un corps vide et `confirm=1` (envoyé
        // dans le corps de la requête) serait perdu.
        $input = $this->readInput();

        // `confirm=1` est exigé : POST sans confirmation = simple rapport.
        $confirm = !empty($input['confirm']) && $input['confirm'] !== '0';

        $result = $this->run($confirm, $input);

        if ($confirm && !empty($result['applied'])) {
            $this->logAudit('repair', 'produit', null, [
                'action' => 'repair_barcodes',
                'repares' => $result['summary']['repaired'],
                'doublons_supprimes' => $result['summary']['duplicates_deleted'],
                'shop_id' => $result['shop_id'],
            ]);
        }

        $this->json($result);
    }

    /**
     * Construit le rapport et, si demandé, applique la réparation.
     *
     * @param bool                $apply  true = écriture en base
     * @param array<string,mixed> $input  Paramètres déjà lus
     */
    private function run(bool $apply, array $input = []): array
    {
        if ($input === []) {
            $input = $this->readInput();
        }

        // Boutique cible : le super_admin peut viser une boutique précise.
        $shopId = null;
        $requestedShop = (int)($input['shop_id'] ?? 0);
        if ($requestedShop > 0) {
            $shopId = $requestedShop;
        }

        $resolve = !empty($input['resolve']) && $input['resolve'] !== '0';

        $deleteIds = [];
        if (!empty($input['delete_ids'])) {
            $raw = is_array($input['delete_ids'])
                ? $input['delete_ids']
                : explode(',', (string)$input['delete_ids']);
            foreach ($raw as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $deleteIds[] = $id;
                }
            }
        }

        $plan = $this->service->buildPlan($shopId, $resolve, $deleteIds);

        $summary = [
            'detected' => $plan['detected'],
            'repaired' => 0,
            'duplicates_deleted' => 0,
            'skipped' => count($plan['skipped']),
        ];

        $applied = false;

        if ($apply && ($plan['plan'] !== [] || $plan['duplicates'] !== [])) {
            try {
                $result = $this->service->applyPlan($plan['plan'], $plan['duplicates']);
                $summary['repaired'] = $result['repaired'];
                $summary['duplicates_deleted'] = $result['deleted'];
                $applied = true;
            } catch (\Throwable $e) {
                error_log('[REPAIR BARCODES] Erreur : ' . $e->getMessage());

                return [
                    'success' => false,
                    'error' => 'Réparation impossible : ' . $e->getMessage(),
                    'shop_id' => $shopId,
                    'summary' => $summary,
                    'applied' => false,
                ];
            }
        }

        return [
            'success' => true,
            'applied' => $applied,
            'dry_run' => !$applied,
            'shop_id' => $shopId,
            'summary' => $summary,
            'repairs' => $plan['plan'],
            'duplicates' => $plan['duplicates'],
            'skipped' => $plan['skipped'],
            'message' => $this->buildMessage($apply, $applied, $plan),
        ];
    }

    /**
     * Message explicite : distingue « rien à réparer » de « rapport non
     * confirmé » (les deux cas renvoyaient le même texte, source de
     * confusion).
     *
     * @param bool $apply   true = l'écriture a été demandée
     * @param bool $applied true = l'écriture a eu lieu
     * @param array $plan    Plan issu de buildPlan()
     */
    private function buildMessage(bool $apply, bool $applied, array $plan): string
    {
        if ($applied) {
            $count = $plan['plan'] === [] ? 0 : count($plan['plan']);

            return $count === 1
                ? 'Réparation appliquée : 1 code-barres corrigé.'
                : "Réparation appliquée : {$count} code-barres corrigés.";
        }

        // Rien à réparer : le catalogue est déjà sain.
        if ($plan['detected'] === 0) {
            return 'Aucun code-barres corrompu détecté : rien à réparer.';
        }

        // Des codes-barres corrompus existent, mais l'écriture n'a pas été
        // demandée (dry-run).
        if (!$apply) {
            return sprintf(
                'Rapport uniquement : %d code-barres corrompu(s) détecté(s). '
                . 'Ajoutez confirm=1 pour appliquer.',
                $plan['detected']
            );
        }

        // L'écriture a été demandée mais toutes les lignes ont été ignorées
        // (collisions).
        if ($plan['plan'] === [] && $plan['duplicates'] === []) {
            return sprintf(
                'Aucune correction appliquée : les %d code-barres corrompu(s) '
                . 'sont en collision. Résolvez les collisions puis relancez.',
                $plan['detected']
            );
        }

        return 'Rapport uniquement. Ajoutez confirm=1 pour appliquer.';
    }

    /**
     * Lit les paramètres de la requête (JSON ou formulaire).
     *
     * @return array<string, mixed>
     */
    private function readInput(): array
    {
        $input = $_POST;

        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = array_merge($input, $decoded);
            }
        }

        // Les query string complètent le tout (GET + POST).
        return array_merge($_GET, $input);
    }
}
