<?php
$db   = getDB();
$auth = requireAuth();
$uid  = $auth['id'];

if ($resource === 'cart') {
    if ($method === 'GET') {
        try {
            $stmt = $db->prepare("
                SELECT ci.*, p.name, p.price, p.image, p.emoji, p.stock, p.category,
                       (ci.quantity * p.price) as subtotal
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.user_id=? AND p.active=1
            ");
            $stmt->execute([$uid]);
            jsonResponse($stmt->fetchAll());
        } catch (PDOException $e) { jsonError('Error al obtener el carrito', 500); }
    }

    if ($method === 'POST' && $action === 'add') {
        if (empty($body['product_id'])) jsonError('product_id requerido');
        $qty = max(1, (int)($body['quantity'] ?? 1));
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT id, stock FROM products WHERE id=? AND active=1");
            $stmt->execute([$body['product_id']]);
            $product = $stmt->fetch();
            if (!$product) jsonError('Producto no encontrado', 404);

            $existing = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=?");
            $existing->execute([$uid, $body['product_id']]);
            $item = $existing->fetch();

            if ($item) {
                $newQty = $item['quantity'] + $qty;
                if ($newQty > $product['stock']) jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                $db->prepare("UPDATE cart_items SET quantity=? WHERE id=?")->execute([$newQty, $item['id']]);
            } else {
                if ($qty > $product['stock']) jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)")->execute([$uid, $body['product_id'], $qty]);
            }
            $db->commit();
            jsonResponse(['message' => 'Agregado al carrito']);
        } catch (PDOException $e) {
            $db->rollBack();
            jsonError('Error al agregar al carrito', 500);
        }
    }

    if ($method === 'PUT' && $action === 'update') {
        if (empty($body['product_id']) || !isset($body['quantity'])) jsonError('product_id y quantity requeridos');
        $qty = (int)$body['quantity'];
        try {
            if ($qty <= 0) {
                $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$uid, $body['product_id']]);
            } else {
                $stmt = $db->prepare("SELECT stock FROM products WHERE id=? AND active=1");
                $stmt->execute([$body['product_id']]);
                $product = $stmt->fetch();
                if ($product && $qty > $product['stock']) jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                $db->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND product_id=?")->execute([$qty, $uid, $body['product_id']]);
            }
            jsonResponse(['message' => 'Carrito actualizado']);
        } catch (PDOException $e) { jsonError('Error al actualizar carrito', 500); }
    }

    if ($method === 'DELETE' && $action === 'clear') {
        try {
            $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$uid]);
            jsonResponse(['message' => 'Carrito vaciado']);
        } catch (PDOException $e) { jsonError('Error al vaciar carrito', 500); }
    }

    if ($method === 'DELETE' && $id !== null) {
        try {
            $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$uid, $id]);
            jsonResponse(['message' => 'Eliminado del carrito']);
        } catch (PDOException $e) { jsonError('Error al eliminar del carrito', 500); }
    }

    jsonError('Ruta cart no encontrada', 404);
}