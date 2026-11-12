/**
 * Tableau de bord : indicateurs DGI/OSAT (jour, semaine, mois).
 *
 * Les trois cartes « Ventes » ne sont PLUS calculées en local : elles
 * affichent les montants officiels fournis par la DGI via l'API OSAT.
 *
 * ⚠️ Le chargement est ASYNCHRONE : 3 périodes × N boutiques = autant
 * d'appels provider (~1,7 s chacun, lancés en parallèle côté serveur).
 * La page s'affiche donc immédiatement, avec un état « Chargement… », et les
 * valeurs sont injectées dès que la réponse arrive.
 */
const DgiDashboardStats = {
    endpoint: '/api/dashboard/dgi-stats',
    loaded: false,

    init() {
        if (this.loaded) return;
        this.loaded = true;

        if (!document.querySelector('[data-dgi-stat]')) {
            return; // Pas sur la page du tableau de bord.
        }

        this.load();
    },

    async load() {
        try {
            const response = await fetch(this.endpoint, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                this.renderError(payload?.message || 'Service DGI indisponible.');
                return;
            }

            this.render(payload.data?.periods || {}, payload.data?.meta?.periods || {});
        } catch (error) {
            console.error('[DgiDashboardStats] Chargement impossible', error);
            this.renderError('Service DGI momentanément indisponible.');
        }
    },

    /**
     * Injecte les valeurs reçues dans les cartes.
     *
     * @param {object} periods Valeurs par période (jour / semaine / mois).
     * @param {object} bounds  Bornes de chaque période (meta.periods), pour
     *                        afficher l'intervalle de dates couvert.
     */
    render(periods, bounds) {
        ['day', 'week', 'month'].forEach((key) => {
            const period = periods[key];
            this.setPeriod(key, bounds?.[key]);

            if (!period) {
                this.setValue(key, '— Fc');
                this.setCount(key, 'Données indisponibles');
                return;
            }

            if (period.error) {
                this.setValue(key, '— Fc');
                this.setCount(key, 'Indisponible');
                this.markError(key);
                return;
            }

            this.setValue(key, this.formatMoney(period.ttc));
            this.setCount(key, this.formatCount(period.invoice_count));
        });
    },

    /**
     * État d'erreur : les 3 cartes sont signalées, sans bloquer la page.
     */
    renderError(message) {
        ['day', 'week', 'month'].forEach((key) => {
            this.setValue(key, '— Fc');
            this.setCount(key, 'Indisponible');
            this.markError(key);
        });
        console.warn('[DgiDashboardStats] ' + message);
    },

    setValue(key, text) {
        const el = document.querySelector(`[data-dgi-stat="${key}"]`);
        if (el) el.textContent = text;
    },

    setCount(key, text) {
        const el = document.querySelector(`[data-dgi-stat-count="${key}"]`);
        if (el) el.textContent = text;
    },

    /**
     * Affiche l'intervalle de dates couvert par la période.
     */
    setPeriod(key, bounds) {
        const el = document.querySelector(`[data-dgi-stat-period="${key}"]`);
        if (!el) return;
        el.textContent = this.formatPeriod(key, bounds);
    },

    /**
     * Formate l'intervalle de la période.
     *
     * - jour    : « 11/11/2026 »
     * - semaine : « 05/11/2026 → 11/11/2026 »
     * - mois    : « 01/11/2026 → 30/11/2026 »
     */
    formatPeriod(key, bounds) {
        if (!bounds || !bounds.start || !bounds.end) {
            return '—';
        }

        const start = this.formatDate(bounds.start);
        const end = this.formatDate(bounds.end);

        // Période d'un seul jour : on n'affiche qu'une date.
        if (start === end) {
            return start;
        }

        return `${start} → ${end}`;
    },

    /**
     * Date SQL (`Y-m-d H:i:s`) au format français court.
     *
     * ⚠️ Le formatage se fait DIRECTEMENT sur la chaîne : passer par
     * `new Date()` interpréterait la date dans le fuseau du navigateur et
     * pourrait décaler l'affichage d'un jour (les bornes sont des dates SQL
     * locales, horloge serveur).
     */
    formatDate(value) {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return String(value).slice(0, 10);
        }
        return `${match[3]}/${match[2]}/${match[1]}`;
    },

    /**
     * Signale visuellement une période en erreur.
     */
    markError(key) {
        const el = document.querySelector(`[data-dgi-stat="${key}"]`);
        if (el) {
            el.style.opacity = '0.5';
            el.title = 'Données DGI indisponibles pour cette période.';
        }
    },

    /**
     * Montant au format français (2 décimales), comme `number_format()`.
     */
    formatMoney(amount) {
        const value = Number(amount) || 0;
        return value.toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' Fc';
    },

    formatCount(count) {
        const value = Number(count) || 0;
        return value.toLocaleString('fr-FR') + (value > 1 ? ' transactions' : ' transaction');
    },
};

document.addEventListener('DOMContentLoaded', () => {
    DgiDashboardStats.init();
});
