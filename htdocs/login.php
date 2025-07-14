<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php'); // DB connection

$errors = [];

if (isset($_POST['login_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $errors[] = "Username is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Retrieve hashed password, email verification status, and email
        $stmt = $conn->prepare("SELECT password, email_verified, email FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($hashed_password, $email_verified, $email_db);
            $stmt->fetch();
            
            if (!$email_verified) {
                // Generate a new 6-digit verification code
                $new_verify_code = strval(random_int(100000, 999999));
                
                // Update the user's verify_code in the database
                $upd = $conn->prepare("UPDATE users SET verify_code = ? WHERE email = ?");
                $upd->bind_param("ss", $new_verify_code, $email_db);
                if (!$upd->execute()) {
                    $errors[] = "Database error: Unable to update verification code.";
                }
                $upd->close();

                // Send a new verification email via API call using mail.php
                $api_url = "https://cfcbazar.ct.ws/mail.php";
                $data = [
                    "email"       => $email_db,
                    "username"    => $username,
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
                    $_SESSION['message'] = "Your account is not verified. A new verification email has been sent. Please verify your email to continue.";
                    header("Location: verify.php?email=" . urlencode($email_db));
                    exit();
                }
            }
            
            // If the email is verified, check the password.
            if (password_verify($password, $hashed_password)) {
                // Login successful
                $_SESSION['username'] = $username;
                $_SESSION['success'] = "You are now logged in";
                header('Location: index.php');
                exit();
            } else {
                $errors[] = "Wrong username/password combination";
            }
        } else {
            $errors[] = "Wrong username/password combination";
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
      /* Additional styling to discourage autocomplete if needed */
      input[autocomplete="off"] {
        background-clip: padding-box;
      }
    </style>
</head>
<body>

<div class="header">
    <h2>Login</h2>
</div>

<!-- Disable autocomplete on the form as well -->
<form method="post" action="login.php" autocomplete="off">

    <?php if (!empty($errors)) : ?>
        <div class="error">
            <?php foreach ($errors as $error) {
                echo "<p>" . htmlspecialchars($error) . "</p>";
            } ?>
        </div>
    <?php endif; ?>

    <div class="input-group">
        <label>Username</label>
        <!-- We explicitly clear any pre-filled value -->
        <input type="text" name="username" autocomplete="off" value="">
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
</form>

</body>
</html> 