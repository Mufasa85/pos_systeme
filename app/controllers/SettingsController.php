<?php

namespace App\Controllers;

use App\Models\Settings;

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
        if (!$this->requireAuth()) {
            return;
        }

        $shopId = $this->getShopId();

        // Clés non liées au magasin (theme, paper_type, tax_rate, receipt_padding...)
        $settings = $this->settingsModel->getAll($shopId);

        // Les infos Magasin/POS proviennent désormais de la table shops
        $shop = $shopId ? (new \App\Models\Shop())->findById($shopId) : null;

        if ($shop) {
            $settings = array_merge($settings, [
                // Informations magasin
                'store_name'    => $shop['nom'] ?? '',
                'store_address' => $shop['adresse'] ?? '',
                'store_phone'   => $shop['telephone'] ?? '',
                'store_email'   => $shop['email'] ?? '',
                'store_ice'     => $shop['ice'] ?? '',
                'store_rccm'    => $shop['rccm'] ?? '',
                'store_isf'     => $shop['isf'] ?? '',
                'store_homologation' => !empty($shop['homologation']),

                // Informations POS
                'shop_id'      => $shop['id'] ?? null,
                'pdv'          => $shop['pdv'] ?? '',
                'nid'          => $shop['nid'] ?? '',
                'token'        => $shop['token'] ?? '',
                'port'         => $shop['port'] ?? '',
                'service_type' => $shop['service_type_id'] ?? 'Caisse',
            ]);
        }

        // Pour le super admin, fallback sur company_info si les infos magasin sont vides
        if ($this->isSuperAdmin()) {
            $companyInfo = (new \App\Models\CompanyInfo())->get();
            $fallback = [
                'store_name'    => $companyInfo['name'] ?? '',
                'store_address' => $companyInfo['address'] ?? '',
                'store_phone'   => $companyInfo['phone'] ?? '',
                'store_email'   => $companyInfo['email'] ?? '',
                'store_ice'     => $companyInfo['ice'] ?? '',
                'store_rccm'    => $companyInfo['rccm'] ?? '',
                'store_isf'     => $companyInfo['isf'] ?? '',
                'pdv'           => $companyInfo['pdv'] ?? '',
                'nid'           => $companyInfo['nid'] ?? '',
            ];
            foreach ($fallback as $key => $value) {
                if (!empty($value)) {
                    $settings[$key] = $value;
                }
            }
        }

        $this->json($settings);
    }

    // POST /api/settings - Mettre à jour les paramètres
    public function update()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        // Gestion des données POST ou JSON
        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        if (empty($input)) {
            $this->status(400)->json(['error' => 'Aucune donnée fournie']);
            return;
        }

        $shopId = $this->getShopId();

        // Champs du magasin à enregistrer dans la table shops
        $shopFields = [
            'store_name'    => 'nom',
            'store_address' => 'adresse',
            'store_phone'   => 'telephone',
            'store_email'   => 'email',
            'store_ice'     => 'ice',
            'store_rccm'    => 'rccm',
            'store_isf'     => 'isf',
            'pdv'           => 'pdv',
            'nid'           => 'nid',
            'token'         => 'token',
            'port'          => 'port',
        ];

        $shopData = [];
        foreach ($shopFields as $inputKey => $dbCol) {
            if (array_key_exists($inputKey, $input)) {
                $shopData[$dbCol] = is_string($input[$inputKey]) ? $this->sanitaze($input[$inputKey]) : $input[$inputKey];
            }
        }

        // service_type envoyé sous forme d'ID
        if (array_key_exists('service_type', $input) && is_numeric($input['service_type'])) {
            $shopData['service_type_id'] = (int)$input['service_type'];
        }

        try {
            if (!empty($shopData) && $shopId) {
                $shopModel = new \App\Models\Shop();
                $shopModel->update($shopId, $shopData);
            }

            // Les autres clés restent dans settings (theme, paper_type, tax_rate, etc.)
            $settingsKeys = array_diff(array_keys($input), array_keys($shopFields), ['service_type']);
            foreach ($settingsKeys as $key) {
                $this->settingsModel->set($key, $input[$key], $shopId);
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
        if (!$this->requireAdmin()) {
            return;
        }

        $data = [
            'store_name'    => $_POST['store_name'] ?? '',
            'store_address' => $_POST['store_address'] ?? '',
            'store_phone'   => $_POST['store_phone'] ?? '',
            'store_ice'     => $_POST['store_ice'] ?? '',
            'store_rccm'    => $_POST['store_rccm'] ?? '',
            'store_isf'     => $_POST['store_isf'] ?? '',
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
        if (!$this->requireAdmin()) {
            return;
        }

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
        if (!$this->requireAdmin()) {
            return;
        }

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
        if (!$this->requireAdmin()) {
            return;
        }

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
                'error' => 'Format de papier invalide. Formats acceptés : ' . implode(', ', $allowed),
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

    // POST /api/settings/receipt-padding - Mettre à jour le padding des articles (57mm / 80mm)
    // Payload attendu : { paper_type: '57mm'|'80mm', padding_h: number, padding_v: number } (en mm)
    public function updateReceiptPadding()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $allowedFormats = ['57mm', '80mm'];

        $raw = file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $input = (!empty($_POST)) ? $_POST : (is_array($json) ? $json : []);

        $paperType = $input['paper_type'] ?? null;
        $paddingH = $input['padding_h'] ?? null;
        $paddingV = $input['padding_v'] ?? null;

        if (!in_array($paperType, $allowedFormats, true)) {
            $this->status(400)->json([
                'error' => 'Format de papier invalide. Formats acceptés : ' . implode(', ', $allowedFormats),
            ]);
            return;
        }

        if (!is_numeric($paddingH) || !is_numeric($paddingV)) {
            $this->status(400)->json(['error' => 'Les valeurs de padding doivent être numériques']);
            return;
        }

        $paddingH = max(0, min(10, (float)$paddingH));
        $paddingV = max(0, min(10, (float)$paddingV));

        $shopId = $this->getShopId();
        try {
            $key = 'receipt_padding_' . $paperType;
            $this->settingsModel->set($key, json_encode(['h' => $paddingH, 'v' => $paddingV]), $shopId);
            $this->json([
                'success' => true,
                'message' => 'Padding du ticket mis à jour',
                'paper_type' => $paperType,
                'padding_h' => $paddingH,
                'padding_v' => $paddingV,
            ]);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/receipt-padding - Récupérer le padding configuré pour 57mm et 80mm
    public function getReceiptPadding()
    {
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $defaults = [
            '57mm' => ['h' => 0, 'v' => 1],
            '80mm' => ['h' => 0, 'v' => 1],
        ];

        $result = [];
        foreach (['57mm', '80mm'] as $format) {
            $raw = $this->settingsModel->get('receipt_padding_' . $format, $shopId);
            $decoded = $raw ? json_decode($raw, true) : null;
            $result[$format] = (is_array($decoded) && isset($decoded['h'], $decoded['v']))
                ? ['h' => (float)$decoded['h'], 'v' => (float)$decoded['v']]
                : $defaults[$format];
        }

        $this->json($result);
    }
}
