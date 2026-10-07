<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

header('Content-Type: text/html; charset=utf-8');
echo "<h1>AI boot debug</h1><pre>\n";
echo "PHP " . PHP_VERSION . "\n";
echo "__DIR__=" . __DIR__ . "\n";
echo "DOCUMENT_ROOT=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n\n";

function step(string $label, callable $fn): void
{
    echo "STEP: {$label} ... ";
    try {
        $fn();
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAIL\n";
        echo get_class($e) . ': ' . $e->getMessage() . "\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n";
        echo $e->getTraceAsString() . "\n";
        echo "</pre>";
        exit;
    }
}

step('core/Helpers.php', function () {
    require_once __DIR__ . '/core/Helpers.php';
});
step('core/LocalEmbedder.php', function () {
    require_once __DIR__ . '/core/LocalEmbedder.php';
});
step('core/SkillData.php', function () {
    require_once __DIR__ . '/core/SkillData.php';
});
step('core/SpellCorrector.php', function () {
    require_once __DIR__ . '/core/SpellCorrector.php';
});
step('skills/FactSkill.php', function () {
    require_once __DIR__ . '/skills/FactSkill.php';
});
step('FactSkill::embed smoke', function () {
    $v = FactSkill::embed('test vector embeddings');
    if (!is_array($v) || count($v) < 10) {
        throw new RuntimeException('embed returned unexpected size ' . (is_array($v) ? count($v) : -1));
    }
});
step('core/MemoryStore.php', function () {
    require_once __DIR__ . '/core/MemoryStore.php';
});
step('MemoryStore construct', function () {
    $m = new MemoryStore(__DIR__ . '/memory/memory.json');
    $topics = $m->listTopics();
    echo "(topics=" . count($topics) . ") ";
});
step('core/ConversationStore.php', function () {
    require_once __DIR__ . '/core/ConversationStore.php';
});
step('ConversationStore::resolveSessionId', function () {
    $id = ConversationStore::resolveSessionId();
    echo "(id=" . substr($id, 0, 12) . "...) ";
});
step('core/SkillManager.php', function () {
    require_once __DIR__ . '/core/SkillManager.php';
});
step('core/Router.php', function () {
    require_once __DIR__ . '/core/Router.php';
});
step('skills/PromptUnderstandingSkill.php', function () {
    require_once __DIR__ . '/skills/PromptUnderstandingSkill.php';
});
step('skills/KnowledgeSkill.php', function () {
    require_once __DIR__ . '/skills/KnowledgeSkill.php';
});
step('skills/ResponseUnderstandingSkill.php', function () {
    require_once __DIR__ . '/skills/ResponseUnderstandingSkill.php';
});
step('new Router()', function () {
    $r = new Router();
    echo "(class=" . get_class($r) . ") ";
});

// Site includes
$candidates = [
    dirname(__DIR__, 2),
    dirname(__DIR__),
    $_SERVER['DOCUMENT_ROOT'] ?? '',
];
$siteRoot = '';
foreach ($candidates as $c) {
    $c = rtrim((string)$c, '/');
    if ($c !== '' && is_readable($c . '/includes/reusable.php')) {
        $siteRoot = $c;
        break;
    }
}
echo "\nsiteRoot=" . ($siteRoot !== '' ? $siteRoot : '(none)') . "\n";

if ($siteRoot !== '') {
    step('includes/reusable.php', function () use ($siteRoot) {
        require_once $siteRoot . '/includes/reusable.php';
    });
}

echo "\nALL STEPS OK — problem may be in index.php layout output or a die()/exit in site helpers.\n";
echo "</pre>";
