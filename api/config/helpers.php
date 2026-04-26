<?php
function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['error' => $message], $code);
}

function formatGrade(string $input): string {
    $clean = strtoupper(preg_replace('/[\s\-_]+/', '', trim($input)));
    if (preg_match('/^(\d+)([A-Z])$/', $clean, $m)) {
        return $m[1] . $m[2];
    }
    return $clean;
}

function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function generateQRData(array $data): string {
    return base64url_encode(json_encode($data));
}