<?php
function get_data($table) {
    clearstatcache();
    $file = __DIR__ . "/data/$table.json";
    if (!file_exists($file)) return [];
    
    // Acquire lock to read securely if we are paranoid, 
    // but simple read is fine since atomicity is not extremely critical here, 
    // however for full safety:
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $size = filesize($file);
    $content = $size > 0 ? fread($fp, $size) : '[]';
    flock($fp, LOCK_UN);
    fclose($fp);
    
    return json_decode($content, true) ?: [];
}

function save_data($table, $data) {
    $file = __DIR__ . "/data/$table.json";
    $temp_file = $file . '.tmp';
    
    // Write to a temporary file first
    $fp = fopen($temp_file, 'w');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            // Rename is atomic in linux
            rename($temp_file, $file);
        } else {
            fclose($fp);
            // Fallback
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        }
    } else {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
?>
