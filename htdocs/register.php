<?php
session_start();
require_once "config.php"; // Ensure correct DB connection

$errors = [];
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$pass1 = $_POST['password_1'] ?? '';
$pass2 = $_POST['password_2'] ?? '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reg_user'])) {
    $username = trim($username);
    $email = strtolower(trim($email));

    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($pass1)) $errors[] = 'Password is required';
    if ($pass1 !== $pass2) $errors[] = 'Passwords do not match';

    if (empty($errors)) {
        // Check for duplicate username or email
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR LOWER(email)=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows) {
                $errors[] = 'Username or email already registered';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
    
    if (empty($errors)) {
        $hashedPass = password_hash($pass1, PASSWORD_DEFAULT);
        // Generate a 6-digit numeric verification code
        $verify_code = strval(random_int(100000, 999999));

        // You can uncomment the line below for debugging purposes
        // var_dump($verify_code);

        // Insert the new user. Adjust your table structure accordingly:
        // In this revision, we store username, email, password, verify_code, and email_verified.
        $ins = $conn->prepare(
            "INSERT INTO users (username, email, password, verify_code, email_verified)
             VALUES (?, ?, ?, ?, 0)"
        );

        if ($ins) {
            $ins->bind_param('ssss', $username, $email, $hashedPass, $verify_code);
            if ($ins->execute()) {
                // Send verification email via an API call to mail.php
                $api_url = "https://cfcbazar.ct.ws/mail.php";
                $data = [
                    "email"       => $email,
                    "username"    => $username,
                    "verify_code" => $verify_code
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
                    // Redirect users to verify their email (the email field will be prefilled)
                    header("Location: verify.php?email=" . urlencode($email));
                    exit();
                }
            } else {
                $errors[] = 'Database error: could not create user.';
            }
            $ins->close();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registration System - CfCbazar</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="header">
        <h2>Register</h2>
    </div>
    <form method="post" action="register.php">
        <?php if ($errors): ?>
            <div class="errors">
                <?php foreach ($errors as $err): ?>
                    <p class="error"><?php echo htmlspecialchars($err); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="input-group">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password_1">
        </div>
        <div class="input-group">
            <label>Confirm Password</label>
            <input type="password" name="password_2">
        </div>
        <div class="input-group">
            <button type="submit" class="btn" name="reg_user">Register</button>
        </div>
        <p>
            Already a member? <a href="login.php">Sign in</a>
        </p>
    </form>
</body>
</html>
