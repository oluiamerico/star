<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../api/db.php';

$sessions     = get_data('sessions');
$events       = get_data('events');
$leads        = get_data('leads');
$transactions = get_data('transactions');

$now            = time();
$live_threshold = 30;
$live_users     = [];

foreach ($sessions as $s) {
    if ($now - $s['last_ping'] <= $live_threshold) {
        $live_users[] = $s;
    }
}

$funnel = [
    'index'         => 0,
    'chip'          => 0,
    'checkout'      => 0,
    'gerados'       => 0,
    'pagos'         => 0,
    'valor_gerado'  => 0.0,
    'valor_pago'    => 0.0,
];

$visited_index    = [];
$visited_chip     = [];
$visited_checkout = [];

foreach ($events as $e) {
    if ($e['event_type'] === 'index')   $visited_index[$e['session_id']]    = true;
    if ($e['event_type'] === 'chip')    $visited_chip[$e['session_id']]     = true;
    if ($e['event_type'] === 'checkout') $visited_checkout[$e['session_id']] = true;
    if ($e['event_type'] === 'gerou')   $funnel['gerados']++;
    if ($e['event_type'] === 'pagou')   $funnel['pagos']++;
}

$funnel['index']    = count($visited_index);
$funnel['chip']     = count($visited_chip);
$funnel['checkout'] = count($visited_checkout);

foreach ($transactions as $t) {
    if ($t['status'] === 'pending' || $t['status'] === 'COMPLETED') {
        $funnel['valor_gerado'] += (float) $t['amount'];
    }
    if ($t['status'] === 'COMPLETED') {
        $funnel['valor_pago'] += (float) $t['amount'];
    }
}

// Build live users with transaction info
$live_out = [];
foreach ($live_users as $lu) {
    $tx = null;
    foreach ($transactions as $t) {
        if ($t['session_id'] === $lu['session_id']) { $tx = $t; break; }
    }
    $live_out[] = [
        'ip'           => $lu['ip'] ?? 'Desconhecido',
        'location'     => $lu['location'] ?? 'Desconhecido',
        'current_page' => $lu['current_page'] ?? '',
        'tx_status'    => $tx ? $tx['status'] : null,
        'tx_amount'    => $tx ? (float) $tx['amount'] : null,
    ];
}

// Build leads sorted by newest
usort($leads, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
$leads_out = [];
foreach ($leads as $l) {
    if (empty($l['name']) && empty($l['email']) && empty($l['document']) && empty($l['phone'])) continue;
    $tx = null;
    foreach ($transactions as $t) {
        if ($t['session_id'] === $l['session_id']) { $tx = $t; break; }
    }
    $leads_out[] = [
        'name'       => $l['name'] ?? '',
        'document'   => $l['document'] ?? '',
        'email'      => $l['email'] ?? '',
        'phone'      => $l['phone'] ?? '',
        'updated_at' => $l['updated_at'],
        'tx_status'  => $tx ? $tx['status'] : null,
        'tx_amount'  => $tx ? (float) $tx['amount'] : null,
    ];
}

echo json_encode([
    'total_visitors' => count($sessions),
    'live_count'     => count($live_users),
    'funnel'         => $funnel,
    'live_users'     => $live_out,
    'leads'          => $leads_out,
    'ts'             => $now,
]);
?>
