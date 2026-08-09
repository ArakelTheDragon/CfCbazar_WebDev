<?php
/**
 * CfCbazar Crypto & Price Tracker Helper Library
 * File: /includes/render_token_price_tracker.php
 *
 * Renders a live PancakeSwap V2 price tracker widget using ethers.js to fetch
 * real-time exchange rates for WorkTHR/USDT and WTK/WorkTHR pairs directly from BSC.
 */

declare(strict_types=1);

if (!function_exists('render_token_price_tracker')) {
    /**
     * Renders the token price tracker container CSS, HTML, and client-side JS module.
     *
     * @return void
     */
    function render_token_price_tracker(): void
    {
        echo <<<'HTML'
  <style>
    .token-tracker-container {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      padding: 20px;
      text-align: center;
      max-width: 600px;
      margin: 0 auto;
    }
    .token-tracker-container h2 {
      color: #333;
      font-size: 1.6em;
      margin-bottom: 20px;
    }
    .price-box {
      margin: 10px auto;
      padding: 16px;
      font-size: 1.2em;
      font-weight: bold;
      color: #28a745;
      background: #fff;
      border: 2px solid #28a745;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      word-wrap: break-word;
      overflow-wrap: break-word;
      max-width: 90%;
      transition: box-shadow 0.3s ease;
    }
    .price-box:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .price-box.error {
      color: #cc0000;
      border-color: #cc0000;
    }
    @media screen and (max-width: 480px) {
      .token-tracker-container h2 {
        font-size: 1.3em;
      }
      .price-box {
        font-size: 1em;
        padding: 12px;
      }
    }
  </style>

  <div class="token-tracker-container">
    <h2>📈 Live Token Prices</h2>
    <div id="workthr-price" class="price-box">Loading WorkTHR → USDT...</div>
    <div id="wtk-price" class="price-box">Loading WTK → WorkTHR...</div>
  </div>

  <script type="module">
    async function trackTokenPrice(path, labelId, symbol, targetSymbol) {
      try {
        const { ethers } = await import('https://cdn.jsdelivr.net/npm/ethers@6.8.0/+esm');
        const provider = new ethers.JsonRpcProvider('https://bsc-dataseed.binance.org/');
        const router = new ethers.Contract(
          '0x10ED43C718714eb63d5aA57B78B54704E256024E',
          ['function getAmountsOut(uint amountIn, address[] calldata path) external view returns (uint[] memory amounts)'],
          provider
        );
        const inputAmount = ethers.parseUnits('1', 18);
        const amounts = await router.getAmountsOut(inputAmount, path);
        const price = ethers.formatUnits(amounts[amounts.length - 1], 18);
        const el = document.getElementById(labelId);
        el.textContent = `1 ${symbol} ≈ ${price} ${targetSymbol}`;
        el.classList.remove('error');
      } catch (err) {
        console.error(`${symbol} price fetch error:`, err);
        const el = document.getElementById(labelId);
        el.textContent = `Error fetching ${symbol} price`;
        el.classList.add('error');
      }
    }

    function refreshPrices() {
      trackTokenPrice(
        ['0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00', '0x55d398326f99059fF775485246999027B3197955'],
        'workthr-price',
        'WorkTHR',
        'USDT'
      );
      trackTokenPrice(
        ['0xecbD4E86EE8583c8681E2eE2644FC778848B237D', '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00'],
        'wtk-price',
        'WTK',
        'WorkTHR'
      );
    }

    refreshPrices();
    setInterval(refreshPrices, 86400000);
  </script>
HTML;
    }
}
