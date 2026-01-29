<?php
require_once __DIR__ . '/includes/reusable.php';
$title = 'Digital Product Tracker';
include_header();
include_menu();

echo '<main class="tracker">';
echo '<h2>📦 Track Your Digital Product</h2>';

// JSON API mode
if (isset($_GET['code']) && !isset($_POST['tracking_code'])) {
    $code = trim($_GET['code']);
    $stmt = $conn->prepare("SELECT product_name, status FROM digital_orders WHERE tracking_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode([
            "tracking_code" => $code,
            "product" => $row["product_name"],
            "status" => $row["status"]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Tracking code not found"]);
    }
    exit;
}

// Interactive form flow
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["tracking_code"])) {
        $code = trim($_POST["tracking_code"]);
        $stmt = $conn->prepare("SELECT product_name, status FROM digital_orders WHERE tracking_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo "<p><strong>Product:</strong> " . htmlspecialchars($row["product_name"]) . "</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($row["status"]) . "</p>";
            echo "<form method='post'>
                    <input type='hidden' name='complete_code' value='" . htmlspecialchars($code) . "'>
                    <button type='submit'>✅ Mark as Completed to Download</button>
                  </form>";
        } else {
            echo "<p>❌ Tracking number not found.</p>";
        }
    }

    if (isset($_POST["complete_code"])) {
        $code = $_POST["complete_code"];
        $stmt = $conn->prepare("UPDATE digital_orders SET status='completed' WHERE tracking_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT drive_link FROM digital_orders WHERE tracking_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo "<p>🎉 Thank you! Your order is marked as completed.</p>";
        echo "<p><a href='" . htmlspecialchars($row["drive_link"]) . "' target='_blank'>📥 Click here to download</a></p>";
    }
}

// Initial form
echo '<form method="post" class="track-form">';
echo '<label for="tracking_code">Enter your tracking number:</label><br>';
echo '<input type="text" name="tracking_code" id="tracking_code" required>';
echo '<button type="submit">🔍 Track</button>';
echo '</form>';

echo '</main>';
include_footer();
?>