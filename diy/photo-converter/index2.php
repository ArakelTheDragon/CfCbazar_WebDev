<?php
// /diy/photo-converter/index.php

require_once __DIR__ . '/../../includes/reusable.php';

// --- Core logic ---
enforce_https();
checkSystemFlags($conn);
$return_url = '/about.php';

// --- Image converter function (Imagick primary, GD fallback) ---
function convertImage($sourcePath, $targetPath, $format) {
    $format = strtolower($format);
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

    if (in_array($ext, ['svg', 'tif', 'tiff']) && !extension_loaded('imagick')) {
        header('HTTP/1.1 400 Bad Request');
        die("Error: SVG/TIFF conversions require Imagick on server. Use client-side conversion.");
    }

    // 1. Primary Engine: Imagick
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();

            // Set ultra-high resolution (1200 DPI) before reading SVG vector data
            if ($ext === 'svg' || mime_content_type($sourcePath) === 'image/svg+xml') {
                $imagick->setResolution(1200, 1200);
            }

            $imagick->readImage($sourcePath);

            // Handle transparency for JPEG output
            if (in_array($format, ['jpg', 'jpeg'])) {
                $imagick->setImageBackgroundColor('white');
                $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }

            $imagick->setImageFormat($format);

            if (in_array($format, ['jpg', 'jpeg'])) {
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality(100);
            } elseif ($format === 'webp') {
                $imagick->setImageCompressionQuality(100);
            }

            $imagick->writeImage($targetPath);
            $imagick->clear();
            $imagick->destroy();
            return;
        } catch (Exception $e) {
            // Fall through to GD
        }
    }

    // 2. Fallback Engine: GD
    $info = @getimagesize($sourcePath);
    if (!$info) {
        header('HTTP/1.1 400 Bad Request');
        die("Unsupported source format or unreadable image file.");
    }

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            die("Unsupported source format for GD engine.");
    }

    switch ($format) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $targetPath, 100);
            break;
        case 'png':
            imagepng($image, $targetPath, 0);
            break;
        case 'webp':
            imagewebp($image, $targetPath, 100);
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            die("Target format ($format) requires server Imagick extension.");
    }

    imagedestroy($image);
}

// --- Handle upload BEFORE ANY HTML OUTPUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outputFormat = $_POST['format'] ?? 'jpg';
    $file = $_FILES['photo'] ?? null;

    if (!$file || $file['error'] !== 0) {
        header('HTTP/1.1 400 Bad Request');
        die("File upload error.");
    }

    if ($file['size'] > 20 * 1024 * 1024) {
        header('HTTP/1.1 400 Bad Request');
        die("File too large. Max 20MB.");
    }

    $tmp = $file['tmp_name'];
    $name = pathinfo($file['name'], PATHINFO_FILENAME);
    $target = $name . "_converted." . $outputFormat;

    convertImage($tmp, $target, $outputFormat);

    header("Content-Disposition: attachment; filename=\"$target\"");
    header("Content-Type: application/octet-stream");
    header("Content-Length: " . filesize($target));

    readfile($target);
    unlink($target);
    exit;
}

// --- Render layout AFTER all header() logic ---
$title = "Photo Image Converter — CfCbazar DIY";
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
.progress-container {
    width: 100%;
    background: #eee;
    border-radius: 10px;
    margin-top: 15px;
    display: none;
}
.progress-bar {
    height: 18px;
    width: 0%;
    background: #28a745;
    border-radius: 10px;
    transition: width 0.2s;
}
.progress-text {
    margin-top: 5px;
    font-size: 0.9rem;
    text-align: center;
}
</style>

</head>
<body>

<div class="container">

    <h1 class="page-title">Photo Converter</h1>
    <p class="subtitle">Convert JPG, PNG, WEBP, TIFF, and SVG images instantly (max 20MB).</p>

    <div class="card">
        <form id="convertForm" method="POST" enctype="multipart/form-data">

            <div class="input-group">
                <label>Select Image</label>
                <input type="file" name="photo" id="photoInput" accept="image/*,.tiff,.tif,.svg" required>
            </div>

            <div class="input-group">
                <label>Convert To</label>
                <select name="format" id="formatSelect">
                    <option value="jpg">JPG</option>
                    <option value="png">PNG</option>
                    <option value="webp">WEBP</option>
                    <option value="tiff">TIFF</option>
                    <option value="svg">SVG</option>
                </select>
            </div>

            <div class="progress-container" id="progressBox">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="progress-text" id="progressText"></div>

            <div class="input-group">
                <button type="submit" class="btn">Convert</button>
            </div>

        </form>
    </div>

</div>

<script>
document.getElementById("convertForm").addEventListener("submit", function(e) {
    const fileInput = document.getElementById("photoInput");
    const targetFormat = document.getElementById("formatSelect").value;
    const file = fileInput.files[0];

    if (!file) return;

    if (file.size > 20 * 1024 * 1024) {
        alert("File too large. Maximum allowed is 20MB.");
        e.preventDefault();
        return;
    }

    const isSvgInput = file.name.toLowerCase().endsWith('.svg') || file.type === 'image/svg+xml';

    // High-Precision SVG Vector Rasterization
    if (isSvgInput && ['png', 'jpg', 'jpeg', 'webp'].includes(targetFormat)) {
        e.preventDefault();

        const progressBox = document.getElementById("progressBox");
        const progressBar = document.getElementById("progressBar");
        const progressText = document.getElementById("progressText");

        progressBox.style.display = "block";
        progressBar.style.width = "40%";
        progressText.textContent = "Parsing SVG vector geometry...";

        const reader = new FileReader();
        reader.onload = function(event) {
            const svgContent = event.target.result;

            // 1. Parse real SVG dimensions or viewBox
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(svgContent, "image/svg+xml");
            const svgEl = xmlDoc.querySelector("svg");

            let nativeWidth = 0;
            let nativeHeight = 0;

            if (svgEl) {
                // Try reading width/height attributes
                const wAttr = parseFloat(svgEl.getAttribute("width"));
                const hAttr = parseFloat(svgEl.getAttribute("height"));

                if (!isNaN(wAttr) && !isNaN(hAttr) && wAttr > 0 && hAttr > 0) {
                    nativeWidth = wAttr;
                    nativeHeight = hAttr;
                } else if (svgEl.getAttribute("viewBox")) {
                    // Extract aspect ratio from viewBox if width/height are missing
                    const vb = svgEl.getAttribute("viewBox").trim().split(/[\s,]+/);
                    if (vb.length === 4) {
                        nativeWidth = parseFloat(vb[2]);
                        nativeHeight = parseFloat(vb[3]);
                    }
                }
            }

            // Fallback default aspect ratio if parsing failed
            if (!nativeWidth || !nativeHeight) {
                nativeWidth = 1414; // Standard eGuide/A4 aspect ratio
                nativeHeight = 2000;
            }

            const aspectRatio = nativeWidth / nativeHeight;

            // 2. Set Target Output Dimensions (3000px Height for Ultra HD)
            const targetHeight = 3000;
            const targetWidth = Math.round(targetHeight * aspectRatio);

            // 3. Convert SVG text into clean Blob URL with explicit width/height
            if (svgEl) {
                svgEl.setAttribute("width", targetWidth);
                svgEl.setAttribute("height", targetHeight);
            }
            const modifiedSvgString = new XMLSerializer().serializeToString(xmlDoc);
            const blob = new Blob([modifiedSvgString], { type: "image/svg+xml;charset=utf-8" });
            const blobUrl = URL.createObjectURL(blob);

            progressBar.style.width = "70%";
            progressText.textContent = "Rendering Ultra-HD canvas...";

            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement("canvas");
                canvas.width = targetWidth;
                canvas.height = targetHeight;

                const ctx = canvas.getContext("2d");
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = "high";

                // Solid white background for JPG
                if (targetFormat === 'jpg' || targetFormat === 'jpeg') {
                    ctx.fillStyle = "#FFFFFF";
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                }

                ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                URL.revokeObjectURL(blobUrl);

                const mime = (targetFormat === 'jpg' || targetFormat === 'jpeg') ? 'image/jpeg' : (targetFormat === 'webp' ? 'image/webp' : 'image/png');
                const dataUrl = canvas.toDataURL(mime, 1.0);

                progressBar.style.width = "100%";
                progressText.textContent = "Complete!";

                const link = document.createElement("a");
                const cleanName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                link.download = cleanName + "_converted." + targetFormat;
                link.href = dataUrl;
                link.click();

                setTimeout(() => {
                    progressBox.style.display = "none";
                    progressBar.style.width = "0%";
                    progressText.textContent = "";
                }, 1500);
            };

            img.src = blobUrl;
        };

        reader.readAsText(file); // Read as text to parse XML
        return;
    }

    // Standard Server Upload Process
    e.preventDefault();

    const progressBox = document.getElementById("progressBox");
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");

    progressBox.style.display = "block";

    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener("progress", function(event) {
        if (event.lengthComputable) {
            const percent = (event.loaded / event.total) * 100;
            const mbLoaded = (event.loaded / (1024 * 1024)).toFixed(2);
            const mbTotal = (event.total / (1024 * 1024)).toFixed(2);

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

                const filename = xhr.getResponseHeader("Content-Disposition")
                    ?.split("filename=")[1]
                    ?.replace(/"/g, "") || "converted_file";

                link.download = filename;
                link.click();
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
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/photo-converter",
    "Photo Converter Source Code"
);
?>
    
<?php include_footer(); ?>
</body>
</html>
