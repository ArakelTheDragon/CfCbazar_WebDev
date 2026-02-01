<?php
// power.php � Power Consumption Calculator with WorkToken Fee + Prefill
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include(__DIR__ . '/../config.php');

// 1. Require login
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
$email = $_SESSION['email'];

// 2. Check WorkToken balance
$stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($tokens);
$stmt->fetch();
$stmt->close();

$requiredTokens = 0.01;
if ($tokens < $requiredTokens) {
    echo "<h2 style='color:red; text-align:center;'>
            Insufficient WorkToken balance. You need at least {$requiredTokens} to use this tool.
          </h2>";
    exit();
}

// 3. Deduct WorkToken fee
$deduct = $conn->prepare("
    UPDATE workers 
       SET tokens_earned = tokens_earned - ? 
     WHERE email = ?
");
$deduct->bind_param("ds", $requiredTokens, $email);
$deduct->execute();
$deduct->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title> Power Consumption Calculator</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 20px; max-width: 1000px;
      line-height: 1.6; background: #fdfdfd; color: #333;
    }
    h1, h2 { color: #2c3e50; text-align: center; }
    .container { margin: 20px 0; }
    label { font-weight: bold; margin-right: 5px; }
    input[type="number"], select {
      padding: 8px; margin-right: 10px;
      font-size: 1em; border: 1px solid #ccc; border-radius: 4px;
    }
    table {
      width: 100%; border-collapse: collapse; margin-top: 10px;
    }
    th, td {
      padding: 8px; text-align: left; border-bottom: 1px solid #ddd;
    }
    .button-container { margin-top: 20px; text-align: center; }
    button {
      padding: 10px 20px; font-size: 1em;
      background-color: #2c3e50; color: white;
      border: none; border-radius: 4px; cursor: pointer;
    }
    button:hover { background-color: #1a252f; }
    .output {
      margin-top: 30px; background: #f4f6f8;
      padding: 20px; border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    .error-console {
      margin-top: 20px; background: #ffe6e6;
      padding: 10px; border: 1px solid #cc0000;
      border-radius: 4px; color: #cc0000; font-family: monospace;
    }
    @media(max-width: 600px) {
      input[type="number"], select {
        width: 100%; margin-bottom: 8px;
      }
    }
  </style>
  <script>
    // Default rows to prefill
    const defaultRows = [
      {device:'LED Bulb',       power:10,   qty:4,  hours:5},
      {device:'Refrigerator',    power:150,  qty:1,  hours:24},
      {device:'Air Conditioner', power:1000, qty:1,  hours:6},
      {device:'Computer',       power:200,  qty:1,  hours:8},
      {device:'Laptop',         power:50,   qty:1,  hours:8},
      {device:'Microwave',      power:800,  qty:1,  hours:0.5},
      {device:'Fan',            power:75,   qty:1,  hours:8},
      {device:'Other',          power:100,  qty:1,  hours:1}
    ];

    window.onload = () => {
      // Prefill the table
      document.querySelectorAll("table tbody tr").forEach((row, i) => {
        const def = defaultRows[i];
        if (!def) return;
        const device = row.querySelector(".device");
        const power  = row.querySelector(".power");
        const qty    = row.querySelector(".quantity");
        const hrs    = row.querySelector(".hours");
        if (device) device.value = def.device;
        if (power)  power.value  = def.power;
        if (qty)    qty.value    = def.qty;
        if (hrs)    hrs.value    = def.hours;
      });
    };

    function calculateConsumption() {
      const errorConsole = document.getElementById("errorConsole");
      errorConsole.style.display = "none";
      errorConsole.innerHTML = "";

      try {
        const pricePerKwh = parseFloat(document.getElementById("price").value);
        if (isNaN(pricePerKwh) || pricePerKwh <= 0) {
          throw new Error("Please enter a valid positive price per kWh.");
        }

        let totalMonthlyKwh = 0,
            totalMonthlyCost = 0,
            outputHtml = 
              "<h2>Results</h2>" +
              "<table style='width:100%;border-collapse:collapse;'>" +
              "<tr><th>Device</th><th>Monthly Consumption (kWh)</th><th>Monthly Cost</th></tr>";

        const rows = document.querySelectorAll("table tbody tr");
        rows.forEach((row, idx) => {
          const deviceField   = row.querySelector(".device");
          const powerField    = row.querySelector(".power");
          const qtyField      = row.querySelector(".quantity");
          const hoursField    = row.querySelector(".hours");
          if (!(deviceField && powerField && qtyField && hoursField)) return; // safety

          const device = deviceField.value;
          const power  = parseFloat(powerField.value);
          const qty    = parseFloat(qtyField.value);
          const hours  = parseFloat(hoursField.value);

          // Skip entirely empty row
          if (isNaN(power) && isNaN(qty) && isNaN(hours)) return;

          if (isNaN(power) || power <= 0) throw new Error(`Row ${idx+1}: invalid power rating.`);
          if (isNaN(qty)   || qty   <= 0) throw new Error(`Row ${idx+1}: invalid quantity.`);
          if (isNaN(hours) || hours <= 0) throw new Error(`Row ${idx+1}: invalid hours/day.`);

          // duty-cycle factors
          const df = {
            "LED Bulb":1.0, "LCD TV":0.8, "Refrigerator":0.1,
            "Air Conditioner":0.6, "Washing Machine":0.2,
            "Computer":0.7, "Laptop":0.5, "Microwave":1.0,
            "Fan":0.9, "Boiler":0.3, "Heater":0.7, "Other":1.0
          }[device] || 1.0;

          const effPower    = power * df,
                dailyKwh    = (effPower/1000)*hours,
                monthlyKwh  = dailyKwh * 30 * qty,
                monthlyCost = monthlyKwh * pricePerKwh;

          totalMonthlyKwh += monthlyKwh;
          totalMonthlyCost += monthlyCost;

          outputHtml += `<tr>
            <td>${device}</td>
            <td>${monthlyKwh.toFixed(2)}</td>
            <td>${monthlyCost.toFixed(2)}</td>
          </tr>`;
        });

        outputHtml += `<tr style="font-weight:bold;">
            <td>Total</td>
            <td>${totalMonthlyKwh.toFixed(2)} kWh</td>
            <td>${totalMonthlyCost.toFixed(2)}</td>
          </tr></table>`;

        document.getElementById("outputArea").innerHTML = outputHtml;
      }
      catch(err) {
        console.error(err);
        const ec = document.getElementById("errorConsole");
        ec.style.display = "block";
        ec.innerHTML = `<p>${err.message}</p>`;
      }
    }
  </script>
</head>
<body>
  <h1> Power Consumption Calculator</h1>
  <h2 style="font-size:1em; color:#555;">
    0.01 WorkToken deducted (new balance: <?php echo number_format($tokens - $requiredTokens, 2); ?>)
  </h2>

  <div class="container">
    <label for="price">Price per kWh:</label>
    <input type="number" id="price" placeholder="e.g., 0.15" step="0.01" />
  </div>
  
  <div class="container">
    <h2>Enter Device Details</h2>
    <table>
      <thead>
        <tr>
          <th>Device</th>
          <th>Power Rating (W)</th>
          <th>Quantity</th>
          <th>Hours/Day</th>
        </tr>
      </thead>
      <tbody>
        <?php for($i=0;$i<8;$i++): ?>
        <tr>
          <td>
            <select class="device">
              <option>LED Bulb</option><option>LCD TV</option>
              <option>Refrigerator</option><option>Air Conditioner</option>
              <option>Washing Machine</option><option>Computer</option>
              <option>Laptop</option><option>Microwave</option>
              <option>Fan</option><option>Boiler</option>
              <option>Heater</option><option selected>Other</option>
            </select>
          </td>
          <td><input type="number" class="power" placeholder="Watts" step="0.1" /></td>
          <td><input type="number" class="quantity" placeholder="Qty" step="1" /></td>
          <td><input type="number" class="hours" placeholder="Hrs/Day" step="0.1" /></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
  
  <div class="button-container">
    <button onclick="calculateConsumption()">Calculate Consumption &amp; Cost</button>
  </div>

  <div class="output" id="outputArea"></div>
  <div class="error-console" id="errorConsole" style="display:none;"></div>

</body>
</html>
<?php $conn->close(); ?>