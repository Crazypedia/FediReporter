<?php
class MastoModerationNotes {
    private $cfg;
    public function __construct($config) { $this->cfg = $config; }

    public function sendModerationNote($platform, $instance, $accountId, $agentName, $comment) {
        $platform = strtolower($platform);
        $instance = rtrim($instance, '/');
        $maxLen = ($platform === 'mastodon') ? 480 : 950;
        $text = sprintf("[%s]: %s", $agentName, $comment);
        if (mb_strlen($text) > $maxLen) $text = mb_substr($text, 0, $maxLen - 1) . '…';

        if ($platform === 'mastodon') {
            $token = trim((string)$this->cfg->get('mastodon_access_token'));
            if (!$token) { error_log('MastoModerationNotes: missing mastodon_access_token'); return; }
            $url = $instance . '/api/v1/admin/accounts/' . rawurlencode($accountId) . '/notes';
            $payload = ['content' => $text];
            $headers = ['Authorization: Bearer '.$token, 'Content-Type: application/json'];
        } else {
            $token = trim((string)$this->cfg->get('misskey_access_token'));
            if (!$token) { error_log('MastoModerationNotes: missing misskey_access_token'); return; }
            $url = $instance . '/api/admin/notes/create';
            $payload = ['i' => $token, 'userId' => $accountId, 'text' => $text];
            $headers = ['Content-Type: application/json'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 300) error_log("Moderation note send failed ($code): ".$raw);
    }
}
