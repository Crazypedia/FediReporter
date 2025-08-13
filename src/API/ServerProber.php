<?php

namespace FediversePlugin\API;

use FediversePlugin\API\APIException;

/**
 * ServerProber detects the type of Fediverse server (Mastodon, Misskey, etc.)
 * based on common instance metadata endpoints like /.well-known/nodeinfo.
 */
class ServerProber
{
    /**
     * Probe a server and return structured info about its platform and version.
     *
     * @param string $domain
     * @return array
     * @throws APIException
     */
    public static function probe(string $domain): array
    {
        $baseUrl = "https://{$domain}";

        // Try Nodeinfo discovery
        $wellKnownUrl = "{$baseUrl}/.well-known/nodeinfo";

        $nodeinfoList = self::get($wellKnownUrl);

        if (!isset($nodeinfoList['links']) || !is_array($nodeinfoList['links'])) {
            throw new APIException("No nodeinfo links found for: {$domain}");
        }

        foreach ($nodeinfoList['links'] as $link) {
            if (isset($link['href'])) {
                $nodeinfo = self::get($link['href']);

                if (isset($nodeinfo['software']['name'])) {
                    return [
                        'platform' => strtolower($nodeinfo['software']['name']),
                        'version'  => $nodeinfo['software']['version'] ?? 'unknown',
                        'url'      => $link['href'],
                        'raw'      => $nodeinfo
                    ];
                }
            }
        }

        throw new APIException("Unable to determine platform from nodeinfo for: {$domain}");
    }

    /**
     * Helper method for GET requests with basic error handling.
     *
     * @param string $url
     * @return array
     * @throws APIException
     */
    private static function get(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json
",
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new APIException("Failed to fetch URL: {$url}");
        }

        $json = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new APIException("Invalid JSON from URL: {$url}");
        }

        return $json;
    }
}
