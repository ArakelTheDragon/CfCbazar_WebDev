<?php
require_once '../includes/reusable.php'; // DB + getUserStatus()

$status = getUserStatus($conn); // 0 = guest, 1 = admin
$action = $_GET['action'] ?? 'home';

// -------------------------
// ADMIN ACTIONS
// -------------------------

// Approve
if ($action === 'approve' && $status >= 1) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE bus_schedule SET approved = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: index.php?action=admin");
    exit;
}

// Reject
if ($action === 'reject' && $status >= 1) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM bus_schedule WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: index.php?action=admin");
    exit;
}

// Public submission save
if ($action === 'submit_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $line = $_POST['line_number'];
    $stop = $_POST['stop_name'];
    $day  = $_POST['day_of_week'];
    $time = $_POST['time'];

    $submitted_by = $_SESSION['email'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO bus_schedule (line_number, stop_name, day_of_week, time, submitted_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssss', $line, $stop, $day, $time, $submitted_by);
    $stmt->execute();

    echo "<p>Submission received and awaiting admin approval.</p>";
    echo "<p><a href='index.php'>Back</a></p>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bus Schedule</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        td, th { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
    </style>
</head>
<body>

<h1>Bus Schedule System</h1>

<?php
// -------------------------
// HOME PAGE
// -------------------------
if ($action === 'home') {
    echo "<h2>Bus Lines</h2>";

    $result = $conn->query("SELECT DISTINCT line_number FROM bus_schedule WHERE approved = 1 ORDER BY line_number");

    while ($row = $result->fetch_assoc()) {
        echo "<p><a href='index.php?action=line&line={$row['line_number']}'>Line {$row['line_number']}</a></p>";
    }

    echo "<hr>";
    echo "<p><a href='index.php?action=submit'>Submit a bus time</a></p>";

    if ($status >= 1) {
        echo "<p><a href='index.php?action=admin'>Admin Panel</a></p>";
    }
}

// -------------------------
// SHOW STOPS FOR A LINE
// -------------------------
if ($action === 'line') {
    $line = $_GET['line'];

    echo "<h2>Stops for Line $line</h2>";

    $stmt = $conn->prepare("
        SELECT DISTINCT stop_name 
        FROM bus_schedule 
        WHERE line_number = ? AND approved = 1
        ORDER BY stop_name
    ");
    $stmt->bind_param('s', $line);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<p><a href='index.php?action=timetable&line=$line&stop={$row['stop_name']}'>{$row['stop_name']}</a></p>";
    }

    echo "<p><a href='index.php'>Back</a></p>";
}

// -------------------------
// TIMETABLE FOR LINE + STOP
// -------------------------
if ($action === 'timetable') {
    $line = $_GET['line'];
    $stop = $_GET['stop'];

    echo "<h2>Timetable for Line $line — Stop: $stop</h2>";

    $stmt = $conn->prepare("
        SELECT day_of_week, time
        FROM bus_schedule
        WHERE line_number = ? AND stop_name = ? AND approved = 1
        ORDER BY FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), time
    ");
    $stmt->bind_param('ss', $line, $stop);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<table><tr><th>Day</th><th>Time</th></tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['day_of_week']}</td><td>{$row['time']}</td></tr>";
    }

    echo "</table>";
    echo "<p><a href='index.php?action=line&line=$line'>Back</a></p>";
}

// -------------------------
// PUBLIC SUBMISSION FORM
// -------------------------
if ($action === 'submit') {
?>
    <h2>Submit Bus Time</h2>
    <form method="POST" action="index.php?action=submit_save">
        Line Number: <input type="text" name="line_number" required><br><br>
        Stop Name: <input type="text" name="stop_name" required><br><br>
        Day: 
        <select name="day_of_week">
            <option>Mon</option><option>Tue</option><option>Wed</option>
            <option>Thu</option><option>Fri</option><option>Sat</option><option>Sun</option>
        </select><br><br>
        Time: <input type="time" name="time" required><br><br>
        <button type="submit">Submit</button>
    </form>
    <p><a href="index.php">Back</a></p>
<?php
}

// -------------------------
// ADMIN PANEL
// -------------------------
if ($action === 'admin' && $status >= 1) {
    echo "<h2>Admin Panel — Pending Submissions</h2>";

    $result = $conn->query("SELECT * FROM bus_schedule WHERE approved = 0 ORDER BY submitted_at");

    echo "<table>";
    echo "<tr><th>ID</th><th>Line</th><th>Stop</th><th>Day</th><th>Time</th><th>Actions</th></tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['line_number']}</td>
            <td>{$row['stop_name']}</td>
            <td>{$row['day_of_week']}</td>
            <td>{$row['time']}</td>
            <td>
                <a href='index.php?action=approve&id={$row['id']}'>Approve</a> | 
                <a href='index.php?action=reject&id={$row['id']}'>Reject</a>
            </td>
        </tr>";
    }

    echo "</table>";
    echo "<p><a href='index.php'>Back</a></p>";
}
?>

</body>
</html>