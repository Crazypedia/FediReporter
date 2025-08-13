<?php

require_once '../../../bootstrap.php';
require_once INCLUDE_DIR . 'staff.inc.php';

use FediversePlugin\ReportIngestor;

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Ensure JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Determine source domain
$domain = $_SERVER['HTTP_X_FEDIVERSE_DOMAIN'] ?? $_SERVER['REMOTE_ADDR'];

try {
    $ticket = ReportIngestor::process($data, $domain);
    if ($ticket) {
        http_response_code(201);
        echo json_encode(['success' => true, 'ticket_id' => $ticket->getId()]);
    } else {
        http_response_code(202);
        echo json_encode(['success' => false, 'message' => 'Report received but no ticket created.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
