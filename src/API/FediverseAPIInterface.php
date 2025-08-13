<?php

namespace FediversePlugin\API;

interface FediverseAPIInterface
{
    /**
     * Fetch a single report by ID.
     */
    public function fetchReport(string $reportId): array;

    /**
     * Fetch all abuse reports (with optional filters).
     */
    public function fetchReports(array $filters = []): array;

    /**
     * Close a remote report.
     */
    public function closeReport(string $reportId): bool;

    /**
     * Post a moderation comment on a remote report.
     */
    public function postModerationComment(string $reportId, string $comment, string $agentName, string $agentDomain): bool;

    /**
     * Fetch moderation comments or history associated with a report.
     */
    public function getModerationComments(string $reportId): array;

    /**
     * Fetch account info by ID or handle.
     */
    public function fetchAccount(string $accountIdOrHandle): array;

    /**
     * Fetch related posts (statuses, notes, etc.) by IDs.
     */
    public function fetchPosts(array $postIds): array;

    /**
     * Check if the current token and domain are valid.
     */
    public function validateConnection(): bool;

    /**
     * Return the server domain this API client is connected to.
     */
    public function getDomain(): string;

    /**
     * Return the name/type of the platform (e.g., mastodon, misskey, etc.)
     */
    public function getPlatform(): string;

    /**
     * Suspend the reported account remotely.
     *
     * @param string $accountId
     * @return bool
     */
    public function suspendAccount(string $accountId): bool;

    /**
     * Block the domain of the reported account.
     *
     * @param string $domain
     * @return bool
     */
    public function blockDomain(string $domain): bool;

}
