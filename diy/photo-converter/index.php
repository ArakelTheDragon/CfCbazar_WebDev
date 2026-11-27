<?php
// /diy/photo-converter/index.php

// Force errors to log on POST binary responses (prevents HTML warnings/errors from corrupting image bytes)
$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
$GLOBAL_DISPLAY_ERRORS = !$isPost;
ini_set('display_errors', $isPost ? '0' : '1');
ini_set('display_startup_errors', $isPost ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if ($isPost) {
    ob_start();
}

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
// $NEED_SESSION       = false;
// $NEED_LOGIN         = false;
// $NEED_CART          = false;
// $NEED_ADMIN         = false;   // 1
// $NEED_MODERATOR     = false;   // 2
// $NEED_CONTRIBUTOR   = false;   // 3
// $NEED_VIP           = false;   // 4
// $NEED_STANDARD_USER = false;   // 5
// $IS_FRONTEND        = true;
// $IS_BACKEND         = false;

if ($isPost) {
    $GLOBAL_DISPLAY_ERRORS = false;
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// ------------------------

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/photo-converter/index.php";
$parent_url = "/diy/photo-converter";
$title      = "Photo Image Converter — CfCbazar DIY";
$template   = "Generic";
$meta_title = "Photo Image Converter — CfCbazar DIY";
$meta_desc  = "Convert JPG, PNG, WEBP, TIFF, and SVG images instantly (max 20MB).";

// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

setReturnUrl($url);

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}
custom_edge_protection($conn);

// ------------------------
// Server capability detection
// ------------------------
$HAS_IMAGICK = extension_loaded('imagick');
$HAS_GD      = extension_loaded('gd');

$outputFormats = ['jpg', 'png', 'webp'];
if ($HAS_IMAGICK) {
    $outputFormats[] = 'tiff';
}

// ------------------------
// Helpers
// ------------------------
function clean_ob(): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function is_valid_image_file(string $path, string $format): bool {
    if (!is_file($path) || filesize($path) < 32) {
        return false;
    }
    $fh = fopen($path, 'rb');
    if (!$fh) return false;
    $head = fread($fh, 16);
    fclose($fh);

    return match (strtolower($format)) {
        'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
        'png'         => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
        'webp'        => str_starts_with($head, "RIFF") && substr($head, 8, 4) === "WEBP",
        'tiff', 'tif' => str_starts_with($head, "II*\x00") || str_starts_with($head, "MM\x00*"),
        default       => true,
    };
}

// --- Image converter (Imagick primary, GD fallback) -------------------------
function convertImage(string $sourcePath, string $targetPath, string $format): void {
    $format = strtolower($format);
    $ext    = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $isJpg  = in_array($format, ['jpg', 'jpeg'], true);

    if (in_array($ext, ['svg', 'tif', 'tiff'], true) && !extension_loaded('imagick')) {
        clean_ob();
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("Error: SVG/TIFF input requires Imagick on the server.");
    }

    // 1. Primary: Imagick
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();

            if ($ext === 'svg' || @mime_content_type($sourcePath) === 'image/svg+xml') {
                $imagick->setResolution(1200, 1200);
            }

            $imagick->readImage($sourcePath);

            // If multi-frame (e.g. APNG), select first frame before flattening
            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }

            if ($isJpg) {
                $imagick->setImageBackgroundColor(new ImagickPixel('white'));
                if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                }
                $flattened = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $imagick->clear();
                $imagick->destroy();
                $imagick = $flattened;
            }

            $imagick->setImageFormat($isJpg ? 'jpeg' : $format);

            if ($isJpg) {
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality(92);
            } elseif ($format === 'webp') {
                $imagick->setImageCompressionQuality(92);
            }

            $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();
            return;
        } catch (Throwable $e) {
            error_log("Photo converter Imagick error (falling back to GD): " . $e->getMessage());
        }
    }

    // 2. Fallback: GD
    if (!extension_loaded('gd')) {
        clean_ob();
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        die("Neither Imagick nor GD extension is available on this server.");
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        clean_ob();
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("Unsupported source format or unreadable image file.");
    }

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            if (!function_exists('imagecreatefromjpeg')) {
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("JPEG input is not supported by this server's GD build.");
            }
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            if (!function_exists('imagecreatefrompng')) {
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("PNG input is not supported by this server's GD build (enable Imagick or GD PNG support).");
            }
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("WEBP input is not supported by this server's GD build.");
            }
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            clean_ob();
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: text/plain; charset=utf-8');
            die("Unsupported source format for GD engine.");
    }

    if (!$image) {
        clean_ob();
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("Failed to decode source image.");
    }

    if ($isJpg) {
        $w = imagesx($image);
        $h = imagesy($image);
        $flat = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);

        imagealphablending($image, true);
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);

        imagedestroy($image);
        $image = $flat;
    }

    $ok = false;
    switch ($format) {
        case 'jpg':
        case 'jpeg':
            if (!function_exists('imagejpeg')) {
                imagedestroy($image);
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("JPEG output is not supported by this server's GD build.");
            }
            $ok = imagejpeg($image, $targetPath, 92);
            break;
        case 'png':
            if (!function_exists('imagepng')) {
                imagedestroy($image);
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("PNG output is not supported by this server's GD build.");
            }
            imagesavealpha($image, true);
            $ok = imagepng($image, $targetPath, 6);
            break;
        case 'webp':
            if (!function_exists('imagewebp')) {
                imagedestroy($image);
                clean_ob();
                header('HTTP/1.1 400 Bad Request');
                header('Content-Type: text/plain; charset=utf-8');
                die("WEBP output is not supported by this server's GD build.");
            }
            $ok = imagewebp($image, $targetPath, 92);
            break;
        default:
            imagedestroy($image);
            clean_ob();
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: text/plain; charset=utf-8');
            die("Target format ($format) requires the server Imagick extension.");
    }

    imagedestroy($image);

    if (!$ok) {
        clean_ob();
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        die("Image encoder failed to produce output.");
    }
}

// --- Handle upload BEFORE any HTML output ------------------------------------
if ($isPost) {
    ini_set('display_errors', '0');
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        error_log("Photo converter POST error [{$severity}]: {$message} in {$file}:{$line}");
        return true;
    });
    clean_ob();

    $outputFormat = strtolower($_POST['format'] ?? 'jpg');

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (extension_loaded('imagick')) {
        $allowed[] = 'tiff';
    }
    if (!in_array($outputFormat, $allowed, true)) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("Unsupported output format ($outputFormat) on this server.");
    }

    $file = $_FILES['photo'] ?? null;

    if (!$file || $file['error'] !== 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("File upload error.");
    }

    if ($file['size'] > 20 * 1024 * 1024) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        die("File too large. Max 20MB.");
    }

    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    if ($name === '' || $name === null) {
        $name = 'image';
    }

    $tmpBase = tempnam(sys_get_temp_dir(), 'imgconv_');
    if ($tmpBase === false) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        die("Server could not allocate a temp file.");
    }
    $target = $tmpBase . '.' . $outputFormat;

    convertImage($file['tmp_name'], $target, $outputFormat);

    clean_ob();

    if (!is_file($target) || filesize($target) === 0) {
        @unlink($tmpBase);
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        die("Conversion failed — no output produced.");
    }

    if (!is_valid_image_file($target, $outputFormat)) {
        @unlink($target);
        @unlink($tmpBase);
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        die("Conversion produced invalid $outputFormat data. Please try a different source image.");
    }

    $downloadName = $name . "_converted." . $outputFormat;
    $fileData = file_get_contents($target);

    @unlink($target);
    @unlink($tmpBase);

    ini_set('display_errors', '0');
    clean_ob();

    if (in_array($outputFormat, ['jpg', 'jpeg'], true) && !str_starts_with((string)$fileData, "\xFF\xD8\xFF")) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Generated JPEG data is invalid (missing SOI marker).";
        exit;
    }

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$downloadName\"");
    header("Content-Length: " . strlen($fileData));
    header("X-Download-Filename: $downloadName");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    echo $fileData;
    close_database();
    exit;
}


// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);
// <head>, CSS/JS from /assets/, nav
render_header($title, $meta_desc);
?>

<main class="content">
    <div class="container">

        <h1 class="page-title">Photo Converter</h1>
        <p class="subtitle">
            Convert JPG, PNG, WEBP, TIFF, and SVG images instantly (max 20MB).
            <?php if (!$HAS_IMAGICK): ?>
                <br><small>TIFF output requires Imagick on the server and is currently unavailable.</small>
            <?php endif; ?>
        </p>

        <div class="card">
            <form id="convertForm" method="POST" enctype="multipart/form-data">

                <div class="input-group">
                    <label>Select Image</label>
                    <input type="file" name="photo" id="photoInput" accept="image/*,.tiff,.tif,.svg" required>
                </div>

                <div class="input-group">
                    <label>Convert To</label>
                    <select name="format" id="formatSelect">
                        <?php foreach ($outputFormats as $fmt): ?>
                            <option value="<?= htmlspecialchars($fmt) ?>"><?= strtoupper(htmlspecialchars($fmt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="progress-container" id="progressBox" style="display:none;width:100%;background:#eee;border-radius:10px;margin-top:15px;">
                    <div class="progress-bar" id="progressBar" style="height:18px;width:0%;background:#28a745;border-radius:10px;transition:width 0.2s;"></div>
                </div>
                <div class="progress-text" id="progressText" style="margin-top:5px;font-size:0.9rem;text-align:center;"></div>

                <div class="input-group">
                    <button type="submit" class="btn">Convert</button>
                </div>

            </form>
        </div>

    </div>
</main>

<script>
document.getElementById("convertForm").addEventListener("submit", function(e) {
    const fileInput    = document.getElementById("photoInput");
    const targetFormat = document.getElementById("formatSelect").value.toLowerCase();
    const file         = fileInput.files[0];

    if (!file) return;

    if (file.size > 20 * 1024 * 1024) {
        alert("File too large. Maximum allowed is 20MB.");
        e.preventDefault();
        return;
    }

    const sourceExt = (file.name.split('.').pop() || '').toLowerCase();

    const normalizedSource = (sourceExt === 'jpeg') ? 'jpg' : sourceExt;
    const normalizedTarget = (targetFormat === 'jpeg') ? 'jpg' : targetFormat;
    if (normalizedSource === normalizedTarget) {
        alert("Source and target format are the same. Please choose a different output format.");
        e.preventDefault();
        return;
    }

    const isSvgInput = sourceExt === 'svg' || file.type === 'image/svg+xml';

    // Client-side SVG rasterization
    if (isSvgInput && ['png', 'jpg', 'jpeg', 'webp'].includes(targetFormat)) {
        e.preventDefault();

        const progressBox  = document.getElementById("progressBox");
        const progressBar  = document.getElementById("progressBar");
        const progressText = document.getElementById("progressText");

        progressBox.style.display = "block";
        progressBar.style.width = "40%";
        progressText.textContent = "Parsing SVG vector geometry...";

        const reader = new FileReader();
        reader.onload = function(event) {
            const svgContent = event.target.result;

            const parser  = new DOMParser();
            const xmlDoc  = parser.parseFromString(svgContent, "image/svg+xml");
            const svgEl   = xmlDoc.querySelector("svg");

            let nativeWidth  = 0;
            let nativeHeight = 0;

            if (svgEl) {
                const wAttr = parseFloat(svgEl.getAttribute("width"));
                const hAttr = parseFloat(svgEl.getAttribute("height"));

                if (!isNaN(wAttr) && !isNaN(hAttr) && wAttr > 0 && hAttr > 0) {
                    nativeWidth  = wAttr;
                    nativeHeight = hAttr;
                } else if (svgEl.getAttribute("viewBox")) {
                    const vb = svgEl.getAttribute("viewBox").trim().split(/[\s,]+/);
                    if (vb.length === 4) {
                        nativeWidth  = parseFloat(vb[2]);
                        nativeHeight = parseFloat(vb[3]);
                    }
                }
            }

            if (!nativeWidth || !nativeHeight) {
                nativeWidth  = 1414;
                nativeHeight = 2000;
            }

            const aspectRatio  = nativeWidth / nativeHeight;
            const targetHeight = 3000;
            const targetWidth  = Math.round(targetHeight * aspectRatio);

            if (svgEl) {
                svgEl.setAttribute("width", targetWidth);
                svgEl.setAttribute("height", targetHeight);
            }

            const modifiedSvgString = new XMLSerializer().serializeToString(xmlDoc);
            const blob    = new Blob([modifiedSvgString], { type: "image/svg+xml;charset=utf-8" });
            const blobUrl = URL.createObjectURL(blob);

            progressBar.style.width = "70%";
            progressText.textContent = "Rendering Ultra-HD canvas...";

            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement("canvas");
                canvas.width  = targetWidth;
                canvas.height = targetHeight;

                const ctx = canvas.getContext("2d");
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = "high";

                if (targetFormat === 'jpg' || targetFormat === 'jpeg') {
                    ctx.fillStyle = "#FFFFFF";
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                }

                ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                URL.revokeObjectURL(blobUrl);

                const mime = (targetFormat === 'jpg' || targetFormat === 'jpeg')
                    ? 'image/jpeg'
                    : (targetFormat === 'webp' ? 'image/webp' : 'image/png');
                const dataUrl = canvas.toDataURL(mime, 1.0);

                progressBar.style.width = "100%";
                progressText.textContent = "Complete!";

                const link = document.createElement("a");
                const cleanName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                link.download = cleanName + "_converted." + targetFormat;
                link.href = dataUrl;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                setTimeout(() => {
                    progressBox.style.display = "none";
                    progressBar.style.width = "0%";
                    progressText.textContent = "";
                }, 1500);
            };

            img.src = blobUrl;
        };

        reader.readAsText(file);
        return;
    }

    // Server upload
    e.preventDefault();

    const progressBox  = document.getElementById("progressBox");
    const progressBar  = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");

    progressBox.style.display = "block";

    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener("progress", function(event) {
        if (event.lengthComputable) {
            const percent   = (event.loaded / event.total) * 100;
            const mbLoaded  = (event.loaded / (1024 * 1024)).toFixed(2);
            const mbTotal   = (event.total / (1024 * 1024)).toFixed(2);

            progressBar.style.width = percent + "%";
            progressText.textContent = `${mbLoaded} MB / ${mbTotal} MB`;
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const blob = new Blob([xhr.response], { type: "application/octet-stream" });
                const link = document.createElement("a");
                link.href = window.URL.createObjectURL(blob);

                let filename = xhr.getResponseHeader("X-Download-Filename");
                if (!filename) {
                    const cd = xhr.getResponseHeader("Content-Disposition");
                    if (cd) {
                        const m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
                        if (m) filename = decodeURIComponent(m[1]);
                    }
                }
                if (!filename || !filename.includes(".")) {
                    const cleanName = (file.name.replace(/\.[^/.]+$/, "")) || "image";
                    filename = cleanName + "_converted." + targetFormat;
                }

                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(link.href);
            } else {
                const enc = new TextDecoder("utf-8");
                const errorMsg = enc.decode(xhr.response);
                alert(errorMsg || "Conversion failed.");
            }

            setTimeout(() => {
                progressBox.style.display = "none";
                progressBar.style.width = "0%";
                progressText.textContent = "";
            }, 1000);
        }
    };

    xhr.open("POST", window.location.href);
    xhr.responseType = "arraybuffer";
    xhr.send(formData);
});
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/photo-converter",
    "Photo Converter Source Code"
);
include_footer();
close_database();
?>
