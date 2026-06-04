<?php

// ── CORS: cabeceras para todas las respuestas reales (no OPTIONS, ese lo maneja Apache) ──
$_CORS_ORIGIN = $_SERVER['HTTP_ORIGIN'] ?? '';
$_CORS_ALLOWED = [
    'https://siis09.simde.com.co',
    'https://devel82els.simde.com.co',
    'https://devel74.simde.com.co',
];
if (in_array($_CORS_ORIGIN, $_CORS_ALLOWED, true)) {
    header("Access-Control-Allow-Origin: {$_CORS_ORIGIN}", true);
    header('Access-Control-Allow-Credentials: true', true);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN', true);
}
// ── FIN CORS ──

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
