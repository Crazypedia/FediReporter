<?php

namespace FediversePlugin\API;

use FediversePlugin\API\FediverseAPIInterface;
use FediversePlugin\API\APIException;

/**
 * MisskeyAPI handles integration with Misskey-compatible servers.
 * It implements the FediverseAPIInterface for unified access to reports,
 * accounts, and moderation actions.
 */
class MisskeyAPI implements FediverseAPIInterface
{
    private string $domain;
    private string $accessToken;
    private string $baseUrl;

    /**
     * Initialize the API client for Misskey.
     *
     * @param string $domain
     * @param string $accessToken
     */
    public function __construct(string $domain, string $accessToken)
    {
        $this->domain = $domain;
        $this->accessToken = $accessToken;
        $this->baseUrl = "https://{$domain}/api";
    }

    /**
     * Validates the token by performing a dummy authenticated request.
     */
    public function validateConnection(): bool
    {
        $url = "{$this->baseUrl}/i";

        $response = $this->post($url, ['i' => $this->accessToken]);

        return isset($response['id']);
    }

    /**
     * Fetch a single report by ID (note: actual Misskey APIs vary by fork).
     */
    public function fetchReport(string $reportId): array
    {
        // Misskey does not have a standard report endpoint across all forks.
        throw new APIException("Fetching single reports is not yet implemented in Misskey.");
    }

    /**
     * Fetch all reports (limited by admin access and implementation).
     */
    public function fetchReports(array $filters = []): array
    {
        $url = "{$this->baseUrl}/admin/abuse/notes/list";

        $data = ['i' => $this->accessToken] + $filters;

        return $this->post($url, $data);
    }

    /**
     * Attempt to close a report (not standardized; placeholder).
     */
    public function closeReport(string $reportId): bool
    {
        // No known standard API; could be custom for forks like Calckey
        throw new APIException("Closing reports is not yet supported in Misskey.");
    }

    /**
     * Post a moderation comment to a report (not supported in core).
     */
    public function postModerationComment(string $reportId, string $comment, string $agentName, string $agentDomain): bool
    {
        // Not supported natively in Misskey
        throw new APIException("Posting moderation comments is not supported in Misskey.");
    }

    /**
     * Retrieve moderation comments (unavailable).
     */
    public function getModerationComments(string $reportId): array
    {
        return []; // Misskey doesn't support this
    }

    /**
     * Fetch account info using account ID or handle.
     */
    public function fetchAccount(string $accountIdOrHandle): array
    {
        $url = "{$this->baseUrl}/users/show";

        $data = [
            'i' => $this->accessToken,
            'userId' => $accountIdOrHandle
        ];

        return $this->post($url, $data);
    }

    /**
     * Fetch related posts by ID.
     */
    public function fetchPosts(array $postIds): array
    {
        $posts = [];

        foreach ($postIds as $id) {
            $url = "{$this->baseUrl}/notes/show";
            $data = [
                'i' => $this->accessToken,
                'noteId' => $id
            ];

            $posts[] = $this->post($url, $data);
        }

        return $posts;
    }

    /**
     * Get the connected instance domain.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Return platform type.
     */
    public function getPlatform(): string
    {
        return 'misskey';
    }

    /**
     * Perform a POST request to the Misskey API.
     */
    private function post(string $url, array $data): array
    {
        $payload = json_encode($data);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json
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
    }

    /**
     * Stub: Misskey does not support account suspension via standard API.
     *
     * @param string $accountId
     * @return bool
     * @throws APIException
     */
    public function suspendAccount(string $accountId): bool
    {
        throw new APIException("Account suspension not supported in Misskey.");
    }


    /**
     * Stub: Misskey may support domain blocking via admin API in some forks.
     *
     * @param string $domain
     * @return bool
     * @throws APIException
     */
    public function blockDomain(string $domain): bool
    {
        throw new APIException("Domain blocking not supported in Misskey.");
    }
}
