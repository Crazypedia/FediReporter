<?php
class MastoPlatformDetector {
    /**
     * Detect platform from payload or via instance probing.
     * Returns 'mastodon' or 'misskey' (misskey covers Sharkey).
     */
    public static function detectFromPayload($payload) {
        if (is_array($payload)) {
            // Mastodon-style admin report payload
            if (isset($payload['account']) && isset($payload['target_account'])) return 'mastodon';
            if (isset($payload['created_at']) && (isset($payload['category']) || isset($payload['status_ids']))) return 'mastodon';

            // Some deliveries may wrap in "report"
            if (isset($payload['report']) && is_array($payload['report'])) {
                $r = $payload['report'];
                if (isset($r['account']) || isset($r['target_account'])) return 'mastodon';
            }

            // Misskey/Sharkey report payload
            if (isset($payload['createdAt']) && (isset($payload['reporter']) || isset($payload['targetUser']))) return 'misskey';
            if (isset($payload['userId']) && isset($payload['comment']) && isset($payload['id'])) return 'misskey';
        }
        return null;
    }

    public static function probeInstance($base) {
        $base = rtrim($base, '/');
        // Try Mastodon
        $url = $base.'/api/v1/instance';
        $res = self::simpleGet($url);
        if ($res && isset($res['version'])) {
            if (stripos($res['version'], 'mastodon') !== false || isset($res['uri']))
                return 'mastodon';
        }
        // Try Misskey/Sharkey
        $res = self::simplePost($base.'/api/meta', []);
        if ($res && isset($res['version'])) {
            if (isset($res['softwareName'])) {
                $n = strtolower($res['softwareName']);
                if ($n === 'misskey' || $n === 'sharkey') return 'misskey';
            }
            // Heuristics
            if (isset($res['maintainerName'])) return 'misskey';
        }
        return null;
    }

    private static function simpleGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
    private static function simplePost($url, $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
