<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'products') {

    if ($method === 'GET' && $id === null) {
        try {
            $showAll = !empty($_GET['all']) && $_GET['all'] == '1';
            $where   = $showAll ? "WHERE 1=1" : "WHERE active=1";
            $params  = [];

            if (!empty($_GET['category'])) {
                $where   .= " AND category=?";
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['q'])) {
                $where   .= " AND (name LIKE ? OR description LIKE ?)";
                $params[] = '%' . $_GET['q'] . '%';
                $params[] = '%' . $_GET['q'] . '%';
            }

            $stmt = $db->prepare("SELECT * FROM products $where ORDER BY category, created_at ASC");
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll());
        } catch (PDOException $e) {
            logDatabaseError('products/get_all', $e);
            jsonError('Error al obtener productos: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $id !== null) {
        try {
            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) jsonError('Producto no encontrado', 404);
            jsonResponse($p);
        } catch (PDOException $e) {
            logDatabaseError('products/get_by_id', $e);
            jsonError('Error al obtener producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'POST') {
        try {
            requireAdmin();
            if (empty($body['name']) || !isset($body['price'])) jsonError('Nombre y precio requeridos');

            $validCats = ['almuerzos','desayunos','rapidos','bebidas','snacks','postres','general'];
            $category  = in_array($body['category'] ?? '', $validCats) ? $body['category'] : 'general';

            $db->prepare("INSERT INTO products (name, description, price, stock, category, image) VALUES (?,?,?,?,?,?)")
               ->execute([trim($body['name']), trim($body['description'] ?? ''), (float)$body['price'], (int)($body['stock'] ?? 0), $category, $body['image'] ?? null]);
            $newId  = (int)$db->lastInsertId();
            $qrData = generateQRData(['type' => 'product', 'id' => $newId, 'name' => $body['name'], 'price' => (float)$body['price']]);
            $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $newId]);

            emitEvent($db, 'product_changed', ['product_id' => $newId, 'action' => 'created']);

            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$newId]);
            jsonResponse($stmt->fetch(), 201);
        } catch (PDOException $e) {
            logDatabaseError('products/create', $e);
            jsonError('Error al crear producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'PUT' && $id !== null) {
        try {
            requireAdmin();
            $stmt = $db->prepare("SELECT id FROM products WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError('Producto no encontrado', 404);

            $fields = [];
            $params = [];
            foreach (['name','description','price','stock','category','image','active'] as $f) {
                if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = $body[$f]; }
            }
            if (empty($fields)) jsonError('Sin campos para actualizar');

            $params[] = $id;
            $db->prepare("UPDATE products SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

            if (isset($body['name']) || isset($body['price'])) {
                $p      = $db->query("SELECT name, price FROM products WHERE id=$id")->fetch();
                $qrData = generateQRData(['type' => 'product', 'id' => $id, 'name' => $p['name'], 'price' => $p['price']]);
                $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $id]);
            }

            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'updated']);

            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetch());
        } catch (PDOException $e) {
            logDatabaseError('products/update', $e);
            jsonError('Error al actualizar producto: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'DELETE' && $id !== null) {
        try {
            requireAdmin();
            $stmt = $db->prepare("SELECT name FROM products WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError('Producto no encontrado', 404);

            $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'deleted']);
            jsonResponse(['message' => 'Producto desactivado']);
        } catch (PDOException $e) {
            logDatabaseError('products/delete', $e);
            jsonError('Error al desactivar producto: ' . $e->getMessage(), 500);
        }
    }

    jsonError('Ruta products no encontrada', 404);
}