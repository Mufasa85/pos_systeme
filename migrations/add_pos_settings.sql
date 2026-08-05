-- Migration: ajout des paramètres POS par défaut (NID, TOKEN, PORT)
-- Ces clés sont utilisées pour la connexion au serveur DGI

INSERT INTO settings (setting_key, value)
VALUES
    ('nid', 'non defini'),
    ('port', 'non defini')
ON DUPLICATE KEY UPDATE value = VALUES(value);
