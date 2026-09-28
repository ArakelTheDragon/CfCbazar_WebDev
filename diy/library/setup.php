<?php
/**
 * Ebook Library Setup Script
 * Creates the necessary database tables for the ebook library
 */

// Enable error reporting for setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/reusable.php';

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
echo "a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>📚 Ebook Library Database Setup</h1>";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo "<div class='error'>✗ Database connection failed: " . ($conn->connect_error ?? 'Connection not established') . "</div>";
    echo "<p>Please check your database configuration in config.php</p>";
    echo "</body></html>";
    exit;
}

echo "<div class='info'>Database connection: ✓ Connected</div>";

// Create combined ebooks table
$createEbooksTable = "
CREATE TABLE IF NOT EXISTS ebooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    author VARCHAR(255),
    file_path VARCHAR(512) NOT NULL,
    file_type ENUM('pdf', 'doc', 'docx', 'zip') NOT NULL,
    file_size BIGINT NOT NULL,
    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(255) NOT NULL,
    download_count INT DEFAULT 0,
    downloads_json TEXT DEFAULT NULL,
    category VARCHAR(100),
    tags VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    INDEX (title),
    INDEX (category),
    INDEX (file_type),
    INDEX (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    if ($conn->query($createEbooksTable)) {
        echo "<div class='success'>✓ Ebooks table created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating ebooks table: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Exception creating ebooks table: " . $e->getMessage() . "</div>";
}

// Drop the old ebook_downloads table if it exists
$dropOldTable = "DROP TABLE IF EXISTS ebook_downloads";
try {
    if ($conn->query($dropOldTable)) {
        echo "<div class='info'>ℹ Old ebook_downloads table removed (migrated to single table)</div>";
    }
} catch (Exception $e) {
    // Ignore if table doesn't exist
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "<div class='success'>✓ Uploads directory created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating uploads directory</div>";
    }
} else {
    echo "<div class='info'>ℹ Uploads directory already exists</div>";
}

// Verify tables exist
$checkEbooks = $conn->query("SHOW TABLES LIKE 'ebooks'");
$checkDownloads = $conn->query("SHOW TABLES LIKE 'ebook_downloads'");

echo "<h2>Setup Verification</h2>";
if ($checkEbooks && $checkEbooks->num_rows > 0) {
    echo "<div class='success'>✓ ebooks table exists</div>";
} else {
    echo "<div class='error'>✗ ebooks table not found</div>";
}

if ($checkDownloads && $checkDownloads->num_rows > 0) {
    echo "<div class='success'>✓ ebook_downloads table exists</div>";
} else {
    echo "<div class='error'>✗ ebook_downloads table not found</div>";
}

echo "<a href='index.php'>Go to Ebook Library →</a>";
echo "</body>";
echo "</html>";
?>