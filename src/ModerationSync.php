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
        $ticketId = $ticket->getId();
        $reportKey = self::getReportKeyFromTicket($ticketId);

        if (!$reportKey) {
            DebugHelper::logDebug('ModerationSync', 'Ticket not linked to fediverse report, skipping note push', [
                'ticket_id' => $ticketId
            ]);
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = Instance::getByDomain($domain);

        if (!$instance) {
            DebugHelper::logWarning('ModerationSync', 'Instance not found for note push', [
                'ticket_id' => $ticketId,
                'domain' => $domain,
                'report_key' => $reportKey
            ]);
            return;
        }

        try {
            DebugHelper::logDebug('ModerationSync', 'Pushing ticket note to remote instance', [
                'ticket_id' => $ticketId,
                'domain' => $domain,
                'report_id' => $reportId
            ]);

            $client = InstanceManager::getClient($domain, $instance['token'], $instance['platform']);
            $client->postModerationComment($reportId, $note, $agentName, $agentDomain);

            DebugHelper::logSuccess('ModerationSync', 'Note pushed successfully', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey
            ]);
        } catch (APIException $e) {
            DebugHelper::logError('ModerationSync', 'Failed to push moderation comment', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function pullModerationComments(Ticket $ticket): void
    {
        $ticketId = $ticket->getId();
        $reportKey = self::getReportKeyFromTicket($ticketId);

        if (!$reportKey) {
            DebugHelper::logDebug('ModerationSync', 'Ticket not linked to fediverse report, skipping comment pull', [
                'ticket_id' => $ticketId
            ]);
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = Instance::getByDomain($domain);

        if (!$instance) {
            DebugHelper::logWarning('ModerationSync', 'Instance not found for comment pull', [
                'ticket_id' => $ticketId,
                'domain' => $domain,
                'report_key' => $reportKey
            ]);
            return;
        }

        try {
            DebugHelper::logDebug('ModerationSync', 'Pulling moderation comments from remote instance', [
                'ticket_id' => $ticketId,
                'domain' => $domain,
                'report_id' => $reportId
            ]);

            $client = InstanceManager::getClient($domain, $instance['token'], $instance['platform']);
            $comments = $client->getModerationComments($reportId);

            $importedCount = 0;
            foreach ($comments as $comment) {
                $content = $comment['content'] ?? null;
                $timestamp = $comment['created_at'] ?? date('c');

                if ($content && !self::noteExists($ticketId, $content)) {
                    ThreadEntry::create([
                        'ticket_id' => $ticketId,
                        'poster' => 'Remote Moderator',
                        'body' => $content,
                        'created' => $timestamp,
                        'type' => 'note',
                        'flags' => ['internal' => true],
                    ]);
                    $importedCount++;
                }
            }

            DebugHelper::logSuccess('ModerationSync', 'Comments pulled successfully', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey,
                'imported' => $importedCount,
                'total' => count($comments)
            ]);
        } catch (APIException $e) {
            DebugHelper::logError('ModerationSync', 'Failed to pull moderation comments', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function applyModerationOnClose(Ticket $ticket): void
    {
        $ticketId = $ticket->getId();
        $reportKey = self::getReportKeyFromTicket($ticketId);

        if (!$reportKey) {
            DebugHelper::logDebug('ModerationSync', 'Ticket not linked to fediverse report, skipping moderation actions', [
                'ticket_id' => $ticketId
            ]);
            return;
        }

        [$domain, $reportId] = explode(':', $reportKey, 2);
        $instance = \FediversePlugin\Model\Instance::getByDomain($domain);

        if (!$instance) {
            DebugHelper::logError('ModerationSync', 'Instance not found for moderation actions', [
                'ticket_id' => $ticketId,
                'domain' => $domain,
                'report_key' => $reportKey
            ]);
            return;
        }

        try {
            DebugHelper::logInfo('ModerationSync', 'Applying moderation actions on ticket close', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey
            ]);

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
                DebugHelper::logWarning('ModerationSync', 'Report data not found or empty', [
                    'ticket_id' => $ticketId,
                    'report_key' => $reportKey
                ]);
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
                    Logger::log($ticketId, $domain, $reportId, 'suspend_account', 'success', 'Account suspended.');
                    $actionsTaken[] = "account suspended";
                    DebugHelper::logSuccess('ModerationSync', 'Account suspended', ['account_id' => $accountId]);
                } catch (APIException $e) {
                    Logger::log($ticketId, $domain, $reportId, 'suspend_account', 'failure', $e->getMessage());
                    DebugHelper::logError('ModerationSync', 'Account suspension failed', [
                        'account_id' => $accountId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($block && $acctDomain) {
                try {
                    $client->blockDomain($acctDomain);
                    Logger::log($ticketId, $domain, $reportId, 'block_domain', 'success', 'Domain blocked.');
                    $actionsTaken[] = "domain blocked";
                    DebugHelper::logSuccess('ModerationSync', 'Domain blocked', ['domain' => $acctDomain]);
                } catch (APIException $e) {
                    Logger::log($ticketId, $domain, $reportId, 'block_domain', 'failure', $e->getMessage());
                    DebugHelper::logError('ModerationSync', 'Domain blocking failed', [
                        'domain' => $acctDomain,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($limit && method_exists($client, 'limitAccount') && $accountId) {
                try {
                    $client->limitAccount($accountId);
                    Logger::log($ticketId, $domain, $reportId, 'limit_account', 'success', 'Account limited.');
                    $actionsTaken[] = "account limited";
                    DebugHelper::logSuccess('ModerationSync', 'Account limited', ['account_id' => $accountId]);
                } catch (APIException $e) {
                    Logger::log($ticketId, $domain, $reportId, 'limit_account', 'failure', $e->getMessage());
                    DebugHelper::logError('ModerationSync', 'Account limitation failed', [
                        'account_id' => $accountId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($flagAccountMedia && method_exists($client, 'flagAccountMediaSensitive') && $accountId) {
                try {
                    $client->flagAccountMediaSensitive($accountId);
                    Logger::log($ticketId, $domain, $reportId, 'flag_account_media', 'success', 'Account media flagged as sensitive.');
                    $actionsTaken[] = "account media flagged sensitive";
                    DebugHelper::logSuccess('ModerationSync', 'Account media flagged', ['account_id' => $accountId]);
                } catch (APIException $e) {
                    Logger::log($ticketId, $domain, $reportId, 'flag_account_media', 'failure', $e->getMessage());
                    DebugHelper::logError('ModerationSync', 'Account media flagging failed', [
                        'account_id' => $accountId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($flagServerMedia && method_exists($client, 'flagServerMediaSensitive') && $acctDomain) {
                try {
                    $client->flagServerMediaSensitive($acctDomain);
                    Logger::log($ticketId, $domain, $reportId, 'flag_server_media', 'success', 'Server media flagged as sensitive.');
                    $actionsTaken[] = "server media flagged sensitive";
                    DebugHelper::logSuccess('ModerationSync', 'Server media flagged', ['domain' => $acctDomain]);
                } catch (APIException $e) {
                    Logger::log($ticketId, $domain, $reportId, 'flag_server_media', 'failure', $e->getMessage());
                    DebugHelper::logError('ModerationSync', 'Server media flagging failed', [
                        'domain' => $acctDomain,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $summary = "Remote report closed.";
            if ($actionsTaken) {
                $summary .= " Actions: " . implode(', ', $actionsTaken) . ".";
            } else {
                $summary .= " No further actions taken.";
            }

            \ThreadEntry::create([
                'ticket_id' => $ticketId,
                'poster' => 'Fediverse Plugin',
                'body' => $summary,
                'type' => 'note',
                'flags' => ['internal' => true],
            ]);

            DebugHelper::logSuccess('ModerationSync', 'Moderation actions applied successfully', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey,
                'actions' => $actionsTaken
            ]);

        } catch (\FediversePlugin\API\APIException $e) {
            DebugHelper::logError('ModerationSync', 'Moderation sync on close failed', [
                'ticket_id' => $ticketId,
                'report_key' => $reportKey,
                'error' => $e->getMessage()
            ]);
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
