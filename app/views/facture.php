<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Numérique - POS System</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .receipt-view {
            max-width: 450px;
            width: 100%;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .receipt-meta {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed var(--border);
        }
        @media print {
            .no-print, .btn { display: none !important; }
            body { background: white; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>
    <div id="receipt-container" class="receipt-view">
        <div id="loading" style="text-align: center; padding: 2rem;">
            <p>Chargement de la facture...</p>
        </div>
    </div>

    <div class="no-print" style="position: fixed; bottom: 2rem; right: 2rem;">
        <button onclick="window.print()" class="btn btn-primary" style="box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer la facture
        </button>
    </div>

    <script>
        function formatCurrency(amount) {
            return amount.toFixed(2) + ' Fc';
        }

        function formatDate(date) {
            return new Date(date).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        window.onload = function() {
            const params = new URLSearchParams(window.location.search);
            const encodedData = params.get('hash');

            if (!encodedData) {
                document.getElementById('receipt-container').innerHTML = '<div style="color: red; text-align: center;">Erreur: Aucune donnée de facture trouvée.</div>';
                return;
            }

            try {
                // Decode Base64
                const jsonStr = decodeURIComponent(escape(atob(encodedData)));
                const data = JSON.parse(jsonStr);
                
                renderReceipt(data);
            } catch (e) {
                console.error(e);
                document.getElementById('receipt-container').innerHTML = '<div style="color: red; text-align: center;">Erreur: Données de facture invalides.</div>';
            }
        };

        function renderReceipt(data) {
            const settings = data.st; // Store settings (minified)
            
            const itemsHtml = data.it.map(item => `
                <div class="receipt-item">
                    <span class="item-name">${item.n}</span>
                    <span class="item-qty">x${item.q}</span>
                    <span class="item-pu">${formatCurrency(item.p)}</span>
                    <span class="item-price">${formatCurrency(item.p * item.q)}</span>
                </div>
            `).join('');

            const taxRate = settings.t / 100;
            const subtotalHT = data.t / (1 + taxRate);
            const tax = data.t - subtotalHT;

            const html = `
                <div class="receipt-header">
                    <div class="store-name">${settings.n}</div>
                    <div class="store-info">
                        ${settings.a}<br>
                        Tel: ${settings.p}<br>
                        ICE: ${settings.i}
                    </div>
                </div>

                <div class="receipt-meta">
                    <span>${data.i}</span>
                    <span>${formatDate(data.d)}</span>
                </div>

                <div class="receipt-items">
                    <div class="receipt-item" style="font-weight: 600; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                        <span class="item-name">Article</span>
                        <span class="item-qty">Qte</span>
                        <span class="item-pu">PU</span>
                        <span class="item-price">Total</span>
                    </div>
                    ${itemsHtml}
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Sous-total HT:</span>
                        <span>${formatCurrency(subtotalHT)}</span>
                    </div>
                    <div class="receipt-total-row">
                        <span>TVA (${settings.t}%):</span>
                        <span>${formatCurrency(tax)}</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${formatCurrency(data.t)}</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div>Vendeur: ${data.s}</div>
                    <div class="barcode">||||| ${data.i} |||||</div>
                    <div class="thank-you">Merci de votre visite !</div>
                    <div style="margin-top: 0.5rem;">Document numérique officiel</div>
                    <div style="margin-top: 0.25rem; font-size: 0.7rem; color: var(--muted);">Conservez ce ticket pour tout échange</div>
                </div>
            `;

            const container = document.getElementById('receipt-container');
            container.innerHTML = html;
            container.classList.add('receipt'); // Add the thermal ticket class for global styling
            document.title = 'Facture ' + data.i;
        }
    </script>
</body>
</html>
