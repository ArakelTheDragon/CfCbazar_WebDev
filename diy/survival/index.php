<?php
// /diy/survival/index.php
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
$url        = "/diy/survival/index.php";
$parent_url = "/diy/survival";
$title      = "Survival Budget Tool | CfCbazar";
$template   = "Generic";
$meta_title = "💸 Survival Budget Tool | CfCbazar";
$meta_desc  = "Explore essential monthly expenses with CfCbazar's Survival Budget Tool. Calculate food, transport, hygiene, and more to manage your budget effectively.";

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

        <h1>💸 Survival Budget Tool</h1>
        <p>Select a category to explore budget guidance:</p>

        <select id="category" onchange="if(this.value.endsWith('.php')) window.location.href=this.value;">
            <option value="" disabled selected>Choose category...</option>
            <option value="calculator">🧮 Budget Calculator</option>
            <option value="/diy/survival/business.php">🏢 Business Profit Calculator</option>
        </select>

        <button id="showBtn">Show Selection</button>

        <div class="output" id="outputBox"></div>

        <div id="calcContainer" class="output" style="display:none;">
            <h2>🧮 Budget Calculator</h2>

            <form id="calcForm">
                <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 20px;">
                    <div class="field">
                        <label>💼 Income (monthly):</label>
                        <input type="number" name="income" placeholder="0" />
                    </div>
                    <div class="field">
                        <label>🎯 Target Budget (monthly):</label>
                        <input type="number" name="target" placeholder="600" value="600" />
                    </div>
                </div>

                <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 20px;">
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

    </div>
</main>

<script>
const conv = {
    food: 30,          // daily → monthly
    mobile: 1,
    clothing: 1 / 12,  // yearly → monthly
    transport: 1,
    hygiene: 1,
    rent: 1,
    utilities: 1,
    savings: 1,
    subscriptions: 1,
    other: 1
};

const colors = {
    food: '#27ae60',
    mobile: '#8e44ad',
    clothing: '#d35400',
    transport: '#2980b9',
    hygiene: '#16a085',
    rent: '#2c3e50',
    utilities: '#f1c40f',
    savings: '#27ae60',
    subscriptions: '#e67e22',
    other: '#7f8c8d'
};

document.getElementById('showBtn').addEventListener('click', showOptions);
document.addEventListener('DOMContentLoaded', restoreFormFromStorage);

function showOptions() {
    const cat  = document.getElementById('category').value;
    const out  = document.getElementById('outputBox');
    const calc = document.getElementById('calcContainer');
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
    const form       = document.getElementById('calcForm');
    const income     = parseFloat(form.income.value) || 0;
    const baseBudget = parseFloat(form.target.value) > 0 ? parseFloat(form.target.value) : 600;

    let totalMonthly = 0;
    let details      = '';

    for (let key in conv) {
        const val      = parseFloat(form[key].value) || 0;
        const monthAmt = val * conv[key];
        totalMonthly  += monthAmt;

        const pctBase = ((monthAmt / baseBudget) * 100) || 0;
        const label   = key.charAt(0).toUpperCase() + key.slice(1);
        const color   = colors[key] || '#3498db';
        const width   = Math.max(0, Math.min(pctBase, 100));

        details += `
            <div class="bar-row" style="margin-top:8px;font-size:0.9em;">
                <div class="bar-label" style="display:flex;justify-content:space-between;margin-bottom:3px;">
                    <span><strong>${label}:</strong> $${monthAmt.toFixed(2)}</span>
                    <span>${pctBase.toFixed(1)}% of $${baseBudget.toFixed(2)}</span>
                </div>
                <div class="bar" style="width:100%;height:8px;background:#dde3ea;border-radius:4px;overflow:hidden;">
                    <div class="bar-fill" style="width:${width}%;height:100%;border-radius:4px;background:${color};transition:width 0.3s ease;"></div>
                </div>
            </div>
        `;
    }

    const daily  = totalMonthly / 30;
    const weekly = totalMonthly / 4.345;
    const yearly = totalMonthly * 12;

    const pctOfBase     = ((totalMonthly / baseBudget) * 100).toFixed(1);
    const remainingBase = (baseBudget - totalMonthly).toFixed(2);

    let incomeBlock = '';
    if (income > 0) {
        const pctOfIncome      = ((totalMonthly / income) * 100).toFixed(1);
        const remainingIncome  = (income - totalMonthly).toFixed(2);
        incomeBlock = `
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Income vs Expenses</strong>
                Income: $${income.toFixed(2)}<br>
                Used: $${totalMonthly.toFixed(2)} (${pctOfIncome}% of income)<br>
                Remaining: $${remainingIncome}
            </div>
        `;
    }

    document.getElementById('calcOutput').innerHTML = `
        <h3>📊 Spending Summary</h3>

        <div class="summary-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 20px;margin-top:10px;">
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Daily Spend</strong>
                $${daily.toFixed(2)}
            </div>
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Weekly Spend</strong>
                $${weekly.toFixed(2)}
            </div>
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Monthly Spend</strong>
                $${totalMonthly.toFixed(2)} (${pctOfBase}% of $${baseBudget.toFixed(2)})
            </div>
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Yearly Spend</strong>
                $${yearly.toFixed(2)}
            </div>
            <div class="summary-box" style="background:#fff;border-radius:6px;padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.95em;">
                <strong style="display:block;margin-bottom:4px;">Remaining vs Target Budget</strong>
                From $${baseBudget.toFixed(2)}/mo: $${remainingBase}
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

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/survival",
    "Survival Budget Source Code"
);
include_footer();
close_database();
?>
