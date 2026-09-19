<?php
// config/cart.php
//
// One cart per account, shared by the website and the mobile app.
//
// Two layers live here:
//
//   cart_db_*  — pure functions over the cart_items table. They take a user id
//                explicitly and touch no session, so the JSON API (which
//                authenticates with a JWT and has no session) uses them too.
//
//   cart_*     — the website's view: signed-in visitors read and write the
//                database through the layer above; guests fall back to
//                $_SESSION['cart'], since there is no account to hang rows on.
//                cart_adopt_session() folds that guest cart in at login.
//
// Every function returns and accepts the same item shape the site has always
// used, so the existing views and create_db_order() need no changes:
//
//   [ product_id => ['id','name','price','image','quantity','item_id'] ]
//
// 'item_id' is the cart_items row id and is null for a guest cart. The mobile
// app addresses lines by it.

// ───────────────────────────────────────────────────────────────────────────
// Database layer
// ───────────────────────────────────────────────────────────────────────────

/** Every line in a user's cart, keyed by product id, joined to live product data. */
function cart_db_items(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare(
        "SELECT c.id AS item_id, c.product_id, c.quantity,
                p.name, p.price, p.image
           FROM cart_items c
           JOIN products p ON p.id = c.product_id
          WHERE c.user_id = ?
          ORDER BY c.created_at, c.id"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $pid = (int)$r['product_id'];
        $out[$pid] = [
            'id'       => $pid,
            'item_id'  => (int)$r['item_id'],
            'name'     => $r['name'],
            'price'    => (float)$r['price'],
            'image'    => $r['image'],
            'quantity' => (int)$r['quantity'],
        ];
    }
    $stmt->close();
    return $out;
}

/**
 * Add to a line, creating it if absent. Returns the cart_items row id, or 0 if
 * the product does not exist — a cart must never reference a missing product.
 */
function cart_db_add(mysqli $conn, int $userId, int $productId, int $qty = 1): int {
    $qty = max(1, $qty);

    $check = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $check->bind_param('i', $productId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) return 0;

    // The unique key on (user_id, product_id) turns a concurrent double-add
    // into one row with the quantities summed, instead of a duplicate-key error.
    $stmt = $conn->prepare(
        "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
    );
    $stmt->bind_param('iii', $userId, $productId, $qty);
    $stmt->execute();
    $stmt->close();

    return cart_db_item_id($conn, $userId, $productId);
}

/** The row id of one user's line for a product, or 0 if they have none. */
function cart_db_item_id(mysqli $conn, int $userId, int $productId): int {
    $stmt = $conn->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}

/** Set an absolute quantity by product. Zero or less removes the line. */
function cart_db_set_qty(mysqli $conn, int $userId, int $productId, int $qty): void {
    if ($qty <= 0) { cart_db_remove($conn, $userId, $productId); return; }
    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param('iii', $qty, $userId, $productId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Set an absolute quantity by cart row id — what the mobile app holds.
 * The user_id in the WHERE is the authorisation check: it stops one account
 * editing another's cart line by guessing an id.
 */
function cart_db_set_qty_by_item(mysqli $conn, int $userId, int $itemId, int $qty): bool {
    if ($qty <= 0) return cart_db_remove_item($conn, $userId, $itemId);
    // Checked first: an UPDATE that sets the quantity it already had reports
    // zero affected rows, which is indistinguishable from "no such line".
    if (!cart_db_item_exists($conn, $userId, $itemId)) return false;

    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('iii', $qty, $itemId, $userId);
    $stmt->execute();
    $stmt->close();
    return true;
}

function cart_db_item_exists(mysqli $conn, int $userId, int $itemId): bool {
    $stmt = $conn->prepare("SELECT id FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $itemId, $userId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

function cart_db_remove(mysqli $conn, int $userId, int $productId): void {
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $stmt->close();
}

function cart_db_remove_item(mysqli $conn, int $userId, int $itemId): bool {
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $itemId, $userId);
    $stmt->execute();
    $removed = $stmt->affected_rows > 0;
    $stmt->close();
    return $removed;
}

function cart_db_clear(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

// ───────────────────────────────────────────────────────────────────────────
// Website layer — database when signed in, session when not
// ───────────────────────────────────────────────────────────────────────────

/** The signed-in user's id, or null for a guest. */
function cart_user_id(): ?int {
    $id = $_SESSION['user_id'] ?? null;
    return $id ? (int)$id : null;
}

function cart_items(mysqli $conn): array {
    $uid = cart_user_id();
    if ($uid !== null) return cart_db_items($conn, $uid);
    return $_SESSION['cart'] ?? [];
}

function cart_add(mysqli $conn, int $productId, int $qty = 1): void {
    $uid = cart_user_id();
    if ($uid !== null) { cart_db_add($conn, $uid, $productId, $qty); return; }

    // Guest: look the product up so the session line can render without a
    // second query on every page.
    $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$p) return;

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += max(1, $qty);
    } else {
        $_SESSION['cart'][$productId] = [
            'id'       => $productId,
            'item_id'  => null,
            'name'     => $p['name'],
            'price'    => (float)$p['price'],
            'image'    => $p['image'],
            'quantity' => max(1, $qty),
        ];
    }
}

function cart_set_qty(mysqli $conn, int $productId, int $qty): void {
    $uid = cart_user_id();
    if ($uid !== null) { cart_db_set_qty($conn, $uid, $productId, $qty); return; }

    if ($qty <= 0) { unset($_SESSION['cart'][$productId]); return; }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $qty;
    }
}

function cart_remove(mysqli $conn, int $productId): void {
    $uid = cart_user_id();
    if ($uid !== null) { cart_db_remove($conn, $uid, $productId); return; }
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(mysqli $conn): void {
    $uid = cart_user_id();
    if ($uid !== null) { cart_db_clear($conn, $uid); return; }
    $_SESSION['cart'] = [];
}

/**
 * Total number of models in the cart — the header badge.
 *
 * It sums quantities rather than counting lines: two of one model is two items
 * in the cart, and a badge that reads 1 while the cart says 2 looks broken.
 */
function cart_count(mysqli $conn): int {
    $uid = cart_user_id();
    if ($uid === null) {
        return array_sum(array_map(fn($i) => (int)$i['quantity'], $_SESSION['cart'] ?? []));
    }
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS n FROM cart_items WHERE user_id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['n'] ?? 0);
}

/**
 * Fold whatever a guest collected into their account's cart, then empty the
 * session copy. Called right after a successful login or registration, so the
 * models someone added before signing in are not silently thrown away.
 *
 * Quantities add rather than overwrite: a phone cart holding two and a browser
 * cart holding one become three, which is the reading that never loses an item.
 */
function cart_adopt_session(mysqli $conn, int $userId): void {
    $guest = $_SESSION['cart'] ?? [];
    $_SESSION['cart'] = [];
    foreach ($guest as $item) {
        $pid = (int)($item['id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($pid > 0 && $qty > 0) cart_db_add($conn, $userId, $pid, $qty);
    }
}
