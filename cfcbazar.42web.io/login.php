<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('config.php'); // DB connection

$errors = [];

// ✅ Default redirect URL
$return_url = 'index.php';

// ✅ Check for return_url cookie
if (isset($_COOKIE['return_url'])) {
    $cookie_url = urldecode($_COOKIE['return_url']);
    if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $cookie_url)) {
        $return_url = $cookie_url;
    }
}

// ✅ Override with GET param if valid
if (isset($_GET['return_url'])) {
    $requested_url = urldecode($_GET['return_url']);
    if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $requested_url)) {
        $return_url = $requested_url;
    }
}

if (isset($_POST['login_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email)) {
        $errors[] = "Email is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, password, email_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $hashed_password, $email_verified);
            $stmt->fetch();

            if (!password_verify($password, $hashed_password)) {
                $errors[] = "Wrong email/password combination";
            } elseif ((int)$email_verified !== 1) {
                $new_verify_code = strval(random_int(100000, 999999));

                $upd = $conn->prepare("UPDATE users SET verify_code = ? WHERE email = ?");
                $upd->bind_param("ss", $new_verify_code, $email);
                if (!$upd->execute()) {
                    $errors[] = "Database error: Unable to update verification code.";
                }
                $upd->close();

                $api_url = "https://cfcbazar.ct.ws/mail.php";
                $data = [
                    "email"       => $email,
                    "verify_code" => $new_verify_code
                ];
                $options = [
                    "http" => [
                        "header"  => "Content-Type: application/x-www-form-urlencoded",
                        "method"  => "POST",
                        "content" => http_build_query($data)
                    ]
                ];
                $context = stream_context_create($options);
                $response = file_get_contents($api_url, false, $context);
                $result = json_decode($response, true);

                if (isset($result["error"])) {
                    $errors[] = "Email error: " . $result["error"];
                } else {
                    $_SESSION['message'] = "Your account is not verified. A new verification email has been sent.";
                    header("Location: verify.php?email=" . urlencode($email));
                    exit();
                }
            } else {
                session_regenerate_id(true);
                $_SESSION['email'] = $email;
                $_SESSION['user_id'] = $id;
                $_SESSION['success'] = "You are now logged in";
                header("Location: $return_url");
                exit();
            }
        } else {
            $errors[] = "Wrong email/password combination";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - CfCbazar</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        input[autocomplete="off"] {
            background-clip: padding-box;
        }
        .error { background: #ffe0e0; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .error p { margin: 0; color: #b00020; }
        .input-group { margin-bottom: 12px; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="header">
    <h2>Login</h2>
</div>

<form method="post" action="login.php" autocomplete="off">

    <?php if (!empty($errors)) : ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" autocomplete="off" value="">
    </div>
    <div class="input-group">
        <label>Password</label>
        <input type="password" name="password" autocomplete="new-password">
    </div>
    <div class="input-group">
        <button type="submit" class="btn" name="login_user">Login</button>
    </div>
    <p>
        Not yet a member? <a href="register.php">Sign up</a>
    </p>
    <p>
        <a href="forgot_password.php">Forgot Your Password?</a>
    </p>
</form>

</body>
</html>