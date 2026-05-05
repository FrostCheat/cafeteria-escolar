<?php
$db = getDB();
requireAdmin();

if ($resource === 'users') {
    if ($method === 'GET' && $id === null) {
        try {
            $where = "WHERE role='user'";
            $params = [];
            if (!empty($_GET['q'])) {
                $where .= " AND (full_name LIKE ? OR email LIKE ? OR doc_number LIKE ? OR grade LIKE ?)";
                $q = '%' . $_GET['q'] . '%';
                $params = [$q, $q, $q, $q];
            }
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked,created_at FROM users $where ORDER BY full_name");
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll());
        } catch (PDOException $e) { jsonError('Error al obtener usuarios', 500); }
    }

    if ($method === 'GET' && $id !== null) {
        try {
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked,created_at FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) jsonError('Usuario no encontrado', 404);
            $orders = $db->prepare("SELECT id, total, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
            $orders->execute([$id]);
            $user['orders'] = $orders->fetchAll();
            jsonResponse($user);
        } catch (PDOException $e) { jsonError('Error al obtener usuario', 500); }
    }

    if ($method === 'PUT' && $id !== null && $action === 'block') {
        try {
            $stmt = $db->prepare("SELECT blocked FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) jsonError('Usuario no encontrado', 404);
            $newStatus = $user['blocked'] ? 0 : 1;
            $db->prepare("UPDATE users SET blocked=? WHERE id=?")->execute([$newStatus, $id]);
            jsonResponse(['blocked' => (bool)$newStatus]);
        } catch (PDOException $e) { jsonError('Error al cambiar estado', 500); }
    }

    if ($method === 'PUT' && $id !== null) {
        try {
            $fields = []; $params = [];
            foreach (['full_name','birth_date','grade','doc_type','doc_number','email'] as $f) {
                if (array_key_exists($f, $body)) {
                    $fields[] = "$f=?";
                    $params[] = $f === 'grade' ? formatGrade($body[$f]) : $body[$f];
                }
            }
            if (!empty($body['password'])) {
                $fields[] = "password=?";
                $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
            }
            if (empty($fields)) jsonError('Sin campos para actualizar');
            $params[] = $id;
            $db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked FROM users WHERE id=?");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetch());
        } catch (PDOException $e) { jsonError('Error al actualizar usuario', 500); }
    }

    if ($method === 'DELETE' && $id !== null) {
        try {
            $stmt = $db->prepare("SELECT role FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) jsonError('Usuario no encontrado', 404);
            if ($user['role'] === 'admin') jsonError('No puedes eliminar administradores');
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            jsonResponse(['message' => 'Usuario eliminado']);
        } catch (PDOException $e) { jsonError('Error al eliminar usuario', 500); }
    }

    jsonError('Ruta users no encontrada', 404);
}