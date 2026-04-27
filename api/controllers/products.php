<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'products') {
    
    if ($method === 'GET' && $id === null) {
        logInfo('Obteniendo lista de productos');
        
        try {
            $showAll = !empty($_GET['all']) && $_GET['all'] == '1';
            $where = $showAll ? "WHERE 1=1" : "WHERE active=1";
            $params = [];
            
            if (!empty($_GET['category'])) {
                $where .= " AND category=?";
                $params[] = $_GET['category'];
                logInfo('Filtrando por categoría', ['category' => $_GET['category']]);
            }
            
            if (!empty($_GET['q'])) {
                $where .= " AND (name LIKE ? OR description LIKE ?)";
                $params[] = '%' . $_GET['q'] . '%';
                $params[] = '%' . $_GET['q'] . '%';
                logInfo('Buscando productos', ['query' => $_GET['q']]);
            }
            
            $stmt = $db->prepare("SELECT * FROM products $where ORDER BY category, created_at ASC");
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            logInfo('Productos obtenidos', ['count' => count($products), 'showAll' => $showAll]);
            jsonResponse($products);
            
        } catch (PDOException $e) {
            logDatabaseError('products/get_all', $e);
            jsonError('Error al obtener productos: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $id !== null) {
        logInfo('Obteniendo producto específico', ['product_id' => $id]);
        
        try {
            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            
            if (!$p) {
                logWarning('Producto no encontrado', ['product_id' => $id]);
                jsonError('Producto no encontrado', 404);
            }
            
            logInfo('Producto encontrado', ['product_id' => $id, 'name' => $p['name']]);
            jsonResponse($p);
            
        } catch (PDOException $e) {
            logDatabaseError('products/get_by_id', $e, ['id' => $id]);
            jsonError('Error al obtener producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'POST') {
        logInfo('Creando nuevo producto');
        
        try {
            requireAdmin();
            
            if (empty($body['name']) || !isset($body['price'])) {
                logWarning('Intento de crear producto sin nombre o precio', ['body' => array_keys($body)]);
                jsonError('Nombre y precio requeridos');
            }

            $validCats = ['almuerzos','desayunos','rapidos','bebidas','snacks','postres','general'];
            $category = trim($body['category'] ?? 'general');
            if (!in_array($category, $validCats)) {
                logWarning('Categoría inválida, usando general', ['category' => $category]);
                $category = 'general';
            }

            $stmt = $db->prepare("
                INSERT INTO products (name, description, price, stock, category, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($body['name']),
                trim($body['description'] ?? ''),
                (float)$body['price'],
                (int)($body['stock'] ?? 0),
                $category,
                $body['image'] ?? null
            ]);
            $newId = (int)$db->lastInsertId();
            
            logInfo('Producto insertado', ['product_id' => $newId, 'name' => $body['name']]);

            $qrData = generateQRData(['type' => 'product', 'id' => $newId, 'name' => $body['name'], 'price' => (float)$body['price']]);
            $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $newId]);
            logInfo('QR generado para producto', ['product_id' => $newId]);

            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$newId]);
            
            logInfo('Producto creado exitosamente', ['product_id' => $newId]);
            jsonResponse($stmt->fetch(), 201);
            
        } catch (PDOException $e) {
            logDatabaseError('products/create', $e, $body);
            jsonError('Error al crear producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'PUT' && $id !== null) {
        logInfo('Actualizando producto', ['product_id' => $id]);
        
        try {
            requireAdmin();
            
            $stmt = $db->prepare("SELECT id FROM products WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                logWarning('Producto no encontrado para actualizar', ['product_id' => $id]);
                jsonError('Producto no encontrado', 404);
            }

            $fields = [];
            $params = [];
            $allowed = ['name','description','price','stock','category','image','active'];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $body)) {
                    $fields[] = "$f=?";
                    $params[] = $body[$f];
                }
            }
            
            if (empty($fields)) {
                logWarning('Intento de actualizar sin campos', ['product_id' => $id]);
                jsonError('Sin campos para actualizar');
            }
            
            $params[] = $id;
            $db->prepare("UPDATE products SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            logInfo('Producto actualizado', ['product_id' => $id, 'fields' => $fields]);

            if (isset($body['name']) || isset($body['price'])) {
                $p = $db->query("SELECT name, price FROM products WHERE id=$id")->fetch();
                $qrData = generateQRData(['type' => 'product', 'id' => $id, 'name' => $p['name'], 'price' => $p['price']]);
                $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $id]);
                logInfo('QR actualizado para producto', ['product_id' => $id]);
            }

            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetch());
            
        } catch (PDOException $e) {
            logDatabaseError('products/update', $e, ['id' => $id, 'body' => $body]);
            jsonError('Error al actualizar producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'DELETE' && $id !== null) {
        logInfo('Eliminando (desactivando) producto', ['product_id' => $id]);
        
        try {
            requireAdmin();
            
            $stmt = $db->prepare("SELECT name FROM products WHERE id=?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                logWarning('Producto no encontrado para eliminar', ['product_id' => $id]);
                jsonError('Producto no encontrado', 404);
            }
            
            $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
            
            logInfo('Producto desactivado', ['product_id' => $id, 'name' => $product['name']]);
            jsonResponse(['message' => 'Producto desactivado']);
            
        } catch (PDOException $e) {
            logDatabaseError('products/delete', $e, ['id' => $id]);
            jsonError('Error al desactivar producto: ' . $e->getMessage(), 500);
        }
    }

    logWarning('Ruta products no encontrada', ['method' => $method, 'id' => $id]);
    jsonError('Ruta products no encontrada', 404);
}
?>