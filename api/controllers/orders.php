<?php
$db = getDB();

if ($resource === 'orders') {

    if ($method === 'GET' && $action === 'stats') {
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
        jsonResponse($stats);
    }

    if ($method === 'GET' && $action === 'scan') {
        requireAdmin();
        $token = $segments[2] ?? null;
        if (!$token) jsonError('Token requerido', 400);
        $stmt = $db->prepare("SELECT o.*, u.full_name, u.email, u.grade, u.doc_type, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id WHERE o.qr_token=?");
        $stmt->execute([$token]);
        $order = $stmt->fetch();
        if (!$order) jsonError('Orden no encontrada', 404);
        $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();
        jsonResponse($order);
    }

    if ($method === 'GET' && $action === 'my') {
        $auth = requireAuth();
        $stmt = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
        $stmt->execute([$auth['id']]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        jsonResponse($orders);
    }

    if ($method === 'POST' && $action === 'checkout') {
        $auth = requireAuth();
        $userId = $auth['id'];

        $stmt = $db->prepare("
            SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id=? AND p.active=1
        ");
        $stmt->execute([$userId]);
        $cartItems = $stmt->fetchAll();

        if (empty($cartItems)) jsonError('El carrito está vacío');

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
        try {
            $stmt = $db->prepare("INSERT INTO orders (user_id, total, qr_code, qr_token) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $total, $qrData, $token]);
            $orderId = (int)$db->lastInsertId();

            foreach ($cartItems as $item) {
                $db->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_price,quantity,subtotal) VALUES (?,?,?,?,?,?)")
                   ->execute([$orderId, $item['product_id'], $item['name'], $item['price'], $item['quantity'], $item['price'] * $item['quantity']]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Error al crear la orden', 500);
        }

        $order = $db->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
        $order['items'] = $cartItems;
        jsonResponse($order, 201);
    }

    if ($method === 'GET' && $id !== null) {
        $auth = requireAuth();
        $stmt = $db->prepare("SELECT o.*, u.full_name, u.grade FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) jsonError('Orden no encontrada', 404);
        if ($auth['role'] !== 'admin' && $order['user_id'] !== $auth['id']) jsonError('Acceso denegado', 403);
        $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();
        jsonResponse($order);
    }

    if ($method === 'PUT' && $id !== null && $action === 'status') {
        requireAdmin();
        $status = $body['status'] ?? null;
        if (!in_array($status, ['pending','paid','cancelled'])) jsonError('Estado inválido');
        $paidAt = $status === 'paid' ? date('c') : null;
        $db->prepare("UPDATE orders SET status=?, paid_at=? WHERE id=?")->execute([$status, $paidAt, $id]);
        $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'GET') {
        requireAdmin();
        $stmt = $db->query("SELECT o.*, u.full_name, u.grade, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC");
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        jsonResponse($orders);
    }

    jsonError('Ruta orders no encontrada', 404);
}