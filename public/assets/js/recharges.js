/**
 * Recharges.js - Paiement Factures Electricité/Eau
 * Flow: Fetch API REGIDESO → Mois → Panier → Paiement
 * 
 * Structure API: results[annee][mois] = [{ MONTANT, NUMERO_FACTURE, ... }]
 */

const RECHARGE_PHONE_NUMBER_REGEX = /^0[89]\d{8}$/;

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

        // Types de factures
        this.invoiceTypes = {
            'FV': 'Facture de Vente',
            'EV': "Fac de Vente à l'exportation",
            'FT': "Facture d'acompte",
            'FA': "Facture d'avoir",
            'EA': "Fac d'avoir à l'exportation",
            'ET': "Fac d'acompte à l'exportation",
        };

        console.log('[BillPayment] Initialisé, session:', this.sessionId);
    }

    // Obtenir le label complet du type de facture
    getInvoiceTypeLabel(code) {
        return this.invoiceTypes[code] || code || 'Facture de Vente';
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

        const providerId = providerCode === 'ELECTRICITE' ? 1 : 2;
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

        // Vérifier que le type de client est sélectionné
        const clientTypeSelect = document.getElementById('client-type');
        const clientTypeValue = clientTypeSelect?.value;
        if (!clientTypeValue || clientTypeValue === '') {
            this.showError('Veuillez sélectionner le type de client');
            return { success: false, error: 'Type client non sélectionné' };
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

            console.log(this.currentProvider)

            const service = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';
            const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);

            // Logique de négation: si la facture N'est PAS FA ou EA, on envoie
            // les quantités et le total en négatif à la DGI et au backend
            const rechargeTypeFacture = document.getElementById('invoice-type')?.value || 'FV';
            const rechargeShouldNegate = rechargeTypeFacture === 'FA' || rechargeTypeFacture === 'EA';
            const rechargeSign = rechargeShouldNegate ? -1 : 1;

            // Étape 2: Sauvegarder dans la table ventes (comme pour les ventes normales)
            const ventePayload = {
                articles: this.selectedMonths.map(m => ({
                    produit_id: 0,
                    nom: `${this.moisNoms[m.mois]} ${m.annee} (${service})`,
                    prix: m.montant,
                    quantite: 1 * rechargeSign,
                    tax_rate: 0,
                    tax_etiquette: 'B'
                })),
                client_id: this.clientInfo?.id || null,
                sous_total_ht: total * rechargeSign,
                tva: 0,
                total: total * rechargeSign,
                type_facture: rechargeTypeFacture,
                providerService: service,
                payments: this.currentPayments?.length > 0 ? this.currentPayments : [{ type: (document.getElementById('modal-payment-type') || document.getElementById('payment-type'))?.value || 'cash', amount: total }],
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

            // Sauvegarder le numéro de compteur et service avant reset
            const savedCompteur = document.getElementById('invoice-number')?.value;
            const savedService = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';

            this.reset();

            // Recharger les données depuis l'API pour mettre à jour l'état des mois
            if (savedCompteur) {
                setTimeout(() => {
                    this.fetchBillInquiry(savedService, savedCompteur);
                }, 500);
            }

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
            const service = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';
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

            // Infos client depuis le modal (pré-rempli)
            const clientNom = document.getElementById('client-nom')?.value || this.clientInfo?.nom || 'Client';
            const clientNumero = document.getElementById('invoice-number')?.value || '';
            const invoiceType = document.getElementById('invoice-type')?.value || 'FV';
            const invoiceRef = document.getElementById('invoice-ref')?.value || '';

            // Construire les articles (mois sélectionnés) pour la DGI
            // Si la facture N'est PAS FA ou EA, on envoie les quantités et
            // le total en négatif à l'API DGI
            const dgiShouldNegate = false;
            const dgiSign = dgiShouldNegate ? -1 : 1;

            const articles = this.selectedMonths.map((month, idx) => ({
                name: `${this.moisNoms[month.mois]} ${month.annee} (${service})`,
                quantity: 1 * dgiSign,
                year: month.annee,
                month: month.mois,
                price: month.montant,
                tax_rate: month.tva,
                tax_etiquette: 'B',
                numero_facture: month.numero_facture
            }));

            const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);

            // Récupérer les infos client pour DGI depuis le modal
            const clientTypeSelect = document.getElementById('client-type');
            const clientType = clientTypeSelect?.options?.[clientTypeSelect.selectedIndex]?.text || 'PP';
            const clientNif = document.getElementById('client-nif')?.value || this.apiResponse?.client_nif || '';
            const clientNumeroDoc = document.getElementById('invoice-ref')?.value || '';

            // Récupérer client_number depuis le champ du panier
            const clientNumberInput = document.getElementById('client-numero');
            const clientNumber = clientNumberInput?.value;

            // Récupérer téléphone: d'abord depuis le modal, sinon depuis l'API
            const modalClientTel = document.getElementById('modal-client-tel1')?.value;
            const refFacture = document.getElementById('modal-invoice-num')?.value || '';
            const exoneration = document.getElementById('modal-exoneration')?.value || '';
            const payments = this.currentPayments?.length > 0 ? this.currentPayments : [{ type: (document.getElementById('modal-payment-type') || document.getElementById('payment-type'))?.value || 'cash', amount: total }];
            const paymentType = payments[0]?.type || 'cash';

            const clientTel = (modalClientTel && modalClientTel !== '0000') ? modalClientTel : (this.clientInfo?.numero || this.apiResponse?.client_numero || '');
            const clientCommune = this.clientInfo?.commune || this.apiResponse?.client_commune || '';
            const clientAddress = document.getElementById('client-address')?.value || this.clientInfo?.adresse || this.apiResponse?.client_adresse || '';

            const payload = {

                store_phone: STORE_INFO.phone,
                store_address: STORE_INFO.address,
                store_email: STORE_INFO.email,
                store_ice: STORE_INFO.ice,
                store_isf: STORE_INFO.isf || '',
                store_rccm: STORE_INFO.rccm,
                seller_name: sellerName,
                seller_agent_code: (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '',
                store_name: STORE_INFO.name,
                amount: total * dgiSign,
                client_number: clientTel,

                client_commune: clientCommune,
                invoice_number: invoiceNum,
                invoice_type: invoiceType,
                invoice_ref: invoiceRef,

                articles: articles,
                providerService: service,
                deviceId: this.apiResponse?.deviceid,

                //
                ref_facture: refFacture,
                exoneration: exoneration,
               // payment_type: paymentType,
                payments: payments,
                rate: (typeof USD_RATE !== 'undefined') ? USD_RATE : 0,
                client_name: clientNom,
                client_type: clientType,
                client_nif: clientNif,
                client_address: clientAddress,
                //client_document: clientNumeroDoc,
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
                client_nif: user.NIF || '',
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

        const service = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';
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
            const numero_facture = m.numero_facture

            const serviceIcon = this.currentProvider === 1 ? '⚡' : '💧';
            html += `
                <div class="month-card ${isImpayé ? 'unpaid' : 'paid'} ${isSelected ? 'selected' : ''}" 
                     data-id="${m.id}"
                     onclick="billPayment.toggleMonth('${m.id}')">
                    <div class="month-header">
                        <span class="month-status ${isImpayé ? 'unpaid' : 'paid'}">
                            ${isImpayé ? serviceIcon + ' Impayé' : '✓ Réglé'}
                        </span>
                    </div>
                    <div class="month-name">${monthName}</div>
                    <div class="month-year">${m.annee}</div>
                    <div class="month-amount">${isImpayé ? this.formatMoney(m.montant) + ' Fc' : '0.00 Fc'}</div>
                    <div class="month-invoice-number">
                        <span class="invoice-value">${numero_facture}</span>
                    </div>
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

        // Au lieu d'ouvrir directement le preview, ouvrir le modal client avec infos pré-remplies
        this.openInvoiceInfoModalRecharge();
    }

    // Ouvrir le modal d'informations client avec données pré-remplies depuis l'API
    openInvoiceInfoModalRecharge() {
        const modal = document.getElementById('invoice-info-modal');
        if (!modal) {
            console.error('[BillPayment] Modal invoice-info-modal non trouvé');
            return;
        }

        // Pré-remplir les champs avec les données du client depuis l'API
        const clientNom = this.clientInfo?.nom || this.apiResponse?.client_nom || '';
        const clientNif = this.apiResponse?.client_nif || '';
        const clientNumero = this.clientInfo?.numero || this.apiResponse?.client_numero || '';
        const clientCommune = this.clientInfo?.commune || this.apiResponse?.client_commune || '';
        const clientAddress = this.clientInfo?.adresse || this.apiResponse?.client_adresse || document.getElementById('client-address')?.value || '';

        // Récupérer les valeurs existantes du panier
        const invoiceType = document.getElementById('invoice-type')?.value || 'FV';
        const invoiceRef = document.getElementById('invoice-ref')?.value || '';
        const clientType = document.getElementById('client-type')?.value || '';

        // Remplir le modal
        const isAdmin = typeof CURRENT_USER !== 'undefined' && CURRENT_USER.role === 'admin';
        if (isAdmin) {
            document.getElementById('modal-invoice-type').value = invoiceType;
            document.getElementById('modal-invoice-ref').value = invoiceRef;
        }
        document.getElementById('modal-client-name').value = clientNom;
        document.getElementById('modal-client-tel').value = clientNumero;
        document.getElementById('modal-client-nif').value = clientNif;
        document.getElementById('modal-client-address').value = clientAddress;

        // Sélectionner le type de client si disponible
        if (clientType) {
            document.getElementById('modal-client-type').value = clientType;
        }

        // Initialiser la liste des modes de paiement
        this.initModalPaymentsRecharge();

        // Initialiser les références documents (max 8)
        this.initModalRefDocsRecharge();
        const extraRefs = (this.currentRefDocs || []).slice(1);
        extraRefs.forEach(ref => this.addModalRefDocLineRecharge(ref));

        // Afficher le modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Fermer le modal client
    closeInvoiceInfoModalRecharge() {
        const modal = document.getElementById('invoice-info-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // ==================== MODAL MULTI-PAIEMENT ====================

    highlightModalField(fieldId) {
        const el = document.getElementById(fieldId);
        if (!el) return;
        const originalBoxShadow = el.style.boxShadow;
        const originalBorderColor = el.style.borderColor;
        el.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.5)';
        el.style.borderColor = '#dc2626';
        const clearHighlight = () => {
            el.style.boxShadow = originalBoxShadow;
            el.style.borderColor = originalBorderColor;
            el.removeEventListener('input', clearHighlight);
            el.removeEventListener('change', clearHighlight);
            el.removeEventListener('focus', clearHighlight);
        };
        el.addEventListener('input', clearHighlight, { once: true });
        el.addEventListener('change', clearHighlight, { once: true });
        el.addEventListener('focus', clearHighlight, { once: true });
        el.focus();
    }

    getPaymentTypeLabel(type) {
        const labels = {
            ESPECES: 'Espèces',
            MOBILEMONEY: 'Mobile Money',
            CARTEBANCAIRE: 'Carte Bancaire',
            VIREMENT: 'Virement',
            CREDIT: 'Crédit',
            CHEQUES: 'Chèques',
            AUTRE: 'Autres'
        };
        return labels[type] || type;
    }

    getCartTotal() {
        return this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
    }

    initModalPaymentsRecharge() {
        const list = document.getElementById('modal-payments-list');
        if (!list) return;
        list.innerHTML = '';
        this.addModalPaymentLineRecharge('ESPECES', this.getCartTotal());
        this.updateAddPaymentButtonRecharge();
    }

    addModalPaymentLineRecharge(type = 'ESPECES', amount = 0) {
        const list = document.getElementById('modal-payments-list');
        if (!list) return;
        const maxLines = 6;
        if (list.children.length >= maxLines) return;
        const line = document.createElement('div');
        line.className = 'modal-payment-line';
        line.style.cssText = 'display: grid; grid-template-columns: 1fr 100px 28px; gap: 8px; align-items: end;';
        line.innerHTML = `
            <div>
                <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Type</label>
                <select class="modal-payment-type client-number-input" style="width: 100%; background: #fff;" onchange="billPayment.calculateModalPaymentsRecharge()">
                    <option value="ESPECES" ${type === 'ESPECES' ? 'selected' : ''}>Espèces</option>
                    <option value="MOBILEMONEY" ${type === 'MOBILEMONEY' ? 'selected' : ''}>Mobile Money</option>
                    <option value="CARTEBANCAIRE" ${type === 'CARTEBANCAIRE' ? 'selected' : ''}>Carte Bancaire</option>
                    <option value="VIREMENT" ${type === 'VIREMENT' ? 'selected' : ''}>Virement</option>
                    <option value="CREDIT" ${type === 'CREDIT' ? 'selected' : ''}>Crédit</option>
                    <option value="CHEQUES" ${type === 'CHEQUES' ? 'selected' : ''}>Chèques</option>
                    <option value="AUTRE" ${type === 'AUTRE' ? 'selected' : ''}>Autres</option>
                </select>
            </div>
            <div>
                <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Montant</label>
                <input type="number" class="modal-payment-amount client-number-input" step="0.01" min="0" placeholder="0.00" value="${amount > 0 ? amount.toFixed(2) : ''}" style="width: 100%;" oninput="billPayment.calculateModalPaymentsRecharge()">
            </div>
            <button type="button" onclick="removeModalPaymentLineRecharge(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>
        `;
        list.appendChild(line);
        this.updateModalPaymentRemoveButtonsRecharge();
        this.updateAddPaymentButtonRecharge();
        this.calculateModalPaymentsRecharge();
    }

    updateAddPaymentButtonRecharge() {
        const list = document.getElementById('modal-payments-list');
        const btn = document.getElementById('add-payment-line-btn');
        if (!list || !btn) return;
        const maxLines = 6;
        btn.style.display = list.children.length >= maxLines ? 'none' : 'flex';
    }

    removeModalPaymentLineRecharge(btn) {
        const line = btn.closest('.modal-payment-line');
        if (line) line.remove();
        this.updateModalPaymentRemoveButtonsRecharge();
        this.updateAddPaymentButtonRecharge();
        this.calculateModalPaymentsRecharge();
    }

    updateModalPaymentRemoveButtonsRecharge() {
        const lines = document.querySelectorAll('.modal-payment-line');
        lines.forEach(line => {
            const btn = line.querySelector('button[onclick^="removeModalPaymentLineRecharge"]');
            if (btn) btn.style.display = lines.length > 1 ? 'flex' : 'none';
        });
    }

    calculateModalPaymentsRecharge() {
        const lines = document.querySelectorAll('.modal-payment-line');
        let total = 0;
        const payments = [];
        lines.forEach(line => {
            const type = line.querySelector('.modal-payment-type')?.value || 'cash';
            const amount = parseFloat(line.querySelector('.modal-payment-amount')?.value) || 0;
            total += amount;
            payments.push({ type, amount });
        });
        const cartTotal = this.getCartTotal();
        const diff = total - cartTotal;
        const summary = document.getElementById('modal-payment-summary');
        const summaryLabel = document.getElementById('modal-payment-summary-label');
        const summaryAmount = document.getElementById('modal-payment-summary-amount');
        if (summary && summaryLabel && summaryAmount) {
            if (Math.abs(diff) < 0.001) {
                summary.style.display = 'none';
            } else if (diff < 0) {
                summary.style.display = 'block';
                summaryLabel.textContent = 'Reste à payer:';
                summaryLabel.style.color = '#c62828';
                summaryAmount.textContent = Math.abs(diff).toFixed(2) + ' Fc';
                summaryAmount.style.color = '#c62828';
            } else {
                summary.style.display = 'block';
                summaryLabel.textContent = 'Monnaie à rendre:';
                summaryLabel.style.color = '#166534';
                summaryAmount.textContent = diff.toFixed(2) + ' Fc';
                summaryAmount.style.color = '#0B5E88';
            }
        }
        const hiddenType = document.getElementById('modal-payment-type');
        if (hiddenType && payments.length > 0) hiddenType.value = payments[0].type;
        return payments;
    }

    getModalPaymentsRecharge() {
        const lines = document.querySelectorAll('.modal-payment-line');
        const payments = [];
        lines.forEach(line => {
            const type = line.querySelector('.modal-payment-type')?.value || 'cash';
            const amount = parseFloat(line.querySelector('.modal-payment-amount')?.value) || 0;
            if (amount > 0) payments.push({ type, amount });
        });
        return payments;
    }

    // ==================== MODAL MULTI-RÉFÉRENCES DOCUMENTS ====================

    initModalRefDocsRecharge() {
        const list = document.getElementById('modal-ref-docs-list');
        if (!list) return;
        list.innerHTML = '';
        this.updateAddRefDocButtonRecharge();
    }

    addModalRefDocLineRecharge(value = '') {
        const list = document.getElementById('modal-ref-docs-list');
        if (!list) return;
        const maxLines = 8;
        const mainInput = document.getElementById('modal-invoice-ref');
        const totalLines = (mainInput ? 1 : 0) + list.children.length;
        if (totalLines >= maxLines) return;

        const line = document.createElement('div');
        line.className = 'modal-ref-doc-line';
        line.style.cssText = 'display: flex; gap: 8px; align-items: center;';
        line.innerHTML = `
            <input type="text" class="modal-ref-doc-input client-number-input" placeholder="Réf..." value="${value}" style="width: 100%;" oninput="billPayment.syncRefDocsCountRecharge()">
            <button type="button" onclick="billPayment.removeModalRefDocLineRecharge(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>
        `;
        list.appendChild(line);
        this.updateAddRefDocButtonRecharge();
        this.syncRefDocsCountRecharge();
    }

    removeModalRefDocLineRecharge(btn) {
        const line = btn.closest('.modal-ref-doc-line');
        if (line) line.remove();
        this.updateAddRefDocButtonRecharge();
        this.syncRefDocsCountRecharge();
    }

    updateAddRefDocButtonRecharge() {
        const list = document.getElementById('modal-ref-docs-list');
        const btn = document.getElementById('add-ref-doc-btn');
        const mainInput = document.getElementById('modal-invoice-ref');
        if (!list || !btn) return;
        const maxLines = 8;
        const totalLines = (mainInput ? 1 : 0) + list.children.length;
        btn.style.display = totalLines >= maxLines ? 'none' : 'flex';
    }

    syncRefDocsCountRecharge() {
        const list = document.getElementById('modal-ref-docs-list');
        const countInput = document.getElementById('modal-ref-docs-count');
        const mainInput = document.getElementById('modal-invoice-ref');
        if (countInput) {
            const totalLines = (mainInput ? 1 : 0) + (list ? list.children.length : 0);
            countInput.value = totalLines;
        }
    }

    getModalRefDocsRecharge() {
        const refs = [];
        const mainInput = document.getElementById('modal-invoice-ref');
        if (mainInput && mainInput.value.trim()) refs.push(mainInput.value.trim());
        const list = document.getElementById('modal-ref-docs-list');
        if (list) {
            list.querySelectorAll('.modal-ref-doc-input').forEach(input => {
                if (input.value.trim()) refs.push(input.value.trim());
            });
        }
        return refs;
    }

    // Confirmer les infos client et ouvrir le preview
    async confirmInvoiceInfoRecharge() {
        // Sauvegarder les valeurs du modal vers les champs du panier
        const isAdmin = typeof CURRENT_USER !== 'undefined' && CURRENT_USER.role === 'admin';
        const modalInvoiceType = isAdmin ? document.getElementById('modal-invoice-type')?.value || 'FV' : 'FV';
        const modalClientName = document.getElementById('modal-client-name')?.value || '';
        const modalClientNumber = document.getElementById('modal-client-tel1')?.value?.trim() || '';
        const modalClientType = document.getElementById('modal-client-type')?.value || '';
        const modalClientNif = document.getElementById('modal-client-nif')?.value || '';
        const modalClientAddress = document.getElementById('modal-client-address')?.value || '';

        if (modalClientNumber && !RECHARGE_PHONE_NUMBER_REGEX.test(modalClientNumber)) {
            alert('Le numéro de téléphone doit respecter le format 08xxxxxxxx ou 09xxxxxxxx.');
            this.highlightModalField('modal-client-tel1');
            return;
        }

        // Extraire le code client depuis le texte de l'option sélectionnée (ex: "PP - Particulier")
        const clientTypeSelect = document.getElementById('modal-client-type');
        const modalClientTypeText = clientTypeSelect?.options?.[clientTypeSelect.selectedIndex]?.textContent || '';
        const modalClientTypeCode = modalClientTypeText.split(' - ')[0].trim() || '';

        // Validation selon le type de client avant d'ouvrir la preview
        // PM, PL, PC, AO : adresse obligatoire
        if (['PM', 'PL', 'PC', 'AO'].includes(modalClientTypeCode)) {
            if (!modalClientAddress.trim()) {
                alert("L'adresse est obligatoire pour ce type de client.");
                this.highlightModalField('modal-client-address');
                return;
            }
        }

        // Le NIF est obligatoire pour tous les types de client sauf PP (Particulier)
        if (modalClientTypeCode !== 'PP' && !modalClientNif.trim()) {
            alert("Le NIF est obligatoire pour ce type de client.");
            this.highlightModalField('modal-client-nif');
            return;
        }

        // Récupérer les références documents (jusqu'à 8)
        const modalInvoiceRefs = isAdmin ? this.getModalRefDocsRecharge() : [];
        const modalInvoiceRef = modalInvoiceRefs[0] || '';

        // AO : référence document obligatoire
        if (modalClientTypeCode === 'AO') {
            if (modalInvoiceRefs.length === 0 || modalInvoiceRefs.every(r => !r.trim())) {
                alert("La référence document est obligatoire pour les ambassades et organisations internationales.");
                this.highlightModalField('modal-invoice-ref');
                return;
            }
        }

        // Récupérer et valider les paiements
        const payments = this.getModalPaymentsRecharge();
        const cartTotal = this.getCartTotal();
        const paymentsTotal = payments.reduce((sum, p) => sum + p.amount, 0);
        if (payments.length === 0 || paymentsTotal < cartTotal - 0.001) {
            alert('Le montant total des paiements est insuffisant par rapport au total de la facture.');
            const firstAmount = document.querySelector('#modal-payments-list .modal-payment-amount');
            this.highlightModalField(firstAmount?.id || 'modal-payments-list');
            return;
        }

        // Mettre à jour les champs cachés du panier
        document.getElementById('invoice-type').value = modalInvoiceType;
        document.getElementById('invoice-ref').value = modalInvoiceRef;
        document.getElementById('client-nom').value = modalClientName;
        document.getElementById('client-type').value = modalClientType;
        document.getElementById('client-nif').value = modalClientNif;
        document.getElementById('client-address').value = modalClientAddress;
        document.getElementById('client-numero').value = modalClientNumber;

        // Mémoriser les paiements, les références documents et l'adresse sur le client courant
        this.currentPayments = payments;
        this.currentRefDocs = modalInvoiceRefs;
        if (this.clientInfo) {
            this.clientInfo.adresse = modalClientAddress;
        }

        // Fermer le modal client
        this.closeInvoiceInfoModalRecharge();

        // Ouvrir le preview
        await this.showPreviewFinal();
    }

    async saveClientFromModalRecharge() {
        const nom = document.getElementById('modal-client-name')?.value?.trim();
        const numero = document.getElementById('modal-client-tel1')?.value?.trim() || document.getElementById('invoice-number')?.value?.trim();
        const typeId = document.getElementById('modal-client-type')?.value;
        const nif = document.getElementById('modal-client-nif')?.value?.trim();
        const adresse = document.getElementById('modal-client-address')?.value?.trim();

        if (!nom || !numero) {
            this.showError('Veuillez remplir le nom et le numéro');
            return;
        }

        if (!RECHARGE_PHONE_NUMBER_REGEX.test(numero)) {
            alert('Le numéro de téléphone doit respecter le format 08xxxxxxxx ou 09xxxxxxxx.');
            return;
        }

        try {
            const res = await fetch(APP_URL + '/api/client', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nom, numero, type_client_id: typeId, nif, adresse })
            });
            const data = await res.json();

            if (data.success && data.client) {
                this.clientInfo = {
                    ...this.clientInfo,
                    id: data.client.id,
                    nom: data.client.nom_client || nom,
                    numero: data.client.numero || numero,
                    nif: data.client.nif || nif,
                    adresse: data.client.adresse || adresse
                };
                const nomField = document.getElementById('client-nom');
                const numeroField = document.getElementById('client-numero');
                const typeField = document.getElementById('client-type');
                const nifField = document.getElementById('client-nif');
                const adresseField = document.getElementById('client-address');
                if (nomField) nomField.value = data.client.nom_client || nom;
                if (numeroField) numeroField.value = data.client.numero || numero;
                if (typeField) typeField.value = data.client.type_id || typeId || '';
                if (nifField) nifField.value = data.client.nif || nif || '';
                if (adresseField) adresseField.value = data.client.adresse || adresse || '';
                this.showToast('Client enregistré: ' + (data.client.nom_client || nom), 'success');
            } else {
                this.showError(data.message || 'Erreur lors de l\'enregistrement');
            }
        } catch (e) {
            this.showError('Erreur de connexion');
        }
    }

    // Générer et afficher le preview (après confirmation client)
    async showPreviewFinal() {
        // Récupérer le numéro de facture depuis la base de données
        let invoiceNum = 'FAC-000001';
        try {
            const res = await fetch(APP_URL + '/api/vente/next-invoice');
            const data = await res.json();
            if (data.invoice_number) {
                invoiceNum = data.invoice_number;
                this.currentInvoiceNum = invoiceNum;
            }
        } catch (e) {
            console.warn('Erreur récupération numéro facture:', e);
        }

        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
        const service = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';
        const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;
        const agentNumero = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.id) ? CURRENT_USER.agentCode : '';

        // Récupérer les infos client depuis les champs
        const clientNom = document.getElementById('client-nom')?.value || this.clientInfo?.nom || '';
        const clientNumero = document.getElementById('invoice-number')?.value || '';
        const clientTelNum = document.getElementById('modal-client-tel1')?.value
        console.log(clientTelNum)
        const clientType = document.getElementById('client-type')?.value || '';

        const clientNif = document.getElementById('modal-client-nif')?.value || '';
        const clientAddress = document.getElementById('client-address')?.value || this.clientInfo?.adresse || '';
        const invoiceType = document.getElementById('invoice-type')?.value || 'FV';

        // Logique de négation visuelle: si la facture N'est PAS FA ou EA, on
        // affiche les quantités et le total en négatif dans la preview
        const rechargePreviewShouldNegate = invoiceType === 'FA' || invoiceType === 'EA';
        const rechargePreviewSign = rechargePreviewShouldNegate ? -1 : 1;

        // Masquer les 4 derniers chiffres du numéro client
        const maskClientNumero = (num) => {
            if (!num || num.length < 6) return num || '';
            return num.substring(0, num.length - 4) + '****';
        };

        // Obtenir le label complet du type de client
        const getClientTypeLabel = (code) => {
            const types = {
                'PP': 'Personne Physique',
                'PM': 'Personne Morale',
                'PC': 'Personne Physique Commerçante',
                'PL': 'Profession Libérale',
                'AO': 'Ambassades et Organisations Internationales',
            };
            return types[code] || code || '';
        };

        // Infos RCCM et ISF
        let storeExtraInfo = '';
        if (STORE_INFO.rccm) {
            storeExtraInfo += `<div>RCCM: ${STORE_INFO.rccm}</div>`;
        }
        if (STORE_INFO.isf) {
            storeExtraInfo += `<div>Numero Impot: ${STORE_INFO.isf}</div>`;
        }

        // Construire les items du reçu avec tableau (style caisse)
        let itemsHtml = '<table class="receipt-table"><thead><tr><th>Article</th><th>Qté</th><th>HT</th></tr></thead><tbody>';
        this.selectedMonths.forEach(month => {
            const monthName = this.moisNoms[month.mois];
            itemsHtml += `
                <tr>
                    <td><span class="item-name">${monthName} ${month.annee}<span class="item-tax-badge">B</span>[SER]</span></td>
                    <td class="item-qty">${1 * rechargePreviewSign}</td>
                    <td class="item-total">${this.formatMoney(month.montant * rechargePreviewSign)} Fc</td>
                </tr>
            `;
        });
        itemsHtml += '</tbody></table>';



        // Section infos (Vendeur + Client) - style caisse
        let infoSection = `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 11px; line-height: 1.5;">
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>VENDEUR:</strong></span><span>${vendeur}</span></div>
                           ${agentNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>CODE AGENT:</strong></span><span>${agentNumero}</span></div>` : ''}
                           ${clientNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>CLIENT:</strong></span><span>${clientNom}</span></div>` : ''}
                           ${clientTelNum ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° CLIENT:</strong></span><span>${maskClientNumero(clientTelNum)}</span></div>` : ''}
                           ${clientAddress ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>ADRESSE:</strong></span><span>${clientAddress}</span></div>` : ''}
                           ${clientType ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>TYPE CLIENT:</strong></span><span>${getClientTypeLabel(clientType)}</span></div>` : ''}
                           ${clientNif ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>NIF:</strong></span><span>${clientNif}</span></div>` : ''}
                           ${clientNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° COMPTEUR:</strong></span><span>${clientNumero}</span></div>` : ''}
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>SERVICE:</strong></span><span>${service}</span></div>

                          </div>`;

        $('#preview-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div><strong>Point de vente :</strong> ${STORE_INFO.pdv}</div>
                        <div>Adresse : ${STORE_INFO.address}</div>
                        <div>Tel: ${STORE_INFO.phone}</div>

                        <div>ID Nat: ${STORE_INFO.ice}</div>
                        ${storeExtraInfo}
                    </div>
                    ${infoSection}
                </div>
        <div class="receipt-meta" style="justify-content: center; font-size: 14px; font-weight: 555;">
                    ${this.getInvoiceTypeLabel(invoiceType)}
                </div>
                <div class="receipt-items">
                    ${itemsHtml}
                </div>
        <div class="receipt-totals">
                    ${this.getTaxBreakdownHtml()}
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.formatMoney(total * rechargePreviewSign)} Fc</span>
                    </div>
                    ${this.getPaymentInfoHtml()}
                </div>
                <div class="receipt-footer">
                    <div class="thank-you">FACTURE n°${invoiceNum}</div>
                    <div class="thank-you">Paiement ${service}</div>
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
                    Valider la facture
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

        const service = this.currentProvider === 1 ? 'ELECTRICITE' : 'EAU';
        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);
        const invoiceNum = this.currentInvoiceNum;
        const invoiceType = document.getElementById('invoice-type')?.value || 'FV';

        // Logique de négation visuelle: si la facture N'est PAS FA ou EA, on
        // affiche les quantités et le total en négatif dans la facture finale
        const ticketShouldNegate = invoiceType === 'FA' || invoiceType === 'EA';
        const ticketSign = ticketShouldNegate ? -1 : 1;

        // Construire items avec tableau (style caisse)
        let itemsHtml = '<table class="receipt-table"><thead><tr><th>Article</th><th>Qté x Prix unitaire</th><th>Total HT</th></tr></thead><tbody>';
        this.selectedMonths.forEach(month => {
            const monthName = this.moisNoms[month.mois];
            itemsHtml += `
                <tr>
                    <td><span class="item-name">${monthName} ${month.annee}<span class="item-tax-badge">B</span>[SER]</span></td>
                    <td class="item-qty" style="text-align:left; white-space:nowrap;">${1 * ticketSign} x ${this.formatMoney(month.montant * ticketSign)}</td>
                    <td class="item-total" style="text-align:right;">${this.formatMoney(month.montant * ticketSign)} Fc</td>
                </tr>
            `;
        });
        itemsHtml += '</tbody></table>';

        // Récupérer client info
        const clientNom = document.getElementById('client-nom')?.value || '';
        const clientNumero = document.getElementById('invoice-number')?.value || '';
        const clientTelNum = document.getElementById('modal-client-tel1')?.value || this.apiResponse.client_numero;
        const clientType = document.getElementById('client-type')?.value || '';
        const clientNif = document.getElementById('client-nif')?.value || '';
        const clientAddress = document.getElementById('client-address')?.value || this.clientInfo?.adresse || '';
        const vendeur = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : STORE_INFO.name;
        const agentNumero = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '';
        const ticketDateTime = new Date().toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        // Masquer les 4 derniers chiffres du numéro client
        const maskClientNumero = (num) => {
            if (!num || num.length < 6) return num || '';
            return num.substring(0, num.length - 4) + '****';
        };

        // Obtenir le label complet du type de client
        const getClientTypeLabel = (code) => {
            const types = {
                'PP': 'Personne Physique',
                'PM': 'Personne Morale',
                'PC': 'Personne Physique Commerçante',
                'PL': 'Profession Libérale',
                'AO': 'Ambassades et Organisations Internationales',
            };
            return types[code] || code || '';
        };

        // Infos RCCM et ISF
        let storeExtraInfo = '';
        if (STORE_INFO.rccm) {
            storeExtraInfo += `<div>RCCM: ${STORE_INFO.rccm}</div>`;
        }
        if (STORE_INFO.isf) {
            storeExtraInfo += `<div>Numero Impot: ${STORE_INFO.isf}</div>`;
        }

        // Section infos (Vendeur + Client) - style caisse
        let infoSection = `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 11px; line-height: 1.5;">
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>VENDEUR:</strong></span><span>${vendeur}</span></div>
                           ${agentNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° AGENT:</strong></span><span>${agentNumero}</span></div>` : ''}
                           ${clientNom ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>CLIENT:</strong></span><span>${clientNom}</span></div>` : ''}
                           ${clientTelNum ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° CLIENT:</strong></span><span>${maskClientNumero(clientTelNum)}</span></div>` : ''}
                           ${clientAddress ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>ADRESSE:</strong></span><span>${clientAddress}</span></div>` : ''}
                           ${clientType ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>TYPE CLIENT:</strong></span><span>${getClientTypeLabel(clientType)}</span></div>` : ''}
                           ${clientNif ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>NIF:</strong></span><span>${clientNif}</span></div>` : ''}
                           ${clientNumero ? `<div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>N° COMPTEUR:</strong></span><span>${clientNumero}</span></div>` : ''}
                           <div style="display: flex; justify-content: space-between; gap: 10px;"><span><strong>SERVICE:</strong></span><span>${service}</span></div>
                        
                         </div>`;

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

        // Afficher le modal ticket (style caisse)
        $('#receipt-content').innerHTML = `
            <div class="receipt">
                <div class="receipt-header">
                    <div class="store-name">${STORE_INFO.name}</div>
                    <div class="store-info">
                        <div><strong>Point De vente :</strong> ${STORE_INFO.pdv}</div>
                        <div>Adresse ${STORE_INFO.address}/div>
                        <div>Tel: ${STORE_INFO.phone}</div>
                        ${STORE_INFO.email ? `<div>Email: ${STORE_INFO.email}</div>` : ''}
                        <div>ID Nat: ${STORE_INFO.ice}</div>
                        ${storeExtraInfo}
                    </div>
                    ${infoSection}
                </div>
                <div class="receipt-meta" style="justify-content: center; font-size: 14px; font-weight: 555; display: flex; flex-direction: column; text-align: center; gap: 4px;">
                    ${this.getInvoiceTypeLabel(invoiceType)}
                     ${dgiResponse.data?.refDocument ? `<div style="text-align: center; font-size: 11px; color: #888; font-style: italic;">${dgiResponse.data.actionFacture || ''}</div>` : ''}
                       
                        ${dgiResponse.data?.refDocument ? `<div style="text-align: center; font-size: 11px; color: #888; font-style: italic;">${dgiResponse.data.refFacture || ''}</div>` : ''}

                </div>
                <div class="receipt-items">
                    ${itemsHtml}
                </div>
                <div class="receipt-totals">
                    ${this.getTaxBreakdownHtml(dgiResponse)}
                    <div class="receipt-total-row grand-total">
                        <span>TOTAL TTC:</span>
                        <span>${this.formatMoney(total * ticketSign)} Fc</span>
                    </div>
                    ${this.getPaymentInfoHtml()}
                    <div style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: center;">
                        ISF : ${dgiResponse?.data?.isf || '0'}
                    </div>
                </div>
                ${dgiInfoHtml}
                <div class="receipt-footer">
                    <div id="${qrContainerId}" class="qrcode-container"></div>
                    <div class="thank-you">FACTURE n°${invoiceNum}</div>
                    <div class="thank-you" style="font-size: 10px;">Date: ${ticketDateTime}</div>
                    <div class="thank-you">Paiement ${service}</div>
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
                width: 250,
                height: 250,
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

    // Generate tax breakdown HTML (simplified for exonerated recharges)
    getTaxBreakdownHtml(dgiResponse) {
        // Recharges are exonerated - show EXONERE ET HORS CHAMP
        let html = '';

        // Logique de négation visuelle pour le détail des taxes
        // (cohérent avec le reste du fichier : signe = -1 quand le type EST FA ou EA)
        const rechargeTaxTypeFacture = document.getElementById('invoice-type')?.value || 'FV';
        const rechargeTaxShouldNegate = rechargeTaxTypeFacture === 'FA' || rechargeTaxTypeFacture === 'EA';
        const rechargeTaxSign = rechargeTaxShouldNegate ? -1 : 1;

        // Try to parse ht_tva_group from DGI response
        let haData = {};
        let vaData = {};

        try {
            if (dgiResponse?.data?.ht_tva_group) {
                const parsed = typeof dgiResponse.data.ht_tva_group === 'string'
                    ? JSON.parse(dgiResponse.data.ht_tva_group)
                    : dgiResponse.data.ht_tva_group;

                if (parsed?.data) {
                    haData = parsed.data.ha || {};
                    vaData = parsed.data.va || {};
                }
            }
        } catch (e) {
            console.warn('Error parsing ht_tva_group:', e);
        }

        // Display each category (a=standard, b, c, d, e, f=reduced, g, h, i, j, k, l, m, n, o, p)
        const TAX_CATEGORIES = [
            { key: 'haa', label: 'A', tax: 0, description: 'EXONERE ET HORS CHAMP' },
            { key: 'hab', label: 'B', tax: 16, description: 'Taxable' },
            { key: 'hac', label: 'C', tax: 5, description: 'Taxable' },
            { key: 'had', label: 'D', tax: 0, description: 'Régimes dérogatoires TVA' },
            { key: 'hae', label: 'E', tax: 0, description: 'Exportation et opération assimilées' },
            { key: 'haf', label: 'F', tax: 16, description: 'TVA marché public à financement exterieur ' },
            { key: 'hag', label: 'G', tax: 5, description: 'TVA marché public à financement exterieur ' },
            { key: 'hah', label: 'H', tax: 0, description: 'consignation/déconsignation emballage' },
            { key: 'hai', label: 'I', tax: 0, description: 'Garantie et caution' },
            { key: 'haj', label: 'J', tax: 0, description: 'Débours' },
            { key: 'hak', label: 'K', tax: 0, description: 'Opérations réalisées par les non-assujettis' },
            { key: 'hal', label: 'L', tax: 0, description: 'Prélèvements sur les ventes' },
            { key: 'ham', label: 'M', tax: 0, description: 'Ventes réglemntées TVA spécifique' },
            { key: 'han', label: 'N', tax: 0, description: 'TVA spécifique' },
            { key: 'hao', label: 'O', tax: 1, description: 'Taxable' },
            { key: 'hap', label: 'P', tax: 1, description: 'TVA marché public à financement extérieur' }
        ];

        TAX_CATEGORIES.forEach(cat => {
            // Appliquer le signe de négation (sign = -1 quand le type EST FA ou EA)
            const ht = (parseFloat(haData[cat.key]) || 0);
            const va = (parseFloat(vaData['va' + cat.key.slice(-1)]) || 0);

            if (Math.abs(ht) > 0 || Math.abs(va) > 0) {
                html += `<div class="receipt-total-row" style="font-size: 11px; padding-left: 10px;">
                    <span>HT[${cat.label}] ${cat.description} ${cat.tax} % :</span>
                    <span>${ht.toFixed(2)} Fc</span>
                </div>`;
                if (Math.abs(va) > 0) {
                    html += `<div class="receipt-total-row" style="font-size: 11px; padding-left: 10px; color: #666;">
                        <span>TVA[${cat.label}] ${cat.description} ${cat.tax} % :</span>
                        <span>${va.toFixed(2)} Fc</span>
                    </div>`;
                }
            }
        });

        // Only show exonerated items when tax_etiquette is "A" (haa)
        if (dgiResponse) {
            const exoneratedItems = this.months.filter(item => item.tax_etiquette === 'A' || item.tax_etiquette === 'haa');
            const exoneratedTotal = exoneratedItems.reduce((sum, item) => sum + (item.prix * item.quantite), 0) * rechargeTaxSign;
            if (exoneratedItems.length > 0 && Math.abs(exoneratedTotal) > 0) {
                html += `<div class="receipt-total-row" style="font-size: 11px; padding-left: 5px; color: #888;">
                    <span>EXONERE ET HORS CHAMP:</span>
                    <span>${exoneratedTotal.toFixed(2)} Fc</span>
                </div>`;
            }
        }

        return html;
    }

    // Generate payment info HTML for receipts (called after TOTAL TTC)
    getPaymentInfoHtml() {
        const totalQty = this.selectedMonths.reduce((sum, m) => sum + 1, 0);
        const total = this.selectedMonths.reduce((acc, m) => acc + m.montant, 0);

        let paymentsHtml = '';
        const payments = this.currentPayments?.length > 0 ? this.currentPayments : null;
        if (payments) {
            const paymentsTotal = payments.reduce((sum, p) => sum + p.amount, 0);
            paymentsHtml += `<div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px;">`;
            payments.forEach(p => {
                paymentsHtml += `<div class="receipt-total-row" style="font-size: 11px; color: #555;">
                    <span>${this.getPaymentTypeLabel(p.type)} :</span>
                    <span>${p.amount.toFixed(2)} Fc</span>
                </div>`;
            });
            if (paymentsTotal > total + 0.001) {
                paymentsHtml += `<div class="receipt-total-row" style="font-size: 11px; color: #2e7d32;">
                    <span>Monnaie rendue :</span>
                    <span>${(paymentsTotal - total).toFixed(2)} Fc</span>
                </div>`;
            }
            paymentsHtml += `</div>`;
        } else {
            const paymentTypeSelect = document.getElementById('modal-payment-type') || document.getElementById('payment-type');
            const paymentType = paymentTypeSelect?.value || 'cash';
            const paymentLabel = this.getPaymentTypeLabel(paymentType);
            paymentsHtml += `<div class="receipt-total-row" style="font-size: 11px; color: #555;">
                <span>Paiment : </span>
                <span>${paymentLabel}</span>
            </div>`;
        }

        const amountInWords = this.numberToFrenchWords(total || 0);


        return `
            <div class="receipt-total-row" style="font-size: 11px; color: #555">
                <span>TAUX DU JOUR :</span>
                <span>${USD_RATE} Fc/USD</span>
            </div>
            <div class="receipt-total-row" style="font-size: 11px; color: #555">
                <span>Equivalent en USD :</span>   
                <span> ${(total / USD_RATE).toFixed(2)}$ </span>
            </div>
            ${paymentsHtml}
            <div class="receipt-total-row" style="font-size: 11px; color: #555;">
                <span>Qté:</span>
                <span>${totalQty}</span>
            </div>
            
            <div style="text-align: center; font-size: 12px; color: #888; font-style: italic; margin-top: 2px;">
                Arrêté la présente facture à la somme de ${amountInWords} congolais toutes taxes comprises
            </div>
        `;
    }

    // Convert number to French words (for currency) - handles up to billions
    numberToFrenchWords(num) {

        const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
            'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'nonante'];

        if (num === 0) return 'zéro';

        const intPart = Math.floor(num);
        const decPart = Math.round((num - intPart) * 100);

        function threeDigitsToWords(n) {
            if (n === 0) return '';
            if (n < 20) return units[n];
            if (n < 100) {
                const ten = Math.floor(n / 10);
                const unit = n % 10;
                if (ten === 7) {
                    const teen = 10 + unit;
                    if (unit === 1) return 'soixante-et-' + units[teen];
                    return 'soixante-' + units[teen];
                }
                if (ten === 8 && unit === 0) return 'quatre-vingts';
                if (unit === 0) return tens[ten];
                return tens[ten] + (unit === 1 ? '-et-' : '-') + units[unit];
            }
            if (n < 1000) {
                const hundreds = Math.floor(n / 100);
                const remainder = n % 100;
                if (hundreds === 1) return remainder === 0 ? 'cent' : 'cent ' + threeDigitsToWords(remainder);
                if (hundreds === 2) return remainder === 0 ? 'deux cents' : 'deux cent ' + threeDigitsToWords(remainder);
                if (hundreds === 3) return remainder === 0 ? 'trois cents' : 'trois cent ' + threeDigitsToWords(remainder);
                if (hundreds === 4) return remainder === 0 ? 'quatre cents' : 'quatre cent ' + threeDigitsToWords(remainder);
                if (hundreds === 5) return remainder === 0 ? 'cinq cents' : 'cinq cent ' + threeDigitsToWords(remainder);
                if (hundreds === 6) return remainder === 0 ? 'six cents' : 'six cent ' + threeDigitsToWords(remainder);
                if (hundreds === 7) return remainder === 0 ? 'sept cents' : 'sept cent ' + threeDigitsToWords(remainder);
                if (hundreds === 8) return remainder === 0 ? 'huit cents' : 'huit cent ' + threeDigitsToWords(remainder);
                if (hundreds === 9) return remainder === 0 ? 'neuf cents' : 'neuf cent ' + threeDigitsToWords(remainder);
                return units[hundreds] + ' cents ' + threeDigitsToWords(remainder);
            }
            return n.toString();
        }

        function convertChunk(n) {
            if (n === 0) return '';
            if (n < 1000) return threeDigitsToWords(n);
            if (n < 1000000) {
                const thousands = Math.floor(n / 1000);
                const remainder = n % 1000;
                if (thousands === 1) return remainder === 0 ? 'mille' : 'mille ' + threeDigitsToWords(remainder);
                return threeDigitsToWords(thousands) + ' mille' + (remainder > 0 ? ' ' + threeDigitsToWords(remainder) : '');
            }
            if (n < 1000000000) {
                const millions = Math.floor(n / 1000000);
                const remainder = n % 1000000;
                const millionsText = millions === 1 ? 'un million' : threeDigitsToWords(millions) + ' millions';
                return millionsText + (remainder > 0 ? ' ' + convertChunk(remainder) : '');
            }
            // Billions
            const billions = Math.floor(n / 1000000000);
            const remainder = n % 1000000000;
            const billionsText = billions === 1 ? 'un milliard' : threeDigitsToWords(billions) + ' milliards';
            return billionsText + (remainder > 0 ? ' ' + convertChunk(remainder) : '');
        }

        let result = convertChunk(intPart);
        if (intPart > 1) result += ' francs';
        else if (intPart === 1) result += ' franc';

        if (decPart > 0) {
            result += ' ' + threeDigitsToWords(decPart) + ' centimes';
        }

        return result.charAt(0).toUpperCase() + result.slice(1);
    }
}

// ==========================================
// GLOBAL INSTANCE
// ==========================================

let billPayment;

// Fonctions globales pour les boutons inline du modal
function addModalPaymentLineRecharge(type, amount) {
    if (billPayment) billPayment.addModalPaymentLineRecharge(type, amount);
}
function removeModalPaymentLineRecharge(btn) {
    if (billPayment) billPayment.removeModalPaymentLineRecharge(btn);
}
function addModalRefDocLineRecharge(value) {
    if (billPayment) billPayment.addModalRefDocLineRecharge(value);
}
function removeModalRefDocLineRecharge(btn) {
    if (billPayment) billPayment.removeModalRefDocLineRecharge(btn);
}

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