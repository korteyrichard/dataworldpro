<?php

// DOMAIN RESTRICTION - FORCE CHECK BEFORE LARAVEL LOADS
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$host = explode(':', $host)[0]; // Remove port
$path = trim($_SERVER['REQUEST_URI'] ?? '/', '/');

if ($host === 'alldatagh.com' || $host === 'www.alldatagh.com') {
    // Only allow shop routes on this domain
    if (!str_starts_with($path, 'shop/') && !str_starts_with($path, 'guest/payment/') && $path !== 'domain-check.php') {
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Domain Only</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-color: #f5f5f5; }
        .container { text-align: center; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 1rem; }
        p { color: #666; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>This domain only shows store pages</h1>
        <p>Please access individual store pages through /shop/{shopname} URLs.</p>
    </div>
</body>
</html>';
        exit;
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
