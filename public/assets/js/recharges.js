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
        this.dgiResponse = null;

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
            console.log('[BillPayment] API Response:', JSON.stringify(response).substring(0, 500));

            // Extraire années disponibles - parser toutes les clés numériques
            const allKeys = Object.keys(response.results || {});
            this.availableYears = allKeys
                .filter(key => !isNaN(parseInt(key))) // Filter only numeric keys (years)
                .map(y => parseInt(y))
                .sort((a, b) => b - a);

            console.log('[BillPayment] Années disponibles:', this.availableYears);

            // Afficher filtre années
            this.populateYearFilter();

            // Afficher info client avec les nouvelles données de l'API
            this.displayClientInfo({
                nom: response.client_nom || 'Client ' + numeroCompteur,
                commune: response.client_commune || '',
                province: response.client_province || '',
                numero: response.client_numero || numeroCompteur,
                adresse: response.client_adresse || ''
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
    // STEP 5: PAYMENT - Valider le paiement (avec DGI)
    // ==========================================

    async processPayment(paymentData) {
        console.log('[BillPayment] Traitement paiement avec DGI...');

        if (this.selectedMonths.length === 0) {
            this.showError('Aucun mois sélectionné');
            return { success: false, error: 'Panier vide' };
        }

        try {
            this.showLoading('Validation DGI...');

            // Étape 1: Valider avec DGI
            const dgiResponse = await this.validateWithDGI();
            this.dgiResponse = dgiResponse;

            if (!dgiResponse.success) {
                this.hideLoading();
                this.showError('Erreur DGI: ' + (dgiResponse.message || 'Impossible de valider'));
                return { success: false, error: dgiResponse.message };
            }

            this.showLoading('Sauvegarde en cours...');

            const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';
            const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);

            // Étape 2: Sauvegarder dans la table ventes (comme pour les ventes normales)
            const ventePayload = {
                articles: this.selectedMonths.map(m => ({
                    produit_id: 0,
                    nom: `${this.moisNoms[m.mois]} ${m.annee} (${service})`,
                    prix: m.montant,
                    quantite: 1,
                    tax_rate: 0,
                    tax_etiquette: 'B'
                })),
                client_id: null,
                sous_total_ht: total,
                tva: 0,
                total: total,
                type_facture: document.getElementById('invoice-type')?.value || 'FV',
                providerService: service,
                dgi_data: {
                    dateDGI: dgiResponse.data?.dateDGI || null,
                    qrCode: dgiResponse.data?.qrCode || '',
                    codeDEFDGI: dgiResponse.data?.codeDEFDGI || '',
                    counters: dgiResponse.data?.counters || null,
                    nim: dgiResponse.data?.nim || null,
                    total: dgiResponse.data?.total || null,
                    vtotal: dgiResponse.data?.vtotal || null,
                    isf: dgiResponse.data?.isf || null,
                    comment: dgiResponse.comment || dgiResponse.data?.comment || null
                }
            };

            console.log('[BillPayment] Appel API:', APP_URL + '/api/vente');
            console.log('[BillPayment] Payload:', JSON.stringify(ventePayload));

            const venteRes = await fetch(APP_URL + '/api/vente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(ventePayload)
            });

            // Vérifier si la réponse est du HTML (erreur PHP)
            const text = await venteRes.text();
            if (text.startsWith('<')) {
                console.error('[BillPayment] Réponse HTML au lieu de JSON:', text.substring(0, 200));
                throw new Error('Erreur serveur: réponse HTML au lieu de JSON');
            }

            const venteData = JSON.parse(text);

            if (!venteData.success) {
                throw new Error(venteData.error || 'Erreur sauvegarde');
            }

            this.hideLoading();
            this.closePreview();

            // Étape 3: Afficher la facture finale avec QR code
            this.generateTicket({
                numero_facture: venteData.numero_facture,
                type_facture: ventePayload.type_facture
            }, dgiResponse);

            this.reset();
            return { success: true, data: venteData, dgi: dgiResponse };

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
            console.error('[BillPayment] Erreur payment:', error);
            return { success: false, error: error.message };
        }
    }

    // ==========================================
    // DGI VALIDATION - Valider avec l'API DGI (identique à app.js)
    // ==========================================

    async validateWithDGI() {
        try {
            const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';
            const sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;

            // Récupérer le numéro de facture
            let invoiceNum = this.currentInvoiceNum;
            if (!invoiceNum) {
                try {
                    const invRes = await fetch(APP_URL + '/api/vente/next-invoice');
                    const invData = await invRes.json();
                    invoiceNum = invData.invoice_number || 'FAC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
                    this.currentInvoiceNum = invoiceNum;
                } catch (e) {
                    invoiceNum = 'FAC-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
                }
            }

            // Infos client
            const clientNom = document.getElementById('client-nom')?.value || this.clientInfo?.nom || 'Client';
            const clientNumero = document.getElementById('invoice-number')?.value || '';
            const invoiceType = document.getElementById('invoice-type')?.value || 'FV';
            const invoiceRef = document.getElementById('invoice-ref')?.value || '';

            // Construire les articles (mois sélectionnés) pour la DGI
            const articles = this.selectedMonths.map((month, idx) => ({
                name: `${this.moisNoms[month.mois]} ${month.annee} (${service})`,
                quantity: 1,
                year: month.annee,
                month: month.mois,
                price: month.montant,
                tax_rate: month.tva,
                tax_etiquette: 'B'
            }));

            const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);

            const payload = {

                store_name: STORE_INFO.name,
                store_phone: STORE_INFO.phone,
                store_address: STORE_INFO.address,
                store_ice: STORE_INFO.ice,
                store_isf: STORE_INFO.isf || '',
                seller_name: sellerName,
                amount: total,
                client_number: this.apiResponse.client_numero,
                client_name: this.apiResponse.client_nom,
                client_type: "PP",
                client_nif: '',
                invoice_number: invoiceNum,
                invoice_type: invoiceType,
                invoice_ref: invoiceRef,
                articles: articles,
                providerService: service,
                deviceId: this.apiResponse.deviceid
            };

            console.log('[DGI] Payload:', JSON.stringify(payload, null, 2));

            const res = await fetch(DGI_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const text = await res.text();
            console.log('[DGI] Response status:', res.status);
            console.log('[DGI] Response text:', text.substring(0, 500));

            if (!res.ok) {
                console.warn('[DGI] Réponse non OK:', res.status, res.statusText);
                return { success: false, message: 'Erreur serveur DGI: ' + res.status };
            }

            if (!text || text.trim() === '') {
                console.warn('[DGI] Réponse vide du serveur');
                return { success: false, message: 'Réponse vide du serveur DGI' };
            }

            try {
                const parsed = JSON.parse(text);
                // Si DGI retourne success: false avec une erreur
                if (parsed.success === false) {
                    console.warn('[DGI] Erreur DGI:', parsed.message);
                    return { success: false, message: parsed.message || 'Erreur DGI' };
                }
                return parsed;
            } catch (jsonErr) {
                console.warn('[DGI] Réponse non-JSON:', text.substring(0, 200));
                return { success: false, message: 'Réponse invalide du serveur DGI' };
            }
        } catch (e) {
            console.error('Erreur appel DGI:', e);
            return { success: false, message: 'Erreur de connexion DGI: ' + e.message };
        }
    }

    // ==========================================
    // API CALLS
    // ==========================================

    async callProviderAPI(providerCode, numeroCompteur) {
        // Appel API via proxy backend POST (évite CORS)
        try {
            const response = await fetch('/api/bill-payment', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    compteur: numeroCompteur,
                    service: providerCode
                })
            });

            if (!response.ok) {
                throw new Error('Erreur API: ' + response.status);
            }

            const result = await response.json();
            console.log('[BillPayment] Données API:', JSON.stringify(result).substring(0, 1000));

            if (!result.success) {
                throw new Error(result.message || 'Erreur API');
            }

            const data = result.data;

            // Parser les données reçues (nouvelle structure avec user)
            const user = data.user || {};
            const results = data.results || {};

            return {
                success: data.success !== false,
                deviceid: data.deviceid || user.DEVICEID || numeroCompteur,
                client_nom: user.NOM_POST_NOM || user.nom || data.client_nom || '',
                client_numero: user.NUMERO || '',
                client_email: user.EMAIL || '',
                client_adresse: user.COMMUNE || user.adresse || user.COMMUNE || '',
                client_province: user.province_users || '',
                client_commune: user.COMMUNE || '',
                client_category: user.CATEGORY || '',
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
        // Stocker pour usage ultérieur
        this.clientInfo = client;

        const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';
        const numeroCompteur = document.getElementById('invoice-number')?.value || '';

        // Mettre à jour l'affichage du modal avec les nouvelles données
        const nomDisplay = document.getElementById('modal-client-nom-display');
        const postnomDisplay = document.getElementById('modal-client-postnom-display');
        const prenomDisplay = document.getElementById('modal-client-prenom-display');
        const telDisplay = document.getElementById('modal-client-tel-display');
        const compteurDisplay = document.getElementById('modal-client-compteur-display');
        const serviceDisplay = document.getElementById('modal-client-service-display');

        if (nomDisplay) nomDisplay.textContent = client.nom || '-';
        if (postnomDisplay) postnomDisplay.textContent = client.commune || client.adresse || '-';
        if (prenomDisplay) prenomDisplay.textContent = client.province || '-';
        if (telDisplay) telDisplay.textContent = client.numero || '0000';
        if (compteurDisplay) compteurDisplay.textContent = numeroCompteur || '-';
        if (serviceDisplay) serviceDisplay.textContent = service;

        // Remplir les champs du modal
        const nomEl = document.getElementById('modal-client-nom');
        const postnomEl = document.getElementById('modal-client-postnom');
        const prenomEl = document.getElementById('modal-client-prenom');
        const telEl = document.getElementById('modal-client-tel');

        if (nomEl) nomEl.value = client.nom || '';
        if (postnomEl) postnomEl.value = client.commune || client.adresse || '';
        if (prenomEl) prenomEl.value = client.province || '';
        if (telEl) telEl.value = client.numero || '0000';

        // Remplir aussi les champs cachés
        const hiddenNom = document.getElementById('client-nom');
        const hiddenPostnom = document.getElementById('client-postnom');
        const hiddenPrenom = document.getElementById('client-prenom');
        const hiddenTel = document.getElementById('client-tel');

        if (hiddenNom) hiddenNom.value = client.nom || '';
        if (hiddenPostnom) hiddenPostnom.value = client.commune || client.adresse || '';
        if (hiddenPrenom) hiddenPrenom.value = client.province || '';
        if (hiddenTel) hiddenTel.value = client.numero || '0000';
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

    async showPreview() {
        if (this.selectedMonths.length === 0) return;

        // Récupérer le numéro de facture depuis la base de données
        let invoiceNum = 'FAC-000001';
        try {
            const res = await fetch(APP_URL + '/api/vente/next-invoice');
            const data = await res.json();
            if (data.invoice_number) {
                invoiceNum = data.invoice_number;
                this.currentInvoiceNum = invoiceNum; // Stocker pour DGI
            }
        } catch (e) {
            console.warn('Erreur récupération numéro facture:', e);
        }

        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
        const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';
        const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;

        // Récupérer les infos client
        const clientNom = document.getElementById('client-nom')?.value || this.clientInfo?.nom || '';
        const clientNumero = document.getElementById('invoice-number')?.value || '';

        // Infos RCCM et ISF
        let storeExtraInfo = '';
        if (STORE_INFO.rccm) {
            storeExtraInfo += `<div>RCCM: ${STORE_INFO.rccm}</div>`;
        }
        if (STORE_INFO.isf) {
            storeExtraInfo += `<div>Numero Impot: ${STORE_INFO.isf}</div>`;
        }

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

        // Section infos (Vendeur + Client)
        let infoSection = `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 11px; line-height: 1.5;">
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Vendeur:</strong></span><span>${vendeur}</span></div>
                           ${clientNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Client:</strong></span><span>${clientNom}</span></div>` : ''}
                           ${clientNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° Compteur:</strong></span><span>${clientNumero}</span></div>` : ''}
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Service:</strong></span><span>${service}</span></div>
                           ${STORE_INFO.isf ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>ISF:</strong></span><span>${STORE_INFO.isf}</span></div>` : ''}
                        </div>`;

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div>${STORE_INFO.address}</div>
                        <div>Tel: ${STORE_INFO.phone}</div>
                        <div>ID Nat: ${STORE_INFO.ice}</div>
                        ${storeExtraInfo}
                    </div>
                    ${infoSection}
                </div>
                <div class="receipt-meta">
                    <span>${invoiceNum}</span>
                    <span>${document.getElementById('invoice-type')?.value || 'FV'}</span>
                </div>
                <div class="receipt-items receipt-items-grid">
                    ${itemsHtml}
                </div>
                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Sous-total:</span>
                        <span>${this.formatMoney(total)} Fc</span>
                    </div>
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.formatMoney(total)} Fc</span>
                    </div>
                </div>
                <div class="receipt-footer">
                    <div class="barcode">${invoiceNum}</div>
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

            const response = await fetch('/api/bill-payment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'process', ...payload })
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

    generateTicket(data, dgiResponse) {
        console.log('[BillPayment] Ticket généré avec DGI:', data, dgiResponse);

        const service = this.currentProvider === 1 ? 'SNEL' : 'REGIDESO';
        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
        const invoiceNum = data.numero_transaction || 'RECHARGE-' + Date.now();

        // Construire items
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

        // Récupérer client info
        const clientNom = document.getElementById('client-nom')?.value || '';
        const clientNumero = document.getElementById('invoice-number')?.value || '';
        const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;

        // Infos DGI
        let dgiInfoHtml = '';
        if (dgiResponse?.data) {
            dgiInfoHtml = `<div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;">
                <div style="color: #2e7d32; font-weight: bold; font-size: 11px;">--- Elements de securite ---</div>
                <div style="font-size: 12px; color: #555; margin-top: 4px;">
                    ${dgiResponse.data.codeDEFDGI ? 'CODE DEF/DGI: ' + dgiResponse.data.codeDEFDGI : ''}
                    ${dgiResponse.data.nim ? '<br> DEF NID : ' + dgiResponse.data.nim : ''}
                    ${dgiResponse.data.counters ? '<br> DEF Compteurs: ' + dgiResponse.data.counters : ''}
                    ${dgiResponse.data.dateDGI ? '<br> DEF Heure : ' + dgiResponse.data.dateDGI : ''}
                    <br> ISF : ${STORE_INFO.isf || '0'}
                </div>
            </div>`;
        }

        const qrContainerId = 'dgi-qrcode-container';

        // Afficher le modal ticket
        $('#receipt-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div>${STORE_INFO.address}</div>
                        <div>Tel: ${STORE_INFO.phone}</div>
                        <div>ID Nat: ${STORE_INFO.ice}</div>
                        ${STORE_INFO.isf ? `<div>Numero Impot: ${STORE_INFO.isf}</div>` : ''}
                    </div>
                    <div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 15px; line-height: 1.5;">
                        <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Vendeur:</strong></span><span>${vendeur}</span></div>
                        ${clientNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Client:</strong></span><span>${clientNom}</span></div>` : ''}
                        ${clientNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° Compteur:</strong></span><span>${clientNumero}</span></div>` : ''}
                        <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>Service:</strong></span><span>${service}</span></div>
                    </div>
                </div>
                <div class="receipt-meta">
                    <span>${invoiceNum}</span>
                    <span>${document.getElementById('invoice-type')?.value || 'FV'}</span>
                </div>
                <div class="receipt-items receipt-items-grid">
                    ${itemsHtml}
                </div>
                <div class="receipt-totals">
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.formatMoney(total)} Fc</span>
                    </div>
                    <div style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: left;">
                        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Commentaire:</div>
                        <div>${dgiResponse?.comment || dgiResponse?.data?.comment || 'Paiement facture ' + service}</div>
                    </div>
                </div>
                ${dgiInfoHtml}
                <div class="receipt-footer">
                    <div id="${qrContainerId}" class="qrcode-container"></div>
                    <div class="barcode">${invoiceNum}</div>
                    <div class="thank-you">Paiement facture ${service}</div>
                    <div style="margin-top: 5px; font-size: 9px; font-style: italic;">---Powered By Osat---</div>
                </div>
            </div>
        `;

        // Générer QR code
        const qrCodeContent = dgiResponse?.data?.qrCode || invoiceNum;
        this.generateDGIQRCode(qrCodeContent, qrContainerId);

        $('#receipt-modal').classList.add('active');
    }

    async generateDGIQRCode(qrCodeContent, containerId) {
        if (typeof QRCodeStyling === 'undefined') {
            await this.loadQRCodeLibrary();
        }
        try {
            const qrCode = new QRCodeStyling({
                width: 180,
                height: 180,
                type: "svg",
                data: qrCodeContent,
                margin: 10,
                dotsOptions: { color: "#000000", type: "rounded" },
                cornersSquareOptions: { color: "#000000", type: "extra-rounded" },
                backgroundOptions: { color: "#ffffff" }
            });
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = '';
                qrCode.append(container);
            }
        } catch (e) {
            console.error('Erreur generation QR:', e);
        }
    }

    loadQRCodeLibrary() {
        return new Promise((resolve, reject) => {
            if (typeof QRCodeStyling !== 'undefined') {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
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
