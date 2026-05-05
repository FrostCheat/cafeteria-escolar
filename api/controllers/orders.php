<?php
$db = getDB();

if ($resource === 'orders') {
    if ($method === 'GET' && $action === 'scan') {
        requireAdmin();
        $token = $segments[2] ?? null;
        if (!$token) jsonError('Token requerido', 400);
        try {
            $stmt = $db->prepare("SELECT o.*, u.full_name, u.email, u.grade, u.doc_type, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id WHERE o.qr_token=?");
            $stmt->execute([$token]);
            $order = $stmt->fetch();
            if (!$order) jsonError('Orden no encontrada', 404);
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
            jsonResponse($order);
        } catch (PDOException $e) {
            logDatabaseError('orders/scan', $e);
            jsonError('Error al escanear orden', 500);
        }
    }

    if ($method === 'GET' && $action === 'my') {
        $auth = requireAuth();
        try {
            $stmt = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
            $stmt->execute([$auth['id']]);
            $orders = $stmt->fetchAll();
            foreach ($orders as &$order) {
                $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
                $items->execute([$order['id']]);
                $order['items'] = $items->fetchAll();
            }
            jsonResponse($orders);
        } catch (PDOException $e) {
            logDatabaseError('orders/my', $e);
            jsonError('Error al obtener órdenes', 500);
        }
    }

    if ($method === 'POST' && $action === 'checkout') {
        $auth   = requireAuth();
        $userId = $auth['id'];
        try {
            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci JOIN products p ON p.id=ci.product_id
                WHERE ci.user_id=? AND p.active=1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) jsonError('El carrito está vacío');

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError("Stock insuficiente para: {$item['name']}. Disponible: {$item['stock']}", 400);
                }
            }

            $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $token = generateToken(24);

            $queueCfg   = $db->query("SELECT enabled FROM queue_config WHERE id=1")->fetch();
            $turnEnabled = $queueCfg && $queueCfg['enabled'];
            $turnNumber  = null;

            if ($turnEnabled) {
                $lastTurn   = $db->query("SELECT MAX(turn_number) as max_turn FROM orders WHERE DATE(created_at)=CURDATE()")->fetch();
                $turnNumber = ((int)($lastTurn['max_turn'] ?? 0)) + 1;
            }

            $qrData = generateQRData(['type' => 'order', 'token' => $token, 'user_id' => $userId, 'total' => $total]);

            $db->beginTransaction();
            $db->prepare("INSERT INTO orders (user_id, total, qr_code, qr_token, turn_number) VALUES (?,?,?,?,?)")
               ->execute([$userId, $total, $qrData, $token, $turnNumber]);
            $orderId = (int)$db->lastInsertId();

            foreach ($cartItems as $item) {
                $db->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_price,quantity,subtotal) VALUES (?,?,?,?,?,?)")
                   ->execute([$orderId, $item['product_id'], $item['name'], $item['price'], $item['quantity'], $item['price'] * $item['quantity']]);
                $db->prepare("UPDATE products SET stock=stock-? WHERE id=?")->execute([$item['quantity'], $item['product_id']]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
            emitEvent($db, 'order_created', ['order_id' => $orderId, 'user_id' => $userId]);
            $db->commit();

            $order = $db->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
            $order['items'] = $cartItems;
            jsonResponse($order, 201);
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            logDatabaseError('orders/checkout', $e);
            jsonError('Error al crear la orden', 500);
        }
    }

    if ($method === 'GET' && $id !== null) {
        $auth = requireAuth();
        try {
            $stmt = $db->prepare("SELECT o.*, u.full_name, u.grade, u.doc_type, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            if (!$order) jsonError('Orden no encontrada', 404);
            if ($auth['role'] !== 'admin' && (int)$order['user_id'] !== (int)$auth['id']) jsonError('Acceso denegado', 403);
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$id]);
            $order['items'] = $items->fetchAll();
            jsonResponse($order);
        } catch (PDOException $e) {
            logDatabaseError('orders/get_by_id', $e);
            jsonError('Error al obtener orden', 500);
        }
    }

    if ($method === 'PUT' && $id !== null && $action === 'status') {
        requireAdmin();
        $status = $body['status'] ?? null;
        if (!in_array($status, ['pending', 'paid', 'cancelled'])) jsonError('Estado inválido');
        try {
            $stmt = $db->prepare("SELECT user_id FROM orders WHERE id=?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            if (!$order) jsonError('Orden no encontrada', 404);
            $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
            $db->prepare("UPDATE orders SET status=?, paid_at=? WHERE id=?")->execute([$status, $paidAt, $id]);
            emitEvent($db, 'order_updated', ['order_id' => $id, 'user_id' => (int)$order['user_id'], 'status' => $status]);
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetch());
        } catch (PDOException $e) {
            logDatabaseError('orders/status', $e);
            jsonError('Error al actualizar estado', 500);
        }
    }

    if ($method === 'GET') {
        requireAdmin();
        try {
            $stmt = $db->query("SELECT o.*, u.full_name, u.grade, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 200");
            $orders = $stmt->fetchAll();
            foreach ($orders as &$order) {
                $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
                $items->execute([$order['id']]);
                $order['items'] = $items->fetchAll();
            }
            jsonResponse($orders);
        } catch (PDOException $e) {
            logDatabaseError('orders/get_all', $e);
            jsonError('Error al obtener órdenes', 500);
        }
    }

    jsonError('Ruta orders no encontrada', 404);
}