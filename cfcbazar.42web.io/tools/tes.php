<?php
// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test output
echo "Hello from test.php";

// Optional: test database connection
$servername = "sql313.infinityfree.com";
$username   = "if0_39103611";
$password   = "53098516";
$dbname     = "if0_39103611_db1";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo "<br>Database connected successfully!";
?>