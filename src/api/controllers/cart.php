<?php
$db = getDB();

if ($resource === 'cart') {
    $auth = requireAuth();
    $userId = $auth['id'];

    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT ci.*, p.name, p.price, p.image, p.stock, p.category,
                   (ci.quantity * p.price) as subtotal
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id=? AND p.active=1
        ");
        $stmt->execute([$userId]);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $action === 'add') {
        if (empty($body['product_id'])) jsonError('product_id requerido');
        $qty = max(1, (int)($body['quantity'] ?? 1));

        $stmt = $db->prepare("SELECT id, stock FROM products WHERE id=? AND active=1");
        $stmt->execute([$body['product_id']]);
        $product = $stmt->fetch();
        if (!$product) jsonError('Producto no encontrado', 404);

        $existing = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=?");
        $existing->execute([$userId, $body['product_id']]);
        $item = $existing->fetch();

        if ($item) {
            $newQty = $item['quantity'] + $qty;
            $db->prepare("UPDATE cart_items SET quantity=? WHERE id=?")->execute([$newQty, $item['id']]);
        } else {
            $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)")
               ->execute([$userId, $body['product_id'], $qty]);
        }
        jsonResponse(['message' => 'Agregado al carrito']);
    }

    if ($method === 'PUT' && $action === 'update') {
        if (empty($body['product_id']) || !isset($body['quantity'])) jsonError('Campos requeridos');
        $qty = (int)$body['quantity'];
        if ($qty <= 0) {
            $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$userId, $body['product_id']]);
        } else {
            $db->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND product_id=?")->execute([$qty, $userId, $body['product_id']]);
        }
        jsonResponse(['message' => 'Carrito actualizado']);
    }

    if ($method === 'DELETE' && $id !== null) {
        $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$userId, $id]);
        jsonResponse(['message' => 'Eliminado del carrito']);
    }

    if ($method === 'DELETE' && $action === 'clear') {
        $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
        jsonResponse(['message' => 'Carrito vaciado']);
    }

    jsonError('Ruta cart no encontrada', 404);
}