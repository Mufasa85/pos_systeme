<?php

namespace App\Controllers;

use App\Models\Settings;
use App\controllers\Controller;

class SettingsController extends Controller
{
    private $settingsModel;

    public function __construct()
    {
        $this->settingsModel = new Settings();
    }

    // GET /api/settings - Récupérer tous les paramètres
    public function index()
    {
        // Super admin: paramètres globaux via table settings
        if ($this->isSuperAdmin()) {
            $this->json($this->settingsModel->getAll(null));
            return;
        }

        // Admin: les infos Magasin/POS doivent venir de la table shops
        $shopId = $this->getShopId();
        $shop = (new \App\Models\Shop())->findById($shopId);

        if (!$shop) {
            $this->status(404)->json(['error' => 'Boutique non trouvée']);
            return;
        }

        // On renvoie uniquement les clés attendues par la vue JS
        $this->json([
            // Informations magasin
            'store_name' => $shop['nom'] ?? '',
            'store_address' => $shop['adresse'] ?? '',
            'store_phone' => $shop['telephone'] ?? '',
            'store_email' => $shop['email'] ?? '',
            'store_ice' => $shop['ice'] ?? '',
            'store_rccm' => $shop['rccm'] ?? '',
            'store_isf' => $shop['isf'] ?? '',

            // Informations POS
            // (ces champs existaient dans settings; ici on garde la compatibilité)
            'nid' => $this->settingsModel->get('nid', $shopId) ?? '',
            'token' => $this->settingsModel->get('token', $shopId) ?? '',
            'port' => $this->settingsModel->get('port', $shopId) ?? '',
            'service_type' => $shop['service_type_id'] ?? 'Caisse'
        ]);
    }

    // POST /api/settings - Mettre à jour les paramètres
    public function update()
    {
        if (!$this->requireAdmin()) return;

        // Gestion des données POST ou JSON
        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        if (empty($input)) {
            $this->status(400)->json(['error' => 'Aucune donnée fournie']);
            return;
        }

        $shopId = $this->getShopId();
        try {
            foreach ($input as $key => $value) {
                $this->settingsModel->set($key, $value, $shopId);
            }
            $this->logAudit('update', 'settings', null, ['keys' => array_keys($input)]);
            $this->json(['success' => true, 'message' => 'Paramètres mis à jour avec succès']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/:key - Récupérer un paramètre spécifique
    public function get($key)
    {
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $value = $this->settingsModel->get($key, $shopId);
        if ($value === null) {
            $this->status(404)->json(['error' => 'Paramètre non trouvé']);
            return;
        }
        $this->json(['key' => $key, 'value' => $value]);
    }

    // POST /api/settings/store - Mettre à jour les infos du magasin
    public function updateStore()
    {
        if (!$this->requireAdmin()) return;

        $data = [
            'store_name'    => $_POST['store_name'] ?? '',
            'store_address' => $_POST['store_address'] ?? '',
            'store_phone'   => $_POST['store_phone'] ?? '',
            'store_ice'     => $_POST['store_ice'] ?? '',
            'store_rccm'    => $_POST['store_rccm'] ?? '',
            'store_isf'     => $_POST['store_isf'] ?? ''
        ];

        $shopId = $this->getShopId();
        try {
            foreach ($data as $key => $value) {
                $this->settingsModel->set($key, $value, $shopId);
            }
            $this->logAudit('update', 'store_info', null);
            $this->json(['success' => true, 'message' => 'Informations du magasin mises à jour']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // POST /api/settings/tax - Mettre à jour les paramètres TVA
    public function updateTax()
    {
        if (!$this->requireAdmin()) return;

        $taxRate = $_POST['tax_rate'] ?? null;

        if ($taxRate === null || !is_numeric($taxRate)) {
            $this->status(400)->json(['error' => 'Taux de TVA invalide']);
            return;
        }

        $shopId = $this->getShopId();
        try {
            $this->settingsModel->set('tax_rate', (float)$taxRate, $shopId);
            $this->json(['success' => true, 'message' => 'Taux de TVA mis à jour']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // POST /api/settings/theme - Sauvegarder le thème (Admin/Super_admin)
    public function saveTheme()
    {
        if (!$this->requireAdmin()) return;

        $theme = $_POST['theme'] ?? null;

        if ($theme === null) {
            $this->status(400)->json(['error' => 'Thème non fourni']);
            return;
        }

        // Super_admin sauvegarde en global (shopId=null), admin pour sa boutique
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        try {
            $this->settingsModel->set('theme', $theme, $shopId);
            $this->json(['success' => true, 'message' => 'Thème sauvegardé']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/theme - Récupérer le thème actuel
    public function getTheme()
    {
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $theme = $this->settingsModel->get('theme', $shopId) ?? 'blue';
        $this->json(['theme' => $theme]);
    }

    // POST /api/settings/paper-type - Mettre à jour le format d'impression (Admin only)
    // Formats acceptés : 80mm, 57mm, A4, A5, Letter, Legal
    public function updatePaperType()
    {
        if (!$this->requireAdmin()) return;

        $allowed = ['80mm', '57mm', 'A4', 'A5', 'Letter', 'Legal'];

        // Supporte à la fois application/x-www-form-urlencoded ($_POST)
        // et application/json (php://input)
        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        $paperType = $input['paper_type'] ?? null;

        if ($paperType === null) {
            $this->status(400)->json(['error' => 'Format de papier non fourni']);
            return;
        }

        if (!in_array($paperType, $allowed, true)) {
            $this->status(400)->json([
                'error' => 'Format de papier invalide. Formats acceptés : ' . implode(', ', $allowed)
            ]);
            return;
        }

        $shopId = $this->getShopId();
        try {
            $this->settingsModel->set('paper_type', $paperType, $shopId);
            $this->json(['success' => true, 'message' => 'Format d\'impression mis à jour', 'paper_type' => $paperType]);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/paper-type - Récupérer le format d'impression actuel
    public function getPaperType()
    {
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $paperType = $this->settingsModel->get('paper_type', $shopId) ?? '80mm';
        $this->json(['paper_type' => $paperType]);
    }
}
