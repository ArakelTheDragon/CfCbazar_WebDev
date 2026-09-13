<?php
/**
 * /includes/agent_generate_pdf.php
 *
 * CfCbazar AI Agent PDF Generator
 *
 * Converts AI-generated Markdown/text into a formatted PDF.
 *
 * Supported:
 *   \# Heading
 *   \## Heading
 *   \### Heading
 *   **bold**
 *   *italic*
 *   - bullet
 *   * bullet
 *   1. numbered item
 *   ---
 *   `inline code`
 *
 * Also supports explicit page markers such as:
 *   \## PAGE 1: COVER
 *   \## PAGE 2: TABLE OF CONTENTS
 *
 * Special TOC support:
 *   1. Title ................................ 3
 */

if (!function_exists('agent_generate_pdf')) {

    function agent_generate_pdf(string $content): string
    {
        /*
         * -------------------------------------------------------------
         * Configuration
         * -------------------------------------------------------------
         */

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);

        $pdfDirectory = rtrim($documentRoot, '/\\') . '/diy/ai/pdfs';

        if (!is_dir($pdfDirectory)) {
            if (!@mkdir($pdfDirectory, 0755, true) && !is_dir($pdfDirectory)) {
                throw new RuntimeException('Unable to create PDF directory.');
            }
        }

        if (!is_writable($pdfDirectory)) {
            throw new RuntimeException('PDF directory is not writable.');
        }

        /*
         * Delete generated PDFs older than one hour.
         */

        $oldFiles = @glob($pdfDirectory . '/ai_pdf_*.pdf');

        if (is_array($oldFiles)) {
            $cutoff = time() - 3600;

            foreach ($oldFiles as $oldFile) {
                if (is_file($oldFile)) {
                    $mtime = @filemtime($oldFile);

                    if ($mtime !== false && $mtime < $cutoff) {
                        @unlink($oldFile);
                    }
                }
            }
        }

        /*
         * -------------------------------------------------------------
         * Validate content
         * -------------------------------------------------------------
         */

        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('PDF content cannot be empty.');
        }

        if (strlen($content) > 1000000) {
            throw new InvalidArgumentException(
                'PDF content is too large. Maximum size is 1,000,000 bytes.'
            );
        }

        /*
         * Normalize line endings.
         */

        $content = str_replace(["\r\n", "\r"], "\n", $content);

        /*
         * Remove UTF-8 BOM.
         */

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        /*
         * -------------------------------------------------------------
         * Page configuration
         *
         * US Letter: 8.5 x 11 inches
         * 612 x 792 points
         * -------------------------------------------------------------
         */

        $pageWidth  = 612;
        $pageHeight = 792;

        $marginLeft   = 54;
        $marginRight  = 54;
        $marginTop    = 58;
        $marginBottom = 55;

        $contentWidth = $pageWidth - $marginLeft - $marginRight;

        /*
         * Font sizes.
         */

        $bodySize       = 10.5;
        $bodyLeading    = 15;
        $h1Size         = 23;
        $h2Size         = 17;
        $h3Size         = 13;
        $coverTitleSize = 28;
        $coverSubSize   = 15;
        $smallSize      = 8.5;

        /*
         * -------------------------------------------------------------
         * Utility functions
         * -------------------------------------------------------------
         */

        $pdfEscape = static function (string $text): string {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace('(', '\(', $text);
            $text = str_replace(')', '\)', $text);

            return $text;
        };

        $normalizeTypography = static function (string $text): string {

            $replacements = [
                "\xE2\x80\x94" => '-',
                "\xE2\x80\x93" => '-',
                "\xE2\x80\x98" => "'",
                "\xE2\x80\x99" => "'",
                "\xE2\x80\x9C" => '"',
                "\xE2\x80\x9D" => '"',
                "\xE2\x80\xA6" => '...',
                "\xE2\x80\xA2" => '-',
                "\xC2\xA0"     => ' ',
                "\xE2\x86\x92" => '->',
                "\xE2\x86\x90" => '<-',
                "\xE2\x86\x91" => '^',
                "\xE2\x86\x93" => 'v',
                "\xE2\x84\xA2" => '(TM)',
                "\xC2\xAE"     => '(R)',
                "\xC2\xA9"     => '(C)',
            ];

            return strtr($text, $replacements);
        };

        $toPdfText = static function (string $text) use ($normalizeTypography): string {

            $text = $normalizeTypography($text);

            if (function_exists('iconv')) {
                $converted = @iconv(
                    'UTF-8',
                    'Windows-1252//TRANSLIT//IGNORE',
                    $text
                );

                if ($converted !== false) {
                    return $converted;
                }
            }

            $converted = @mb_convert_encoding(
                $text,
                'Windows-1252',
                'UTF-8'
            );

            if ($converted !== false) {
                return $converted;
            }

            return preg_replace('/[^\x20-\x7E]/', '', $text);
        };

        $estimateWidth = static function (
            string $text,
            float $fontSize,
            bool $bold = false,
            bool $italic = false
        ): float {

            $length = strlen($text);

            $average = 0.51;

            if ($bold) {
                $average += 0.02;
            }

            if ($italic) {
                $average += 0.01;
            }

            return $length * $fontSize * $average;
        };

        $wrapText = static function (
            string $text,
            float $fontSize,
            float $availableWidth,
            bool $bold = false
        ) use ($estimateWidth): array {

            $text = trim($text);

            if ($text === '') {
                return [''];
            }

            $words = preg_split('/\s+/', $text);

            if (!is_array($words)) {
                return [$text];
            }

            $lines = [];
            $current = '';

            foreach ($words as $word) {

                if ($word === '') {
                    continue;
                }

                $candidate = $current === ''
                    ? $word
                    : $current . ' ' . $word;

                if (
                    $current !== '' &&
                    $estimateWidth(
                        $candidate,
                        $fontSize,
                        $bold
                    ) > $availableWidth
                ) {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }

                if (
                    $current !== '' &&
                    $estimateWidth(
                        $current,
                        $fontSize,
                        $bold
                    ) > $availableWidth
                ) {

                    $characters = str_split($current);
                    $piece = '';

                    foreach ($characters as $character) {

                        $candidatePiece = $piece . $character;

                        if (
                            $piece !== '' &&
                            $estimateWidth(
                                $candidatePiece,
                                $fontSize,
                                $bold
                            ) > $availableWidth
                        ) {
                            $lines[] = $piece;
                            $piece = $character;
                        } else {
                            $piece = $candidatePiece;
                        }
                    }

                    $current = $piece;
                }
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            return $lines ?: [''];
        };

        /*
         * -------------------------------------------------------------
         * Parse Markdown
         * -------------------------------------------------------------
         */

        $rawLines = explode("\n", $content);

        $elements = [];

        $insideCodeBlock = false;
        $codeBuffer = [];

        foreach ($rawLines as $rawLine) {

            $line = rtrim($rawLine);

            if (preg_match('/^\s*```/', $line)) {

                if (!$insideCodeBlock) {
                    $insideCodeBlock = true;
                    $codeBuffer = [];
                } else {
                    $insideCodeBlock = false;

                    if ($codeBuffer) {
                        $elements[] = [
                            'type' => 'code',
                            'text' => implode("\n", $codeBuffer)
                        ];
                    }

                    $codeBuffer = [];
                }

                continue;
            }

            if ($insideCodeBlock) {
                $codeBuffer[] = $line;
                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:#{1,6}\s*)?PAGE\s+\d+(?:\s*:\s*(.*))?\s*$/i',
                    $line,
                    $matches
                )
            ) {

                $pageTitle = isset($matches[1])
                    ? trim($matches[1])
                    : '';

                $elements[] = [
                    'type'  => 'pagebreak',
                    'title' => $pageTitle
                ];

                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:---+|\*\*\*+|___+)\s*$/',
                    $line
                )
            ) {

                $elements[] = [
                    'type' => 'rule'
                ];

                continue;
            }

            if (preg_match('/^\s*#\s+(.+?)\s*$/', $line, $matches)) {

                $elements[] = [
                    'type' => 'h1',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            if (preg_match('/^\s*##\s+(.+?)\s*$/', $line, $matches)) {

                $elements[] = [
                    'type' => 'h2',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            if (preg_match('/^\s*###\s+(.+?)\s*$/', $line, $matches)) {

                $elements[] = [
                    'type' => 'h3',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            if (
                preg_match(
                    '/^\s*#{4,6}\s+(.+?)\s*$/',
                    $line,
                    $matches
                )
            ) {

                $elements[] = [
                    'type' => 'h4',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            // Table of Contents style lines
            if (
                preg_match(
                    '/^\s*(\d+\.\s+.+?)\s*\.{2,}\s*(\d+)\s*$/',
                    $line,
                    $matches
                )
            ) {
                $elements[] = [
                    'type'  => 'toc',
                    'title' => trim($matches[1]),
                    'page'  => $matches[2]
                ];
                continue;
            }

            if (
                preg_match(
                    '/^\s*[-*+]\s+(.+?)\s*$/',
                    $line,
                    $matches
                )
            ) {

                $elements[] = [
                    'type' => 'bullet',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            if (
                preg_match(
                    '/^\s*(\d+)[.)]\s+(.+?)\s*$/',
                    $line,
                    $matches
                )
            ) {

                $elements[] = [
                    'type'   => 'number',
                    'number' => $matches[1],
                    'text'   => trim($matches[2])
                ];

                continue;
            }

            if (
                preg_match(
                    '/^\s*>\s*(.+?)\s*$/',
                    $line,
                    $matches
                )
            ) {

                $elements[] = [
                    'type' => 'quote',
                    'text' => trim($matches[1])
                ];

                continue;
            }

            if (trim($line) === '') {

                $elements[] = [
                    'type' => 'space'
                ];

                continue;
            }

            $elements[] = [
                'type' => 'paragraph',
                'text' => trim($line)
            ];
        }

        if ($insideCodeBlock && $codeBuffer) {
            $elements[] = [
                'type' => 'code',
                'text' => implode("\n", $codeBuffer)
            ];
        }

        /*
         * -------------------------------------------------------------
         * Build PDF page content
         * -------------------------------------------------------------
         */

        $pages = [];

        $currentPage = [
            'content' => '',
            'y'       => $pageHeight - $marginTop,
            'cover'   => false
        ];

        $pageNumber = 1;

        $addText = static function (
            array &$page,
            string $text,
            float $x,
            float $y,
            float $size,
            string $font,
            callable $pdfEscape
        ): void {

            $escaped = $pdfEscape($text);

            $page['content'] .=
                "BT\n" .
                "/{$font} {$size} Tf\n" .
                "1 0 0 1 {$x} {$y} Tm\n" .
                "({$escaped}) Tj\n" .
                "ET\n";
        };

        $addRule = static function (
            array &$page,
            float $x1,
            float $y,
            float $x2
        ): void {

            $page['content'] .=
                "q\n" .
                "0.65 w\n" .
                "{$x1} {$y} m\n" .
                "{$x2} {$y} l\n" .
                "S\n" .
                "Q\n";
        };

        $finishPage = static function () use (
            &$pages,
            &$currentPage
        ): void {

            $pages[] = $currentPage;

            $currentPage = [
                'content' => '',
                'y'       => 0,
                'cover'   => false
            ];
        };

        $newPage = static function () use (
            &$currentPage,
            &$pageNumber,
            $pageHeight,
            $marginTop
        ): void {

            $pageNumber++;

            $currentPage = [
                'content' => '',
                'y'       => $pageHeight - $marginTop,
                'cover'   => false
            ];
        };

        $ensureSpace = static function (
            float $needed
        ) use (
            &$currentPage,
            $finishPage,
            $newPage,
            $pageHeight,
            $marginTop,
            $marginBottom
        ): void {

            if (
                $currentPage['y'] - $needed <
                $marginBottom
            ) {
                $finishPage();
                $newPage();
            }
        };

        $parseInline = static function (
            string $text
        ): array {

            $runs = [];

            $pattern =
                '/(\*\*.+?\*\*|' .
                '\*.+?\*|' .
                '_.+?_|' .
                '`.+?`)/';

            $parts = preg_split(
                $pattern,
                $text,
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );

            if (!is_array($parts)) {
                return [
                    [
                        'text' => $text,
                        'bold' => false,
                        'italic' => false,
                        'code' => false
                    ]
                ];
            }

            foreach ($parts as $part) {

                if ($part === '') {
                    continue;
                }

                if (
                    strlen($part) >= 4 &&
                    substr($part, 0, 2) === '**' &&
                    substr($part, -2) === '**'
                ) {

                    $runs[] = [
                        'text'   => substr($part, 2, -2),
                        'bold'   => true,
                        'italic' => false,
                        'code'   => false
                    ];

                    continue;
                }

                if (
                    strlen($part) >= 2 &&
                    $part[0] === '*' &&
                    substr($part, -1) === '*'
                ) {

                    $runs[] = [
                        'text'   => substr($part, 1, -1),
                        'bold'   => false,
                        'italic' => true,
                        'code'   => false
                    ];

                    continue;
                }

                if (
                    strlen($part) >= 2 &&
                    $part[0] === '_' &&
                    substr($part, -1) === '_'
                ) {

                    $runs[] = [
                        'text'   => substr($part, 1, -1),
                        'bold'   => false,
                        'italic' => true,
                        'code'   => false
                    ];

                    continue;
                }

                if (
                    strlen($part) >= 2 &&
                    $part[0] === '`' &&
                    substr($part, -1) === '`'
                ) {

                    $runs[] = [
                        'text'   => substr($part, 1, -1),
                        'bold'   => false,
                        'italic' => false,
                        'code'   => true
                    ];

                    continue;
                }

                $runs[] = [
                    'text'   => $part,
                    'bold'   => false,
                    'italic' => false,
                    'code'   => false
                ];
            }

            return $runs;
        };

        $renderInlineLine = static function (
            array &$page,
            string $text,
            float $x,
            float $y,
            float $fontSize,
            float $availableWidth
        ) use (
            $parseInline,
            $toPdfText,
            $pdfEscape,
            $estimateWidth,
            $addText
        ): void {

            $runs = $parseInline($text);

            $cursorX = $x;

            foreach ($runs as $run) {

                $runText = $run['text'];

                if ($runText === '') {
                    continue;
                }

                $runText = $toPdfText($runText);

                $font = 'F1';

                if ($run['bold']) {
                    $font = 'F2';
                } elseif ($run['italic']) {
                    $font = 'F3';
                }

                if ($run['code']) {
                    $font = 'F4';
                }

                $addText(
                    $page,
                    $runText,
                    $cursorX,
                    $y,
                    $fontSize,
                    $font,
                    $pdfEscape
                );

                $cursorX += $estimateWidth(
                    $runText,
                    $fontSize,
                    $run['bold'],
                    $run['italic']
                );
            }
        };

        /*
         * -------------------------------------------------------------
         * Cover detection
         * -------------------------------------------------------------
         */

        $coverTitle = '';
        $coverSubtitle = '';
        $coverAuthor = '';

        foreach ($elements as $element) {

            if ($element['type'] === 'h1' && $coverTitle === '') {
                $coverTitle = $element['text'];
                continue;
            }

            if (
                $element['type'] === 'h2' &&
                $coverSubtitle === ''
            ) {
                $coverSubtitle = $element['text'];
                continue;
            }

            if (
                $element['type'] === 'paragraph' &&
                preg_match(
                    '/^\*?\*?A Practical .+?\*?\*?$/i',
                    trim($element['text'])
                )
            ) {
                $coverAuthor = $element['text'];
                break;
            }
        }

        /*
         * -------------------------------------------------------------
         * Render elements
         * -------------------------------------------------------------
         */

        $hasRenderedContent = false;

        foreach ($elements as $index => $element) {

            $type = $element['type'];

            if ($type === 'pagebreak') {

                if (
                    trim($currentPage['content']) !== ''
                ) {
                    $finishPage();
                    $newPage();
                }

                $pageTitle = strtolower(
                    trim($element['title'] ?? '')
                );

                if (
                    str_contains($pageTitle, 'cover') &&
                    $coverTitle !== ''
                ) {

                    $currentPage['cover'] = true;

                    $titleLines = $wrapText(
                        $coverTitle,
                        $coverTitleSize,
                        $contentWidth - 40,
                        true
                    );

                    $coverY = 545;

                    foreach ($titleLines as $titleLine) {

                        $lineWidth = $estimateWidth(
                            $titleLine,
                            $coverTitleSize,
                            true
                        );

                        $x =
                            ($pageWidth - $lineWidth) / 2;

                        $addText(
                            $currentPage,
                            $toPdfText($titleLine),
                            $x,
                            $coverY,
                            $coverTitleSize,
                            'F2',
                            $pdfEscape
                        );

                        $coverY -= 38;
                    }

                    if ($coverSubtitle !== '') {

                        $subtitleLines = $wrapText(
                            $coverSubtitle,
                            $coverSubSize,
                            $contentWidth - 70,
                            false
                        );

                        $coverY -= 20;

                        foreach ($subtitleLines as $subtitleLine) {

                            $lineWidth = $estimateWidth(
                                $subtitleLine,
                                $coverSubSize
                            );

                            $x =
                                ($pageWidth - $lineWidth) / 2;

                            $addText(
                                $currentPage,
                                $toPdfText($subtitleLine),
                                $x,
                                $coverY,
                                $coverSubSize,
                                'F3',
                                $pdfEscape
                            );

                            $coverY -= 23;
                        }
                    }

                    $coverY -= 35;

                    $addRule(
                        $currentPage,
                        170,
                        $coverY,
                        442
                    );

                    $coverY -= 45;

                    $authorText = $coverAuthor !== ''
                        ? $coverAuthor
                        : 'CfCbazar AI';

                    $authorText = preg_replace(
                        '/[*_`]/',
                        '',
                        $authorText
                    );

                    $authorText = trim($authorText);

                    if (
                        stripos($authorText, 'By:') !== 0 &&
                        stripos($authorText, 'by ') !== 0
                    ) {
                        $authorText = 'By: ' . $authorText;
                    }

                    $authorWidth = $estimateWidth(
                        $authorText,
                        11
                    );

                    $addText(
                        $currentPage,
                        $toPdfText($authorText),
                        ($pageWidth - $authorWidth) / 2,
                        $coverY,
                        11,
                        'F1',
                        $pdfEscape
                    );

                    $coverY -= 35;

                    $brand = 'CfCbazar AI';

                    $brandWidth = $estimateWidth(
                        $brand,
                        9
                    );

                    $addText(
                        $currentPage,
                        $brand,
                        ($pageWidth - $brandWidth) / 2,
                        $coverY,
                        9,
                        'F1',
                        $pdfEscape
                    );

                    $hasRenderedContent = true;

                    continue;
                }

                continue;
            }

            if ($type === 'h1') {

                $text = preg_replace(
                    '/[*_`]/',
                    '',
                    $element['text']
                );

                $lines = $wrapText(
                    $text,
                    $h1Size,
                    $contentWidth,
                    true
                );

                $needed =
                    count($lines) * 29 + 18;

                $ensureSpace($needed);

                $currentPage['y'] -= 8;

                foreach ($lines as $line) {

                    $addText(
                        $currentPage,
                        $toPdfText($line),
                        $marginLeft,
                        $currentPage['y'],
                        $h1Size,
                        'F2',
                        $pdfEscape
                    );

                    $currentPage['y'] -= 29;
                }

                $addRule(
                    $currentPage,
                    $marginLeft,
                    $currentPage['y'] + 7,
                    $pageWidth - $marginRight
                );

                $currentPage['y'] -= 18;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'h2') {

                $text = preg_replace(
                    '/[*_`]/',
                    '',
                    $element['text']
                );

                $lines = $wrapText(
                    $text,
                    $h2Size,
                    $contentWidth,
                    true
                );

                $needed =
                    count($lines) * 22 + 20;

                $ensureSpace($needed);

                $currentPage['y'] -= 6;

                foreach ($lines as $line) {

                    $addText(
                        $currentPage,
                        $toPdfText($line),
                        $marginLeft,
                        $currentPage['y'],
                        $h2Size,
                        'F2',
                        $pdfEscape
                    );

                    $currentPage['y'] -= 22;
                }

                $currentPage['y'] -= 12;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'h3') {

                $text = preg_replace(
                    '/[*_`]/',
                    '',
                    $element['text']
                );

                $lines = $wrapText(
                    $text,
                    $h3Size,
                    $contentWidth,
                    true
                );

                $ensureSpace(
                    count($lines) * 18 + 15
                );

                $currentPage['y'] -= 5;

                foreach ($lines as $line) {

                    $addText(
                        $currentPage,
                        $toPdfText($line),
                        $marginLeft,
                        $currentPage['y'],
                        $h3Size,
                        'F2',
                        $pdfEscape
                    );

                    $currentPage['y'] -= 18;
                }

                $currentPage['y'] -= 10;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'h4') {

                $text = preg_replace(
                    '/[*_`]/',
                    '',
                    $element['text']
                );

                $lines = $wrapText(
                    $text,
                    11,
                    $contentWidth,
                    true
                );

                $ensureSpace(
                    count($lines) * 16 + 12
                );

                foreach ($lines as $line) {

                    $addText(
                        $currentPage,
                        $toPdfText($line),
                        $marginLeft,
                        $currentPage['y'],
                        11,
                        'F2',
                        $pdfEscape
                    );

                    $currentPage['y'] -= 16;
                }

                $currentPage['y'] -= 6;

                $hasRenderedContent = true;

                continue;
            }

            // Table of Contents entry
            if ($type === 'toc') {

                $title = $element['title'];
                $pageNum = $element['page'];

                $ensureSpace($bodyLeading + 8);

                $addText(
                    $currentPage,
                    $toPdfText($title),
                    $marginLeft,
                    $currentPage['y'],
                    $bodySize,
                    'F1',
                    $pdfEscape
                );

                $pageWidthEst = $estimateWidth($pageNum, $bodySize);
                $addText(
                    $currentPage,
                    $toPdfText($pageNum),
                    $pageWidth - $marginRight - $pageWidthEst,
                    $currentPage['y'],
                    $bodySize,
                    'F1',
                    $pdfEscape
                );

                $currentPage['y'] -= $bodyLeading + 4;

                $hasRenderedContent = true;
                continue;
            }

            if ($type === 'paragraph') {

                $text = trim($element['text']);

                if ($text === '') {
                    continue;
                }

                if (
                    preg_match(
                        '/^\s*PAGE\s+\d+/i',
                        $text
                    )
                ) {
                    continue;
                }

                $plainForWrap = preg_replace(
                    '/(\*\*|\*|_|`)/',
                    '',
                    $text
                );

                $lines = $wrapText(
                    $plainForWrap,
                    $bodySize,
                    $contentWidth
                );

                $ensureSpace(
                    count($lines) * $bodyLeading + 8
                );

                foreach ($lines as $line) {

                    $renderInlineLine(
                        $currentPage,
                        $line,
                        $marginLeft,
                        $currentPage['y'],
                        $bodySize,
                        $contentWidth
                    );

                    $currentPage['y'] -= $bodyLeading;
                }

                $currentPage['y'] -= 5;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'bullet') {

                $text = trim($element['text']);

                $plain = preg_replace(
                    '/(\*\*|\*|_|`)/',
                    '',
                    $text
                );

                $textIndent = 28;

                $lines = $wrapText(
                    $plain,
                    $bodySize,
                    $contentWidth - $textIndent
                );

                $ensureSpace(
                    count($lines) * $bodyLeading + 5
                );

                foreach ($lines as $lineIndex => $line) {

                    if ($lineIndex === 0) {

                        $addText(
                            $currentPage,
                            '-',
                            $marginLeft + 4,
                            $currentPage['y'],
                            $bodySize,
                            'F2',
                            $pdfEscape
                        );
                    }

                    $renderInlineLine(
                        $currentPage,
                        $line,
                        $marginLeft + $textIndent,
                        $currentPage['y'],
                        $bodySize,
                        $contentWidth - $textIndent
                    );

                    $currentPage['y'] -= $bodyLeading;
                }

                $currentPage['y'] -= 2;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'number') {

                $number = $element['number'];
                $text = trim($element['text']);

                $prefix = $number . '.';

                $textIndent = 30;

                $plain = preg_replace(
                    '/(\*\*|\*|_|`)/',
                    '',
                    $text
                );

                $lines = $wrapText(
                    $plain,
                    $bodySize,
                    $contentWidth - $textIndent
                );

                $ensureSpace(
                    count($lines) * $bodyLeading + 5
                );

                foreach ($lines as $lineIndex => $line) {

                    if ($lineIndex === 0) {

                        $addText(
                            $currentPage,
                            $toPdfText($prefix),
                            $marginLeft,
                            $currentPage['y'],
                            $bodySize,
                            'F2',
                            $pdfEscape
                        );
                    }

                    $renderInlineLine(
                        $currentPage,
                        $line,
                        $marginLeft + $textIndent,
                        $currentPage['y'],
                        $bodySize,
                        $contentWidth - $textIndent
                    );

                    $currentPage['y'] -= $bodyLeading;
                }

                $currentPage['y'] -= 2;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'quote') {

                $text = trim($element['text']);

                $plain = preg_replace(
                    '/(\*\*|\*|_|`)/',
                    '',
                    $text
                );

                $quoteIndent = 25;

                $lines = $wrapText(
                    $plain,
                    10,
                    $contentWidth - 45
                );

                $ensureSpace(
                    count($lines) * 14 + 12
                );

                $quoteTop = $currentPage['y'] + 4;

                foreach ($lines as $line) {

                    $renderInlineLine(
                        $currentPage,
                        $line,
                        $marginLeft + $quoteIndent,
                        $currentPage['y'],
                        10,
                        $contentWidth - 45
                    );

                    $currentPage['y'] -= 14;
                }

                $quoteBottom = $currentPage['y'] + 3;

                $currentPage['content'] .=
                    "q\n" .
                    "2 w\n" .
                    ($marginLeft + 10) . " {$quoteTop} m\n" .
                    ($marginLeft + 10) . " {$quoteBottom} l\n" .
                    "S\n" .
                    "Q\n";

                $currentPage['y'] -= 8;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'code') {

                $codeLines = explode(
                    "\n",
                    $element['text']
                );

                $ensureSpace(
                    min(count($codeLines), 10) * 12 + 15
                );

                foreach ($codeLines as $codeLine) {

                    $codeLine = trim($codeLine);

                    if ($codeLine === '') {
                        $codeLine = ' ';
                    }

                    $wrappedCode = $wrapText(
                        $codeLine,
                        8,
                        $contentWidth - 20
                    );

                    foreach ($wrappedCode as $line) {

                        $addText(
                            $currentPage,
                            $toPdfText($line),
                            $marginLeft + 10,
                            $currentPage['y'],
                            8,
                            'F4',
                            $pdfEscape
                        );

                        $currentPage['y'] -= 11;
                    }
                }

                $currentPage['y'] -= 8;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'rule') {

                $ensureSpace(20);

                $currentPage['y'] -= 4;

                $addRule(
                    $currentPage,
                    $marginLeft,
                    $currentPage['y'],
                    $pageWidth - $marginRight
                );

                $currentPage['y'] -= 15;

                $hasRenderedContent = true;

                continue;
            }

            if ($type === 'space') {

                if ($hasRenderedContent) {
                    $currentPage['y'] -= 7;
                }

                continue;
            }
        }

        if (
            trim($currentPage['content']) !== '' ||
            empty($pages)
        ) {
            $pages[] = $currentPage;
        }

        /*
         * -------------------------------------------------------------
         * Add headers and footers
         * -------------------------------------------------------------
         */

        $totalPages = count($pages);

        foreach ($pages as $index => &$page) {

            $displayPage = $index + 1;

            if (!empty($page['cover'])) {
                continue;
            }

            $headerText = 'CfCbazar AI';

            $page['content'] .=
                "BT\n" .
                "/F1 {$smallSize} Tf\n" .
                "1 0 0 1 {$marginLeft} " .
                ($pageHeight - 30) .
                " Tm\n" .
                "(" .
                $pdfEscape(
                    $toPdfText($headerText)
                ) .
                ") Tj\n" .
                "ET\n";

            $page['content'] .=
                "q\n" .
                "0.35 w\n" .
                "{$marginLeft} " .
                ($pageHeight - 38) .
                " m\n" .
                ($pageWidth - $marginRight) .
                " " .
                ($pageHeight - 38) .
                " l\n" .
                "S\n" .
                "Q\n";

            $page['content'] .=
                "q\n" .
                "0.35 w\n" .
                "{$marginLeft} 38 m\n" .
                ($pageWidth - $marginRight) .
                " 38 l\n" .
                "S\n" .
                "Q\n";

            $pageNumberText =
                'Page ' .
                $displayPage .
                ' of ' .
                $totalPages;

            $pageNumberWidth = $estimateWidth(
                $pageNumberText,
                $smallSize
            );

            $page['content'] .=
                "BT\n" .
                "/F1 {$smallSize} Tf\n" .
                "1 0 0 1 " .
                (($pageWidth - $pageNumberWidth) / 2) .
                " 22 Tm\n" .
                "(" .
                $pdfEscape(
                    $toPdfText($pageNumberText)
                ) .
                ") Tj\n" .
                "ET\n";
        }

        unset($page);

        /*
         * -------------------------------------------------------------
         * Build PDF objects
         * -------------------------------------------------------------
         */

        $objects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '';

        $objects[] =
            '<< /Type /Font /Subtype /Type1 ' .
            '/BaseFont /Helvetica ' .
            '/Encoding /WinAnsiEncoding >>';

        $objects[] =
            '<< /Type /Font /Subtype /Type1 ' .
            '/BaseFont /Helvetica-Bold ' .
            '/Encoding /WinAnsiEncoding >>';

        $objects[] =
            '<< /Type /Font /Subtype /Type1 ' .
            '/BaseFont /Helvetica-Oblique ' .
            '/Encoding /WinAnsiEncoding >>';

        $objects[] =
            '<< /Type /Font /Subtype /Type1 ' .
            '/BaseFont /Courier ' .
            '/Encoding /WinAnsiEncoding >>';

        $pageObjectNumbers = [];

        foreach ($pages as $pageIndex => $page) {

            $pageObjectNumber =
                count($objects) + 1;

            $pageObjectNumbers[] =
                $pageObjectNumber;

            $contentObjectNumber =
                $pageObjectNumber + 1;

            $objects[] =
                '<< /Type /Page ' .
                '/Parent 2 0 R ' .
                '/MediaBox [0 0 612 792] ' .
                '/Resources << ' .
                '/Font << ' .
                '/F1 3 0 R ' .
                '/F2 4 0 R ' .
                '/F3 5 0 R ' .
                '/F4 6 0 R ' .
                '>> ' .
                '>> ' .
                '/Contents ' .
                $contentObjectNumber .
                ' 0 R >>';

            $stream =
                $page['content'];

            $objects[] =
                '<< /Length ' .
                strlen($stream) .
                " >>\n" .
                "stream\n" .
                $stream .
                "endstream";
        }

        $kids = [];

        foreach ($pageObjectNumbers as $number) {
            $kids[] = $number . ' 0 R';
        }

        $objects[1] =
            '<< /Type /Pages ' .
            '/Kids [' .
            implode(' ', $kids) .
            '] ' .
            '/Count ' .
            count($pageObjectNumbers) .
            ' >>';

        /*
         * -------------------------------------------------------------
         * Generate final PDF
         * -------------------------------------------------------------
         */

        $pdf = "%PDF-1.4\n";
        $pdf .= "%\xE2\xE3\xCF\xD3\n";

        $offsets = [0];

        foreach ($objects as $objectIndex => $objectBody) {

            $objectNumber = $objectIndex + 1;

            $offsets[$objectNumber] =
                strlen($pdf);

            $pdf .=
                $objectNumber .
                " 0 obj\n" .
                $objectBody .
                "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);

        $objectCount = count($objects);

        $pdf .=
            "xref\n" .
            "0 " .
            ($objectCount + 1) .
            "\n";

        $pdf .=
            "0000000000 65535 f \n";

        for ($i = 1; $i <= $objectCount; $i++) {

            $offset =
                $offsets[$i] ?? 0;

            $pdf .=
                str_pad(
                    (string)$offset,
                    10,
                    '0',
                    STR_PAD_LEFT
                ) .
                " 00000 n \n";
        }

        $pdf .=
            "trailer\n" .
            "<< /Size " .
            ($objectCount + 1) .
            " /Root 1 0 R >>\n" .
            "startxref\n" .
            $xrefOffset .
            "\n" .
            "%%EOF";

        /*
         * -------------------------------------------------------------
         * Write secure random filename
         * -------------------------------------------------------------
         */

        try {
            $random = bin2hex(
                random_bytes(16)
            );
        } catch (Throwable $e) {
            $random = sha1(
                uniqid('', true) .
                microtime(true)
            );
        }

        // IMPORTANT: Filename must start with "ai_pdf_" to match download.php
        $filename =
            'ai_pdf_' .
            date('Ymd_His') .
            '_' .
            $random .
            '.pdf';

        $filepath =
            $pdfDirectory .
            DIRECTORY_SEPARATOR .
            $filename;

        if (file_exists($filepath)) {
            throw new RuntimeException(
                'Unable to create a unique PDF filename.'
            );
        }

        $bytesWritten = @file_put_contents(
            $filepath,
            $pdf,
            LOCK_EX
        );

        if ($bytesWritten === false) {
            throw new RuntimeException(
                'Unable to write the generated PDF.'
            );
        }

        if (
            $bytesWritten < 100 ||
            !is_file($filepath)
        ) {
            @unlink($filepath);

            throw new RuntimeException(
                'Generated PDF failed validation.'
            );
        }

        /*
         * -------------------------------------------------------------
         * Return download URL
         * -------------------------------------------------------------
         */

        $host =
            $_SERVER['HTTP_HOST'] ??
            '';

        $host = preg_replace(
            '/[^a-zA-Z0-9.\-:\[\]]/',
            '',
            $host
        );

        if ($host === '') {
            @unlink($filepath);

            throw new RuntimeException(
                'Unable to determine the website host.'
            );
        }

        $https =
            (!empty($_SERVER['HTTPS']) &&
             strtolower((string)$_SERVER['HTTPS']) !== 'off')
            ||
            (
                isset($_SERVER['SERVER_PORT']) &&
                (int)$_SERVER['SERVER_PORT'] === 443
            );

        $scheme = $https ? 'https' : 'http';

        return
            $scheme .
            '://' .
            $host .
            '/diy/ai/download.php?file=' .
            rawurlencode($filename);
    }
}