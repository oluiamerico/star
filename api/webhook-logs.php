<?php
header("Content-Type: application/json; charset=UTF-8");
error_reporting(0);
ini_set('display_errors', 0);

$secret = $_GET['s'] ?? '';
if ($secret !== 'fire2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/db.php';

$logs = get_data('webhook_logs');
$logs = array_reverse($logs); // newest first

foreach ($logs as &$l) {
    $l['received_at_human'] = date('Y-m-d H:i:s', $l['received_at'] ?? 0);
}

echo json_encode(['total' => count($logs), 'logs' => $logs], JSON_PRETTY_PRINT);
