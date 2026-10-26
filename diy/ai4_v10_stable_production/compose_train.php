<?php
// compose_train.php — Streaming ingest for data.jsonl
declare(strict_types=1);

if (PHP_SAPI !== 'cli') die("CLI only. Run: php compose_train.php [--rebuild]\n");

require_once __DIR__ . '/compose.php';

const DATA_FILE  = __DIR__ . '/data.jsonl';
const MEMORY_DIR = __DIR__ . '/memory';

// Big corpus → allow more memory and time
ini_set('memory_limit', '512M');
set_time_limit(0);

$rebuild = in_array('--rebuild', $argv ?? [], true);

echo "=== Compose: Training ===\n\n";

if (!file_exists(DATA_FILE)) die("data.jsonl not found\n");

// Optional rebuild
if ($rebuild && is_dir(MEMORY_DIR)) {
    echo "Wiping existing memory (--rebuild)...\n";
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(MEMORY_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir(MEMORY_DIR);
    echo "  done.\n\n";
}

// File info
$size = filesize(DATA_FILE);
$lineCount = count(file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
printf("File:  %s\n", DATA_FILE);
printf("Size:  %.2f MB\n", $size / 1048576);
printf("Lines: %d\n\n", $lineCount);

// Load existing memory (or start fresh)
$memory = new CompositionMemory(MEMORY_DIR);
$before = $memory->stats();
printf("Before: facts=%d  learned=%d  shards=%d\n\n",
    $before['facts'], $before['learned'], $before['factsShards']);

// Stream ingest with progress
$t0 = microtime(true);
$lastShown = 0;

try {
    $memory->ingestFile(DATA_FILE, function($lineNo, $facts, $learned) use ($t0, $lineCount, &$lastShown) {
        if ($lineNo - $lastShown < 5000) return;
        $lastShown = $lineNo;
        $elapsed = microtime(true) - $t0;
        $rate = $lineNo / max(0.001, $elapsed);
        $eta  = ($lineCount - $lineNo) / max(0.001, $rate);
        printf("  line %6d / %d (%.1f%%)  facts=%d learned=%d  %.0f/s  ETA %.0fs\n",
            $lineNo, $lineCount, 100 * $lineNo / $lineCount, $facts, $learned, $rate, $eta);
    });
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

$memory->saveNow();

// Build Markov transition matrix
echo "\nExtracting transitions...\n";
$t0t = microtime(true);
$memory->extractTransitions();
printf(" Done in %.2fs\n", microtime(true) - $t0t);

$elapsed = microtime(true) - $t0;

$after = $memory->stats();

echo "\n=== Summary ===\n";
echo "  Facts:          {$after['facts']}\n";
echo "  Learned pairs:  {$after['learned']}\n";
echo "  Fact shards:    {$after['factsShards']}\n";
echo "  Learned shards: {$after['learnedShards']}\n";
echo "  Index keys:     " . ($after['indexKeys'] ?? 0) . "\n";

$totalDisk = 0;
foreach (glob(MEMORY_DIR . '/*/*.json') as $f) $totalDisk += filesize($f);
if (file_exists(MEMORY_DIR . '/index.json')) $totalDisk += filesize(MEMORY_DIR . '/index.json');
printf("  Disk:           %.2f MB\n", $totalDisk / 1048576);
printf("  Time:           %.1fs (%.0f lines/s)\n", $elapsed, $lineCount / max(0.001, $elapsed));

$deltaFacts   = $after['facts']   - $before['facts'];
$deltaLearned = $after['learned'] - $before['learned'];
if ($deltaFacts !== 0 || $deltaLearned !== 0) {
    printf("  Delta:          +%d facts, +%d learned\n", $deltaFacts, $deltaLearned);
}
