<?php
// success.php — IPN + Session + Referrer Security

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include($_SERVER['DOCUMENT_ROOT'] . "/config.php");

// Step 1: Session Check
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}

// Step 2: Referrer Check
$allowed_referrer = "https://sandbox.paypal.com/";
if (!isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] !== $allowed_referrer) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied. Invalid referrer.";
    exit();
}

// Step 3: IPN Validation (PayPal server-to-server)
$raw_post_data = file_get_contents('php://input');
$req = 'cmd=_notify-validate&' . $raw_post_data;

$ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
curl_close($ch);

if (strcmp($response, "VERIFIED") === 0) {
    $payment_status = $_POST['payment_status'] ?? '';
    $mc_gross = $_POST['mc_gross'] ?? '';
    $payer_email = $_POST['payer_email'] ?? '';
    $txn_id = $_POST['txn_id'] ?? '';
    $expected_amount = "1.35";

    if ($payment_status === "Completed" && $mc_gross == $expected_amount) {
        // Payment confirmed
        $email = $_SESSION['username'];
        echo "Welcome, $email! Payment verified. Award granted.";
        // Optional: update database, send email, etc.
    } else {
        echo "Payment verification failed or amount mismatch.";
    }
} else {
    echo "IPN response invalid. Access denied.";
}
?>

