<?php
// test.php
// --- Config ---
$API_KEY = "4ca061480c65a9db8094300fd5de9bd2";

// Handle solution submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha_id = $_POST['captcha_id'] ?? '';
    $solution   = $_POST['solution'] ?? '';

    if ($captcha_id && $solution) {
        // Send solution to 2Captcha
        $res = file_get_contents("http://2captcha.com/res.php?key=$API_KEY&action=reportgood&id=$captcha_id");
        echo "<p>Submitted solution: " . htmlspecialchars($solution) . "</p>";
        echo "<p>2Captcha response: $res</p>";
        echo '<a href="test.php">Solve another</a>';
        exit;
    }
}

// Request a new captcha
// Example: fetch an image captcha
$captcha_url = "http://2captcha.com/in.php?key=$API_KEY&method=captcha&json=1";
$response = file_get_contents($captcha_url);
$data = json_decode($response, true);

if (!$data || !isset($data['request'])) {
    die("Error fetching captcha: $response");
}

$captcha_id = $data['request'];
// Image URL format
$image_url = "http://2captcha.com/captcha/$captcha_id.png";
?>

<!DOCTYPE html>
<html>
<head>
  <title>Captcha Test</title>
</head>
<body>
  <h2>Solve Captcha</h2>
  <form method="POST">
    <img src="<?php echo $image_url; ?>" alt="captcha"><br><br>
    <input type="hidden" name="captcha_id" value="<?php echo $captcha_id; ?>">
    <input type="text" name="solution" placeholder="Enter captcha text" required>
    <button type="submit">Submit</button>
  </form>
</body>
</html>