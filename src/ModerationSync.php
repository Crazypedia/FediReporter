<?php

namespace FediversePlugin;

use FediversePlugin\Model\Instance;
use FediversePlugin\API\APIException;
use ThreadEntry;
use Ticket;
use Db;

/**
 * ModerationSync handles two-way synchronization of moderation comments
 * between osTicket tickets and remote server abuse reports.
 */
class ModerationSync
{
    public static function pushTicketNote(Ticket $ticket, string $note, string $agentName, string $agentDomain): void
    {
        $reportKey = self::getReportKeyFromTicket($ticket->getId());

        if (!$reportKey) {
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = Instance::getByDomain($domain);

        if (!$instance) {
            return;
        }

        try {
            $client = InstanceManager::getClient($domain, $instance['token'], $instance['platform']);
            $client->postModerationComment($reportId, $note, $agentName, $agentDomain);
        } catch (APIException $e) {
            error_log("Failed to push moderation comment: " . $e->getMessage());
        }
    }

    public static function pullModerationComments(Ticket $ticket): void
    {
        $reportKey = self::getReportKeyFromTicket($ticket->getId());

        if (!$reportKey) {
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = Instance::getByDomain($domain);

        if (!$instance) {
            return;
        }

        try {
            $client = InstanceManager::getClient($domain, $instance['token'], $instance['platform']);
            $comments = $client->getModerationComments($reportId);

            foreach ($comments as $comment) {
                $content = $comment['content'] ?? null;
                $timestamp = $comment['created_at'] ?? date('c');

                if ($content && !self::noteExists($ticket->getId(), $content)) {
                    ThreadEntry::create([
                        'ticket_id' => $ticket->getId(),
                        'poster' => 'Remote Moderator',
                        'body' => $content,
                        'created' => $timestamp,
                        'type' => 'note',
                        'flags' => ['internal' => true],
                    ]);
                }
            }
        } catch (APIException $e) {
            error_log("Failed to pull moderation comments: " . $e->getMessage());
        }
    }

    public static function applyModerationOnClose(Ticket $ticket): void
    {
        $reportKey = self::getReportKeyFromTicket($ticket->getId());

        if (!$reportKey) {
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = \FediversePlugin\Model\Instance::getByDomain($domain);

        if (!$instance) {
            return;
        }

        try {
            $client = \FediversePlugin\InstanceManager::getClient(
                $domain,
                $instance['token'],
                $instance['platform']
            );

            // Close the remote report
            $client->closeReport($reportId);

            // Load raw report to extract target account
            $reportData = Db::connection()->query(
                "SELECT raw_data FROM plugin_fediverse_reports WHERE report_key = ?",
                [$reportKey]
            )->fetch();

            if (!$reportData || !$reportData['raw_data']) {
                return;
            }

            $reportJson = json_decode($reportData['raw_data'], true);
            $accountId = $reportJson['target_account']['id'] ?? null;
            $acct = $reportJson['target_account']['acct'] ?? '';
            $acctDomain = explode('@', $acct)[1] ?? $domain;

            // Flags from ticket UI
            $suspend = $ticket->getField('fediverse_suspend_account') === '1';
            $block = $ticket->getField('fediverse_block_domain') === '1';
            $limit = $ticket->getField('fediverse_limit_account') === '1';
            $flagAccountMedia = $ticket->getField('fediverse_flag_account_media_sensitive') === '1';
            $flagServerMedia = $ticket->getField('fediverse_flag_server_media_sensitive') === '1';

            $actionsTaken = [];

            if ($suspend && $accountId) {
                try {
                    $client->suspendAccount($accountId);
                    // Log account suspension
                Logger::log($ticket->getId(), $domain, $reportId, 'suspend_account', 'success', 'Account suspended.');
                    $actionsTaken[] = "account suspended";
                } catch (APIException $e) {
                    // Log account suspension
                Logger::log($ticket->getId(), $domain, $reportId, 'suspend_account', 'failure', $e->getMessage());
                }
                
            }

            if ($block && $acctDomain) {
                try {
                    $client->blockDomain($acctDomain);
                    // Log domain block
                Logger::log($ticket->getId(), $domain, $reportId, 'block_domain', 'success', 'Domain blocked.');
                    $actionsTaken[] = "domain blocked";
                } catch (APIException $e) {
                    // Log domain block
                Logger::log($ticket->getId(), $domain, $reportId, 'block_domain', 'failure', $e->getMessage());
                }
                
            }

            if ($limit && method_exists($client, 'limitAccount') && $accountId) {
                try {
                    $client->limitAccount($accountId);
                    // Log account limitation
                Logger::log($ticket->getId(), $domain, $reportId, 'limit_account', 'success', 'Account limited.');
                    $actionsTaken[] = "account limited";
                } catch (APIException $e) {
                    // Log account limitation
                Logger::log($ticket->getId(), $domain, $reportId, 'limit_account', 'failure', $e->getMessage());
                }
                
            }

            if ($flagAccountMedia && method_exists($client, 'flagAccountMediaSensitive') && $accountId) {
                try {
                    $client->flagAccountMediaSensitive($accountId);
                    // Log account media sensitivity flag
                Logger::log($ticket->getId(), $domain, $reportId, 'flag_account_media', 'success', 'Account media flagged as sensitive.');
                    $actionsTaken[] = "account media flagged sensitive";
                } catch (APIException $e) {
                    // Log account media sensitivity flag
                Logger::log($ticket->getId(), $domain, $reportId, 'flag_account_media', 'failure', $e->getMessage());
                }
                
            }

            if ($flagServerMedia && method_exists($client, 'flagServerMediaSensitive') && $acctDomain) {
                try {
                    $client->flagServerMediaSensitive($acctDomain);
                    // Log server media sensitivity flag
                Logger::log($ticket->getId(), $domain, $reportId, 'flag_server_media', 'success', 'Server media flagged as sensitive.');
                    $actionsTaken[] = "server media flagged sensitive";
                } catch (APIException $e) {
                    // Log server media sensitivity flag
                Logger::log($ticket->getId(), $domain, $reportId, 'flag_server_media', 'failure', $e->getMessage());
                }
                
            }

            $summary = "Remote report closed.";
            if ($actionsTaken) {
                $summary .= " Actions: " . implode(', ', $actionsTaken) . ".";
            } else {
                $summary .= " No further actions taken.";
            }

            \ThreadEntry::create([
                'ticket_id' => $ticket->getId(),
                'poster' => 'Fediverse Plugin',
                'body' => $summary,
                'type' => 'note',
                'flags' => ['internal' => true],
            ]);

        } catch (\FediversePlugin\API\APIException $e) {
            error_log("Moderation sync on close failed: " . $e->getMessage());
        }
    }

    private static function noteExists(int $ticketId, string $content): bool
    {
        $sql = "SELECT COUNT(*) FROM thread_entry
                WHERE ticket_id = ? AND body = ? AND type = 'note'";
        $count = Db::connection()->query($sql, [$ticketId, $content])->fetchColumn();
        return $count > 0;
    }

    private static function getReportKeyFromTicket(int $ticketId): ?string
    {
        $sql = "SELECT report_key FROM plugin_fediverse_reports WHERE ticket_id = ?";
        $row = Db::connection()->query($sql, [$ticketId])->fetch();
        return $row['report_key'] ?? null;
    }
}
