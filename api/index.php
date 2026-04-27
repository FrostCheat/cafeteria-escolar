<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

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

$request_uri = $_SERVER['REQUEST_URI'];
$request_uri = strtok($request_uri, '?');
$request_uri = preg_replace('#^/api#', '', $request_uri);
$request_uri = '/' . ltrim($request_uri, '/');

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$segments = array_values(array_filter(explode('/', $request_uri)));
$resource = $segments[0] ?? '';

$id = null;
$action = null;

if (isset($segments[1])) {
    if (is_numeric($segments[1])) {
        $id = (int)$segments[1];
        $action = $segments[2] ?? null;
    } else {
        $action = $segments[1];
    }
}

getDB();

if ($resource === 'auth') { require __DIR__ . '/controllers/auth.php'; exit; }
if ($resource === 'products') { require __DIR__ . '/controllers/products.php'; exit; }
if ($resource === 'cart') { require __DIR__ . '/controllers/cart.php'; exit; }
if ($resource === 'orders') { require __DIR__ . '/controllers/orders.php'; exit; }
if ($resource === 'users') { require __DIR__ . '/controllers/users.php'; exit; }
if ($resource === 'health') {
    echo json_encode(['status' => 'ok', 'time' => date('c')]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada', 'path' => $request_uri, 'resource' => $resource]);
exit;