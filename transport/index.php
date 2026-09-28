<?php
require_once '../includes/reusable.php'; // DB + layout + session

$status = getUserStatus($conn); // 0 = guest, 1 = admin
$action = $_GET['action'] ?? 'home';

// ✅ Redirect URL
// Set return url cookie for after log in
setReturnUrlCookie('/transport/');
enforce_https();

// -------------------------
// ADMIN ACTIONS
// -------------------------

if ($action === 'approve' && $status >= 1) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE bus_schedule SET approved = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: index.php?action=admin");
    exit;
}

if ($action === 'reject' && $status >= 1) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM bus_schedule WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: index.php?action=admin");
    exit;
}

// -------------------------
// PUBLIC SUBMISSION SAVE
// -------------------------

if ($action === 'submit_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $line = trim($_POST['line_number']);
    $city = trim($_POST['city']);
    $stop = trim($_POST['stop_name']);
    $day  = trim($_POST['day_of_week']);
    $time = trim($_POST['time']);

    // SERVER-SIDE VALIDATION
    if ($line === '' || $city === '' || $stop === '' || $day === '' || $time === '') {
        include_header();
        include_menu();
        echo "<main class='container'>";
        echo "<p style='color:red;'>All fields are required, including city.</p>";
        echo "<p><a href='index.php?action=submit'>Go back</a></p>";
        echo "</main>";
        include_footer();
        exit;
    }

    $submitted_by = $_SESSION['email'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO bus_schedule (line_number, city, stop_name, day_of_week, time, submitted_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssss', $line, $city, $stop, $day, $time, $submitted_by);
    $stmt->execute();

    include_header();
    include_menu();
    echo "<main class='container'>";
    echo "<p>Submission received and awaiting admin approval.</p>";
    echo "<p><a href='index.php'>Back</a></p>";
    echo "</main>";
    include_footer();
    exit;
}

// -------------------------
// PAGE LAYOUT START
// -------------------------

include_header();
include_menu();
showAdvertPopup(); // Function to show popup.
?>

<!-- Responsive table fix -->
<style>
.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.table-wrap table {
    width: 100%;
    min-width: 320px;
    border-collapse: collapse;
}

.table-wrap th,
.table-wrap td {
    padding: 8px;
    white-space: nowrap;
}
</style>

<main class="container">
<h1>Bus Schedule System</h1>

<?php
// -------------------------
// HOME PAGE
// -------------------------
if ($action === 'home') {
    echo "<h2>Bus Lines</h2>";

    $result = $conn->query("
        SELECT DISTINCT city, line_number
        FROM bus_schedule
        WHERE approved = 1
        ORDER BY city, line_number
    ");

    while ($row = $result->fetch_assoc()) {
        $lineEsc = htmlspecialchars($row['line_number'], ENT_QUOTES, 'UTF-8');
        $cityEsc = htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8');

        echo "<p><a href='index.php?action=line&line={$lineEsc}&city={$cityEsc}'>
            {$cityEsc} — Line {$lineEsc}
        </a></p>";
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
    $line = $_GET['line'] ?? '';
    $city = $_GET['city'] ?? '';

    $lineEsc = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    $cityEsc = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');

    echo "<h2>Stops for {$cityEsc} — Line {$lineEsc}</h2>";

    $stmt = $conn->prepare("
        SELECT DISTINCT stop_name
        FROM bus_schedule
        WHERE line_number = ? AND city = ? AND approved = 1
        ORDER BY stop_name
    ");
    $stmt->bind_param('ss', $line, $city);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stopEsc = htmlspecialchars($row['stop_name'], ENT_QUOTES, 'UTF-8');
        echo "<p><a href='index.php?action=timetable&line={$lineEsc}&city={$cityEsc}&stop={$stopEsc}'>{$stopEsc}</a></p>";
    }

    echo "<p><a href='index.php'>Back</a></p>";
}

// -------------------------
// TIMETABLE
// -------------------------
if ($action === 'timetable') {
    $line = $_GET['line'] ?? '';
    $city = $_GET['city'] ?? '';
    $stop = $_GET['stop'] ?? '';

    $lineEsc = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    $cityEsc = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');
    $stopEsc = htmlspecialchars($stop, ENT_QUOTES, 'UTF-8');

    echo "<h2>Timetable for {$cityEsc} — Line {$lineEsc} — Stop: {$stopEsc}</h2>";

    $stmt = $conn->prepare("
        SELECT day_of_week, time
        FROM bus_schedule
        WHERE line_number = ? AND city = ? AND stop_name = ? AND approved = 1
        ORDER BY FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), time
    ");
    $stmt->bind_param('sss', $line, $city, $stop);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<div class='table-wrap'><table>";
    echo "<tr><th>Day</th><th>Time</th></tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . htmlspecialchars($row['day_of_week']) . "</td>
            <td>" . htmlspecialchars($row['time']) . "</td>
        </tr>";
    }

    echo "</table></div>";
    echo "<p><a href='index.php?action=line&line={$lineEsc}&city={$cityEsc}'>Back</a></p>";
}

// -------------------------
// SUBMISSION FORM
// -------------------------
if ($action === 'submit') {
?>
    <h2>Submit Bus Time</h2>
    <form method="POST" action="index.php?action=submit_save">
        Line Number: <input type="text" name="line_number" required><br><br>
        City: <input type="text" name="city" required><br><br>
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

    $result = $conn->query("
        SELECT *
        FROM bus_schedule
        WHERE approved = 0
        ORDER BY submitted_at
    ");

    echo "<div class='table-wrap'><table>";
    echo "<tr><th>ID</th><th>Line</th><th>City</th><th>Stop</th><th>Day</th><th>Time</th><th>Actions</th></tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['id']}</td>
            <td>" . htmlspecialchars($row['line_number']) . "</td>
            <td>" . htmlspecialchars($row['city']) . "</td>
            <td>" . htmlspecialchars($row['stop_name']) . "</td>
            <td>" . htmlspecialchars($row['day_of_week']) . "</td>
            <td>" . htmlspecialchars($row['time']) . "</td>
            <td>
                <a href='index.php?action=approve&id={$row['id']}'>Approve</a> |
                <a href='index.php?action=reject&id={$row['id']}'>Reject</a>
            </td>
        </tr>";
    }

    echo "</table></div>";
    echo "<p><a href='index.php'>Back</a></p>";
}
?>

</main>

<?php include_footer(); ?>
