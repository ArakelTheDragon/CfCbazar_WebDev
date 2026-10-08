<?php
declare(strict_types=1);

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function field(string $key, string $default = ''): string {
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

// ---- Method -----------------------------------------------------------------
if (($_SERVER["REQUEST_METHOD"] ?? '') !== "POST") {
    respond(["error" => "Invalid request method"]);
}

// ---- Required ---------------------------------------------------------------
$email = field('email');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(["error" => "Valid email is required"]);
}

$subject = field('subject');
if ($subject === '') {
    respond(["error" => "Subject is required"]);
}
// Header-injection guard + length cap
$subject = str_replace(["\r", "\n"], ' ', $subject);
if (mb_strlen($subject) > 150) {
    $subject = mb_substr($subject, 0, 147) . '...';
}

$message = field('message');
if ($message === '') {
    respond(["error" => "Message is required"]);
}
if (strlen($message) > 5000) {
    respond(["error" => "Message is too long"]);
}
// Reject control bytes (allow \n and \t)
if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $message)) {
    respond(["error" => "Invalid message"]);
}

// ---- Optional overrides -----------------------------------------------------
$heading    = field('heading',     'CfCbazar');
$intro      = field('intro');
$footerNote = field('footer_note', 'If you did not request this message, you can safely ignore this email.');
$fromName   = field('from_name',   'CfCbazar');

// ---- Render -----------------------------------------------------------------
$safeMessage    = htmlspecialchars($message,    ENT_QUOTES, 'UTF-8');
$safeHeading    = htmlspecialchars($heading,    ENT_QUOTES, 'UTF-8');
$safeIntro      = htmlspecialchars($intro,      ENT_QUOTES, 'UTF-8');
$safeFooterNote = htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8');

$htmlMessage = nl2br($safeMessage, false);
$introBlock  = $intro !== '' ? "<p>{$safeIntro}</p>" : '';

$htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:8px;'>
    <h2 style='color:#2c3e50;margin-top:0;'>{$safeHeading}</h2>
    <p>Hello,</p>
    {$introBlock}
    <div style='font-size:16px;line-height:1.6;color:#2c3e50;background:#ffffff;padding:16px 18px;border-left:4px solid #27ae60;border-radius:4px;margin:20px 0;'>
        {$htmlMessage}
    </div>
    <p style='color:#555;font-size:14px;'>{$safeFooterNote}</p>
    <hr style='border:none;border-top:1px solid #ddd;margin:24px 0;'>
    <p style='font-size:12px;color:#888;'>© CfCbazar</p>
</div>";

$plainBody = $message . "\n\n" . $footerNote;

// ---- Send -------------------------------------------------------------------
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

    $mail->setFrom('cfcbazar@gmail.com', $fromName);
    $mail->addAddress($email);
    $mail->addReplyTo('cfcbazar@gmail.com', 'CfCbazar Support');

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $plainBody;

    $mail->send();

    respond(["success" => "Email sent"]);
} catch (Exception $e) {
    error_log("mail.php error: " . $e->getMessage() . " | PHPMailer: " . ($mail->ErrorInfo ?? ''));
    respond(["error" => "Mail sending failed"]);
}
