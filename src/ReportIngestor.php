<?php

namespace FediversePlugin;

use Ticket;
use FediversePlugin\Model\Instance;
use FediversePlugin\API\APIException;
use Exception;

/**
 * ReportIngestor handles parsing, validation, and storage of abuse reports,
 * and optionally creates osTicket tickets from them.
 */
class ReportIngestor
{
    /**
     * Entry point to process an abuse report from a fediverse server.
     *
     * @param array $payload The raw decoded JSON report body.
     * @param string $domain The domain the report was received from.
     * @return Ticket|null
     */
    public static function process(array $payload, string $domain): ?Ticket
    {
        try {
            // 1. Validate report structure
            if (!self::isValidReport($payload)) {
                error_log("Invalid report structure from $domain");
                return null;
            }

            // 2. Generate unique report_key
            $reportId = $payload['id'];
            $reportKey = "{$domain}:{$reportId}";

            // 3. Prevent duplicate
            if (self::reportExists($reportKey)) {
                error_log("Duplicate report received: $reportKey");
                return null;
            }

            // 4. Save to plugin_fediverse_reports
            // Normalize Lemmy-style report structure
            if (isset($payload['post']) && isset($payload['reason']) && isset($payload['creator'])) {
                $acct = $payload['creator']['name'] . '@' . $domain;
                $payload = [
                    'id' => $reportId,
                    'comment' => $payload['reason'],
                    'created_at' => $payload['published'] ?? date('c'),
                    'target_account' => [
                        'acct' => $acct,
                        'url' => $payload['creator']['actor_id'] ?? null,
                    ],
                    'statuses' => [[
                        'account' => [
                            'acct' => $acct,
                            'display_name' => $payload['creator']['name']
                        ],
                        'created_at' => $payload['post']['published'] ?? null,
                        'content' => $payload['post']['body'] ?? $payload['post']['name'] ?? ''
                    ]]
                ];
            }

            self::storeReport($reportKey, $domain, $reportId, $payload);

            // 5. Placeholder for ticket creation
            return self::createTicketFromReport($reportKey, $payload);

            return null;

        } catch (Exception $e) {
            error_log("Report ingestion error: " . $e->getMessage());
            return null;
        }
    }

    private static function isValidReport(array $payload): bool
    {
        return isset($payload['id'], $payload['target_account']['acct']);
    }

    private static function reportExists(string $reportKey): bool
    {
        $sql = "SELECT 1 FROM plugin_fediverse_reports WHERE report_key = ?";
        $result = \Db::connection()->query($sql, [$reportKey])->fetch();
        return (bool)$result;
    }

    private static function storeReport(string $reportKey, string $domain, string $reportId, array $payload): void
    {
        $sql = "INSERT INTO plugin_fediverse_reports
                (report_key, domain, report_id, raw_data, created)
                VALUES (?, ?, ?, ?, NOW())";
        \Db::connection()->query($sql, [
            $reportKey,
            $domain,
            $reportId,
            json_encode($payload)
        ]);
    }
}
