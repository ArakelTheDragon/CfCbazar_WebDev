<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php'); // DB connection

$errors = [];
$message = '';
$step = 1; // Default to step 1 (request reset)

$email = $_SESSION['reset_email'] ?? ($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Request reset code
    if (isset($_POST['request_code'])) {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    // Generate reset code (6 digits)
                    $reset_code = random_int(100000, 999999);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $upd = $conn->prepare("UPDATE users SET verify_code = ?, verify_expires = ? WHERE email = ?");
                    if ($upd) {
                        $upd->bind_param("iss", $reset_code, $expires_at, $email);
                        if ($upd->execute()) {
                            // Send email with raw code
                            $api_url = "https://cfcbazar.ct.ws/mail.php";
                            $data = [
                                "email" => $email,
                                "verify_code" => $reset_code,
                                "type" => "reset"
                            ];
                            $options = [
                                "http" => [
                                    "header" => "Content-Type: application/x-www-form-urlencoded",
                                    "method" => "POST",
                                    "content" => http_build_query($data)
                                ]
                            ];
                            $context = stream_context_create($options);
                            $response = @file_get_contents($api_url, false, $context);

                            if ($response === false) {
                                $errors[] = "Unable to send reset code. Try again later.";
                            } else {
                                $result = json_decode($response, true);
                                if (isset($result["error"])) {
                                    $errors[] = "Email error: " . $result["error"];
                                } else {
                                    $message = "A reset code has been sent to your email. It expires in 1 hour.";
                                    $_SESSION['reset_email'] = $email;
                                    $step = 2;
                                }
                            }
                        } else {
                            $errors[] = "Database error: Could not save reset code.";
                        }
                        $upd->close();
                    }
                } else {
                    $errors[] = "No account found with that email.";
                }
                $stmt->close();
            }
        }
    }

    // Step 2: Verify code & reset password
    if (isset($_POST['reset_password'])) {
        $email = $_SESSION['reset_email'] ?? '';
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $errors[] = "Session expired. Please request a new reset code.";
        }
        if (!preg_match('/^\d{6}$/', $code)) {
            $errors[] = "Reset code must be 6 digits.";
        }
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT verify_code, verify_expires FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->bind_result($stored_code, $expires_at);

                if ($stmt->fetch()) {
                    // ✅ close before new query
                    $stmt->close();

                    if (strtotime($expires_at) < time()) {
                        $errors[] = "Reset code has expired.";
                    } elseif ($stored_code != $code) {
                        $errors[] = "Invalid reset code.";
                    } else {
                        // Update password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE users SET password = ?, verify_code = NULL, verify_expires = NULL WHERE email = ?");
                        if ($upd) {
                            $upd->bind_param("ss", $hashed_password, $email);
                            if ($upd->execute()) {
                                $message = "Password reset successfully. <a href='login.php'>Log in</a>.";
                                unset($_SESSION['reset_email']);
                                session_regenerate_id(true);
                                $step = 1;
                            } else {
                                $errors[] = "Could not update password. Try again.";
                            }
                            $upd->close();
                        }
                    }
                } else {
                    $stmt->close();
                    $errors[] = "Invalid request. Try again.";
                }
            }
        } else {
            $step = 2; // Stay on reset form
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - CfCbazar</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<div class="header">
    <h2>Forgot Password</h2>
</div>

<?php if (!empty($errors)) : ?>
    <div class="error">
        <?php foreach ($errors as $error) : ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($message) : ?>
    <div class="success">
        <p><?php echo $message; ?></p>
    </div>
<?php endif; ?>

<?php if ($step === 1) : ?>
<form method="post" action="forgot_password.php" autocomplete="off">
    <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
    </div>
    <div class="input-group">
        <button type="submit" class="btn" name="request_code">Send Reset Code</button>
    </div>
    <p><a href="login.php">Back to Login</a></p>
</form>
<?php endif; ?>

<?php if ($step === 2) : ?>
<form method="post" action="forgot_password.php" autocomplete="off">
    <div class="input-group">
        <label>Reset Code</label>
        <input type="text" name="code" placeholder="Enter 6-digit code" required autofocus>
    </div>
    <div class="input-group">
        <label>New Password</label>
        <input type="password" name="password" placeholder="Enter new password" required>
    </div>
    <div class="input-group">
        <button type="submit" class="btn" name="reset_password">Reset Password</button>
    </div>
    <p><a href="forgot_password.php">Request New Code</a></p>
</form>
<?php endif; ?>

</body>
</html>