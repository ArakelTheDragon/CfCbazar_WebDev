<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php'); // DB connection

$errors  = [];
$message = '';
$email   = trim($_POST['email'] ?? '');

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Action 1: send reset code -----------------------------------------
    if (isset($_POST['request_code'])) {
        if ($email === '') {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $reset_code = random_int(100000, 999999);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $upd = $conn->prepare("UPDATE users SET verify_code = ?, verify_expires = ? WHERE email = ?");
                    if ($upd) {
                        $upd->bind_param("iss", $reset_code, $expires_at, $email);
                        if ($upd->execute()) {
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode([
                                    'success'    => true,
                                    'email'      => $email,
                                    'reset_code' => (string)$reset_code,
                                    'message'    => 'A reset code has been sent to your email. It expires in 1 hour.',
                                ]);
                                exit;
                            }
                            // Non-AJAX fallback (server-side send)
                            $api_url = "https://cfcbazar.42web.io/mail.php";
                            $data = [
                                "email"   => $email,
                                "subject" => "CfCbazar – Password Reset",
                                "heading" => "Password Reset",
                                "intro"   => "You requested a password reset code:",
                                "message" => "Your CfCbazar password reset code is: {$reset_code}\n\nIt expires in 1 hour.",
                                "footer_note" => "If you did not request this, you can safely ignore this email."
                            ];
                            $opts = ["http" => [
                                "header"  => "Content-Type: application/x-www-form-urlencoded",
                                "method"  => "POST",
                                "content" => http_build_query($data)
                            ]];
                            $resp   = @file_get_contents($api_url, false, stream_context_create($opts));
                            $result = json_decode($resp ?: '', true);
                            if (isset($result['error'])) {
                                $errors[] = "Email error: " . $result['error'];
                            } else {
                                $message = "A reset code has been sent to your email. It expires in 1 hour.";
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

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($errors ? ['error' => $errors] : ['success' => true]);
            exit;
        }
    }

    // ---- Action 2: verify code + reset password -----------------------------
    if (isset($_POST['reset_password'])) {
        $code     = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required.";
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
                    $stmt->close();

                    if (empty($expires_at) || strtotime($expires_at) < time()) {
                        $errors[] = "Reset code has expired. Please request a new one.";
                    } elseif ((string)$stored_code !== $code) {
                        $errors[] = "Invalid reset code.";
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE users SET password = ?, verify_code = NULL, verify_expires = NULL WHERE email = ?");
                        if ($upd) {
                            $upd->bind_param("ss", $hashed, $email);
                            if ($upd->execute()) {
                                $message = "Password reset successfully. <a href='login.php'>Log in</a>.";
                                session_regenerate_id(true);
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
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($errors ? ['error' => $errors] : ['success' => true, 'message' => $message]);
            exit;
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

<div id="fp-errors" class="error" style="display:none;"></div>
<div id="fp-success" class="success" style="display:none;"></div>

<?php if (!empty($errors)) : ?>
    <div class="error">
        <?php foreach ($errors as $error) : ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($message) : ?>
    <div class="success"><p><?php echo $message; ?></p></div>
<?php endif; ?>

<!-- Step 1: request code -->
<form id="requestForm" method="post" action="forgot_password.php" autocomplete="off">
    <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" id="fp-email"
               value="<?php echo htmlspecialchars($email); ?>" required autofocus>
    </div>
    <div class="input-group">
        <button type="submit" class="btn" name="request_code" id="requestBtn">Send Reset Code</button>
    </div>
</form>

<!-- Step 2: enter code + new password -->
<form id="resetForm" method="post" action="forgot_password.php" autocomplete="off">
    <div class="input-group">
        <label>Reset Code</label>
        <input type="text" name="code" id="fp-code" placeholder="Enter 6-digit code" required>
    </div>
    <div class="input-group">
        <label>New Password</label>
        <input type="password" name="password" id="fp-password" placeholder="Enter new password" required>
    </div>
    <div class="input-group">
        <button type="submit" class="btn" name="reset_password" id="resetBtn">Reset Password</button>
    </div>
    <p><a href="login.php">Back to Login</a></p>
</form>

<script>
function showErrors(box, list) {
    box.innerHTML = list.map(m =>
        "<p>" + String(m).replace(/[<>&]/g, c => ({"<":"&lt;",">":"&gt;","&":"&amp;"}[c])) + "</p>"
    ).join("");
    box.style.display = "block";
}
function showSuccess(box, text) {
    box.innerHTML = "<p>" + text + "</p>";
    box.style.display = "block";
}

// ---- Send reset code --------------------------------------------------------
document.getElementById("requestForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const btn      = document.getElementById("requestBtn");
    const errBox   = document.getElementById("fp-errors");
    const okBox    = document.getElementById("fp-success");
    const email    = document.getElementById("fp-email").value.trim();

    errBox.style.display = "none";
    okBox.style.display  = "none";
    btn.disabled = true;
    btn.textContent = "Sending…";

    fetch("forgot_password.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: new URLSearchParams({ request_code: "1", email: email })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            showErrors(errBox, Array.isArray(data.error) ? data.error : [data.error]);
            btn.disabled = false;
            btn.textContent = "Send Reset Code";
            return;
        }

        // Send the email via mail.php
        const mailBody = new URLSearchParams({
            email:   data.email,
            subject: "CfCbazar – Password Reset",
            heading: "Password Reset",
            intro:   "You requested a password reset code:",
            message: "Your CfCbazar password reset code is: " + data.reset_code + "\n\nIt expires in 1 hour.",
            footer_note: "If you did not request this, you can safely ignore this email."
        });

        return fetch("/mail.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: mailBody
        })
        .then(res => res.json())
        .then(mail => {
            btn.disabled = false;
            btn.textContent = "Send Reset Code";
            if (mail.error) {
                showErrors(errBox, ["Code generated, but the email could not be sent: " + mail.error]);
                return;
            }
            showSuccess(okBox, "A reset code has been sent to your email. It expires in 1 hour.");
        });
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = "Send Reset Code";
        showErrors(errBox, ["Network error. Please try again."]);
    });
});

// ---- Reset password ---------------------------------------------------------
document.getElementById("resetForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const btn    = document.getElementById("resetBtn");
    const errBox = document.getElementById("fp-errors");
    const okBox  = document.getElementById("fp-success");
    const email  = document.getElementById("fp-email").value.trim();
    const code   = document.getElementById("fp-code").value.trim();
    const pass   = document.getElementById("fp-password").value;

    errBox.style.display = "none";
    okBox.style.display  = "none";
    btn.disabled = true;
    btn.textContent = "Resetting…";

    fetch("forgot_password.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: new URLSearchParams({
            reset_password: "1",
            email: email,
            code: code,
            password: pass
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = "Reset Password";

        if (data.error) {
            showErrors(errBox, Array.isArray(data.error) ? data.error : [data.error]);
            return;
        }
        showSuccess(okBox, data.message || "Password reset successfully. <a href='login.php'>Log in</a>.");
        document.getElementById("resetForm").reset();
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = "Reset Password";
        showErrors(errBox, ["Network error. Please try again."]);
    });
});
</script>

</body>
</html>
