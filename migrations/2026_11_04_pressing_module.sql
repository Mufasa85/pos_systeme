-- ============================================================================
-- MIGRATION : Module Pressing — schema du suivi des commandes de pressing
-- Date       : 2026-11-04
-- Reference  : docs/PLAN_PRESSING.md
--
-- Fusionne l'appli autonome `pressing/` dans le POS. Le schema a ete revu
-- pour supprimer les redondances avec les tables deja presentes :
--   - `clients_pressing`        -> table `clients` (clients du POS)
--   - `suivi_articles_pressing` -> table `audit_logs` (journal d'actions)
--   - colonne `nif` (5 tables)  -> `shops.isf` + `pressing_commandes.shop_id`
--
-- Il reste 3 tables : le dossier, les vetements, les encaissements pressing.
--
-- A executer sur la base du POS (pos_system).
-- Idempotent : peut etre rejoue sans erreur.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Dossier de pressing (un par vente POS)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pressing_commandes` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `vente_id`   INT NULL COMMENT 'FK ventes(id) : vente POS a l origine du dossier',
  `numero`     VARCHAR(20) NOT NULL COMMENT 'Numero interne PR-00001',
  `facture`    VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'Numero de facture DGI',
  `client_id`  INT NULL COMMENT 'FK clients(id) : detache si le client est supprime',
  `date_depot` DATETIME NOT NULL COMMENT 'Date et heure de depot du linge',
  `shop_id`    INT NULL COMMENT 'FK shops(id) : perimetre multi-boutique',
  `user_id`    INT NULL COMMENT 'FK utilisateurs(id) : agent ayant cree le dossier',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pressing_cmd_numero` (`numero`),
  KEY `idx_pressing_cmd_shop` (`shop_id`),
  KEY `idx_pressing_cmd_vente` (`vente_id`),
  KEY `idx_pressing_cmd_facture` (`facture`),
  KEY `idx_pressing_cmd_client` (`client_id`),
  CONSTRAINT `fk_pressing_cmd_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pressing_cmd_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dossiers de pressing';

-- ----------------------------------------------------------------------------
-- 2. Vetements d'un dossier (avec leur etat d'avancement)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pressing_articles` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `commande_id`   INT NOT NULL COMMENT 'FK pressing_commandes(id)',
  `type_vetement` VARCHAR(80) NOT NULL,
  `couleur`       VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  `quantite`      INT NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Prix final, coefficient deja applique',
  `coefficient`   DECIMAL(8,2) NOT NULL DEFAULT 1.00 COMMENT 'Multiplicateur de prix (express, delicat)',
  `observation`   VARCHAR(255) NOT NULL DEFAULT '',
  `delai_type`    ENUM('normal','express','perso') NOT NULL DEFAULT 'normal',
  `delai_heures`  INT NOT NULL DEFAULT 48,
  `date_dispo`    DATETIME NULL COMMENT 'Heure estimee de disponibilite',
  `etape`         TINYINT NOT NULL DEFAULT 0 COMMENT '0 Depose -> 6 Remis',
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pressing_art_cmd` (`commande_id`),
  KEY `idx_pressing_art_etape` (`etape`),
  CONSTRAINT `fk_pressing_art_cmd` FOREIGN KEY (`commande_id`) REFERENCES `pressing_commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Vetements d un dossier de pressing';

-- ----------------------------------------------------------------------------
-- 3. Encaissements pressing
-- ----------------------------------------------------------------------------
-- Le pressing a son propre flux d encaissement : le total du dossier (prix x
-- coefficient) differe du montant de la vente POS et du total de la facture
-- DGI. Ces paiements ne sont donc PAS les paiements de `ventes.payments`.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pressing_paiements` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `commande_id`   INT NOT NULL COMMENT 'FK pressing_commandes(id)',
  `montant`       DECIMAL(12,2) NOT NULL,
  `mode`          ENUM('ESPECES','MOBILEMONEY','VIREMENT') NOT NULL DEFAULT 'ESPECES',
  `user_id`       INT NULL COMMENT 'FK utilisateurs(id) : agent ayant encaisse',
  `date_paiement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pressing_pay_cmd` (`commande_id`),
  CONSTRAINT `fk_pressing_pay_cmd` FOREIGN KEY (`commande_id`) REFERENCES `pressing_commandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_pay_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Encaissements pressing hors paiement de la vente POS';

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 4. Acces au module Pressing — piloté par l'API OSAT (flag `pressing`)
-- ----------------------------------------------------------------------------
-- AUCUNE colonne n'est necessaire : l'acces au module suit exactement le
-- meme mecanisme que /recharges, /payroll et /hotel, c'est-a-dire le flag
-- `pressing` renvoye par l'API OSAT :
--
--     GET https://osat-energie.com/dgi/option_client/feedback.php?nif={isf}
--     -> { "success": true, "data": { ..., "pressing": 1 } }
--
-- Le flag est lu par `RechargeAccessService::canAccessPressing()` et mis en
-- cache 5 min en session (`$_SESSION['feature_access_cache']`).
--
-- Si une version anterieure du module avait cree `shops.acces_pressing`,
-- on la supprime ici pour ne pas laisser une colonne morte qui donnerait
-- l'illusion d'un second systeme d'acces.
DELIMITER $$

DROP PROCEDURE IF EXISTS drop_shops_acces_pressing_column_if_exists$$
CREATE PROCEDURE drop_shops_acces_pressing_column_if_exists()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shops'
          AND column_name = 'acces_pressing'
    ) THEN
        ALTER TABLE `shops`
            DROP COLUMN `acces_pressing`;
    END IF;
END$$

DELIMITER ;

CALL drop_shops_acces_pressing_column_if_exists();

DROP PROCEDURE IF EXISTS drop_shops_acces_pressing_column_if_exists;

-- ----------------------------------------------------------------------------
-- 5. `clients.nom_client` elargi (VARCHAR(50) -> VARCHAR(120))
-- ----------------------------------------------------------------------------
-- Le pressing accepte des noms de clients plus longs que le POS n'en
-- attendait. Idempotent : un MODIFY repetitif ne change rien.
-- ----------------------------------------------------------------------------
ALTER TABLE `clients`
    MODIFY COLUMN `nom_client` VARCHAR(120) NOT NULL;

-- ----------------------------------------------------------------------------
-- 6. `clients.numero_normalise` : colonne generee pour la recherche client
-- ----------------------------------------------------------------------------
-- Le pressing stocke les numeros au format RDC ('+243 89 548 96 08') alors
-- que le POS les stocke bruts ('0895511485'). Sans normalisation, la meme
-- personne est creee en double.
--
-- Colonne GENERATED STORED : MySQL calcule la valeur a l'ecriture, donc le
-- code applicatif n'a plus a maintenir la regle. Indexee, elle rend la
-- recherche par telephone indexee.
--
-- La regle (identique a App\Models\Client::normaliserNumero()) :
--   1) ne garder que les chiffres ;
--   2) retirer le prefixe international '243' ;
--   3) retirer le '0' de tete (format local).
-- ----------------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS add_clients_numero_normalise_if_missing$$
CREATE PROCEDURE add_clients_numero_normalise_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'clients'
          AND column_name = 'numero_normalise'
    ) THEN
        ALTER TABLE `clients`
            ADD COLUMN `numero_normalise` VARCHAR(30)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN REGEXP_REPLACE(`numero`, '[^0-9]', '') LIKE '243%'
                         AND LENGTH(REGEXP_REPLACE(`numero`, '[^0-9]', '')) = 12
                            THEN SUBSTRING(REGEXP_REPLACE(`numero`, '[^0-9]', ''), 4)
                        WHEN LEFT(REGEXP_REPLACE(`numero`, '[^0-9]', ''), 1) = '0'
                            THEN SUBSTRING(REGEXP_REPLACE(`numero`, '[^0-9]', ''), 2)
                        ELSE REGEXP_REPLACE(`numero`, '[^0-9]', '')
                    END
                ) STORED;
    END IF;
END$$

DELIMITER ;

CALL add_clients_numero_normalise_if_missing();

DROP PROCEDURE IF EXISTS add_clients_numero_normalise_if_missing;

-- Index sur la colonne generee (idempotent)
DELIMITER $$

DROP PROCEDURE IF EXISTS add_idx_client_numero_normalise_if_missing$$
CREATE PROCEDURE add_idx_client_numero_normalise_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'clients'
          AND index_name = 'idx_client_numero_normalise'
    ) THEN
        CREATE INDEX `idx_client_numero_normalise` ON `clients` (`numero_normalise`);
    END IF;
END$$

DELIMITER ;

CALL add_idx_client_numero_normalise_if_missing();

DROP PROCEDURE IF EXISTS add_idx_client_numero_normalise_if_missing;

-- ----------------------------------------------------------------------------
-- 7. Verification du perimetre multi-boutique
-- ----------------------------------------------------------------------------
-- Les dossiers sont scopes par `pressing_commandes.shop_id`, qui vaut la
-- boutique de l'utilisateur connecte au moment de la creation du dossier.
--
-- ⚠️ Un dossier dont `shop_id` est NULL est INVISIBLE dans le POS (le
-- scoping filtre dessus). Cela arrive si la boutique de l'utilisateur n'a
-- pas d'ISF, ou pour un dossier cree avant le rattachement a une boutique.
--
-- Ce controle est purement informatif : il ne modifie rien.
-- ----------------------------------------------------------------------------
SELECT
    'Dossiers sans boutique (shop_id IS NULL) — invisibles dans le POS' AS `avertissement`,
    COUNT(*) AS `nombre`,
    GROUP_CONCAT(DISTINCT `facture` ORDER BY `facture` SEPARATOR ', ') AS `factures`
FROM `pressing_commandes`
WHERE `shop_id` IS NULL
  AND `facture` <> '';
