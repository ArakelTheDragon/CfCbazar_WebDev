<?php
// /includes/add-item.php

/**
 * Process POST request to append a new item to a target JSON file
 */
function process_add_nutrition_item($json_file_path, $redirect_url = 'index.php') {
    $userStatus = function_exists('getUserStatus') ? (int)getUserStatus() : 0;
    if (!in_array($userStatus, [1, 2, 3], true)) {
        return "Access Denied: You do not have permission to add new items.";
    }

    $name     = trim($_POST['name'] ?? '');
    $grams    = isset($_POST['grams']) && $_POST['grams'] !== '' ? (float)$_POST['grams'] : 0;
    $calories = isset($_POST['calories']) && $_POST['calories'] !== '' ? (float)$_POST['calories'] : 0;
    $carbs    = isset($_POST['carbs']) && $_POST['carbs'] !== '' ? (float)$_POST['carbs'] : 0;
    $protein  = isset($_POST['protein']) && $_POST['protein'] !== '' ? (float)$_POST['protein'] : 0;
    $fat      = isset($_POST['fat']) && $_POST['fat'] !== '' ? (float)$_POST['fat'] : 0;

    if (empty($name)) {
        return "Dish/Ingredient name is required.";
    }

    $existing_data = [];
    if (file_exists($json_file_path)) {
        $raw_json = file_get_contents($json_file_path);
        $existing_data = json_decode($raw_json, true) ?? [];
    }

    $newItem = [
        "name"     => $name,
        "grams"    => $grams,
        "calories" => $calories,
        "carbs"    => $carbs,
        "protein"  => $protein,
        "fat"      => $fat
    ];

    $existing_data[] = $newItem;

    if (file_put_contents($json_file_path, json_encode($existing_data, JSON_PRETTY_PRINT))) {
        header("Location: " . $redirect_url . "?added=success");
        exit();
    }

    return "Failed to update nutrition.json file. Check permissions.";
}

/**
 * Render the Add New Item Form UI
 */
function render_add_nutrition_form($error_msg = '', $cancel_url = 'index.php') {
    ?>
    <div class="card">
        <h2>➕ Add New Ingredient or Dish</h2>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?action=add">
            <input type="hidden" name="submit_add_item" value="1">

            <div class="form-group">
                <label for="name">Dish / Ingredient Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Scrambled Eggs">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="grams">Serving Size (Grams)</label>
                    <input type="number" step="any" id="grams" name="grams" placeholder="0">
                </div>

                <div class="form-group">
                    <label for="calories">Calories (kcal)</label>
                    <input type="number" step="any" id="calories" name="calories" placeholder="0">
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label for="carbs">Carbs (g)</label>
                    <input type="number" step="any" id="carbs" name="carbs" placeholder="0">
                </div>

                <div class="form-group">
                    <label for="protein">Protein (g)</label>
                    <input type="number" step="any" id="protein" name="protein" placeholder="0">
                </div>

                <div class="form-group">
                    <label for="fat">Fat (g)</label>
                    <input type="number" step="any" id="fat" name="fat" placeholder="0">
                </div>
            </div>

            <div style="margin-top:20px; display:flex; gap:15px;">
                <button type="submit">Save Item</button>
                <a href="<?= htmlspecialchars($cancel_url); ?>" style="padding:12px 20px; background:#6c757d; color:#fff; text-decoration:none; border-radius:var(--radius-small, 8px); font-weight:600;">Cancel</a>
            </div>
        </form>
    </div>
    <?php
}
