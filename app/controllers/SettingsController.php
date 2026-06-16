<?php

namespace App\Controllers;

use App\Models\Settings;

class SettingsController
{
    private $settingsModel;

    public function __construct()
    {
        $this->settingsModel = new Settings();
    }

    private function json($data)
    {
        header("Content-Type:application/json");
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    private function status($code)
    {
        http_response_code($code);
        return $this;
    }

    // GET /api/settings - Récupérer tous les paramètres
    public function index()
    {
        $this->json($this->settingsModel->getAll());
    }

    // POST /api/settings - Mettre à jour les paramètres
    public function update()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès réservé aux administrateurs']);
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

        try {
            $this->settingsModel->setMultiple($input);
            $this->json(['success' => true, 'message' => 'Paramètres mis à jour avec succès']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/:key - Récupérer un paramètre spécifique
    public function get($key)
    {
        $value = $this->settingsModel->get($key);
        if ($value === null) {
            $this->status(404)->json(['error' => 'Paramètre non trouvé']);
            return;
        }
        $this->json(['key' => $key, 'value' => $value]);
    }

    // POST /api/settings/store - Mettre à jour les infos du magasin
    public function updateStore()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        $data = [
            'store_name'    => $_POST['store_name'] ?? '',
            'store_address' => $_POST['store_address'] ?? '',
            'store_phone'   => $_POST['store_phone'] ?? '',
            'store_ice'     => $_POST['store_ice'] ?? '',
            'store_rccm'    => $_POST['store_rccm'] ?? '',
            'store_isf'     => $_POST['store_isf'] ?? ''
        ];

        try {
            $this->settingsModel->setMultiple($data);
            $this->json(['success' => true, 'message' => 'Informations du magasin mises à jour']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // POST /api/settings/tax - Mettre à jour les paramètres TVA
    public function updateTax()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        $taxRate = $_POST['tax_rate'] ?? null;

        if ($taxRate === null || !is_numeric($taxRate)) {
            $this->status(400)->json(['error' => 'Taux de TVA invalide']);
            return;
        }

        try {
            $this->settingsModel->set('tax_rate', (float)$taxRate);
            $this->json(['success' => true, 'message' => 'Taux de TVA mis à jour']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // POST /api/settings/theme - Sauvegarder le thème (Admin only)
    public function saveTheme()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès réservé aux administrateurs']);
            return;
        }

        $theme = $_POST['theme'] ?? null;

        if ($theme === null) {
            $this->status(400)->json(['error' => 'Thème non fourni']);
            return;
        }

        try {
            $this->settingsModel->set('theme', $theme);
            $this->json(['success' => true, 'message' => 'Thème sauvegardé']);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/theme - Récupérer le thème actuel
    public function getTheme()
    {
        $theme = $this->settingsModel->get('theme') ?? 'blue';
        $this->json(['theme' => $theme]);
    }

    // POST /api/settings/paper-type - Mettre à jour le format d'impression (Admin only)
    // Formats acceptés : 80mm, 57mm, A4, A5, Letter, Legal
    public function updatePaperType()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès réservé aux administrateurs']);
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
                'error' => 'Format de papier invalide. Formats acceptés : ' . implode(', ', $allowed)
            ]);
            return;
        }

        try {
            $this->settingsModel->set('paper_type', $paperType);
            $this->json(['success' => true, 'message' => 'Format d\'impression mis à jour', 'paper_type' => $paperType]);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    // GET /api/settings/paper-type - Récupérer le format d'impression actuel
    public function getPaperType()
    {
        $paperType = $this->settingsModel->get('paper_type') ?? '80mm';
        $this->json(['paper_type' => $paperType]);
    }
}
