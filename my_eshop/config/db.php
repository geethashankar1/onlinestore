<?php
// config/db.php
// Connection settings are read from environment variables (set in docker-compose.yml
// locally, or in the Render dashboard in production).
// Fallback defaults keep the file working outside Docker, but no real secrets live here.

$servername = getenv('DB_HOST') ?: 'db';            // Docker service name, NOT localhost
$username   = getenv('DB_USER') ?: 'eshop';
$password   = getenv('DB_PASS') ?: 'eshop_pass';
$dbname     = getenv('DB_NAME') ?: 'ecommerce_db';

// Managed MySQL providers (Aiven, PlanetScale, TiDB…) listen on a non-default
// port and require TLS, so both are configurable. Local Docker sets neither.
$dbport = (int)(getenv('DB_PORT') ?: 3306);
$sslCa  = (string)(getenv('DB_SSL_CA') ?: '');
$useSsl = $sslCa !== '' || filter_var((string)(getenv('DB_SSL') ?: 'false'), FILTER_VALIDATE_BOOLEAN);

$conn = mysqli_init();
if (!$conn) {
    die('Database connection failed.');
}

$flags = 0;
if ($useSsl) {
    if ($sslCa !== '' && is_file($sslCa)) {
        // Preferred: verify the provider's certificate against their CA bundle.
        $conn->ssl_set(null, null, $sslCa, null, null);
        $flags = MYSQLI_CLIENT_SSL;
    } else {
        // Fallback: encrypted but unverified. Set DB_SSL_CA to the provider's
        // CA file to get verification — without it this is open to an active
        // man-in-the-middle, though still better than plaintext.
        $conn->ssl_set(null, null, null, null, null);
        $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    }
}

// mysqli throws mysqli_sql_exception on failure since PHP 8.1 — it does not
// return false — so this must be caught, not tested. Without the try/catch an
// unreachable database prints a stack trace (host, user, paths) to visitors.
try {
    $connected = $conn->real_connect($servername, $username, $password, $dbname, $dbport, null, $flags);
} catch (mysqli_sql_exception $e) {
    $connected = false;
    $connect_error = $e->getMessage();
}

if (!$connected) {
    $detail = $connect_error ?? (mysqli_connect_error() ?: 'unknown error');
    // Always log the detail; only show it when debugging, so a misconfigured
    // production box does not print credentials-adjacent errors to visitors.
    error_log('DB connection failed: ' . $detail);

    if (filter_var((string)(getenv('APP_DEBUG') ?: 'false'), FILTER_VALIDATE_BOOLEAN)) {
        die('Connection failed: ' . htmlspecialchars($detail));
    }

    http_response_code(503);
    header('Retry-After: 300');
    die('<!doctype html><meta charset="utf-8"><title>Temporarily unavailable</title>'
      . '<div style="font:15px/1.6 system-ui,sans-serif;max-width:32rem;margin:12vh auto;padding:0 1.5rem;color:#F4F2ED;background:#0A0B0D">'
      . '<h1 style="font-size:1.5rem;margin:0 0 .5rem">Temporarily unavailable</h1>'
      . '<p style="color:rgba(244,242,237,.64);margin:0">We can&rsquo;t reach the database right now. '
      . 'Please try again in a few minutes.</p></div>');
}

// Set character set (good practice)
$conn->set_charset("utf8mb4");

// Image storage helpers (Cloudinary in production, local uploads/ in dev).
require_once __DIR__ . '/media.php';

// Cart helpers — database-backed for signed-in visitors, session for guests.
require_once __DIR__ . '/cart.php';

// Start session if not already started - useful for cart, login status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
