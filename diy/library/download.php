<?php
/**
 * Ebook Library - Download Page
 * Handles ebook downloads with email requirement
 */

require_once __DIR__ . '/../../includes/reusable.php';

// Track page visit
if (function_exists('trackVisit')) {
    trackVisit('diy-library-download');
}

// Get ebook ID
$ebookId = intval($_GET['id'] ?? 0);

if ($ebookId <= 0) {
    header('Location: index.php');
    exit;
}

// Get ebook details
$stmt = $conn->prepare("SELECT * FROM ebooks WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $ebookId);
$stmt->execute();
$result = $stmt->get_result();
$ebook = $result->fetch_assoc();
$stmt->close();

if (!$ebook) {
    header('Location: index.php');
    exit;
}

// Check if file exists
$filePath = __DIR__ . '/' . $ebook['file_path'];
if (!file_exists($filePath)) {
    $error = 'The requested file is not available. Please contact support.';
}

// CSRF token
$csrfToken = null;
if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
}

// Form processing
$downloadError = null;
$downloadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$downloadError) {
    // CSRF validation
    if (function_exists('csrf_token')) {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if ($submittedToken !== $csrfToken) {
            $downloadError = 'Security validation failed. Please try again.';
        }
    }
    
    if (!$downloadError) {
        $email = trim($_POST['email'] ?? '');
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $downloadError = 'Please enter a valid email address.';
        }
        
        if (!$downloadError) {
            // Get current downloads JSON
            $stmt = $conn->prepare("SELECT downloads_json FROM ebooks WHERE id = ?");
            $stmt->bind_param("i", $ebookId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            $downloads = [];
            if (!empty($row['downloads_json'])) {
                $downloads = json_decode($row['downloads_json'], true);
                if (!is_array($downloads)) {
                    $downloads = [];
                }
            }
            
            // Add new download record
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $downloads[] = [
                'email' => $email,
                'download_date' => date('Y-m-d H:i:s'),
                'ip_address' => $ipAddress
            ];
            
            // Update with new downloads JSON
            $downloadsJson = json_encode($downloads);
            $updateQuery = "UPDATE ebooks SET download_count = download_count + 1, downloads_json = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $downloadsJson, $ebookId);
            
            if ($updateStmt->execute()) {
                $downloadSuccess = true;
            } else {
                $downloadError = 'Failed to record download. Please try again.';
            }
            $updateStmt->close();
        }
    }
}

// If download is successful, serve the file
if ($downloadSuccess && !$downloadError) {
    // Get file info
    $fileSize = filesize($filePath);
    $fileType = mime_content_type($filePath);
    
    // Set appropriate content type based on file extension
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $contentType = match($extension) {
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip' => 'application/zip',
        default => $fileType
    };
    
    // Clean output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers for file download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $ebook['title'] . '.' . $extension . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit;
}

// Render layout for email form
include_menu();
include_header();
render_top_userbar();
?>

<div class="container">
    <div class="header">
        <h1>📥 Download Ebook</h1>
    </div>

    <div style="margin: 15px 0;">
        <a href="index.php" style="display: inline-block; padding: 8px 16px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px;">← Back to Library</a>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb;">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($downloadError): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb;">
            <strong>Error:</strong> <?php echo htmlspecialchars($downloadError); ?>
        </div>
    <?php endif; ?>

    <?php if (!isset($error) && !$downloadSuccess): ?>
        <div class="card" style="max-width: 500px; margin: 20px auto;">
            <div style="padding: 20px;">
                <h2 style="margin-top: 0;"><?php echo htmlspecialchars($ebook['title']); ?></h2>
                
                <?php if (!empty($ebook['author'])): ?>
                    <p style="color: #666; margin-bottom: 15px;">
                        <strong>Author:</strong> <?php echo htmlspecialchars($ebook['author']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($ebook['description'])): ?>
                    <p style="line-height: 1.6; margin-bottom: 15px;">
                        <?php echo htmlspecialchars($ebook['description']); ?>
                    </p>
                <?php endif; ?>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                    <p style="margin: 5px 0;">
                        <strong>File Type:</strong> <?php echo strtoupper($ebook['file_type']); ?>
                    </p>
                    <p style="margin: 5px 0;">
                        <strong>File Size:</strong> <?php echo number_format($ebook['file_size'] / 1024 / 1024, 2); ?> MB
                    </p>
                    <p style="margin: 5px 0;">
                        <strong>Downloads:</strong> <?php echo $ebook['download_count']; ?>
                    </p>
                </div>
                
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            Enter your email to download *
                        </label>
                        <input type="email" id="email" name="email" required
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;"
                               placeholder="your@email.com">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Your email will be used for download tracking purposes only.
                        </small>
                    </div>
                    
                    <button type="submit" 
                            style="width: 100%; padding: 12px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 16px;">
                        📥 Download Now
                    </button>
                </form>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <h3 style="margin-top: 0;">🔒 Privacy Notice</h3>
            <p style="color: #555; line-height: 1.6;">
                Your email address is collected solely for download tracking and statistical purposes. 
                We do not share your email with third parties. By providing your email, you agree to our 
                download tracking policy.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php include_footer(); ?>