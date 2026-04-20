<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_clean();
    echo json_encode(['success' => true]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    // WayMB may send form-encoded too
    $data = $_POST;
}

require_once __DIR__ . '/db.php';

$transaction_id = $data['id'] ?? $data['transaction_id'] ?? null;
$status = isset($data['status']) ? strtoupper($data['status']) : null;

if ($transaction_id && $status) {
    $transactions = get_data('transactions');
    $session_id = null;

    foreach ($transactions as &$tx) {
        if ($tx['transaction_id'] === $transaction_id) {
            if ($tx['status'] !== 'COMPLETED' && $status === 'COMPLETED') {
                $session_id = $tx['session_id'];
            }
            $tx['status'] = $status;
            $tx['updated_at'] = time();
            break;
        }
    }
    unset($tx);
    save_data('transactions', $transactions);

    if ($session_id && $status === 'COMPLETED') {
        $events = get_data('events');
        $already = false;
        foreach ($events as $ev) {
            if ($ev['session_id'] === $session_id && $ev['event_type'] === 'pagou') {
                $already = true;
                break;
            }
        }
        if (!$already) {
            $events[] = [
                'session_id' => $session_id,
                'event_type' => 'pagou',
                'created_at' => time()
            ];
            save_data('events', $events);
        }
    }
}

// WayMB requires HTTP 200
http_response_code(200);
ob_clean();
echo json_encode(['success' => true, 'received' => true]);
exit;
?>
