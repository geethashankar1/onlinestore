<?php
// api/lib/bootstrap.php — shared setup for every API request.
// JSON headers, CORS, a stateless mysqli connection (no session), and helpers.

require_once __DIR__ . '/jwt.php';

// ---- headers / CORS (dev: open. Lock the origin down before production) ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- config (same env vars the web app uses; set in docker-compose.yml) ----
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'dev-only-change-me-eshop-secret');
define('API_DEBUG', getenv('API_DEBUG') === '1');

$DB_HOST = getenv('DB_HOST') ?: 'db';
$DB_USER = getenv('DB_USER') ?: 'eshop';
$DB_PASS = getenv('DB_PASS') ?: 'eshop_pass';
$DB_NAME = getenv('DB_NAME') ?: 'ecommerce_db';

// ---- response helpers ----
function respond(int $status, $data): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
function body(): array {
    $data = json_decode((string)file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function product_shape(array $r): array {
    $img = (string)($r['image'] ?? '');
    $file = $img !== '' ? rawurlencode($img) : 'default_placeholder.png';
    return [
        'id'          => (int)$r['id'],
        'name'        => $r['name'],
        'description' => $r['description'],
        'price'       => (float)$r['price'],
        'image'       => $img,
        'image_url'   => base_url() . '/uploads/' . $file,
        'created_at'  => $r['created_at'] ?? null,
    ];
}

// ---- auth ----
function bearer_token(): ?string {
    $hdr = null;
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))          $hdr = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strtolower($k) === 'authorization') { $hdr = $v; break; }
        }
    }
    if (!$hdr || stripos($hdr, 'Bearer ') !== 0) return null;
    return trim(substr($hdr, 7));
}
function require_auth(): array {
    $token = bearer_token();
    if (!$token) respond(401, ['error' => 'Missing or malformed Authorization header']);
    $payload = jwt_decode($token, JWT_SECRET);
    if (!$payload || !isset($payload['sub'])) respond(401, ['error' => 'Invalid or expired token']);
    return $payload;
}

// ---- DB connection (stateless: no session_start here) ----
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    respond(500, ['error' => 'Database connection failed'] + (API_DEBUG ? ['detail' => $e->getMessage()] : []));
}
