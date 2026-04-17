<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Prevent any PHP errors from being displayed and breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    if (!isset($input['amount']) || !isset($input['payer_name']) || !isset($input['document']) || !isset($input['method'])) {
        throw new Exception('Amount, payer_name, document and method are required.', 400);
    }

    $phone = isset($input['phone']) ? preg_replace('/\D/', '', $input['phone']) : '';
    // Fix: Portugal phone padding
    if (!empty($phone)) {
        if (!str_starts_with($phone, '351')) {
            $phone = '+351' . $phone;
        } else {
            $phone = '+' . $phone;
        }
    }

    $amount = floatval($input['amount']);
    $payer_name = $input['payer_name'];
    $document = preg_replace('/\D/', '', $input['document']);
    $email = isset($input['email']) && !empty($input['email']) ? $input['email'] : 'cliente@gmail.com';
    $method = strtolower($input['method']);

    $client_id = 'z1r0_7988d1c4';
    $client_secret = 'a2f61e17-2a5f-4277-9ea0-e53835f6ccec';
    $account_email = 'arilsonsouza2706@gmail.com';

    $payload_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'account_email' => $account_email,
        'amount' => $amount,
        'method' => $method,
        'currency' => 'EUR',
        'payer' => [
            'email' => $email,
            'name' => $payer_name,
            'document' => !empty($document) ? $document : '999999999',
            'phone' => $phone
        ],
    ];

    // DIAGNOSTIC LOG REMOVED

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $payload_data['success_url'] = $protocol . '://' . $host . '/upsell-01/index.html';

    $order_url = 'https://api.waymb.com/transactions/create';

    $ch = curl_init($order_url);
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

    // DIAGNOSTIC LOG REMOVED

    $response_data = json_decode($response_raw, true);

    if ($http_code !== 200 && $http_code !== 201) {
        $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Failed to generate payment';
        throw new Exception($error_msg, $http_code === 0 ? 500 : $http_code);
    }

    // LOG TRANSACTION
    if (isset($input['session_id'])) {
        require_once __DIR__ . '/db.php';
        $session_id = preg_replace('/[^a-zA-Z0-9-]/', '', $input['session_id']);
        
        $transaction_id = $response_data['id'] ?? $response_data['transaction_id'] ?? null;
        if ($transaction_id) {
            $transactions = get_data('transactions');
            $transactions[] = [
                'session_id' => $session_id,
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => time()
            ];
            save_data('transactions', $transactions);

            $events = get_data('events');
            $events[] = [
                'session_id' => $session_id,
                'event_type' => 'gerou',
                'created_at' => time()
            ];
            save_data('events', $events);
        }
    }

    // Clear buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
