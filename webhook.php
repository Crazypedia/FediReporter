<?php
// Webhook endpoint for Mastodon/Misskey/Sharkey reports.
// Place URL e.g. https://your-osticket-domain/include/plugins/masto_reports_plugin/webhook.php
// This script bootstraps osTicket and invokes the importer.

chdir(dirname(__DIR__, 2)); // move to include/
require_once 'staff/ost-config.php';
require_once 'main.inc.php';

require_once __DIR__ . '/lib/PlatformDetector.php';
require_once __DIR__ . '/lib/ReportImporter.php';

header('Content-Type: application/json; charset=utf-8');

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Find plugin instance
$plugin = null;
foreach (PluginManager::getInstance()->allInstalled() as $p) {
    if ($p instanceof MastoReportsPlugin) { $plugin = $p; break; }
}
if (!$plugin) respond(500, ['error' => 'Plugin not installed']);

$cfg = $plugin->getConfig();

// Verify secret: Authorization: Bearer <secret> or X-Webhook-Token: <secret>
$secret = trim((string)$cfg->get('webhook_secret'));
$hdrAuth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$hdrToken = isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']) ? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] : '';
$ok = false;
if ($secret) {
    if (stripos($hdrAuth, 'Bearer ') === 0) {
        $tok = trim(substr($hdrAuth, 7));
        if (hash_equals($secret, $tok)) $ok = true;
    }
    if (!$ok && $hdrToken && hash_equals($secret, $hdrToken)) $ok = true;
}
if (!$ok) respond(401, ['error' => 'Unauthorized']);

// Parse JSON body
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) respond(400, ['error' => 'Invalid JSON']);

// Instance identification (required; pass via header or body)
$instance = isset($_SERVER['HTTP_X_ORIGIN_INSTANCE']) ? $_SERVER['HTTP_X_ORIGIN_INSTANCE'] : ($payload['instance'] ?? '');
if (!$instance) respond(400, ['error' => 'Missing instance identifier (X-Origin-Instance header or body.instance)']);

try {
    $importer = new MastoReportImporter($cfg);
    $ticket = $importer->importFromPayload($instance, $payload);
    if ($ticket) {
        respond(201, ['ok' => true, 'ticket_id' => $ticket->getId(), 'number' => $ticket->getNumber()]);
    } else {
        respond(200, ['ok' => true, 'duplicate' => true]);
    }
} catch (Throwable $e) {
    error_log('Webhook error: '.$e->getMessage());
    respond(500, ['error' => $e->getMessage()]);
}
