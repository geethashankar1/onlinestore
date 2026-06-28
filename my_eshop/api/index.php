<?php
// api/index.php — front controller. All /api/* requests route here via .htaccess.

require_once __DIR__ . '/lib/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

// derive the route relative to /api  (e.g. /api/products/5 -> ['products','5'])
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pos  = strpos($path, '/api');
if ($pos !== false) $path = substr($path, $pos + 4);
$path = trim($path, '/');
$seg  = $path === '' ? [] : explode('/', $path);

try {

    // ---- GET /api/products  (optional ?search=) ----
    if ($seg === ['products'] && $method === 'GET') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt = $conn->prepare("SELECT id,name,description,price,image,created_at FROM products WHERE name LIKE ? OR description LIKE ? ORDER BY created_at DESC");
            $stmt->bind_param('ss', $like, $like);
        } else {
            $stmt = $conn->prepare("SELECT id,name,description,price,image,created_at FROM products ORDER BY created_at DESC");
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = product_shape($r);
        respond(200, ['products' => $out]);
    }

    // ---- GET /api/products/{id} ----
    elseif (count($seg) === 2 && $seg[0] === 'products' && ctype_digit($seg[1]) && $method === 'GET') {
        $id = (int)$seg[1];
        $stmt = $conn->prepare("SELECT id,name,description,price,image,created_at FROM products WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if (!$r) respond(404, ['error' => 'Product not found']);
        respond(200, ['product' => product_shape($r)]);
    }

    // ---- POST /api/auth/register ----
    elseif ($seg === ['auth', 'register'] && $method === 'POST') {
        $b = body();
        $username = trim($b['username'] ?? '');
        $email    = trim($b['email'] ?? '');
        $password = (string)($b['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') respond(422, ['error' => 'username, email and password are required']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))            respond(422, ['error' => 'Invalid email']);
        $pwFails = [];
        if (strlen($password) < 8)                    $pwFails[] = 'at least 8 characters';
        if (!preg_match('/[A-Z]/', $password))        $pwFails[] = 'at least one uppercase letter';
        if (!preg_match('/[a-z]/', $password))        $pwFails[] = 'at least one lowercase letter';
        if (!preg_match('/[0-9]/', $password))        $pwFails[] = 'at least one number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $pwFails[] = 'at least one special character';
        if (!empty($pwFails)) respond(422, ['error' => 'Password must contain: ' . implode(', ', $pwFails)]);

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) respond(409, ['error' => 'Username or email already taken']);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?,?,?)");
        $stmt->bind_param('sss', $username, $email, $hash);
        $stmt->execute();
        $id = (int)$stmt->insert_id;

        $token = jwt_encode(['sub' => $id, 'username' => $username], JWT_SECRET);
        respond(201, [
            'token' => $token,
            'user'  => ['id' => $id, 'username' => $username, 'email' => $email, 'is_admin' => ($username === 'admin' || $id === 1)],
        ]);
    }

    // ---- POST /api/auth/login ----
    elseif ($seg === ['auth', 'login'] && $method === 'POST') {
        $b   = body();
        $idf = trim($b['email_or_username'] ?? '');
        $password = (string)($b['password'] ?? '');
        if ($idf === '' || $password === '') respond(422, ['error' => 'email_or_username and password are required']);

        $stmt = $conn->prepare("SELECT id,username,email,password FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param('ss', $idf, $idf);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if (!$u || !password_verify($password, $u['password'])) respond(401, ['error' => 'Invalid credentials']);

        $id      = (int)$u['id'];
        $isAdmin = ($u['username'] === 'admin' || $id === 1);
        $token   = jwt_encode(['sub' => $id, 'username' => $u['username']], JWT_SECRET);
        respond(200, [
            'token' => $token,
            'user'  => ['id' => $id, 'username' => $u['username'], 'email' => $u['email'], 'is_admin' => $isAdmin],
        ]);
    }

    // ---- GET /api/me  (auth) ----
    elseif ($seg === ['me'] && $method === 'GET') {
        $auth = require_auth();
        $id   = (int)$auth['sub'];
        $stmt = $conn->prepare("SELECT id,username,email,created_at FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if (!$u) respond(404, ['error' => 'User not found']);
        respond(200, ['user' => [
            'id' => (int)$u['id'], 'username' => $u['username'], 'email' => $u['email'],
            'created_at' => $u['created_at'], 'is_admin' => ($u['username'] === 'admin' || (int)$u['id'] === 1),
        ]]);
    }

    // ---- POST /api/orders  (auth)  body: { shipping_address, items:[{product_id, quantity}] } ----
    elseif ($seg === ['orders'] && $method === 'POST') {
        $auth   = require_auth();
        $userId = (int)$auth['sub'];
        $b        = body();
        $shipping = trim($b['shipping_address'] ?? '');
        $items    = $b['items'] ?? [];
        if ($shipping === '')                              respond(422, ['error' => 'shipping_address is required']);
        if (!is_array($items) || count($items) === 0)      respond(422, ['error' => 'items must be a non-empty array']);

        // Resolve prices server-side — never trust a price sent by the client.
        $resolved  = [];
        $total     = 0.0;
        $priceStmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = (int)($it['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) respond(422, ['error' => 'Each item needs product_id and quantity >= 1']);
            $priceStmt->bind_param('i', $pid);
            $priceStmt->execute();
            $row = $priceStmt->get_result()->fetch_assoc();
            if (!$row) respond(422, ['error' => "Product $pid not found"]);
            $price  = (float)$row['price'];
            $total += $price * $qty;
            $resolved[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price];
        }

        $conn->begin_transaction();
        try {
            $o = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, status) VALUES (?,?,?, 'Pending')");
            $o->bind_param('ids', $userId, $total, $shipping);
            $o->execute();
            $orderId = (int)$o->insert_id;

            $oi = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?,?,?,?)");
            foreach ($resolved as $r) {
                $oi->bind_param('iiid', $orderId, $r['pid'], $r['qty'], $r['price']);
                $oi->execute();
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            respond(500, ['error' => 'Order failed'] + (API_DEBUG ? ['detail' => $e->getMessage()] : []));
        }
        respond(201, ['order_id' => $orderId, 'total_amount' => round($total, 2), 'status' => 'Pending']);
    }

    // ---- GET /api/orders  (auth) — current user's orders ----
    elseif ($seg === ['orders'] && $method === 'GET') {
        $auth   = require_auth();
        $userId = (int)$auth['sub'];
        $stmt = $conn->prepare("SELECT id, total_amount, shipping_address, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $orders = [];
        while ($r = $res->fetch_assoc()) {
            $orders[] = [
                'id' => (int)$r['id'], 'total_amount' => (float)$r['total_amount'],
                'shipping_address' => $r['shipping_address'], 'status' => $r['status'], 'created_at' => $r['created_at'],
            ];
        }
        respond(200, ['orders' => $orders]);
    }

    // ---- GET /api/wishlist  (auth) — current user's wishlist with product info ----
    elseif ($seg === ['wishlist'] && $method === 'GET') {
        $auth   = require_auth();
        $userId = (int)$auth['sub'];
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.price, p.image, w.created_at AS added_at
            FROM wishlist w
            JOIN products p ON p.id = w.product_id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($r = $res->fetch_assoc()) {
            $items[] = [
                'product_id' => (int)$r['id'],
                'name'       => $r['name'],
                'price'      => (float)$r['price'],
                'image'      => $r['image'],
                'added_at'   => $r['added_at'],
            ];
        }
        respond(200, ['wishlist' => $items]);
    }

    // ---- POST /api/wishlist  (auth)  body: { product_id } ----
    elseif ($seg === ['wishlist'] && $method === 'POST') {
        $auth   = require_auth();
        $userId = (int)$auth['sub'];
        $b      = body();
        $pid    = (int)($b['product_id'] ?? 0);
        if ($pid <= 0) respond(422, ['error' => 'product_id is required']);

        // Verify product exists
        $chk = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $chk->bind_param('i', $pid);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) respond(404, ['error' => 'Product not found']);

        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $userId, $pid);
        $stmt->execute();
        $added = $stmt->affected_rows > 0;
        respond($added ? 201 : 200, ['message' => $added ? 'Added to wishlist' : 'Already in wishlist', 'product_id' => $pid]);
    }

    // ---- DELETE /api/wishlist/{product_id}  (auth) ----
    elseif (count($seg) === 2 && $seg[0] === 'wishlist' && ctype_digit($seg[1]) && $method === 'DELETE') {
        $auth   = require_auth();
        $userId = (int)$auth['sub'];
        $pid    = (int)$seg[1];
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $userId, $pid);
        $stmt->execute();
        if ($stmt->affected_rows === 0) respond(404, ['error' => 'Item not in wishlist']);
        respond(200, ['message' => 'Removed from wishlist', 'product_id' => $pid]);
    }

    // ---- fallback ----
    else {
        respond(404, ['error' => 'Route not found', 'method' => $method, 'path' => '/' . $path]);
    }

} catch (Throwable $e) {
    respond(500, ['error' => 'Server error'] + (API_DEBUG ? ['detail' => $e->getMessage()] : []));
}
