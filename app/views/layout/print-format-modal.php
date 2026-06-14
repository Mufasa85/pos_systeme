<?php

/**
 * Modal de sélection du format d'impression.
 * Affiché quand l'utilisateur clique sur "Imprimer" depuis le reçu.
 * Propose : 57 mm, 80 mm, A4, A5, Letter, Legal.
 *
 * Exposé via JS : window.openPrintFormatModal(content)
 *   - Ouvre le modal, pré-sélectionne le format configuré.
 *   - Au clic d'un format : appelle window._printReceiptContentWithFormat(content, format).
 *   - Au clic "Imprimer" : imprime avec le format actuellement sélectionné.
 *
 * Intercepte aussi le clic sur #print-receipt (en capture phase) pour ouvrir
 * directement le modal au lieu d'imprimer avec le format par défaut.
 */
?>
<style>
    /* ===== Modal de sélection du format d'impression ===== */
    #print-format-modal .modal-content {
        max-width: 520px;
        padding: 0;
    }

    #print-format-modal .modal-header {
        border-bottom: 1px solid var(--border);
        padding: 1rem 1.25rem;
    }

    #print-format-modal .modal-header h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.05rem;
    }

    #print-format-modal .modal-body {
        padding: 1.25rem;
    }

    #print-format-modal .modal-footer {
        border-top: 1px solid var(--border);
        padding: 1rem 1.25rem;
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .print-format-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    .print-format-option {
        border: 2px solid var(--border);
        border-radius: 10px;
        padding: 0.85rem 0.6rem;
        text-align: center;
        cursor: pointer;
        background: var(--card);
        transition: all 0.15s ease;
        position: relative;
        user-select: none;
    }

    .print-format-option:hover {
        border-color: var(--primary);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .print-format-option.selected {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(11, 94, 136, 0.06) 0%, rgba(42, 183, 230, 0.06) 100%);
    }

    .print-format-option.selected::after {
        content: "✓";
        position: absolute;
        top: 6px;
        right: 8px;
        background: var(--primary);
        color: #fff;
        width: 18px;
        height: 18px;
        line-height: 18px;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 700;
    }

    .print-format-option .pf-icon {
        width: 44px;
        height: 44px;
        margin: 0 auto 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--background);
        border-radius: 8px;
        color: var(--primary);
    }

    .print-format-option.selected .pf-icon {
        background: var(--primary);
        color: #fff;
    }

    .print-format-option .pf-label {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--foreground);
    }

    .print-format-option .pf-desc {
        font-size: 0.7rem;
        color: var(--muted);
        margin-top: 2px;
        line-height: 1.2;
    }

    #print-format-modal .pf-hint {
        margin-top: 0.85rem;
        font-size: 0.75rem;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    #print-format-modal .pf-current {
        background: var(--background);
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        color: var(--muted);
    }

    #print-format-modal .pf-current strong {
        color: var(--primary);
        font-weight: 600;
    }

    @media (max-width: 520px) {
        .print-format-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Modal de sélection du format d'impression -->
<div id="print-format-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Choisir le format d'impression
            </h3>
            <button type="button" class="close-modal" onclick="closePrintFormatModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="pf-current">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Format par défaut : <strong id="pf-current-label">80 mm</strong></span>
            </div>

            <div class="print-format-grid" id="print-format-grid">
                <!-- Généré dynamiquement par JS -->
            </div>

            <div class="pf-hint">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                Sélectionnez le format puis cliquez sur "Imprimer".
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closePrintFormatModal()">
                Annuler
            </button>
            <button type="button" class="btn btn-primary" id="pf-confirm-print" onclick="confirmPrintFormat()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Imprimer
            </button>
        </div>
    </div>
</div>

<script>
    /**
     * Gestion du modal de sélection du format d'impression.
     * Stocke temporairement le contenu à imprimer et le format sélectionné.
     *
     * Intercepte aussi le clic sur #print-receipt (en capture phase) pour ouvrir
     * directement ce modal, au lieu de lancer l'impression avec le format par défaut.
     */
    (function() {
        'use strict';

        let _printContent = null;
        let _selectedFormat = '80mm';

        // Icônes SVG pour chaque type de format
        const FORMAT_ICONS = {
            '57mm': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12v20H6z"/><line x1="9" y1="7" x2="15" y2="7"/><line x1="9" y1="11" x2="15" y2="11"/><line x1="9" y1="15" x2="13" y2="15"/></svg>',
            '80mm': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2h14v20H5z"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="8" y1="18" x2="13" y2="18"/></svg>',
            'A4': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/></svg>',
            'A5': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/></svg>',
            'Letter': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="1"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/></svg>',
            'Legal': '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="1"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="8" y1="18" x2="13" y2="18"/></svg>'
        };

        // Construit la grille de choix à partir de window.PAPER_FORMATS
        function buildFormatGrid(selectedKey) {
            const grid = document.getElementById('print-format-grid');
            if (!grid) return;
            grid.innerHTML = '';

            const formats = (typeof window.PAPER_FORMATS !== 'undefined') ? window.PAPER_FORMATS : [];

            formats.forEach(function(fmt) {
                const isSelected = fmt.key === selectedKey;
                const opt = document.createElement('div');
                opt.className = 'print-format-option' + (isSelected ? ' selected' : '');
                opt.dataset.format = fmt.key;
                opt.innerHTML =
                    '<div class="pf-icon">' + (FORMAT_ICONS[fmt.key] || FORMAT_ICONS['80mm']) + '</div>' +
                    '<div class="pf-label">' + fmt.label + '</div>' +
                    '<div class="pf-desc">' + fmt.description + '</div>';
                opt.addEventListener('click', function() {
                    _selectedFormat = fmt.key;
                    document.querySelectorAll('.print-format-option').forEach(function(el) {
                        el.classList.toggle('selected', el.dataset.format === _selectedFormat);
                    });
                });
                grid.appendChild(opt);
            });
        }

        function updateCurrentLabel() {
            const lbl = document.getElementById('pf-current-label');
            if (!lbl) return;
            const cur = (typeof window.getCurrentPaperType === 'function') ? window.getCurrentPaperType() : '80mm';
            const fmt = (window.PAPER_FORMATS || []).find(function(f) {
                return f.key === cur;
            });
            lbl.textContent = fmt ? fmt.label : cur;
        }

        // API publique
        window.openPrintFormatModal = function(content) {
            _printContent = content;
            const current = (typeof window.getCurrentPaperType === 'function') ? window.getCurrentPaperType() : '80mm';
            _selectedFormat = current || '80mm';
            buildFormatGrid(_selectedFormat);
            updateCurrentLabel();

            const modal = document.getElementById('print-format-modal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        };

        window.closePrintFormatModal = function() {
            const modal = document.getElementById('print-format-modal');
            if (modal) modal.classList.remove('active');
            document.body.style.overflow = '';
        };

        window.confirmPrintFormat = function() {
            if (!_printContent) {
                closePrintFormatModal();
                return;
            }
            const fmt = _selectedFormat;
            const content = _printContent;
            closePrintFormatModal();
            // Petit délai pour laisser le modal se fermer avant l'impression
            setTimeout(function() {
                if (typeof window._printReceiptContentWithFormat === 'function') {
                    window._printReceiptContentWithFormat(content, fmt);
                } else if (typeof window._printReceiptContent === 'function') {
                    window._printReceiptContent(content);
                } else {
                    console.error('Aucune fonction d\'impression disponible.');
                }
            }, 200);
        };

        // Fermer le modal si on clique sur l'overlay
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('print-format-modal');
            if (modal && modal.classList.contains('active') && e.target === modal) {
                closePrintFormatModal();
            }
        });

        // Fermer avec Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('print-format-modal');
                if (modal && modal.classList.contains('active')) {
                    closePrintFormatModal();
                }
            }
        });

        // ====================================================================
        // OVERRIDE du bouton #print-receipt : ouvre le modal de format
        // au lieu de lancer l'impression immédiatement.
        // ====================================================================
        function attachPrintOverride() {
            const printBtn = document.getElementById('print-receipt');
            if (!printBtn) return;
            if (printBtn.dataset.pfBound === '1') return;
            printBtn.dataset.pfBound = '1';

            printBtn.addEventListener('click', function(e) {
                // Si le modal de format est déjà ouvert, ne pas ré-ouvrir
                const fmtModal = document.getElementById('print-format-modal');
                if (fmtModal && fmtModal.classList.contains('active')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return;
                }

                // Toujours bloquer l'impression directe et ouvrir le modal
                e.preventDefault();
                e.stopImmediatePropagation();

                const contentEl = document.getElementById('receipt-content');
                const content = contentEl ? contentEl.innerHTML : '';

                if (!content || content.trim() === '') {
                    alert('Aucun contenu à imprimer.');
                    return;
                }

                window.openPrintFormatModal(content);
            }, true); // capture phase pour passer avant les autres listeners
        }

        // Attacher dès que le DOM est prêt
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachPrintOverride);
        } else {
            attachPrintOverride();
        }

        // Réattacher si le bouton est réinjecté dynamiquement
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function() {
                if (document.getElementById('print-receipt')) attachPrintOverride();
            });
            if (document.body) {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                });
            }
        }
    })();
</script>