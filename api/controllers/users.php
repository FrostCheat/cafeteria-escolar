<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'users') {
    logInfo('Acceso a usuarios controller', ['method' => $method, 'id' => $id, 'action' => $action]);
    
    try {
        requireAdmin();
        logInfo('Usuario autorizado como admin');
    } catch (Exception $e) {
        logWarning('Intento de acceso no autorizado a usuarios', [
            'method' => $method,
            'id' => $id,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }

    if ($method === 'GET' && $id === null) {
        logInfo('Obteniendo lista de usuarios');
        
        try {
            $where = "WHERE role='user'";
            $params = [];
            
            if (!empty($_GET['q'])) {
                $where .= " AND (full_name LIKE ? OR email LIKE ? OR doc_number LIKE ? OR grade LIKE ?)";
                $q = '%' . $_GET['q'] . '%';
                $params = [$q, $q, $q, $q];
                logInfo('Buscando usuarios', ['query' => $_GET['q']]);
            }
            
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked,created_at FROM users $where ORDER BY full_name");
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            logInfo('Usuarios obtenidos', ['count' => count($users)]);
            jsonResponse($users);
            
        } catch (PDOException $e) {
            logDatabaseError('users/get_all', $e);
            jsonError('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $id !== null) {
        logInfo('Obteniendo usuario específico', ['user_id' => $id]);
        
        try {
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked,created_at FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                logWarning('Usuario no encontrado', ['user_id' => $id]);
                jsonError('Usuario no encontrado', 404);
            }
            
            $orders = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
            $orders->execute([$id]);
            $user['orders'] = $orders->fetchAll();
            
            logInfo('Usuario encontrado', ['user_id' => $id, 'name' => $user['full_name']]);
            jsonResponse($user);
            
        } catch (PDOException $e) {
            logDatabaseError('users/get_by_id', $e, ['id' => $id]);
            jsonError('Error al obtener usuario: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'PUT' && $id !== null && $action === 'block') {
        logInfo('Cambiando estado de bloqueo de usuario', ['user_id' => $id]);
        
        try {
            $stmt = $db->prepare("SELECT blocked, full_name FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                logWarning('Usuario no encontrado para bloquear/desbloquear', ['user_id' => $id]);
                jsonError('Usuario no encontrado', 404);
            }
            
            $newStatus = $user['blocked'] ? 0 : 1;
            $db->prepare("UPDATE users SET blocked=? WHERE id=?")->execute([$newStatus, $id]);
            
            logInfo('Estado de usuario cambiado', [
                'user_id' => $id,
                'user_name' => $user['full_name'],
                'new_status' => $newStatus ? 'bloqueado' : 'desbloqueado'
            ]);
            
            jsonResponse(['blocked' => (bool)$newStatus]);
            
        } catch (PDOException $e) {
            logDatabaseError('users/block', $e, ['id' => $id]);
            jsonError('Error al cambiar estado: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'PUT' && $id !== null && $action !== 'block') {
        logInfo('Actualizando usuario', ['user_id' => $id]);
        
        try {
            $fields = [];
            $params = [];
            $allowed = ['full_name','birth_date','grade','doc_type','doc_number','email'];
            
            foreach ($allowed as $f) {
                if (array_key_exists($f, $body)) {
                    $val = $f === 'grade' ? formatGrade($body[$f]) : $body[$f];
                    $fields[] = "$f=?";
                    $params[] = $val;
                    logInfo('Campo actualizado', ['field' => $f, 'value' => $val]);
                }
            }
            
            if (!empty($body['password'])) {
                $fields[] = "password=?";
                $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
                logInfo('Contraseña actualizada');
            }
            
            if (empty($fields)) {
                logWarning('Intento de actualizar usuario sin campos', ['user_id' => $id]);
                jsonError('Sin campos para actualizar');
            }
            
            $params[] = $id;
            $db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,blocked FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            logInfo('Usuario actualizado exitosamente', ['user_id' => $id]);
            jsonResponse($user);
            
        } catch (PDOException $e) {
            logDatabaseError('users/update', $e, ['id' => $id, 'body' => $body]);
            jsonError('Error al actualizar usuario: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'DELETE' && $id !== null) {
        logInfo('Intentando eliminar usuario', ['user_id' => $id]);
        
        try {
            $stmt = $db->prepare("SELECT role, full_name FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                logWarning('Usuario no encontrado para eliminar', ['user_id' => $id]);
                jsonError('Usuario no encontrado', 404);
            }
            
            if ($user['role'] === 'admin') {
                logWarning('Intento de eliminar administrador', ['user_id' => $id, 'user_name' => $user['full_name']]);
                jsonError('No puedes eliminar administradores');
            }
            
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            
            logInfo('Usuario eliminado', [
                'user_id' => $id,
                'user_name' => $user['full_name']
            ]);
            
            jsonResponse(['message' => 'Usuario eliminado']);
            
        } catch (PDOException $e) {
            logDatabaseError('users/delete', $e, ['id' => $id]);
            jsonError('Error al eliminar usuario: ' . $e->getMessage(), 500);
        }
    }

    logWarning('Ruta users no encontrada', ['method' => $method, 'id' => $id, 'action' => $action]);
    jsonError('Ruta users no encontrada', 404);
}
?>