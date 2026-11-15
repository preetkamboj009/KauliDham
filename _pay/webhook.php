<?php
// Razorpay webhook (event: payment.captured). Catches payments where the customer
// paid in a UPI app and never returned to the site, so verify.php never ran.
// ponytail: no webhook secret needed — the body is not trusted; the payment is
// re-fetched from Razorpay with our API key and must be captured.
require __DIR__ . '/common.php';
$in = read_json();

$pid = (string)($in['payload']['payment']['entity']['id'] ?? '');
if (!preg_match('/^pay_\w+$/', $pid)) out(['ok' => true]);   // other events: ignore

$p = rzp('GET', '/v1/payments/' . $pid);
if (($p['status'] ?? '') !== 'captured' || !preg_match('/^order_\w+$/', $p['order_id'] ?? '')) out(['ok' => true]);

notify_order($p['order_id'], $pid);
out(['ok' => true]);
