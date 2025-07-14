<?php
require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";
require_once "config.php"; // Loads SMTP credentials and DB connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $username = htmlspecialchars($_POST['username'] ?? '');
    $verify_code = htmlspecialchars($_POST['verify_code'] ?? '');

    if (empty($email) || empty($username) || empty($verify_code)) {
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
        $mail->addAddress($email, $username);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - CfCbazar';
        $mail->Body = "
            <p>Hello <strong>$username</strong>,</p>
            <p>Your verification code is:</p>
            <h2>$verify_code</h2>
            <p>Please enter this 6-digit code on our verification page.</p>
            <p>Thank you!</p>
        ";

        if ($mail->send()) {
            echo json_encode(["success" => "Verification email sent"]);
        } else {
            echo json_encode(["error" => "Mail sending failed: " . $mail->ErrorInfo]);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => "Exception: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}
?>