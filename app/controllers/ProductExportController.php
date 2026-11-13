<?php

namespace App\Controllers;

use App\Services\ProductExportService;

/**
 * Export du catalogue produits vers un fichier Excel (.xlsx).
 *
 * Route associée (cf. routes/api.php) :
 *   GET /api/produit/export
 *
 * Le fichier produit reprend exactement les en-têtes du modèle d'import :
 * un catalogue exporté peut donc être ré-importé tel quel.
 */
class ProductExportController extends Controller
{
    /**
     * Exporte les produits correspondant aux filtres demandés.
     *
     * Paramètres GET (tous optionnels) :
     *   search          : recherche sur le nom ou le code-barres
     *   category        : nom de la catégorie ('all' = toutes)
     *   shop_id         : ID de la boutique (super_admin uniquement)
     *   include_inactive: inclure les produits des boutiques désactivées
     *   details         : '1' pour ajouter la feuille « Détail »
     */
    public function export()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        // ── Périmètre du catalogue ──
        //
        // L'admin exporte UNIQUEMENT sa boutique. Le super_admin, lui,
        // peut exporter le catalogue de TOUTES les boutiques (par défaut)
        // ou d'une seule boutique via le filtre de la page.
        //
        // ⚠️ `getShopId()` renvoie null pour le super_admin (il n'est
        // rattaché à aucune boutique) : l'utiliser ici l'aurait forcé à
        // ne voir que les produits orphelins.
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $filters = [
            'search' => trim((string)($_GET['search'] ?? '')),
            'category' => trim((string)($_GET['category'] ?? 'all')),
            'include_inactive' => !empty($_GET['include_inactive']),
        ];

        // Filtre boutique explicite (super_admin) : une boutique en particulier.
        if ($this->isSuperAdmin()) {
            $requestedShop = (int)($_GET['shop_id'] ?? 0);
            if ($requestedShop > 0) {
                $filters['shop_id'] = $requestedShop;
            }
        }

        $options = [
            'include_details' => !empty($_GET['details']),
        ];

        try {
            (new ProductExportService())->export($shopId, $filters, $options);
        } catch (\Throwable $e) {
            error_log('ProductExportController::export error: ' . $e->getMessage());
            $this->status(500)->json(['error' => 'Export impossible : ' . $e->getMessage()]);
        }
    }
}
