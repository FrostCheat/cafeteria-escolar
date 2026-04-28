<?php
require_once __DIR__ . '/api/config/logger.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/helpers.php';
require_once __DIR__ . '/api/middleware/auth.php';

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('X-Accel-Buffering: no');

date_default_timezone_set('America/Bogota');

$token = $_GET['token'] ?? null;
$user = $token ? jwtDecode($token) : getAuthUser();

if (!$user) {
    echo "event: error\ndata: {\"error\":\"unauthorized\"}\n\n";
    flush();
    exit;
}

$db = getDB();

function sseFlush(string $event, array $data): void {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function getQueueState(PDO $db): array {
    $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
    $currentOrder = null;
    if ($cfg && $cfg['current_order_id']) {
        $stmt = $db->prepare("SELECT o.*, u.full_name, u.grade FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
        $stmt->execute([$cfg['current_order_id']]);
        $currentOrder = $stmt->fetch() ?: null;
    }
    return [
        'enabled'       => $cfg ? (bool)$cfg['enabled'] : false,
        'current_turn'  => $cfg ? (int)$cfg['current_turn'] : 0,
        'current_order' => $currentOrder,
        'last_updated'  => $cfg ? $cfg['updated_at'] : null,
    ];
}

function getStats(PDO $db): array {
    $stats = $db->query("
        SELECT
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_orders,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            COALESCE(SUM(CASE WHEN status='paid' THEN total ELSE 0 END),0) as total_revenue,
            COALESCE(SUM(total),0) as pending_revenue
        FROM orders
    ")->fetch();
    $stats['total_products'] = $db->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
    $stats['total_users']    = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    return $stats;
}

function getAdminOrders(PDO $db): array {
    return $db->query("
        SELECT o.id, o.status, o.total, o.created_at, o.turn_number,
               u.full_name, u.grade, u.doc_number
        FROM orders o JOIN users u ON u.id=o.user_id
        ORDER BY o.created_at DESC LIMIT 100
    ")->fetchAll();
}

function getUserOrders(PDO $db, int $userId): array {
    $stmt = $db->prepare("
        SELECT o.id, o.status, o.total, o.created_at, o.turn_number, o.qr_token, o.qr_code
        FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 50
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    if (!$orders) return [];

    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT order_id, product_name, quantity, subtotal FROM order_items WHERE order_id IN ($in)");
    $stmt->execute($ids);

    $grouped = [];
    foreach ($stmt->fetchAll() as $item) {
        $grouped[$item['order_id']][] = $item;
    }
    foreach ($orders as &$o) {
        $o['items'] = $grouped[$o['id']] ?? [];
    }
    return $orders;
}

$isAdmin = $user['role'] === 'admin';
$userId  = (int)$user['id'];

$lastEventId = (int)($db->query("SELECT COALESCE(MAX(id),0) FROM events")->fetchColumn());

set_time_limit(90);
$start   = time();
$maxTime = 55;

sseFlush('connected', ['status' => 'ok', 'role' => $user['role'], 'user_id' => $userId]);

sseFlush('queue', getQueueState($db));

if ($isAdmin) {
    sseFlush('orders_updated', ['orders' => getAdminOrders($db)]);
    sseFlush('stats_updated', getStats($db));
    sseFlush('products_updated', ['products' => $db->query("SELECT id, name, stock, active, price, category FROM products ORDER BY id")->fetchAll()]);
} else {
    sseFlush('my_orders_updated', ['orders' => getUserOrders($db, $userId)]);
}

while ((time() - $start) < $maxTime) {
    if (connection_aborted()) break;

    try {
        $stmt = $db->prepare("SELECT id, type, payload FROM events WHERE id > ? ORDER BY id ASC LIMIT 50");
        $stmt->execute([$lastEventId]);
        $newEvents = $stmt->fetchAll();

        if ($newEvents) {
            $lastEventId = (int)end($newEvents)['id'];

            $types = array_unique(array_column($newEvents, 'type'));

            $needsQueue    = in_array('queue_changed', $types);
            $needsOrders   = array_intersect(['order_created', 'order_updated'], $types);
            $needsProducts = in_array('product_changed', $types);
            $needsStats    = $needsOrders || $needsProducts;

            if ($needsQueue || $needsOrders) {
                sseFlush('queue', getQueueState($db));
            }

            if ($isAdmin) {
                if ($needsOrders) {
                    sseFlush('orders_updated', ['orders' => getAdminOrders($db)]);
                }
                if ($needsStats) {
                    sseFlush('stats_updated', getStats($db));
                }
                if ($needsProducts) {
                    sseFlush('products_updated', ['products' => $db->query("SELECT id, name, stock, active, price, category FROM products ORDER BY id")->fetchAll()]);
                }
            } else {
                if ($needsOrders) {
                    $relevant = array_filter($newEvents, function($e) use ($userId) {
                        $payload = json_decode($e['payload'], true);
                        return isset($payload['user_id']) && (int)$payload['user_id'] === $userId;
                    });
                    if ($relevant || $needsQueue) {
                        sseFlush('my_orders_updated', ['orders' => getUserOrders($db, $userId)]);
                    } elseif ($needsQueue) {
                        sseFlush('my_orders_updated', ['orders' => getUserOrders($db, $userId)]);
                    }
                }
                if ($needsQueue) {
                    sseFlush('my_orders_updated', ['orders' => getUserOrders($db, $userId)]);
                }
            }
        }

        echo ": heartbeat " . time() . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();

    } catch (Exception $e) {
        logError('SSE loop error', ['message' => $e->getMessage(), 'user_id' => $userId]);
    }

    sleep(3);
}

sseFlush('reconnect', ['message' => 'session_end']);