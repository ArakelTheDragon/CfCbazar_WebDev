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
            "name" => "Grilled Chicken Breast",
            "grams" => 100,
            "calories" => 165,
            "carbs" => 0,
            "protein" => 31,
            "fat" => 3.6
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
</head>
<body>

<main class="container">
    <h1>🥗 Nutrition & Calorie Counter</h1>

    <?php if ($action === 'add' && $canAddSections): ?>
        
        <!-- RENDER ADD ITEM FORM FROM /includes/add-item.php -->
        <?php render_add_nutrition_form($error, 'index.php'); ?>

    <?php else: ?>

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
                <a href="index.php?action=add" style="display: inline-block; padding: 10px 16px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;">➕ Add New Item</a>
            </div>
        <?php endif; ?>

        <?php
        $raw_json = file_get_contents($json_file);
        $dishes = json_decode($raw_json, true) ?? [];

        function get_val($item, $key) {
            return (isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null) ? (float)$item[$key] : 0;
        }
        ?>

        <div class="table-wrapper">
            <table id="nutritionTable">
                <thead>
                    <tr>
                        <th>Ingredient / Dish</th>
                        <th>Serving (g)</th>
                        <th>Calories (kcal)</th>
                        <th>Carbs (g)</th>
                        <th>Protein (g)</th>
                        <th>Fat (g)</th>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
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
}
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
