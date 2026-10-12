<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

$files = [
    'core/Helpers.php',
    'core/LocalEmbedder.php',
    'core/SpellCorrector.php',
    'core/MemoryStore.php',
    'core/SkillManager.php',
    'core/Router.php',
    'skills/FactSkill.php',
    'skills/PromptUnderstandingSkill.php',
    'skills/KnowledgeSkill.php',
    'skills/ResponseUnderstandingSkill.php',
    'config/openrouter.php',
    'index.php',
];

echo "PHP " . PHP_VERSION . "\n\n";
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo $f . ': ' . (is_file($path) ? 'OK' : 'MISSING') . "\n";
}

echo "\n--- load test ---\n";
try {
    require_once __DIR__ . '/core/SpellCorrector.php';
    require_once __DIR__ . '/core/MemoryStore.php';
    require_once __DIR__ . '/core/Router.php';
    echo "classes loaded\n";
    $m = new MemoryStore(__DIR__ . '/memory/memory.json');
    echo "topics=" . count($m->listTopics()) . "\n";
    echo "resolve=" . $m->resolveTopic('vecrot_embedings') . "\n";
    echo "spell=" . SpellCorrector::correct('vecrot embedings', $m->listTopics()) . "\n";
    echo "BOOT OK\n";
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}
