<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport"
        content="width=device-width,
                 initial-scale=1.0,
                 maximum-scale=1.0,
                 user-scalable=no" />
  <title>Survival Budget Tool — WorkToken Edition</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; padding: 40px 20px;
           max-width: 800px; margin: auto; background: #fdfdfd; color: #333; }
    h1, h2, h3 { color: #2c3e50; }
    select, button, input {
      padding: 10px; margin: 8px 0 12px; width: 100%;
      font-size: 1em; border-radius: 6px; border: 1px solid #ccc;
    }
    button { background: #2c3e50; color: #fff; cursor: pointer; }
    button:hover { background: #1a252f; }
    #balance { margin-bottom: 20px; font-size: 1.1rem; }
    .output {
      margin-top: 20px; padding: 20px; background: #f4f6f8;
      border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    img { width: 100%; margin-top: 15px; border-radius: 6px; }
    .food-examples { background: #eef6ff; padding: 15px; border-radius: 6px; margin-top:20px; }
  </style>
</head>
<body>

  <div id="balance">
    Your WorkToken Balance:
    <strong><span id="balance-value">Loading...</span></strong> WT
  </div>

  <h1>💸 Survival Budget Tool</h1>
  <p>Select a category to explore budget guidance:</p>

  <select id="category">
    <option value="" disabled selected>Choose category...</option>
    <option value="food">🥪 Food (Daily)</option>
    <option value="mobile">📱 Mobile Plan (Monthly)</option>
    <option value="clothing">👕 Clothing (Yearly)</option>
    <option value="transport">🚇 Transport (Monthly)</option>
    <option value="hygiene">🧼 Hygiene (Monthly)</option>
    <option value="calculator">🧮 Budget Calculator</option>
  </select>

  <button id="showBtn">Show Selection (0.01 WT)</button>

  <div class="output" id="outputBox"></div>

  <div id="calcContainer" class="output" style="display:none;">
    <h2>🧮 Budget Calculator</h2>
    <form id="calcForm">
      <div><label>🥪 Food (daily):</label><input type="number" name="food" /></div>
      <div><label>📱 Mobile (monthly):</label><input type="number" name="mobile" /></div>
      <div><label>👕 Clothing (yearly):</label><input type="number" name="clothing" /></div>
      <div><label>🚇 Transport (monthly):</label><input type="number" name="transport" /></div>
      <div><label>🧼 Hygiene (monthly):</label><input type="number" name="hygiene" /></div>
    </form>
    <button type="button" onclick="calculateBudget()">Calculate Usage</button>
    <div id="calcOutput" style="margin-top:20px;"></div>
  </div>

  <script>
    const email     = "<?= $email ?>";
    const workerUrl = window.location.origin + '/worker.php';
    let balance     = 0;

    const baseBudget = 600;  // USD/month

    // conversion to monthly
    const conv = { food:30, mobile:1, clothing:1/12, transport:1, hygiene:1 };

    // load balance
    async function fetchBalance() {
      try {
        let res  = await fetch(`${workerUrl}?email=${encodeURIComponent(email)}`);
        if (!res.ok) throw new Error(res.statusText);
        let data = await res.json();
        balance = parseFloat(data.tokens_earned)||0;
        document.getElementById('balance-value').textContent = balance.toFixed(5);
      } catch(e) {
        console.error(e);
        document.getElementById('balance-value').textContent = 'Error';
      }
    }

    // update balance
    async function updateBalance(delta) {
      try {
        await fetch(workerUrl, {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`email=${encodeURIComponent(email)}&tokens=${delta}`
        });
      } catch(e){ console.error(e); }
    }

    // show guidance or calculator
    async function showOptions() {
      if (balance < 0.01) { alert('Not enough WT.'); return; }
      balance -= 0.01;
      document.getElementById('balance-value').textContent = balance.toFixed(5);
      await updateBalance(-0.01);

      const cat = document.getElementById('category').value;
      const out = document.getElementById('outputBox');
      const calc= document.getElementById('calcContainer');
      out.innerHTML = ''; calc.style.display='none';

      if (cat==='calculator') {
        calc.style.display = 'block';
        calculateBudget();
        return;
      }

      let html='', img='', pct;
      switch(cat) {
        case 'food':
          html = `🍽 Food: ~$8/day → $240/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Food';
          pct  = ((240/baseBudget)*100).toFixed(1);
          html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
          break;
        case 'mobile':
          html = `📱 Mobile Plan: $40/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Mobile';
          pct  = ((40/baseBudget)*100).toFixed(1);
          html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
          break;
        case 'clothing':
          html = `👕 Clothing: $1 200/yr → $100/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Clothing';
          pct  = ((100/baseBudget)*100).toFixed(1);
          html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
          break;
        case 'transport':
          html = `🚇 Transport: $80/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Transport';
          pct  = ((80/baseBudget)*100).toFixed(1);
          html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
          break;
        case 'hygiene':
          html = `🧼 Hygiene: $30/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Hygiene';
          pct  = ((30/baseBudget)*100).toFixed(1);
          html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
          break;
        default:
          html = 'Please select a category.';
      }

      html += `<div style="margin-top:20px;"><em>Leaves $${(baseBudget - (cat==='calculator'?0:(
                {food:240,mobile:40,clothing:100,transport:80,hygiene:30}[cat]||0
              ))).toFixed(2)} for misc.</em></div>`;

      out.innerHTML = html + (img?`<img src="${img}" alt="${cat}">`:``);
    }

    // calculator
    function calculateBudget() {
      const form = document.getElementById('calcForm');
      let total=0, br='';
      for (let k in conv) {
        const v = parseFloat(form[k].value)||0;
        const m = v*conv[k];
        total+=m;
        const p = (m/baseBudget*100).toFixed(1);
        br+= `<strong>${k.charAt(0).toUpperCase()+k.slice(1)}:</strong> $${m.toFixed(2)} (${p}%)<br>`;
      }
      const rem = baseBudget-total;
      const tp  = (total/baseBudget*100).toFixed(1);
      document.getElementById('calcOutput').innerHTML = `
        <h3>📊 Monthly Spend</h3>${br}
        <br><strong>Total: $${total.toFixed(2)} (${tp}%)</strong><br>
        <strong>Remaining: $${rem.toFixed(2)}</strong>`;
    }

    document.getElementById('showBtn').addEventListener('click', showOptions);
    window.addEventListener('load', fetchBalance);
  </script>
</body>
</html>
