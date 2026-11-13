// ==================== IMPORT DE PRODUITS (Excel / CSV) ====================
//
// Gestion du modal d'import de produits (page /produits).
// Le traitement réel est fait côté serveur par ProductImportService.

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
        const res = await fetch(APP_URL + '/api/produit/import', {
            method: 'POST',
            body: fd
        });

        let data;
        try {
            data = await res.json();
        } catch (e) {
            alert('Réponse invalide du serveur (HTTP ' + res.status + ').');
            return;
        }

        if (!data.success) {
            alert(data.error || 'Import impossible');
            if (data.summary) renderProductImportSummary(data.summary, data.rows || []);
            return;
        }

        renderProductImportSummary(data.summary, data.rows || []);
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
 * Affiche le résumé chiffré + le détail ligne par ligne.
 */
function renderProductImportSummary(summary, rows) {
    const formStep = document.getElementById('product-import-step-form');
    const resultStep = document.getElementById('product-import-step-result');
    if (formStep) formStep.style.display = 'none';
    if (resultStep) resultStep.style.display = '';

    const submitBtn = document.getElementById('product-import-submit-btn');
    if (submitBtn) submitBtn.style.display = 'none';

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
