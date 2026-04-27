<?php
$db = getDB();

if ($resource === 'products') {
    if ($method === 'GET' && $id === null) {
        $showAll = !empty($_GET['all']) && $_GET['all'] == '1';
        $where = $showAll ? "WHERE 1=1" : "WHERE active=1";
        $params = [];
        if (!empty($_GET['category'])) {
            $where .= " AND category=?";
            $params[] = $_GET['category'];
        }
        if (!empty($_GET['q'])) {
            $where .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = '%' . $_GET['q'] . '%';
            $params[] = '%' . $_GET['q'] . '%';
        }
        $stmt = $db->prepare("SELECT * FROM products $where ORDER BY category, created_at ASC");
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'GET' && $id !== null) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) jsonError('Producto no encontrado', 404);
        jsonResponse($p);
    }

    if ($method === 'POST') {
        requireAdmin();
        if (empty($body['name']) || !isset($body['price'])) jsonError('Nombre y precio requeridos');

        $validCats = ['almuerzos','desayunos','rapidos','bebidas','snacks','postres','general'];
        $category = trim($body['category'] ?? 'general');
        if (!in_array($category, $validCats)) $category = 'general';

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

        $qrData = generateQRData(['type' => 'product', 'id' => $newId, 'name' => $body['name'], 'price' => (float)$body['price']]);
        $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $newId]);

        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$newId]);
        jsonResponse($stmt->fetch(), 201);
    }

    if ($method === 'PUT' && $id !== null) {
        requireAdmin();
        $stmt = $db->prepare("SELECT id FROM products WHERE id=?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) jsonError('Producto no encontrado', 404);

        $fields = [];
        $params = [];
        $allowed = ['name','description','price','stock','category','image','active'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f=?";
                $params[] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Sin campos para actualizar');
        $params[] = $id;
        $db->prepare("UPDATE products SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

        if (isset($body['name']) || isset($body['price'])) {
            $p = $db->query("SELECT name, price FROM products WHERE id=$id")->fetch();
            $qrData = generateQRData(['type' => 'product', 'id' => $id, 'name' => $p['name'], 'price' => $p['price']]);
            $db->prepare("UPDATE products SET qr_code=? WHERE id=?")->execute([$qrData, $id]);
        }

        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetch());
    }

    if ($method === 'DELETE' && $id !== null) {
        requireAdmin();
        $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
        jsonResponse(['message' => 'Producto eliminado']);
    }

    jsonError('Ruta products no encontrada', 404);
}