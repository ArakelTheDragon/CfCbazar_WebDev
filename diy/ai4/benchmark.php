<?php
// benchmark.php — Measure retrieval accuracy across query types
declare(strict_types=1);

require_once __DIR__ . '/compose.php';

$memory = new CompositionMemory(__DIR__ . '/memory');

// Format: [query, expected_substring, category, expect_min_score]
$tests = [
// ============================================================
// CATEGORY: single-intent exact (should be 100%)
// ============================================================
['what is water', 'liquid', 'single-exact', 0.9],
['who wrote hamlet', 'shakespeare', 'single-exact', 0.9],
['who invented the telephone', 'bell', 'single-exact', 0.9],
['how do i make tea', 'boil', 'single-exact', 0.9],
['explain gravity', 'attract', 'single-exact', 0.9],
['what is the moon', 'satellite', 'single-exact', 0.9],
['capital of france', 'paris', 'single-exact', 0.9],
['what is beer', 'alcoholic', 'single-exact', 0.9],
['what is the sun', 'star', 'single-exact', 0.9],
['what is love', 'affection', 'single-exact', 0.9],

// ============================================================
// CATEGORY: single-intent synonym (should be >70%)
// ============================================================
['describe water', 'liquid', 'synonym', 0.4],
['tell me about water', 'liquid', 'synonym', 0.4],
['explain water', 'liquid', 'synonym', 0.4],
['describe the sun', 'star', 'synonym', 0.4],
['tell me about beer', 'alcoholic', 'synonym', 0.4],
['describe gravity', 'attract', 'synonym', 0.4],
['explain love', 'affection', 'synonym', 0.4],
['tell me about coffee', 'brew', 'synonym', 0.4],

// ============================================================
// CATEGORY: position-varied (should be >60%)
// ============================================================
['france is capital of', 'paris', 'position', 0.4],
['the capital of france is','paris', 'position', 0.4],
['the sun is described how','star', 'position', 0.3],
['describe what water is', 'liquid', 'position', 0.3],

// ============================================================
// CATEGORY: compound exact (should be 100% — in training data)
// ============================================================
['who is shakespeare and what did he write', 'shakespeare', 'compound-exact', 0.9],
['what is water and who invented it', 'liquid', 'compound-exact', 0.9],
['capital of france and what is its population', 'paris', 'compound-exact', 0.9],
['who wrote hamlet and what is it about', 'shakespeare', 'compound-exact', 0.9],

// ============================================================
// CATEGORY: compound variant (should be >50% — needs Markov + fuzzy)
// ============================================================
['who is shakespeare and what did shakespeare write', 'shakespeare', 'compound-variant', 0.3],
['what is beer and who invented beer', 'alcoholic', 'compound-variant', 0.3],
['who is einstein and what did einstein discover', 'einstein', 'compound-variant', 0.3],
['what is coffee and who invented coffee', 'brew', 'compound-variant', 0.3],

// ============================================================
// CATEGORY: multi-query (should be >70%)
// ============================================================
['what is water? what is love?', 'liquid', 'multi', 0.6],
['describe beer. what is wine?', 'alcoholic', 'multi', 0.6],
['what is water and what is tea', 'liquid', 'multi', 0.6],
['capital of france and capital of japan', 'paris', 'multi', 0.6],

// ============================================================
// CATEGORY: unknown (should return [none] or [low])
// ============================================================
['what is xyzabcnotathing', '', 'unknown', 0.0],
['who wrote the nonexistent book', '', 'unknown', 0.0],
['capital of atlantis', '', 'unknown', 0.0],
['how do i build a spaceship', '', 'unknown', 0.0],
];

// Run
$results = [];
$byCategory = [];

foreach ($tests as [$q, $expect, $cat, $minScore]) {
$r = $memory->ask($q);
$ans = strtolower($r['answer']);
$exp = strtolower($expect);

// Success criteria
if ($cat === 'unknown') {
// Success if status is none or low
$pass = in_array($r['status'], ['none', 'low'], true);
} else {
// Success if expected substring found AND score >= minScore
$pass = ($exp === '' || strpos($ans, $exp) !== false)
&& $r['score'] >= $minScore;
}

$results[] = [
'query' => $q,
'expect' => $expect,
'answer' => $r['answer'],
'status' => $r['status'],
'score' => $r['score'],
'layer' => $r['layer'] ?? '?',
'pass' => $pass,
'cat' => $cat,
];

if (!isset($byCategory[$cat])) $byCategory[$cat] = ['pass' => 0, 'total' => 0];
$byCategory[$cat]['total']++;
if ($pass) $byCategory[$cat]['pass']++;
}

// Print detailed results
echo str_repeat('=', 90) . "\n";
echo "BENCHMARK RESULTS\n";
echo str_repeat('=', 90) . "\n\n";

foreach ($results as $r) {
$mark = $r['pass'] ? '✅' : '❌';
printf("%s [%-9s] [%-15s] score=%.2f %s\n",
$mark, $r['status'], $r['cat'], $r['score'], substr($r['query'], 0, 55));

if (!$r['pass']) {
printf(" expected: %s\n", $r['expect']);
printf(" got: %s\n", substr($r['answer'], 0, 70));
printf(" layer: %s\n", $r['layer']);
}
}

// Summary by category
echo "\n" . str_repeat('=', 90) . "\n";
echo "SUMMARY BY CATEGORY\n";
echo str_repeat('=', 90) . "\n\n";

foreach ($byCategory as $cat => $stats) {
$pct = 100 * $stats['pass'] / $stats['total'];
printf("%-20s %2d/%2d (%.0f%%)\n", $cat, $stats['pass'], $stats['total'], $pct);
}

// Overall
$totalPass = array_sum(array_column($byCategory, 'pass'));
$totalTests = array_sum(array_column($byCategory, 'total'));
$overall = 100 * $totalPass / $totalTests;

echo "\n" . str_repeat('=', 90) . "\n";
printf("OVERALL: %d/%d = %.1f%%\n", $totalPass, $totalTests, $overall);
echo str_repeat('=', 90) . "\n";

// Exit code reflects accuracy
exit($overall >= 80 ? 0 : 1);
