<?php
// config/store.php — resolves which store is currently active.
// Include AFTER config/db.php. Sets $active_store (array|null).

function get_store_by_slug(mysqli $conn, string $slug): ?array {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE slug = ? AND status = 'active'");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function get_store_by_seller(mysqli $conn, int $seller_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE seller_id = ?");
    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function get_store_by_id(mysqli $conn, int $store_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Resolve active store: slug param > seller session > null
$_slug = $_GET['slug'] ?? null;
if ($_slug) {
    $active_store = get_store_by_slug($conn, $_slug);
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'seller' && isset($_SESSION['user_id'])) {
    $active_store = get_store_by_seller($conn, (int)$_SESSION['user_id']);
} else {
    $active_store = null;
}
