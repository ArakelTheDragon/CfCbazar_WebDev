<?php
// /admin.php — CfCbazar Admin Panel
session_start();
require 'config.php';

// Safe include for reusable.php and trackVisit
$reusablePath = __DIR__ . '/includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) trackVisit($conn);
}

// Set return URL for login redirect
if (function_exists('setReturnUrlCookie')) {
    setReturnUrlCookie('/admin.php');
}

// Check user status
$status = function_exists('getUserStatus') ? getUserStatus($conn) : 0;
if ($status === 0) {
    header("Location: /login.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
} elseif ($status !== 1) {
    header("Location: /index.php");
    exit;
}

// SEO metadata
$title = 'CfCbazar Admin Panel';
$description = 'Manage CfCbazar database tables. Restricted to authorized admins.';

// Available tables
$tables = ['achievements', 'click_logs', 'deposit_amounts', 'devices', 'miner', 'pages', 'quests', 'urls', 'users', 'workers'];
$selected_table = $_POST['table'] ?? '';

// Fetch table columns and data
$columns = [];
$data = [];
if ($selected_table && in_array($selected_table, $tables)) {
    $result = $conn->query("SHOW COLUMNS FROM `$selected_table`");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $stmt = $conn->prepare("SELECT * FROM `$selected_table`");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;

    if ($action === 'update' && $id) {
        $updates = [];
        $params = [];
        $types = '';
        foreach ($_POST as $key => $value) {
            if (in_array($key, $columns) && $key !== 'id') {
                $updates[] = "`$key` = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
        if ($updates) {
            $params[] = $id;
            $types .= 'i';
            $query = "UPDATE `$selected_table` SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM `$selected_table` WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    // Refresh data
    $stmt = $conn->prepare("SELECT * FROM `$selected_table`");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
}

// Layout
if (function_exists('include_header')) include_header();
if (function_exists('include_menu')) include_menu();
?>

<main class="container">
    <h1>Admin Panel</h1>
    <section class="card">
        <h2>Manage Tables</h2>
        <form method="POST">
            <label>Select Table:</label>
            <select name="table" onchange="this.form.submit()">
                <option value="">Select a table</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?= htmlspecialchars($table) ?>" <?= $selected_table === $table ? 'selected' : '' ?>>
                        <?= htmlspecialchars($table) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <?php if ($selected_table && $columns): ?>
    <section class="card">
        <h2>Edit <?= htmlspecialchars($selected_table) ?></h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                <input type="hidden" name="action" value="update">
                                <?php foreach ($columns as $col): ?>
                                    <td>
                                        <?php if ($col === 'id'): ?>
                                            <?= htmlspecialchars($row[$col]) ?>
                                        <?php else: ?>
                                            <input type="text" name="<?= htmlspecialchars($col) ?>" value="<?= htmlspecialchars($row[$col] ?? '') ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <button type="submit">Update</button>
                                    <button type="submit" onclick="this.form.action.value='delete'">Delete</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</main>

<style>
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; min-width: 100px; }
th { background: #f0f0f0; }
input[type="text"] { width: 100%; padding: 4px; box-sizing: border-box; }
button { padding: 6px 12px; margin: 2px; }
</style>

<?php if (function_exists('include_footer')) include_footer(); ?>