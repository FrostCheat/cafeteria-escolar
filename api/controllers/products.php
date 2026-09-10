<?php
$db = getDB();

if ($resource === 'products') {
    if ($method === 'GET' && $id === null) {
        try {
            $showAll = !empty($_GET['all']) && $_GET['all'] == '1';
            $where   = $showAll ? "WHERE 1=1" : "WHERE active=1";
            $params  = [];
            if (!empty($_GET['category'])) { $where .= " AND category=?"; $params[] = $_GET['category']; }
            if (!empty($_GET['q'])) { $where .= " AND (name LIKE ? OR description LIKE ?)"; $params[] = '%'.$_GET['q'].'%'; $params[] = '%'.$_GET['q'].'%'; }
            $stmt = $db->prepare("SELECT * FROM products $where ORDER BY category, created_at ASC");
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll());
        } catch (PDOException $e) { jsonError('Error al obtener productos', 500); }
    }

    if ($method === 'GET' && $id !== null) {
        try {
            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) jsonError('Producto no encontrado', 404);
            jsonResponse($p);
        } catch (PDOException $e) { jsonError('Error al obtener producto', 500); }
    }

    if ($method === 'POST' && $id === null && $action === 'upload-image') {
        requireAdmin();
        if (empty($body['image'])) jsonError('Imagen requerida');
        $dataUrl = $body['image'];
        if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/', $dataUrl, $m)) {
            jsonError('Formato de imagen no válido. Usa PNG, JPG, GIF o WEBP');
        }
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $decoded = base64_decode($base64, true);
        if ($decoded === false) jsonError('No se pudo procesar la imagen');
        if (strlen($decoded) > 3 * 1024 * 1024) jsonError('La imagen no debe superar 3MB');
        try {
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
            $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (file_put_contents(UPLOAD_PATH . $filename, $decoded) === false) {
                jsonError('No se pudo guardar la imagen', 500);
            }
            jsonResponse(['url' => '/imgs/uploads/' . $filename]);
        } catch (Exception $e) { jsonError('Error al subir la imagen', 500); }
    }

    if ($method === 'POST' && $id === null && $action === null) {
        requireAdmin();
        if (empty($body['name']) || !isset($body['price'])) jsonError('Nombre y precio requeridos');
        try {
            $validCats = ['almuerzos','desayunos','rapidos','bebidas','snacks','postres','general'];
            $category  = in_array($body['category'] ?? '', $validCats) ? $body['category'] : 'general';
            $db->prepare("INSERT INTO products (name, description, price, stock, category, image, emoji) VALUES (?,?,?,?,?,?,?)")
               ->execute([trim($body['name']), trim($body['description'] ?? ''), (float)$body['price'], (int)($body['stock'] ?? 0), $category, $body['image'] ?? null, $body['emoji'] ?? null]);
            $newId  = (int)$db->lastInsertId();
            $qrData = generateQRData(['type' => 'product', 'id' => $newId, 'name' => $body['name'], 'price' => (float)$body['price']]);
            $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $newId]);
            emitEvent($db, 'product_changed', ['product_id' => $newId, 'action' => 'created']);
            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$newId]);
            jsonResponse($stmt->fetch(), 201);
        } catch (PDOException $e) { jsonError('Error al crear producto', 500); }
    }

    if ($method === 'PUT' && $id !== null && $action === 'toggle') {
        requireAdmin();
        try {
            $stmt = $db->prepare("SELECT active FROM products WHERE id=?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) jsonError('Producto no encontrado', 404);
            $newState = $p['active'] ? 0 : 1;
            $db->prepare("UPDATE products SET active=? WHERE id=?")->execute([$newState, $id]);
            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'toggled']);
            jsonResponse(['active' => (bool)$newState]);
        } catch (PDOException $e) { jsonError('Error al cambiar estado', 500); }
    }

    if ($method === 'PUT' && $id !== null) {
        requireAdmin();
        try {
            $stmt = $db->prepare("SELECT id FROM products WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError('Producto no encontrado', 404);
            $fields = []; $params = [];
            foreach (['name','description','price','stock','category','image','emoji','active'] as $f) {
                if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = $body[$f]; }
            }
            if (empty($fields)) jsonError('Sin campos para actualizar');
            $params[] = $id;
            $db->prepare("UPDATE products SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            if (isset($body['name']) || isset($body['price'])) {
                $p = $db->query("SELECT name, price FROM products WHERE id=$id")->fetch();
                $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([generateQRData(['type' => 'product', 'id' => $id, 'name' => $p['name'], 'price' => $p['price']]), $id]);
            }
            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'updated']);
            $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetch());
        } catch (PDOException $e) { jsonError('Error al actualizar producto', 500); }
    }

    if ($method === 'DELETE' && $id !== null && $action === 'hard') {
        requireAdmin();
        try {
            $stmt = $db->prepare("SELECT name FROM products WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonError('Producto no encontrado', 404);
            $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'hard_deleted']);
            jsonResponse(['message' => 'Producto eliminado permanentemente']);
        } catch (PDOException $e) { jsonError('Error al eliminar producto', 500); }
    }

    if ($method === 'DELETE' && $id !== null) {
        requireAdmin();
        try {
            $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
            emitEvent($db, 'product_changed', ['product_id' => $id, 'action' => 'deactivated']);
            jsonResponse(['message' => 'Producto desactivado']);
        } catch (PDOException $e) { jsonError('Error al desactivar producto', 500); }
    }

    jsonError('Ruta products no encontrada', 404);
}