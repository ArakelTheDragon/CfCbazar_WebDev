<?php
// /diy/survival/business.php — Public Business Calculator with CSV Export & Visit Tracking

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;
}


// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
  $upd->bind_param('s', $path);
  $upd->execute();

  if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'business' : $slug;
    $title = 'Business Profit Calculator';

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
  <title>🏢 Business Profit Calculator | CfCbazar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="description" content="Calculate product margins, net/gross revenue, profit, and quantity with CfCbazar's Business Calculator. Export reports to CSV." />
  <meta name="keywords" content="business calculator, profit margin, gross revenue, net cost, product budget, CSV export, CfCbazar" />
  <meta name="author" content="CfCbazar" />
  <meta name="robots" content="index, follow" />

  <meta property="og:title" content="🏢 Business Profit Calculator | CfCbazar" />
  <meta property="og:description" content="Manage product margins, total profit, and export business data to CSV." />
  <meta property="og:type" content="website" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      padding: 40px 20px;
      max-width: 1000px;
      margin: auto;
      background: #fdfdfd;
      color: #333;
    }
    h1, h2, h3 { color: #2c3e50; }
    select, button, input {
      padding: 10px;
      margin: 4px 0;
      font-size: 0.95em;
      border-radius: 6px;
      border: 1px solid #ccc;
      box-sizing: border-box;
    }
    button {
      background: #2c3e50;
      color: #fff;
      cursor: pointer;
      border: none;
      padding: 10px 18px;
      font-weight: bold;
    }
    button:hover { background: #1a252f; }
    .btn-secondary { background: #16a085; }
    .btn-secondary:hover { background: #0e6655; }
    .btn-danger { background: #c0392b; padding: 6px 12px; }
    .btn-danger:hover { background: #962d22; }
    .btn-export { background: #27ae60; }
    .btn-export:hover { background: #1e8449; }

    .nav-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    .output {
      margin-top: 20px;
      padding: 20px;
      background: #f4f6f8;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    .table-responsive {
      overflow-x: auto;
      margin-top: 15px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 6px;
      overflow: hidden;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
      font-size: 0.9em;
    }
    th {
      background: #2c3e50;
      color: #fff;
    }
    td input {
      width: 100%;
      margin: 0;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    .summary-box {
      background: #ffffff;
      border-radius: 6px;
      padding: 12px 15px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border-left: 4px solid #2c3e50;
    }
    .summary-box strong {
      display: block;
      font-size: 0.85em;
      color: #7f8c8d;
      text-transform: uppercase;
      margin-bottom: 5px;
    }
    .summary-box .val {
      font-size: 1.25em;
      font-weight: bold;
      color: #2c3e50;
    }
    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 15px;
    }
    .limit-warning {
      color: #c0392b;
      font-size: 0.85em;
      font-weight: bold;
    }
  </style>
</head>
<body>

  <div class="nav-bar">
    <button onclick="window.location.href='/diy/survival/index.php'" class="btn-secondary">⬅️ Survival Budget Tool</button>
  </div>

  <h1>🏢 Business Profit Calculator</h1>
  <p>Track up to 20 products, calculate profit margins, and export report as CSV.</p>

  <div class="output">
    <div class="toolbar">
      <div>
        <button type="button" id="addBtn" onclick="addProductRow()">➕ Add Product</button>
        <button type="button" class="btn-export" onclick="exportToCSV()">📥 Export as CSV</button>
      </div>
      <span id="productCounter" class="limit-warning">Products: 0 / 20</span>
    </div>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th style="width: 25%;">Product Name</th>
            <th style="width: 15%;">Net (Cost/unit)</th>
            <th style="width: 15%;">Gross (Price/unit)</th>
            <th style="width: 12%;">Quantity</th>
            <th style="width: 15%;">Total Profit</th>
            <th style="width: 10%;">Action</th>
          </tr>
        </thead>
        <tbody id="productTableBody">
          </tbody>
      </table>
    </div>

    <div class="summary-grid">
      <div class="summary-box">
        <strong>Total Items Sold</strong>
        <div class="val" id="sumQty">0</div>
      </div>
      <div class="summary-box">
        <strong>Total Net Cost</strong>
        <div class="val" id="sumNet">$0.00</div>
      </div>
      <div class="summary-box">
        <strong>Total Gross Revenue</strong>
        <div class="val" id="sumGross">$0.00</div>
      </div>
      <div class="summary-box" style="border-left-color: #27ae60;">
        <strong>Total Net Profit</strong>
        <div class="val" id="sumProfit" style="color: #27ae60;">$0.00</div>
      </div>
    </div>
  </div>

  <script>
    const MAX_PRODUCTS = 20;
    let products = [];

    document.addEventListener('DOMContentLoaded', () => {
      restoreData();
      if (products.length === 0) {
        // Default initial rows
        addProductRow('Product 1', 10, 25, 5);
        addProductRow('Product 2', 5, 12, 10);
      } else {
        renderProducts();
      }
    });

    function addProductRow(name = '', net = 0, gross = 0, qty = 1) {
      if (products.length >= MAX_PRODUCTS) {
        alert('Maximum limit of 20 products reached.');
        return;
      }

      const id = Date.now() + Math.random().toString(36).substr(2, 4);
      products.push({
        id,
        name: name || `Product ${products.length + 1}`,
        net: parseFloat(net) || 0,
        gross: parseFloat(gross) || 0,
        qty: parseInt(qty) || 1
      });

      renderProducts();
    }

    function removeProductRow(id) {
      products = products.filter(p => p.id !== id);
      renderProducts();
    }

    function updateProduct(id, field, value) {
      const prod = products.find(p => p.id === id);
      if (prod) {
        if (field === 'name') {
          prod[field] = value;
        } else if (field === 'qty') {
          prod[field] = Math.max(0, parseInt(value) || 0);
        } else {
          prod[field] = Math.max(0, parseFloat(value) || 0);
        }
      }
      calculateTotals();
      saveData();
    }

    function renderProducts() {
      const tbody = document.getElementById('productTableBody');
      tbody.innerHTML = '';

      products.forEach((p, index) => {
        const unitProfit = p.gross - p.net;
        const totalProfit = unitProfit * p.qty;

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input type="text" value="${escapeHtml(p.name)}" oninput="updateProduct('${p.id}', 'name', this.value)" placeholder="Product Name" /></td>
          <td><input type="number" step="0.01" value="${p.net}" oninput="updateProduct('${p.id}', 'net', this.value)" /></td>
          <td><input type="number" step="0.01" value="${p.gross}" oninput="updateProduct('${p.id}', 'gross', this.value)" /></td>
          <td><input type="number" step="1" value="${p.qty}" oninput="updateProduct('${p.id}', 'qty', this.value)" /></td>
          <td><strong>$${totalProfit.toFixed(2)}</strong></td>
          <td><button class="btn-danger" onclick="removeProductRow('${p.id}')">✕</button></td>
        `;
        tbody.appendChild(tr);
      });

      document.getElementById('productCounter').innerText = `Products: ${products.length} / ${MAX_PRODUCTS}`;
      document.getElementById('addBtn').disabled = products.length >= MAX_PRODUCTS;

      calculateTotals();
      saveData();
    }

    function calculateTotals() {
      let totalQty = 0;
      let totalNet = 0;
      let totalGross = 0;
      let totalProfit = 0;

      products.forEach(p => {
        totalQty += p.qty;
        totalNet += (p.net * p.qty);
        totalGross += (p.gross * p.qty);
        totalProfit += ((p.gross - p.net) * p.qty);
      });

      document.getElementById('sumQty').innerText = totalQty;
      document.getElementById('sumNet').innerText = `$${totalNet.toFixed(2)}`;
      document.getElementById('sumGross').innerText = `$${totalGross.toFixed(2)}`;
      document.getElementById('sumProfit').innerText = `$${totalProfit.toFixed(2)}`;
    }

    function exportToCSV() {
      if (products.length === 0) {
        alert('No data available to export.');
        return;
      }

      let csv = 'Product Name,Net (Cost/Unit),Gross (Price/Unit),Quantity,Total Net Cost,Total Gross Revenue,Total Profit\n';

      products.forEach(p => {
        const totalNet = (p.net * p.qty).toFixed(2);
        const totalGross = (p.gross * p.qty).toFixed(2);
        const totalProfit = ((p.gross - p.net) * p.qty).toFixed(2);
        const name = `"${p.name.replace(/"/g, '""')}"`;

        csv += `${name},${p.net.toFixed(2)},${p.gross.toFixed(2)},${p.qty},${totalNet},${totalGross},${totalProfit}\n`;
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.setAttribute('download', `business_calc_export_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function saveData() {
      try {
        localStorage.setItem('cfcBuzinessCalc', JSON.stringify(products));
      } catch (e) {}
    }

    function restoreData() {
      try {
        const raw = localStorage.getItem('cfcBuzinessCalc');
        if (raw) products = JSON.parse(raw);
      } catch (e) {}
    }

    function escapeHtml(str) {
      return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
  </script>
</body>
</html>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/index.php",
    "Main Index Source Code"
);
?>
