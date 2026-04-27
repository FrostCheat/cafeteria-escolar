<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function jwtEncode(array $payload): string {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 60 * 60 * 24 * 7;
    $body = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || $payload['exp'] < time()) return null;
    return $payload;
}

function getBearerToken(): ?string {
    $authHeader = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    
    foreach ($_SERVER as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                return $matches[1];
            }
        }
    }
    
    return null;
}

function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    return jwtDecode($token);
}

function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }
    return $user;
}
?>