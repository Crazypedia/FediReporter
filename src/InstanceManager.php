<?php

namespace FediversePlugin;

use FediversePlugin\API\ServerProber;
use FediversePlugin\API\APIException;

/**
 * InstanceManager handles configuration and verification of remote instances.
 * This includes storing domain, access token, and detected server platform.
 */
class InstanceManager
{
    /**
     * Validate and register a new Fediverse instance.
     *
     * @param string $domain
     * @param string $accessToken
     * @return array
     * @throws APIException
     */
    public static function registerInstance(string $domain, string $accessToken): array
    {
        // Probe server type and metadata
        $info = ServerProber::probe($domain);

        // Optionally: store this info in DB
        // Example return:
        return [
            'domain'   => $domain,
            'token'    => $accessToken,
            'platform' => $info['platform'],
            'version'  => $info['version'],
            'verified' => true,
            'metadata' => $info['raw']
        ];
    }

    /**
     * Returns a list of all configured instances.
     * Stubbed; in real implementation this would query the DB.
     *
     * @return array[]
     */
    public static function listInstances(): array
    {
        return []; // Replace with DB-backed list
    }

    /**
     * Retrieve API client based on instance platform.
     *
     * @param string $domain
     * @param string $accessToken
     * @param string $platform
     * @return \FediversePlugin\API\FediverseAPIInterface
     * @throws APIException
     */
    public static function getClient(string $domain, string $accessToken, string $platform)
    {
        $platform = strtolower($platform);

        switch ($platform) {
            case 'mastodon':
                return new \FediversePlugin\API\MastodonAPI($domain, $accessToken);
            case 'misskey':
                return new \FediversePlugin\API\MisskeyAPI($domain, $accessToken);
            case 'lemmy':
                // TODO: Lemmy support is incomplete and disabled temporarily
                // LemmyAPI exists but needs refactoring to implement FediverseAPIInterface
                // Required changes:
                // - Implement FediverseAPIInterface instead of APIClientInterface
                // - Add missing methods: fetchReport, fetchReports, validateConnection, getDomain, fetchAccount, fetchPosts
                // - Fix method signatures to return correct types (e.g., closeReport should return bool)
                throw new APIException("Lemmy platform support is currently under development. Coming soon!");
            default:
                throw new APIException("Unsupported platform: {$platform}");
        }
    }
}
