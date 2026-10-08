<?php
// ebook_maker.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../includes/reusable.php";

// --- Page metadata -----------------------------------------------------------
$url        = "diy/convert/index.php";
$parent_url = "diy/convert";
$title      = "Open Source Photo Converter Tool";
$template   = "Generic";   // optional: e.g. "tool", "article", "game"
$meta_title = "Open Source Photo Converter Tool";
$meta_desc  = "Convert JPG, PNG, WEBP, TIFF, and SVG images instantly (max 20MB).";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);


require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = "Title and content are required.";
    } else {
        $phpWord = new PhpWord();

        // Global styles
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(12);

        // Define styles
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 26], ['alignment' => 'center', 'spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => false, 'size' => 16, 'color' => '555555'], ['alignment' => 'center', 'spaceAfter' => 240]);

        $phpWord->addParagraphStyle('BodyParagraph', [
            'spaceAfter' => 240,
            'lineHeight' => 1.4
        ]);

        $phpWord->addParagraphStyle('SectionHeading', [
            'spaceBefore' => 360,
            'spaceAfter'  => 120,
        ]);

        $phpWord->addFontStyle('SectionHeadingFont', [
            'bold' => true,
            'size' => 14
        ]);

        // New section
        $section = $phpWord->addSection([
            'marginTop'    => 1440,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
        ]);

        // Title block
        $section->addTitle($title, 1);

        if ($subtitle !== '') {
            $section->addTitle($subtitle, 2);
        }

        $section->addTextBreak(2);

        // Simple rule: split content by "## " as section headings
        $blocks = preg_split('/\R?##\s+/', $content);

        // First block is intro/body before any heading
        if (!empty($blocks)) {
            $firstBlock = array_shift($blocks);
            if (trim($firstBlock) !== '') {
                $paragraphs = preg_split('/\R{2,}/', $firstBlock);
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $section->addText($p, [], 'BodyParagraph');
                    }
                }
            }
        }

        // Remaining blocks: each starts with a heading line (we lost it in split)
        // So we re-split original content to get headings + text
        preg_match_all('/##\s+(.+?)(\R{1,}[^#]+)?/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $heading = trim($match[1] ?? '');
            $body    = trim($match[2] ?? '');

            if ($heading !== '') {
                $section->addText($heading, 'SectionHeadingFont', 'SectionHeading');
            }

            if ($body !== '') {
                $paragraphs = preg_split('/\R{2,}/', $body);
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $section->addText($p, [], 'BodyParagraph');
                    }
                }
            }
        }

        // Footer with page numbers
        $footer = $section->addFooter();
        $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 10, 'color' => '777777'], ['alignment' => 'center']);

        // Save to temp file
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', strtolower($title));
        $filename  = $safeTitle !== '' ? $safeTitle : ('ebook_' . time());
        $filepath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.docx';

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        // Send file to browser
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mini Ebook Maker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #f5f5f7;
            --card-bg: #ffffff;
            --accent: #111827;
            --accent-soft: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text-main);
        }
        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header-title {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .header-sub {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            border: 1px solid var(--border);
        }
        .field-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        input[type="text"],
        textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 10px 11px;
            font-size: 0.95rem;
            font-family: inherit;
            resize: vertical;
            min-height: 42px;
            background: #f9fafb;
        }
        textarea {
            min-height: 260px;
            line-height: 1.4;
        }
        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            background: #ffffff;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.08);
        }
        .hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .btn-row {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
        }
        button {
            border: none;
            border-radius: 999px;
            padding: 9px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--accent);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        button:hover {
            background: #020617;
        }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 0.85rem;
            margin-bottom: 14px;
        }
        .footer {
            margin-top: 18px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        @media (min-width: 768px) {
            .card {
                padding: 24px 22px;
            }
            .header-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-title">Mini Ebook Maker</div>
        <div class="header-sub">Paste your content → get a clean, formatted DOCX ebook ready to refine or publish.</div>
    </div>

    <div class="card">
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field-group">
                <label for="title">Ebook title</label>
                <input type="text" id="title" name="title" required
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <div class="field-group">
                <label for="subtitle">Subtitle (optional)</label>
                <input type="text" id="subtitle" name="subtitle"
                       value="<?php echo isset($_POST['subtitle']) ? htmlspecialchars($_POST['subtitle'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>

            <div class="field-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" required><?php
                    echo isset($_POST['content']) ? htmlspecialchars($_POST['content'], ENT_QUOTES, 'UTF-8') : '';
                ?></textarea>
                <div class="hint">
                    Use <strong>## Heading</strong> on its own line to start a new section.<br>
                    Blank lines create paragraph breaks.
                </div>
            </div>

            <div class="btn-row">
                <button type="submit">
                    Generate DOCX
                </button>
            </div>
        </form>
    </div>

    <div class="footer">
        Generated DOCX uses a clean, high-contrast layout suitable for 5–20 page mini ebooks.
    </div>
</div>
</body>
</html>
