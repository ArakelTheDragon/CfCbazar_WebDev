<?php
/**
 * Ebook Library - Edit Page
 * Allows admins (status 1) and contributors (status 3) to edit ebook details
 */

require_once __DIR__ . '/../../includes/reusable.php';

// Track page visit
if (function_exists('trackVisit')) {
    trackVisit('diy-library-edit');
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

// Get ebook ID
$ebookId = intval($_GET['id'] ?? 0);

if ($ebookId <= 0) {
    header('Location: index.php');
    exit;
}

// Get ebook details
$stmt = $conn->prepare("SELECT * FROM ebooks WHERE id = ?");
$stmt->bind_param("i", $ebookId);
$stmt->execute();
$result = $stmt->get_result();
$ebook = $result->fetch_assoc();
$stmt->close();

if (!$ebook) {
    header('Location: index.php');
    exit;
}

// Check if user can edit this ebook (admins can edit all, contributors can only edit their own)
if ($userStatus === 3 && $ebook['uploaded_by'] !== $email) {
    header('Location: index.php');
    exit;
}

// CSRF token
$csrfToken = null;
if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
}

// Form processing
$editError = null;
$editSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (function_exists('csrf_token')) {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if ($submittedToken !== $csrfToken) {
            $editError = 'Security validation failed. Please try again.';
        }
    }
    
    // Check for delete action
    if (isset($_POST['delete_ebook']) && !$editError) {
        // Delete the file
        $filePath = __DIR__ . '/' . $ebook['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database (no need to clean up separate table anymore)
        $deleteStmt = $conn->prepare("DELETE FROM ebooks WHERE id = ?");
        $deleteStmt->bind_param("i", $ebookId);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        header('Location: index.php?deleted=1');
        exit;
    }
    
    if (!$editError) {
        // Validate fields
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($title)) {
            $editError = 'Title is required.';
        }
        
        if (!$editError) {
            // Update database
            $updateQuery = "
                UPDATE ebooks 
                SET title = ?, description = ?, author = ?, category = ?, tags = ?, is_active = ?
                WHERE id = ?
            ";
            
            $stmt = $conn->prepare($updateQuery);
            if ($stmt) {
                $stmt->bind_param(
                    'ssssiii',
                    $title,
                    $description,
                    $author,
                    $category,
                    $tags,
                    $isActive,
                    $ebookId
                );
                
                if ($stmt->execute()) {
                    $editSuccess = 'Ebook updated successfully!';
                    $stmt->close();
                    // Refresh ebook data
                    $refreshStmt = $conn->prepare("SELECT * FROM ebooks WHERE id = ?");
                    $refreshStmt->bind_param("i", $ebookId);
                    $refreshStmt->execute();
                    $result = $refreshStmt->get_result();
                    $ebook = $result->fetch_assoc();
                    $refreshStmt->close();
                } else {
                    $editError = 'Database error: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $editError = 'Database preparation failed: ' . $conn->error;
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
        <h1>✏️ Edit Ebook</h1>
        <p>Modify ebook details (Admins and Contributors only)</p>
    </div>

    <div style="margin: 15px 0;">
        <a href="index.php" style="display: inline-block; padding: 8px 16px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px;">← Back to Library</a>
    </div>

    <?php if ($editError): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb;">
            <strong>Error:</strong> <?php echo htmlspecialchars($editError); ?>
        </div>
    <?php endif; ?>

    <?php if ($editSuccess): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #c3e6cb;">
            <strong>Success:</strong> <?php echo htmlspecialchars($editSuccess); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 20px auto;">
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div style="margin-bottom: 15px;">
                <label for="title" style="display: block; margin-bottom: 5px; font-weight: bold;">Title *</label>
                <input type="text" id="title" name="title" required 
                       value="<?php echo htmlspecialchars($ebook['title']); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="author" style="display: block; margin-bottom: 5px; font-weight: bold;">Author</label>
                <input type="text" id="author" name="author" 
                       value="<?php echo htmlspecialchars($ebook['author'] ?? ''); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="description" style="display: block; margin-bottom: 5px; font-weight: bold;">Description</label>
                <textarea id="description" name="description" rows="4"
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?php echo htmlspecialchars($ebook['description'] ?? ''); ?></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Category</label>
                <input type="text" id="category" name="category" 
                       value="<?php echo htmlspecialchars($ebook['category'] ?? ''); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="tags" style="display: block; margin-bottom: 5px; font-weight: bold;">Tags</label>
                <input type="text" id="tags" name="tags" 
                       value="<?php echo htmlspecialchars($ebook['tags'] ?? ''); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="is_active" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <input type="checkbox" id="is_active" name="is_active" 
                           <?php echo $ebook['is_active'] ? 'checked' : ''; ?> style="margin-right: 8px;">
                    Active (visible in library)
                </label>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h4 style="margin-top: 0;">File Information</h4>
                <p style="margin: 5px 0;"><strong>File Type:</strong> <?php echo strtoupper($ebook['file_type']); ?></p>
                <p style="margin: 5px 0;"><strong>File Size:</strong> <?php echo number_format($ebook['file_size'] / 1024 / 1024, 2); ?> MB</p>
                <p style="margin: 5px 0;"><strong>Upload Date:</strong> <?php echo $ebook['upload_date']; ?></p>
                <p style="margin: 5px 0;"><strong>Uploaded By:</strong> <?php echo htmlspecialchars($ebook['uploaded_by']); ?></p>
                <p style="margin: 5px 0;"><strong>Downloads:</strong> <?php echo $ebook['download_count']; ?></p>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" 
                        style="flex: 1; padding: 10px 20px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    💾 Save Changes
                </button>
                
                <?php if ($userStatus === 1): // Only admins can delete ?>
                    <button type="submit" name="delete_ebook" 
                            onclick="return confirm('Are you sure you want to delete this ebook? This action cannot be undone.');"
                            style="flex: 1; padding: 10px 20px; background-color: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                        🗑️ Delete Ebook
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin-top: 0;">📋 Edit Guidelines</h3>
        <ul style="color: #555; line-height: 1.6;">
            <li>Keep titles and descriptions accurate and helpful</li>
            <li>Use appropriate categories and tags for better discoverability</li>
            <li>Deactivate ebooks instead of deleting if they might be needed later</li>
            <li>Only admins can delete ebooks permanently</li>
            <li>Contributors can only edit ebooks they uploaded</li>
        </ul>
    </div>
</div>

<?php include_footer(); ?>