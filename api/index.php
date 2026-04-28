<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config/logger.php';

logInfo('API Request recibida', ['method' => $_SERVER['REQUEST_METHOD'], 'uri' => $_SERVER['REQUEST_URI']]);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
date_default_timezone_set('America/Bogota');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    logInfo('Solicitud OPTIONS recibida');
    http_response_code(204);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError('Error fatal detectado', $error);
        http_response_code(500);
        echo json_encode([
            'fatal_error' => true,
            'message'     => $error['message'],
            'file'        => $error['file'],
            'line'        => $error['line']
        ]);
        exit;
    }
});

set_exception_handler(function($exception) {
    logError('Excepción no capturada', [
        'message' => $exception->getMessage(),
        'file'    => $exception->getFile(),
        'line'    => $exception->getLine(),
        'trace'   => $exception->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'exception' => true,
        'message'   => $exception->getMessage(),
        'file'      => $exception->getFile(),
        'line'      => $exception->getLine()
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logWarning('Error capturado', [
        'message' => $errstr,
        'file'    => $errfile,
        'line'    => $errline,
        'type'    => $errno
    ]);
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => $errstr,
        'file'    => $errfile,
        'line'    => $errline
    ]);
    exit;
});

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/helpers.php';
    require_once __DIR__ . '/middleware/auth.php';

    $request_uri = $_SERVER['REQUEST_URI'];
    $request_uri = strtok($request_uri, '?');
    $request_uri = preg_replace('#^/api#', '', $request_uri);
    $request_uri = '/' . ltrim($request_uri, '/');

    $method = $_SERVER['REQUEST_METHOD'];
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    $segments = array_values(array_filter(explode('/', $request_uri)));
    $resource = $segments[0] ?? '';
    $id       = isset($segments[1]) && is_numeric($segments[1]) ? (int)$segments[1] : null;
    $action   = isset($segments[1]) && !is_numeric($segments[1]) ? $segments[1] : ($segments[2] ?? null);
    if ($id !== null && isset($segments[2])) $action = $segments[2];

    logInfo('Procesando solicitud', ['resource' => $resource, 'id' => $id, 'action' => $action]);

    getDB();

    if ($resource === 'health') {
        logInfo('Health check solicitado');
        echo json_encode(['status' => 'ok', 'time' => date('c')]);
        exit;
    }

    if ($resource === 'sse') {
        require __DIR__ . '/sse.php';
        exit;
    }

    $controllerMap = [
        'auth'     => __DIR__ . '/controllers/auth.php',
        'products' => __DIR__ . '/controllers/products.php',
        'cart'     => __DIR__ . '/controllers/cart.php',
        'orders'   => __DIR__ . '/controllers/orders.php',
        'users'    => __DIR__ . '/controllers/users.php',
        'queue'    => __DIR__ . '/controllers/queue.php',
    ];

    if (isset($controllerMap[$resource]) && file_exists($controllerMap[$resource])) {
        logInfo('Cargando controlador', ['file' => $controllerMap[$resource]]);
        require $controllerMap[$resource];
        exit;
    }

    logWarning('Ruta no encontrada', ['resource' => $resource, 'uri' => $request_uri]);
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada', 'path' => $request_uri, 'resource' => $resource]);
    exit;

} catch (PDOException $e) {
    logDatabaseError('index.php', $e);
    http_response_code(500);
    echo json_encode([
        'db_error' => true,
        'message'  => $e->getMessage(),
        'code'     => $e->getCode()
    ]);
    exit;
} catch (Exception $e) {
    logError('Excepción general en index.php', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage()
    ]);
    exit;
}