-- OSTicket Fediverse Moderation Plugin - Database Installation
-- Run this SQL script if manual installation is required
-- Or simply activate the plugin in OSTicket Admin Panel to auto-install

-- Table 1: Stores abuse reports from fediverse instances
CREATE TABLE IF NOT EXISTS `plugin_fediverse_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `report_key` VARCHAR(255) NOT NULL UNIQUE,
    `domain` VARCHAR(255) NOT NULL,
    `report_id` VARCHAR(255) NOT NULL,
    `ticket_id` INT(11) NULL,
    `raw_data` TEXT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`),
    KEY `domain` (`domain`),
    KEY `report_key` (`report_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 2: Stores configured fediverse instances with credentials
CREATE TABLE IF NOT EXISTS `plugin_fediverse_instances` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `domain` VARCHAR(255) NOT NULL UNIQUE,
    `token` TEXT NOT NULL,
    `platform` VARCHAR(50) NOT NULL,
    `version` VARCHAR(50) NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    `last_polled` DATETIME NULL,
    `metadata` TEXT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `domain` (`domain`),
    KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 3: Audit log for all moderation actions
CREATE TABLE IF NOT EXISTS `plugin_fediverse_moderation_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `domain` VARCHAR(255) NOT NULL,
    `report_id` VARCHAR(255) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `message` TEXT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`),
    KEY `domain` (`domain`),
    KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
