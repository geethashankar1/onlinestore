<?php
// api/lib/jwt.php — minimal, dependency-free HS256 JWT (no Composer needed).

function jwt_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function jwt_b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Create a signed JWT. Adds iat + exp automatically.
 */
function jwt_encode(array $payload, string $secret, int $ttlSeconds = 604800): string {
    $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now     = time();
    $payload = array_merge(['iat' => $now, 'exp' => $now + $ttlSeconds], $payload);

    $h   = jwt_b64url_encode(json_encode($header));
    $p   = jwt_b64url_encode(json_encode($payload));
    $sig = jwt_b64url_encode(hash_hmac('sha256', "$h.$p", $secret, true));
    return "$h.$p.$sig";
}

/**
 * Verify + decode a JWT. Returns the payload array, or null if invalid/expired.
 */
function jwt_decode(string $jwt, string $secret): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $sig] = $parts;

    $expected = jwt_b64url_encode(hash_hmac('sha256', "$h.$p", $secret, true));
    if (!hash_equals($expected, $sig)) return null;          // constant-time compare

    $payload = json_decode(jwt_b64url_decode($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && time() >= (int)$payload['exp']) return null; // expired
    return $payload;
}
