<?php
// Handle vote submission
$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    $vote = trim($_POST['vote']);
    if ($vote !== '') {
        file_put_contents("results.txt", $vote . "\n", FILE_APPEND);
        $submitted = true;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Favorite Programming Language Poll</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            text-align: center;
            padding: 50px;
        }
        .poll-container {
            background: white;
            display: inline-block;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }
        h2 {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0;
            font-size: 18px;
            text-align: left;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            background-color: #3f51b5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #303f9f;
        }
        .thank-you {
            font-size: 20px;
            color: green;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="poll-container">
    <?php if ($submitted): ?>
        <div class="thank-you">✅ Thank you for voting!</div>
    <?php else: ?>
        <h2>Which is your favorite part of our channel?</h2>
        <form method="POST">
            <label><input type="radio" name="vote" value="DIY hacks" required> DIY hacks</label>
            <label><input type="radio" name="vote" value="Games"> Games</label>
            <label><input type="radio" name="vote" value="Music"> Music</label>
            <label><input type="radio" name="vote" value="Smart deals"> Smart deals</label>
            <label><input type="radio" name="vote" value="the WorkToken"> The WorkToken</label>
            <button type="submit">Submit Vote</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>