<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction ID']);
    exit;
}

$transaction_id = preg_replace('/[^a-zA-Z0-9-_]/', '', $_GET['id']);

$client_id = 'z1r0_7988d1c4';
$client_secret = 'a2f61e17-2a5f-4277-9ea0-e53835f6ccec';

$check_url = 'https://api.waymb.com/transactions/info';

$payload_data = [
    'id' => $transaction_id,
    'client_id' => $client_id,
    'client_secret' => $client_secret
];

$ch = curl_init($check_url);
$payload = json_encode($payload_data);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response_raw, true);

if ($http_code !== 200) {
    http_response_code($http_code === 0 ? 500 : $http_code);
    echo json_encode(['success' => false, 'error' => 'Failed to check status', 'details' => $data]);
    exit;
}

// UPDATE TRANSACTION STATUS AND LOG PAGOU EVENT
if (isset($data['status'])) {
    require_once __DIR__ . '/db.php';
    $status = strtoupper($data['status']);
    
    $transactions = get_data('transactions');
    $session_id = null;
    $updated = false;

    foreach ($transactions as &$tx) {
        if ($tx['transaction_id'] === $transaction_id) {
            // Only update if not already marked as COMPLETED
            if ($tx['status'] !== 'COMPLETED' && $status === 'COMPLETED') {
                $session_id = $tx['session_id'];
            }
            $tx['status'] = $status;
            $tx['updated_at'] = time();
            $updated = true;
            break;
        }
    }
    
    if ($updated) save_data('transactions', $transactions);

    if ($session_id && $status === 'COMPLETED') {
        $events = get_data('events');
        $has_pagou = false;
        foreach ($events as $ev) {
            if ($ev['session_id'] === $session_id && $ev['event_type'] === 'pagou') {
                $has_pagou = true;
                break;
            }
        }
        if (!$has_pagou) {
            $events[] = [
                'session_id' => $session_id,
                'event_type' => 'pagou',
                'created_at' => time()
            ];
            save_data('events', $events);

            // Fire Utmify Purchase (backup path — in case webhook didn't arrive)
            $all_tx = get_data('transactions');
            $matched_tx = null;
            foreach ($all_tx as $t) {
                if ($t['transaction_id'] === $transaction_id) { $matched_tx = $t; break; }
            }

            $already_sent = false;
            foreach ($events as $ev) {
                if (($ev['session_id'] ?? '') === $session_id && ($ev['event_type'] ?? '') === 'utmify_purchase_sent') {
                    $already_sent = true; break;
                }
            }

            if ($matched_tx && !empty($matched_tx['utmify_lead']['_id']) && !$already_sent) {
                $lead = $matched_tx['utmify_lead'];
                $lead['updatedAt'] = date('c');
                if (isset($lead['parameters']) && is_array($lead['parameters'])) {
                    $lead['parameters'] = json_encode($lead['parameters']);
                }
                $event_id = bin2hex(random_bytes(12));
                $utmify_payload = json_encode([
                    'type'     => 'Purchase',
                    'value'    => floatval($matched_tx['amount']),
                    'currency' => 'EUR',
                    'lead'     => $lead,
                    'event'    => [
                        '_id'       => $event_id,
                        'pageTitle' => 'Obrigado — eSIM Virtual Starlink',
                        'sourceUrl' => 'https://global-satelite.shop/obrigado/',
                    ],
                ]);
                $ch2 = curl_init('https://tracking.utmify.com.br/tracking/v1/events');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $utmify_payload);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
                $utmify_resp = curl_exec($ch2);
                curl_close($ch2);

                $events2 = get_data('events');
                $events2[] = [
                    'session_id'  => $session_id,
                    'event_type'  => 'utmify_purchase_sent',
                    'source'      => 'check_php_backup',
                    'utmify_resp' => substr($utmify_resp ?? '', 0, 500),
                    'lead_id'     => $lead['_id'],
                    'amount'      => $matched_tx['amount'],
                    'created_at'  => time()
                ];
                save_data('events', $events2);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
