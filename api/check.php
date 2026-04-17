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

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
