<?PHP
require_once 'includes/reusable.php'; // conn and config.php with db connection, reusable header, menu, footer, scripts.js, styles.css

// Set return cookie URL after log in
setReturnURLCookie('move.php');

enforce_https();
include_header();
include_menu();

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$userStatus = getUserStatus($conn);

if ($userStatus === 0) {
	// if user is not logged in
	header("Location: /login.php");
	exit();
}

$email = strtolower($ _SESSION['email'] ?? '');
$userStats = $email ? getWorkerStats($email) : [];

?>

<html>
<head>
<title>Move through the squares</title>
</head>
<body>
<?PHP 
$email= "select email FROM workers";
$email_result = $conn->query($email);

echo "email: ".$email["email"];

$conn->close();
?>
</body>
</html>
<?PHP include_footer(); ?>