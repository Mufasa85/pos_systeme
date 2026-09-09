-- Migration : Tables des rapports fiscaux (Z-rapports & A-rapports)
-- Date : 2026-09-09
-- Projet : SYS-POS

-- ── Table z_reports (suivi des Z-rapports) ──────────────────
CREATE TABLE IF NOT EXISTS `z_reports` (
  `id`             INT           NOT NULL AUTO_INCREMENT,
  `shop_id`        INT           DEFAULT NULL,
  `issued_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start`   DATETIME      NOT NULL COMMENT 'Début de période couverte',
  `period_end`     DATETIME      NOT NULL COMMENT 'Fin de période couverte',
  `counter`        INT           NOT NULL DEFAULT 0 COMMENT 'N° séquentiel Z-rapport',
  `total_sales_ht` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_tax`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_ttc`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `invoice_count`  INT           NOT NULL DEFAULT 0,
  `status`         ENUM('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_z_reports_shop` (`shop_id`),
  KEY `idx_z_reports_date` (`issued_at`),
  CONSTRAINT `fk_z_reports_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ── Table a_reports (suivi des A-rapports — par article) ───
CREATE TABLE IF NOT EXISTS `a_reports` (
  `id`                    INT           NOT NULL AUTO_INCREMENT,
  `shop_id`               INT           DEFAULT NULL,
  `issued_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start`          DATETIME      NOT NULL COMMENT 'Début de période couverte',
  `period_end`            DATETIME      NOT NULL COMMENT 'Fin de période couverte',
  `counter`               INT           NOT NULL DEFAULT 0 COMMENT 'N° séquentiel A-rapport',
  `articles_count`        INT           NOT NULL DEFAULT 0 COMMENT 'Nombre d''articles dans le rapport',
  `total_quantity_sold`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_quantity_returned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount_collected`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status`                ENUM('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_a_reports_shop` (`shop_id`),
  KEY `idx_a_reports_date` (`issued_at`),
  CONSTRAINT `fk_a_reports_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
