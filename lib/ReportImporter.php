<?php
class MastoReportImporter {
    private $cfg;
    public function __construct($config) { $this->cfg = $config; }

    public function importFromPayload($instance, $payload) {
        // Auto-detect platform by payload
        $platform = MastoPlatformDetector::detectFromPayload($payload);
        if (!$platform) throw new Exception('Unable to detect platform from webhook payload.');

        // Normalize report
        $norm = $this->normalize($platform, $payload);
        if (!$norm || !isset($norm['report_id'])) throw new Exception('Malformed report payload.');

        // Deduplicate using table
        if ($this->exists($platform, $instance, $norm['report_id'])) return null;

        // Create ticket
        $ticket = $this->createTicket($platform, $instance, $norm);
        if ($ticket) {
            $this->remember($platform, $instance, $norm['report_id'], $ticket->getId());
        }
        return $ticket;
    }

    private function normalize($platform, $p) {
        if ($platform === 'mastodon') {
            $r = isset($p['report']) && is_array($p['report']) ? $p['report'] : $p;
            $report_id = (string)($r['id'] ?? '');
            $reporter = $r['account'] ?? [];
            $target = $r['target_account'] ?? [];
            $comment = (string)($r['comment'] ?? '');
            $created_at = $r['created_at'] ?? null;
            $status_ids = isset($r['status_ids']) && is_array($r['status_ids']) ? $r['status_ids'] : [];
            $category = strtoupper((string)($r['category'] ?? 'REPORT'));
            return [
                'report_id' => $report_id,
                'reporter' => $reporter,
                'target' => $target,
                'comment' => $comment,
                'created_at' => $created_at,
                'category' => $category,
                'status_ids' => $status_ids,
            ];
        } else { // misskey/sharkey
            $report_id = (string)($p['id'] ?? ($p['report']['id'] ?? ''));
            $reporter = $p['reporter'] ?? ($p['report']['reporter'] ?? []);
            $target = $p['targetUser'] ?? ($p['report']['targetUser'] ?? []);
            $comment = (string)($p['comment'] ?? ($p['report']['comment'] ?? ''));
            $created_at = $p['createdAt'] ?? ($p['report']['createdAt'] ?? null);
            $category = strtoupper((string)($p['category'] ?? 'REPORT'));
            return [
                'report_id' => $report_id,
                'reporter' => $reporter,
                'target' => $target,
                'comment' => $comment,
                'created_at' => $created_at,
                'category' => $category,
                'status_ids' => [],
            ];
        }
    }

    private function acctToEmail($acct) {
        $domainPref = trim((string)$this->cfg->get('synthetic_email_domain')) ?: 'reports.local';
        if (isset($acct['acct']) && $acct['acct']) {
            $handle = preg_replace('~[^a-z0-9_\-\.@]+~i', '', $acct['acct']);
            if (strpos($handle, '@') === false) $handle .= '@'.$domainPref;
            return strtolower($handle);
        }
        if (isset($acct['username']) && $acct['username']) {
            $u = preg_replace('~[^a-z0-9_\-\.]+~i', '', $acct['username']);
            return strtolower($u).'@'.$domainPref;
        }
        return 'reporter@'.$domainPref;
    }

    private function createTicket($platform, $instance, $n) {
        $subject = sprintf('Report #%s — %s', $n['report_id'], $n['category']);
        $target_handle = $n['target']['acct'] ?? ($n['target']['username'] ?? 'unknown');

        $body = [];
        $body[] = "**Platform:** ".ucfirst($platform);
        $body[] = "**Instance:** ".$instance;
        $body[] = "**Report ID:** ".$n['report_id'];
        if (!empty($n['created_at'])) $body[] = "**Created:** ".$n['created_at'];
        $body[] = "**Category:** ".$n['category'];
        $body[] = "**Target:** @".$target_handle;
        if (!empty($n['status_ids'])) $body[] = "**Status IDs:** ".implode(', ', $n['status_ids']);
        if (strlen($n['comment'])) {
            $body[] = "**Reporter comment:**\n".$n['comment'];
        }
        $body[] = "";
        $body[] = "Imported automatically via webhook.";

        $vars = [
            'name' => $n['reporter']['username'] ?? 'Reporter',
            'email' => $this->acctToEmail($n['reporter']),
            'subject' => $subject,
            'message' => implode("\n", $body),
            'ip' => '127.0.0.1',
            'source' => 'API',
        ];

        if ($topicId = (int)$this->cfg->get('default_help_topic_id')) $vars['topicId'] = $topicId;
        if ($deptId = (int)$this->cfg->get('default_department_id')) $vars['deptId'] = $deptId;
        if ($prioId = (int)$this->cfg->get('default_priority_id')) $vars['priorityId'] = $prioId;

        $ticket = Ticket::create($vars, $errors);
        if (!$ticket) throw new Exception('Ticket create failed: '.json_encode($errors));

        // Save metadata for write-back
        $meta = $ticket->getExtraData() ?: [];
        $meta['mr_platform'] = $platform;
        $meta['mr_instance'] = $instance;
        // Prefer platform-specific target IDs
        $meta['mr_target_account_id'] = $n['target']['id'] ?? ($n['target']['userId'] ?? null);
        $ticket->setExtraData($meta);

        return $ticket;
    }

    private function exists($platform, $instance, $report_id) {
        $platform = db_input($platform);
        $instance = db_input($instance);
        $report_id = db_input($report_id);
        $res = db_query("SELECT 1 FROM ost_masto_reports_imports WHERE platform='$platform' AND instance='$instance' AND report_id='$report_id' LIMIT 1");
        return ($res && db_num_rows($res) > 0);
    }

    private function remember($platform, $instance, $report_id, $ticket_id) {
        $platform = db_input($platform);
        $instance = db_input($instance);
        $report_id = db_input($report_id);
        $ticket_id = (int)$ticket_id;
        db_query("INSERT IGNORE INTO ost_masto_reports_imports (platform, instance, report_id, ticket_id) VALUES ('$platform','$instance','$report_id',$ticket_id)");
    }
}
