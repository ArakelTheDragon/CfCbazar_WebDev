<?php
// ============================================================================
// CfCbazar AI PDF Download Handler
// File: /diy/ai/download.php
// ============================================================================

declare(strict_types=1);


// ============================================================================
// Security
// ============================================================================

$file = basename(
    (string)($_GET['file'] ?? '')
);

if ($file === '') {
    http_response_code(400);
    exit('Missing PDF filename.');
}


// Only allow generated AI PDFs.
if (!preg_match(
    '/^ai_pdf_[a-zA-Z0-9_-]+\.pdf$/',
    $file
)) {
    http_response_code(400);
    exit('Invalid PDF filename.');
}


// ============================================================================
// PDF directory
// ============================================================================

$pdfDir =
    __DIR__ . '/pdfs';


$filePath =
    $pdfDir . '/' . $file;


// ============================================================================
// Verify file
// ============================================================================

if (
    !is_file($filePath) ||
    !is_readable($filePath)
) {
    http_response_code(404);
    exit('PDF not found or it has already been deleted.');
}


// ============================================================================
// Get file information
// ============================================================================

$fileSize =
    filesize($filePath);

if ($fileSize === false) {
    http_response_code(500);
    exit('Unable to determine PDF size.');
}


// ============================================================================
// Send PDF
// ============================================================================

header(
    'Content-Type: application/pdf'
);

header(
    'Content-Length: ' . $fileSize
);

header(
    'Content-Disposition: attachment; filename="' .
    $file .
    '"'
);

header(
    'Content-Transfer-Encoding: binary'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


// ============================================================================
// Flush existing output
// ============================================================================

while (
    ob_get_level() > 0
) {
    ob_end_clean();
}


// ============================================================================
// Stream file
// ============================================================================

$handle =
    fopen(
        $filePath,
        'rb'
    );

if ($handle === false) {
    http_response_code(500);
    exit('Unable to open PDF.');
}


while (!feof($handle)) {

    $buffer =
        fread(
            $handle,
            8192
        );

    if ($buffer === false) {
        break;
    }

    echo $buffer;

    flush();
}


fclose($handle);


// ============================================================================
// Delete PDF AFTER it has been streamed
// ============================================================================
//
// The file is no longer needed on the server after the response has been
// sent. This removes the generated PDF from /pdfs/.
// ============================================================================

@unlink($filePath);

exit;