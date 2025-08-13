<?php
if (!defined('INCLUDE_DIR')) die('No direct access.');

require_once INCLUDE_DIR . 'class.plugin.php';

require_once __DIR__ . '/lib/PlatformDetector.php';
require_once __DIR__ . '/lib/ReportImporter.php';
require_once __DIR__ . '/lib/ModerationNotes.php';

class MastoReportsPlugin extends Plugin {
    var $config_class = 'MastoReportsConfig';

    function bootstrap() {
        // Hook agent notes/replies so we can push moderation notes back
        Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);
    }

    function onThreadEntryCreated($entry) {
        try {
            if (!($entry instanceof ThreadEntry)) return;
            $thread = $entry->getThread();
            if (!$thread || $thread->getObjectType() !== 'T') return;
            $ticket = $thread->getObject();
            if (!($ticket instanceof Ticket)) return;
            $agent = $entry->getStaff();
            if (!$agent) return; // staff-only

            // Get stored metadata from importer
            $meta = $ticket->getExtraData();
            if (empty($meta['mr_platform']) || empty($meta['mr_instance']) || empty($meta['mr_target_account_id'])) return;

            $cfg = $this->getConfig();
            $notes = new MastoModerationNotes($cfg);
            $notes->sendModerationNote(
                $meta['mr_platform'],
                $meta['mr_instance'],
                $meta['mr_target_account_id'],
                $agent->getName(),
                $entry->getBody()
            );
        } catch (Throwable $e) {
            error_log("MastoReportsPlugin thread hook error: ".$e->getMessage());
        }
    }

    // Optional: create a small table to dedupe webhook deliveries
    function install() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS ost_masto_reports_imports (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                platform VARCHAR(16) NOT NULL,
                instance VARCHAR(255) NOT NULL,
                report_id VARCHAR(64) NOT NULL,
                ticket_id INT UNSIGNED NOT NULL,
                created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_platform_instance_report (platform, instance(191), report_id)
            ) ENGINE=InnoDB";
            db_query($sql);
        } catch (Throwable $e) {
            error_log("MastoReportsPlugin install notice: ".$e->getMessage());
        }
        return true;
    }
}

return array(
  'id' => 'chatgpt:masto_reports',
  'version' => '1.0.0',
  'name' => 'Masto/Misskey Reports Importer',
  'author' => 'ChatGPT',
  'description' => 'Imports reports from Mastodon, Misskey, Sharkey via webhook and syncs moderation notes.',
  'plugin' => 'plugin.php:MastoReportsPlugin',
  'config' => 'config.php:MastoReportsConfig'
);
