<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include reusable.php using a relative path to avoid open_basedir restrictions
// Adjust 'includes/reusable.php' if it is located in a different folder relative to this script
require_once 'includes/reusable.php'; 

// Note: Ensure your reusable.php or config.php initializes your database connection 
// using a variable like $conn or $mysqli (e.g., $conn = new mysqli(...);)
// If your connection variable has a different name, update it below where $conn is used.

// Complete list of tables extracted from the database schema
$tables = [
    'achievements',
    'bus_schedule',
    'click_logs',
    'deposit_amounts',
    'devices',
    'miner',
    'pages',
    'quests',
    'scheduler',
    'settings',
    'short_links'
];

$current_table = $_GET['table'] ?? '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle record updates using MySQLi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $table = $_POST['table'];
    if (in_array($table, $tables)) {
        $primary_key = 'id'; // Assuming 'id' is present as the primary key
        $record_id = $_POST['id'];
        
        $fields = [];
        $types = "";
        $values = [];
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'update_record' && $key !== 'table' && $key !== 'id') {
                $fields[] = "`$key` = ?";
                $types .= "s"; // Treat inputs as strings for binding
                $values[] = $value;
            }
        }
        
        if (!empty($fields)) {
            $values[] = $record_id; // Add ID for the WHERE clause
            $types .= "s";
            
            $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE `$primary_key` = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
                header("Location: admin.php?table=$table&status=updated");
                exit;
            } else {
                $update_error = "Prepare failed: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Admin Panel - CfCbazar</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; color: #333; }
        h1, h2 { color: #2c3e50; }
        .nav { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .nav a { text-decoration: none; color: #3498db; font-weight: bold; padding: 5px 10px; border-radius: 3px; background: #ecf0f1; }
        .nav a:hover, .nav a.active { background: #3498db; color: white; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; font-size: 14px; }
        th { background: #34495e; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        form input[type="text"], form textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        form input[type="submit"] { background: #2ecc71; color: white; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; }
        form input[type="submit"]:hover { background: #27ae60; }
        .btn { display: inline-block; padding: 5px 10px; background: #3498db; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .btn:hover { background: #2980b9; }
        .alert { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #f5c6cb; }
        .table-container { overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Database Admin Panel</h1>
    
    <div class="nav">
        <strong>Tables:</strong>
        <?php foreach ($tables as $t): ?>
            <a href="admin.php?table=<?php echo $t; ?>" class="<?php echo ($current_table === $t) ? 'active' : ''; ?>"><?php echo htmlspecialchars($t); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
        <div class="alert">Record updated successfully!</div>
    <?php endif; ?>

    <?php if (isset($update_error)): ?>
        <div class="error"><?php echo htmlspecialchars($update_error); ?></div>
    <?php endif; ?>

    <?php if ($current_table && in_array($current_table, $tables)): ?>
        <?php if ($action === 'edit' && $id): ?>
            <h2>Edit Record in `<?php echo htmlspecialchars($current_table); ?>` (ID: <?php echo htmlspecialchars($id); ?>)</h2>
            <?php
            $stmt = $conn->prepare("SELECT * FROM `$current_table` WHERE id = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $record = $result->fetch_assoc();
            $stmt->close();

            if ($record):
            ?>
            <form method="POST" action="admin.php?table=<?php echo htmlspecialchars($current_table); ?>">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($current_table); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                <table>
                    <?php foreach ($record as $column => $value): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($column); ?></th>
                            <td>
                                <?php if ($column === 'id'): ?>
                                    <?php echo htmlspecialchars($value); ?> (Primary Key - Read Only)
                                <?php elseif (is_string($value) && (strlen($value) > 80 || strpos($value, "\n") !== false)): ?>
                                    <textarea name="<?php echo htmlspecialchars($column); ?>" rows="4"><?php echo htmlspecialchars($value); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo htmlspecialchars($column); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <input type="submit" name="update_record" value="Save Changes">
                <a href="admin.php?table=<?php echo htmlspecialchars($current_table); ?>" class="btn" style="background: #7f8c8d;">Cancel</a>
            </form>
            <?php else: ?>
                <p>Record not found.</p>
            <?php endif; ?>

        <?php else: ?>
            <h2>Viewing Table: `<?php echo htmlspecialchars($current_table); ?>`</h2>
            <?php
            $result = $conn->query("SELECT * FROM `$current_table` LIMIT 100");
            if ($result && $result->num_rows > 0):
                $rows = $result->fetch_all(MYSQLI_ASSOC);
            ?>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Actions</th>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?php echo htmlspecialchars($col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <?php if (isset($row['id'])): ?>
                                    <a href="admin.php?table=<?php echo htmlspecialchars($current_table); ?>&action=edit&id=<?php echo $row['id']; ?>" class="btn">Edit</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <?php foreach ($row as $val): ?>
                                <td><?php echo htmlspecialchars(substr((string)$val, 0, 100)); ?><?php echo strlen((string)$val) > 100 ? '...' : ''; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php else: ?>
                <p>No records found in this table or query failed.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <p>Please select a table from the top menu to view its fields and records.</p>
    <?php endif; ?>
</body>
</html>
