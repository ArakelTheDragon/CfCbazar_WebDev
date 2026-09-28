<?php
declare(strict_types=1);

require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";
require_once "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Invalid request method"]);
    exit;
}

$email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$verify_code = trim($_POST['verify_code'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["error" => "Valid email is required"]);
    exit;
}

if ($verify_code === '') {
    echo json_encode(["error" => "Verification code is required"]);
    exit;
}

// Basic length protection
/* if (strlen($verify_code) > 64) {
    echo json_encode(["error" => "Invalid verification code"]);
    exit;
}*/

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('cfcbazar@gmail.com', 'CfCbazar');
    $mail->addAddress($email);
    $mail->addReplyTo('cfcbazar@gmail.com', 'CfCbazar Support');

    $mail->isHTML(true);
    $mail->Subject = 'CfCbazar – Verification Code';

    $safeCode = htmlspecialchars($verify_code, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:8px;'>
        <h2 style='color:#2c3e50;margin-top:0;'>CfCbazar Verification</h2>
        <p>Hello,</p>
        <p>You requested a verification message from the CfCbazar platform:</p>
        <div style='font-size:28px;font-weight:bold;letter-spacing:4px;background:#ffffff;border:2px solid #27ae60;color:#27ae60;padding:16px;text-align:center;border-radius:6px;margin:20px 0;'>
            {$safeCode}
        </div>
        <p style='color:#555;font-size:14px;'>If you did not request this message, you can safely ignore this email.</p>
        <hr style='border:none;border-top:1px solid #ddd;margin:24px 0;'>
        <p style='font-size:12px;color:#888;'>© CfCbazar</p>
    </div>";

    $mail->AltBody = "Your CfCbazar verification message is: {$verify_code}\n\nIf you did not request this, please ignore this email.";

    $mail->send();
    echo json_encode(["success" => "Verification email sent"]);

} catch (Exception $e) {
    error_log("mail.php error: " . $e->getMessage());
    echo json_encode(["error" => "Mail sending failed"]);
}
