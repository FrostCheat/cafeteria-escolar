<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Incluir logger
require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'auth') {
    if ($method === 'POST' && $action === 'register') {
        logInfo('Intento de registro', ['email' => $body['email'] ?? 'no email']);
        
        $required = ['full_name','birth_date','grade','doc_type','doc_number','email','password'];
        foreach ($required as $f) {
            if (empty($body[$f])) {
                logWarning('Registro fallido - campo requerido faltante', ['field' => $f]);
                jsonError("El campo $f es requerido");
            }
        }

        $fullName = trim($body['full_name']);
        $words = array_filter(explode(' ', $fullName));
        if (count($words) < 3) {
            logWarning('Registro fallido - nombre incompleto', ['full_name' => $fullName]);
            jsonError('El nombre completo debe tener mínimo 3 palabras (nombre y apellidos). Ejemplo: Juan Carlos Pérez García');
        }

        $grade = formatGrade($body['grade']);
        if (!preg_match('/^\d{1,2}[A-Z]$/', $grade)) {
            logWarning('Registro fallido - grado inválido', ['grade' => $body['grade']]);
            jsonError('Grado inválido. Ejemplo: 11B, 10A, 6D');
        }

        $validDocs = ['TI', 'CC', 'CE', 'PA'];
        if (!in_array($body['doc_type'], $validDocs)) {
            logWarning('Registro fallido - tipo documento inválido', ['doc_type' => $body['doc_type']]);
            jsonError('Tipo de documento inválido');
        }

        $email = strtolower(trim($body['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            logWarning('Registro fallido - email inválido', ['email' => $email]);
            jsonError('Email inválido');
        }
        if (!str_ends_with($email, '@santajuanalestonnac.edu.co')) {
            logWarning('Registro fallido - dominio no permitido', ['email' => $email]);
            jsonError('Solo se permiten correos institucionales @santajuanalestonnac.edu.co');
        }

        if (strlen($body['password']) < 6) {
            logWarning('Registro fallido - contraseña corta', ['email' => $email]);
            jsonError('La contraseña debe tener mínimo 6 caracteres');
        }

        try {
            $existing = $db->prepare("SELECT id FROM users WHERE email=? OR doc_number=?");
            $existing->execute([$email, trim($body['doc_number'])]);
            if ($existing->fetch()) {
                logWarning('Registro fallido - usuario ya existe', ['email' => $email, 'doc' => $body['doc_number']]);
                jsonError('Email o documento ya registrado');
            }

            $hash = password_hash($body['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO users (full_name, birth_date, grade, doc_type, doc_number, email, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fullName,
                $body['birth_date'],
                $grade,
                $body['doc_type'],
                trim($body['doc_number']),
                $email,
                $hash
            ]);

            $userId = (int)$db->lastInsertId();
            $stmt   = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,role FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user  = $stmt->fetch();
            $token = jwtEncode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'name' => $user['full_name']]);
            
            logInfo('Registro exitoso', ['user_id' => $userId, 'email' => $email]);
            jsonResponse(['token' => $token, 'user' => $user], 201);
            
        } catch (PDOException $e) {
            logDatabaseError('auth/register', $e, $stmt->queryString ?? '');
            jsonError('Error al registrar usuario: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'POST' && $action === 'login') {
        logInfo('Intento de login', ['email' => $body['email'] ?? 'no email']);
        
        if (empty($body['email']) || empty($body['password'])) {
            logWarning('Login fallido - campos vacíos');
            jsonError('Email y contraseña requeridos');
        }

        $email = strtolower(trim($body['email']));

        if (!str_ends_with($email, '@santajuanalestonnac.edu.co')) {
            logWarning('Login fallido - dominio no permitido', ['email' => $email]);
            jsonError('Solo se permiten correos institucionales @santajuanalestonnac.edu.co');
        }

        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email=?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($body['password'], $user['password'])) {
                logWarning('Login fallido - credenciales inválidas', ['email' => $email]);
                jsonError('Credenciales inválidas');
            }
            if ($user['blocked']) {
                logWarning('Login fallido - usuario bloqueado', ['user_id' => $user['id'], 'email' => $email]);
                jsonError('Cuenta bloqueada. Contacta al administrador.');
            }

            $token = jwtEncode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'name' => $user['full_name']]);
            unset($user['password']);
            
            logInfo('Login exitoso', ['user_id' => $user['id'], 'email' => $email]);
            jsonResponse(['token' => $token, 'user' => $user]);
            
        } catch (PDOException $e) {
            logDatabaseError('auth/login', $e);
            jsonError('Error al iniciar sesión: ' . $e->getMessage(), 500);
        }
    }

    if ($method === 'GET' && $action === 'me') {
        logInfo('Obteniendo información del usuario autenticado');
        
        try {
            $auth = requireAuth();
            $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,role,blocked,created_at FROM users WHERE id=?");
            $stmt->execute([$auth['id']]);
            $user = $stmt->fetch();
            if (!$user) {
                logWarning('Usuario autenticado no encontrado', ['user_id' => $auth['id']]);
                jsonError('Usuario no encontrado', 404);
            }
            logInfo('Información de usuario obtenida', ['user_id' => $user['id']]);
            jsonResponse($user);
            
        } catch (Exception $e) {
            logError('Error en auth/me', ['message' => $e->getMessage()]);
            jsonError('Error al obtener información del usuario', 500);
        }
    }

    logWarning('Ruta auth no encontrada', ['method' => $method, 'action' => $action]);
    jsonError('Ruta auth no encontrada', 404);
}
?>