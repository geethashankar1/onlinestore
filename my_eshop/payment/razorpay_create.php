<?php
// payment/razorpay_create.php
// AJAX endpoint: validates shipping/billing, then creates a Razorpay order.
// Returns JSON. Called by checkout.php's client-side JS.
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
if (empty($_SESSION['cart'])) {
    echo json_encode(['ok' => false, 'error' => 'Cart is empty']); exit;
}

$b = json_decode(file_get_contents('php://input'), true) ?? [];

$s_street    = trim($b['s_street']    ?? '');
$s_city      = trim($b['s_city']      ?? '');
$s_state     = trim($b['s_state']     ?? '');
$s_pin       = trim($b['s_pin']       ?? '');
$s_phone     = trim($b['s_phone']     ?? '');
$billing_same = !empty($b['billing_same']);

$errs = [];
if ($s_street === '') $errs[] = 'Street address required.';
if ($s_city   === '') $errs[] = 'City required.';
if ($s_state  === '') $errs[] = 'State required.';
if (!preg_match('/^\d{4,10}$/', $s_pin))             $errs[] = 'Valid PIN required.';
if (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $s_phone)) $errs[] = 'Valid phone required.';

if (!$billing_same) {
    $b_street = trim($b['b_street'] ?? '');
    $b_city   = trim($b['b_city']   ?? '');
    $b_state  = trim($b['b_state']  ?? '');
    $b_pin    = trim($b['b_pin']    ?? '');
    $b_phone  = trim($b['b_phone']  ?? '');
    if ($b_street === '') $errs[] = 'Billing street address required.';
    if ($b_city   === '') $errs[] = 'Billing city required.';
    if ($b_state  === '') $errs[] = 'Billing state required.';
    if (!preg_match('/^\d{4,10}$/', $b_pin))             $errs[] = 'Valid billing PIN required.';
    if (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $b_phone)) $errs[] = 'Valid billing phone required.';
}

if (!empty($errs)) {
    echo json_encode(['ok' => false, 'error' => implode(' ', $errs)]); exit;
}

// Compute amount server-side from session cart (never trust the client amount).
$total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $_SESSION['cart']));

// Persist shipping data in session so razorpay_verify.php can use it.
$_SESSION['rzp_pending'] = [
    's_street' => $s_street, 's_city'  => $s_city,
    's_state'  => $s_state,  's_pin'   => $s_pin,   's_phone' => $s_phone,
    'billing_same' => $billing_same,
    'b_street' => $b['b_street'] ?? '', 'b_city' => $b['b_city'] ?? '',
    'b_state'  => $b['b_state']  ?? '', 'b_pin'  => $b['b_pin'] ?? '',
    'b_phone'  => $b['b_phone']  ?? '',
    'total'    => $total,
];

$result = razorpay_create_order($total, 'rcpt_' . $_SESSION['user_id'] . '_' . time());

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['message']]); exit;
}

echo json_encode([
    'ok'               => true,
    'razorpay_order_id'=> $result['razorpay_order_id'],
    'amount'           => $result['amount'],
    'currency'         => RAZORPAY_CURRENCY,
    'key_id'           => RAZORPAY_KEY_ID,
]);
