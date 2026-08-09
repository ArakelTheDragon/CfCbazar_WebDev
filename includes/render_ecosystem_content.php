<?php
/**
 * CfCbazar UI Helper Library
 * File: /includes/render_ecosystem_content.php
 *
 * Renders the ecosystem section displaying token conversion mechanics, 
 * credit flow instructions, token chart canvas, and deposit QR code generator.
 */

declare(strict_types=1);

if (!function_exists('render_ecosystem_content')) {
    /**
     * Outputs HTML section for the blockchain token ecosystem, credit conversion
     * rules, and deposit instructions with dynamic QR canvas.
     *
     * @return void
     */
    function render_ecosystem_content(): void
    {
        ?>
        <main class="ecosystem-section">
            <h1>💠 CfCbazar Blockchain Token Ecosystem & Credit Flow</h1>

            <div class="chart-container">
                <canvas id="tokenChart"></canvas>
            </div>

            <section class="ecosystem-flow">
                <h2>🔁 How CfCbazar Converts Blockchain Tokens into Platform Credits</h2>
                <ul>
                    <li><strong>Blockchain Reserve:</strong> Backs all platform credits</li>
                    <li><strong>Platform Credits:</strong> Used for games, features, and withdrawals</li>
                    <li><strong>To Get Credits:</strong> Send WorkTokens or BNB to <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
                    <li><strong>On Withdraw:</strong> You receive WorkTokens from <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
                </ul>
                <p>Learn more about <a href="/worktoken/index.php">WorkToken mechanics</a> or explore <a href="/games/index.php">CfCbazar games</a>.</p>
            </section>

            <div class="deposit-instructions">
                <canvas id="qr-canvas" data-qr-value="0xFBd767f6454bCd07c959da2E48fD429531A1323A"></canvas>
                <button onclick="downloadQR()">Download QR Code</button>
            </div>
        </main>
        <?php
    }
}
