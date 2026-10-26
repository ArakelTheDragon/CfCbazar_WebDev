<?php
// compose_train.php — Streaming ingest for data.jsonl + composition training
declare(strict_types=1);

if (PHP_SAPI !== 'cli') die("CLI only. Run: php compose_train.php [--rebuild]\n");

require_once __DIR__ . '/compose.php';

const DATA_FILE = __DIR__ . '/data.jsonl';
const COMPLEX_FILE = __DIR__ . '/data_complex.jsonl';
const MEMORY_DIR = __DIR__ . '/memory';

ini_set('memory_limit', '512M');
set_time_limit(0);

$rebuild = in_array('--rebuild', $argv ?? [], true);

echo "=== Compose: Training ===\n\n";

if (!file_exists(DATA_FILE)) die("data.jsonl not found\n");

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
    echo " done.\n\n";
}

$size = filesize(DATA_FILE);
$lineCount = count(file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
printf("File: %s\n", DATA_FILE);
printf("Size: %.2f MB\n", $size / 1048576);
printf("Lines: %d\n\n", $lineCount);

$memory = new CompositionMemory(MEMORY_DIR);
$before = $memory->stats();
printf("Before: facts=%d learned=%d shards=%d\n\n",
    $before['facts'], $before['learned'], $before['factsShards']);

$t0 = microtime(true);
$lastShown = 0;

try {
    $memory->ingestFile(DATA_FILE, function($lineNo, $facts, $learned) use ($t0, $lineCount, &$lastShown) {
        if ($lineNo - $lastShown < 5000) return;
        $lastShown = $lineNo;
        $elapsed = microtime(true) - $t0;
        $rate = $lineNo / max(0.001, $elapsed);
        $eta = ($lineCount - $lineNo) / max(0.001, $rate);
        printf(" line %6d / %d (%.1f%%) facts=%d learned=%d %.0f/s ETA %.0fs\n",
            $lineNo, $lineCount, 100 * $lineNo / $lineCount, $facts, $learned, $rate, $eta);
    });
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR (ingest): " . $e->getMessage() . "\n");
    exit(1);
}

$memory->saveNow();
$elapsed = microtime(true) - $t0;
$after1 = $memory->stats();

echo "\n=== Stage 1 Ingest Done ===\n";
echo " Facts: {$after1['facts']}\n";
echo " Learned pairs: {$after1['learned']}\n";
printf(" Ingest time: %.1fs\n\n", $elapsed);

echo "Extracting transitions...\n";
$t1 = microtime(true);
try {
    $memory->extractTransitions();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR (transitions): " . $e->getMessage() . "\n");
}
printf(" Done in %.2fs\n\n", microtime(true) - $t1);

if (file_exists(COMPLEX_FILE)) {
    $complexSize = filesize(COMPLEX_FILE);
    $complexCount = count(file(COMPLEX_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

    echo "Extracting compositions...\n";
    printf(" File: %s\n", COMPLEX_FILE);
    printf(" Size: %.2f MB (%d lines)\n", $complexSize / 1048576, $complexCount);

    $t2 = microtime(true);
    try {
        $memory->extractCompositions(COMPLEX_FILE);
        printf(" Done in %.2fs\n\n", microtime(true) - $t2);

        $compPath = MEMORY_DIR . '/composition.json';
        if (file_exists($compPath)) {
            $compData = json_decode(file_get_contents($compPath), true);
            $entries = $compData['entries'] ?? [];
            printf(" Total composition entries: %d\n", count($entries));
            if (!empty($entries)) {
                echo " Sample headers:\n";
                foreach (array_slice($entries, 0, 5) as $e) {
                    $spaceNames = array_map(fn($s) => $s['name'] ?? '?', $e['spaces'] ?? []);
                    printf(" %-30s → spaces: [%s]\n",
                        $e['key'] ?? '?',
                        implode(', ', $spaceNames));
                }
            }
            echo "\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR (compositions): " . $e->getMessage() . "\n");
    }
} else {
    echo "No data_complex.jsonl — Stage 2 composition skipped.\n\n";
}

$final = $memory->stats();

echo "=== Final Summary ===\n";
echo " Facts: {$final['facts']}\n";
echo " Learned pairs: {$final['learned']}\n";
echo " Fact shards: {$final['factsShards']}\n";
echo " Learned shards: {$final['learnedShards']}\n";
echo " Index keys: " . ($final['indexKeys'] ?? 0) . "\n";

$totalDisk = 0;
foreach (glob(MEMORY_DIR . '/*/*.json') as $f) $totalDisk += filesize($f);
foreach (glob(MEMORY_DIR . '/*.json') as $f) $totalDisk += filesize($f);
printf(" Disk: %.2f MB\n", $totalDisk / 1048576);
printf(" Total time: %.1fs\n", microtime(true) - $t0);
