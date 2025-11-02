-- OSTicket Fediverse Moderation Plugin - Database Uninstallation
-- WARNING: This will delete all plugin data including reports, instances, and logs
-- Run this SQL script to manually remove plugin tables

DROP TABLE IF EXISTS `plugin_fediverse_moderation_log`;
DROP TABLE IF EXISTS `plugin_fediverse_reports`;
DROP TABLE IF EXISTS `plugin_fediverse_instances`;
