<?php

namespace FediversePlugin\API;

use FediversePlugin\API\FediverseAPIInterface;
use FediversePlugin\API\APIException;

/**
 * MastodonAPI handles integration with Mastodon's admin API.
 * It implements the FediverseAPIInterface to provide unified access
 * to abuse reports, account data, and moderation features.
 */
class MastodonAPI implements FediverseAPIInterface
{
    private string $domain;
    private string $accessToken;
    private string $baseUrl;

    /**
     * Constructor to initialize the API client with server info.
     *
     * @param string $domain       Mastodon instance domain (e.g., mastodon.social)
     * @param string $accessToken  Admin access token
     */
    public function __construct(string $domain, string $accessToken)
    {
        $this->domain = $domain;
        $this->accessToken = $accessToken;
        $this->baseUrl = "https://{$domain}/api/v1";
    }

    /**
     * Validate connection and token.
     *
     * @return bool
     */
    public function validateConnection(): bool
    {
        $url = "{$this->baseUrl}/accounts/verify_credentials";

        $response = $this->get($url);

        // Check if token works and account is admin
        return isset($response['id']) && isset($response['role']) && $response['role'] === 'admin';
    }

    /**
     * Fetch a specific report by ID.
     *
     * @param string $reportId
     * @return array
     */
    public function fetchReport(string $reportId): array
    {
        // GET /api/v1/admin/reports/:id
        $url = "{$this->baseUrl}/admin/reports/{$reportId}";
        return $this->get($url);
    }

    /**
     * Fetch all abuse reports (possibly paginated).
     *
     * @param array $filters
     * @return array
     */
    public function fetchReports(array $filters = []): array
    {
        // Example endpoint: GET /api/v1/admin/reports?resolved=false
        $url = "{$this->baseUrl}/admin/reports";

        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }

        return $this->get($url);
    }

    /**
     * Close a report.
     *
     * @param string $reportId
     * @return bool
     */
    public function closeReport(string $reportId): bool
    {
        // POST /api/v1/admin/reports/:id/resolve
        $url = "{$this->baseUrl}/admin/reports/{$reportId}/resolve";

        $response = $this->post($url);

        return isset($response['resolved']) && $response['resolved'] === true;
    }

    /**
     * Post a moderation comment to a report.
     *
     * @param string $reportId
     * @param string $comment
     * @param string $agentName
     * @param string $agentDomain
     * @return bool
     */
    public function postModerationComment(string $reportId, string $comment, string $agentName, string $agentDomain): bool
    {
        // POST /api/v1/admin/reports/:id/notes
        $url = "{$this->baseUrl}/admin/reports/{$reportId}/notes";

        $data = [
            'content' => "[{$agentDomain}] {$agentName}: {$comment}"
        ];

        $response = $this->post($url, $data);

        return isset($response['id']);
    }

    /**
     * Fetch moderation comments (notes) from a report.
     *
     * @param string $reportId
     * @return array
     */
    public function getModerationComments(string $reportId): array
    {
        $report = $this->fetchReport($reportId);

        return $report['notes'] ?? [];
    }

    /**
     * Fetch account details.
     *
     * @param string $accountIdOrHandle
     * @return array
     */
    public function fetchAccount(string $accountIdOrHandle): array
    {
        // Supports both numeric ID and @handle
        if (is_numeric($accountIdOrHandle)) {
            $url = "{$this->baseUrl}/admin/accounts/{$accountIdOrHandle}";
        } else {
            $url = "{$this->baseUrl}/accounts/lookup?acct=" . urlencode($accountIdOrHandle);
        }

        return $this->get($url);
    }

    /**
     * Fetch a set of related posts (statuses) by ID.
     *
     * @param array $postIds
     * @return array
     */
    public function fetchPosts(array $postIds): array
    {
        $posts = [];

        foreach ($postIds as $id) {
            $url = "{$this->baseUrl}/statuses/{$id}";
            $posts[] = $this->get($url);
        }

        return $posts;
    }

    /**
     * Get the connected domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Return the platform type for routing and logging.
     *
     * @return string
     */
    public function getPlatform(): string
    {
        return 'mastodon';
    }

    /**
     * Helper: Perform GET request with authentication.
     *
     * @param string $url
     * @return array
     * @throws APIException
     */
    private function get(string $url): array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$this->accessToken}
"
            ]
        ];

        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            throw new APIException("GET request failed: {$url}");
        }

        return json_decode($result, true);
    }

    /**
     * Helper: Perform POST request with authentication.
     *
     * @param string $url
     * @param array|null $data
     * @return array
     * @throws APIException
     */
    private function post(string $url, array $data = null): array
    {
        $payload = $data ? json_encode($data) : '';

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$this->accessToken}
" .
                            "Content-Type: application/json
",
                'content' => $payload
            ]
        ];

        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            throw new APIException("POST request failed: {$url}");
        }

        return json_decode($result, true);
    

    /**
     * Suspend a Mastodon account using the admin API.
     *
     * @param string $accountId
     * @return bool
     */
    public function suspendAccount(string $accountId): bool
    {
        // POST /api/v1/admin/accounts/:id/action
        $url = "{$this->baseUrl}/admin/accounts/{$accountId}/action";

        $data = ['type' => 'suspend'];

        $response = $this->post($url, $data);

        return isset($response['status']) && $response['status'] === 'success';
    }


    /**
     * Block a domain using the admin API.
     *
     * @param string $domain
     * @return bool
     */
    public function blockDomain(string $domain): bool
    {
        // POST /api/v1/admin/domain_blocks
        $url = "{$this->baseUrl}/admin/domain_blocks";

        $data = ['domain' => $domain];

        $response = $this->post($url, $data);

        return isset($response['id']);
    }
}
