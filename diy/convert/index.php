<?php
// /diy/convert/index.php
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

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/convert/index.php";
$parent_url = "/diy/convert";
$title      = "Open Source Photo Converter Tool";
$template   = "Generic";
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

// --- DOCX library ------------------------------------------------------------
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// --- Form handling -----------------------------------------------------------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ebookTitle    = trim($_POST['title'] ?? '');
    $ebookSubtitle = trim($_POST['subtitle'] ?? '');
    $ebookContent  = trim($_POST['content'] ?? '');

    if ($ebookTitle === '' || $ebookContent === '') {
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
            'lineHeight' => 1.4,
        ]);

        $phpWord->addParagraphStyle('SectionHeading', [
            'spaceBefore' => 360,
            'spaceAfter'  => 120,
        ]);

        $phpWord->addFontStyle('SectionHeadingFont', [
            'bold' => true,
            'size' => 14,
        ]);

        // New section
        $section = $phpWord->addSection([
            'marginTop'    => 1440,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
        ]);

        // Title block
        $section->addTitle($ebookTitle, 1);

        if ($ebookSubtitle !== '') {
            $section->addTitle($ebookSubtitle, 2);
        }

        $section->addTextBreak(2);

        // Simple rule: split content by "## " as section headings
        $blocks = preg_split('/\R?##\s+/', $ebookContent);

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

        // Remaining blocks: each starts with a heading line
        preg_match_all('/##\s+(.+?)(\R{1,}[^#]+)?/s', $ebookContent, $matches, PREG_SET_ORDER);

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
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', strtolower($ebookTitle));
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

render_header($title);
?>

<main class="content">
    <div class="container">

        <div class="page">

            <div class="header">
                <div class="header-title">Mini Ebook Maker</div>
                <div class="header-sub">Paste your content → get a clean, formatted DOCX ebook ready to refine or publish.</div>
            </div>

            <div class="card">
                <?php if ($error !== ''): ?>
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

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/convert",
    "Photo Converter Source Code"
);
include_footer();
close_database();
?>
