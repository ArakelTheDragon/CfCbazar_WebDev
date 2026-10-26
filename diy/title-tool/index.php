<?php
// /diy/survival/business.php
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
$url        = "/diy/survival/business.php";
$parent_url = "/diy/survival";
$title      = "Business Profit Calculator | CfCbazar";
$template   = "Generic";
$meta_title = "🏢 Business Profit Calculator | CfCbazar";
$meta_desc  = "Calculate revenue, costs, and profit for your business with CfCbazar's free Business Profit Calculator.";

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

        <h1>🏢 Business Profit Calculator</h1>
        <p>Enter your revenue and costs to see profit, margin, and break-even insights.</p>

        <form id="profitForm">
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 20px;">
                <div class="field">
                    <label>💵 Revenue (monthly):</label>
                    <input type="number" name="revenue" placeholder="0" step="0.01" />
                </div>
                <div class="field">
                    <label>📦 Cost of Goods Sold (COGS):</label>
                    <input type="number" name="cogs" placeholder="0" step="0.01" />
                </div>
                <div class="field">
                    <label>💼 Operating Expenses:</label>
                    <input type="number" name="opex" placeholder="0" step="0.01" />
                </div>
                <div class="field">
                    <label>🧾 Taxes (% of gross profit):</label>
                    <input type="number" name="tax" placeholder="0" step="0.1" />
                </div>
            </div>
        </form>

        <button type="button" onclick="calculateProfit()">Calculate Profit</button>
        <div id="profitOutput" style="margin-top:20px;"></div>

        <p style="margin-top:20px;">
            <a href="/diy/survival/index.php">← Back to Survival Budget Tool</a>
        </p>

    </div>
</main>

<script>
function calculateProfit() {
    const form    = document.getElementById('profitForm');
    const revenue = parseFloat(form.revenue.value) || 0;
    const cogs    = parseFloat(form.cogs.value)    || 0;
    const opex    = parseFloat(form.opex.value)    || 0;
    const taxPct  = parseFloat(form.tax.value)     || 0;

    const grossProfit  = revenue - cogs;
    const operatingProfit = grossProfit - opex;
    const taxAmount    = operatingProfit > 0 ? operatingProfit * (taxPct / 100) : 0;
    const netProfit    = operatingProfit - taxAmount;

    const grossMargin  = revenue > 0 ? (grossProfit / revenue) * 100 : 0;
    const netMargin    = revenue > 0 ? (netProfit   / revenue) * 100 : 0;

    const breakEven    = (grossMargin > 0) ? (opex / (grossMargin / 100)) : 0;

    let status = '';
    if (netProfit > 0) {
        status = '<span style="color:#27ae60;font-weight:bold;">✅ Profitable</span>';
    } else if (netProfit === 0) {
        status = '<span style="color:#f1c40f;font-weight:bold;">⚠️ Break-even</span>';
    } else {
        status = '<span style="color:#e74c3c;font-weight:bold;">❌ Loss</span>';
    }

    document.getElementById('profitOutput').innerHTML = `
        <h3>📊 Profit Summary</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 20px;margin-top:10px;">
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Revenue</strong>
                $${revenue.toFixed(2)}
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Gross Profit</strong>
                $${grossProfit.toFixed(2)}
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Operating Profit</strong>
                $${operatingProfit.toFixed(2)}
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Tax</strong>
                $${taxAmount.toFixed(2)}
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Net Profit</strong>
                $${netProfit.toFixed(2)}
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Gross Margin</strong>
                ${grossMargin.toFixed(1)}%
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Net Margin</strong>
                ${netMargin.toFixed(1)}%
            </div>
            <div style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Break-even Revenue</strong>
                $${breakEven.toFixed(2)}
            </div>
        </div>

        <p style="margin-top:15px;font-size:1.1em;">Status: ${status}</p>
    `;
}
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/survival",
    "Business Profit Calculator Source Code"
);
include_footer();
close_database();
?>
