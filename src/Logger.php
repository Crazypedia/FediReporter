<?php

namespace FediversePlugin;

use Db;
use Exception;

/**
 * Logger handles audit logs for moderation actions.
 */
class Logger
{
    /**
     * Write a log entry for a moderation action.
     *
     * @param int $ticketId
     * @param string $domain
     * @param string $reportId
     * @param string $action
     * @param string $status
     * @param string|null $message
     * @return void
     */
    public static function log(int $ticketId, string $domain, string $reportId, string $action, string $status, ?string $message = null): void
    {
        try {
            $sql = "INSERT INTO plugin_fediverse_moderation_log
                    (ticket_id, domain, report_id, action, status, message, created)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";

            Db::connection()->query($sql, [
                $ticketId,
                $domain,
                $reportId,
                $action,
                $status,
                $message
            ]);
        } catch (Exception $e) {
            error_log("FediversePlugin Logger failed: " . $e->getMessage());
        }
    }
}
