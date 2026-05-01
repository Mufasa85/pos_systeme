<?php

namespace App\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Settings;

class InvoiceController extends Controller
{
    private function render($view, $data = [])
    {
        // Vérifier la session AVANT d'afficher la page
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        $page = $view;
        // Charger le nom du magasin pour toutes les pages
        $settingsModel = new Settings();
        $data['storeName'] = $settingsModel->get('store_name') ?? 'Mon Magasin';
        extract($data);
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/header.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/' . $view . '.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/footer.php';
    }

    /**
     * Afficher la page de la facture par ID de vente (authentifié)
     */
    public function show($params)
    {
        $saleId = $params['id'] ?? null;

        if (!$saleId) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/historique');
            exit;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $settingsModel = new Settings();

        $sale = $saleModel->exist($saleId);
        if (!$sale) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/historique');
            exit;
        }

        $details = $detailModel->getBySaleId($saleId);

        // Charger les informations du magasin
        $storeInfo = [
            'name' => $settingsModel->get('store_name') ?? 'Mon Magasin',
            'address' => $settingsModel->get('store_address') ?? '',
            'phone' => $settingsModel->get('store_phone') ?? '',
            'ice' => $settingsModel->get('store_ice') ?? ''
        ];

        // URL de base pour les liens
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        $this->render('facture', [
            'sale' => $sale,
            'details' => $details,
            'storeInfo' => $storeInfo,
            'baseUrl' => $baseUrl
        ]);
    }

    /**
     * Afficher la page de la facture par numéro de facture (query param ?ref=)
     * Page PUBLIQUE - accessible sans authentification pour envoyer aux clients
     */
    public function showByRef()
    {
        // Récupérer le numéro de facture depuis le query param
        $invoiceRef = $_GET['ref'] ?? null;

        if (!$invoiceRef) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Facture introuvable</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<style>body{font-family:Arial,sans-serif;padding:2rem;text-align:center;background:#f5f5f5}';
            echo '.container{background:#fff;padding:2rem;border-radius:12px;max-width:500px;margin:2rem auto;box-shadow:0 2px 10px rgba(0,0,0,0.1)}';
            echo 'h1{color:#ef4444}p{color:#666}</style>';
            echo '</head><body><div class="container">';
            echo '<h1>⚠️ Facture introuvable</h1>';
            echo '<p>Le numero de facture est requis.</p>';
            echo '<a href="/" style="color:#0B5E88;margin-top:1rem;display:inline-block;">Retour a l\'accueil</a>';
            echo '</div></body></html>';
            exit;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $settingsModel = new Settings();

        // Rechercher la vente par numéro de facture
        $sale = $saleModel->findByInvoiceNumber($invoiceRef);

        if (!$sale) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Facture introuvable</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<style>body{font-family:Arial,sans-serif;padding:2rem;text-align:center;background:#f5f5f5}';
            echo '.container{background:#fff;padding:2rem;border-radius:12px;max-width:500px;margin:2rem auto;box-shadow:0 2px 10px rgba(0,0,0,0.1)}';
            echo 'h1{color:#ef4444}p{color:#666}</style>';
            echo '</head><body><div class="container">';
            echo '<h1>⚠️ Facture introuvable</h1>';
            echo '<p>Cette facture n\'existe pas ou a été supprimée.</p>';
            echo '<a href="/" style="color:#0B5E88;margin-top:1rem;display:inline-block;">Retour a l\'accueil</a>';
            echo '</div></body></html>';
            exit;
        }

        $details = $detailModel->getBySaleId($sale['id']);

        // Charger les informations du magasin
        $storeInfo = [
            'name' => $settingsModel->get('store_name') ?? 'Mon Magasin',
            'address' => $settingsModel->get('store_address') ?? '',
            'phone' => $settingsModel->get('store_phone') ?? '',
            'ice' => $settingsModel->get('store_ice') ?? ''
        ];

        // URL de base pour les liens
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        // Charger la vue style ticket (showPreview) - sans sidebar, PUBLIC
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/header-minimal.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/facture-ticket.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/footer-minimal.php';
    }

    /**
     * Afficher la page de facture CLIENT (publique, sans authentification)
     */
    public function publicInvoice($params)
    {
        $saleId = $params['id'] ?? null;

        if (!$saleId) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Facture introuvable</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<style>body{font-family:Arial,sans-serif;padding:2rem;text-align:center;background:#f5f5f5}';
            echo '.container{background:#fff;padding:2rem;border-radius:12px;max-width:500px;margin:2rem auto;box-shadow:0 2px 10px rgba(0,0,0,0.1)}';
            echo 'h1{color:#ef4444}p{color:#666}</style>';
            echo '</head><body><div class="container">';
            echo '<h1>⚠️ Facture introuvable</h1>';
            echo '<p>Cette facture n\'existe pas ou a été supprimée.</p>';
            echo '<a href="/" style="color:#0B5E88;margin-top:1rem;display:inline-block;">Retour à l\'accueil</a>';
            echo '</div></body></html>';
            exit;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $settingsModel = new Settings();

        $sale = $saleModel->exist($saleId);
        if (!$sale) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Facture introuvable</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<style>body{font-family:Arial,sans-serif;padding:2rem;text-align:center;background:#f5f5f5}';
            echo '.container{background:#fff;padding:2rem;border-radius:12px;max-width:500px;margin:2rem auto;box-shadow:0 2px 10px rgba(0,0,0,0.1)}';
            echo 'h1{color:#ef4444}p{color:#666}</style>';
            echo '</head><body><div class="container">';
            echo '<h1>⚠️ Facture introuvable</h1>';
            echo '<p>Cette facture n\'existe pas ou a été supprimée.</p>';
            echo '<a href="/" style="color:#0B5E88;margin-top:1rem;display:inline-block;">Retour à l\'accueil</a>';
            echo '</div></body></html>';
            exit;
        }

        $details = $detailModel->getBySaleId($saleId);

        // Charger les informations du magasin
        $storeInfo = [
            'name' => $settingsModel->get('store_name') ?? 'Mon Magasin',
            'address' => $settingsModel->get('store_address') ?? '',
            'phone' => $settingsModel->get('store_phone') ?? '',
            'ice' => $settingsModel->get('store_ice') ?? ''
        ];

        // Définir l'URL de la facture publique
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $invoiceUrl = $protocol . '://' . $host . '/facture-client/' . $saleId;

        // Charger la vue publique
        $saleData = $sale;
        $saleDetails = $details;
        $storeData = $storeInfo;
        $publicUrl = $invoiceUrl;
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/facture-client.php';
    }

    /**
     * Envoyer la facture par SMS/WhatsApp
     */
    public function sendInvoice($params)
    {
        header('Content-Type: application/json');

        $saleId = $params['id'] ?? null;
        $phone = $_POST['phone'] ?? '';

        if (!$saleId || !$phone) {
            echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
            exit;
        }

        // Nettoyer le numéro de téléphone
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) < 8) {
            echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide']);
            exit;
        }

        $saleModel = new Sale();
        $settingsModel = new Settings();

        $sale = $saleModel->exist($saleId);
        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Facture introuvable']);
            exit;
        }

        // Construire l'URL de la facture - utiliser /facture?ref= pour le lien public
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $invoiceUrl = $protocol . '://' . $host . '/facture?ref=' . $sale['numero_facture'];
        $storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';

        // Construire le message
        $message = "{$storeName}\n";
        $message .= "Facture: {$sale['numero_facture']}\n";
        $message .= "Total: " . number_format($sale['total'], 2) . " Fc\n";
        $message .= "Date: " . date('d/m/Y H:i', strtotime($sale['date'])) . "\n\n";
        $message .= "Consulter votre facture:\n{$invoiceUrl}";

        // Lien WhatsApp
        $whatsappUrl = 'https://wa.me/' . ltrim($phone, '+') . '?text=' . urlencode($message);

        echo json_encode([
            'success' => true,
            'message' => 'Message prêt à être envoyé',
            'whatsapp_url' => $whatsappUrl,
            'sms_preview' => $message
        ]);
    }

    /**
     * Générer et télécharger la facture en PDF
     */
    public function downloadPdf($params)
    {
        $saleId = $params['id'] ?? null;

        if (!$saleId) {
            http_response_code(404);
            echo 'Facture introuvable';
            exit;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $settingsModel = new Settings();

        $sale = $saleModel->exist($saleId);
        if (!$sale) {
            http_response_code(404);
            echo 'Facture introuvable';
            exit;
        }

        $details = $detailModel->getBySaleId($saleId);

        $storeInfo = [
            'name' => $settingsModel->get('store_name') ?? 'Mon Magasin',
            'address' => $settingsModel->get('store_address') ?? '',
            'phone' => $settingsModel->get('store_phone') ?? '',
            'ice' => $settingsModel->get('store_ice') ?? ''
        ];

        // Générer le contenu HTML de la facture
        $html = $this->generateInvoiceHtml($sale, $details, $storeInfo);

        // Pour la génération PDF, on utilise une approche simple avec html2pdf ou on retourne l'HTML
        // En production, utilisez une vraie bibliothèque PDF comme TCPDF, Dompdf, ouWkHtmlToPdf
        echo $html;
        exit;
    }

    /**
     * Générer le HTML de la facture pour impression/PDF
     */
    private function generateInvoiceHtml($sale, $details, $storeInfo)
    {
        $itemsHtml = '';
        foreach ($details as $item) {
            $subtotal = floatval($item['quantite']) * floatval($item['prix']);
            $itemsHtml .= '
            <tr>
                <td>' . htmlspecialchars($item['produit_nom'] ?? 'Produit') . '</td>
                <td style="text-align:center;">' . $item['quantite'] . '</td>
                <td style="text-align:right;">' . number_format(floatval($item['prix']), 2) . ' Fc</td>
                <td style="text-align:right;">' . number_format($subtotal, 2) . ' Fc</td>
            </tr>';
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture ' . htmlspecialchars($sale['numero_facture']) . '</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 3px solid #0B5E88; padding-bottom: 20px; margin-bottom: 20px; }
        .store-name { font-size: 24px; font-weight: bold; color: #0B5E88; }
        .store-info { color: #666; font-size: 12px; margin-top: 5px; }
        .invoice-meta { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .invoice-number { font-size: 18px; font-weight: bold; }
        .invoice-date { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #0B5E88; color: #fff; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .totals { margin-left: auto; width: 300px; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .grand-total { font-size: 18px; font-weight: bold; color: #0B5E88; border-top: 2px solid #0B5E88; margin-top: 10px; padding-top: 10px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; color: #666; font-size: 12px; }
        .dgi-info { background: #e8f5e9; padding: 10px; border-radius: 8px; margin: 15px 0; text-align: center; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name">' . htmlspecialchars($storeInfo['name']) . '</div>
        <div class="store-info">
            ' . htmlspecialchars($storeInfo['address']) . '<br>
            Tél: ' . htmlspecialchars($storeInfo['phone']) . '<br>
            ICE: ' . htmlspecialchars($storeInfo['ice']) . '
        </div>
    </div>

    <div class="invoice-meta">
        <div>
            <div class="invoice-number">Facture: ' . htmlspecialchars($sale['numero_facture']) . '</div>
            <div class="invoice-date">Date: ' . date('d/m/Y H:i', strtotime($sale['date'])) . '</div>
        </div>
        <div style="text-align:right;">
            <div>Vendeur: ' . htmlspecialchars($sale['nom_vendeur'] ?? 'N/A') . '</div>
            ' . (!empty($sale['counters']) ? '<div style="font-size:12px;color:#0B5E88;">Compteur DGI: ' . htmlspecialchars($sale['counters']) . '</div>' : '') . '
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th style="text-align:center;">Qté</th>
                <th style="text-align:right;">Prix Unit.</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            ' . $itemsHtml . '
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Sous-total HT:</span>
            <span>' . number_format(floatval($sale['sous_total_ht']), 2) . ' Fc</span>
        </div>
        <div class="total-row">
            <span>TVA (16%):</span>
            <span>' . number_format(floatval($sale['tva']), 2) . ' Fc</span>
        </div>
        <div class="total-row grand-total">
            <span>TOTAL TTC:</span>
            <span>' . number_format(floatval($sale['total']), 2) . ' Fc</span>
        </div>
    </div>

    ' . (!empty($sale['counters']) ? '<div class="dgi-info"><strong>✓ Facture validée DGI</strong><br>Compteur: ' . htmlspecialchars($sale['counters']) . '</div>' : '') . '

    <div class="footer">
        <p>Merci de votre confiance !</p>
        <p style="margin-top:5px;">Conservez cette facture pour tout échange.</p>
    </div>
</body>
</html>';

        return $html;
    }
}
