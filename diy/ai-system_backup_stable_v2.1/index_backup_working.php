<?php

require_once __DIR__ . '/core/Router.php';

$response = "";
$prompt = "";
$status = [];
$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prompt = trim($_POST['prompt'] ?? "");

    if ($prompt !== "") {
        $router = new Router();
        $response = $router->handle($prompt);

        $status = [
            "last_topic"     => $router->lastTopic,
            "last_entities"  => $router->lastEntities,
            "last_intent"    => $router->lastIntent,
            "question_type"  => $router->lastQuestionType,
            "response_time"  => microtime(true) - $startTime
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI System - Beta GUI</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body { background: #121212; color: #e0e0e0; font-family: Arial, sans-serif; margin: 0; padding: 0; }
    .container { width: 95%; max-width: 900px; margin: 40px auto; background: #1e1e1e; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #000; }
    h1 { text-align: center; color: #4fc3f7; margin-bottom: 20px; }
    textarea { width: 100%; height: 140px; background: #0f0f0f; color: #e0e0e0; border: 1px solid #333; border-radius: 6px; padding: 10px; font-size: 16px; resize: vertical; }
    button { background: #4fc3f7; color: #000; padding: 12px 20px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 10px; width: 100%; }
    button:hover { background: #81d4fa; }
    .response-box { margin-top: 20px; background: #0f0f0f; padding: 15px; border-radius: 6px; white-space: pre-wrap; border: 1px solid #333; font-size: 15px; line-height: 1.5; }
    .status-panel { margin-top: 30px; background: #1e1e1e; padding: 20px; border-radius: 10px; border: 1px solid #333; }
    .status-panel h2 { color: #4fc3f7; margin-bottom: 15px; }
    .status-item { margin-bottom: 8px; font-size: 15px; }
</style>
</head>
<body>

<div class="container">
    <h1>AI System - Beta Test GUI</h1>

    <form method="POST">
        <textarea name="prompt" placeholder="Type your prompt here..."><?php echo htmlspecialchars($prompt); ?></textarea>
        <button type="submit">Run AI</button>
    </form>

    <?php if ($response !== ""): ?>
        <div class="response-box">
            <?php echo htmlspecialchars($response); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($status)): ?>
        <div class="status-panel">
            <h2>System Status</h2>
            <div class="status-item"><strong>Last Topic:</strong> <?php echo htmlspecialchars($status['last_topic']); ?></div>
            <div class="status-item"><strong>Last Entities:</strong> <?php echo implode(", ", $status['last_entities']); ?></div>
            <div class="status-item"><strong>Intent:</strong> <?php echo htmlspecialchars($status['last_intent']); ?></div>
            <div class="status-item"><strong>Question Type:</strong> <?php echo htmlspecialchars($status['question_type']); ?></div>
            <div class="status-item"><strong>Response Time:</strong> <?php echo number_format($status['response_time'], 4); ?> sec</div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>