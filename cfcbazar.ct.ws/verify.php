<?php
require 'config.php';
session_start();

$errors = [];
$success = '';

// Pre-fill email if provided via POST or GET
$email = trim($_POST['email'] ?? $_GET['email'] ?? '');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST['verify_code'] ?? '');

    if (empty($email)) {
        $errors[] = 'Email is required.';
    }

    if (empty($code)) {
        $errors[] = 'Verification code is required.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Verification code must be 6 digits.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ? AND verify_code = ? LIMIT 1");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $email_verified);
            $stmt->fetch();

            if ((int)$email_verified === 1) {
                $errors[] = '❌ This email is already verified.';
            } else {
                // Set email as verified and clear code
                $update = $conn->prepare("UPDATE users SET email_verified = 1, verify_code = NULL WHERE id = ?");
                $update->bind_param("i", $user_id);
                if ($update->execute()) {
                    $success = '✅ Your email has been verified! <a href="login.php">Log in now</a>.';
                } else {
                    $errors[] = '❌ Failed to update verification. Try again.';
                }
                $update->close();
            }
        } else {
            $errors[] = '❌ Incorrect verification code or email.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email - CfCbazar</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="header"><h2>Email Verification</h2></div>

<form method="post" action="verify.php">
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $err): ?>
                <p class="error"><?php echo htmlspecialchars($err); ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
    </div>
    <div class="input-group">
        <label>Verification Code (6 digits)</label>
        <input type="text" name="verify_code" pattern="\d{6}" maxlength="6" required>
    </div>
    <div class="input-group">
        <button type="submit" class="btn">Verify Email</button>
    </div>
</form>
</body>
</html>