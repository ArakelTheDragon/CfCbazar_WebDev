<?php
// register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "config.php"; // assumes $conn is your mysqli connection

$errors        = [];
$email         = $_POST['email']      ?? '';
$pass1         = $_POST['password_1'] ?? '';
$pass2         = $_POST['password_2'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_user'])) {
    // 1) Normalize & validate
    $email = strtolower(trim($email));

    if (empty($email))                     $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($pass1))                     $errors[] = 'Password is required';
    if ($pass1 !== $pass2)                 $errors[] = 'Passwords do not match';

    // 2) Duplicate check
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

    // 3) Insert user and create worker
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
            VALUES (?, ?, 5, ?, ?, ?, 0, ?)
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
                // 3a) Insert into workers
                $chk = $conn->prepare("SELECT 1 FROM workers WHERE worker_name = ? LIMIT 1");
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
                    } else {
                        error_log("Worker stmt prepare failed: " . $conn->error);
                        $errors[] = "Could not create worker record.";
                    }
                }
                $chk->close();

                // 3b) Send verification email
                if (empty($errors)) {
                    $api_url = "https://cfcbazar.ct.ws/mail.php";
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
                    $resp   = file_get_contents($api_url, false, $ctx);
                    $result = json_decode($resp, true);

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
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register – CfCbazar</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="header">
    <h2>Register</h2>
  </div>
  <form method="post" action="register.php">
    <?php if ($errors): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p class="error"><?php echo htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="input-group">
      <label>Email</label>
      <input
        type="email"
        name="email"
        value="<?php echo htmlspecialchars($email) ?>"
        required>
    </div>

    <div class="input-group">
      <label>Password</label>
      <input type="password" name="password_1" required>
    </div>

    <div class="input-group">
      <label>Confirm Password</label>
      <input type="password" name="password_2" required>
    </div>

    <div class="input-group">
      <button type="submit" class="btn" name="reg_user">
        Register
      </button>
    </div>

    <p>
      Already a member? <a href="login.php">Sign in</a>
    </p>
  </form>
</body>
</html>