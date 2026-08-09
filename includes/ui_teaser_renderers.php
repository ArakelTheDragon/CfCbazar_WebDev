<?php
/**
 * CfCbazar UI Teaser & Trade Card Helper Library
 * File: /includes/ui_teaser_renderers.php
 *
 * Renders reusable link cards and external DEX callout components.
 */

declare(strict_types=1);

if (!function_exists('render_withdraw_link')) {
    function render_withdraw_link(): void {
        echo '<a href="/w.php" class="link-card" aria-label="Withdraw WorkTokens/WorkTHR">💸 <span>Withdraw WorkTokens/WorkTHR</span></a>';
    }
}

if (!function_exists('render_workthr_teaser')) {
    function render_workthr_teaser(): void {
        echo '<a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=BNB" class="link-card" target="_blank" rel="noopener noreferrer">🥞 <span>Trade WorkTHR on PancakeSwap</span></a>';
    }
}

if (!function_exists('render_worktoken_teaser')) {
    function render_worktoken_teaser(): void {
        echo '<a href="https://cc.free.bg/workth/" class="link-card" aria-label="Trade WorkTokens on our DApp">🧠 <span>Trade WorkTokens on our DApp</span></a>';
    }
}

if (!function_exists('rfTradeWorkTokens')) {
    function rfTradeWorkTokens(): void {
        echo <<<HTML
        <section class="card">
            <h2>Trade WorkTokens</h2>
            <p>Trade WTK and WorkTHR using PancakeSwap.</p>
            <p>
                <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener noreferrer">
                    Open PancakeSwap
                </a>
            </p>
        </section>
        HTML;
    }
}
