<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'orders') {

    if ($method === 'GET' && $action === 'stats') {
        logInfo('Obteniendo estadísticas de órdenes');
        
        try {
            requireAdmin();
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
            $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
            
            logInfo('Estadísticas obtenidas exitosamente', $stats);
            jsonResponse($stats);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/stats', $e);
            jsonError('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $action === 'scan') {
        logInfo('Escanear QR de orden');
        
        try {
            requireAdmin();
            $token = $segments[2] ?? null;
            if (!$token) {
                logWarning('Token requerido para escanear orden');
                jsonError('Token requerido', 400);
            }
            
            $stmt = $db->prepare("SELECT o.*, u.full_name, u.email, u.grade, u.doc_type, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id WHERE o.qr_token=?");
            $stmt->execute([$token]);
            $order = $stmt->fetch();
            
            if (!$order) {
                logWarning('Orden no encontrada al escanear', ['token' => $token]);
                jsonError('Orden no encontrada', 404);
            }
            
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
            
            logInfo('Orden encontrada por QR', ['order_id' => $order['id'], 'token' => $token]);
            jsonResponse($order);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/scan', $e);
            jsonError('Error al escanear orden: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $action === 'my') {
        logInfo('Obteniendo mis órdenes');
        
        try {
            $auth = requireAuth();
            $stmt = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
            $stmt->execute([$auth['id']]);
            $orders = $stmt->fetchAll();
            
            foreach ($orders as &$order) {
                $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
                $items->execute([$order['id']]);
                $order['items'] = $items->fetchAll();
            }
            
            logInfo('Órdenes del usuario obtenidas', [
                'user_id' => $auth['id'],
                'count' => count($orders)
            ]);
            jsonResponse($orders);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/my', $e);
            jsonError('Error al obtener tus órdenes: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'POST' && $action === 'checkout') {
        logInfo('Iniciando checkout');
        
        try {
            $auth = requireAuth();
            $userId = $auth['id'];
            logInfo('Checkout usuario', ['user_id' => $userId]);

            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.user_id=? AND p.active=1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                logWarning('Checkout fallido - carrito vacío', ['user_id' => $userId]);
                jsonError('El carrito está vacío');
            }

            // Verificar stock
            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    logWarning('Stock insuficiente en checkout', [
                        'user_id' => $userId,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'requested' => $item['quantity'],
                        'available' => $item['stock']
                    ]);
                    jsonError("Stock insuficiente para: {$item['name']}. Disponible: {$item['stock']}", 400);
                }
            }

            $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $token = generateToken(24);

            $qrPayload = [
                'type'    => 'order',
                'token'   => $token,
                'user_id' => $userId,
                'user'    => $auth['name'],
                'total'   => $total,
                'items'   => array_map(fn($i) => ['name' => $i['name'], 'qty' => $i['quantity'], 'price' => $i['price']], $cartItems)
            ];
            $qrData = generateQRData($qrPayload);

            $db->beginTransaction();
            
            $stmt = $db->prepare("INSERT INTO orders (user_id, total, qr_code, qr_token) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $total, $qrData, $token]);
            $orderId = (int)$db->lastInsertId();
            
            logInfo('Orden creada', ['order_id' => $orderId, 'user_id' => $userId, 'total' => $total]);

            foreach ($cartItems as $item) {
                $db->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_price,quantity,subtotal) VALUES (?,?,?,?,?,?)")
                   ->execute([$orderId, $item['product_id'], $item['name'], $item['price'], $item['quantity'], $item['price'] * $item['quantity']]);
                
                // Actualizar stock
                $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
            $db->commit();
            
            logInfo('Checkout completado exitosamente', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'items_count' => count($cartItems)
            ]);

            $order = $db->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
            $order['items'] = $cartItems;
            jsonResponse($order, 201);
            
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            logDatabaseError('orders/checkout', $e);
            jsonError('Error al crear la orden: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            logError('Checkout error general', ['message' => $e->getMessage()]);
            jsonError('Error al crear la orden', 500);
        }
    }

    if ($method === 'GET' && $id !== null) {
        logInfo('Obteniendo orden específica', ['order_id' => $id]);
        
        try {
            $auth = requireAuth();
            $stmt = $db->prepare("SELECT o.*, u.full_name, u.grade FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                logWarning('Orden no encontrada', ['order_id' => $id]);
                jsonError('Orden no encontrada', 404);
            }
            
            if ($auth['role'] !== 'admin' && $order['user_id'] !== $auth['id']) {
                logWarning('Acceso denegado a orden', [
                    'order_id' => $id,
                    'user_id' => $auth['id'],
                    'role' => $auth['role']
                ]);
                jsonError('Acceso denegado', 403);
            }
            
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$id]);
            $order['items'] = $items->fetchAll();
            
            logInfo('Orden obtenida', ['order_id' => $id, 'status' => $order['status']]);
            jsonResponse($order);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/get_by_id', $e);
            jsonError('Error al obtener orden: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'PUT' && $id !== null && $action === 'status') {
        logInfo('Actualizando estado de orden', ['order_id' => $id, 'new_status' => $body['status'] ?? 'unknown']);
        
        try {
            requireAdmin();
            $status = $body['status'] ?? null;
            
            if (!in_array($status, ['pending','paid','cancelled'])) {
                logWarning('Estado de orden inválido', ['status' => $status]);
                jsonError('Estado inválido');
            }
            
            $paidAt = $status === 'paid' ? date('c') : null;
            $db->prepare("UPDATE orders SET status=?, paid_at=? WHERE id=?")->execute([$status, $paidAt, $id]);
            
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            logInfo('Estado de orden actualizado', [
                'order_id' => $id,
                'old_status' => $order['status'],
                'new_status' => $status
            ]);
            jsonResponse($order);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/status', $e);
            jsonError('Error al actualizar estado: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET') {
        logInfo('Obteniendo todas las órdenes (admin)');
        
        try {
            requireAdmin();
            $stmt = $db->query("SELECT o.*, u.full_name, u.grade, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC");
            $orders = $stmt->fetchAll();
            
            foreach ($orders as &$order) {
                $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
                $items->execute([$order['id']]);
                $order['items'] = $items->fetchAll();
            }
            
            logInfo('Todas las órdenes obtenidas', ['count' => count($orders)]);
            jsonResponse($orders);
            
        } catch (PDOException $e) {
            logDatabaseError('orders/get_all', $e);
            jsonError('Error al obtener órdenes: ' . $e->getMessage(), 500);
        }
    }

    logWarning('Ruta orders no encontrada', ['method' => $method, 'action' => $action, 'id' => $id]);
    jsonError('Ruta orders no encontrada', 404);
}
?>