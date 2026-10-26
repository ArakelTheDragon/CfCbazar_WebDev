<?php
// /diy/nutritions/index.php
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
$url        = "/diy/nutritions/index.php";
$parent_url = "/diy/nutritions";
$title      = "Nutrition & Calorie Counter";
$template   = "Generic";
$meta_title = "Nutrition & Calorie Counter";
$meta_desc  = "Browse and compare nutrition data for dishes and ingredients, with average macros and top protein sources.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

// --- Permissions -------------------------------------------------------------
$userStatus     = function_exists('getUserStatus') ? (int)getUserStatus() : 0;
$canAddSections = in_array($userStatus, [1, 2, 3], true);

// --- Data file ---------------------------------------------------------------
$json_file = __DIR__ . '/nutrition.json';

if (!file_exists($json_file)) {
    $sample_data = [
        [
            'name'        => 'Minestrone Soup',
            'grams'       => 240,
            'calories'    => 110,
            'carbs'       => 19,
            'protein'     => 4.5,
            'fat'         => 2.5,
            'fiber'       => 3.5,
            'cholesterol' => 0,
            'sodium'      => 640,
            'potassium'   => 390,
            'calcium'     => 55,
            'salt'        => 1.6,
            'iron'        => 1.4,
        ],
    ];
    file_put_contents($json_file, json_encode($sample_data, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? '';
$error  = '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_add_item'])) {
    $error = process_add_nutrition_item($json_file, 'index.php');
}

// --- Load data ---------------------------------------------------------------
$raw_json = file_get_contents($json_file);
$dishes   = json_decode($raw_json, true) ?? [];

function get_val(array $item, string $key): float {
    return (isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null)
        ? (float)$item[$key]
        : 0;
}

$keys     = ['grams', 'calories', 'carbs', 'protein', 'fat', 'fiber', 'cholesterol', 'sodium', 'potassium', 'calcium', 'salt', 'iron'];
$totals   = array_fill_keys($keys, 0);
$averages = array_fill_keys($keys, 0);

$total_items          = count($dishes);
$highest_protein_name = 'N/A';
$highest_protein_val  = 0;

if ($total_items > 0) {
    foreach ($dishes as $dish) {
        foreach ($keys as $k) {
            $totals[$k] += get_val($dish, $k);
        }

        $p = get_val($dish, 'protein');
        if ($p > $highest_protein_val) {
            $highest_protein_val  = $p;
            $highest_protein_name = $dish['name'] ?? 'Unknown';
        }
    }

    foreach ($keys as $k) {
        $averages[$k] = round($totals[$k] / $total_items, 1);
    }
}

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1>🥗 Nutrition &amp; Calorie Counter</h1>

        <?php if ($action === 'add' && $canAddSections): ?>

            <?php render_add_nutrition_form($error, 'index.php'); ?>

        <?php else: ?>

            <div class="stats-container" style="display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0;">
                <div class="stat-card" style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center;">
                    <span style="font-size: 0.8em; color: #6c757d; font-weight: bold; text-transform: uppercase;">Total Items</span>
                    <h2 style="margin: 4px 0 0; color: #333; font-size: 1.6em;"><?= $total_items; ?></h2>
                </div>
                <div class="stat-card" style="flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center;">
                    <span style="font-size: 0.8em; color: #6c757d; font-weight: bold; text-transform: uppercase;">Avg. Calories</span>
                    <h2 style="margin: 4px 0 0; color: #333; font-size: 1.6em;"><?= $averages['calories']; ?> <small style="font-size: 0.5em;">kcal</small></h2>
                </div>
                <div class="stat-card" style="flex: 1; min-width: 160px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center;">
                    <span style="font-size: 0.8em; color: #6c757d; font-weight: bold; text-transform: uppercase;">Avg Macros (C / P / F)</span>
                    <h2 style="margin: 4px 0 0; color: #007bff; font-size: 1.2em;"><?= $averages['carbs']; ?>g / <?= $averages['protein']; ?>g / <?= $averages['fat']; ?>g</h2>
                </div>
                <div class="stat-card" style="flex: 1; min-width: 160px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center;">
                    <span style="font-size: 0.8em; color: #6c757d; font-weight: bold; text-transform: uppercase;">Top Protein Source</span>
                    <h2 style="margin: 4px 0 0; color: #28a745; font-size: 1.1em;"><?= htmlspecialchars($highest_protein_name); ?></h2>
                    <small style="color: #6c757d; font-weight: bold;"><?= $highest_protein_val; ?>g protein</small>
                </div>
            </div>

            <div class="search-box">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search ingredients or dishes..."
                    onkeyup="liveSearch()"
                >
            </div>

            <?php if ($canAddSections): ?>
                <div style="margin: 15px 0;">
                    <a href="index.php?action=add" style="display: inline-block; padding: 8px 14px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;">➕ Add New Item</a>
                </div>
            <?php endif; ?>

            <div class="top-scrollbar-wrapper" id="topScrollWrapper" style="width:100%;overflow-x:auto;overflow-y:hidden;height:22px;margin-bottom:6px;background:#f1f3f5;border-radius:6px;">
                <div class="top-scrollbar-content" id="topScrollContent" style="height:22px;"></div>
            </div>

            <div class="table-wrapper" id="tableWrapper" style="width:100%;overflow-x:auto;">
                <table id="nutritionTable" style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:left;padding-left:8px;">Ingredient / Dish</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Serving (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Cal (kcal)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Carbs (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Prot (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Fat (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Fiber (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Chol (mg)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Sodium (mg)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">K (mg)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Ca (mg)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;">Salt (g)</th>
                            <th style="padding:5px 4px;background:#28a745;color:#fff;font-weight:600;text-align:right;padding-right:8px;">Iron (mg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dishes as $dish): ?>
                            <tr>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:left;padding-left:8px;"><strong><?= htmlspecialchars($dish['name'] ?? 'Unknown'); ?></strong></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'grams'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'calories'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'carbs'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'protein'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'fat'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'fiber'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'cholesterol'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'sodium'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'potassium'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'calcium'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;"><?= get_val($dish, 'salt'); ?></td>
                                <td style="padding:5px 4px;border-bottom:1px solid #dee2e6;text-align:right;padding-right:8px;"><?= get_val($dish, 'iron'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#e9ecef;font-weight:bold;">
                            <td style="padding:5px 4px;text-align:left;padding-left:8px;">Average Across Database</td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['grams']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['calories']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['carbs']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['protein']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['fat']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['fiber']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['cholesterol']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['sodium']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['potassium']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['calcium']; ?></td>
                            <td style="padding:5px 4px;text-align:right;"><?= $averages['salt']; ?></td>
                            <td style="padding:5px 4px;text-align:right;padding-right:8px;"><?= $averages['iron']; ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        <?php endif; ?>

    </div>
</main>

<script>
function liveSearch() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let rows = document.querySelectorAll('#nutritionTable tbody tr');

    rows.forEach(row => {
        let text = row.cells[0].textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
    syncTopScrollbarWidth();
}

function syncTopScrollbarWidth() {
    const table = document.getElementById('nutritionTable');
    const topContent = document.getElementById('topScrollContent');
    if (table && topContent) {
        topContent.style.width = table.scrollWidth + 'px';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const topWrapper = document.getElementById('topScrollWrapper');
    const tableWrapper = document.getElementById('tableWrapper');

    if (!topWrapper || !tableWrapper) return;

    syncTopScrollbarWidth();
    window.addEventListener('resize', syncTopScrollbarWidth);

    let isSyncingTop = false;
    let isSyncingTable = false;

    topWrapper.addEventListener('scroll', () => {
        if (!isSyncingTop) {
            isSyncingTable = true;
            tableWrapper.scrollLeft = topWrapper.scrollLeft;
        }
        isSyncingTop = false;
    });

    tableWrapper.addEventListener('scroll', () => {
        if (!isSyncingTable) {
            isSyncingTop = true;
            topWrapper.scrollLeft = tableWrapper.scrollLeft;
        }
        isSyncingTable = false;
    });
});
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/nutritions",
    "Tool GitHub Source Code"
);
include_footer();
close_database();
?>
