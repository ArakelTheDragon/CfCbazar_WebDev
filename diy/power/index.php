<?php
// /diy/power/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/power/index.php";
$parent_url = "/diy/power";
$title      = "Power Consumption Calculator | CfCbazar";
$template   = "Generic";
$meta_title = "⚡ Power Consumption Calculator | CfCbazar";
$meta_desc  = "Estimate your monthly electricity usage and cost with CfCbazar's Power Consumption Calculator. Enter your devices and usage to get instant results.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1>⚡ Power Consumption Calculator</h1>

        <div class="container">
            <label for="price">Price per kWh:</label>
            <input type="number" id="price" placeholder="e.g., 0.15" step="0.01" />
        </div>

        <div class="container">
            <h2>Enter Device Details</h2>
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                <thead>
                    <tr>
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #ddd;">Device</th>
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #ddd;">Power Rating (W)</th>
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #ddd;">Quantity</th>
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #ddd;">Hours/Day</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 8; $i++): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #ddd;">
                            <select class="device" style="padding:8px;border:1px solid #ccc;border-radius:4px;">
                                <option>LED Bulb</option><option>LCD TV</option>
                                <option>Refrigerator</option><option>Air Conditioner</option>
                                <option>Washing Machine</option><option>Computer</option>
                                <option>Laptop</option><option>Microwave</option>
                                <option>Fan</option><option>Boiler</option>
                                <option>Heater</option><option selected>Other</option>
                            </select>
                        </td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><input type="number" class="power" placeholder="Watts" step="0.1" style="padding:8px;border:1px solid #ccc;border-radius:4px;" /></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><input type="number" class="quantity" placeholder="Qty" step="1" style="padding:8px;border:1px solid #ccc;border-radius:4px;" /></td>
                        <td style="padding:8px;border-bottom:1px solid #ddd;"><input type="number" class="hours" placeholder="Hrs/Day" step="0.1" style="padding:8px;border:1px solid #ccc;border-radius:4px;" /></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="button-container" style="margin-top:20px;text-align:center;">
            <button onclick="calculateConsumption()" style="padding:10px 20px;font-size:1em;background-color:#2c3e50;color:white;border:none;border-radius:4px;cursor:pointer;">Calculate Consumption &amp; Cost</button>
        </div>

        <div class="output" id="outputArea" style="margin-top:30px;background:#f4f6f8;padding:20px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.1);"></div>
        <div class="error-console" id="errorConsole" style="display:none;margin-top:20px;background:#ffe6e6;padding:10px;border:1px solid #cc0000;border-radius:4px;color:#cc0000;font-family:monospace;"></div>

    </div>
</main>

<script>
const defaultRows = [
    {device:'LED Bulb',       power:10,   qty:4,  hours:5},
    {device:'Refrigerator',   power:150,  qty:1,  hours:24},
    {device:'Air Conditioner', power:1000, qty:1,  hours:6},
    {device:'Computer',       power:200,  qty:1,  hours:8},
    {device:'Laptop',         power:50,   qty:1,  hours:8},
    {device:'Microwave',      power:800,  qty:1,  hours:0.5},
    {device:'Fan',            power:75,   qty:1,  hours:8},
    {device:'Other',          power:100,  qty:1,  hours:1}
];

window.onload = () => {
    document.querySelectorAll("table tbody tr").forEach((row, i) => {
        const def = defaultRows[i];
        if (!def) return;
        row.querySelector(".device").value   = def.device;
        row.querySelector(".power").value    = def.power;
        row.querySelector(".quantity").value = def.qty;
        row.querySelector(".hours").value    = def.hours;
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

        let totalMonthlyKwh  = 0;
        let totalMonthlyCost = 0;
        let outputHtml = "<h2>Results</h2><table><tr><th>Device</th><th>Monthly Consumption (kWh)</th><th>Monthly Cost</th></tr>";

        document.querySelectorAll("table tbody tr").forEach((row, idx) => {
            const device = row.querySelector(".device").value;
            const power  = parseFloat(row.querySelector(".power").value);
            const qty    = parseFloat(row.querySelector(".quantity").value);
            const hours  = parseFloat(row.querySelector(".hours").value);

            if (isNaN(power) && isNaN(qty) && isNaN(hours)) return;
            if (isNaN(power) || power <= 0) throw new Error(`Row ${idx+1}: invalid power rating.`);
            if (isNaN(qty)   || qty   <= 0) throw new Error(`Row ${idx+1}: invalid quantity.`);
            if (isNaN(hours) || hours <= 0) throw new Error(`Row ${idx+1}: invalid hours per day.`);

            const dfMap = {
                "LED Bulb": 1.0, "LCD TV": 0.8, "Refrigerator": 0.1,
                "Air Conditioner": 0.6, "Washing Machine": 0.2,
                "Computer": 0.7, "Laptop": 0.5, "Microwave": 1.0,
                "Fan": 0.9, "Boiler": 0.3, "Heater": 0.7, "Other": 1.0
            };
            const df = dfMap[device] || 1.0;

            const effPower    = power * df;
            const dailyKwh    = (effPower / 1000) * hours;
            const monthlyKwh  = dailyKwh * 30 * qty;
            const monthlyCost = monthlyKwh * pricePerKwh;

            totalMonthlyKwh  += monthlyKwh;
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
    } catch (err) {
        const ec = document.getElementById("errorConsole");
        ec.style.display = "block";
        ec.innerHTML = `<p>${err.message}</p>`;
    }
}
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/power",
    "Power Tool Source Code"
);
include_footer();
close_database();
?>
