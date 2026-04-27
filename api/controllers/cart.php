<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'cart') {
    try {
        $auth = requireAuth();
        $userId = $auth['id'];
        logInfo('Carrito - Usuario autenticado', ['user_id' => $userId, 'method' => $method, 'action' => $action]);

        if ($method === 'GET') {
            logInfo('Obteniendo carrito del usuario', ['user_id' => $userId]);
            
            try {
                $stmt = $db->prepare("
                    SELECT ci.*, p.name, p.price, p.image, p.stock, p.category,
                           (ci.quantity * p.price) as subtotal
                    FROM cart_items ci
                    JOIN products p ON p.id = ci.product_id
                    WHERE ci.user_id=? AND p.active=1
                ");
                $stmt->execute([$userId]);
                $cartItems = $stmt->fetchAll();
                
                logInfo('Carrito obtenido exitosamente', [
                    'user_id' => $userId, 
                    'items_count' => count($cartItems)
                ]);
                
                jsonResponse($cartItems);
                
            } catch (PDOException $e) {
                logDatabaseError('cart/GET', $e);
                jsonError('Error al obtener el carrito: ' . $e->getMessage(), 500);
            }
        }

        if ($method === 'POST' && $action === 'add') {
            if (empty($body['product_id'])) {
                logWarning('Intento de agregar al carrito sin product_id', ['user_id' => $userId]);
                jsonError('product_id requerido');
            }
            
            $qty = max(1, (int)($body['quantity'] ?? 1));
            logInfo('Agregando producto al carrito', [
                'user_id' => $userId,
                'product_id' => $body['product_id'],
                'quantity' => $qty
            ]);

            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id, stock, name FROM products WHERE id=? AND active=1");
                $stmt->execute([$body['product_id']]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    logWarning('Producto no encontrado', [
                        'user_id' => $userId,
                        'product_id' => $body['product_id']
                    ]);
                    jsonError('Producto no encontrado', 404);
                }

                $existing = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=?");
                $existing->execute([$userId, $body['product_id']]);
                $item = $existing->fetch();

                if ($item) {
                    $newQty = $item['quantity'] + $qty;
                    if ($newQty > $product['stock']) {
                        logWarning('Stock insuficiente al actualizar carrito', [
                            'user_id' => $userId,
                            'product_id' => $body['product_id'],
                            'requested' => $newQty,
                            'stock' => $product['stock']
                        ]);
                        jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                    }
                    
                    $db->prepare("UPDATE cart_items SET quantity=? WHERE id=?")->execute([$newQty, $item['id']]);
                    logInfo('Cantidad actualizada en carrito', [
                        'user_id' => $userId,
                        'product_id' => $body['product_id'],
                        'old_quantity' => $item['quantity'],
                        'new_quantity' => $newQty
                    ]);
                } else {
                    if ($qty > $product['stock']) {
                        logWarning('Stock insuficiente para nuevo item', [
                            'user_id' => $userId,
                            'product_id' => $body['product_id'],
                            'requested' => $qty,
                            'stock' => $product['stock']
                        ]);
                        jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                    }
                    
                    $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)")
                       ->execute([$userId, $body['product_id'], $qty]);
                    logInfo('Producto agregado al carrito', [
                        'user_id' => $userId,
                        'product_id' => $body['product_id'],
                        'quantity' => $qty
                    ]);
                }
                
                $db->commit();
                jsonResponse(['message' => 'Agregado al carrito']);
                
            } catch (PDOException $e) {
                $db->rollBack();
                logDatabaseError('cart/add', $e);
                jsonError('Error al agregar al carrito: ' . $e->getMessage(), 500);
            }
        }

        if ($method === 'PUT' && $action === 'update') {
            if (empty($body['product_id']) || !isset($body['quantity'])) {
                logWarning('Intento de actualizar carrito sin campos requeridos', [
                    'user_id' => $userId,
                    'received' => array_keys($body)
                ]);
                jsonError('Campos requeridos: product_id, quantity');
            }
            
            $qty = (int)$body['quantity'];
            logInfo('Actualizando cantidad en carrito', [
                'user_id' => $userId,
                'product_id' => $body['product_id'],
                'new_quantity' => $qty
            ]);

            try {
                if ($qty <= 0) {
                    $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$userId, $body['product_id']]);
                    logInfo('Producto eliminado del carrito (quantity <= 0)', [
                        'user_id' => $userId,
                        'product_id' => $body['product_id']
                    ]);
                } else {
                    $stmt = $db->prepare("SELECT stock FROM products WHERE id=? AND active=1");
                    $stmt->execute([$body['product_id']]);
                    $product = $stmt->fetch();
                    
                    if ($product && $qty > $product['stock']) {
                        logWarning('Stock insuficiente al actualizar', [
                            'user_id' => $userId,
                            'product_id' => $body['product_id'],
                            'requested' => $qty,
                            'stock' => $product['stock']
                        ]);
                        jsonError('Stock insuficiente. Disponible: ' . $product['stock'], 400);
                    }
                    
                    $db->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND product_id=?")->execute([$qty, $userId, $body['product_id']]);
                    logInfo('Carrito actualizado', [
                        'user_id' => $userId,
                        'product_id' => $body['product_id'],
                        'quantity' => $qty
                    ]);
                }
                
                jsonResponse(['message' => 'Carrito actualizado']);
                
            } catch (PDOException $e) {
                logDatabaseError('cart/update', $e);
                jsonError('Error al actualizar carrito: ' . $e->getMessage(), 500);
            }
        }

        if ($method === 'DELETE' && $id !== null) {
            logInfo('Eliminando producto del carrito', [
                'user_id' => $userId,
                'product_id' => $id
            ]);
            
            try {
                $db->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$userId, $id]);
                logInfo('Producto eliminado del carrito', [
                    'user_id' => $userId,
                    'product_id' => $id
                ]);
                jsonResponse(['message' => 'Eliminado del carrito']);
                
            } catch (PDOException $e) {
                logDatabaseError('cart/delete', $e);
                jsonError('Error al eliminar del carrito: ' . $e->getMessage(), 500);
            }
        }

        if ($method === 'DELETE' && $action === 'clear') {
            logInfo('Vaciando carrito completo', ['user_id' => $userId]);
            
            try {
                $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
                logInfo('Carrito vaciado completamente', ['user_id' => $userId]);
                jsonResponse(['message' => 'Carrito vaciado']);
                
            } catch (PDOException $e) {
                logDatabaseError('cart/clear', $e);
                jsonError('Error al vaciar carrito: ' . $e->getMessage(), 500);
            }
        }

        logWarning('Ruta cart no encontrada', [
            'method' => $method,
            'action' => $action,
            'id' => $id,
            'user_id' => $userId
        ]);
        jsonError('Ruta cart no encontrada', 404);
        
    } catch (Exception $e) {
        logError('Error general en cart controller', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        jsonError('Error interno del servidor', 500);
    }
}
?>