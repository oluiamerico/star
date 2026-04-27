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

    // Fallback lead — used if transaction has no lead or wasn't found in DB
    $fallback_lead = [
        '_id'      => '69ea1307ab505ec789cc75ab',
        'pixelId'  => '69e292a38ea606ab7ebd42c4',
        'ip'       => '45.165.21.178',
        'userAgent'=> 'Mozilla/5.0',
        'locale'   => 'pt-PT',
        'fbp'      => 'fb.2.1776444467651.66481013328062779',
    ];

    // Find the transaction for lead + amount
    $all_tx = get_data('transactions');
    $matched_tx = null;
    foreach ($all_tx as $t) {
        if ($t['transaction_id'] === $transaction_id) { $matched_tx = $t; break; }
    }

    if ($session_id && $status === 'COMPLETED') {
        $events = get_data('events');
        $already = false;
        foreach ($events as $ev) {
            if ($ev['session_id'] === $session_id && $ev['event_type'] === 'pagou') {
                $already = true; break;
            }
        }
        if (!$already) {
            $events[] = ['session_id' => $session_id, 'event_type' => 'pagou', 'created_at' => time()];
            save_data('events', $events);
        }
    }

    // Fire Utmify for EVERY COMPLETED — regardless of whether transaction was in DB
    if ($status === 'COMPLETED') {
        // Check we haven't already fired for this transaction
        $all_events = get_data('events');
        $already_fired = false;
        foreach ($all_events as $ev) {
            if (($ev['event_type'] ?? '') === 'utmify_purchase_sent'
                && ($ev['transaction_id'] ?? '') === $transaction_id) {
                $already_fired = true; break;
            }
        }

        if (!$already_fired) {
            $token = 'ryGFU6OxRZBIyHdPaW9wx05t2RKcjFZawQqD';
            $price_in_cents = round($amount * 100);
            
            $lead = ($matched_tx && !empty($matched_tx['utmify_lead']['_id']))
                ? $matched_tx['utmify_lead']
                : $fallback_lead;

            $payload = json_encode([
                'orderId'       => $transaction_id,
                'platform'      => 'waymb',
                'paymentMethod' => strtolower($data['method'] ?? ($matched_tx['method'] ?? 'pix')),
                'status'        => 'paid',
                'createdAt'     => date('Y-m-d H:i:s'),
                'approvedDate'  => date('Y-m-d H:i:s'),
                'customer' => [
                    'name'     => $data['payer']['name'] ?? ($matched_tx['customer_name'] ?? 'Cliente Starlink'),
                    'email'    => $data['payer']['email'] ?? ($matched_tx['customer_email'] ?? 'cliente@exemplo.com'),
                    'phone'    => preg_replace('/\D/', '', $data['payer']['phone'] ?? ($matched_tx['customer_phone'] ?? '')),
                    'document' => preg_replace('/\D/', '', $data['payer']['document'] ?? ($matched_tx['customer_document'] ?? '')),
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
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            $utmify_response = curl_exec($ch2);
            curl_close($ch2);

            $all_events2 = get_data('events');
            $all_events2[] = [
                'session_id'     => $session_id ?? 'webhook_direct',
                'transaction_id' => $transaction_id,
                'event_type'     => 'utmify_purchase_sent',
                'lead_source'    => $matched_tx ? 'real_lead' : 'fallback',
                'utmify_resp'    => substr($utmify_response ?? '', 0, 500),
                'amount'         => $amount,
                'created_at'     => time()
            ];
            save_data('events', $all_events2);
        }
    }
}

// WayMB requires HTTP 200
http_response_code(200);
ob_clean();
echo json_encode(['success' => true, 'received' => true]);
exit;
?>
