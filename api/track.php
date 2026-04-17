<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['session_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session_id or action']);
    exit;
}

$session_id = preg_replace('/[^a-zA-Z0-9-]/', '', $input['session_id']);
$action = $input['action'];
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// Determine location if missing
function get_location_for_ip($ip) {
    if (!$ip || $ip === '127.0.0.1' || $ip === '::1') return 'Localhost';
    $location_data = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city");
    if ($location_data) {
        $loc = json_decode($location_data, true);
        if ($loc && isset($loc['status']) && $loc['status'] === 'success') {
            return $loc['city'] . ', ' . $loc['country'];
        }
    }
    return 'Unknown';
}


if ($action === 'ping') {
    $current_page = $input['page'] ?? 'unknown';
    $sessions = get_data('sessions');
    $found = false;
    
    foreach ($sessions as &$session) {
        if ($session['session_id'] === $session_id) {
            $session['last_ping'] = time();
            $session['current_page'] = $current_page;
            // update ip in case they changed networks
            if (!isset($session['ip']) || $session['ip'] !== $ip) {
                $session['ip'] = $ip;
                $session['location'] = get_location_for_ip($ip);
            }
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $location = get_location_for_ip($ip);
        $sessions[] = [
            'session_id' => $session_id,
            'ip' => $ip,
            'location' => $location,
            'current_page' => $current_page,
            'created_at' => time(),
            'last_ping' => time()
        ];
    }
    save_data('sessions', $sessions);
}

if ($action === 'log_event') {
    $event_type = $input['event_type'] ?? '';
    if ($event_type) {
        $events = get_data('events');
        $has_event = false;
        foreach ($events as $ev) {
            if ($ev['session_id'] === $session_id && $ev['event_type'] === $event_type) {
                $has_event = true;
                break;
            }
        }
        if (!$has_event) {
            $events[] = [
                'session_id' => $session_id,
                'event_type' => $event_type,
                'created_at' => time()
            ];
            save_data('events', $events);
        }
    }
}

if ($action === 'update_lead') {
    $leads = get_data('leads');
    $found = false;
    foreach ($leads as &$lead) {
        if ($lead['session_id'] === $session_id) {
            $lead['name'] = !empty($input['name']) ? $input['name'] : $lead['name'];
            $lead['email'] = !empty($input['email']) ? $input['email'] : $lead['email'];
            $lead['document'] = !empty($input['document']) ? $input['document'] : $lead['document'];
            $lead['phone'] = !empty($input['phone']) ? $input['phone'] : $lead['phone'];
            $lead['updated_at'] = time();
            $found = true;
            break;
        }
    }
    if (!$found) {
        $leads[] = [
            'session_id' => $session_id,
            'name' => $input['name'] ?? '',
            'email' => $input['email'] ?? '',
            'document' => $input['document'] ?? '',
            'phone' => $input['phone'] ?? '',
            'ip' => $ip,
            'updated_at' => time()
        ];
    }
    save_data('leads', $leads);
}

echo json_encode(['success' => true]);
?>
