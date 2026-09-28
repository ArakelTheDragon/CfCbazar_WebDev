<?php
require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

/* ============================================================
   WITHDRAWAL REQUEST (from miner)
============================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['amount'])) {

    $amount  = trim($_POST['amount']  ?? '');
    $address = trim($_POST['address'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validate BEP20 address
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        echo json_encode(["status" => "error", "error" => "Invalid BEP20 address"]);
        exit;
    }

    // Validate amount
    if (!is_numeric($amount) || $amount <= 0) {
        echo json_encode(["status" => "error", "error" => "Invalid amount"]);
        exit;
    }

    // Build email
    $subject = "WorkTHR Withdrawal Request";
    $body = "
        <h2>WorkTHR Withdrawal Request</h2>
        <p><strong>Amount:</strong> $amount WorkTHR</p>
        <p><strong>BEP20 Address:</strong> $address</p>
        <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
    ";

    // Send email to admin
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('cfcbazar@gmail.com', 'CfCbazar');
        $mail->addAddress("cfcbazar@gmail.com");

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        echo json_encode(["status" => "ok"]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "error" => $mail->ErrorInfo]);
    }

    exit;
}

/* ============================================================
   VERIFICATION MODE (legacy)
============================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_code'])) {

    $email       = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $verify_code = htmlspecialchars($_POST['verify_code'] ?? '');

    if (empty($email) || empty($verify_code)) {
        echo json_encode(["error" => "Missing required fields"]);
        exit;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('cfcbazar@gmail.com', 'CfCbazar');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'CfCbazar - Verification Code';
        $mail->Body = "
            <p>Hello,</p>
            <p>Your verification code is:</p>
            <h2>$verify_code</h2>
            <p>Thank you!</p>
        ";

        $mail->send();
        echo json_encode(["success" => "Verification email sent"]);

    } catch (Exception $e) {
        echo json_encode(["error" => "Exception: " . $e->getMessage()]);
    }

    exit;
}

echo json_encode(["error" => "Invalid request"]);
?>

