<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/db.php';

$tables = ['sessions', 'events', 'leads', 'transactions'];

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}

if (isset($_GET['reset']) && $_GET['reset'] === 'portuga2026') {
    foreach ($tables as $table) {
        save_data($table, []);
    }
    echo json_encode(['success' => true, 'message' => 'Metrics reset.']);
} else {
    foreach ($tables as $table) {
        if (!file_exists(__DIR__ . "/data/$table.json")) {
            save_data($table, []);
        }
    }
    echo json_encode(['success' => true, 'message' => 'JSON DB configured.']);
}
?>
