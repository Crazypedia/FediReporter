<?php

namespace FediversePlugin;

/**
 * ServerProber detects the type of fediverse platform based on public API responses.
 */
class ServerProber
{
    /**
     * Probes the server's public endpoints to determine its platform.
     *
     * @param string $domain
     * @return string|null Platform ID ('mastodon', 'misskey', 'lemmy', etc.)
     */
    public static function probe(string $domain): ?string
    {
        $domain = trim($domain);

        // Try Mastodon instance detection
        $mastodon = @file_get_contents("https://{$domain}/api/v1/instance");
        if ($mastodon) {
            $json = json_decode($mastodon, true);
            if (isset($json['uri']) && isset($json['version'])) {
                return 'mastodon';
            }
        }

        // Try Misskey instance detection
        $misskey = @file_get_contents("https://{$domain}/api/meta");
        if ($misskey) {
            $json = json_decode($misskey, true);
            if (isset($json['version']) && isset($json['server'])) {
                return 'misskey';
            }
        }

        // Try Lemmy instance detection
        $lemmy = @file_get_contents("https://{$domain}/api/v3/site");
        if ($lemmy) {
            $json = json_decode($lemmy, true);
            if (isset($json['site']['name'])) {
                return 'lemmy';
            }
        }

        // Future: detect other platforms here

        return null;
    }
}
