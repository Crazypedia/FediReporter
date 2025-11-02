<?php

namespace FediversePlugin;

use FediversePlugin\API\APIException;
use FediversePlugin\Model\Instance;

/**
 * OAuthHandler manages OAuth authentication flow for Fediverse instances.
 * Supports Mastodon and Misskey OAuth flows.
 */
class OAuthHandler
{
    /**
     * Register application with a Fediverse instance and get OAuth URL.
     *
     * @param string $domain The instance domain (e.g., mastodon.social)
     * @param string $platform The platform type (mastodon or misskey)
     * @return array ['auth_url' => string, 'client_id' => string, 'client_secret' => string]
     * @throws APIException
     */
    public static function registerApp(string $domain, string $platform): array
    {
        $callbackUrl = self::getCallbackUrl();

        if ($platform === 'mastodon') {
            return self::registerMastodonApp($domain, $callbackUrl);
        } elseif ($platform === 'misskey') {
            return self::registerMisskeyApp($domain, $callbackUrl);
        }

        throw new APIException("Unsupported platform for OAuth: {$platform}");
    }

    /**
     * Register app with Mastodon instance.
     *
     * @param string $domain
     * @param string $callbackUrl
     * @return array
     * @throws APIException
     */
    private static function registerMastodonApp(string $domain, string $callbackUrl): array
    {
        $url = "https://{$domain}/api/v1/apps";

        $data = [
            'client_name' => 'OSTicket Fediverse Moderation',
            'redirect_uris' => $callbackUrl,
            'scopes' => 'admin:read admin:write', // Admin scopes required
            'website' => get_osticket_url()
        ];

        DebugHelper::logInfo('OAuthHandler', 'Registering Mastodon app', [
            'domain' => $domain,
            'callback_url' => $callbackUrl
        ]);

        $response = self::httpPost($url, $data);

        if (!isset($response['client_id'], $response['client_secret'])) {
            throw new APIException("Failed to register app with {$domain}: Invalid response");
        }

        // Generate authorization URL
        $authUrl = "https://{$domain}/oauth/authorize?" . http_build_query([
            'client_id' => $response['client_id'],
            'scope' => 'admin:read admin:write',
            'redirect_uri' => $callbackUrl,
            'response_type' => 'code'
        ]);

        return [
            'auth_url' => $authUrl,
            'client_id' => $response['client_id'],
            'client_secret' => $response['client_secret']
        ];
    }

    /**
     * Register app with Misskey instance.
     *
     * @param string $domain
     * @param string $callbackUrl
     * @return array
     * @throws APIException
     */
    private static function registerMisskeyApp(string $domain, string $callbackUrl): array
    {
        // Misskey uses a different OAuth flow (MiAuth)
        $url = "https://{$domain}/api/app/create";

        $data = [
            'name' => 'OSTicket Fediverse Moderation',
            'description' => 'Moderation integration for OSTicket',
            'permission' => ['read:admin:abuse-user-reports', 'write:admin:abuse-user-reports'],
            'callbackUrl' => $callbackUrl
        ];

        DebugHelper::logInfo('OAuthHandler', 'Registering Misskey app', [
            'domain' => $domain,
            'callback_url' => $callbackUrl
        ]);

        $response = self::httpPost($url, $data);

        if (!isset($response['id'], $response['secret'])) {
            throw new APIException("Failed to register app with {$domain}: Invalid response");
        }

        // Generate MiAuth URL
        $session = bin2hex(random_bytes(16));
        $authUrl = "https://{$domain}/miauth/{$session}?" . http_build_query([
            'name' => 'OSTicket Moderation',
            'callback' => $callbackUrl,
            'permission' => 'read:admin:abuse-user-reports,write:admin:abuse-user-reports'
        ]);

        return [
            'auth_url' => $authUrl,
            'client_id' => $response['id'],
            'client_secret' => $response['secret'],
            'session' => $session
        ];
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $domain
     * @param string $platform
     * @param string $code Authorization code from callback
     * @param string $clientId
     * @param string $clientSecret
     * @return string Access token
     * @throws APIException
     */
    public static function exchangeToken(string $domain, string $platform, string $code, string $clientId, string $clientSecret): string
    {
        if ($platform === 'mastodon') {
            return self::exchangeMastodonToken($domain, $code, $clientId, $clientSecret);
        } elseif ($platform === 'misskey') {
            return self::exchangeMisskeyToken($domain, $code);
        }

        throw new APIException("Unsupported platform for token exchange: {$platform}");
    }

    /**
     * Exchange Mastodon authorization code for token.
     *
     * @param string $domain
     * @param string $code
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     * @throws APIException
     */
    private static function exchangeMastodonToken(string $domain, string $code, string $clientId, string $clientSecret): string
    {
        $url = "https://{$domain}/oauth/token";

        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => self::getCallbackUrl(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'scope' => 'admin:read admin:write'
        ];

        DebugHelper::logInfo('OAuthHandler', 'Exchanging Mastodon auth code for token', [
            'domain' => $domain
        ]);

        $response = self::httpPost($url, $data);

        if (!isset($response['access_token'])) {
            throw new APIException("Failed to get access token from {$domain}");
        }

        return $response['access_token'];
    }

    /**
     * Exchange Misskey session for token.
     *
     * @param string $domain
     * @param string $session
     * @return string
     * @throws APIException
     */
    private static function exchangeMisskeyToken(string $domain, string $session): string
    {
        $url = "https://{$domain}/api/miauth/{$session}/check";

        $response = self::httpPost($url, []);

        if (!isset($response['token'])) {
            throw new APIException("Failed to get access token from {$domain}");
        }

        return $response['token'];
    }

    /**
     * Verify that a token has admin permissions.
     *
     * @param string $domain
     * @param string $platform
     * @param string $token
     * @return array Account info if valid admin
     * @throws APIException
     */
    public static function verifyAdminToken(string $domain, string $platform, string $token): array
    {
        if ($platform === 'mastodon') {
            return self::verifyMastodonAdmin($domain, $token);
        } elseif ($platform === 'misskey') {
            return self::verifyMisskeyAdmin($domain, $token);
        }

        throw new APIException("Unsupported platform for verification: {$platform}");
    }

    /**
     * Verify Mastodon admin token.
     *
     * @param string $domain
     * @param string $token
     * @return array
     * @throws APIException
     */
    private static function verifyMastodonAdmin(string $domain, string $token): array
    {
        $url = "https://{$domain}/api/v1/accounts/verify_credentials";

        $headers = ["Authorization: Bearer {$token}"];
        $response = self::httpGet($url, $headers);

        if (!isset($response['id'])) {
            throw new APIException("Invalid token: Could not verify credentials");
        }

        // Check if user has admin or moderator role
        $role = $response['role']['name'] ?? $response['role'] ?? null;

        if (!in_array($role, ['admin', 'Admin', 'moderator', 'Moderator'], true)) {
            DebugHelper::logWarning('OAuthHandler', 'User lacks admin/moderator role', [
                'domain' => $domain,
                'username' => $response['username'] ?? 'unknown',
                'role' => $role
            ]);
            throw new APIException("Account must have admin or moderator role. Current role: " . ($role ?? 'none'));
        }

        DebugHelper::logSuccess('OAuthHandler', 'Admin token verified', [
            'domain' => $domain,
            'username' => $response['username'] ?? 'unknown',
            'role' => $role
        ]);

        return [
            'id' => $response['id'],
            'username' => $response['username'] ?? 'unknown',
            'display_name' => $response['display_name'] ?? '',
            'role' => $role
        ];
    }

    /**
     * Verify Misskey admin token.
     *
     * @param string $domain
     * @param string $token
     * @return array
     * @throws APIException
     */
    private static function verifyMisskeyAdmin(string $domain, string $token): array
    {
        $url = "https://{$domain}/api/i";

        $response = self::httpPost($url, ['i' => $token]);

        if (!isset($response['id'])) {
            throw new APIException("Invalid token: Could not verify credentials");
        }

        // Check if user is admin or moderator
        $isAdmin = $response['isAdmin'] ?? false;
        $isModerator = $response['isModerator'] ?? false;

        if (!$isAdmin && !$isModerator) {
            DebugHelper::logWarning('OAuthHandler', 'User lacks admin/moderator status', [
                'domain' => $domain,
                'username' => $response['username'] ?? 'unknown'
            ]);
            throw new APIException("Account must be an admin or moderator");
        }

        $role = $isAdmin ? 'admin' : 'moderator';

        DebugHelper::logSuccess('OAuthHandler', 'Admin token verified', [
            'domain' => $domain,
            'username' => $response['username'] ?? 'unknown',
            'role' => $role
        ]);

        return [
            'id' => $response['id'],
            'username' => $response['username'] ?? 'unknown',
            'display_name' => $response['name'] ?? '',
            'role' => $role
        ];
    }

    /**
     * Get the OAuth callback URL for this installation.
     *
     * @return string
     */
    private static function getCallbackUrl(): string
    {
        $osticketUrl = rtrim(get_osticket_url(), '/');
        return $osticketUrl . '/scp/plugins.php?id=fediverse:moderation&action=oauth_callback';
    }

    /**
     * Perform HTTP POST request.
     *
     * @param string $url
     * @param array $data
     * @return array
     * @throws APIException
     */
    private static function httpPost(string $url, array $data): array
    {
        $payload = json_encode($data);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 30
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            throw new APIException("HTTP POST failed: " . ($error['message'] ?? 'Unknown error'));
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new APIException("Invalid JSON response from {$url}");
        }

        return $decoded;
    }

    /**
     * Perform HTTP GET request.
     *
     * @param string $url
     * @param array $headers
     * @return array
     * @throws APIException
     */
    private static function httpGet(string $url, array $headers = []): array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            throw new APIException("HTTP GET failed: " . ($error['message'] ?? 'Unknown error'));
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new APIException("Invalid JSON response from {$url}");
        }

        return $decoded;
    }
}

/**
 * Helper function to get OSTicket base URL.
 * Falls back to detecting from current request if not configured.
 */
function get_osticket_url(): string
{
    // Try to get from OSTicket config
    if (defined('ROOT_URL')) {
        return ROOT_URL;
    }

    // Fallback: detect from request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return "{$protocol}://{$host}";
}
