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
<!-- Client-Side Markdown Parser -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    body { background: #121212; color: #e0e0e0; font-family: Arial, sans-serif; margin: 0; padding: 0; }
    .container { width: 95%; max-width: 900px; margin: 40px auto; background: #1e1e1e; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #000; }
    h1 { text-align: center; color: #4fc3f7; margin-bottom: 20px; }
    textarea { width: 100%; height: 140px; background: #0f0f0f; color: #e0e0e0; border: 1px solid #333; border-radius: 6px; padding: 10px; font-size: 16px; resize: vertical; box-sizing: border-box; }
    button { background: #4fc3f7; color: #000; padding: 12px 20px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 10px; width: 100%; font-weight: bold; }
    button:hover { background: #81d4fa; }

    /* Markdown Output Styles */
    .response-box { margin-top: 20px; background: #0f0f0f; padding: 20px; border-radius: 6px; border: 1px solid #333; font-size: 15px; line-height: 1.6; word-wrap: break-word; }
    .response-box h1, .response-box h2, .response-box h3, .response-box h4 { color: #4fc3f7; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #2a2a2a; padding-bottom: 4px; }
    .response-box h1:first-child, .response-box h2:first-child, .response-box h3:first-child { margin-top: 0; }
    .response-box p { margin: 0 0 12px 0; }
    .response-box code { background: #1a1a1a; color: #ffb74d; padding: 2px 6px; border-radius: 4px; font-family: 'Courier New', Courier, monospace; font-size: 14px; }
    .response-box pre { background: #050505; padding: 14px; border-radius: 6px; border: 1px solid #282828; overflow-x: auto; margin: 15px 0; }
    .response-box pre code { background: transparent; color: #81d4fa; padding: 0; }
    .response-box ul, .response-box ol { padding-left: 24px; margin-bottom: 12px; }
    .response-box li { margin-bottom: 6px; }
    .response-box blockquote { border-left: 4px solid #4fc3f7; margin: 12px 0; padding-left: 15px; color: #b0bec5; font-style: italic; }
    .response-box table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    .response-box th, .response-box td { border: 1px solid #333; padding: 8px 12px; text-align: left; }
    .response-box th { background: #1a1a1a; color: #4fc3f7; }
    .response-box hr { border: 0; height: 1px; background: #333; margin: 20px 0; }

    .status-panel { margin-top: 30px; background: #1e1e1e; padding: 20px; border-radius: 10px; border: 1px solid #333; }
    .status-panel h2 { color: #4fc3f7; margin-bottom: 15px; margin-top: 0; }
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
        <div id="response-box" class="response-box"></div>
        <script>
            const rawMarkdown = <?php echo json_encode($response); ?>;
            document.getElementById('response-box').innerHTML = marked.parse(rawMarkdown);
        </script>
    <?php endif; ?>

    <?php if (!empty($status)): ?>
        <div class="status-panel">
            <h2>System Status</h2>
            <div class="status-item"><strong>Last Topic:</strong> <?php echo htmlspecialchars($status['last_topic'] ?? ''); ?></div>
            <div class="status-item"><strong>Last Entities:</strong> <?php echo htmlspecialchars(implode(", ", (array)($status['last_entities'] ?? []))); ?></div>
            <div class="status-item"><strong>Intent:</strong> <?php echo htmlspecialchars($status['last_intent'] ?? ''); ?></div>
            <div class="status-item"><strong>Question Type:</strong> <?php echo htmlspecialchars($status['question_type'] ?? ''); ?></div>
            <div class="status-item"><strong>Response Time:</strong> <?php echo number_format($status['response_time'], 4); ?> sec</div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
