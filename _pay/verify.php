<?php
// Verifies the Checkout signature, then emails the order (see notify_order in common.php).
// webhook.php sends the same emails if the customer never comes back to the site after paying.
require __DIR__ . '/common.php';
$in = read_json();

$oid = (string)($in['razorpay_order_id'] ?? '');
$pid = (string)($in['razorpay_payment_id'] ?? '');
$sig = (string)($in['razorpay_signature'] ?? '');
if (!preg_match('/^order_\w+$/', $oid) || !preg_match('/^pay_\w+$/', $pid)) fail('Bad request');
if (!hash_equals(hash_hmac('sha256', "$oid|$pid", RZP_KEY_SECRET), $sig)) fail('Signature mismatch');

notify_order($oid, $pid);
out(['ok' => true, 'payment_id' => $pid]);
