<?php

/**
 * PSR-4 Autoloader for FediversePlugin namespace
 */
spl_autoload_register(function ($class) {
    $prefix = 'FediversePlugin\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * FediversePlugin hooks into osTicket events to manage moderation sync.
 */
class FediversePlugin extends Plugin
{
    /**
     * Plugin version number
     */
    public $version = '0.11';

    public function bootstrap()
    {
        // Hook into ticket closure event
        Signal::connect('ticket.closed', [$this, 'onTicketClosed']);

        // Hook into new thread entries (e.g., agent notes)
        Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);
    }

    /**
     * Handles ticket closure event and triggers moderation sync.
     *
     * @param Ticket $ticket
     */
    public function onTicketClosed(Ticket $ticket)
    {
        \FediversePlugin\ModerationSync::applyModerationOnClose($ticket);
    }

    /**
     * Called when a new thread entry is created.
     * Pushes internal notes to remote server as moderation comments.
     *
     * @param ThreadEntry $entry
     */
    public function onThreadEntryCreated(ThreadEntry $entry)
    {
        // Only sync internal notes
        if (!$entry->isInternal() || $entry->getType() !== 'note') {
            return;
        }

        $ticket = $entry->getTicket();
        if (!$ticket instanceof Ticket) {
            return;
        }

        $agent = $entry->getPoster();
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';

        \FediversePlugin\ModerationSync::pushTicketNote($ticket, $entry->getBody(), $agent, $domain);
    }

    /**
     * Install plugin database tables.
     * Called when the plugin is activated in osTicket.
     *
     * @param array &$errors Array to collect error messages
     * @return bool
     */
    public function install(&$errors)
    {
        $db = \Db::connection();

        // Table 1: plugin_fediverse_reports
        // Stores abuse reports from fediverse instances
        $sql = "CREATE TABLE IF NOT EXISTS `plugin_fediverse_reports` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        if (!$db->query($sql)) {
            $errors[] = 'Failed to create plugin_fediverse_reports table';
            return false;
        }

        // Table 2: plugin_fediverse_instances
        // Stores configured fediverse instances with credentials
        $sql = "CREATE TABLE IF NOT EXISTS `plugin_fediverse_instances` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        if (!$db->query($sql)) {
            $errors[] = 'Failed to create plugin_fediverse_instances table';
            return false;
        }

        // Table 3: plugin_fediverse_moderation_log
        // Audit log for all moderation actions
        $sql = "CREATE TABLE IF NOT EXISTS `plugin_fediverse_moderation_log` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        if (!$db->query($sql)) {
            $errors[] = 'Failed to create plugin_fediverse_moderation_log table';
            return false;
        }

        return true;
    }

    /**
     * Uninstall plugin database tables.
     * Called when the plugin is removed from osTicket.
     *
     * @param array &$errors Array to collect error messages
     * @return bool
     */
    public function uninstall(&$errors)
    {
        $db = \Db::connection();

        $tables = [
            'plugin_fediverse_moderation_log',
            'plugin_fediverse_reports',
            'plugin_fediverse_instances'
        ];

        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS `{$table}`";
            if (!$db->query($sql)) {
                $errors[] = "Failed to drop table: {$table}";
                return false;
            }
        }

        return true;
    }
}
