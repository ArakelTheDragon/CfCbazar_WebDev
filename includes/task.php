<?php
// ============================================================================
// CfCbazar - WorkToken (WTK) Task Component Handler
// File: /includes/task.php
// Uses CSS classes from /css/styles.css
// ============================================================================

if (!function_exists('accept_user_task')) {
    /**
     * Records a 'started' entry into `user_tasks` for the specified user email
     * and decrements available quantity in the `tasks` table.
     *
     * @param mysqli $conn   Active database connection.
     * @param string $email  User email address.
     * @param int    $taskId Unique task ID to accept.
     * @return bool          True on success, false on failure/out of stock.
     */
    function accept_user_task($conn, string $email, int $taskId): bool {
        if (!$conn || empty($email) || $taskId <= 0) {
            return false;
        }

        $conn->begin_transaction();

        try {
            // 1. Check task availability
            $stmt = $conn->prepare("SELECT quantity FROM tasks WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $taskId);
            $stmt->execute();
            $stmt->bind_result($quantity);
            if (!$stmt->fetch() || $quantity <= 0) {
                $stmt->close();
                throw new Exception("Task unavailable or out of stock.");
            }
            $stmt->close();

            // 2. Decrement global available quantity
            $updateTask = $conn->prepare("UPDATE tasks SET quantity = quantity - 1 WHERE id = ? AND quantity > 0");
            $updateTask->bind_param("i", $taskId);
            $updateTask->execute();
            if ($updateTask->affected_rows === 0) {
                $updateTask->close();
                throw new Exception("Failed to update task quantity.");
            }
            $updateTask->close();

            // 3. Record task status as 'started' for the user's email
            $insertUserTask = $conn->prepare("
                INSERT INTO user_tasks (user_email, task_id, status, created_at) 
                VALUES (?, ?, 'started', NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status)
            ");
            $insertUserTask->bind_param("si", $email, $taskId);
            $insertUserTask->execute();
            $insertUserTask->close();

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }
}

if (!function_exists('handle_task_actions')) {
    /**
     * Handles POST submissions when a user clicks the Accept button.
     * Updates database status directly during render without redirection headers.
     *
     * @param mysqli $conn  Active MySQLi database connection.
     * @param string $email Logged-in user email address.
     * @return string|null  Status message or null if no action taken.
     */
    function handle_task_actions($conn, string $email): ?string {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_task') {
            if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
                return "Security token validation failed.";
            }

            $taskId = (int)($_POST['task_id'] ?? 0);
            if ($taskId > 0 && !empty($email)) {
                if (!accept_user_task($conn, $email, $taskId)) {
                    return "Unable to accept task. It may be out of stock or already taken.";
                }
            }
        }
        return null;
    }
}

if (!function_exists('get_user_tasks')) {
    /**
     * Reads all tasks from the `tasks` table and joins user status from `user_tasks` for the logged-in email.
     *
     * @param mysqli $conn  Active MySQLi database connection.
     * @param string $email Logged-in user email address.
     * @return array        Task array with user progress state.
     */
    function get_user_tasks($conn, string $email): array {
        if (!$conn || empty($email)) {
            return [];
        }

        $sql = "
            SELECT 
                t.id, 
                t.title, 
                t.description,
                t.reward, 
                t.bonus,
                t.quantity,
                t.category, 
                t.desc_url, 
                COALESCE(ut.status, 'pending') AS status
            FROM tasks t
            LEFT JOIN user_tasks ut 
                   ON t.id = ut.task_id AND ut.user_email = ?
            WHERE ut.status IS NOT NULL OR t.quantity > 0
            ORDER BY 
                CASE LOWER(COALESCE(ut.status, 'pending'))
                    WHEN 'started'   THEN 1
                    WHEN 'accepted'  THEN 1
                    WHEN 'completed' THEN 2
                    WHEN 'pending'   THEN 3
                    ELSE 4
                END ASC,
                t.id DESC
        ";

        $tasks = [];
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $tasks[] = [
                    'id'          => $row['id'],
                    'title'       => $row['title'],
                    'description' => $row['description'] ?? '',
                    'reward'      => $row['reward'],
                    'bonus'       => $row['bonus'] ?? 0,
                    'quantity'    => $row['quantity'],
                    'status'      => $row['status'],
                    'desc_url'    => $row['desc_url'] ?: '/worktoken/task.php?id=' . $row['id'],
                    'category'    => $row['category'] ?: 'General'
                ];
            }
            $stmt->close();
        }

        return $tasks;
    }
}

if (!function_exists('render_task_card')) {
    /**
     * Renders individual Task Card UI.
     * Shows an "Accept Task" button before selection, changing to "In Progress" button once selected.
     *
     * @param array $task Task data payload.
     */
    function render_task_card(array $task) {
        $id       = htmlspecialchars($task['id'] ?? '');
        $title    = htmlspecialchars($task['title'] ?? 'Untitled Task');
        $reward   = number_format((float)($task['reward'] ?? 0), 2);
        $bonus    = number_format((float)($task['bonus'] ?? 0), 2);
        $status   = strtolower(trim($task['status'] ?? 'pending'));
        $descUrl  = htmlspecialchars($task['desc_url'] ?? '#');
        $category = htmlspecialchars($task['category'] ?? 'Task');
        $csrf     = htmlspecialchars($_SESSION['csrf_token'] ?? '');
        ?>
        <div class="link-card task-card text-left flex-column" data-task-id="<?php echo $id; ?>">
            <div class="flex-between w-100 mb-10">
                <span class="badge badge-info"><?php echo $category; ?></span>
                <span class="text-success font-weight-bold">
                    💰 +<?php echo $reward; ?> WTK
                    <?php if ((float)$bonus > 0): ?>
                        <small class="text-warning">(+<?php echo $bonus; ?> Bonus)</small>
                    <?php endif; ?>
                </span>
            </div>
            
            <h4 class="mt-5 mb-15"><?php echo $title; ?></h4>
            
            <div class="w-100 mt-auto pt-10" style="border-top: 1px solid var(--border);">
                <?php if ($status === 'started' || $status === 'accepted'): ?>
                    <a href="<?php echo $descUrl; ?>" style="text-decoration: none; display: block;">
                        <button type="button" class="w-100 mt-0 p-10" style="background: var(--blue, #007bff); color: #ffffff; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;">
                            ⏳ In Progress
                        </button>
                    </a>
                <?php elseif ($status === 'completed'): ?>
                    <a href="<?php echo $descUrl; ?>" style="text-decoration: none; display: block;">
                        <button type="button" class="w-100 mt-0 p-10" style="background: var(--text-light, #6c757d); color: #ffffff; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;">
                            ✅ Completed
                        </button>
                    </a>
                <?php else: ?>
                    <form method="post" action="" style="margin: 0;">
                        <input type="hidden" name="action" value="accept_task">
                        <input type="hidden" name="task_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <button type="submit" class="w-100 mt-0 p-10" style="font-size: 0.85rem; cursor: pointer;">📋 Accept Task</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_task_grid')) {
    /**
     * Renders a grid container displaying all tasks from the DB.
     *
     * @param mysqli|array $dbOrTasks Database connection instance or manual task array.
     * @param string       $email     User email address (required if DB connection supplied).
     * @param string       $title     Card heading title.
     */
    function render_task_grid($dbOrTasks, string $email = '', string $title = '⚙️ Available Tasks') {
        if ($dbOrTasks instanceof mysqli) {
            $taskError = handle_task_actions($dbOrTasks, $email);
            $tasksList = get_user_tasks($dbOrTasks, $email);
        } else {
            $taskError = null;
            $tasksList = is_array($dbOrTasks) ? $dbOrTasks : [];
        }
        ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($title); ?></h3>

            <?php if (!empty($taskError)): ?>
                <div class="error mb-10"><?php echo htmlspecialchars($taskError); ?></div>
            <?php endif; ?>

            <div class="grid-3 mt-20">
                <?php
                if (!empty($tasksList)) {
                    foreach ($tasksList as $task) {
                        render_task_card($task);
                    }
                } else {
                    echo '<p class="text-muted">No tasks available at this time.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
}
