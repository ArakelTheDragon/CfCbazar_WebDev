<?php
// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    $vote = $_POST['vote'];
    file_put_contents("results.txt", $vote . "\n", FILE_APPEND);
    echo "<p>Thank you for voting for <strong>$vote</strong>!</p>";
}

// Read and count votes
$results = [];
if (file_exists("results.txt")) {
    $votes = file("results.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($votes as $v) {
        $results[$v] = ($results[$v] ?? 0) + 1;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Poll</title>
</head>
<body>
    <h2>Which is your favorite programming language?</h2>
    <form method="POST">
        <input type="radio" name="vote" value="JavaScript" required> JavaScript<br>
        <input type="radio" name="vote" value="Python"> Python<br>
        <input type="radio" name="vote" value="PHP"> PHP<br>
        <input type="radio" name="vote" value="C++"> C++<br>
        <button type="submit">Vote</button>
    </form>

    <h3>Current Results:</h3>
    <ul>
        <?php
        if ($results) {
            arsort($results);
            foreach ($results as $choice => $count) {
                echo "<li><strong>$choice</strong>: $count votes</li>";
            }
        } else {
            echo "<li>No votes yet.</li>";
        }
        ?>
    </ul>
</body>
</html>