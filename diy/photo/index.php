<?php
// /diy/photo/index.php

$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        trackVisit('diy-photo');
    }
} else {
    http_response_code(500);
    exit('Required system files are missing.');
}

// -----------------------------------------------------------------------------
// Core logic
// -----------------------------------------------------------------------------

if (function_exists('enforce_https')) {
    enforce_https();
}

if (function_exists('checkSystemFlags')) {
    checkSystemFlags();
}

$title = 'Upload & Lookup Photo — CfCbazar DIY';

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

const PHOTO_RETENTION_DAYS = 5;
const PHOTO_MAX_SIZE = 10485760; // 10 MB

$photoDir = __DIR__ . '/photos/';

// -----------------------------------------------------------------------------
// Photo directory
// -----------------------------------------------------------------------------

if (!is_dir($photoDir)) {
    if (!mkdir($photoDir, 0755, true)) {
        http_response_code(500);
        exit('Photo storage directory could not be created.');
    }
}

// -----------------------------------------------------------------------------
// Auto-delete photos older than the retention period
// -----------------------------------------------------------------------------

$files = glob($photoDir . '*.{jpg,png}', GLOB_BRACE);
$now = time();

if ($files !== false) {
    foreach ($files as $file) {
        if (
            is_file($file) &&
            @filemtime($file) !== false &&
            ($now - filemtime($file)) > (PHOTO_RETENTION_DAYS * 86400)
        ) {
            @unlink($file);
        }
    }
}

// -----------------------------------------------------------------------------
// Variables
// -----------------------------------------------------------------------------

$uploadError = null;
$lookupError = null;
$referenceNumber = null;
$lookupImage = null;

// -----------------------------------------------------------------------------
// CSRF helper
// -----------------------------------------------------------------------------

$csrfToken = null;

if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
} elseif (isset($_SESSION['csrf_token'])) {
    $csrfToken = $_SESSION['csrf_token'];
}

// -----------------------------------------------------------------------------
// CSRF validation
// -----------------------------------------------------------------------------

function photoValidateCsrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';

    if ($submitted === '') {
        return false;
    }

    if (function_exists('verify_csrf_token')) {
        return verify_csrf_token($submitted);
    }

    if (function_exists('validate_csrf_token')) {
        return validate_csrf_token($submitted);
    }

    if (isset($_SESSION['csrf_token'])) {
        return hash_equals($_SESSION['csrf_token'], $submitted);
    }

    return false;
}

// -----------------------------------------------------------------------------
// Handle upload
// -----------------------------------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'upload'
) {

    // CSRF
    if (!photoValidateCsrf()) {
        $uploadError = 'Security validation failed. Please refresh the page and try again.';
    }

    // Upload presence / PHP upload error
    elseif (!isset($_FILES['photo'])) {
        $uploadError = 'Please select a photo.';
    }

    elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

        switch ($_FILES['photo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $uploadError = 'File too large. Maximum size is 10 MB.';
                break;

            case UPLOAD_ERR_NO_FILE:
                $uploadError = 'Please select a photo.';
                break;

            default:
                $uploadError = 'Upload failed. Please try again.';
                break;
        }
    }

    else {

        $file = $_FILES['photo'];

        // ---------------------------------------------------------------------
        // File size
        // ---------------------------------------------------------------------

        if ($file['size'] <= 0) {
            $uploadError = 'The uploaded file is empty.';
        }

        elseif ($file['size'] > PHOTO_MAX_SIZE) {
            $uploadError = 'File too large. Maximum size is 10 MB.';
        }

        // ---------------------------------------------------------------------
        // Temporary file validation
        // ---------------------------------------------------------------------

        elseif (!is_uploaded_file($file['tmp_name'])) {
            $uploadError = 'Invalid upload.';
        }

        else {

            // -----------------------------------------------------------------
            // Server-side MIME detection
            // -----------------------------------------------------------------

            $mime = false;

            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
            }

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png'
            ];

            if ($mime === false || !isset($allowed[$mime])) {
                $uploadError = 'Invalid file type. Only JPG, JPEG, and PNG files are allowed.';
            }

            // -----------------------------------------------------------------
            // Verify that the file is actually an image
            // -----------------------------------------------------------------

            elseif (@getimagesize($file['tmp_name']) === false) {
                $uploadError = 'The uploaded file is not a valid image.';
            }

            else {

                $ext = $allowed[$mime];

                // -------------------------------------------------------------
                // Generate collision-safe 6-digit reference number
                // -------------------------------------------------------------

                $referenceNumber = null;

                for ($attempt = 0; $attempt < 20; $attempt++) {

                    try {
                        $candidate = str_pad(
                            (string) random_int(0, 999999),
                            6,
                            '0',
                            STR_PAD_LEFT
                        );
                    } catch (Throwable $e) {
                        $candidate = str_pad(
                            (string) mt_rand(0, 999999),
                            6,
                            '0',
                            STR_PAD_LEFT
                        );
                    }

                    $existing = glob($photoDir . $candidate . '.*');

                    if (!$existing) {
                        $referenceNumber = $candidate;
                        break;
                    }
                }

                if ($referenceNumber === null) {
                    $uploadError = 'Unable to generate a unique reference number. Please try again.';
                }

                else {

                    $targetPath = $photoDir . $referenceNumber . '.' . $ext;

                    // ---------------------------------------------------------
                    // Save uploaded image
                    // ---------------------------------------------------------

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {

                        $uploadError = 'Failed to save file.';
                        $referenceNumber = null;

                    } else {

                        // Restrict permissions where supported
                        @chmod($targetPath, 0644);

                        // -----------------------------------------------------
                        // Redirect after successful upload
                        // -----------------------------------------------------

                        header(
                            'Location: /diy/photo/index.php?ref=' .
                            rawurlencode($referenceNumber) .
                            '#refnum'
                        );

                        exit;
                    }
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Handle lookup
// -----------------------------------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'lookup'
) {

    if (!photoValidateCsrf()) {
        $lookupError = 'Security validation failed. Please refresh the page and try again.';
    }

    else {

        $ref = trim($_POST['ref'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $ref)) {
            $lookupError = 'Invalid reference number.';
        }

        else {

            $matches = glob($photoDir . $ref . '.{jpg,png}', GLOB_BRACE);

            if ($matches !== false && isset($matches[0]) && is_file($matches[0])) {

                $lookupImage = '/diy/photo/photos/' .
                    rawurlencode(basename($matches[0]));

            } else {

                $lookupError = 'No photo found for this reference number.';
            }
        }
    }
}

// -----------------------------------------------------------------------------
// If redirected after upload
// -----------------------------------------------------------------------------

if (
    isset($_GET['ref']) &&
    preg_match('/^[0-9]{6}$/', $_GET['ref'])
) {
    $referenceNumber = $_GET['ref'];
}

// -----------------------------------------------------------------------------
// Render layout
// -----------------------------------------------------------------------------

include_header($title);
include_menu();
showAdvertPopup();
render_top_userbar();
?>

<style>
/*
 * Keeps the reference result visible below the fixed
 * CfCbazar navigation/top bar when using #refnum.
 */
#refnum {
    scroll-margin-top: 180px;
}
</style>

<div class="container">

    <!-- Legal Disclaimer -->
    <div class="card">
        <h2>Legal Disclaimer</h2>

        <p style="text-align:left;">
            Please ensure any photos taken are close-ups of your home setup.<br><br>

            By submitting this image, you confirm that you have the right
            to submit it and agree that the image may be stored and used
            for the purposes of this service.<br><br>

            This image will be stored for no longer than
            <?php echo PHOTO_RETENTION_DAYS; ?> days.<br>

            It will not be associated with your account.<br><br>

            Do not include any personal information, property details,
            documents, addresses, or identifiable individuals.
        </p>
    </div>

    <h1 class="page-title">Upload or Look Up Photo</h1>

    <!-- Upload Section -->
    <div class="card">

        <h2>Upload Photo</h2>

        <?php if ($uploadError): ?>

            <div class="error">
                <?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>

        <?php if ($referenceNumber): ?>

            <div id="refnum" class="success">
                Photo uploaded successfully!<br>

                <strong>Your Reference Number:</strong><br>

                <span style="font-size:1.8rem;">
                    <?php echo htmlspecialchars($referenceNumber, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>

        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="action" value="upload">

            <?php if ($csrfToken !== null): ?>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
                >
            <?php endif; ?>

            <div class="input-group">

                <label for="photo">
                    Select Photo (JPG, JPEG, PNG — max 10 MB)
                </label>

                <input
                    type="file"
                    id="photo"
                    name="photo"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    required
                >

            </div>

            <div class="input-group">

                <button type="submit" class="btn">
                    Upload
                </button>

            </div>

        </form>

    </div>

    <!-- Lookup Section -->
    <div id="lookup" class="card">

        <h2>Look Up Photo</h2>

        <?php if ($lookupError): ?>

            <div class="error">
                <?php echo htmlspecialchars($lookupError, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="action" value="lookup">

            <?php if ($csrfToken !== null): ?>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
                >
            <?php endif; ?>

            <div class="input-group">

                <label for="ref">
                    Enter 6-digit Reference Number
                </label>

                <input
                    type="text"
                    id="ref"
                    name="ref"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                >

            </div>

            <div class="input-group">

                <button type="submit" class="btn">
                    Look Up
                </button>

            </div>

        </form>

        <?php if ($lookupImage): ?>

            <div class="card" style="margin-top:20px;">

                <h3>Photo Found</h3>

                <img
                    src="<?php echo htmlspecialchars($lookupImage, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Uploaded Photo"
                    loading="lazy"
                    style="max-width:100%; height:auto; border-radius:12px;"
                >

            </div>

        <?php endif; ?>

    </div>

</div>

<?php include_footer(); ?>

