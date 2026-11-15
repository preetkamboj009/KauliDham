<?php
// Shared helpers for the Razorpay endpoints. Lives on Hostinger at pay.kaulidham.in.
require __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: https://kaulidham.in');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

const PRICE_PAISE = 99900;
const COD_FEE_PAISE = 10000;   // COD: only this booking amount is paid online
const ORDERS_EMAIL = 'orders@kaulidham.in';

function out($data) { echo json_encode($data); exit; }
function fail($msg, $code = 400) { http_response_code($code); out(['error' => $msg]); }

function read_json() {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) fail('Bad request');
    return $in;
}

function clean($v, $max) {
    $v = trim(preg_replace('/\s+/', ' ', (string)$v));
    return mb_substr($v, 0, $max);
}

// Emails the order to orders@ (and the customer). Runs once per payment even if both
// verify.php and webhook.php call it: a marker file outside public_html records it.
function notify_order($oid, $pid) {
    $dir = dirname(__DIR__) . '/sent';
    if (!is_dir($dir)) @mkdir($dir, 0700);
    $mark = @fopen("$dir/$pid", 'x');   // fails if this payment was already emailed
    if ($mark === false) return;
    fclose($mark);

    $order = rzp('GET', '/v1/orders/' . $oid);
    $n = ($order['notes'] ?? []) + ['address' => '', 'landmark' => '', 'city' => '', 'state' => '', 'pincode' => '', 'email' => '', 'phone' => '', 'name' => '', 'quantity' => 1];
    $total = number_format($order['amount'] / 100, 0, '.', ',');
    $cash = (int)($n['cash_on_delivery'] ?? 0);
    $cod = ($n['mode'] ?? '') === 'COD';

    // One-tap WhatsApp: opens a chat with the customer, message pre-filled; owner presses Send.
    $ref = strtoupper(substr($pid, -6));   // short order number = last 6 of payment ID
    $addr = implode(', ', array_filter([$n['address'], $n['landmark'], $n['city'], $n['state']])) . " - {$n['pincode']}";
    $wa = "*JAI MATA DI* 🙏\n\n"
        . "Aap Ka Order Mil Gaya Hai ✅\n\n"
        . "*Order:* #$ref ({$n['quantity']} Kauli Dham Prasad Box)\n"
        . ($cod ? "*Payment:* ₹$total booking paid, ₹$cash cash on delivery\n"
                : "*Payment:* ₹$total paid\n")
        . "*Name:* {$n['name']}\n"
        . "*Mobile:* {$n['phone']}\n"
        . "*Address:* $addr";
    $waLink = 'https://wa.me/91' . $n['phone'] . '?text=' . rawurlencode($wa);

    $body = ($cod ? "New COD order\n\nCASH ON DELIVERY: collect Rs $cash in cash\n\n" : "New paid order (prepaid)\n\n")
          . "WhatsApp the customer (tap, then press Send):\n$waLink\n\n"
          . "Order #$ref\nPayment ID: $pid\nOrder ID: $oid\nPaid online: Rs $total\nQuantity: {$n['quantity']}\n\n"
          . "Name: {$n['name']}\nMobile: {$n['phone']}\nEmail: {$n['email']}\n\n"
          . "Address: {$n['address']}\nLandmark: {$n['landmark']}\n"
          . "{$n['city']}, {$n['state']} {$n['pincode']}\n";
    $headers = "From: Kauli Dham <" . ORDERS_EMAIL . ">\r\nContent-Type: text/plain; charset=UTF-8";
    mail(ORDERS_EMAIL, mb_encode_mimeheader(($cod ? "New COD order" : "New paid order") . " #$ref - {$n['name']}", 'UTF-8'), $body, $headers);

    if (filter_var($n['email'], FILTER_VALIDATE_EMAIL)) {
        $msg = "Jai Mata Di {$n['name']},\n\n"
             . ($cod
                 ? "Thank you. We received your booking amount of Rs $total for {$n['quantity']} Kauli Dham Prasad Box. Please keep Rs $cash in cash ready when the box arrives.\n"
                 : "Thank you. We received your payment of Rs $total for {$n['quantity']} Kauli Dham Prasad Box.\n")
             . "Payment ID: $pid\n\n"
             . "Your box will be dispatched within 2-3 days. For help, reply to this email or call +91 83603 35885.\n\n"
             . "Maa Kaali Vastu Kendra\nkaulidham.in\n";
        mail($n['email'], 'Your Kauli Dham order is confirmed', $msg, $headers . "\r\nReply-To: " . ORDERS_EMAIL);
    }
}

function rzp($method, $path, $body = null) {
    $ch = curl_init('https://api.razorpay.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !is_array($res)) {
        error_log('Razorpay ' . $path . ' failed: ' . $code . ' ' . json_encode($res));
        fail('Payment service error', 502);
    }
    return $res;
}
