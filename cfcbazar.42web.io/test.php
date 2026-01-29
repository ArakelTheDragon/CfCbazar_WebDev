<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Token Price Tracker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      padding: 40px;
      text-align: center;
    }
    h1 {
      color: #333;
    }
    .price-box {
      margin-top: 20px;
      font-size: 1.5em;
      font-weight: bold;
      color: #28a745;
    }
    .error {
      color: #cc0000;
    }
  </style>
</head>
<body>
  <h1>📈 Token Price Tracker</h1>
  <div id="workthr-price" class="price-box">Loading WorkTHR → USDT...</div>
  <div id="wtk-price" class="price-box">Loading WTK → WorkTHR...</div>

  <script type="module">
    async function fetchTokenPrice(path, label, symbol, targetSymbol) {
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
        document.getElementById(label).textContent = `1 ${symbol} ≈ ${price} ${targetSymbol}`;
      } catch (err) {
        console.error(`${label} fetch error:`, err);
        const el = document.getElementById(label);
        el.textContent = `Error fetching ${symbol} price`;
        el.classList.add('error');
      }
    }

    // WorkTHR → USDT
    fetchTokenPrice(
      ['0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00', '0x55d398326f99059fF775485246999027B3197955'],
      'workthr-price',
      'WorkTHR',
      'USDT'
    );

    // WTK → WorkTHR
    fetchTokenPrice(
      ['0xecbD4E86EE8583c8681E2eE2644FC778848B237D', '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00'],
      'wtk-price',
      'WTK',
      'WorkTHR'
    );

    setInterval(() => {
      fetchTokenPrice(
        ['0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00', '0x55d398326f99059fF775485246999027B3197955'],
        'workthr-price',
        'WorkTHR',
        'USDT'
      );
      fetchTokenPrice(
        ['0xecbD4E86EE8583c8681E2eE2644FC778848B237D', '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00'],
        'wtk-price',
        'WTK',
        'WorkTHR'
      );
    }, 30000);
  </script>
</body>
</html>