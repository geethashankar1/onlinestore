<?php
// logout.php
require_once 'config/db.php'; // Ensures session_start() is called

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect: back to seller storefront if we came from one, otherwise login
$store = isset($_GET['store']) && preg_match('/^[a-z0-9_-]+$/', $_GET['store'])
         ? $_GET['store'] : '';
header('Location: ' . ($store ? '/shop/' . $store : 'login.php'));
exit;
?>