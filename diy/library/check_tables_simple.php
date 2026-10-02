<?php
/**
 * Simple Database Tables Check - No downloads_json dependency
 */

require_once __DIR__ . '/../../includes/reusable.php';

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Database Tables Check</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; }";
echo "h1 { color: #333; }";
echo "table { width: 100%; border-collapse: collapse; margin: 20px 0; }";
echo "th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }";
echo "th { background: #4CAF50; color: white; }";
echo ".success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".info { color: #004085; background: #cce5ff; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo "a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>📊 Database Tables Check</h1>";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo "<div class='error'>✗ Database connection failed</div>";
    echo "</body></html>";
    exit;
}

echo "<div class='success'>✓ Database connected successfully</div>";

// Get all tables
$tablesResult = $conn->query("SHOW TABLES");
if ($tablesResult) {
    $tables = [];
    while ($row = $tablesResult->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "<h2>Tables in Database (" . count($tables) . " total)</h2>";
    echo "<table>";
    echo "<tr><th>Table Name</th><th>Status</th></tr>";
    
    foreach ($tables as $table) {
        $status = '';
        if ($table === 'ebooks') {
            $status = '<span style="color: green;">✓ Ebooks table exists</span>';
        } elseif ($table === 'ebook_downloads') {
            $status = '<span style="color: orange;">⚠ Old downloads table (should be removed)</span>';
        } else {
            $status = 'Other table';
        }
        echo "<tr><td>" . htmlspecialchars($table) . "</td><td>" . $status . "</td></tr>";
    }
    echo "</table>";
    
    // Check for specific tables
    echo "<h2>Ebook Library Tables Status</h2>";
    
    if (in_array('ebooks', $tables)) {
        echo "<div class='success'>✓ ebooks table exists</div>";
        
        // Show ebooks table structure
        $structure = $conn->query("DESCRIBE ebooks");
        if ($structure) {
            echo "<h3>ebooks Table Structure</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Default'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check if downloads_json column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM ebooks LIKE 'downloads_json'");
        $hasDownloadsJson = ($columnCheck && $columnCheck->num_rows > 0);
        
        if ($hasDownloadsJson) {
            echo "<div class='success'>✓ downloads_json column exists</div>";
            
            // Show ebooks with download data
            $ebooksData = $conn->query("SELECT id, title, download_count, downloads_json FROM ebooks ORDER BY id DESC LIMIT 5");
            if ($ebooksData && $ebooksData->num_rows > 0) {
                echo "<h3>Recent Ebooks with Download Data</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Title</th><th>Download Count</th><th>Recent Downloads (JSON)</th></tr>";
                while ($row = $ebooksData->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['download_count']) . "</td>";
                    
                    $downloadsJson = $row['downloads_json'];
                    if (!empty($downloadsJson)) {
                        $downloads = json_decode($downloadsJson, true);
                        if (is_array($downloads) && count($downloads) > 0) {
                            $recentDownloads = array_slice($downloads, -3); // Show last 3 downloads
                            $downloadInfo = [];
                            foreach ($recentDownloads as $dl) {
                                $downloadInfo[] = htmlspecialchars($dl['email'] ?? 'unknown') . ' (' . htmlspecialchars($dl['download_date'] ?? 'unknown') . ')';
                            }
                            echo "<td>" . implode('<br>', $downloadInfo) . "</td>";
                        } else {
                            echo "<td>No valid download data</td>";
                        }
                    } else {
                        echo "<td>No downloads yet</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No ebooks found yet.</p>";
            }
        } else {
            echo "<div class='error'>✗ downloads_json column missing from ebooks table</div>";
            echo "<p><strong>To fix this, run the setup script:</strong> <a href='setup.php'>setup.php</a></p>";
            
            // Show basic ebook info without downloads_json
            $ebooksData = $conn->query("SELECT id, title, download_count FROM ebooks ORDER BY id DESC LIMIT 5");
            if ($ebooksData && $ebooksData->num_rows > 0) {
                echo "<h3>Recent Ebooks (Basic Info)</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Title</th><th>Download Count</th></tr>";
                while ($row = $ebooksData->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['download_count']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    } else {
        echo "<div class='error'>✗ ebooks table NOT found</div>";
        echo "<p><strong>To fix this, run the setup script:</strong> <a href='setup.php'>setup.php</a></p>";
    }
    
    if (in_array('ebook_downloads', $tables)) {
        echo "<div class='info'>ℹ Old ebook_downloads table still exists (will be removed on next setup run)</div>";
        echo "<p><strong>To remove it now, run the setup script:</strong> <a href='setup.php'>setup.php</a></p>";
    }
    
} else {
    echo "<div class='error'>✗ Could not retrieve table list</div>";
}

echo "<a href='index.php'>Back to Ebook Library</a>";
echo "</body>";
echo "</html>";
?>