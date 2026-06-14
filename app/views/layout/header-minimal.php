<?php
if (!isset($settingsModel)) {
    $settingsModel = new \App\Models\Settings();
}
$paperType = $settingsModel->get('paper_type') ?: '80mm';

// Configuration selon le type de papier
$sizeRule = '80mm auto';
$maxWidth = '76mm';
$bodyFont = "'Courier New', Courier, monospace";
$bodyFontSize = '14px';

switch ($paperType) {
    case '57mm':
        $sizeRule = '57mm auto';
        $maxWidth = '53mm';
        $bodyFontSize = '11px';
        break;
    case '80mm':
        $sizeRule = '80mm auto';
        $maxWidth = '76mm';
        $bodyFontSize = '14px';
        break;
    case 'A4':
        $sizeRule = 'A4 portrait';
        $maxWidth = '190mm';
        $bodyFontSize = '12px';
        $bodyFont = "'Helvetica Neue', Arial, sans-serif";
        break;
    case 'A5':
        $sizeRule = 'A5 portrait';
        $maxWidth = '128mm';
        $bodyFontSize = '12px';
        $bodyFont = "'Helvetica Neue', Arial, sans-serif";
        break;
    case 'Letter':
        $sizeRule = 'Letter portrait';
        $maxWidth = '190mm';
        $bodyFontSize = '12px';
        $bodyFont = "'Helvetica Neue', Arial, sans-serif";
        break;
    case 'Legal':
        $sizeRule = 'Legal portrait';
        $maxWidth = '190mm';
        $bodyFontSize = '12px';
        $bodyFont = "'Helvetica Neue', Arial, sans-serif";
        break;
}
$screenMaxWidth = ($paperType === 'A4' || $paperType === 'A5' || $paperType === 'Letter' || $paperType === 'Legal') ? '800px' : '420px';
?><!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de caisse - <?= htmlspecialchars($storeInfo['name'] ?? 'Mon Magasin') ?></title>
    <link rel="stylesheet" href="./assets/css/styles.css?v=1">
    <link rel="stylesheet" href="./assets/css/mobile-caisse.css?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
        const APP_URL = window.location.origin;
        const CURRENT_USER = <?= json_encode([
                                    'id' => $_SESSION['user_id'] ?? null,
                                    'username' => $_SESSION['nom_utilisateur'] ?? '',
                                    'fullName' => $_SESSION['nom_complet'] ?? '',
                                    'role' => $_SESSION['role'] ?? 'vendeur'
                                ]) ?>;
    </script>
    <style>
        /* Minimal styles for ticket page - hide all navigation */
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .page-facture-ticket {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 2rem 1rem;
            box-sizing: border-box;
        }

        .ticket-wrapper {
            width: 100%;
            max-width: <?= $screenMaxWidth ?>;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }

        /* Classic Invoice Layout (A4/A5) Styles */
        .invoice-classic {
            width: 100%;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #000;
            background: #fff;
            padding: 25px;
            box-sizing: border-box;
        }

        .invoice-header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            gap: 20px;
        }

        .invoice-store-details {
            width: 50%;
        }

        .invoice-store-name {
            font-size: 24px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 10px 0;
            color: #000;
            letter-spacing: 0.5px;
        }

        .invoice-store-info {
            font-size: 13px;
            line-height: 1.6;
            color: #111;
        }

        .invoice-client-box {
            width: 45%;
            border: 1.5px solid #000;
            border-radius: 4px;
            overflow: hidden;
        }

        .invoice-client-header {
            background: #f0f0f0;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 12px;
            border-bottom: 1.5px solid #000;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-client-body {
            padding: 10px 12px;
            font-size: 13px;
            line-height: 1.5;
        }

        .invoice-client-row {
            display: flex;
            margin-bottom: 5px;
        }

        .invoice-client-row:last-child {
            margin-bottom: 0;
        }

        .invoice-client-row .label {
            width: 80px;
            font-weight: 700;
            color: #333;
        }

        .invoice-client-row .value {
            flex: 1;
            color: #000;
        }

        .invoice-title-container {
            text-align: center;
            margin-bottom: 25px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 15px 0;
        }

        .invoice-title {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 5px 0;
            letter-spacing: 1px;
            color: #000;
            text-transform: uppercase;
        }

        .invoice-subtitle {
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .invoice-meta-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .invoice-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .invoice-items-table th {
            background: #f5f5f5;
            border-top: 2px solid #000;
            border-bottom: 2.5px solid #000;
            padding: 10px 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: #000;
        }

        .invoice-items-table td {
            border-bottom: 1.5px solid #000;
            padding: 10px 8px;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
        }

        .invoice-summary-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 20px;
        }

        .invoice-summary-left {
            width: 50%;
            font-size: 13px;
            line-height: 1.6;
        }

        .invoice-summary-right {
            width: 45%;
            text-align: right;
        }

        .invoice-grand-total {
            font-size: 22px;
            font-weight: 800;
            border-top: 3.5px double #000;
            border-bottom: 3.5px double #000;
            padding: 10px 0;
            margin-bottom: 15px;
            display: inline-block;
            width: 100%;
            text-align: right;
            color: #000;
            letter-spacing: 0.5px;
        }

        .invoice-comment {
            font-size: 12px;
            color: #333;
            text-align: left;
            border: 1px dashed #000;
            padding: 8px;
            border-radius: 4px;
            background: #fafafa;
            margin-top: 15px;
        }

        .invoice-amount-spelled {
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            font-style: italic;
            margin-bottom: 25px;
            color: #000;
        }

        .invoice-security-box {
            display: flex;
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 15px;
            align-items: center;
            margin-top: 25px;
            background: #fff;
            gap: 20px;
        }

        .security-qr-section {
            width: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .security-details-section {
            flex: 1;
        }

        .security-title {
            font-size: 12px;
            font-weight: 800;
            color: #000;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }

        .security-details-table {
            width: 100%;
            font-size: 12px;
            border-collapse: collapse;
        }

        .security-details-table td {
            padding: 4px 0;
            vertical-align: top;
            color: #000;
        }

        .security-details-table td:first-child {
            width: 130px;
            font-weight: 700;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
                font-family: <?= $bodyFont ?>;
                font-size: <?= $bodyFontSize ?>;
            }

            .page-facture-ticket {
                padding: 0;
                min-height: auto;
            }

            .ticket-wrapper {
                box-shadow: none;
                max-width: <?= $maxWidth ?>;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }

            @page {
                margin: 5mm;
                size: <?= $sizeRule ?>;
            }

            .invoice-classic {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="page-facture-ticket">
        <div class="ticket-wrapper">