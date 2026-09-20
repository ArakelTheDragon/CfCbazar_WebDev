<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CfCbazar AI Portal - Select Environment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/css/styles.css">
<style>
    body {
        background: #121212;
        color: #e0e0e0;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    .portal-container {
        width: 95%;
        max-width: 960px;
        background: #1e1e1e;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7);
        border: 1px solid #2a2a2a;
    }
    h1 {
        text-align: center;
        color: #4fc3f7;
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 28px;
    }
    .subtitle {
        text-align: center;
        color: #b0bec5;
        margin-bottom: 30px;
        font-size: 15px;
    }
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    .card {
        background: #0f0f0f;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .card:hover {
        transform: translateY(-4px);
        border-color: #4fc3f7;
    }
    .card h2 {
        color: #81d4fa;
        font-size: 18px;
        margin-top: 0;
        margin-bottom: 10px;
    }
    .card p {
        color: #aaaaaa;
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 20px;
        flex-grow: 1;
    }
    .btn {
        display: block;
        text-align: center;
        background: #4fc3f7;
        color: #000000;
        text-decoration: none;
        padding: 12px;
        border-radius: 6px;
        font-weight: bold;
        font-size: 15px;
        transition: background 0.2s ease;
    }
    .btn:hover {
        background: #81d4fa;
    }
    .footer-note {
        text-align: center;
        margin-top: 25px;
        font-size: 13px;
        color: #666666;
    }
</style>
</head>
<body>

<div class="portal-container">
    <h1>CfCbazar AI Gateway</h1>
    <p class="subtitle">Select the AI runtime environment or system configuration you wish to launch:</p>

    <div class="cards-grid">
        <!-- Option 1 -->
        <div class="card">
            <div>
                <h2>AI System Core</h2>
                <p>Latest offline standalone PHP AI engine with OpenRouter self-learning and two-tier memory indexing.</p>
            </div>
            <a href="https://cfcbazar.42web.io/diy/ai-system/" class="btn">Launch AI System</a>
        </div>

        <!-- Option 2 -->
        <div class="card">
            <div>
                <h2>Modular AI v1</h2>
                <p>Advanced multi-agent framework integration and specialized skill pipeline.</p>
            </div>
            <a href="https://cfcbazar.42web.io/diy/ai/index_ai.php" class="btn">Launch AI v1</a>
        </div>

        <!-- Option 3 -->
        <div class="card">
            <div>
                <h2>Standalone AI v2</h2>
                <p>OpenRouter API implementation AI with direct remote handling.</p>
            </div>
            <a href="https://cfcbazar.42web.io/diy/ai2/index.php" class="btn">Launch AI v2</a>
        </div>

        <!-- Option 4 -->
        <div class="card">
            <div>
                <h2>Standalone AI v4</h2>
                <p>Dynamic local AI system interface and web automation hub.</p>
            </div>
            <a href="https://cfcbazar.42web.io/diy/ai4/index.php" class="btn">Launch AI v4</a>
        </div>
    </div>

    <div class="footer-note">
        CfCbazar Runtime Host &bull; cfcbazar.42web.io
    </div>
</div>

</body>
</html>
