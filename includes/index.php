<?php
/**
 * Ebook Library - Main Index Page
 * Search and browse ebooks with download functionality
 */

require_once __DIR__ . '/../../includes/reusable.php';

// Track page visit
if (function_exists('trackVisit')) {
    trackVisit('diy-library');
}

// Check user status for upload permissions
$userStatus = function_exists('getUserStatus') ? (int)getUserStatus() : 0;
$canUpload = in_array($userStatus, [1, 3], true); // Admin (1) and Contributors (3)

// Get user email for edit permissions
$email = null;
if (function_exists('is_logged_in')) {
    is_logged_in($email);
}

// Get search parameters
$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$fileTypeFilter = trim($_GET['file_type'] ?? '');

// Build query for ebooks
$showAll = ($userStatus === 1 && isset($_GET['show_all']));
$whereConditions = $showAll ? ["1=1"] : ["is_active = 1"];
$params = [];
$types = "";

if (!empty($searchQuery)) {
    $whereConditions[] = "(title LIKE ? OR description LIKE ? OR author LIKE ? OR tags LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

if (!empty($fileTypeFilter)) {
    $whereConditions[] = "file_type = ?";
    $params[] = $fileTypeFilter;
    $types .= "s";
}

$whereClause = implode(' AND ', $whereConditions);

// Get ebooks with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM ebooks WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalEbooks = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalEbooks / $perPage);
$countStmt->close();

// Get ebooks
$ebooksQuery = "SELECT * FROM ebooks WHERE $whereClause ORDER BY upload_date DESC LIMIT ? OFFSET ?";
$ebooksStmt = $conn->prepare($ebooksQuery);
$types .= "ii";
$params[] = $perPage;
$params[] = $offset;
$ebooksStmt->bind_param($types, ...$params);
$ebooksStmt->execute();
$ebooksResult = $ebooksStmt->get_result();
$ebooks = [];
while ($row = $ebooksResult->fetch_assoc()) {
    $ebooks[] = $row;
}
$ebooksStmt->close();

// Get unique categories for filter
$categoriesQuery = "SELECT DISTINCT category FROM ebooks WHERE category IS NOT NULL AND category != '' AND is_active = 1 ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Render layout
include_menu();
include_header();
render_top_userbar();
?>

<div class="container">
    <div class="header">
        <h1>📚 Ebook Library</h1>
        <p>
            <?php if ($showAll): ?>
                <strong>Admin Mode - Viewing All Ebooks (Including Inactive)</strong>
            <?php else: ?>
                Browse and download free ebooks, guides, and resources
            <?php endif; ?>
        </p>
    </div>

    <?php if ($canUpload): ?>
        <div style="margin: 15px 0; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="upload.php" style="display: inline-block; padding: 10px 16px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">➕ Upload New Ebook</a>
            <?php if ($userStatus === 1): ?>
                <a href="index.php?show_all=1" style="display: inline-block; padding: 10px 16px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">📋 Manage All Ebooks</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="search-box" style="margin: 20px 0;">
        <form method="GET" action="index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
                <input type="text" id="search" name="search" placeholder="Search by title, author, description..." 
                       value="<?php echo htmlspecialchars($searchQuery); ?>" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="min-width: 150px;">
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Category:</label>
                <select id="category" name="category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="min-width: 120px;">
                <label for="file_type" style="display: block; margin-bottom: 5px; font-weight: bold;">File Type:</label>
                <select id="file_type" name="file_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">All Types</option>
                    <option value="pdf" <?php echo $fileTypeFilter === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                    <option value="doc" <?php echo $fileTypeFilter === 'doc' ? 'selected' : ''; ?>>DOC</option>
                    <option value="docx" <?php echo $fileTypeFilter === 'docx' ? 'selected' : ''; ?>>DOCX</option>
                    <option value="zip" <?php echo $fileTypeFilter === 'zip' ? 'selected' : ''; ?>>ZIP</option>
                </select>
            </div>
            
            <div>
                <button type="submit" style="padding: 8px 16px; background-color: #4caf50; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Search</button>
                <a href="index.php" style="padding: 8px 16px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; display: inline-block;">Clear</a>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <div style="margin: 10px 0; color: #666;">
        <?php if (isset($_GET['deleted'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                Ebook deleted successfully.
            </div>
        <?php endif; ?>
        
        <?php if ($totalEbooks > 0): ?>
            <p>Showing <?php echo count($ebooks); ?> of <?php echo $totalEbooks; ?> ebooks</p>
        <?php else: ?>
            <p>No ebooks found matching your criteria.</p>
        <?php endif; ?>
    </div>

    <!-- Ebook Grid -->
    <?php if (!empty($ebooks)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin: 20px 0;">
            <?php foreach ($ebooks as $ebook): ?>
                <div class="card" style="border: 1px solid <?php echo $ebook['is_active'] ? '#ddd' : '#ffc107'; ?>; border-radius: 8px; padding: 15px; background: <?php echo $ebook['is_active'] ? '#fff' : '#fffdf0'; ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 24px; margin-right: 10px;">
                            <?php
                            $icon = match($ebook['file_type']) {
                                'pdf' => '📕',
                                'doc' => '📘',
                                'docx' => '📘',
                                'zip' => '📦',
                                default => '📄'
                            };
                            echo $icon;
                            ?>
                        </span>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; font-size: 16px; color: #333;">
                                <?php echo htmlspecialchars($ebook['title']); ?>
                                <?php if (!$ebook['is_active']): ?>
                                    <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">INACTIVE</span>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($ebook['author'])): ?>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">By <?php echo htmlspecialchars($ebook['author']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($ebook['description'])): ?>
                        <p style="margin: 10px 0; font-size: 13px; color: #555; line-height: 1.4;">
                            <?php echo htmlspecialchars(substr($ebook['description'], 0, 150)); ?>
                            <?php echo strlen($ebook['description']) > 150 ? '...' : ''; ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="margin: 10px 0; font-size: 12px; color: #888;">
                        <span style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; margin-right: 5px;">
                            <?php echo strtoupper($ebook['file_type']); ?>
                        </span>
                        <?php if (!empty($ebook['category'])): ?>
                            <span style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; margin-right: 5px;">
                                <?php echo htmlspecialchars($ebook['category']); ?>
                            </span>
                        <?php endif; ?>
                        <span style="margin-left: 5px;">
                            <?php echo number_format($ebook['file_size'] / 1024 / 1024, 2); ?> MB
                        </span>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <a href="download.php?id=<?php echo $ebook['id']; ?>" 
                           style="display: block; text-align: center; padding: 8px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                            📥 Download
                        </a>
                        <p style="text-align: center; margin: 5px 0 0 0; font-size: 11px; color: #888;">
                            <?php echo $ebook['download_count']; ?> downloads
                        </p>
                        <?php if ($canUpload && ($userStatus === 1 || $ebook['uploaded_by'] === $email)): ?>
                            <div style="margin-top: 10px;">
                                <a href="edit.php?id=<?php echo $ebook['id']; ?>" 
                                   style="display: block; text-align: center; padding: 6px; background-color: #ffc107; color: #000; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    ✏️ Edit
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="text-align: center; margin: 20px 0;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       style="padding: 8px 12px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; margin: 0 5px;">Previous</a>
                <?php endif; ?>
                
                <span style="padding: 8px 12px; background: #e9ecef; border-radius: 4px;">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       style="padding: 8px 12px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; margin: 0 5px;">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
            <p style="font-size: 18px; color: #666;">No ebooks available at the moment.</p>
            <p style="color: #888;">Check back later or contact administrators to add content.</p>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin-top: 0;">📖 How to Download</h3>
        <ol style="color: #555; line-height: 1.6;">
            <li>Browse the available ebooks using the search and filters above</li>
            <li>Click the "Download" button on your desired ebook</li>
            <li>Enter your email address to access the download</li>
            <li>Your email will be recorded for download tracking purposes only</li>
        </ol>
    </div>
</div>

<?php include_footer(); ?>