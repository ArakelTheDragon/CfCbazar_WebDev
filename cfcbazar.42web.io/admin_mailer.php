<?php
require_once "includes/reusable.php";
require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";

// Set return url cookie for after log in
setReturnUrlCookie('/admin_mailer.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Get user status and redirect if not admin or not logged in
$userStatus = getUserStatus($conn);

if ($userStatus === 0) {
    // Not logged in
    header("Location: /login.php");
    exit();
} elseif ($userStatus !== 1) {
    // Logged in but not admin
    header("Location: /index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = filter_var($_POST['recipient'] ?? '', FILTER_SANITIZE_EMAIL);
    $subject   = trim($_POST['subject'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    if (!$recipient || !$subject || !$message) {
        $status = "❌ Missing fields.";
    } else {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('cfcbazar@gmail.com', 'CfCbazar Admin');
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br(htmlspecialchars($message));

            $mail->send();
            $status = "✅ Email sent to $recipient";
        } catch (Exception $e) {
            $status = "❌ Mail error: " . $e->getMessage();
        }
    }
}

// Fetch users
$users = [];
$result = $conn->query("SELECT email FROM users ORDER BY email ASC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row['email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>📧 Admin Mailer | CfCbazar</title>
  <style>
    body { font-family: sans-serif; padding: 20px; background: #f9f9f9; }
    form { max-width: 480px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    select, input, textarea { width: 100%; margin-bottom: 12px; padding: 8px; font-size: 1em; }
    button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #0056b3; }
    .status { margin-top: 16px; font-weight: bold; }
  </style>
</head>
<body>
  <form method="POST">
    <h2>📧 Send Email to User</h2>
    <label for="recipient">Recipient:</label>
    <select name="recipient" id="recipient" required>
      <option value="">-- Select User --</option>
      <?php foreach ($users as $email): ?>
        <option value="<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="subject">Subject:</label>
    <input type="text" name="subject" id="subject" required />

    <label for="message">Message:</label>
    <textarea name="message" id="message" rows="6" required></textarea>

    <button type="submit">Send Email</button>

    <?php if (isset($status)): ?>
      <div class="status"><?= htmlspecialchars($status) ?></div>
    <?php endif; ?>
  </form>
</body>
</html>