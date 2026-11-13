<?php

namespace App\Controllers;

use App\Services\ProductImportService;

/**
 * Import de produits depuis un fichier Excel / CSV.
 *
 * Routes associées (cf. routes/api.php) :
 *   POST /api/produit/import          → import d'un fichier
 *   GET  /api/produit/import/template → téléchargement du modèle .xlsx
 */
class ProductImportController extends Controller
{
    /** Extensions acceptées pour l'import. */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    /** Taille maximale du fichier uploadé (5 Mo). */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Importe un fichier de produits.
     */
    public function import()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
            $this->status(400)->json(['error' => 'Aucun fichier reçu']);
            return;
        }

        $upload = $_FILES['file'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json(['error' => $this->uploadErrorMessage((int)$upload['error'])]);
            return;
        }

        if (!is_uploaded_file($upload['tmp_name'])) {
            $this->status(400)->json(['error' => 'Fichier temporaire invalide']);
            return;
        }

        if ((int)$upload['size'] > self::MAX_FILE_SIZE) {
            $this->status(400)->json(['error' => 'Fichier trop volumineux (5 Mo maximum)']);
            return;
        }

        $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->status(400)->json([
                'error' => 'Format non supporté. Formats acceptés : .xlsx, .xls, .csv',
            ]);
            return;
        }

        // ── Boutique de destination ──
        //
        // L'admin est verrouillé sur SA boutique : le paramètre `shop_id`
        // est ignoré. Le super_admin doit choisir explicitement la boutique
        // qui va accueillir les produits (sinon les produits seraient
        // créés SANS boutique, donc invisibles dans le POS).
        $shopId = null;

        if ($this->isSuperAdmin()) {
            $requestedShop = (int)($_POST['shop_id'] ?? 0);
            if ($requestedShop <= 0) {
                $this->status(400)->json([
                    'error' => 'Veuillez choisir la boutique de destination des produits.',
                ]);
                return;
            }

            $shop = (new \App\Models\Shop())->findById($requestedShop);
            if (!$shop) {
                $this->status(400)->json(['error' => 'Boutique introuvable']);
                return;
            }
            if (empty($shop['actif'])) {
                $this->status(400)->json([
                    'error' => 'La boutique « ' . ($shop['nom'] ?? '') . ' » est désactivée.',
                ]);
                return;
            }

            $shopId = $requestedShop;
        } else {
            $shopId = $this->getShopId();
            if (!$shopId) {
                $this->status(400)->json(['error' => 'Aucune boutique rattachée à votre compte']);
                return;
            }
        }

        $options = [
            'mode' => ($_POST['mode'] ?? 'create') === 'update' ? 'update' : 'create',
            'create_categories' => !empty($_POST['create_categories']),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'tax_id' => (int)($_POST['tax_id'] ?? 0),
        ];

        try {
            $service = new ProductImportService();
            $result = $service->importFile($shopId, $upload['tmp_name'], $options);
        } catch (\Throwable $e) {
            error_log('ProductImportController::import error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Erreur pendant l\'import : ' . $e->getMessage()]);
            return;
        }

        if (empty($result['success'])) {
            $this->status(400)->json([
                'error' => $result['error'] ?? 'Import impossible',
                'summary' => $result['summary'],
                'rows' => $result['rows'],
            ]);
            return;
        }

        $this->logAudit('import', 'produit', null, [
            'fichier' => $upload['name'],
            'mode' => $options['mode'],
            'crees' => $result['summary']['created'],
            'mis_a_jour' => $result['summary']['updated'],
            'ignores' => $result['summary']['skipped'],
            'erreurs' => $result['summary']['errors'],
        ]);

        $this->json([
            'success' => true,
            'summary' => $result['summary'],
            'rows' => $result['rows'],
        ]);
    }

    /**
     * Télécharge le fichier modèle (.xlsx) à remplir.
     */
    public function template()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            (new ProductImportService())->sendTemplate();
        } catch (\Throwable $e) {
            error_log('ProductImportController::template error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Génération du modèle impossible']);
        }
    }

    /**
     * Renvoie les catégories disponibles pour une boutique donnée.
     *
     * Utilisé par le super_admin dans le modal d'import : les catégories
     * proposées doivent être celles de la boutique de destination
     * (sinon l'import crée des produits dans la mauvaise catégorie).
     */
    public function categories()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->getShopId();

        if ($this->isSuperAdmin()) {
            $requestedShop = (int)($_GET['shop_id'] ?? 0);
            if ($requestedShop > 0) {
                $shopId = $requestedShop;
            }
        }

        $categories = (new \App\Models\Category())->all($shopId);

        $this->json(array_map(function ($cat) {
            return [
                'id' => (int)$cat['id'],
                'category' => $cat['category'],
                'shop_id' => $cat['shop_id'] !== null ? (int)$cat['shop_id'] : null,
            ];
        }, $categories));
    }

    /**
     * Traduit un code d'erreur d'upload PHP en message lisible.
     */
    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Fichier trop volumineux (5 Mo maximum)';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incomplet, veuillez réessayer';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier sélectionné';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Dossier temporaire manquant sur le serveur';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Écriture du fichier temporaire impossible';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloqué par une extension PHP';
            default:
                return 'Erreur pendant l\'upload du fichier';
        }
    }
}
