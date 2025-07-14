<?php
// admin.php — Secure Admin Panel
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("config.php");

// Redirect if not authenticated
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$email = trim(strtolower($_SESSION['username']));

// Lookup user (case-insensitive)
$query = $conn->prepare("SELECT email, status FROM users WHERE LOWER(email) = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

$hasAccess = false;
$userFound = false;
$status = 0;

if ($user) {
    $userFound = true;
    $status = (int)$user['status'];
    $hasAccess = in_array($status, [1, 2]);
}

// Handle token update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'], $_POST['tokens'])) {
    $targetEmail = trim($_POST['email']);
    $tokens = (int)$_POST['tokens'];

    $update = $conn->prepare("UPDATE workers SET tokens_earned = ? WHERE email = ?");
    $update->bind_param("is", $tokens, $targetEmail);
    $update->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }

        #loader {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .spinner {
            border: 6px solid #eee;
            border-top: 6px solid #3498db;
            border-radius: 50%;
            width: 60px; height: 60px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        #content { display: none; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }

        form input, form button {
            padding: 10px;
            margin-top: 10px;
            width: 300px;
        }
        form button {
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
        }

        h2, h3 { color: #333; margin-top: 40px; }
    </style>
    <script>
        window.onload = () => {
            setTimeout(() => {
                document.getElementById("loader").style.display = "none";
                document.getElementById("content").style.display = "block";
            }, 5000);
        };

        // Debug console logs
        console.log("Session Email:", "<?= addslashes($email) ?>");
        console.log("User Found:", <?= $userFound ? 'true' : 'false' ?>);
        console.log("User Status:", <?= $status ?>);
        console.log("Access Granted:", <?= $hasAccess ? 'true' : 'false' ?>);
    </script>
</head>
<body>
<div id="loader"><div class="spinner"></div></div>

<div id="content">
    <?php if (!$userFound): ?>
        <h2 style="color: red;">User not found in the database.</h2>
    <?php elseif (!$hasAccess): ?>
        <h2 style="color: red;">Access Denied: Admins only!</h2>
    <?php else: ?>

        <h2>Welcome, <?= htmlspecialchars($user['email']) ?> 👋</h2>

        <h3>User Directory</h3>
        <table>
            <tr><th>ID</th><th>Email</th><th>Status</th></tr>
            <?php
            $users = $conn->query("SELECT id, email, status FROM users");
            while ($row = $users->fetch_assoc()):
            ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h3>Update Tokens Earned</h3>
        <form method="POST">
            <input type="email" name="email" placeholder="Worker email" required><br>
            <input type="number" name="tokens" placeholder="Tokens earned" required><br>
            <button type="submit">Update</button>
        </form>

        <h3>All Workers</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Worker Name</th>
                <th>Email</th>
                <th>HR2 (H/s)</th>
                <th>Tokens Earned</th>
                <th>Address</th>
            </tr>
            <?php
            $workers = $conn->query("SELECT id, worker_name, email, hr2, tokens_earned, address FROM workers");
            while ($w = $workers->fetch_assoc()):
            ?>
            <tr>
                <td><?= htmlspecialchars($w['id']) ?></td>
                <td><?= htmlspecialchars($w['worker_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($w['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($w['hr2'] ?? '') ?></td>
                <td><?= number_format((float)$w['tokens_earned'], 2) ?></td>
                <td><?= htmlspecialchars($w['address'] ?? 'N/A') ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <details style="margin-top: 30px; background: #222; color: #fff; padding: 15px; border-radius: 6px;">
            <summary style="cursor: pointer; font-weight: bold;">🧪 Debug Console</summary>
            <pre style="overflow-x: auto;">
Logged In As: <?= htmlspecialchars($email) ?>

User Found: <?= $userFound ? 'Yes' : 'No' ?>
User Status: <?= $status ?>
Access Granted: <?= $hasAccess ? 'Yes' : 'No' ?>
            </pre>
        </details>

    <?php endif; ?>
</div>
</body>
</html>
<?php $conn->close(); ?>