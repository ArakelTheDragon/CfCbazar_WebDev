<?php
/**
 * Standalone Ebook Library Setup Script
 * Adds downloads_json column to existing ebooks table
 * Uses direct database connection to avoid config issues
 */

// Database credentials - update these if needed
$dbHost = 'sql313.infinityfree.com';
$dbUser = 'if0_39103611';
$dbPass = ''; // You'll need to fill this in
$dbName = 'if0_39103611_db1';

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Ebook Library Setup</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }";
echo "h1 { color: #333; }";
echo ".success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".info { color: #004085; background: #cce5ff; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo "input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }";
echo "button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }";
echo "a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>📚 Ebook Library Database Setup</h1>";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? $dbHost;
    $dbUser = $_POST['db_user'] ?? $dbUser;
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = $_POST['db_name'] ?? $dbName;
    
    // Try to connect
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    if ($conn->connect_error) {
        echo "<div class='error'>✗ Database connection failed: " . $conn->connect_error . "</div>";
        echo "<p>Please check your database credentials and try again.</p>";
    } else {
        echo "<div class='success'>✓ Database connected successfully</div>";
        
        // Check if downloads_json column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM ebooks LIKE 'downloads_json'");
        $hasDownloadsJson = ($columnCheck && $columnCheck->num_rows > 0);
        
        if ($hasDownloadsJson) {
            echo "<div class='info'>ℹ downloads_json column already exists</div>";
        } else {
            // Add the downloads_json column
            $addColumn = "ALTER TABLE ebooks ADD COLUMN downloads_json TEXT DEFAULT NULL AFTER download_count";
            
            if ($conn->query($addColumn)) {
                echo "<div class='success'>✓ downloads_json column added successfully</div>";
            } else {
                echo "<div class='error'>✗ Error adding downloads_json column: " . $conn->error . "</div>";
            }
        }
        
        // Check if ebook_downloads table exists and drop it
        $tableCheck = $conn->query("SHOW TABLES LIKE 'ebook_downloads'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $dropTable = "DROP TABLE IF EXISTS ebook_downloads";
            if ($conn->query($dropTable)) {
                echo "<div class='info'>ℹ Old ebook_downloads table removed</div>";
            }
        }
        
        // Verify the column was added
        $verifyCheck = $conn->query("SHOW COLUMNS FROM ebooks LIKE 'downloads_json'");
        if ($verifyCheck && $verifyCheck->num_rows > 0) {
            echo "<div class='success'>✓ Setup completed successfully!</div>";
            echo "<p><a href='index.php'>Go to Ebook Library</a></p>";
        } else {
            echo "<div class='error'>✗ Setup verification failed</div>";
        }
        
        $conn->close();
    }
} else {
    // Show form
    echo "<p>Please enter your database credentials to add the downloads_json column:</p>";
    echo "<form method='POST'>";
    echo "<div>";
    echo "<label>Database Host:</label><br>";
    echo "<input type='text' name='db_host' value='" . htmlspecialchars($dbHost) . "'><br>";
    echo "</div>";
    echo "<div>";
    echo "<label>Database User:</label><br>";
    echo "<input type='text' name='db_user' value='" . htmlspecialchars($dbUser) . "'><br>";
    echo "</div>";
    echo "<div>";
    echo "<label>Database Password:</label><br>";
    echo "<input type='password' name='db_pass'><br>";
    echo "</div>";
    echo "<div>";
    echo "<label>Database Name:</label><br>";
    echo "<input type='text' name='db_name' value='" . htmlspecialchars($dbName) . "'><br>";
    echo "</div>";
    echo "<br>";
    echo "<button type='submit'>Run Setup</button>";
    echo "</form>";
    
    echo "<p><small>Your database credentials are from your config.php file. You can find them in includes/secrets.php</small></p>";
}

echo "</body>";
echo "</html>";
?>