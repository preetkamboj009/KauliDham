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
