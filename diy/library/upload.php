<?php
/**
 * Ebook Library - Upload Page
 * Allows admins (status 1) and contributors (status 3) to upload ebooks
 */

require_once __DIR__ . '/../../includes/reusable.php';

// Track page visit
if (function_exists('trackVisit')) {
    trackVisit('diy-library-upload');
}

// Check user permissions
$userStatus = function_exists('getUserStatus') ? (int)getUserStatus() : 0;
if (!in_array($userStatus, [1, 3], true)) {
    // Not authorized - redirect to library
    header('Location: index.php');
    exit;
}

// Get user email
$email = null;
if (function_exists('is_logged_in')) {
    is_logged_in($email);
}

if (!$email) {
    header('Location: /login.php');
    exit;
}

// Configuration
$allowedTypes = ['pdf', 'doc', 'docx', 'zip'];
$maxFileSize = 50 * 1024 * 1024; // 50MB
$uploadDir = __DIR__ . '/uploads/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// CSRF token
$csrfToken = null;
if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
}

// Form processing
$uploadError = null;
$uploadSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (function_exists('csrf_token')) {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if ($submittedToken !== $csrfToken) {
            $uploadError = 'Security validation failed. Please try again.';
        }
    }
    
    if (!$uploadError) {
        // Validate file upload
        if (!isset($_FILES['ebook_file']) || $_FILES['ebook_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'File upload failed. Please try again.';
        } else {
            $file = $_FILES['ebook_file'];
            
            // Check file size
            if ($file['size'] > $maxFileSize) {
                $uploadError = 'File size exceeds maximum limit of 50MB.';
            }
            
            // Check file type
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedTypes)) {
                $uploadError = 'Invalid file type. Only PDF, DOC, DOCX, and ZIP files are allowed.';
            }
            
            // Validate other fields
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $author = trim($_POST['author'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            
            if (empty($title)) {
                $uploadError = 'Title is required.';
            }
            
            if (!$uploadError) {
                // Generate unique filename
                $uniqueFilename = uniqid('ebook_', true) . '.' . $fileExtension;
                $uploadPath = $uploadDir . $uniqueFilename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    // Insert into database
                    $insertQuery = "
                        INSERT INTO ebooks (title, description, author, file_path, file_type, file_size, uploaded_by, category, tags)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $stmt = $conn->prepare($insertQuery);
                    if ($stmt) {
                        $relativePath = 'uploads/' . $uniqueFilename;
                        $stmt->bind_param(
                            'ssssissss',
                            $title,
                            $description,
                            $author,
                            $relativePath,
                            $fileExtension,
                            $file['size'],
                            $email,
                            $category,
                            $tags
                        );
                        
                        if ($stmt->execute()) {
                            $uploadSuccess = 'Ebook uploaded successfully!';
                        } else {
                            $uploadError = 'Database error: ' . $stmt->error;
                            // Clean up uploaded file on database error
                            unlink($uploadPath);
                        }
                        $stmt->close();
                    } else {
                        $uploadError = 'Database preparation failed: ' . $conn->error;
                        unlink($uploadPath);
                    }
                } else {
                    $uploadError = 'Failed to move uploaded file. Please check directory permissions.';
                }
            }
        }
    }
}

// Render layout
include_menu();
include_header();
render_top_userbar();
?>

<div class="container">
    <div class="header">
        <h1>📤 Upload Ebook</h1>
        <p>Add a new ebook to the library (Admins and Contributors only)</p>
    </div>

    <div style="margin: 15px 0;">
        <a href="index.php" style="display: inline-block; padding: 8px 16px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px;">← Back to Library</a>
    </div>

    <?php if ($uploadError): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb;">
            <strong>Error:</strong> <?php echo htmlspecialchars($uploadError); ?>
        </div>
    <?php endif; ?>

    <?php if ($uploadSuccess): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #c3e6cb;">
            <strong>Success:</strong> <?php echo htmlspecialchars($uploadSuccess); ?>
            <div style="margin-top: 10px;">
                <a href="index.php" style="color: #155724; text-decoration: underline;">View Library</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 20px auto;">
        <form method="POST" enctype="multipart/form-data" style="padding: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div style="margin-bottom: 15px;">
                <label for="title" style="display: block; margin-bottom: 5px; font-weight: bold;">Title *</label>
                <input type="text" id="title" name="title" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                       placeholder="Enter ebook title">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="author" style="display: block; margin-bottom: 5px; font-weight: bold;">Author</label>
                <input type="text" id="author" name="author" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                       placeholder="Enter author name">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="description" style="display: block; margin-bottom: 5px; font-weight: bold;">Description</label>
                <textarea id="description" name="description" rows="4"
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"
                          placeholder="Enter a brief description of the ebook"></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Category</label>
                <input type="text" id="category" name="category" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                       placeholder="e.g., Technology, Cooking, Business">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="tags" style="display: block; margin-bottom: 5px; font-weight: bold;">Tags</label>
                <input type="text" id="tags" name="tags" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                       placeholder="Comma-separated tags (e.g., programming, guide, tutorial)">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="ebook_file" style="display: block; margin-bottom: 5px; font-weight: bold;">Ebook File *</label>
                <input type="file" id="ebook_file" name="ebook_file" required
                       accept=".pdf,.doc,.docx,.zip"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666;">Allowed formats: PDF, DOC, DOCX, ZIP (Max 50MB)</small>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" 
                        style="padding: 10px 20px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    📤 Upload Ebook
                </button>
            </div>
        </form>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin-top: 0;">📋 Upload Guidelines</h3>
        <ul style="color: #555; line-height: 1.6;">
            <li>Only upload files you have the rights to distribute</li>
            <li>Provide accurate titles, descriptions, and author information</li>
            <li>Use appropriate categories and tags for better discoverability</li>
            <li>Maximum file size is 50MB</li>
            <li>Supported formats: PDF, DOC, DOCX, ZIP</li>
            <li>All uploads are moderated and may be reviewed by administrators</li>
        </ul>
    </div>
</div>

<?php include_footer(); ?>