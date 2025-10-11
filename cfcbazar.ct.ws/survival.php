<?php
// survival.php — Public Budget Tool with Visit Tracking and SEO

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php'; // defines $conn

// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
  $upd->bind_param('s', $path);
  $upd->execute();

  if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'index' : $slug;
    $title = 'Survival Budget Tool';

    $ins = $conn->prepare("
      INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
      VALUES (?, ?, ?, 1, NOW(), NOW())
    ");
    if ($ins) {
      $ins->bind_param('sss', $title, $slug, $path);
      $ins->execute();
      $ins->close();
    }
  }
  $upd->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>💸 Survival Budget Tool | CfCbazar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="description" content="Explore essential monthly expenses with CfCbazar's Survival Budget Tool. Calculate food, transport, hygiene, and more to manage your budget effectively." />
  <meta name="keywords" content="budget calculator, survival expenses, monthly costs, CfCbazar tools, food budget, transport cost, hygiene spending" />
  <meta name="author" content="CfCbazar" />
  <meta name="robots" content="index, follow" />

  <!-- Open Graph -->
  <meta property="og:title" content="💸 Survival Budget Tool | CfCbazar" />
  <meta property="og:description" content="Calculate your essential monthly expenses with CfCbazar's free Survival Budget Tool. No login required!" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://cfcbazar.ct.ws/survival.php" />
  <meta property="og:image" content="https://cfcbazar.ct.ws/assets/survival-preview.png" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="💸 Survival Budget Tool | CfCbazar" />
  <meta name="twitter:description" content="Explore and calculate your monthly survival costs with CfCbazar's free budgeting tool." />
  <meta name="twitter:image" content="https://cfcbazar.ct.ws/assets/survival-preview.png" />

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
    .output {
      margin-top: 20px; padding: 20px; background: #f4f6f8;
      border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    img { width: 100%; margin-top: 15px; border-radius: 6px; }
    .food-examples { background: #eef6ff; padding: 15px; border-radius: 6px; margin-top:20px; }
  </style>
</head>
<body>

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

  <button id="showBtn">Show Selection</button>

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
    const baseBudget = 600;
    const conv = { food:30, mobile:1, clothing:1/12, transport:1, hygiene:1 };

    document.getElementById('showBtn').addEventListener('click', showOptions);

    function showOptions() {
      const cat   = document.getElementById('category').value;
      const out   = document.getElementById('outputBox');
      const calc  = document.getElementById('calcContainer');
      out.innerHTML = '';
      calc.style.display = 'none';

      if (!cat) return;

      if (cat === 'calculator') {
        calc.style.display = 'block';
        calculateBudget();
        return;
      }

      let html = '', img = '', pct;
      switch(cat) {
        case 'food':
          html = `🍽 Food: ~$8/day → $240/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Food';
          pct  = ((240/baseBudget)*100).toFixed(1);
          break;
        case 'mobile':
          html = `📱 Mobile Plan: $40/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Mobile';
          pct  = ((40/baseBudget)*100).toFixed(1);
          break;
        case 'clothing':
          html = `👕 Clothing: $1,200/yr → $100/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Clothing';
          pct  = ((100/baseBudget)*100).toFixed(1);
          break;
        case 'transport':
          html = `🚇 Transport: $80/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Transport';
          pct  = ((80/baseBudget)*100).toFixed(1);
          break;
        case 'hygiene':
          html = `🧼 Hygiene: $30/mo`;
          img  = 'https://via.placeholder.com/600x300?text=Hygiene';
          pct  = ((30/baseBudget)*100).toFixed(1);
          break;
      }

      html += `<br><br><strong>${pct}% of $${baseBudget}</strong>`;
      const fixedCosts = { food:240, mobile:40, clothing:100, transport:80, hygiene:30 }[cat] || 0;
      html += `<div style="margin-top:20px;"><em>Leaves $${(baseBudget - fixedCosts).toFixed(2)} for misc.</em></div>`;

      out.innerHTML = html + (img ? `<img src="${img}" alt="${cat}">` : '');
    }

    function calculateBudget() {
      const form  = document.getElementById('calcForm');
      let total   = 0, details = '';

      for (let key in conv) {
        const val = parseFloat(form[key].value) || 0;
        const monthAmt = val * conv[key];
        total += monthAmt;
        const pct = ((monthAmt / baseBudget) * 100).toFixed(1);
        const label = key.charAt(0).toUpperCase() + key.slice(1);
        details += `<strong>${label}:</strong> $${monthAmt.toFixed(2)} (${pct}%)<br>`;
      }

      const pctTotal = ((total / baseBudget) * 100).toFixed(1);
      const remaining = (baseBudget - total).toFixed(2);

      document.getElementById('calcOutput').innerHTML = `
        <h3>📊 Monthly Spend</h3>
        ${details}
        <br><strong>Total: $${total.toFixed(2)} (${pctTotal}%)</strong><br>
        <strong>Remaining: $${remaining}</strong>
      `;
    }
  </script>
</body>
</html>