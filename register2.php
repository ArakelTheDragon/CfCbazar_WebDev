<?php
// /register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/reusable.php';

if (!checkSystemFlags($conn)) {
    exit;
}

$errors = [];
$email  = $_POST['email'] ?? '';
$pass1  = $_POST['password_1'] ?? '';
$pass2  = $_POST['password_2'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_user'])) {
    $email = strtolower(trim($email));

    if (empty($email))                          $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($pass1))                          $errors[] = 'Password is required';
    if ($pass1 !== $pass2)                      $errors[] = 'Passwords do not match';

    // Duplicate check
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
        if (!$dup) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $dup->bind_param('s', $email);
            $dup->execute();
            $dup->store_result();
            if ($dup->num_rows) {
                $errors[] = 'Email already registered';
            }
            $dup->close();
        }
    }

    // Insert user + worker
    if (empty($errors)) {
        $hashedPass     = password_hash($pass1, PASSWORD_DEFAULT);
        $verify_token   = bin2hex(random_bytes(16));
        $verify_code    = random_int(100000, 999999);
        $verify_expires = date('Y-m-d H:i:s', time() + 3600);
        $wallet_address = '';

        $ins = $conn->prepare("
            INSERT INTO users
              (email, password, email_verified,
               verify_token, verify_code, verify_expires,
               status, wallet_address)
            VALUES (?, ?, 0, ?, ?, ?, 5, ?)
        ");

        if (!$ins) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $ins->bind_param(
                'ssssss',
                $email,
                $hashedPass,
                $verify_token,
                $verify_code,
                $verify_expires,
                $wallet_address
            );

            if ($ins->execute()) {
                // Create worker record if needed
                $chk = $conn->prepare("SELECT 1 FROM workers WHERE email = ? LIMIT 1");
                $chk->bind_param('s', $email);
                $chk->execute();
                $chk->store_result();

                if ($chk->num_rows === 0) {
                    $wst = $conn->prepare("INSERT INTO workers (worker_name, email) VALUES (?, ?)");
                    if ($wst) {
                        $wst->bind_param('ss', $email, $email);
                        if (!$wst->execute()) {
                            error_log("Worker insert failed: " . $wst->error);
                            $errors[] = "Could not create worker record.";
                        }
                        $wst->close();
                    }
                }
                $chk->close();

                // Send verification email
                if (empty($errors)) {
                    $api_url = "https://cfcbazar.42web.io/mail.php";
                    $data = [
                        'email'       => $email,
                        'verify_code' => $verify_code
                    ];
                    $opts = [
                        'http' => [
                            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                            'method'  => 'POST',
                            'content' => http_build_query($data)
                        ]
                    ];
                    $ctx    = stream_context_create($opts);
                    $resp   = @file_get_contents($api_url, false, $ctx);
                    $result = json_decode($resp ?? '', true);

                    if (isset($result['error'])) {
                        $errors[] = "Email error: " . $result['error'];
                    } else {
                        header("Location: verify.php?email=" . urlencode($email));
                        exit();
                    }
                }
            } else {
                $errors[] = 'Database error: could not create user.';
            }
            $ins->close();
        }
    }
}

// Layout
$title = "Register – CfCbazar";
include_header($title);
include_menu();
render_top_userbar();
?>

<main class="container" style="max-width:480px; margin: 3rem auto;">
    <div class="card">
        <h2 class="page-title" style="text-align:center; margin-bottom:1.5rem;">Register</h2>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" autocomplete="off">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <label for="password_1">Password</label>
                <input type="password" id="password_1" name="password_1" required>
            </div>

            <div class="form-group">
                <label for="password_2">Confirm Password</label>
                <input type="password" id="password_2" name="password_2" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" name="reg_user">Register</button>
            </div>

            <p style="margin-top:1.2rem; text-align:center;">
                Already a member? <a href="login.php">Sign in</a>
            </p>
        </form>
    </div>
</main>

<?php include_footer(); ?>
