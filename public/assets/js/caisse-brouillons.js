/**
 * caisse-brouillons.js — Brouillons de panier (caisse)
 *
 * Permet au caissier de sauvegarder son panier en cours côté serveur, puis de
 * le reprendre plus tard (autre poste, après un crash navigateur) et de
 * relancer la facture.
 *
 * Référence : docs/PLAN_BROUILLONS_CAISSE.md
 *
 * ⚠️ Règles invariantes :
 *   - Un brouillon ne déduit JAMAIS le stock. Le stock est déduit au moment
 *     de la facturation (POST /api/vente → SaleController::create).
 *   - Un brouillon ne consomme JAMAIS de numéro de facture.
 *   - La reprise réinjecte les articles dans `posCart` : le caissier clique
 *     ensuite sur « Valider la facture » comme d'habitude.
 *   - Le brouillon ne conserve que l'ID du client (ni NIF, ni adresse) : la
 *     fiche complète est relue depuis l'API au moment de la reprise.
 *
 * Chargé APRÈS app.js (le module doit pouvoir utiliser `posCart`, `APP_URL`,
 * `showCaisseToast`…). Les fonctions utilisées par les boutons du HTML sont
 * exposées sur `window`.
 */
(function () {
    'use strict';

    /**
     * ID du brouillon actuellement restauré (0 = aucun).
     *
     * Ce n'est qu'un indicateur de contexte : il est transmis à la création de
     * la vente pour la traçabilité (ventes.brouillon_id), sans jamais modifier
     * la logique de facturation.
     */
    let currentBrouillonId = 0;

    /**
     * Ouvre le modal des brouillons et charge la liste.
     */
    async function openBrouillonModal() {
        const modal = $('#brouillon-modal');
        const list = $('#brouillon-list');
        if (!modal || !list) {
            console.warn('[brouillon] modal introuvable dans le DOM');
            return;
        }

        // Reset UI
        list.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;font-size:0.85rem;">Chargement des brouillons…</div>';
        const empty = $('#brouillon-empty');
        const err = $('#brouillon-error');
        if (empty) empty.style.display = 'none';
        if (err) { err.style.display = 'none'; err.textContent = ''; }

        // Force l'affichage (même correctif que le picker de séjours : on pose
        // 'active' ET un display inline pour court-circuiter toute spécificité CSS).
        modal.classList.add('active');
        modal.style.display = 'flex';

        await renderBrouillonList();
    }

    /**
     * Ferme le modal des brouillons.
     */
    function closeBrouillonModal() {
        const modal = $('#brouillon-modal');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = '';
        }
    }

    /**
     * Charge et affiche la liste des brouillons de la boutique.
     */
    async function renderBrouillonList() {
        const list = $('#brouillon-list');
        const empty = $('#brouillon-empty');
        const err = $('#brouillon-error');
        if (!list) return;

        try {
            const res = await fetch(APP_URL + '/api/caisse/brouillons');
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || ('HTTP ' + res.status));
            }

            const rows = Array.isArray(data.data) ? data.data : [];

            if (rows.length === 0) {
                list.innerHTML = '';
                if (empty) empty.style.display = 'block';
                return;
            }
            if (empty) empty.style.display = 'none';

            list.innerHTML = rows.map(r => {
                const isConverti = r.status === 'converti';
                const statutLabel = isConverti
                    ? '<span style="color:#065f46;font-weight:600;">Facturé</span>'
                    : '<span style="color:#b45309;font-weight:600;">En attente</span>';

                const dateLabel = formatBrouillonDate(r.updated_at);
                const totalLabel = (typeof formatCurrency === 'function')
                    ? formatCurrency(r.total)
                    : (r.total + ' Fc');

                const metaParts = [
                    `${r.nb_articles} article${r.nb_articles > 1 ? 's' : ''}`,
                    totalLabel,
                    dateLabel
                ];
                if (r.client_nom) metaParts.push(r.client_nom);
                if (r.auteur) metaParts.push('par ' + r.auteur);

                // Sécurité XSS : escapeHtml sur toutes les données venues du serveur.
                return `
              <div class="brouillon-item">
                <div class="brouillon-item-main">
                  <div class="brouillon-item-libelle">${escapeHtml(r.libelle)}</div>
                  <div class="brouillon-item-meta">
                    ${escapeHtml(metaParts.join(' · '))} — ${statutLabel}
                  </div>
                </div>
                <div class="brouillon-item-actions">
                  <button type="button" class="btn btn-primary" onclick="restoreBrouillon(${parseInt(r.id, 10)})">Reprendre</button>
                  <button type="button" class="btn btn-ghost" onclick="deleteBrouillon(${parseInt(r.id, 10)})" title="Supprimer ce brouillon">&times;</button>
                </div>
              </div>
            `;
            }).join('');

        } catch (e) {
            console.error('[brouillon] list error:', e);
            list.innerHTML = '';
            if (err) {
                err.textContent = 'Erreur : ' + (e.message || e);
                err.style.display = 'block';
            }
        }
    }

    /**
     * Formate une date pour l'affichage « 17/11 14:32 ».
     */
    function formatBrouillonDate(value) {
        if (!value) return '';
        const d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(value);
        const pad = n => String(n).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    /**
     * Enregistre le panier courant comme brouillon côté serveur.
     *
     * ⚠️ Le stock n'est PAS déduit : la déduction se fait à la facturation.
     */
    async function saveBrouillon() {
        const errBox = $('#brouillon-save-error');
        const btn = $('#btn-save-brouillon');
        const libelleInput = $('#brouillon-libelle');

        if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

        if (!posCart || !Array.isArray(posCart.items) || posCart.items.length === 0) {
            if (errBox) {
                errBox.textContent = 'Le panier est vide : ajoutez au moins un article.';
                errBox.style.display = 'block';
            }
            return;
        }

        if (btn) btn.disabled = true;

        try {
            const res = await fetch(APP_URL + '/api/caisse/brouillons', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    libelle: libelleInput ? libelleInput.value.trim() : '',
                    invoice_type: document.getElementById('invoice-type')?.value || 'FV',
                    articles: posCart.items,
                    client: posCart.currentClient ? { id: posCart.currentClient.id, nom: posCart.currentClient.nom || '' } : null,
                    sejour: posCart.currentSejour ? { id: posCart.currentSejour.id, chambre: posCart.currentSejour.chambre_numero || '' } : null,
                    payments: Array.isArray(posCart.currentPayments) ? posCart.currentPayments : []
                })
            });

            let data = null;
            try { data = await res.json(); } catch (_) { /* pas JSON */ }

            if (!res.ok || !data || !data.success) {
                const msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.style.display = 'block';
                }
                return;
            }

            // Le brouillon sauvegardé devient le contexte courant : s'il est
            // ensuite facturé, la vente portera le lien de traçabilité.
            currentBrouillonId = parseInt(data.id, 10) || 0;

            if (libelleInput) libelleInput.value = '';

            closeBrouillonModal();
            refreshBrouillonBadge();

            if (typeof showCaisseToast === 'function') {
                showCaisseToast('Brouillon enregistré : ' + (data.libelle || ''), 'success');
            }

        } catch (e) {
            console.error('[brouillon] save error:', e);
            if (errBox) {
                errBox.textContent = 'Erreur réseau : ' + (e.message || e);
                errBox.style.display = 'block';
            }
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    /**
     * Reprend un brouillon : réinjecte ses articles dans le panier de la caisse.
     *
     * ⚠️ Cette fonction ne factURE pas : le caissier valide ensuite la facture
     * via le flux normal (DGI + POST /api/vente).
     */
    async function restoreBrouillon(id) {
        const errBox = $('#brouillon-error');

        try {
            const res = await fetch(APP_URL + '/api/caisse/brouillons/' + encodeURIComponent(id) + '/restore', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            });

            let data = null;
            try { data = await res.json(); } catch (_) { /* pas JSON */ }

            if (!res.ok || !data || !data.success) {
                const msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.style.display = 'block';
                }
                if (typeof showCaisseToast === 'function') {
                    showCaisseToast('Erreur : ' + msg, 'error');
                }
                return;
            }

            const payload = data.payload || {};
            const articles = Array.isArray(payload.articles) ? payload.articles : [];

            if (articles.length === 0) {
                if (typeof showCaisseToast === 'function') {
                    showCaisseToast('Ce brouillon est vide.', 'error');
                }
                return;
            }

            // ── Réinjection dans le panier ──────────────────────────────
            posCart.items = articles.map(a => ({
                produit_id: parseInt(a.produit_id, 10),
                nom: a.nom || '',
                prix: parseFloat(a.prix) || 0,
                quantite: parseFloat(a.quantite) || 0,
                maxStock: a.maxStock ?? null,
                tax_rate: parseFloat(a.tax_rate) || 0,
                tax_etiquette: a.tax_etiquette || '',
                tax_groupe: a.tax_groupe || '',
                product_type: a.product_type || 'unite',
                prod_service: a.prod_service || '',
                remise_type: a.remise_type || '%',
                remise_value: parseFloat(a.remise_value) || 0,
                taxe_specifique_type: a.taxe_specifique_type || '%',
                taxe_specifique_value: parseFloat(a.taxe_specifique_value) || 0
            }));

            currentBrouillonId = parseInt(data.id, 10) || 0;

            // Type de facture (FV, FA...) : on restaure celui du brouillon.
            const invoiceTypeInput = document.getElementById('invoice-type');
            if (invoiceTypeInput && data.invoice_type) {
                invoiceTypeInput.value = data.invoice_type;
            }

            // Client : le brouillon ne conserve que son ID, on relit donc la fiche
            // complète depuis le serveur (ni NIF ni adresse dans le brouillon).
            if (payload.client && payload.client.id) {
                await restoreBrouillonClient(payload.client.id);
            } else {
                posCart.currentClient = null;
            }

            // Séjour hôtelier : le brouillon n'en garde que l'ID.
            if (payload.sejour && payload.sejour.id) {
                await restoreBrouillonSejour(payload.sejour);
            } else {
                posCart.currentSejour = null;
            }

            // Paiements (multi-paiements choisis dans le modal de facturation).
            if (Array.isArray(payload.payments) && payload.payments.length > 0) {
                posCart.currentPayments = payload.payments;
            }

            // Recalcule les totaux et affiche le panier.
            posCart.renderCart();

            closeBrouillonModal();
            refreshBrouillonBadge();

            if (typeof showCaisseToast === 'function') {
                showCaisseToast(
                    'Brouillon repris : ' + (data.libelle || '') + ' — validez la facture pour l\'encaisser.',
                    'success'
                );
            }

        } catch (e) {
            console.error('[brouillon] restore error:', e);
            if (typeof showCaisseToast === 'function') {
                showCaisseToast('Erreur réseau : ' + (e.message || e), 'error');
            }
        }
    }

    /**
     * Relit un client depuis l'API pour reconstituer son affichage complet.
     *
     * Le brouillon ne stocke QUE l'ID du client (ni NIF, ni adresse) : c'est
     * ici qu'on relit la fiche complète.
     */
    async function restoreBrouillonClient(clientId) {
        try {
            const res = await fetch(APP_URL + '/api/client/' + encodeURIComponent(clientId));
            const data = await res.json();
            if (res.ok && data && data.client) {
                posCart.currentClient = data.client;
                if (typeof posCart.displayClientName === 'function') {
                    posCart.displayClientName(data.client);
                }
            } else {
                posCart.currentClient = null;
            }
        } catch (e) {
            console.warn('[brouillon] reload client failed:', e);
            posCart.currentClient = null;
        }
    }

    /**
     * Restaure le séjour hôtelier d'un brouillon (imputation « Extras »).
     *
     * On relit la liste des séjours en cours (route existante) pour retrouver
     * la fiche complète du séjour : le brouillon n'en conserve que l'ID et le
     * n° de chambre (cf. §4.7 du plan : aucune donnée sensible conservée).
     */
    async function restoreBrouillonSejour(sejour) {
        try {
            const res = await fetch(APP_URL + '/api/hotel/sejours/en-cours');
            const data = await res.json();
            const sejours = (res.ok && data && Array.isArray(data.data)) ? data.data : [];

            const found = sejours.find(s => parseInt(s.id, 10) === parseInt(sejour.id, 10));

            if (found) {
                if (typeof posCart.setCurrentSejour === 'function') {
                    // setCurrentSejour affiche le toast de confirmation + met à
                    // jour le bouton « Extras ». On l'appelle pour rester
                    // cohérent avec la sélection manuelle d'un séjour.
                    posCart.setCurrentSejour(found);
                    return;
                }
                posCart.currentSejour = found;
            } else {
                // Le séjour n'est plus imputable (clos, facturé ou terminé) :
                // on ne l'attache pas au panier, sinon la vente serait refusée.
                console.warn('[brouillon] séjour non imputable :', sejour.id);
                posCart.currentSejour = null;
            }
        } catch (e) {
            console.warn('[brouillon] reload sejour failed:', e);
            posCart.currentSejour = null;
        }
    }

    /**
     * Supprime un brouillon.
     *
     * L'autorisation est vérifiée côté serveur : l'auteur du brouillon ou un
     * administrateur de la boutique (cf. CaisseBrouillonController::destroy).
     */
    async function deleteBrouillon(id) {
        if (!confirm('Supprimer ce brouillon ?')) {
            return;
        }

        try {
            const res = await fetch(APP_URL + '/api/caisse/brouillons/' + encodeURIComponent(id) + '/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            });

            let data = null;
            try { data = await res.json(); } catch (_) { /* pas JSON */ }

            if (!res.ok || !data || !data.success) {
                const msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
                if (typeof showCaisseToast === 'function') {
                    showCaisseToast('Erreur : ' + msg, 'error');
                }
                return;
            }

            await renderBrouillonList();
            refreshBrouillonBadge();

            if (typeof showCaisseToast === 'function') {
                showCaisseToast('Brouillon supprimé.', 'success');
            }

        } catch (e) {
            console.error('[brouillon] delete error:', e);
            if (typeof showCaisseToast === 'function') {
                showCaisseToast('Erreur réseau : ' + (e.message || e), 'error');
            }
        }
    }

    /**
     * Met à jour le badge du bouton « Brouillon » : nombre de brouillons
     * 'ouvert' du caissier (cf. GET /api/caisse/brouillons/compteur).
     */
    async function refreshBrouillonBadge() {
        const badge = $('#brouillon-badge');
        if (!badge) return;

        try {
            const res = await fetch(APP_URL + '/api/caisse/brouillons/compteur');
            const data = await res.json();

            if (!res.ok || !data || !data.success) {
                badge.style.display = 'none';
                return;
            }

            const count = parseInt(data.count, 10) || 0;
            if (count > 0) {
                badge.textContent = String(count);
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        } catch (e) {
            // Silencieux : le badge est un confort, pas une fonction critique.
            badge.style.display = 'none';
        }
    }

    /**
     * ID du brouillon actuellement restauré (0 = aucun).
     * Utilisé par app.js pour la traçabilité de la vente.
     */
    function getCurrentBrouillonId() {
        return currentBrouillonId;
    }

    /**
     * Remet à zéro le contexte brouillon (après une vente ou un clearCart).
     */
    function resetCurrentBrouillon() {
        currentBrouillonId = 0;
    }

    // ── Exposition globale ───────────────────────────────────────────
    // Les boutons du HTML appellent ces fonctions par leur nom.
    window.openBrouillonModal = openBrouillonModal;
    window.closeBrouillonModal = closeBrouillonModal;
    window.saveBrouillon = saveBrouillon;
    window.restoreBrouillon = restoreBrouillon;
    window.deleteBrouillon = deleteBrouillon;
    window.refreshBrouillonBadge = refreshBrouillonBadge;
    window.getCurrentBrouillonId = getCurrentBrouillonId;
    window.resetCurrentBrouillon = resetCurrentBrouillon;
})();
