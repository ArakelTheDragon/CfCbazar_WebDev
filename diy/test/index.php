<?php
// File: image_generator_test.php

// ----------------------------------------------------------------------------
// Hardcoded Image Dimensions
// ----------------------------------------------------------------------------
define('IMG_WIDTH', 600);
define('IMG_HEIGHT', 150);

// Helper function to convert Hex color codes to RGB
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $val = hexdec($hex);
    return [
        'r' => ($val >> 16) & 0xFF,
        'g' => ($val >> 8) & 0xFF,
        'b' => $val & 0xFF
    ];
}

// ----------------------------------------------------------------------------
// 1. IMAGE STREAM ENDPOINT (Triggered when ?action=render is passed)
// ----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'render') {
    // Disable errors in output so raw binary image isn't corrupted
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    $text    = !empty($_GET['text']) ? trim($_GET['text']) : 'CfCbazar Banner Test';
    $bgHex   = !empty($_GET['bg']) ? $_GET['bg'] : '111827';
    $textHex = !empty($_GET['color']) ? $_GET['color'] : 'E90E5B';

    // Create TrueColor image with hardcoded size
    $image = imagecreatetruecolor(IMG_WIDTH, IMG_HEIGHT);

    // Convert hex values and allocate GD colors
    $rgbBg   = hexToRgb($bgHex);
    $rgbText = hexToRgb($textHex);

    $bgColor   = imagecolorallocate($image, $rgbBg['r'], $rgbBg['g'], $rgbBg['b']);
    $textColor = imagecolorallocate($image, $rgbText['r'], $rgbText['g'], $rgbText['b']);

    // Fill background
    imagefill($image, 0, 0, $bgColor);

    // Calculate center coordinates using GD built-in font
    $font       = 5; // GD built-in font size (1 to 5)
    $fontWidth  = imagefontwidth($font);
    $fontHeight = imagefontheight($font);
    $textLength = strlen($text);

    $x = (int)((IMG_WIDTH - ($textLength * $fontWidth)) / 2);
    $y = (int)((IMG_HEIGHT - $fontHeight) / 2);

    // Render text centered on the canvas
    imagestring($image, $font, max(10, $x), max(10, $y), $text, $textColor);

    // Set PNG headers and stream image
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, must-revalidate');

    imagepng($image);
    imagedestroy($image);
    exit;
}

// ----------------------------------------------------------------------------
// 2. HTML TEST INTERFACE
// ----------------------------------------------------------------------------
$currentText  = isset($_GET['text']) ? htmlspecialchars($_GET['text']) : 'CfCbazar Standalone Banner';
$currentBg    = isset($_GET['bg']) ? htmlspecialchars($_GET['bg']) : '111827';
$currentColor = isset($_GET['color']) ? htmlspecialchars($_GET['color']) : 'E90E5B';

$imgSrc = '?' . http_build_query([
    'action' => 'render',
    'text'   => $currentText,
    'bg'     => $currentBg,
    'color'  => $currentColor
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GD Image Generator Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 660px;
            margin: 0 auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        button {
            background: #0066cc;
            color: #fff;
            border: none;
            padding: 11px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        button:hover { background: #0052a3; }
        .preview-area {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .preview-area img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .code-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 15px;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>GD Image Banner Generator (Fixed 600×150)</h2>
    
    <form method="GET" action="">
        <div class="form-group">
            <label for="text">Banner Text:</label>
            <input type="text" id="text" name="text" value="<?php echo $currentText; ?>" required>
        </div>
        <div class="form-group">
            <label for="bg">Background Color (Hex):</label>
            <input type="text" id="bg" name="bg" value="<?php echo $currentBg; ?>" placeholder="111827">
        </div>
        <div class="form-group">
            <label for="color">Text Color (Hex):</label>
            <input type="text" id="color" name="color" value="<?php echo $currentColor; ?>" placeholder="E90E5B">
        </div>
        <button type="submit">Generate Image</button>
    </form>

    <div class="preview-area">
        <h3>Live Output Preview:</h3>
        <img src="<?php echo $imgSrc; ?>" alt="Generated Image Banner">
        
        <div class="code-box">
            <strong>Direct Image URL:</strong><br>
            image_generator_test.php<?php echo $imgSrc; ?>
        </div>
    </div>
</div>

</body>
</html>
