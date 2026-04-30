<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Code-barres</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" type="text/javascript"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0B5E88, #074a68);
        }

        .box {
            width: 90%;
            max-width: 500px;
            padding: 30px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        h2 {
            color: #0B5E88;
            margin-bottom: 20px;
        }

        #reader {
            width: 100%;
            margin: 20px auto;
            border-radius: 12px;
            overflow: hidden;
        }

        /* Style du cadre de scan */
        #reader video {
            border-radius: 12px;
            object-fit: cover;
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
            display: none;
        }

        .status.loading {
            background: #fff3cd;
            color: #856404;
            display: block;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }

        .product-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            display: none;
        }

        .product-info.show {
            display: block;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .product-price {
            font-size: 24px;
            color: #0B5E88;
            font-weight: bold;
            margin-top: 10px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            margin-top: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        #cancelBtn {
            background: #dc3545;
            color: white;
        }

        #cancelBtn:hover {
            background: #a71d2a;
        }

        #rescanBtn {
            background: #28a745;
            color: white;
            display: none;
        }

        #rescanBtn:hover {
            background: #218838;
        }

        /* Animation de chargement */
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0B5E88;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Message de redirection */
        .redirect-msg {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }

        .redirect-msg.show {
            display: block;
        }

        /* Zone de scan alternative */
        #qr-shaded-region {
            border: 3px solid #0B5E88 !important;
            border-radius: 8px !important;
        }
    </style>
</head>

<body>

    <div class="box">
        <h2>📷 Scanner Code-barres</h2>

        <!-- État de chargement -->
        <div id="loadingStatus" class="status loading">
            <div class="spinner"></div>
            Recherche du produit...
        </div>

        <!-- Résultat -->
        <div id="resultStatus" class="status"></div>

        <!-- Info produit -->
        <div id="productInfo" class="product-info">
            <div class="product-name" id="productName">-</div>
            <div class="product-price" id="productPrice">-</div>
        </div>

        <!-- Message redirection -->
        <div id="redirectMsg" class="redirect-msg">
            ⏳ Redirection vers la caisse...
        </div>

        <!-- Zone de scan -->
        <div id="reader"></div>
        <p id="result"></p>

        <button id="cancelBtn" class="btn" onclick="cancelScan()">Annuler</button>
        <button id="rescanBtn" class="btn" onclick="restartScan()">Scanner à nouveau</button>
    </div>

    <script>
        // Configuration
        const APP_URL = window.location.origin;

        let html5QrCode = null;
        let isScanning = false;
        let lastScannedCode = null;
        let isProcessing = false;

        // Démarrer le scanner automatiquement
        window.addEventListener('load', function() {
            startScanner();
        });

        async function startScanner() {
            html5QrCode = new Html5Qrcode("reader");
            isScanning = true;
            isProcessing = false;
            lastScannedCode = null;

            try {
                await html5QrCode.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 300,
                            height: 300
                        }
                    },
                    onScanSuccess,
                    onScanFailure
                );
                console.log("Scanner démarré avec succès");
            } catch (err) {
                console.error("Erreur démarrage scanner:", err);
                document.getElementById("result").innerHTML =
                    "<span style='color:red;'>Erreur: Impossible d'accéder à la caméra.</span>";
            }
        }

        // Code détecté avec succès
        async function onScanSuccess(code) {
            // Éviter les scans répétitifs
            if (isProcessing || code === lastScannedCode) {
                return;
            }

            lastScannedCode = code;
            isProcessing = true;

            // Afficher le code détecté
            document.getElementById("result").innerHTML =
                "<strong>Code détecté :</strong> " + code;
            document.getElementById("loadingStatus").classList.add("loading");
            document.getElementById("resultStatus").className = "status";
            document.getElementById("productInfo").classList.remove("show");
            document.getElementById("redirectMsg").classList.remove("show");

            // Rechercher le produit dans la base
            await searchProduct(code);
        }

        // Rechercher le produit via l'API
        async function searchProduct(barcode) {
            try {
                console.log("Recherche produit:", barcode);

                const response = await fetch(APP_URL + "/api/produit?code_barres=" + encodeURIComponent(barcode));

                if (!response.ok) {
                    throw new Error("Erreur réponse API");
                }

                const product = await response.json();
                console.log("Produit trouvé:", product);

                // Masquer le chargement
                document.getElementById("loadingStatus").classList.remove("loading");

                if (product && product.id) {
                    // Produit trouvé !
                    onProductFound(product, barcode);
                } else {
                    // Produit non trouvé
                    onProductNotFound(barcode);
                }

            } catch (err) {
                console.error("Erreur recherche produit:", err);
                document.getElementById("loadingStatus").classList.remove("loading");
                document.getElementById("resultStatus").className = "status error";
                document.getElementById("resultStatus").textContent = "Erreur de connexion au serveur";

                // Permettre de rescanner
                setTimeout(() => {
                    isProcessing = false;
                    lastScannedCode = null;
                }, 2000);
            }
        }

        // Produit trouvé - rediriger vers la caisse
        function onProductFound(product, barcode) {
            document.getElementById("resultStatus").className = "status success";
            document.getElementById("resultStatus").textContent = "✓ Produit trouvé !";

            // Afficher les infos du produit
            document.getElementById("productInfo").classList.add("show");
            document.getElementById("productName").textContent = product.nom;
            document.getElementById("productPrice").textContent = formatCurrency(product.prix);

            // Arrêter le scanner
            stopScanner();

            // Afficher message de redirection
            document.getElementById("redirectMsg").classList.add("show");

            // Rediriger vers la caisse après 1 seconde
            setTimeout(() => {
                const redirectUrl = APP_URL + "/caisse?add_product=" + product.id + "&barcode=" + encodeURIComponent(barcode);
                console.log("Redirection vers:", redirectUrl);
                window.location.href = redirectUrl;
            }, 1500);
        }

        // Produit non trouvé
        function onProductNotFound(barcode) {
            document.getElementById("resultStatus").className = "status error";
            document.getElementById("resultStatus").textContent = "✗ Code-barres non trouvé dans la base";

            // Permettre de rescanner
            setTimeout(() => {
                isProcessing = false;
                lastScannedCode = null;
                document.getElementById("resultStatus").className = "status";
            }, 3000);
        }

        // Formatage devise
        function formatCurrency(amount) {
            return parseFloat(amount).toFixed(2) + ' Fc';
        }

        // Échec du scan (normal, pas d'erreur)
        function onScanFailure(error) {
            // Ne rien faire - c'est normal quand rien n'est scanné
        }

        // Annuler et retourner
        async function cancelScan() {
            await stopScanner();
            window.history.back();
        }

        // Redémarrer le scan
        async function restartScan() {
            isProcessing = false;
            lastScannedCode = null;
            document.getElementById("result").innerHTML = "";
            document.getElementById("resultStatus").className = "status";
            document.getElementById("productInfo").classList.remove("show");
            document.getElementById("redirectMsg").classList.remove("show");
            document.getElementById("rescanBtn").style.display = "none";

            await startScanner();
        }

        // Arrêter le scanner
        async function stopScanner() {
            if (html5QrCode && isScanning) {
                try {
                    await html5QrCode.stop();
                    isScanning = false;
                    document.getElementById("rescanBtn").style.display = "block";
                } catch (err) {
                    console.warn("Erreur arrêt scanner:", err);
                }
            }
        }
    </script>

</body>

</html>