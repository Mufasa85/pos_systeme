<!DOCTYPE html>
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
            max-width: 420px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page-facture-ticket {
                padding: 0;
                min-height: auto;
            }

            .ticket-wrapper {
                box-shadow: none;
                max-width: 80mm;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }

            @page {
                margin: 5mm;
                size: 80mm auto;
            }
        }
    </style>
</head>

<body>
    <div class="page-facture-ticket">
        <div class="ticket-wrapper">