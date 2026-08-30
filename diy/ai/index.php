<?php
// -----------------------------
// Simple Web-Based AI Agent (OpenRouter)
// -----------------------------

function call_openrouter($messages) {
    $url = "https://openrouter.ai/api/v1/chat/completions";

    $payload = [
        "model" => "openai/gpt-4o-mini",
        "messages" => $messages
    ];

    $headers = [
        "Authorization: Bearer YOUR_API_KEY_HERE",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// -----------------------------
// Session-based message history
// -----------------------------
session_start();

if (!isset($_SESSION["messages"])) {
    $_SESSION["messages"] = [
        ["role" => "system", "content" => "You are an AI agent. Respond clearly and helpfully."]
    ];
}

$ai_reply = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim($_POST["message"]);

    if ($user !== "") {
        $_SESSION["messages"][] = ["role" => "user", "content" => $user];

        $response = call_openrouter($_SESSION["messages"]);
        $ai_reply = $response["choices"][0]["message"]["content"];

        $_SESSION["messages"][] = ["role" => "assistant", "content" => $ai_reply];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CfCbazar AI Agent</title>

<style>
    body {
        font-family: "Inter", Arial, sans-serif;
        background: linear-gradient(135deg, #eef2f3, #dfe9f3);
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        min-height: 100vh;
    }

    .container {
        width: 100%;
        max-width: 900px;
        margin: 20px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        padding: 25px;
        border-radius: 18px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        display: flex;
        flex-direction: column;
        height: 90vh;
    }

    h2 {
        text-align: center;
        margin-bottom: 15px;
        font-size: 26px;
        font-weight: 700;
        color: #333;
    }

    .chat-box {
        flex: 1;
        background: #ffffff;
        padding: 20px;
        border-radius: 14px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
        margin-bottom: 15px;
        box-shadow: inset 0 0 8px rgba(0,0,0,0.05);
    }

    .msg-user, .msg-ai {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 12px;
        max-width: 85%;
        line-height: 1.5;
        font-size: 15px;
    }

    .msg-user {
        background: #d9f1ff;
        align-self: flex-end;
        border: 1px solid #b8e4ff;
    }

    .msg-ai {
        background: #f1f1f1;
        border: 1px solid #e0e0e0;
        align-self: flex-start;
    }

    form {
        display: flex;
        gap: 10px;
        padding-top: 10px;
    }

    input[type="text"] {
        flex: 1;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid #ccc;
        font-size: 16px;
        background: #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    button {
        padding: 14px 22px;
        background: #0078ff;
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        transition: 0.2s;
    }

    button:hover {
        background: #005fcc;
        transform: translateY(-2px);
    }

    @media (max-width: 600px) {
        .container {
            height: 95vh;
            padding: 15px;
        }

        .chat-box {
            padding: 15px;
        }

        input[type="text"] {
            font-size: 15px;
        }

        button {
            font-size: 15px;
            padding: 12px 18px;
        }
    }
</style>

</head>
<body>

<div class="container">
    <h2>CfCbazar AI Agent</h2>

    <div class="chat-box">
        <?php
        foreach ($_SESSION["messages"] as $msg) {
            if ($msg["role"] === "user") {
                echo "<div class='msg-user'><strong>You:</strong> " . htmlspecialchars($msg["content"]) . "</div>";
            } elseif ($msg["role"] === "assistant") {
                echo "<div class='msg-ai'><strong>AI:</strong> " . nl2br(htmlspecialchars($msg["content"])) . "</div>";
            }
        }
        ?>
    </div>

    <form method="POST">
        <input type="text" name="message" placeholder="Type your message..." required>
        <button type="submit">Send</button>
    </form>
</div>

</body>
</html>