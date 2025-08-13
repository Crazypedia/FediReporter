<?php

namespace FediversePlugin\API;

use Exception;

/**
 * LemmyAPI implements the APIClientInterface for Lemmy-based servers.
 */
class LemmyAPI implements APIClientInterface
{
    private $domain;
    private $token;

    public function __construct(string $domain, string $token)
    {
        $this->domain = $domain;
        $this->token = $token;
    }

    public function getPlatform(): string
    {
        return 'lemmy';
    }

    public function getReport(string $reportId): array
    {
        $url = "https://{$this->domain}/api/v3/modlog";
        $json = $this->get($url);

        foreach ($json['reports'] ?? [] as $report) {
            if ((string)($report['id'] ?? null) === $reportId) {
                return $report;
            }
        }

        throw new APIException("Report not found in Lemmy modlog.");
    }

    public function closeReport(string $reportId): void
    {
        $url = "https://{$this->domain}/api/v3/report/resolve";
        $this->post($url, [
            'report_id' => (int)$reportId,
            'resolved' => true,
            'auth' => $this->token
        ]);
    }

    public function postModerationComment(string $reportId, string $comment, string $agentName, string $agentDomain): void
    {
        $url = "https://{$this->domain}/api/v3/mod/add_note";
        $note = "[{$agentName}@{$agentDomain}] {$comment}";
        $this->post($url, [
            'report_id' => (int)$reportId,
            'content' => $note,
            'auth' => $this->token
        ]);
    }

    public function suspendAccount(string $accountId): void
    {
        $url = "https://{$this->domain}/api/v3/user/ban";
        $this->post($url, [
            'user_id' => (int)$accountId,
            'ban' => true,
            'remove_data' => false,
            'reason' => 'Abuse report',
            'auth' => $this->token
        ]);
    }

    public function blockDomain(string $domain): void
    {
        // Lemmy does not support domain blocking natively
        // Placeholder for future federation control
        throw new APIException("Block domain not supported for Lemmy.");
    }

    public function getModerationComments(string $reportId): array
    {
        return []; // Not supported or not exposed cleanly
    }

    public function flagAccountMediaSensitive(string $accountId): void
    {
        // No such concept in Lemmy
    }

    public function flagServerMediaSensitive(string $domain): void
    {
        // No such concept in Lemmy
    }

    public function limitAccount(string $accountId): void
    {
        // Could be implemented via user ban/mute in future
    }

    private function get(string $url): array
    {
        $opts = ['http' => ['method' => 'GET', 'header' => "Accept: application/json"]];
        $ctx = stream_context_create($opts);
        $resp = file_get_contents($url, false, $ctx);

        if (!$resp) {
            throw new APIException("GET request failed: $url");
        }

        return json_decode($resp, true);
    }

    private function post(string $url, array $params): void
    {
        $opts = ['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json",
            'content' => json_encode($params)
        ]];

        $ctx = stream_context_create($opts);
        $resp = file_get_contents($url, false, $ctx);

        if (!$resp) {
            throw new APIException("POST request failed: $url");
        }
    }
}
