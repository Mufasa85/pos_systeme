/**
 * Recharges.js - Paiement Factures Electricité/Eau
 * Flow: Fetch API REGIDESO → Mois → Panier → Paiement
 * 
 * Structure API: results[annee][mois] = [{ MONTANT, NUMERO_FACTURE, ... }]
 */

class BillPayment {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.currentProvider = null;
        this.currentInquiryId = null;
        this.clientInfo = null;
        this.apiResponse = null;
        this.availableYears = [];
        this.currentYear = null;
        this.months = [];
        this.selectedMonths = [];

        console.log('[BillPayment] Initialisé, session:', this.sessionId);
    }

    // ==========================================
    // INITIALIZATION
    // ==========================================

    generateSessionId() {
        return 'bill_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // ==========================================
    // MAPPING MOIS
    // ==========================================

    get moisMapping() {
        return {
            'janvier': 1, 'fevrier': 2, 'mars': 3, 'avril': 4,
            'mai': 5, 'juin': 6, 'juillet': 7, 'aout': 8,
            'septembre': 9, 'octobre': 10, 'novembre': 11, 'decembre': 12
        };
    }

    get moisNoms() {
        return ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    }

    // ==========================================
    // STEP 1: FETCH API - Récupérer infos client + mois
    // ==========================================

    async fetchBillInquiry(providerCode, numeroCompteur) {
        console.log('[BillPayment] Fetch inquiry:', providerCode, numeroCompteur);

        const providerId = providerCode === 'SNEL' ? 1 : 2;
        this.currentProvider = providerId;

        try {
            this.showLoading('Recherche en cours...');

            // Appel API Provider
            const response = await this.callProviderAPI(providerCode, numeroCompteur);

            if (!response.success) {
                throw new Error(response.message || 'Erreur API');
            }

            this.apiResponse = response;

            // Extraire années disponibles
            this.availableYears = Object.keys(response.results)
                .map(y => parseInt(y))
                .sort((a, b) => b - a);

            // Afficher filtre années
            this.populateYearFilter();

            // Afficher info client
            this.displayClientInfo({
                nom: 'Client ' + numeroCompteur,
                adresse: response.results?.deviceid || numeroCompteur
            });

            this.hideLoading();

            // Charger année par défaut (la plus récente)
            if (this.availableYears.length > 0) {
                this.loadYear(this.availableYears[0]);
            }

            return { success: true, data: response };

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
            console.error('[BillPayment] Erreur fetch:', error);
            return { success: false, error: error.message };
        }
    }

    // ==========================================
    // STEP 2: YEAR FILTER - Charger les mois d'une année
    // ==========================================

    populateYearFilter() {
        const yearFilter = document.getElementById('year-filter');
        if (!yearFilter) return;

        // Vider et ajouter option par défaut
        yearFilter.innerHTML = '<option value="">Toutes les années</option>';

        this.availableYears.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearFilter.appendChild(option);
        });
    }

    loadYear(year) {
        this.currentYear = parseInt(year);
        this.months = [];
        this.selectedMonths = [];

        if (!this.apiResponse?.results?.[year]) {
            console.log('[BillPayment] Aucune donnée pour year:', year);
            this.displayMonths([]);
            return;
        }

        const yearData = this.apiResponse.results[year];

        // Extraire les 12 mois
        const monthOrder = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

        monthOrder.forEach((monthName, index) => {
            const monthRecords = yearData[monthName] || [];
            const firstRecord = monthRecords[0] || {};
            const montant = parseFloat(firstRecord.MONTANT) || 0;

            // Un impayé = montant > 0 (donc sélectionnable)
            const estImpayé = montant > 0;

            if (estImpayé || monthRecords.length > 0) {
                this.months.push({
                    id: `${year}_${index + 1}`,
                    annee: year,
                    mois: index + 1,
                    mois_nom: monthName,
                    montant: montant,
                    numero_facture: firstRecord.NUMERO_FACTURE || '',
                    commune: firstRecord.COMMUNE || '',
                    est_impaye: estImpayé
                });
            }
        });

        this.displayMonths(this.months);

        console.log('[BillPayment] Mois chargés pour', year, ':', this.months.length);
    }

    // ==========================================
    // STEP 3: SELECT MONTHS - Sélectionner mois à payer
    // ==========================================

    toggleMonth(monthId) {
        const month = this.months.find(m => m.id === monthId);
        if (!month) return;

        // Ne permettre que les impayés
        if (!month.est_impaye) {
            this.showToast('Ce mois est déjà réglé (montant = 0)', 'warning');
            return;
        }

        // Toggle sélection
        const index = this.selectedMonths.findIndex(m => m.id === monthId);
        if (index > -1) {
            this.selectedMonths.splice(index, 1);
            this.updateMonthUI(monthId, false);
        } else {
            this.selectedMonths.push({ ...month });
            this.updateMonthUI(monthId, true);
        }

        this.updateCart();

        console.log('[BillPayment] Mois sélectionnés:', this.selectedMonths.length);
    }

    updateMonthUI(monthId, selected) {
        const card = document.querySelector(`.month-card[data-id="${monthId}"]`);
        if (card) {
            card.classList.toggle('selected', selected);
        }
    }

    // ==========================================
    // STEP 4: CART - Gérer le panier
    // ==========================================

    updateCart() {
        const cartItems = document.getElementById('cart-items');
        const subtotalEl = document.getElementById('subtotal');
        const totalEl = document.getElementById('total');
        const validateBtn = document.getElementById('show-preview');

        if (this.selectedMonths.length === 0) {
            cartItems.innerHTML = '<div class="cart-empty">Aucun mois sélectionné</div>';
            if (subtotalEl) subtotalEl.textContent = '0.00 Fc';
            if (totalEl) totalEl.textContent = '0.00 Fc';
            if (validateBtn) validateBtn.disabled = true;
            return;
        }

        let html = '';
        let subtotal = 0;

        this.selectedMonths.forEach((month, index) => {
            subtotal += month.montant;
            const monthName = this.moisNoms[month.mois];

            html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${monthName} ${month.annee}</div>
                        <div class="cart-item-price">${this.formatMoney(month.montant)} Fc</div>
                    </div>
                    <button class="cart-item-remove" onclick="billPayment.removeMonth(${index})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            `;
        });

        cartItems.innerHTML = html;
        if (subtotalEl) subtotalEl.textContent = this.formatMoney(subtotal) + ' Fc';
        if (totalEl) totalEl.textContent = this.formatMoney(subtotal) + ' Fc';
        if (validateBtn) validateBtn.disabled = false;

        this.updateFloatingBadge();
    }

    removeMonth(index) {
        const month = this.selectedMonths[index];
        this.selectedMonths.splice(index, 1);
        this.updateMonthUI(month.id, false);
        this.updateCart();
    }

    clearCart() {
        this.selectedMonths.forEach(m => this.updateMonthUI(m.id, false));
        this.selectedMonths = [];
        this.updateCart();
    }

    updateFloatingBadge() {
        const badge = document.getElementById('cart-badge');
        const total = document.getElementById('cart-floating-total');

        if (badge) badge.textContent = this.selectedMonths.length;
        if (total) {
            const sum = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
            total.textContent = this.formatMoney(sum) + ' Fc';
        }
    }

    // ==========================================
    // STEP 5: PAYMENT - Valider le paiement
    // ==========================================

    async processPayment(paymentData) {
        console.log('[BillPayment] Traitement paiement...');

        if (this.selectedMonths.length === 0) {
            this.showError('Aucun mois sélectionné');
            return { success: false, error: 'Panier vide' };
        }

        try {
            this.showLoading('Traitement du paiement...');

            const payload = {
                provider_id: this.currentProvider,
                numero_compteur: document.getElementById('invoice-number')?.value,
                client_reference: this.apiResponse?.deviceid || '',
                client_nom: document.getElementById('client-nom')?.value || '',
                api_response: JSON.stringify(this.apiResponse),
                months: this.selectedMonths.map(m => ({
                    annee: m.annee,
                    mois: m.mois,
                    montant: m.montant,
                    numero_facture: m.numero_facture
                })),
                total_montant: this.selectedMonths.reduce((acc, m) => acc + m.montant, 0),
                nombre_mois: this.selectedMonths.length,
                methode_paiement: paymentData?.methode || 'cash'
            };

            const response = await fetch('/api/bill-payment?action=process', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            this.hideLoading();

            if (result.success) {
                this.generateTicket(result.data);
                this.reset();
                return { success: true, data: result.data };
            } else {
                throw new Error(result.message || 'Erreur payment');
            }

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
            console.error('[BillPayment] Erreur payment:', error);
            return { success: false, error: error.message };
        }
    }

    // ==========================================
    // API CALLS
    // ==========================================

    async callProviderAPI(providerCode, numeroCompteur) {
        // Appel API via proxy backend (évite CORS)
        try {
            const url = `${APP_URL}/api/bill-payment?action=fetch&compteur=${encodeURIComponent(numeroCompteur)}&service=${encodeURIComponent(providerCode)}`;

            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Erreur API: ' + response.status);
            }

            const result = await response.json();
            console.log('[BillPayment] Données API:', result);

            if (!result.success) {
                throw new Error(result.message || 'Erreur API');
            }

            const data = result.data;

            // Parser les données reçues
            // Structure attendue: {results: {annee: {mois: [{MONTANT, NUMERO_FACTURE, ...}]}}}
            const results = data.results || data;

            return {
                success: data.success !== false,
                deviceid: data.deviceid || numeroCompteur,
                client_nom: data.client_nom || data.nom || '',
                client_adresse: data.adresse || data.adresse_client || '',
                results: results
            };

        } catch (error) {
            console.error('[BillPayment] Erreur API:', error);
            throw error;
        }
    }

    // ==========================================
    // UI HELPERS
    // ==========================================

    displayClientInfo(client) {
        const nomEl = document.getElementById('client-nom');
        const telEl = document.getElementById('client-tel');

        if (nomEl) nomEl.value = client.nom || '';
        if (telEl) telEl.value = client.tel || '0000';
    }

    displayMonths(months) {
        const container = document.getElementById('months-grid');
        if (!container) return;

        if (months.length === 0) {
            container.innerHTML = '<div class="no-months">Aucune donnée pour cette année</div>';
            return;
        }

        let html = '<div class="months-grid">';

        months.forEach(m => {
            const monthName = this.moisNoms[m.mois];
            const isImpayé = m.est_impaye;
            const isSelected = this.selectedMonths.some(sm => sm.id === m.id);

            html += `
                <div class="month-card ${isImpayé ? 'unpaid' : 'paid'} ${isSelected ? 'selected' : ''}" 
                     data-id="${m.id}"
                     onclick="billPayment.toggleMonth('${m.id}')">
                    <div class="month-header">
                        <span class="month-status ${isImpayé ? 'unpaid' : 'paid'}">
                            ${isImpayé ? '⚡ Impayé' : '✓ Réglé'}
                        </span>
                    </div>
                    <div class="month-name">${monthName}</div>
                    <div class="month-year">${m.annee}</div>
                    <div class="month-amount">${isImpayé ? this.formatMoney(m.montant) + ' Fc' : '0.00 Fc'}</div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    showLoading(message) {
        // Ne pas afficher le loader sur /caisse (uniquement /recharges)
        const pageRecharges = document.getElementById('page-recharges');
        if (!pageRecharges) return;

        let loader = document.getElementById('bill-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'bill-loader';
            loader.className = 'bill-loader';
            loader.innerHTML = `
                <div class="loader-overlay">
                    <div class="loader-spinner"></div>
                    <div class="loader-message">${message || 'Chargement...'}</div>
                </div>
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    }

    hideLoading() {
        const loader = document.getElementById('bill-loader');
        if (loader) loader.style.display = 'none';
    }

    showError(message) {
        alert('Erreur: ' + message);
    }

    showToast(message, type = 'info') {
        console.log(`[Toast ${type}]:`, message);
    }

    showPreview() {
        if (this.selectedMonths.length === 0) return;

        const formattedDate = new Date().toLocaleString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        // Construire les items du reçu
        let itemsHtml = '';
        this.selectedMonths.forEach(month => {
            const monthName = this.moisNoms[month.mois];
            itemsHtml += `
                <div class="receipt-item">
                    <span class="item-name">${monthName} ${month.annee}</span>
                    <span class="item-qty">x1</span>
                    <span class="item-price">${this.formatMoney(month.montant)}</span>
                </div>
            `;
        });

        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
        const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div>${STORE_INFO.address}</div>
                        <div>Tel: ${STORE_INFO.phone}</div>
                        <div>ID Nat: ${STORE_INFO.ice}</div>
                    </div>
                </div>
                <div class="receipt-meta">
                    <span>RECHARGE-${service}</span>
                    <span>${document.getElementById('invoice-type')?.value || 'FV'}</span>
                </div>
                <div class="receipt-items">
                    ${itemsHtml}
                </div>
                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Sous-total:</span>
                        <span>${this.formatMoney(total)} Fc</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL:</span>
                        <span>${this.formatMoney(total)} Fc</span>
                    </div>
                </div>
                <div class="receipt-footer">
                    <div class="thank-you">Paiement facture ${service}</div>
                    <div style="margin-top: 5px; font-size: 9px; font-style: italic;">---Powered By Osat---</div>
                </div>
            </div>
        `;

        $('#preview-modal').classList.add('active');

        // Ajouter boutons confirmation
        const previewFooter = document.querySelector('#preview-modal .modal-footer');
        if (previewFooter) {
            previewFooter.innerHTML = `
                <button class="btn btn-secondary" onclick="billPayment.closePreview()">Fermer</button>
                <button class="btn btn-primary" id="confirm-bill-sale" onclick="billPayment.processPayment()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirmer le paiement
                </button>
            `;
        }
    }

    closePreview() {
        $('#preview-modal').classList.remove('active');
    }

    async confirmSale() {
        if (this.selectedMonths.length === 0) {
            this.showError('Aucun mois sélectionné');
            return;
        }

        try {
            this.showLoading('Traitement du paiement...');

            const payload = {
                provider_id: this.currentProvider,
                numero_compteur: document.getElementById('invoice-number')?.value,
                client_nom: document.getElementById('client-nom')?.value || '',
                months: this.selectedMonths.map(m => ({
                    annee: m.annee,
                    mois: m.mois,
                    montant: m.montant,
                    numero_facture: m.numero_facture
                })),
                total_montant: this.selectedMonths.reduce((acc, m) => acc + m.montant, 0),
                nombre_mois: this.selectedMonths.length,
                methode_paiement: 'cash'
            };

            const response = await fetch('/api/bill-payment?action=process', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            this.hideLoading();
            this.closePreview();

            if (result.success) {
                this.showToast('Paiement réussi! N°: ' + result.data.numero_transaction, 'success');
                this.generateTicket(result.data);
                this.reset();
            } else {
                throw new Error(result.message || 'Erreur payment');
            }

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
            console.error('[BillPayment] Erreur confirmSale:', error);
        }
    }

    generateTicket(data) {
        console.log('[BillPayment] Ticket généré:', data);
        alert('Paiement réussi! N°: ' + data.numero_transaction);
    }

    reset() {
        this.currentInquiryId = null;
        this.clientInfo = null;
        this.apiResponse = null;
        this.availableYears = [];
        this.currentYear = null;
        this.months = [];
        this.selectedMonths = [];
        this.updateCart();
    }

    getMonthName(monthNum) {
        return this.moisNoms[monthNum] || '';
    }

    formatMoney(amount) {
        return parseFloat(amount).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
}

// ==========================================
// GLOBAL INSTANCE
// ==========================================

let billPayment;

document.addEventListener('DOMContentLoaded', () => {
    billPayment = new BillPayment();
});

// ==========================================
// GLOBAL FUNCTIONS
// ==========================================

// Fonction appelée par le bouton "Valider la facture" dans preview-modal
// Détecte si on est sur /recharges (billPayment) ou /caisse (posCart)
function confirmSaleFromPreview() {
    // Vérifier si billPayment a des mois sélectionnés (page /recharges)
    if (typeof billPayment !== 'undefined' && billPayment.selectedMonths && billPayment.selectedMonths.length > 0) {
        console.log('[confirmSaleFromPreview] Mode: BillPayment (Recharges)');
        billPayment.processPayment();
    } else {
        // Sinon utiliser posCart (page Caisse classique)
        console.log('[confirmSaleFromPreview] Mode: posCart (Caisse)');
        posCart.confirmSale();
    }
}

function fetchBillInquiry(providerCode, numeroCompteur) {
    if (!providerCode || !numeroCompteur) {
        alert('Veuillez sélectionner un service et entrer un numéro');
        return;
    }
    billPayment.fetchBillInquiry(providerCode, numeroCompteur);
}

function selectService(service) {
    billPayment.currentProvider = service;
}

function filterByYear(year) {
    if (year) {
        billPayment.loadYear(year);
    }
}

// ==========================================
// STYLES
// ==========================================

const billPaymentStyles = `
<style>
/* Loader caché par défaut, affiché uniquement via JS sur page recharges */
body:not(#page-recharges) .bill-loader {
    display: none;
}
.bill-loader {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.5);
}

.loader-overlay {
    background: white;
    padding: 30px 50px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.loader-spinner {
    width: 40px; height: 40px;
    border: 4px solid #e2e8f0;
    border-top-color: #0B5E88;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin { to { transform: rotate(360deg); } }
.loader-message { color: #1a1a2e; font-weight: 500; }

.months-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 16px;
}

.month-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.month-card:hover:not(.paid) {
    border-color: #0B5E88;
    transform: translateY(-2px);
}

.month-card.selected {
    border-color: #0B5E88;
    background: #f0f9ff;
}

.month-card.paid {
    opacity: 0.6;
    cursor: not-allowed;
}

.month-header { margin-bottom: 8px; }

.month-status {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.month-status.paid {
    background: #d1fae5;
    color: #065f46;
}

.month-status.unpaid {
    background: #fee2e2;
    color: #991b1b;
}

.month-name {
    font-weight: 600;
    color: #1a1a2e;
    font-size: 1rem;
}

.month-year {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 4px;
}

.month-amount {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0B5E88;
}

.no-months {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

@media (max-width: 768px) {
    .months-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', billPaymentStyles);
