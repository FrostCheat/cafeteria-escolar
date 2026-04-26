<?php
$db = getDB();

if ($resource === 'auth') {
    if ($method === 'POST' && $action === 'register') {
        $required = ['full_name','birth_date','grade','doc_type','doc_number','email','password'];
        foreach ($required as $f) {
            if (empty($body[$f])) jsonError("El campo $f es requerido");
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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Email inválido');

        $existing = $db->prepare("SELECT id FROM users WHERE email=? OR doc_number=?");
        $existing->execute([$email, $body['doc_number']]);
        if ($existing->fetch()) jsonError('Email o documento ya registrado');

        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO users (full_name, birth_date, grade, doc_type, doc_number, email, password)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($body['full_name']),
            $body['birth_date'],
            $grade,
            $body['doc_type'],
            trim($body['doc_number']),
            $email,
            $hash
        ]);

        $userId = (int)$db->lastInsertId();
        $user = $db->query("SELECT id,full_name,email,grade,doc_type,doc_number,role FROM users WHERE id=$userId")->fetch();
        $token = jwtEncode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'name' => $user['full_name']]);
        jsonResponse(['token' => $token, 'user' => $user], 201);
    }

    if ($method === 'POST' && $action === 'login') {
        if (empty($body['email']) || empty($body['password'])) jsonError('Email y contraseña requeridos');

        $email = strtolower(trim($body['email']));
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