/**
 * print-format-override.js
 * Intercepte le clic sur le bouton "Imprimer" du modal de reçu
 * pour ouvrir d'abord le modal de sélection du format d'impression.
 * Une fois le format choisi, appelle _printReceiptContentWithFormat
 * (ou _printReceiptContent en fallback) pour lancer l'impression réelle.
 *
 * Cet override est global : il s'applique à toutes les pages (caisse, recharges,
 * historique) qui partagent le même receipt-modal du footer.php.
 */
(function () {
    'use strict';

    function attach() {
        const printBtn = document.getElementById('print-receipt');
        if (!printBtn) return;

        // Eviter de ré-attacher plusieurs fois (HMR, re-render)
        if (printBtn.dataset.pfBound === '1') return;
        printBtn.dataset.pfBound = '1';

        // On supprime les anciens listeners (au cas où) en clonant le bouton
        // (les listeners attachés via addEventListener ne sont pas supprimables
        //  directement, mais on remplace par un nouveau node logique via click).
        printBtn.addEventListener('click', function (e) {
            // Si on est en train de choisir un format, on laisse passer
            // (sinon le clic ré-ouvrira le modal pendant qu'il est encore actif)
            const fmtModal = document.getElementById('print-format-modal');
            if (fmtModal && fmtModal.classList.contains('active')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            const contentEl = document.getElementById('receipt-content');
            const content = contentEl ? contentEl.innerHTML : '';

            if (!content || content.trim() === '') {
                alert('Aucun contenu à imprimer.');
                return;
            }

            if (typeof window.openPrintFormatModal === 'function') {
                window.openPrintFormatModal(content);
            } else if (typeof window._printReceiptContent === 'function') {
                // Fallback si le modal n'est pas chargé
                window._printReceiptContent(content);
            } else {
                console.error('[print-format-override] Aucune fonction d\'impression disponible.');
            }
        }, true); // capture phase pour passer avant les autres handlers
    }

    // Attendre que le DOM soit prêt (le script est en fin de body, mais on
    //  protège contre un éventuel chargement en head)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }

    // Re-attacher si le bouton est réinjecté dynamiquement (certains flows
    //  remplacent #receipt-content via innerHTML)
    const observer = new MutationObserver(function () {
        if (document.getElementById('print-receipt')) attach();
    });
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }
})();
