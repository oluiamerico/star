<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// Simple guard — only allow from our domain or with a secret param
$secret = $_GET['s'] ?? $_POST['s'] ?? '';
if ($secret !== 'fire2026') {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$lead   = $data['lead']   ?? null;
$amount = floatval($data['amount'] ?? 29.90);
$leadSource = 'localStorage';

// Fallback: if no lead provided, use the last stored transaction that has a lead
if (!$lead || empty($lead['_id'])) {
    $transactions = get_data('transactions');
    // Find latest transaction with a utmify_lead
    foreach (array_reverse($transactions) as $tx) {
        if (!empty($tx['utmify_lead']['_id'])) {
            $lead = $tx['utmify_lead'];
            $leadSource = 'last_transaction_fallback (tx: ' . ($tx['transaction_id'] ?? '?') . ')';
            break;
        }
    }
}

// Last resort: hardcoded known-good lead for this pixel (confirmed working Apr 23 2026)
if (!$lead || empty($lead['_id'])) {
    $lead = [
        '_id'      => bin2hex(random_bytes(12)),
        'pixelId'  => '69e292a38ea606ab7ebd42c4',
        'name'     => 'Teste Antigravity',
        'email'    => 'teste' . rand(100, 999) . '@exemplo.com',
        'phone'    => '351910000000',
        'ip'       => '45.165.21.178',
        'userAgent'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'locale'   => 'pt-PT',
        'fbp'      => 'fb.2.1776444467651.66481013328062779',
    ];
    $leadSource = 'hardcoded_random_customer_fallback';
}

$token = 'ryGFU6OxRZBIyHdPaW9wx05t2RKcjFZawQqD';
$order_id = 'test_' . bin2hex(random_bytes(6));
$price_in_cents = round($amount * 100);

$payload = json_encode([
    'orderId'       => $order_id,
    'platform'      => 'custom',
    'paymentMethod' => 'pix',
    'status'        => 'paid',
    'createdAt'     => date('Y-m-d H:i:s'),
    'approvedDate'  => date('Y-m-d H:i:s'),
    'customer' => [
        'name'     => 'Teste Antigravity',
        'email'    => 'teste' . rand(100, 999) . '@exemplo.com',
        'phone'    => '351910000000',
        'document' => '999999999',
        'country'  => 'PT',
        'ip'       => '45.165.21.178'
    ],
    'products' => [[
        'id'           => 'starlink_esim_test',
        'name'         => 'Starlink eSIM',
        'planId'       => 'standard',
        'planName'     => 'Standard Plan',
        'quantity'     => 1,
        'priceInCents' => $price_in_cents
    ]],
    'trackingParameters' => [
        'utm_source'   => 'google',
        'utm_medium'   => 'cpc',
        'utm_campaign' => 'teste_api_v1',
        'utm_content'  => null,
        'utm_term'     => null
    ],
    'commission' => [
        'totalPriceInCents'      => $price_in_cents,
        'gatewayFeeInCents'      => 0,
        'userCommissionInCents'  => $price_in_cents
    ],
    'isTest' => false
]);

$ch = curl_init('https://api.utmify.com.br/api-credentials/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-token: ' . $token
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

ob_clean();
echo json_encode([
    'success'    => ($httpCode >= 200 && $httpCode < 300),
    'http_code'  => $httpCode,
    'curl_error' => $curlErr ?: null,
    'utmify_raw' => $resp,
    'utmify_obj' => json_decode($resp, true),
    'sent'       => [
        'order_id'   => $order_id,
        'amount'     => $amount,
        'endpoint'   => 'api-credentials/orders',
    ],
]);
