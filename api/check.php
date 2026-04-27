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

                $token = 'ryGFU6OxRZBIyHdPaW9wx05t2RKcjFZawQqD';
                $price_in_cents = round(floatval($matched_tx['amount'] ?? 0) * 100);

                $payload = json_encode([
                    'orderId'       => $transaction_id,
                    'platform'      => 'waymb',
                    'paymentMethod' => 'pix',
                    'status'        => 'paid',
                    'createdAt'     => date('Y-m-d H:i:s'),
                    'approvedDate'  => date('Y-m-d H:i:s'),
                    'customer' => [
                        'name'     => $matched_tx['customer_name'] ?? 'Cliente Starlink',
                        'email'    => $matched_tx['customer_email'] ?? 'cliente@exemplo.com',
                        'phone'    => preg_replace('/\D/', '', $matched_tx['customer_phone'] ?? ''),
                        'document' => preg_replace('/\D/', '', $matched_tx['customer_document'] ?? ''),
                        'country'  => 'PT',
                        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ],
                    'products' => [[
                        'id'           => 'starlink_esim',
                        'name'         => 'Starlink eSIM',
                        'planId'       => 'standard',
                        'planName'     => 'Standard Plan',
                        'quantity'     => 1,
                        'priceInCents' => $price_in_cents
                    ]],
                    'trackingParameters' => [
                        'utm_source'   => $matched_tx['utm_source']   ?? null,
                        'utm_medium'   => $matched_tx['utm_medium']   ?? null,
                        'utm_campaign' => $matched_tx['utm_campaign'] ?? null,
                        'utm_content'  => $matched_tx['utm_content']  ?? null,
                        'utm_term'     => $matched_tx['utm_term']     ?? null,
                        'src'          => $matched_tx['utm_source']   ?? null
                    ],
                    'commission' => [
                        'totalPriceInCents'      => $price_in_cents,
                        'gatewayFeeInCents'      => 0,
                        'userCommissionInCents'  => $price_in_cents
                    ],
                    'isTest' => false
                ]);

                $ch2 = curl_init('https://api.utmify.com.br/api-credentials/orders');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-api-token: ' . $token
                ]);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
                $utmify_resp = curl_exec($ch2);
                curl_close($ch2);

                $events2 = get_data('events');
                $events2[] = [
                    'session_id'  => $session_id,
                    'event_type'  => 'utmify_purchase_sent',
                    'source'      => 'check_php_backup',
                    'utmify_resp' => substr($utmify_resp ?? '', 0, 500),
                    'amount'      => $matched_tx['amount'],
                    'created_at'  => time()
                ];
                save_data('events', $events2);
        }
    }
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
