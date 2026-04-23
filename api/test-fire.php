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
        '_id'      => '69ea1307ab505ec789cc75ab',
        'pixelId'  => '69b5d2cef247cc4b40527718',
        'ip'       => '45.165.21.178',
        'userAgent'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'locale'   => 'pt-PT',
        'fbp'      => 'fb.2.1776444467651.66481013328062779',
    ];
    $leadSource = 'hardcoded_fallback';
}

$lead['updatedAt'] = date('c');
// Utmify requires parameters as a JSON string, not an object
if (isset($lead['parameters']) && is_array($lead['parameters'])) {
    $lead['parameters'] = json_encode($lead['parameters']);
}
$event_id = bin2hex(random_bytes(12));

$payload = json_encode([
    'type'     => 'Purchase',
    'value'    => $amount,
    'currency' => 'EUR',
    'lead'     => $lead,
    'event'    => [
        '_id'       => $event_id,
        'pageTitle' => 'Obrigado — eSIM Virtual Starlink',
        'sourceUrl' => 'https://star-alfagroupcorpor.replit.app/obrigado/',
    ],
]);

$ch = curl_init('https://tracking.utmify.com.br/tracking/v1/events');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
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
        'lead_id'    => $lead['_id'],
        'amount'     => $amount,
        'event_id'   => $event_id,
        'lead_source'=> $leadSource,
    ],
]);
