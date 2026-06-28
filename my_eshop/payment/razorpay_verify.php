<?php
// payment/razorpay_verify.php
// AJAX endpoint: verifies Razorpay signature, creates the DB order, clears cart.
// Returns JSON. Called by checkout.php's client-side JS after payment succeeds.
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit;
}

$b         = json_decode(file_get_contents('php://input'), true) ?? [];
$rzpOrder  = trim($b['razorpay_order_id']   ?? '');
$rzpPay    = trim($b['razorpay_payment_id'] ?? '');
$rzpSig    = trim($b['razorpay_signature']  ?? '');

if (!$rzpOrder || !$rzpPay || !$rzpSig) {
    echo json_encode(['ok' => false, 'error' => 'Missing payment data.']); exit;
}

// 1. Verify signature — ensures payment came from Razorpay and was not tampered.
if (!razorpay_verify_signature($rzpOrder, $rzpPay, $rzpSig)) {
    echo json_encode(['ok' => false, 'error' => 'Payment signature verification failed.']); exit;
}

// 2. Retrieve session data set by razorpay_create.php.
$pending = $_SESSION['rzp_pending'] ?? null;
$cart    = $_SESSION['cart']        ?? [];

if (!$pending || empty($cart)) {
    echo json_encode(['ok' => false, 'error' => 'Session expired. Please restart checkout.']); exit;
}

$total   = (float)$pending['total'];
$address = "{$pending['s_street']}\n{$pending['s_city']}, {$pending['s_state']} – {$pending['s_pin']}\nPhone: {$pending['s_phone']}";

// 3. Create order in DB (status = Paid because we verified the payment).
[$ok, $orderId] = create_db_order($conn, (int)$_SESSION['user_id'], $total, $address, 'Paid', $cart);

if (!$ok) {
    // Payment was received but DB write failed — store payment ref for manual follow-up.
    error_log("Razorpay DB fail: rzp_payment_id={$rzpPay} rzp_order_id={$rzpOrder} user={$_SESSION['user_id']}");
    echo json_encode([
        'ok'    => false,
        'error' => "Payment received but order creation failed. Please contact support with payment ID: {$rzpPay}",
    ]);
    exit;
}

// 4. Clean up session.
$_SESSION['cart']             = [];
$_SESSION['last_order_id']    = $orderId;
unset($_SESSION['rzp_pending']);

echo json_encode(['ok' => true, 'order_id' => $orderId]);
