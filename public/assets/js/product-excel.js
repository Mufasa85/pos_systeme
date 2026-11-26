// ==================== IMPORT DE PRODUITS (Excel / CSV) ====================
//
// Gestion du modal d'import de produits (page /produits).
// Le traitement réel est fait côté serveur par ProductImportService.

/**
 * La taille d'un BLOC d'import (lignes de données).
 *
 * ⚠️ Doit correspondre à `ProductImportService::DEFAULT_CHUNK_SIZE`
 *    (côté serveur) : 5 000 lignes ≈ 2 s de traitement, ce qui laisse
 *    une marge énorme sous `max_execution_time` (120 s).
 */
const PI_CHUNK_SIZE = 5000;

/**
 * Le seuil (en lignes) au-delà duquel l'import passe en mode « par blocs ».
 *
 * ⚠️ MESURÉ : 20 000 lignes = 94,9 s (211 lignes/s). La limite PHP
 *    est à 120 s : on ne reste donc jamais au-delà de ~15 000 lignes
 *    dans une seule requête.
 */
const PI_CHUNK_THRESHOLD = 12000;

function openProductImportModal() {
    const modal = document.getElementById('product-import-modal');
    if (!modal) return;

    // Réinitialise le formulaire à chaque ouverture.
    const fileInput = document.getElementById('product-import-file');
    if (fileInput) fileInput.value = '';

    const fileLabel = document.querySelector('#product-import-modal .pi-file-label');
    const fileName = document.getElementById('product-import-filename');
    if (fileLabel) fileLabel.classList.remove('is-selected');
    if (fileName) fileName.textContent = 'Choisir un fichier…';

    // Super_admin : réinitialise la boutique de destination et les
    // catégories (qui dépendent de la boutique choisie).
    const shopSelect = document.getElementById('product-import-shop');
    if (shopSelect) {
        shopSelect.value = '';
        const categorySelect = document.getElementById('product-import-default-category');
        if (categorySelect) {
            categorySelect.innerHTML = '<option value="">Aucune</option>';
            categorySelect.disabled = false;
        }
    }

    const formStep = document.getElementById('product-import-step-form');
    const resultStep = document.getElementById('product-import-step-result');
    if (formStep) formStep.style.display = '';
    if (resultStep) resultStep.style.display = 'none';

    // ⚠️ Un import précédent peut avoir laissé un jeton : il faut le
    //    purger, sinon le bouton « Abandonner » viserait un import
    //    déjà terminé.
    window.__productImportToken = null;

    // L'étape « avancement » est masquée, et le bouton « Abandonner »
    // aussi (l'import n'a pas commencé).
    productImportBasculerEtape('form');
    const abortBtn = document.getElementById('product-import-abort-btn');
    if (abortBtn) abortBtn.style.display = 'none';

    const submitBtn = document.getElementById('product-import-submit-btn');
    if (submitBtn) submitBtn.style.display = '';

    modal.classList.add('active');
}

/**
 * Affiche le nom du fichier sélectionné dans le sélecteur.
 */
function onProductImportFileSelected() {
    const fileInput = document.getElementById('product-import-file');
    const fileLabel = document.querySelector('#product-import-modal .pi-file-label');
    const fileName = document.getElementById('product-import-filename');
    if (!fileInput || !fileLabel || !fileName) return;

    const file = fileInput.files && fileInput.files[0];
    if (file) {
        fileName.textContent = file.name;
        fileLabel.classList.add('is-selected');
    } else {
        fileName.textContent = 'Choisir un fichier…';
        fileLabel.classList.remove('is-selected');
    }
}

/**
 * Super_admin : recharge les catégories disponibles quand la boutique de
 * destination change (les catégories sont propres à chaque boutique).
 */
async function onProductImportShopChanged() {
    const shopSelect = document.getElementById('product-import-shop');
    const categorySelect = document.getElementById('product-import-default-category');
    if (!shopSelect || !categorySelect) return;

    const shopId = shopSelect.value;

    // Vide la liste le temps du chargement.
    categorySelect.innerHTML = '<option value="">Aucune</option>';
    categorySelect.disabled = true;

    if (!shopId) return;

    try {
        const res = await fetch(APP_URL + '/api/produit/import/categories?shop_id=' + encodeURIComponent(shopId));
        const categories = await res.json();

        if (!Array.isArray(categories)) return;

        categorySelect.innerHTML = '<option value="">Aucune</option>';
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.category;
            categorySelect.appendChild(option);
        });
        categorySelect.disabled = false;
    } catch (e) {
        console.error('Erreur chargement catégories:', e);
        categorySelect.disabled = false;
    }
}

function closeProductImportModal() {
    const modal = document.getElementById('product-import-modal');
    if (modal) modal.classList.remove('active');

    // ⚠️ Fermer le modal pendant un import abandonne celui-ci : le
    //    fichier déposé sur le serveur doit être supprimé, sinon il
    //    traîne sur le disque (il est purgé après 2 h, mais autant ne
    //    pas l'attendre).
    if (window.__productImportToken) {
        const token = window.__productImportToken;
        window.__productImportToken = null;

        try {
            const fd = new FormData();
            fd.append('token', token);
            fetch(APP_URL + '/api/produit/import/abort', { method: 'POST', body: fd });
        } catch (e) {
            console.error('Erreur abandon import:', e);
        }
    }
}

/**
 * Télécharge le modèle .xlsx à remplir.
 */
function downloadProductImportTemplate() {
    window.location.href = APP_URL + '/api/produit/import/template';
}

/**
 * Exporte les produits affichés dans un fichier Excel.
 *
 * Les filtres actifs de la page (recherche, catégorie, boutique) sont
 * transmis au serveur : le fichier contient exactement les produits
 * visibles dans le tableau.
 */
function exportProductsToExcel() {
    const params = new URLSearchParams();

    const search = document.getElementById('products-filter');
    if (search && search.value.trim()) {
        params.set('search', search.value.trim());
    }

    const category = document.getElementById('category-filter');
    if (category && category.value && category.value !== 'all') {
        params.set('category', category.value);
    }

    const shop = document.getElementById('shop-filter');
    if (shop && shop.value && shop.value !== 'all') {
        params.set('shop_id', shop.value);
    }

    const includeInactive = document.getElementById('include-inactive-shops');
    if (includeInactive && includeInactive.checked) {
        params.set('include_inactive', '1');
    }

    const query = params.toString();
    window.location.href = APP_URL + '/api/produit/export' + (query ? '?' + query : '');
}

/**
 * Envoie le fichier sélectionné au serveur et affiche le rapport.
 *
 * ⚠️ DEUX MODES, choisis selon la TAILLE du fichier :
 *
 *   - PETIT fichier (≤ PI_CHUNK_THRESHOLD lignes) : une seule requête.
 *     Le comportement d'avant, inchangé.
 *
 *   - GROS fichier : le mode « par blocs ». C'est la réponse au crash :
 *     `max_execution_time` vaut 120 s dans le php.ini par défaut, et
 *     l'import de 50 000 produits prenait 237 s MESURÉS → la requête
 *     était TUÉE vers le 26 000e produit.
 *
 *     Le fichier est déposé une fois (POST /api/produit/import avec
 *     `chunked=1`), puis le client enchaîne les blocs
 *     (POST /api/produit/import/chunk). Chaque bloc dure ~2 s : il
 *     passe TOUJOURS, et l'interface affiche l'avancement.
 */
async function submitProductImport() {
    const fileInput = document.getElementById('product-import-file');
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        alert('Veuillez choisir un fichier à importer.');
        return;
    }

    const submitBtn = document.getElementById('product-import-submit-btn');
    const originalHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Import en cours...';
    }

    const fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('mode', document.getElementById('product-import-mode')?.value || 'create');
    fd.append('category_id', document.getElementById('product-import-default-category')?.value || '0');
    fd.append('tax_id', document.getElementById('product-import-default-tax')?.value || '0');
    if (document.getElementById('product-import-create-categories')?.checked) {
        fd.append('create_categories', '1');
    }

    // Super_admin : boutique de destination (obligatoire).
    const shopSelect = document.getElementById('product-import-shop');
    if (shopSelect) {
        if (!shopSelect.value) {
            alert('Veuillez choisir la boutique de destination des produits.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
            return;
        }
        fd.append('shop_id', shopSelect.value);
    }

    try {
        // ── Étape 1 : le fichier est-il assez gros pour le mode par blocs ? ──
        const estGros = await productImportEstGros(fileInput.files[0]);

        if (!estGros) {
            const res = await fetch(APP_URL + '/api/produit/import', {
                method: 'POST',
                body: fd
            });

            const data = await productImportReadJson(res);
            if (!data) {
                alert('Réponse invalide du serveur (HTTP ' + res.status + ').');
                return;
            }

            if (!data.success) {
                alert(data.error || 'Import impossible');
                if (data.summary) renderProductImportSummary(data.summary, data.rows || []);
                return;
            }

            renderProductImportSummary(data.summary, data.rows || []);
            return;
        }

        // ── Étape 2 : dépôt du fichier + boucle sur les blocs ──
        fd.append('chunked', '1');
        const resDepot = await fetch(APP_URL + '/api/produit/import', {
            method: 'POST',
            body: fd
        });

        const depot = await productImportReadJson(resDepot);
        if (!depot || !depot.success) {
            alert((depot && depot.error) || 'Le fichier n\'a pas pu être déposé pour l\'import.');
            return;
        }

        await productImportRunChunks(depot);
    } catch (e) {
        console.error('Erreur import produits:', e);
        alert('Erreur réseau pendant l\'import.');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    }
}

/**
 * Le fichier est-il assez gros pour le mode « par blocs » ?
 *
 * ⚠️ On ne peut PAS compter les lignes d'un .xlsx dans le navigateur
 *    (c'est un ZIP) : on se base donc sur la TAILLE du fichier, qui est
 *    un bon proxy — mesuré : le .csv de 50 000 lignes fait 4,45 Mo,
 *    soit ~93 octets par ligne ; le .xlsx de 50 000 lignes fait 2,96 Mo,
 *    soit ~59 octets par ligne.
 *
 * @returns {Promise<boolean>}
 */
async function productImportEstGros(file) {
    // Au-delà de 512 Ko, on bascule en mode par blocs SANS compter :
    // un fichier de cette taille dépasse déjà ~10 000 lignes, et
    // 10 000 lignes = ~47 s de traitement — trop près de la limite
    // de 120 s pour être laissé en un seul passage.
    const SEUIL_OCTETS = 512 * 1024;

    if (file.size >= SEUIL_OCTETS) {
        return true;
    }

    // En dessous : on peut compter les lignes EXACTEMENT (le fichier
    // tient en RAM : < 512 Ko). C'est plus sûr que la taille — un CSV
    // de 3 000 lignes à noms courts peut dépasser le seuil.
    const extension = (file.name.split('.').pop() || '').toLowerCase();
    if (extension === 'csv' || extension === 'txt') {
        try {
            const lignes = await productImportCompterLignesCsv(file);
            return lignes > PI_CHUNK_THRESHOLD;
        } catch (e) {
            // Impossible de compter : on s'en tient à la taille.
            return file.size >= SEUIL_OCTETS;
        }
    }

    return false;
}

/**
 * Compte les lignes d'un CSV dans le navigateur.
 *
 * ⚠️ Le fichier fait moins de 512 Ko (c'est la condition d'appel) :
 *    le lire en entier ne pose aucun problème de mémoire.
 *
 * @returns {Promise<number>}
 */
function productImportCompterLignesCsv(file) {
    return file.text().then(texte => {
        let compteur = 0;
        for (let i = 0; i < texte.length; i++) {
            if (texte.charCodeAt(i) === 10) {   // '\n'
                compteur++;
            }
        }
        return compteur;
    });
}

/**
 * Enchaîne les blocs d'import et met à jour l'interface.
 *
 * @param {object} depot  La réponse du POST de dépôt (token, total_rows).
 */
async function productImportRunChunks(depot) {
    const token = depot.token;
    const total = depot.total_rows || 0;

    // ⚠️ Le jeton est gardé pour pouvoir ANNULER l'import (le bouton
    //    « Abandonner » supprime le fichier déposé sur le serveur).
    window.__productImportToken = token;

    productImportAfficherProgression({
        percent: 0,
        count: 0,
        total: total,
        elapsed: 0,
        eta: 'Préparation…',
        names: []
    });

    productImportBasculerEtape('progress');

    const abortBtn = document.getElementById('product-import-abort-btn');
    if (abortBtn) abortBtn.style.display = '';

    const debut = Date.now();
    let offset = 0;
    let traite = 0;
    let noms = [];
    let resume = null;
    let lignes = [];

    while (true) {
        const fd = new FormData();
        fd.append('token', token);
        fd.append('offset', String(offset));
        fd.append('chunk_size', String(PI_CHUNK_SIZE));

        const res = await fetch(APP_URL + '/api/produit/import/chunk', {
            method: 'POST',
            body: fd
        });

        const data = await productImportReadJson(res);
        if (!data) {
            throw new Error('Réponse invalide du serveur (HTTP ' + res.status + ').');
        }

        /*
         * ⚠️ Un bloc peut ÉCHOUER sans que le fichier soit terminé.
         *
         *    `done` ne dépend que de la FIN DU FICHIER : un bloc dont
         *    toutes les lignes sont invalides n'arrête donc pas
         *    l'import. C'est le serveur qui décide ; le client ne
         *    s'arrête que sur `done`.
         *
         *    Mais une erreur réseau, elle, doit arrêter la boucle :
         *    on la distingue par l'absence de `next_offset`.
         */
        if (!data.error && typeof data.next_offset === 'undefined') {
            throw new Error('Réponse invalide du serveur (HTTP ' + res.status + ').');
        }

        offset = data.next_offset || (offset + (data.processed || 0));
        traite = offset;
        noms = data.last_names || [];
        resume = data.summary || resume;
        lignes = data.rows || [];

        const elapsed = (Date.now() - debut) / 1000;
        const percent = total > 0 ? Math.min(100, Math.round((traite / total) * 100)) : 0;

        productImportAfficherProgression({
            percent: percent,
            count: traite,
            total: total,
            elapsed: elapsed,
            eta: productImportEta(traite, total, elapsed),
            names: noms
        });

        if (data.done) {
            /*
             * ⚠️ Le dernier bloc peut être VIDE : c'est normal.
             *
             *    Le client demande un bloc de plus pour savoir que le
             *    fichier est épuisé. Le serveur répond alors « fichier
             *    vide », ce qui n'est PAS une erreur : tout a été
             *    importé.
             *
             *    On ne signale donc une erreur que si le serveur a
             *    produit quelque chose ET déclaré une erreur.
             */
            if (data.error && (data.processed || 0) > 0) {
                throw new Error(data.error);
            }
            break;
        }

        // ⚠️ Filet de sécurité : si le serveur ne progresse plus, on
        //    arrête plutôt que de boucler à l'infini.
        if (data.processed === 0 && !data.done) {
            throw new Error("L'import ne progresse plus : arrêt pour éviter une boucle infinie.");
        }
    }

    if (abortBtn) abortBtn.style.display = '';

    renderProductImportSummary(resume || {}, lignes);
}

/**
 * L'estimation du temps restant.
 *
 * ⚠️ L'estimation est calculée sur le TEMPS ÉCOULÉ et non sur la durée
 *    moyenne par bloc : le débit s'améliore au fil de l'import (les
 *    premiers blocs paient le coût de la lecture du fichier), donc la
 *    moyenne cumulée est plus honnête qu'une moyenne par bloc.
 *
 * @param {number} traite   Lignes déjà traitées.
 * @param {number} total    Lignes totales (0 = inconnu).
 * @param {number} elapsed  Secondes écoulées.
 * @returns {string}
 */
function productImportEta(traite, total, elapsed) {
    if (!total || traite <= 0 || elapsed <= 0) {
        return 'Estimation…';
    }

    const debit = traite / elapsed;              // lignes / seconde
    const restant = Math.max(0, total - traite);
    const secondes = restant / debit;

    if (!isFinite(secondes)) {
        return 'Estimation…';
    }

    if (secondes < 60) {
        return 'Temps restant : ~' + Math.ceil(secondes) + ' s';
    }

    const minutes = Math.ceil(secondes / 60);
    if (minutes < 60) {
        return 'Temps restant : ~' + minutes + ' min';
    }

    const heures = Math.floor(minutes / 60);
    return 'Temps restant : ~' + heures + ' h ' + (minutes % 60) + ' min';
}

/**
 * Met à jour le bloc d'avancement du modal.
 */
function productImportAfficherProgression(etat) {
    const percentEl = document.getElementById('pi-progress-percent');
    const countEl = document.getElementById('pi-progress-count');
    const barEl = document.getElementById('pi-progress-bar');
    const etaEl = document.getElementById('pi-progress-eta');
    const elapsedEl = document.getElementById('pi-progress-elapsed');
    const namesEl = document.getElementById('pi-progress-names');

    if (percentEl) percentEl.textContent = etat.percent + ' %';

    if (countEl) {
        countEl.textContent = etat.total > 0
            ? etat.count.toLocaleString('fr-FR') + ' / ' + etat.total.toLocaleString('fr-FR') + ' produits'
            : etat.count.toLocaleString('fr-FR') + ' produits';
    }

    if (barEl) barEl.style.width = etat.percent + '%';

    if (etaEl) etaEl.textContent = etat.eta;

    if (elapsedEl) {
        elapsedEl.textContent = etat.elapsed > 0
            ? 'Écoulé : ' + productImportFormaterDuree(etat.elapsed)
            : '';
    }

    if (namesEl) {
        namesEl.innerHTML = '';
        (etat.names || []).forEach(nom => {
            const li = document.createElement('li');
            li.textContent = nom;
            namesEl.appendChild(li);
        });
    }
}

/**
 * Formate une durée en secondes (1 min 30, 2 h 05…).
 */
function productImportFormaterDuree(secondes) {
    const s = Math.floor(secondes);
    if (s < 60) {
        return s + ' s';
    }

    const minutes = Math.floor(s / 60);
    const reste = s % 60;

    if (minutes < 60) {
        return minutes + ' min ' + String(reste).padStart(2, '0');
    }

    const heures = Math.floor(minutes / 60);
    return heures + ' h ' + String(minutes % 60).padStart(2, '0');
}

/**
 * Bascule le modal entre les étapes (formulaire / progression / résultat).
 */
function productImportBasculerEtape(etape) {
    const formStep = document.getElementById('product-import-step-form');
    const progressStep = document.getElementById('product-import-step-progress');
    const resultStep = document.getElementById('product-import-step-result');
    const submitBtn = document.getElementById('product-import-submit-btn');

    if (formStep) formStep.style.display = etape === 'form' ? '' : 'none';
    if (progressStep) progressStep.style.display = etape === 'progress' ? '' : 'none';
    if (resultStep) resultStep.style.display = etape === 'result' ? '' : 'none';

    if (submitBtn) {
        submitBtn.style.display = (etape === 'form') ? '' : 'none';
    }
}

/**
 * Abandonne l'import en cours et supprime le fichier déposé.
 */
async function abortProductImport() {
    const token = window.__productImportToken;
    if (!token) {
        closeProductImportModal();
        return;
    }

    try {
        const fd = new FormData();
        fd.append('token', token);
        await fetch(APP_URL + '/api/produit/import/abort', { method: 'POST', body: fd });
    } catch (e) {
        console.error('Erreur abandon import:', e);
    }

    window.__productImportToken = null;
    closeProductImportModal();
}

/**
 * Lit une réponse JSON sans planter sur une réponse non-JSON.
 */
async function productImportReadJson(res) {
    try {
        return await res.json();
    } catch (e) {
        return null;
    }
}

/**
 * Affiche le résumé chiffré + le détail ligne par ligne.
 */
function renderProductImportSummary(summary, rows) {
    // ⚠️ Le passage à l'étape « résultat » passe par la même fonction que
    //    le mode par blocs : les deux affichent le même résumé.
    productImportBasculerEtape('result');

    const abortBtn = document.getElementById('product-import-abort-btn');
    if (abortBtn) abortBtn.style.display = 'none';
    window.__productImportToken = null;

    // ── Cartes de résumé ──
    const summaryEl = document.getElementById('product-import-summary');
    if (summaryEl) {
        const cards = [
            { label: 'Créés', value: summary.created || 0, color: '#16a34a' },
            { label: 'Mis à jour', value: summary.updated || 0, color: '#0B5E88' },
            { label: 'Ignorés', value: summary.skipped || 0, color: '#f59e0b' },
            { label: 'Erreurs', value: summary.errors || 0, color: '#dc2626' },
            { label: 'Total', value: summary.total || 0, color: '#475569' },
        ];

        summaryEl.innerHTML = cards.map(c => `
            <div class="pi-summary-card">
                <div class="pi-summary-value" style="color:${c.color};">${c.value}</div>
                <div class="pi-summary-label">${c.label}</div>
            </div>
        `).join('');
    }

    // ── Détail des lignes ──
    const rowsEl = document.getElementById('product-import-rows');
    if (!rowsEl) return;

    if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--muted);">Aucun détail disponible</td></tr>';
        return;
    }

    const badges = {
        created: { cls: 'badge-success', label: 'Créé' },
        updated: { cls: 'badge-primary', label: 'Mis à jour' },
        skipped: { cls: 'badge-warning', label: 'Ignoré' },
        error: { cls: 'badge-danger', label: 'Erreur' },
    };

    // Sécurité XSS : les valeurs sont injectées via textContent.
    rowsEl.innerHTML = '';
    rows.forEach(row => {
        const meta = badges[row.status] || { cls: '', label: row.status };
        const tr = document.createElement('tr');

        const tdLine = document.createElement('td');
        tdLine.textContent = row.line;

        const tdNom = document.createElement('td');
        tdNom.textContent = row.nom || '—';

        const tdStatus = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'badge ' + meta.cls;
        badge.textContent = meta.label;
        tdStatus.appendChild(badge);

        const tdMsg = document.createElement('td');
        tdMsg.textContent = row.message || '';
        tdMsg.style.fontSize = '.8rem';
        tdMsg.style.color = 'var(--muted)';

        tr.appendChild(tdLine);
        tr.appendChild(tdNom);
        tr.appendChild(tdStatus);
        tr.appendChild(tdMsg);
        rowsEl.appendChild(tr);
    });
}
