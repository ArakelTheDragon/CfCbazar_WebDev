<?php
// /login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Reusable Engine
$reusablePath = __DIR__ . '/includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: Core library missing.");
}

// Captcha
renderCaptchaIfNeeded();

// HTTPS
enforce_https();

// Default return URL
$return_url = '/index.php';

// Return URL from cookie
if (isset($_COOKIE['return_url'])) {
    $cookie_url = urldecode($_COOKIE['return_url']);
    if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $cookie_url)) {
        $return_url = $cookie_url;
    }
}

// Return URL from GET (takes priority)
if (isset($_GET['return_url'])) {
    $requested_url = urldecode($_GET['return_url']);
    if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $requested_url)) {
        $return_url = $requested_url;
    }
}

$errors = [];

if (isset($_POST['login_user'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '')    $errors[] = "Email is required";
    if ($password === '') $errors[] = "Password is required";

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
                // Generate new verification code
                $new_verify_code = strval(random_int(100000, 999999));
                $upd = $conn->prepare("UPDATE users SET verify_code = ? WHERE email = ?");
                $upd->bind_param("ss", $new_verify_code, $email);
                $upd->execute();
                $upd->close();

                // Send verification email
                $api_url = "https://cfcbazar.42web.io/mail.php";
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
                $context  = stream_context_create($options);
                $response = @file_get_contents($api_url, false, $context);
                $result   = json_decode($response, true);

                if (isset($result["error"])) {
                    $errors[] = "Email error: " . $result["error"];
                } else {
                    $_SESSION['message'] = "Your account is not verified. A new verification email has been sent.";
                    header("Location: verify.php?email=" . urlencode($email));
                    exit();
                }
            } else {
                // Successful login
                session_regenerate_id(true);
                $_SESSION['email']   = $email;
                $_SESSION['user_id'] = $id;
                $_SESSION['success'] = "You are now logged in";

                // Clear return_url cookie
                if (isset($_COOKIE['return_url'])) {
                    setcookie('return_url', '', time() - 3600, '/');
                    unset($_COOKIE['return_url']);
                }

                header("Location: " . $return_url);
                exit();
            }
        } else {
            $errors[] = "Wrong email/password combination";
        }
        $stmt->close();
    }
}

// Header + Menu
$title = "Login – CfCbazar";
include_header($title);
include_menu();
render_top_userbar();
?>

<main class="container" style="max-width:480px; margin: 3rem auto;">
    <div class="card">
        <h2 class="page-title" style="text-align:center; margin-bottom:1.5rem;">Login</h2>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php<?= isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : '' ?>" autocomplete="off">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" name="login_user">Login</button>
            </div>

            <p style="margin-top:1.2rem; text-align:center;">
                Not yet a member? <a href="register.php">Sign up</a><br>
                <a href="forgot_password.php">Forgot Your Password?</a>
            </p>
        </form>
    </div>
</main>

<?php include_footer(); ?>
