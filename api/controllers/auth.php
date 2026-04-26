<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '../logs/error.log');

$db = getDB();

if ($resource === 'auth') {
    if ($method === 'POST' && $action === 'register') {
        $required = ['full_name','birth_date','grade','doc_type','doc_number','email','password'];
        foreach ($required as $f) {
            if (empty($body[$f])) jsonError("El campo $f es requerido");
        }

        $fullName = trim($body['full_name']);
        $words = array_filter(explode(' ', $fullName));
        if (count($words) < 3) {
            jsonError('El nombre completo debe tener mínimo 3 palabras (nombre y apellidos). Ejemplo: Juan Carlos Pérez García');
        }

        $grade = formatGrade($body['grade']);
        if (!preg_match('/^\d{1,2}[A-Z]$/', $grade)) {
            jsonError('Grado inválido. Ejemplo: 11B, 10A, 6D');
        }

        $validDocs = ['TI', 'CC', 'CE', 'PA'];
        if (!in_array($body['doc_type'], $validDocs)) {
            jsonError('Tipo de documento inválido');
        }

        $email = strtolower(trim($body['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Email inválido');
        }
        if (!str_ends_with($email, '@santajuanalestonnac.edu.co')) {
            jsonError('Solo se permiten correos institucionales @santajuanalestonnac.edu.co');
        }

        if (strlen($body['password']) < 6) {
            jsonError('La contraseña debe tener mínimo 6 caracteres');
        }

        $existing = $db->prepare("SELECT id FROM users WHERE email=? OR doc_number=?");
        $existing->execute([$email, trim($body['doc_number'])]);
        if ($existing->fetch()) jsonError('Email o documento ya registrado');

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
        jsonResponse(['token' => $token, 'user' => $user], 201);
    }

    if ($method === 'POST' && $action === 'login') {
        if (empty($body['email']) || empty($body['password'])) {
            jsonError('Email y contraseña requeridos');
        }

        $email = strtolower(trim($body['email']));

        if (!str_ends_with($email, '@santajuanalestonnac.edu.co')) {
            jsonError('Solo se permiten correos institucionales @santajuanalestonnac.edu.co');
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($body['password'], $user['password'])) {
            jsonError('Credenciales inválidas');
        }
        if ($user['blocked']) jsonError('Cuenta bloqueada. Contacta al administrador.');

        $token = jwtEncode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'name' => $user['full_name']]);
        unset($user['password']);
        jsonResponse(['token' => $token, 'user' => $user]);
    }

    if ($method === 'GET' && $action === 'me') {
        $auth = requireAuth();
        $stmt = $db->prepare("SELECT id,full_name,email,grade,doc_type,doc_number,birth_date,role,blocked,created_at FROM users WHERE id=?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch();
        if (!$user) jsonError('Usuario no encontrado', 404);
        jsonResponse($user);
    }

    jsonError('Ruta auth no encontrada', 404);
}