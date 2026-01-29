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
    body {
      font-family: 'Segoe UI', sans-serif;
      padding: 40px 20px;
      max-width: 900px;
      margin: auto;
      background: #fdfdfd;
      color: #333;
    }
    h1, h2, h3 { color: #2c3e50; }
    select, button, input {
      padding: 10px;
      margin: 8px 0 12px;
      width: 100%;
      font-size: 1em;
      border-radius: 6px;
      border: 1px solid #ccc;
      box-sizing: border-box;
    }
    button {
      background: #2c3e50;
      color: #fff;
      cursor: pointer;
      border: none;
    }
    button:hover { background: #1a252f; }
    .output {
      margin-top: 20px;
      padding: 20px;
      background: #f4f6f8;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px 20px;
    }
    .field label {
      display: block;
      font-size: 0.9em;
      margin-bottom: 4px;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px 20px;
      margin-top: 10px;
    }
    .summary-box {
      background: #ffffff;
      border-radius: 6px;
      padding: 10px 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      font-size: 0.95em;
    }
    .summary-box strong {
      display: block;
      margin-bottom: 4px;
    }
    .bar-row {
      margin-top: 8px;
      font-size: 0.9em;
    }
    .bar-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 3px;
    }
    .bar {
      width: 100%;
      height: 8px;
      background: #dde3ea;
      border-radius: 4px;
      overflow: hidden;
    }
    .bar-fill {
      height: 100%;
      border-radius: 4px;
      transition: width 0.3s ease;
    }
  </style>
</head>
<body>

  <h1>💸 Survival Budget Tool</h1>
  <p>Select a category to explore budget guidance:</p>

  <select id="category">
    <option value="" disabled selected>Choose category...</option>
    <option value="calculator">🧮 Budget Calculator</option>
  </select>

  <button id="showBtn">Show Selection</button>

  <div class="output" id="outputBox"></div>

  <div id="calcContainer" class="output" style="display:none;">
    <h2>🧮 Budget Calculator</h2>

    <form id="calcForm">
      <div class="field">
        <label>💼 Income (monthly):</label>
        <input type="number" name="income" />
      </div>

      <div class="grid">
        <div class="field">
          <label>🥪 Food (daily):</label>
          <input type="number" name="food" />
        </div>
        <div class="field">
          <label>📱 Mobile (monthly):</label>
          <input type="number" name="mobile" />
        </div>
        <div class="field">
          <label>👕 Clothing (yearly):</label>
          <input type="number" name="clothing" />
        </div>
        <div class="field">
          <label>🚇 Transport (monthly):</label>
          <input type="number" name="transport" />
        </div>
        <div class="field">
          <label>🧼 Hygiene (monthly):</label>
          <input type="number" name="hygiene" />
        </div>
        <div class="field">
          <label>🏠 Rent / Mortgage (monthly):</label>
          <input type="number" name="rent" />
        </div>
        <div class="field">
          <label>💡 Utilities (monthly):</label>
          <input type="number" name="utilities" />
        </div>
        <div class="field">
          <label>💰 Savings (monthly):</label>
          <input type="number" name="savings" />
        </div>
        <div class="field">
          <label>📺 Subscriptions (monthly):</label>
          <input type="number" name="subscriptions" />
        </div>
        <div class="field">
          <label>🧩 Other (monthly):</label>
          <input type="number" name="other" />
        </div>
      </div>
    </form>

    <button type="button" onclick="calculateBudget()">Calculate Usage</button>
    <div id="calcOutput" style="margin-top:20px;"></div>
  </div>

  <script>
    const baseBudget = 600; // reference survival baseline

    const conv = {
      food:30,       // daily → monthly
      mobile:1,
      clothing:1/12, // yearly → monthly
      transport:1,
      hygiene:1,
      rent:1,
      utilities:1,
      savings:1,
      subscriptions:1,
      other:1
    };

    const colors = {
      food:'#27ae60',
      mobile:'#8e44ad',
      clothing:'#d35400',
      transport:'#2980b9',
      hygiene:'#16a085',
      rent:'#2c3e50',
      utilities:'#f1c40f',
      savings:'#27ae60',
      subscriptions:'#e67e22',
      other:'#7f8c8d'
    };

    document.getElementById('showBtn').addEventListener('click', showOptions);

    document.addEventListener('DOMContentLoaded', restoreFormFromStorage);

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
    }

    function calculateBudget() {
      const form  = document.getElementById('calcForm');
      const income = parseFloat(form.income.value) || 0;

      let totalMonthly = 0;
      let details = '';

      for (let key in conv) {
        const val = parseFloat(form[key].value) || 0;
        const monthAmt = val * conv[key];
        totalMonthly += monthAmt;

        const pctBase = ((monthAmt / baseBudget) * 100) || 0;
        const label = key.charAt(0).toUpperCase() + key.slice(1);

        const color = colors[key] || '#3498db';
        const width = Math.max(0, Math.min(pctBase, 100));

        details += `
          <div class="bar-row">
            <div class="bar-label">
              <span><strong>${label}:</strong> $${monthAmt.toFixed(2)}</span>
              <span>${pctBase.toFixed(1)}% of $${baseBudget}</span>
            </div>
            <div class="bar">
              <div class="bar-fill" style="width:${width}%;background:${color};"></div>
            </div>
          </div>
        `;
      }

      const daily   = totalMonthly / 30;
      const weekly  = totalMonthly / 4.345;
      const yearly  = totalMonthly * 12;

      const pctOfBase   = ((totalMonthly / baseBudget) * 100).toFixed(1);
      const remainingBase = (baseBudget - totalMonthly).toFixed(2);

      let incomeBlock = '';
      if (income > 0) {
        const pctOfIncome = ((totalMonthly / income) * 100).toFixed(1);
        const remainingIncome = (income - totalMonthly).toFixed(2);
        incomeBlock = `
          <div class="summary-box">
            <strong>Income vs Expenses</strong>
            Income: $${income.toFixed(2)}<br>
            Used: $${totalMonthly.toFixed(2)} (${pctOfIncome}% of income)<br>
            Remaining: $${remainingIncome}
          </div>
        `;
      }

      document.getElementById('calcOutput').innerHTML = `
        <h3>📊 Spending Summary</h3>

        <div class="summary-grid">
          <div class="summary-box">
            <strong>Daily Spend</strong>
            $${daily.toFixed(2)}
          </div>
          <div class="summary-box">
            <strong>Weekly Spend</strong>
            $${weekly.toFixed(2)}
          </div>
          <div class="summary-box">
            <strong>Monthly Spend</strong>
            $${totalMonthly.toFixed(2)} (${pctOfBase}% of $${baseBudget})
          </div>
          <div class="summary-box">
            <strong>Yearly Spend</strong>
            $${yearly.toFixed(2)}
          </div>
          <div class="summary-box">
            <strong>Remaining vs Base Budget</strong>
            From $${baseBudget}/mo: $${remainingBase}
          </div>
          ${incomeBlock}
        </div>

        <hr style="margin:18px 0; border:none; border-top:1px solid #d0d6dd;">

        ${details}
      `;

      saveFormToStorage();
    }

    function saveFormToStorage() {
      const form = document.getElementById('calcForm');
      const data = {};
      Array.from(form.elements).forEach(el => {
        if (el.name) data[el.name] = el.value;
      });
      try {
        localStorage.setItem('survivalBudgetForm', JSON.stringify(data));
      } catch (e) {
        // ignore storage errors offline / private mode
      }
    }

    function restoreFormFromStorage() {
      let raw = null;
      try {
        raw = localStorage.getItem('survivalBudgetForm');
      } catch (e) {
        raw = null;
      }
      if (!raw) return;

      try {
        const data = JSON.parse(raw);
        const form = document.getElementById('calcForm');
        Object.keys(data).forEach(name => {
          if (form[name]) form[name].value = data[name];
        });
      } catch (e) {
        // ignore parse errors
      }
    }
  </script>
</body>
</html>