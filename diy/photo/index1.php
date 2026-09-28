<?php
// /diy/photo/index.php

require_once __DIR__ . '/../../includes/reusable.php';

// --- Core logic ---
enforce_https();
checkSystemFlags($conn);
trackVisit($conn);
$return_url = '/about.php';

// Directory for storing photos
$photoDir = __DIR__ . '/photos/';
if (!is_dir($photoDir)) {
    mkdir($photoDir, 0777, true);
}

// --- Auto-delete photos older than 10 days ---
$files = glob($photoDir . '*');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > (10 * 24 * 60 * 60)) {
        unlink($file);
    }
}

$uploadError = null;
$lookupError = null;
$referenceNumber = null;
$lookupImage = null;

// --- Handle upload ---
if (isset($_POST['action']) && $_POST['action'] === 'upload') {

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
        $uploadError = "Upload failed. Please try again.";
    } else {
        $file = $_FILES['photo'];

        if ($file['size'] > 10 * 1024 * 1024) {
            $uploadError = "File too large. Maximum size is 10 MB.";
        } else {
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file['type'], $allowed)) {
                $uploadError = "Invalid file type. Only JPG, JPEG, and PNG allowed.";
            } else {
                $referenceNumber = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'jpeg') $ext = 'jpg';

                $targetPath = $photoDir . $referenceNumber . '.' . $ext;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $uploadError = "Failed to save file.";
                    $referenceNumber = null;
                } else {
                    // Redirect to scroll-up anchor
                    header("Location: /diy/photo/index.php?ref={$referenceNumber}#scroll-upload");
                    exit;
                }
            }
        }
    }
}

// --- Handle lookup ---
if (isset($_POST['action']) && $_POST['action'] === 'lookup') {

    $ref = trim($_POST['ref'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $ref)) {
        $lookupError = "Invalid reference number.";
    } else {
        $matches = glob($photoDir . $ref . '.*');
        if ($matches && file_exists($matches[0])) {
            $lookupImage = '/diy/photo/photos/' . basename($matches[0]);
        } else {
            $lookupError = "No photo found for this reference number.";
        }
    }
}

// If redirected after upload
if (isset($_GET['ref'])) {
    $referenceNumber = $_GET['ref'];
}

// --- Render layout ---
$title = "Upload & Lookup Photo — CfCbazar DIY";
include_header();
include_menu();
showAdvertPopup();
render_top_userbar();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $title; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/css/styles.css">

<style>
/* Scroll offset anchors */
#scroll-upload {
    position: relative;
    top: -500px;
    height: 0;
    display: block;
}

#scroll-lookup {
    position: relative;
    top: -200px;
    height: 0;
    display: block;
}
</style>

</head>
<body>

<!-- Scroll anchors -->
<a id="scroll-upload"></a>
<a id="scroll-lookup"></a>

<div class="container">

    <!-- Legal Disclaimer -->
    <div class="card">
        <h2>Legal Disclaimer</h2>
        <p style="text-align:left;">
            Please ensure any photos taken are close ups of your Broadband home set up.<br><br>
            In submitting this image, you accept all rights to the image are given up.<br><br>
            This image will be stored for no longer than 5 days.<br>
            It will not be associated with your Sky account.<br><br>
            Do not include any personal information, property or individuals.
        </p>
    </div>

    <h1 class="page-title">Upload or Look Up Photo</h1>

    <!-- Upload Section -->
    <div class="card">
        <h2>Upload Photo</h2>

        <?php if ($uploadError): ?>
            <div class="error"><?php echo htmlspecialchars($uploadError); ?></div>
        <?php endif; ?>

        <?php if ($referenceNumber): ?>
            <div class="success">
                Photo uploaded successfully!<br>
                <strong>Your Reference Number:</strong><br>
                <span style="font-size:1.8rem;"><?php echo $referenceNumber; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" action="#scroll-upload">
            <input type="hidden" name="action" value="upload">

            <div class="input-group">
                <label>Select Photo (JPG, JPEG, PNG — max 10 MB)</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png" required>
            </div>

            <div class="input-group">
                <button type="submit" class="btn">Upload</button>
            </div>
        </form>
    </div>

    <!-- Lookup Section -->
    <div class="card">
        <h2>Look Up Photo</h2>

        <?php if ($lookupError): ?>
            <div class="error"><?php echo htmlspecialchars($lookupError); ?></div>
        <?php endif; ?>

        <form method="POST" action="#scroll-lookup">
            <input type="hidden" name="action" value="lookup">

            <div class="input-group">
                <label>Enter 6‑digit Reference Number</label>
                <input type="text" name="ref" maxlength="6" pattern="[0-9]{6}" required>
            </div>

            <div class="input-group">
                <button type="submit" class="btn">Look Up</button>
            </div>
        </form>

        <?php if ($lookupImage): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Photo Found</h3>
                <img src="<?php echo $lookupImage; ?>" alt="Uploaded Photo" style="max-width:100%; border-radius:12px;">

                <div style="margin-top:15px;">
                    <a href="<?php echo $lookupImage; ?>" download class="btn">
                        Download Photo
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include_footer(); ?>
</body>
</html> 