-- =====================================================
-- Migration: Ajout du paramètre paper_type
-- =====================================================
-- Ce paramètre définit le format d'impression utilisé pour
-- les tickets et factures (80mm, 57mm, A4, A5, Letter, etc.)
-- Valeur par défaut: 80mm (imprimante POS thermique standard)
-- =====================================================

INSERT INTO
    settings (setting_key, value)
VALUES ('paper_type', '80mm')
ON DUPLICATE KEY UPDATE
    value = VALUES(value);