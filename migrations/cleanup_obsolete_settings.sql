-- ============================================================================
-- NETTOYAGE : Suppression des clés obsolètes dans `settings`
-- ============================================================================
-- Ces clés (store_name, store_address, store_phone, store_email, store_ice,
-- store_rccm, store_isf, pdv, nid, token, port) ne sont plus lues ni écrites
-- par SettingsController (voir shopFields dans SettingsController::update()).
-- Les informations sont désormais gérées exclusivement via la table `shops`.
--
-- Ce script effectue d'abord une copie de sécurité (idempotente, ne remplace
-- jamais une valeur déjà présente dans `shops`) avant de supprimer les lignes
-- obsolètes de `settings`.
--
-- Ne touche PAS aux clés encore actives : paper_type, theme, tax_rate,
-- service_type, receipt_padding_57mm, receipt_padding_80mm.
-- ============================================================================

-- 1) Copie de sécurité vers `shops` (au cas où une valeur n'aurait pas
--    encore été synchronisée)
UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_name'
SET s.`nom` = st.`value`
WHERE s.`nom` IS NULL OR s.`nom` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_address'
SET s.`adresse` = st.`value`
WHERE s.`adresse` IS NULL OR s.`adresse` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_phone'
SET s.`telephone` = st.`value`
WHERE s.`telephone` IS NULL OR s.`telephone` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_email'
SET s.`email` = st.`value`
WHERE s.`email` IS NULL OR s.`email` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_ice'
SET s.`ice` = st.`value`
WHERE s.`ice` IS NULL OR s.`ice` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_rccm'
SET s.`rccm` = st.`value`
WHERE s.`rccm` IS NULL OR s.`rccm` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'store_isf'
SET s.`isf` = st.`value`
WHERE s.`isf` IS NULL OR s.`isf` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'pdv'
SET s.`pdv` = st.`value`
WHERE s.`pdv` IS NULL OR s.`pdv` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'nid'
SET s.`nid` = st.`value`
WHERE s.`nid` IS NULL OR s.`nid` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'token'
SET s.`token` = st.`value`
WHERE s.`token` IS NULL OR s.`token` = '';

UPDATE `shops` s
JOIN `settings` st ON st.`shop_id` = s.`id` AND st.`setting_key` = 'port'
SET s.`port` = st.`value`
WHERE s.`port` IS NULL OR s.`port` = '';

-- 2) Suppression des clés obsolètes dans `settings`
DELETE FROM `settings`
WHERE `setting_key` IN (
    'store_name', 'store_address', 'store_phone', 'store_email',
    'store_ice', 'store_rccm', 'store_isf', 'pdv', 'nid', 'token', 'port'
);
