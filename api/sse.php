<?php
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/middleware/auth.php';

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

while (ob_get_level() > 0) {
    ob_end_clean();
}

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
$user = null;

if ($token) {
    $user = jwtDecode($token);
}

if (!$user) {
    $authUser = getAuthUser();
    if ($authUser) $user = $authUser;
}

if (!$user) {
    echo "event: error\n";
    echo "data: {\"error\":\"unauthorized\"}\n\n";
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
            COUNT(*) as total_orders,
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

$lastQueueHash   = '';
$lastOrderHash   = '';
$lastStatsHash   = '';
$lastProductHash = '';

set_time_limit(90);
$start   = time();
$maxTime = 55;

sseFlush('connected', ['status' => 'ok', 'role' => $user['role'], 'user_id' => $user['id']]);

while ((time() - $start) < $maxTime) {
    if (connection_aborted()) {
        break;
    }

    try {
        $queueState = getQueueState($db);
        $queueHash  = md5(json_encode($queueState));
        if ($queueHash !== $lastQueueHash) {
            $lastQueueHash = $queueHash;
            sseFlush('queue', $queueState);
        }

        if ($user['role'] === 'admin') {
            $orders = $db->query("
                SELECT o.id, o.status, o.total, o.created_at, o.turn_number,
                       u.full_name, u.grade, u.doc_number
                FROM orders o
                JOIN users u ON u.id = o.user_id
                ORDER BY o.created_at DESC
                LIMIT 100
            ")->fetchAll();

            $orderHash = md5(json_encode($orders));
            if ($orderHash !== $lastOrderHash) {
                $lastOrderHash = $orderHash;
                sseFlush('orders_updated', ['orders' => $orders]);
            }

            $stats = getStatsCached($db);
            $statsHash = md5(json_encode($stats));
            if ($statsHash !== $lastStatsHash) {
                $lastStatsHash = $statsHash;
                sseFlush('stats_updated', $stats);
            }

            $products    = $db->query("SELECT id, name, stock, active, price, category FROM products ORDER BY id")->fetchAll();
            $productHash = md5(json_encode($products));
            if ($productHash !== $lastProductHash) {
                $lastProductHash = $productHash;
                sseFlush('products_updated', ['products' => $products]);
            }
        } else {
            $stmt = $db->prepare("
                SELECT o.id, o.status, o.total, o.created_at, o.turn_number,
                       o.qr_token, o.qr_code
                FROM orders o
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user['id']]);
            $orders = $stmt->fetchAll();

            $orderIds = array_column($orders, 'id');
            if ($orderIds) {
                $in = implode(',', array_fill(0, count($orderIds), '?'));

                $stmt = $db->prepare("
                    SELECT order_id, product_name, quantity, subtotal 
                    FROM order_items 
                    WHERE order_id IN ($in)
                ");
                $stmt->execute($orderIds);

                $itemsGrouped = [];
                foreach ($stmt->fetchAll() as $item) {
                    $itemsGrouped[$item['order_id']][] = $item;
                }

                foreach ($orders as &$order) {
                    $order['items'] = $itemsGrouped[$order['id']] ?? [];
                }
            }
            unset($order);

            $orderHash = md5(json_encode($orders));
            if ($orderHash !== $lastOrderHash) {
                $lastOrderHash = $orderHash;
                sseFlush('my_orders_updated', ['orders' => $orders]);
            }
        }

        echo ": heartbeat " . time() . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();

    } catch (Exception $e) {
        logError('SSE loop error', ['message' => $e->getMessage(), 'user_id' => $user['id']]);
    }

    sleep(5);
}

function getStatsCached(PDO $db): array {
    static $cache = null;
    static $lastTime = 0;

    if ($cache && (time() - $lastTime < 30)) {
        return $cache;
    }

    $cache = getStats($db);
    $lastTime = time();

    return $cache;
}

sseFlush('reconnect', ['message' => 'session_end']);