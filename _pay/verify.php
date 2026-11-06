<?php
// Verifies the Checkout signature, then emails the order to orders@ (and the customer).
require __DIR__ . '/common.php';
$in = read_json();

$oid = (string)($in['razorpay_order_id'] ?? '');
$pid = (string)($in['razorpay_payment_id'] ?? '');
$sig = (string)($in['razorpay_signature'] ?? '');
if (!preg_match('/^order_\w+$/', $oid) || !preg_match('/^pay_\w+$/', $pid)) fail('Bad request');
if (!hash_equals(hash_hmac('sha256', "$oid|$pid", RZP_KEY_SECRET), $sig)) fail('Signature mismatch');

$order = rzp('GET', '/v1/orders/' . $oid);
$n = $order['notes'] ?? [];
$total = number_format($order['amount'] / 100, 0, '.', ',');

$body = "New paid order\n\n"
      . "Payment ID: $pid\nOrder ID: $oid\nAmount: Rs $total\nQuantity: {$n['quantity']}\n\n"
      . "Name: {$n['name']}\nMobile: {$n['phone']}\nEmail: {$n['email']}\n\n"
      . "Address: {$n['address']}\nLandmark: {$n['landmark']}\n"
      . "{$n['city']}, {$n['state']} {$n['pincode']}\n";
$headers = "From: Kauli Dham <" . ORDERS_EMAIL . ">\r\nContent-Type: text/plain; charset=UTF-8";
mail(ORDERS_EMAIL, "New order $oid - Rs $total", $body, $headers);

if (filter_var($n['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    $msg = "Jai Mata Di {$n['name']},\n\n"
         . "Thank you. We received your payment of Rs $total for {$n['quantity']} Kauli Dham Prasad Box.\n"
         . "Payment ID: $pid\n\n"
         . "Your box will be dispatched within 2-3 days. For help, reply to this email or call +91 83603 35885.\n\n"
         . "Maa Kaali Vastu Kendra\nkaulidham.in\n";
    mail($n['email'], 'Your Kauli Dham order is confirmed', $msg, $headers . "\r\nReply-To: " . ORDERS_EMAIL);
}

out(['ok' => true, 'payment_id' => $pid]);
