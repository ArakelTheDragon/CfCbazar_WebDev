<?php
// /diy/photo-converter/index.php

require_once __DIR__ . '/../../includes/reusable.php';

// --- Core logic ---
enforce_https();
checkSystemFlags($conn);
$return_url = '/about.php';

// --- Image converter function ---
function convertImage($sourcePath, $targetPath, $format) {
    $format = strtolower($format);

    $info = getimagesize($sourcePath);
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
            die("Unsupported source format");
    }

    switch ($format) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $targetPath, 90);
            break;
        case 'png':
            imagepng($image, $targetPath);
            break;
        case 'webp':
            imagewebp($image, $targetPath, 90);
            break;
        default:
            die("Unsupported output format");
    }

    imagedestroy($image);
}

// --- Handle upload BEFORE ANY HTML OUTPUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outputFormat = $_POST['format'];
    $file = $_FILES['photo'];

    // Server-side 20MB limit
    if ($file['size'] > 20 * 1024 * 1024) {
        die("File too large. Max 20MB.");
    }

    if ($file['error'] === 0) {
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
    <p class="subtitle">Convert JPG, PNG, and WEBP images instantly (max 20MB).</p>

    <div class="card">
        <form id="convertForm" method="POST" enctype="multipart/form-data">

            <div class="input-group">
                <label>Select Image</label>
                <input type="file" name="photo" id="photoInput" required>
            </div>

            <div class="input-group">
                <label>Convert To</label>
                <select name="format">
                    <option value="jpg">JPG</option>
                    <option value="png">PNG</option>
                    <option value="webp">WEBP</option>
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
// Client-side 20MB limit + progress bar
document.getElementById("convertForm").addEventListener("submit", function(e) {
    const fileInput = document.getElementById("photoInput");
    const file = fileInput.files[0];

    if (!file) return;

    // 20MB limit
    if (file.size > 20 * 1024 * 1024) {
        alert("File too large. Maximum allowed is 20MB.");
        e.preventDefault();
        return;
    }

    // Show progress bar
    const progressBox = document.getElementById("progressBox");
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");

    progressBox.style.display = "block";

    // Use AJAX upload to track progress
    e.preventDefault();

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
        if (xhr.readyState === 4 && xhr.status === 200) {
            // Trigger download
            const blob = new Blob([xhr.response], { type: "application/octet-stream" });
            const link = document.createElement("a");
            link.href = window.URL.createObjectURL(blob);

            const filename = xhr.getResponseHeader("Content-Disposition")
                ?.split("filename=")[1]
                ?.replace(/"/g, "") || "converted_file";

            link.download = filename;
            link.click();
        }
    };

    xhr.open("POST", window.location.href);
    xhr.responseType = "arraybuffer";
    xhr.send(formData);
});
</script>

<?php include_footer(); ?>
</body>
</html>
