<?php
// Creates a Razorpay order. Amount is computed here, never trusted from the browser.
require __DIR__ . '/common.php';
$in = read_json();

$qty = (int)($in['quantity'] ?? 0);
$o = [
    'name'     => clean($in['name'] ?? '', 80),
    'phone'    => clean($in['phone'] ?? '', 10),
    'email'    => clean($in['email'] ?? '', 100),
    'address'  => clean($in['address'] ?? '', 250),
    'landmark' => clean($in['landmark'] ?? '', 100),
    'city'     => clean($in['city'] ?? '', 60),
    'pincode'  => clean($in['pincode'] ?? '', 6),
    'state'    => clean($in['state'] ?? '', 60),
    'quantity' => (string)$qty,
];

if ($qty < 1 || $qty > 10) fail('Invalid quantity');
if ($o['name'] === '' || $o['address'] === '' || $o['city'] === '' || $o['state'] === '') fail('Please fill all required fields');
if (!preg_match('/^[6-9]\d{9}$/', $o['phone'])) fail('Invalid mobile number');
if (!preg_match('/^[1-9]\d{5}$/', $o['pincode'])) fail('Invalid pincode');
if ($o['email'] !== '' && !filter_var($o['email'], FILTER_VALIDATE_EMAIL)) fail('Invalid email');

$amount = PRICE_PAISE * $qty;
$order = rzp('POST', '/v1/orders', [
    'amount'   => $amount,
    'currency' => 'INR',
    'receipt'  => 'KD' . time() . rand(100, 999),
    'notes'    => $o,   // delivery details stay on the order in the Razorpay dashboard
]);

out(['key' => RZP_KEY_ID, 'order_id' => $order['id'], 'amount' => $amount]);
