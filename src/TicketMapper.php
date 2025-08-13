<?php

namespace FediversePlugin;

use FediversePlugin\API\FediverseAPIInterface;
use Ticket;
use User;
use Db;

/**
 * TicketMapper is responsible for converting abuse reports
 * into osTicket tickets, and managing their updates.
 */
class TicketMapper
{
    /**
     * Import an abuse report as a new or updated ticket.
     *
     * @param array $report             The report data from the remote instance.
     * @param FediverseAPIInterface $client  The API client used to retrieve the report.
     */
    public static function importReport(array $report, FediverseAPIInterface $client): void
    {
        $reportId = $report['id'];
        $server = $client->getDomain();

        // Generate unique report key (domain + reportId)
        $reportKey = "{$server}:{$reportId}";

        // Check if this report has already been imported
        $sql = "SELECT ticket_id FROM plugin_fediverse_reports WHERE report_key = ?";
        $row = Db::connection()->query($sql, [$reportKey])->fetch();

        if ($row) {
            \FediversePlugin\Logger::log("duplicate_report", $server, $reportId, null, "Report already exists.");
            return;
        }

        // Extract fields
        $reason = $report['comment'] ?? 'No reason provided';
        $reporter = $report['account']['acct'] ?? 'Unknown reporter';
        $reportedUser = $report['target_account']['acct'] ?? 'Unknown user';
        $createdAt = $report['created_at'] ?? date('c');

        $subject = "Abuse Report: {$reportedUser} on {$server}";
        $body = <<<EOT
Report ID: {$reportId}
Reported User: {$reportedUser}
Reporter: {$reporter}
Reason: {$reason}
Source: {$server}
Created At: {$createdAt}
EOT;

        // Create or find user
        $user = User::lookupByEmail("reporter@{$server}") 
             ?: User::create(['name' => $reporter, 'email' => "reporter@{$server}"]);

        // Create the ticket
        $ticket = Ticket::create([
            'user' => $user,
            'subject' => $subject,
            'message' => $body,
            'source' => 'API',
        ]);

        if ($ticket) {
            \FediversePlugin\Logger::log("ticket_created", $server, $reportId, $ticket->getId(), "Ticket successfully created.");
            // Store mapping in plugin DB table
            $sql = "INSERT INTO plugin_fediverse_reports (report_key, ticket_id, raw_data) VALUES (?, ?, ?)";
            Db::connection()->query($sql, [
                $reportKey,
                $ticket->getId(),
                json_encode($report)
            ]);
        }
    }
}
