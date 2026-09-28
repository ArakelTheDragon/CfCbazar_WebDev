<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// reusable.php loads config.php & /includes/add-item.php
require_once __DIR__ . '/../../includes/reusable.php';

$userStatus = function_exists('getUserStatus') ? (int)getUserStatus() : 0;
$canAddSections = in_array($userStatus, [1, 2, 3], true);

$json_file = __DIR__ . '/nutrition.json';

// Create a sample JSON file if missing
if (!file_exists($json_file)) {
    $sample_data = [
        [
            "name" => "Minestrone Soup",
            "grams" => 240,
            "calories" => 110,
            "carbs" => 19,
            "protein" => 4.5,
            "fat" => 2.5,
            "fiber" => 3.5,
            "cholesterol" => 0,
            "sodium" => 640,
            "potassium" => 390,
            "calcium" => 55,
            "salt" => 1.6,
            "iron" => 1.4
        ]
    ];
    file_put_contents($json_file, json_encode($sample_data, JSON_PRETTY_PRINT));
}

// Handle Form Submission if requested
$action = $_GET['action'] ?? '';
$error = '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_add_item'])) {
    $error = process_add_nutrition_item($json_file, 'index.php');
}

include_menu();
include_header();
render_top_userbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition & Calorie Counter - CfCbazar</title>
    <style>
        /* Compact table styles for 1280x768 displays */
        #nutritionTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        #nutritionTable th, 
        #nutritionTable td {
            padding: 5px 4px;
            white-space: nowrap;
            text-align: right;
            border-bottom: 1px solid #dee2e6;
        }

        #nutritionTable th:first-child, 
        #nutritionTable td:first-child {
            text-align: left;
            padding-left: 8px;
        }

        #nutritionTable th:last-child, 
        #nutritionTable td:last-child {
            padding-right: 8px;
        }

        #nutritionTable th {
            background-color: #28a745;
            color: #ffffff;
            font-weight: 600;
        }

        #nutritionTable tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        #nutritionTable tfoot tr {
            background-color: #e9ecef;
            font-weight: bold;
        }

        /* Dual Scrollbar styling for mobile/small screens */
        .top-scrollbar-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            height: 22px;
            margin-bottom: 6px;
            background-color: #f1f3f5;
            border-radius: 6px;
        }

        .top-scrollbar-content {
            height: 22px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        /* Enhanced Mobile & Touch Scrollbars */
        .top-scrollbar-wrapper,
        .table-wrapper {
            scrollbar-width: auto;
            scrollbar-color: #28a745 #e9ecef;
            -webkit-overflow-scrolling: touch;
        }

        .top-scrollbar-wrapper::-webkit-scrollbar,
        .table-wrapper::-webkit-scrollbar {
            height: 16px;
            background-color: #e9ecef;
        }

        .top-scrollbar-wrapper::-webkit-scrollbar-track,
        .table-wrapper::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 8px;
        }

        .top-scrollbar-wrapper::-webkit-scrollbar-thumb,
        .table-wrapper::-webkit-scrollbar-thumb {
            background-color: #28a745;
            border-radius: 8px;
            border: 3px solid #e9ecef;
            min-width: 40px;
        }

        .top-scrollbar-wrapper::-webkit-scrollbar-thumb:hover,
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<main class="container">
    <h1>🥗 Nutrition & Calorie Counter</h1>

    <?php if ($action === 'add' && $canAddSections): ?>
        
        <!-- RENDER ADD ITEM FORM FROM /includes/add-item.php -->
        <?php render_add_nutrition_form($error, 'index.php'); ?>

    <?php else: ?>

        <?php
        $raw_json = file_get_contents($json_file);
        $dishes = json_decode($raw_json, true) ?? [];

        function get_val($item, $key) {
            return (isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null) ? (float)$item[$key] : 0;
        }

        // Numerical stats keys
        $keys = ['grams', 'calories', 'carbs', 'protein', 'fat', 'fiber', 'cholesterol', 'sodium', 'potassium', 'calcium', 'salt', 'iron'];
        $totals = array_fill_keys($keys, 0);
        $averages = array_fill_keys($keys, 0);

        $total_items = count($dishes);
        $highest_protein_name = 'N/A';
        $highest_protein_val = 0;

        if ($total_items > 0) {
            foreach ($dishes as $dish) {
                foreach ($keys as $k) {
                    $totals[$k] += get_val($dish, $k);
                }

                $p = get_val($dish, 'protein');
                if ($p > $highest_protein_val) {
                    $highest_protein_val = $p;
                    $highest_protein_name = $dish['name'] ?? 'Unknown';
                }
            }

            foreach ($keys as $k) {
                $averages[$k] = round($totals[$k] / $total_items, 1);
            }
        }
        ?>

        <!-- STATS OVERVIEW CARDS -->
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

        <!-- RENDER SEARCH & TABLE VIEW -->
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

        <!-- TOP SCROLLBAR FOR MOBILE / SMALL SCREENS -->
        <div class="top-scrollbar-wrapper" id="topScrollWrapper">
            <div class="top-scrollbar-content" id="topScrollContent"></div>
        </div>

        <!-- MAIN TABLE WRAPPER -->
        <div class="table-wrapper" id="tableWrapper">
            <table id="nutritionTable">
                <thead>
                    <tr>
                        <th>Ingredient / Dish</th>
                        <th>Serving (g)</th>
                        <th>Cal (kcal)</th>
                        <th>Carbs (g)</th>
                        <th>Prot (g)</th>
                        <th>Fat (g)</th>
                        <th>Fiber (g)</th>
                        <th>Chol (mg)</th>
                        <th>Sodium (mg)</th>
                        <th>K (mg)</th>
                        <th>Ca (mg)</th>
                        <th>Salt (g)</th>
                        <th>Iron (mg)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dishes as $dish): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dish['name'] ?? 'Unknown'); ?></strong></td>
                            <td><?= get_val($dish, 'grams'); ?></td>
                            <td><?= get_val($dish, 'calories'); ?></td>
                            <td><?= get_val($dish, 'carbs'); ?></td>
                            <td><?= get_val($dish, 'protein'); ?></td>
                            <td><?= get_val($dish, 'fat'); ?></td>
                            <td><?= get_val($dish, 'fiber'); ?></td>
                            <td><?= get_val($dish, 'cholesterol'); ?></td>
                            <td><?= get_val($dish, 'sodium'); ?></td>
                            <td><?= get_val($dish, 'potassium'); ?></td>
                            <td><?= get_val($dish, 'calcium'); ?></td>
                            <td><?= get_val($dish, 'salt'); ?></td>
                            <td><?= get_val($dish, 'iron'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Average Across Database</td>
                        <td><?= $averages['grams']; ?></td>
                        <td><?= $averages['calories']; ?></td>
                        <td><?= $averages['carbs']; ?></td>
                        <td><?= $averages['protein']; ?></td>
                        <td><?= $averages['fat']; ?></td>
                        <td><?= $averages['fiber']; ?></td>
                        <td><?= $averages['cholesterol']; ?></td>
                        <td><?= $averages['sodium']; ?></td>
                        <td><?= $averages['potassium']; ?></td>
                        <td><?= $averages['calcium']; ?></td>
                        <td><?= $averages['salt']; ?></td>
                        <td><?= $averages['iron']; ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

    <?php endif; ?>
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
 cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/nutritions",
    "Tool GitHub Source Code"
    );
?>

<?php 
include_footer();
close_database();
?>
</body>
</html>
