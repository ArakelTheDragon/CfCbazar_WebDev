<?php
// /diy/photo/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------
const PHOTO_RETENTION_DAYS = 5;
const PHOTO_MAX_SIZE = 10485760; // 10 MB

$photoDir = __DIR__ . '/photos/';

if (!is_dir($photoDir)) {
    if (!mkdir($photoDir, 0755, true)) {
        http_response_code(500);
        exit('Photo storage directory could not be created.');
    }
}

// Auto-delete photos older than retention period
$files = glob($photoDir . '*.{jpg,png}', GLOB_BRACE);
$now   = time();

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
$uploadError     = null;
$lookupError     = null;
$referenceNumber = null;
$lookupImage     = null;

// -----------------------------------------------------------------------------
// CSRF token
// -----------------------------------------------------------------------------
$csrfToken = null;

if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
} elseif (isset($_SESSION['csrf_token'])) {
    $csrfToken = $_SESSION['csrf_token'];
}

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

    if (!photoValidateCsrf()) {
        $uploadError = 'Security validation failed. Please refresh the page and try again.';
    } elseif (!isset($_FILES['photo'])) {
        $uploadError = 'Please select a photo.';
    } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

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
    } else {

        $file = $_FILES['photo'];

        if ($file['size'] <= 0) {
            $uploadError = 'The uploaded file is empty.';
        } elseif ($file['size'] > PHOTO_MAX_SIZE) {
            $uploadError = 'File too large. Maximum size is 10 MB.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $uploadError = 'Invalid upload.';
        } else {

            $mime = false;
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
            }

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
            ];

            if ($mime === false || !isset($allowed[$mime])) {
                $uploadError = 'Invalid file type. Only JPG, JPEG, and PNG files are allowed.';
            } elseif (@getimagesize($file['tmp_name']) === false) {
                $uploadError = 'The uploaded file is not a valid image.';
            } else {

                $ext = $allowed[$mime];

                for ($attempt = 0; $attempt < 20; $attempt++) {
                    try {
                        $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    } catch (Throwable $e) {
                        $candidate = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    }

                    $existing = glob($photoDir . $candidate . '.*');
                    if (!$existing) {
                        $referenceNumber = $candidate;
                        break;
                    }
                }

                if ($referenceNumber === null) {
                    $uploadError = 'Unable to generate a unique reference number. Please try again.';
                } else {

                    $targetPath = $photoDir . $referenceNumber . '.' . $ext;

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $uploadError = 'Failed to save file.';
                        $referenceNumber = null;
                    } else {
                        @chmod($targetPath, 0644);

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
    } else {

        $ref = trim($_POST['ref'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $ref)) {
            $lookupError = 'Invalid reference number.';
        } else {

            $matches = glob($photoDir . $ref . '.{jpg,png}', GLOB_BRACE);

            if ($matches !== false && isset($matches[0]) && is_file($matches[0])) {
                $lookupImage = '/diy/photo/photos/' . rawurlencode(basename($matches[0]));
            } else {
                $lookupError = 'No photo found for this reference number.';
            }
        }
    }
}

if (isset($_GET['ref']) && preg_match('/^[0-9]{6}$/', $_GET['ref'])) {
    $referenceNumber = $_GET['ref'];
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/photo/index.php";
$parent_url = "/diy/photo";
$title      = "Upload & Lookup Photo — CfCbazar DIY";
$template   = "Generic";
$meta_title = "Upload & Lookup Photo — CfCbazar DIY";
$meta_desc  = "Upload a JPG/PNG photo (max 10MB), get a 6-digit reference number, and retrieve it for up to 5 days.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<style>
#refnum {
    scroll-margin-top: 180px;
}
</style>

<main class="content">
    <div class="container">

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
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/photo",
    "Photo Upload Source Code"
);
include_footer();
close_database();
?>
