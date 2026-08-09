<?php
/**
 * CfCbazar Token & UI Helper Library
 * File: /includes/render_worktoken_dashboard.php
 *
 * Renders the token dashboard UI for WorkToken (WTK) and WorkTHR (WTHR),
 * including token contract details, trading links, and dynamic MetaMask Web3 integration handlers.
 */

declare(strict_types=1);

if (!function_exists('render_worktoken_dashboard')) {
    /**
     * Renders the WorkToken and WorkTHR dashboard cards and MetaMask connection scripts.
     *
     * @return void
     */
    function render_worktoken_dashboard(): void
    {
        ?>
<section class="token-dashboard">
  <div class="token-card">
    <img src="/images/worktoken-logo.png" alt="WorkToken Logo" style="width:120px; height:auto;">
    <h2>WorkToken (WTK)</h2>
    <p>WorkToken is CfCbazar’s dynamic utility token...</p>
    <ul>
      <li><strong>Contract:</strong> <a href="https://bscscan.com/token/0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank">0xecbD4E86EE8583c8681E2eE2644FC778848B237D</a></li>
      <li><strong>Decimals:</strong> 18</li>
      <li><strong>Trading:</strong> <a href="https://cc.free.bg/workth/" target="_blank">CfCbazar dapp</a></li>
    </ul>
    <button onclick="addTokenWTK()">Add WTK to MetaMask</button>
    <script>
      async function addTokenWTK() {
        try {
          await ethereum.request({
            method: 'wallet_watchAsset',
            params: {
              type: 'ERC20',
              options: {
                address: '0xecbD4E86EE8583c8681E2eE2644FC778848B237D',
                symbol: 'WTK',
                decimals: 18,
                image: 'https://cfcbazar.42web.io/images/worktoken-logo.png',
              },
            },
          });
        } catch (error) {
          console.error('MetaMask WTK integration failed:', error);
        }
      }
    </script>
  </div>

  <div class="token-card">
    <img src="/images/workthr-logo.png" alt="WorkTHR Logo" style="width:120px; height:auto;">
    <h2>WorkTHR (WTHR)</h2>
    <p>WorkTHR is CfCbazar’s fixed-supply token...</p>
    <ul>
      <li><strong>Contract:</strong> <a href="https://bscscan.com/token/0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank">0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</a></li>
      <li><strong>Decimals:</strong> 18</li>
      <li><strong>Total Supply:</strong> 999,999,999 WTHR</li>
      <li><strong>Trading:</strong> <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=BNB" target="_blank">PancakeSwap</a></li>
    </ul>
    <button onclick="addTokenWTHR()">Add WTHR to MetaMask</button>
    <script>
      async function addTokenWTHR() {
        try {
          await ethereum.request({
            method: 'wallet_watchAsset',
            params: {
              type: 'ERC20',
              options: {
                address: '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00',
                symbol: 'WorkTHR',
                decimals: 18,
                image: 'https://cfcbazar.42web.io/images/workthr-logo.png',
              },
            },
          });
        } catch (error) {
          console.error('MetaMask WTHR integration failed:', error);
        }
      }
    </script>
  </div>
</section>
        <?php
    }
}
