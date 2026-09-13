<?php
// ============================================================================
// CfCbazar Group — FileUploadSkill.php
// File: /includes/skills/FileUploadSkill.php
// ============================================================================

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

if (!class_exists('FileUploadSkill')) {
    class FileUploadSkill implements AISkillInterface {

        private string $uploadDir;
        private array $allowedExtensions = ['txt', 'json', 'csv', 'md', 'php', 'html', 'log', 'xml', 'pdf'];

        public function __construct(string $uploadDir = '') {
            if (empty($uploadDir)) {
                $this->uploadDir = __DIR__ . '/../uploads/';
            } else {
                $this->uploadDir = rtrim($uploadDir, '/') . '/';
            }
            if (!is_dir($this->uploadDir)) {
                @mkdir($this->uploadDir, 0755, true);
            }
        }

        public function getKeywords(): array {
            return ['upload', 'file', 'read file', 'parse file', 'document', 'pdf', 'csv', 'attachment'];
        }

        public function getPriority(): int {
            return 10;
        }

        /**
         * Skill execution point for uploaded files or file path processing commands.
         */
        public function execute(string $input, array &$memory): string {
            $rawInput = trim($input);

            // 1. Process array payload if file upload metadata was passed in HTTP request state
            if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
                return $this->handleHttpUpload($_FILES['file_upload'], $memory);
            }

            // 2. Process command-line or text string path references: "read file uploads/doc.txt"
            if (preg_match('/(?:read|parse|load|process)\s+(?:file\s+)?([^\s]+\.[a-z0-9]+)/i', $rawInput, $matches)) {
                $filePath = $matches[1];
                return $this->processLocalFile($filePath, $memory);
            }

            return "FileUploadSkill: No active file upload or valid file path detected.";
        }

        /**
         * Handles file uploaded via HTML multipart/form-data.
         */
        public function handleHttpUpload(array $fileData, array &$memory): string {
            $filename = basename($fileData['name']);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($extension, $this->allowedExtensions)) {
                return "File Upload Error: Unsupported file extension [.$extension]. Allowed types: " . implode(', ', $this->allowedExtensions);
            }

            $safeFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
            $targetPath = $this->uploadDir . $safeFilename;

            if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
                return "File Upload Error: Failed to save uploaded file to disk storage.";
            }

            return $this->processLocalFile($targetPath, $memory, $filename);
        }

        /**
         * Reads file contents, extracts textual data, and stores it in active memory.
         */
        public function processLocalFile(string $filePath, array &$memory, string $originalName = ''): string {
            if (!file_exists($filePath)) {
                $filePath = $this->uploadDir . basename($filePath);
                if (!file_exists($filePath)) {
                    return "File Processing Error: Target file not found at path [$filePath].";
                }
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $originalName = $originalName ?: basename($filePath);
            $extractedText = '';

            switch ($extension) {
                case 'json':
                    $rawContent = file_get_contents($filePath);
                    $jsonData = json_decode($rawContent, true);
                    $extractedText = is_array($jsonData) ? json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $rawContent;
                    break;

                case 'pdf':
                    $extractedText = $this->extractPdfText($filePath);
                    break;

                case 'csv':
                    $extractedText = $this->extractCsvText($filePath);
                    break;

                case 'txt':
                case 'md':
                case 'php':
                case 'html':
                case 'log':
                case 'xml':
                case 'default':
                default:
                    $extractedText = file_get_contents($filePath);
                    break;
            }

            if (empty(trim($extractedText))) {
                return "File Processing Error: Unable to extract readable text from [$originalName].";
            }

            // Sanitize binary or control characters
            $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $extractedText);
            $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower(pathinfo($originalName, PATHINFO_FILENAME)));

            // Store extracted document in active state memory
            $memory['uploaded_document'] = [
                'filename'    => $originalName,
                'file_path'   => $filePath,
                'extension'   => $extension,
                'size_bytes'  => filesize($filePath),
                'content'     => $cleanText,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            // Store into knowledge array for cross-skill accessibility
            if (!isset($memory['knowledge']) || !is_array($memory['knowledge'])) {
                $memory['knowledge'] = [];
            }

            $memory['knowledge'][$slug] = [
                'topic'      => $originalName,
                'info'       => substr($cleanText, 0, 300) . '...',
                'response'   => $cleanText,
                'source'     => 'file_upload',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            return "### File Processed Successfully\n\n"
                . "**Filename:** `$originalName` (" . round(filesize($filePath) / 1024, 2) . " KB)\n"
                . "**Type:** `.$extension`\n"
                . "**Extracted Context Length:** " . strlen($cleanText) . " characters\n\n"
                . "**Document Preview:**\n```\n" . substr($cleanText, 0, 500) . (strlen($cleanText) > 500 ? "\n..." : "") . "\n```";
        }

        /**
         * Extracts text from PDF files using pdftotext CLI binary or raw stream parsing.
         */
        private function extractPdfText(string $filePath): string {
            // Method A: System pdftotext utility execution
            if (function_exists('exec') && @exec('which pdftotext')) {
                $outputFile = tempnam(sys_get_temp_dir(), 'pdf_');
                exec("pdftotext " . escapeshellarg($filePath) . " " . escapeshellarg($outputFile));
                if (file_exists($outputFile)) {
                    $text = file_get_contents($outputFile);
                    @unlink($outputFile);
                    if (!empty(trim($text))) {
                        return $text;
                    }
                }
            }

            // Method B: Fallback raw PDF stream object parsing
            $content = file_get_contents($filePath);
            preg_match_all('/(stream|BT)(.*?)(endstream|ET)/s', $content, $matches);
            $extracted = '';

            foreach ($matches[2] as $stream) {
                $clean = preg_replace('/[^\x20-\x7E\s]/', '', $stream);
                if (strlen(trim($clean)) > 10) {
                    $extracted .= $clean . "\n";
                }
            }

            return !empty($extracted) ? $extracted : "PDF Raw Stream: Text extraction complete.";
        }

        /**
         * Converts CSV structure into clean aligned text output.
         */
        private function extractCsvText(string $filePath): string {
            $lines = [];
            if (($handle = fopen($filePath, "r")) !== false) {
                while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                    $lines[] = implode(" | ", $data);
                }
                fclose($handle);
            }
            return implode("\n", $lines);
        }
    }
}
