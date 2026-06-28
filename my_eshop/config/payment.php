<?php
// config/payment.php — payment gateway configuration + shared helpers.
// Included by checkout.php and payment/ endpoints.

// ── Authorize.Net ─────────────────────────────────────────────────────────
define('AUTHNET_LOGIN_ID',   getenv('AUTHNET_LOGIN_ID')   ?: '');
define('AUTHNET_TRANS_KEY',  getenv('AUTHNET_TRANS_KEY')  ?: '');
define('AUTHNET_CLIENT_KEY', getenv('AUTHNET_CLIENT_KEY') ?: '');
define('AUTHNET_SANDBOX',    (getenv('AUTHNET_SANDBOX') ?: 'true') !== 'false');
define('AUTHNET_API_URL',    AUTHNET_SANDBOX
    ? 'https://apitest.authorize.net/xml/v1/request.api'
    : 'https://api.authorize.net/xml/v1/request.api');
define('AUTHNET_JS_URL',     AUTHNET_SANDBOX
    ? 'https://jstest.authorize.net/v1/Accept.js'
    : 'https://js.authorize.net/v1/Accept.js');

// ── Razorpay ──────────────────────────────────────────────────────────────
define('RAZORPAY_KEY_ID',     getenv('RAZORPAY_KEY_ID')     ?: '');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
define('RAZORPAY_CURRENCY',   getenv('RAZORPAY_CURRENCY')   ?: 'INR');
define('RAZORPAY_API',        'https://api.razorpay.com/v1');

// ─────────────────────────────────────────────────────────────────────────
// Authorize.Net: charge using a client-side token (opaqueData).
// Card details are tokenised by Accept.js in the browser — they never
// reach our server, keeping us out of PCI scope.
// ─────────────────────────────────────────────────────────────────────────
function authnet_charge(float $amount, string $descriptor, string $value): array {
    if (!AUTHNET_LOGIN_ID || !AUTHNET_TRANS_KEY) {
        return ['ok' => false, 'message' => 'Card payment gateway not configured.'];
    }

    $payload = json_encode([
        'createTransactionRequest' => [
            'merchantAuthentication' => [
                'name'           => AUTHNET_LOGIN_ID,
                'transactionKey' => AUTHNET_TRANS_KEY,
            ],
            'transactionRequest' => [
                'transactionType' => 'authCaptureTransaction',
                'amount'          => number_format($amount, 2, '.', ''),
                'payment'         => [
                    'opaqueData' => [
                        'dataDescriptor' => $descriptor,
                        'dataValue'      => $value,
                    ],
                ],
            ],
        ],
    ]);

    $ch = curl_init(AUTHNET_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => !AUTHNET_SANDBOX,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    if (!$raw) return ['ok' => false, 'message' => 'Payment gateway timeout. Please try again.'];

    // Authorize.Net prepends a UTF-8 BOM — strip it before decoding.
    $data = json_decode(ltrim($raw, "\xEF\xBB\xBF"), true);
    $txn  = $data['transactionResponse'] ?? [];
    $code = $txn['responseCode'] ?? '';

    if ($code === '1') {
        return ['ok' => true, 'transaction_id' => $txn['transId'] ?? ''];
    }

    $msg = $txn['errors'][0]['errorText']
        ?? $data['messages']['message'][0]['text']
        ?? 'Card payment declined.';
    return ['ok' => false, 'message' => $msg];
}

// ─────────────────────────────────────────────────────────────────────────
// Razorpay: create an order on their servers (amount in smallest currency unit).
// ─────────────────────────────────────────────────────────────────────────
function razorpay_create_order(float $amount, string $receipt = ''): array {
    if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
        return ['ok' => false, 'message' => 'Razorpay not configured.'];
    }

    $body = json_encode([
        'amount'   => (int)round($amount * 100),
        'currency' => RAZORPAY_CURRENCY,
        'receipt'  => $receipt ?: 'rcpt_' . time(),
    ]);

    $ch = curl_init(RAZORPAY_API . '/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$raw || $status !== 200) {
        $err = (json_decode($raw, true)['error']['description'] ?? 'Could not initiate Razorpay payment.');
        return ['ok' => false, 'message' => $err];
    }

    $data = json_decode($raw, true);
    return ['ok' => true, 'razorpay_order_id' => $data['id'], 'amount' => $data['amount']];
}

// ─────────────────────────────────────────────────────────────────────────
// Razorpay: verify the payment signature returned after payment.
// ─────────────────────────────────────────────────────────────────────────
function razorpay_verify_signature(string $orderId, string $paymentId, string $sig): bool {
    if (!RAZORPAY_KEY_SECRET) return false;
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $sig);
}

// ─────────────────────────────────────────────────────────────────────────
// Shared: insert order + items in a transaction. Returns [bool $ok, int $id].
// ─────────────────────────────────────────────────────────────────────────
function create_db_order(mysqli $conn, int $userId, float $total, string $address, string $status, array $cart): array {
    try {
        $conn->begin_transaction();

        $s = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, status) VALUES (?, ?, ?, ?)");
        $s->bind_param('idss', $userId, $total, $address, $status);
        $s->execute();
        $orderId = (int)$s->insert_id;
        $s->close();

        $si = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
        foreach ($cart as $item) {
            $si->bind_param('iiid', $orderId, $item['id'], $item['quantity'], $item['price']);
            $si->execute();
        }
        $si->close();
        $conn->commit();
        return [true, $orderId];
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, 0];
    }
}
