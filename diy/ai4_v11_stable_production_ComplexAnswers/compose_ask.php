<?php
// compose_ask.php — Ask sharded composition memory
declare(strict_types=1);

if (PHP_SAPI !== 'cli') die("CLI only\n");

require_once __DIR__ . '/compose.php';

const MEMORY_DIR = __DIR__ . '/memory';

$memory = new CompositionMemory(MEMORY_DIR);
$s = $memory->stats();

if ($s['facts'] === 0 && $s['learned'] === 0) {
    die("Memory empty. Run: php compose_train.php\n");
}

$args = array_slice($argv, 1);

if (empty($args) || $args[0] === '-i' || $args[0] === '--interactive') {
    echo "=== Compose Interactive ===\n";
    echo "Facts: {$s['facts']}  Learned: {$s['learned']}\n\n";
    while (true) {
        echo "> ";
        $line = trim(fgets(STDIN) ?? '');
        if ($line === '' || in_array(strtolower($line), ['quit','exit','q'])) break;
        render($memory, $line);
    }
    exit(0);
}

if ($args[0] === '--batch') {
    $file = $args[1] ?? null;
    if (!$file || !file_exists($file)) die("Batch file not found\n");
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $r = $memory->ask($line, 3);
        printf("%-50s [%-9s] score=%.3f  → %s\n",
            "'".substr($line, 0, 48)."'",
            $r['status'], $r['score'],
            substr($r['answer'], 0, 50));
    }
    exit(0);
}

$query = implode(' ', $args);
render($memory, $query);

function render(CompositionMemory $memory, string $query): void {
    $r = $memory->ask($query, 3);
    echo "\nQuery: \"$query\"\n" . str_repeat('-', 64) . "\n";
    printf("[%s] score=%.3f\n", strtoupper($r['status']), $r['score']);

    if (!empty($r['parts']) && count($r['parts']) > 1) {
        echo "Composed from " . count($r['parts']) . " parts:\n";
        foreach ($r['parts'] as $i => $p) {
            printf("  %d. %s\n", $i + 1, $p);
        }
    }

    echo "\nAnswer: {$r['answer']}\n";

    if (!empty($r['top'])) {
        echo "\nTop facts:\n";
        foreach ($r['top'] as $t) {
            printf("  %.3f  %s\n", $t['score'], $t['text']);
        }
    }
    echo "\n";
} 