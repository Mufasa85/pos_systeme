-- ============================================================================
-- MIGRATION : Copie de pdv/nid/token/port depuis settings vers shops
-- ============================================================================

-- Copie les clés existantes (pdv, nid, token, port) de la table settings
-- vers la table shops pour chaque boutique liée par shop_id.

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
