<?php
// ============================================================================
// CfCbazar AI Agent - Local GD Image Generator Skill
// File: /includes/agent_generate_image.php
// ============================================================================

if (!function_exists('agent_generate_image')) {
    
    /**
     * Generates a PNG banner/image using GD library and saves it to server.
     *
     * @param string $text      The text string to display.
     * @param int    $width     Width in pixels (default: 400).
     * @param int    $height    Height in pixels (default: 120).
     * @param string $bgHex     Background color in hex (e.g., '#000000').
     * @param string $textHex   Text color in hex (e.g., '#E90E5B').
     * @return string           Public URL of the generated image.
     */
    function agent_generate_image(
        string $text,
        int $width = 400,
        int $height = 120,
        string $bgHex = '#000000',
        string $textHex = '#E90E5B'
    ): string {

        // Validate GD extension
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is not enabled on this server.');
        }

        // Clamp dimensions
        $width  = max(50, min($width, 2000));
        $height = max(50, min($height, 2000));

        // Target storage directory
        $saveDir = __DIR__ . '/../uploads/ai_images/';

        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
                throw new RuntimeException('Failed to create storage directory for generated images.');
            }
        }

        $fileName  = 'gd_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $filePath  = $saveDir . $fileName;
        $publicUrl = 'https://cfcbazar.42web.io/uploads/ai_images/' . $fileName;

        // Create canvas
        $im = @imagecreatetruecolor($width, $height);

        if (!$im) {
            throw new RuntimeException('Failed to initialize GD image stream.');
        }

        // Parse hex colors
        $bgHex   = ltrim($bgHex, '#');
        $textHex = ltrim($textHex, '#');

        if (strlen($bgHex) === 3) {
            $bgHex = $bgHex[0].$bgHex[0].$bgHex[1].$bgHex[1].$bgHex[2].$bgHex[2];
        }
        if (strlen($textHex) === 3) {
            $textHex = $textHex[0].$textHex[0].$textHex[1].$textHex[1].$textHex[2].$textHex[2];
        }

        list($rBg, $gBg, $bBg)       = sscanf($bgHex, "%02x%02x%02x");
        list($rTxt, $gTxt, $bTxt)    = sscanf($textHex, "%02x%02x%02x");

        $backgroundColor = imagecolorallocate($im, $rBg ?? 0, $gBg ?? 0, $bBg ?? 0);
        $textColor       = imagecolorallocate($im, $rTxt ?? 233, $gTxt ?? 14, $bTxt ?? 91);

        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $backgroundColor);

        // Auto-center text using GD built-in font
        $font       = 5; // Built-in font (1-5)
        $fontWidth  = imagefontwidth($font);
        $fontHeight = imagefontheight($font);

        $textLen = strlen($text);
        $x       = (int) max(10, ($width - ($fontWidth * $textLen)) / 2);
        $y       = (int) max(5, ($height - $fontHeight) / 2);

        imagestring($im, $font, $x, $y, $text, $textColor);

        // Write image file
        if (!imagepng($im, $filePath)) {
            imagedestroy($im);
            throw new RuntimeException('Failed to save generated PNG image to disk.');
        }

        imagedestroy($im);

        return $publicUrl;
    }
}
