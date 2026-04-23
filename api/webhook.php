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

$transaction_id = $data['id'] ?? $data['transactionId'] ?? $data['transactionID'] ?? $data['transaction_id'] ?? null;

// Log raw webhook payload for debugging
$debug_log = get_data('webhook_logs');
$debug_log[] = ['received_at' => time(), 'payload' => $data, 'transaction_id' => $transaction_id];
if (count($debug_log) > 50) $debug_log = array_slice($debug_log, -50);
save_data('webhook_logs', $debug_log);

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

            // Find transaction to get stored utmify_lead and UTMs
            $all_tx = get_data('transactions');
            $matched_tx = null;
            foreach ($all_tx as $t) {
                if ($t['transaction_id'] === $transaction_id) {
                    $matched_tx = $t;
                    break;
                }
            }

            // Server-side conversion event to Utmify
            if ($matched_tx && !empty($matched_tx['utmify_lead']) && !empty($matched_tx['utmify_lead']['_id'])) {
                $lead = $matched_tx['utmify_lead'];
                $lead['updatedAt'] = date('c');
                // Utmify requires parameters as a JSON string, not an object
                if (isset($lead['parameters']) && is_array($lead['parameters'])) {
                    $lead['parameters'] = json_encode($lead['parameters']);
                }

                // Generate a random 24-char hex event ID
                $event_id = bin2hex(random_bytes(12));

                $utmify_payload = json_encode([
                    'type'     => 'Purchase',
                    'value'    => floatval($matched_tx['amount']),
                    'currency' => 'EUR',
                    'lead'     => $lead,
                    'event'    => [
                        '_id'       => $event_id,
                        'pageTitle' => 'Obrigado — eSIM Virtual Starlink',
                        'sourceUrl' => 'https://star-alfagroupcorpor.replit.app/obrigado/',
                    ],
                ]);

                $ch2 = curl_init('https://tracking.utmify.com.br/tracking/v1/events');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $utmify_payload);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                $utmify_response = curl_exec($ch2);
                curl_close($ch2);

                // Log the Utmify response for debugging
                $debug_events = get_data('events');
                $debug_events[] = [
                    'session_id'  => $session_id,
                    'event_type'  => 'utmify_purchase_sent',
                    'utmify_resp' => substr($utmify_response ?? '', 0, 500),
                    'lead_id'     => $lead['_id'],
                    'amount'      => $matched_tx['amount'],
                    'created_at'  => time()
                ];
                save_data('events', $debug_events);
            }
        }
    }
}

// WayMB requires HTTP 200
http_response_code(200);
ob_clean();
echo json_encode(['success' => true, 'received' => true]);
exit;
?>
