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

            // 5. Create ticket from report
            return self::createTicketFromReport($reportKey, $domain, $payload);

        } catch (Exception $e) {
            error_log("Report ingestion error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create an osTicket ticket from a fediverse abuse report.
     *
     * @param string $reportKey Unique report identifier (domain:reportId)
     * @param string $domain The fediverse instance domain
     * @param array $payload The normalized report data
     * @return Ticket|null
     */
    private static function createTicketFromReport(string $reportKey, string $domain, array $payload): ?Ticket
    {
        try {
            $reportId = $payload['id'];

            // Extract report details
            $reason = $payload['comment'] ?? 'No reason provided';
            $reportedUser = $payload['target_account']['acct'] ?? 'Unknown user';
            $reportedUserUrl = $payload['target_account']['url'] ?? '';
            $reportedUserDisplay = $payload['target_account']['display_name'] ?? $reportedUser;
            $createdAt = $payload['created_at'] ?? date('c');
            $category = $payload['category'] ?? 'violation';

            // Attempt to identify reporter (may not always be available)
            $reporter = $payload['account']['acct'] ?? $payload['reporter_id'] ?? 'System';

            // Build ticket subject
            $subject = "Fediverse Abuse Report: {$reportedUser}";

            // Build ticket body with report details
            $body = "=== FEDIVERSE ABUSE REPORT ===\n\n";
            $body .= "Report ID: {$reportId}\n";
            $body .= "Source Instance: {$domain}\n";
            $body .= "Reported At: {$createdAt}\n";
            $body .= "Category: {$category}\n\n";

            $body .= "--- Reported Account ---\n";
            $body .= "Username: {$reportedUser}\n";
            $body .= "Display Name: {$reportedUserDisplay}\n";
            if ($reportedUserUrl) {
                $body .= "Profile URL: {$reportedUserUrl}\n";
            }
            $body .= "\n";

            $body .= "--- Report Reason ---\n";
            $body .= "{$reason}\n\n";

            // Include reported posts/statuses if available
            if (isset($payload['statuses']) && is_array($payload['statuses']) && count($payload['statuses']) > 0) {
                $body .= "--- Reported Posts (" . count($payload['statuses']) . ") ---\n\n";

                foreach ($payload['statuses'] as $index => $status) {
                    $postNum = $index + 1;
                    $postId = $status['id'] ?? "unknown";
                    $postDate = $status['created_at'] ?? '';
                    $postContent = $status['content'] ?? '';

                    // Strip HTML tags for cleaner ticket display
                    $postContent = strip_tags($postContent);
                    $postContent = html_entity_decode($postContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $postContent = trim($postContent);

                    $body .= "Post #{$postNum} (ID: {$postId})\n";
                    if ($postDate) {
                        $body .= "Posted: {$postDate}\n";
                    }
                    $body .= "Content:\n{$postContent}\n\n";
                }
            }

            $body .= "--- Next Steps ---\n";
            $body .= "1. Review the reported content and account\n";
            $body .= "2. Determine appropriate moderation action\n";
            $body .= "3. Close ticket to apply selected actions to remote server\n";

            // Create or lookup user (use generic reporter account for fediverse reports)
            $userEmail = "fediverse-reports@{$domain}";
            $userName = "Fediverse Reporter ({$domain})";

            $user = \User::lookupByEmail($userEmail);
            if (!$user) {
                $user = \User::create([
                    'name' => $userName,
                    'email' => $userEmail
                ]);
            }

            if (!$user) {
                error_log("Failed to create/lookup user for report {$reportKey}");
                return null;
            }

            // Create the ticket
            $ticket = \Ticket::create([
                'user' => $user,
                'subject' => $subject,
                'message' => $body,
                'source' => 'API',
                'ip' => $domain, // Store instance domain as IP for tracking
            ]);

            if (!$ticket) {
                error_log("Failed to create ticket for report {$reportKey}");
                return null;
            }

            // Update the report record with the ticket_id
            $sql = "UPDATE plugin_fediverse_reports
                    SET ticket_id = ?
                    WHERE report_key = ?";
            \Db::connection()->query($sql, [$ticket->getId(), $reportKey]);

            // Log successful ticket creation
            Logger::log(
                $ticket->getId(),
                $domain,
                $reportId,
                'ticket_created',
                'success',
                "Ticket #{$ticket->getId()} created from report {$reportKey}"
            );

            return $ticket;

        } catch (Exception $e) {
            error_log("Failed to create ticket from report {$reportKey}: " . $e->getMessage());
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
