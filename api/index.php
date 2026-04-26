<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/middleware/auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = '/api';
if (str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri = '/' . ltrim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$segments = array_values(array_filter(explode('/', $uri)));
$resource = $segments[0] ?? '';
$id = isset($segments[1]) && is_numeric($segments[1]) ? (int)$segments[1] : null;
$action = isset($segments[1]) && !is_numeric($segments[1]) ? $segments[1] : ($segments[2] ?? null);
if ($id !== null && isset($segments[2])) $action = $segments[2];

getDB();

match($resource) {
    'auth'     => require __DIR__ . '/controllers/auth.php',
    'products' => require __DIR__ . '/controllers/products.php',
    'cart'     => require __DIR__ . '/controllers/cart.php',
    'orders'   => require __DIR__ . '/controllers/orders.php',
    'users'    => require __DIR__ . '/controllers/users.php',
    'health'   => jsonResponse(['status' => 'ok', 'time' => date('c')]),
    default    => jsonError('Ruta no encontrada', 404)
};