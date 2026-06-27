<?php

namespace App\Controllers;

use App\Models\Tax;
use App\controllers\Controller;

class TaxController extends Controller
{
    private $taxModel;

    public function __construct()
    {
        $this->taxModel = new Tax();
    }

    // GET /api/taxes - Récupérer toutes les taxes
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            self::status(401)->json(['error' => 'Non authentifié']);
            return;
        }
        $this->json($this->taxModel->getAll());
    }

    // POST /api/taxes - Créer une taxe
    public function create()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            self::status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        // Supporte application/x-www-form-urlencoded ($_POST) ou JSON (php://input)
        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        $groupe = isset($input['groupe_taxe']) ? $this->sanitaze($input['groupe_taxe']) : '';
        $etiquette = isset($input['etiquette']) ? $this->sanitaze($input['etiquette']) : '';
        $taux = isset($input['taux']) ? $input['taux'] : null;
        $description = isset($input['description']) ? $this->sanitaze($input['description']) : '';

        if (empty($groupe) || empty($etiquette) || $taux === null || !is_numeric($taux)) {
            self::status(400)->json(['error' => 'Veuillez remplir tous les champs obligatoires avec des valeurs valides']);
            return;
        }

        $taux = (float)$taux;
        if ($taux < 0 || $taux > 100) {
            self::status(400)->json(['error' => 'Le taux de taxe doit être compris entre 0 et 100']);
            return;
        }

        try {
            $this->taxModel->create([
                'groupe_taxe' => $groupe,
                'etiquette' => $etiquette,
                'description' => $description,
                'taux' => $taux
            ]);
            $this->json(['success' => true, 'message' => 'Taxe créée avec succès']);
        } catch (\Exception $e) {
            self::status(500)->json(['error' => 'Erreur lors de la création : ' . $e->getMessage()]);
        }
    }

    // POST /api/taxes/update - Modifier une taxe
    public function update()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            self::status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            self::status(400)->json(['error' => 'ID de taxe invalide']);
            return;
        }

        // Protection des taxes système (id <= 16)
        if ($id <= 16) {
            self::status(403)->json(['error' => 'Impossible de modifier les taxes système par défaut']);
            return;
        }

        $groupe = isset($input['groupe_taxe']) ? $this->sanitaze($input['groupe_taxe']) : '';
        $etiquette = isset($input['etiquette']) ? $this->sanitaze($input['etiquette']) : '';
        $taux = isset($input['taux']) ? $input['taux'] : null;
        $description = isset($input['description']) ? $this->sanitaze($input['description']) : '';

        if (empty($groupe) || empty($etiquette) || $taux === null || !is_numeric($taux)) {
            self::status(400)->json(['error' => 'Veuillez remplir tous les champs obligatoires avec des valeurs valides']);
            return;
        }

        $taux = (float)$taux;
        if ($taux < 0 || $taux > 100) {
            self::status(400)->json(['error' => 'Le taux doit être compris entre 0 et 100']);
            return;
        }

        try {
            $this->taxModel->update($id, [
                'groupe_taxe' => $groupe,
                'etiquette' => $etiquette,
                'description' => $description,
                'taux' => $taux
            ]);
            $this->json(['success' => true, 'message' => 'Taxe modifiée avec succès']);
        } catch (\Exception $e) {
            self::status(500)->json(['error' => 'Erreur lors de la modification : ' . $e->getMessage()]);
        }
    }

    // POST /api/taxes/delete - Supprimer une taxe
    public function delete()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            self::status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            self::status(400)->json(['error' => 'ID de taxe invalide']);
            return;
        }

        // Protection des taxes système
        if ($id <= 16) {
            self::status(403)->json(['error' => 'Impossible de supprimer les taxes système par défaut']);
            return;
        }

        try {
            // Vérifier s'il y a des produits associés
            $productCount = $this->taxModel->countProducts($id);
            if ($productCount > 0) {
                self::status(400)->json([
                    'error' => "Cette taxe ne peut pas être supprimée car elle est associée à {$productCount} produit(s)."
                ]);
                return;
            }

            $this->taxModel->delete($id);
            $this->json(['success' => true, 'message' => 'Taxe supprimée avec succès']);
        } catch (\Exception $e) {
            self::status(500)->json(['error' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
        }
    }
}
