<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing slash for matching (except root)
$uri = ($uri !== '/') ? rtrim($uri, '/') : $uri;

// Route /admin to admin/index.php
if ($uri === '/admin' || strpos($uri, '/admin/') === 0) {
    $adminPath = __DIR__ . '/admin' . substr($uri, strlen('/admin'));
    
    // If accessing /admin or /admin/ serve admin/index.php
    if ($uri === '/admin' || $uri === '/admin/') {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/admin/index.php';
        require __DIR__ . '/admin/index.php';
        return true;
    }
    
    // Serve static files inside admin/ if they exist
    if (file_exists($adminPath) && !is_dir($adminPath)) {
        return false;
    }
    
    require __DIR__ . '/admin/index.php';
    return true;
}

// For all other requests, let PHP built-in server handle it normally
return false;
?>
