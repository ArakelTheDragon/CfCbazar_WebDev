<?php
// compose_web.php — Browser UI for sharded composition memory
declare(strict_types=1);
require_once __DIR__ . '/compose.php';

const MEMORY_DIR = __DIR__ . '/memory';

$memory = new CompositionMemory(MEMORY_DIR);
$s = $memory->stats();
$query = trim($_GET['q'] ?? '');
$response = null;

if ($query !== '' && ($s['facts'] > 0 || $s['learned'] > 0)) {
    $response = $memory->ask($query, 3);
}

// JSON API
if (($_GET['action'] ?? '') === 'json') {
    header('Content-Type: application/json');
    if ($s['facts'] === 0 && $s['learned'] === 0) {
        echo json_encode(['error' => 'no_memory']); exit;
    }
    if ($query === '') { echo json_encode(['error' => 'no_query']); exit; }
    echo json_encode([
        'query'  => $query,
        'status' => $response['status'],
        'score'  => round($response['score'], 4),
        'answer' => $response['answer'],
        'parts'  => $response['parts'] ?? [],
        'layer'  => $response['layer']  ?? null,
        'layers' => $response['layers'] ?? [],
        'top'    => array_map(fn($t) => [
            'text'   => $t['text'],
            'score'  => round($t['score'], 4),
            'layers' => $t['layers'] ?? [],
        ], $response['top'] ?? []),
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Compose — Memory QA</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; color: #222; }
    h1 { border-bottom: 1px solid #eee; padding-bottom: .3rem; }
    form { display: flex; gap: .5rem; margin: 1rem 0; }
    input[type=text] { flex: 1; padding: .6rem; font-size: 1rem; border: 1px solid #ccc; border-radius: 6px; }
    button { padding: .6rem 1.2rem; border: 1px solid #333; background: #333; color: white; border-radius: 6px; cursor: pointer; }
    .answer { padding: 1rem 1.2rem; border-radius: 6px; margin: 1rem 0; font-size: 1.1rem; border-left: 4px solid #1976d2; background: #f0f7ff; }
    .answer.exact    { border-left-color: #43a047; background: #e8f5e9; }
    .answer.composed { border-left-color: #5e35b1; background: #ede7f6; }
    .answer.ok       { border-left-color: #1976d2; background: #f0f7ff; }
    .answer.low      { border-left-color: #ffa000; background: #fff8e1; }
    .answer.none     { border-left-color: #e53935; background: #ffebee; }
    .meta { font-size: .9rem; color: #555; margin-top: .4rem; }
    .layers { display: inline-flex; gap: .35rem; flex-wrap: wrap; margin-top: .35rem; }
    .layer-tag { display: inline-block; padding: .1rem .5rem; border-radius: 4px; font-size: .75rem; font-family: monospace; background: #e3f2fd; color: #0d47a1; }
    .layer-tag.intent { background: #f3e5f5; color: #4a148c; }
    .layer-tag.word   { background: #e8f5e9; color: #1b5e20; }
    .layer-tag.char   { background: #fff3e0; color: #e65100; }
    .layer-tag.exact  { background: #c8e6c9; color: #1b5e20; }
    .parts { background: #fafafa; padding: .8rem 1rem; border-radius: 6px; margin: 1rem 0; }
    .parts ol { margin: .3rem 0; padding-left: 1.4rem; }
    .top { background: #fafafa; padding: .8rem 1rem; border-radius: 6px; margin: 1rem 0; }
    .top table { width: 100%; border-collapse: collapse; }
    .top td { padding: .35rem .5rem; border-bottom: 1px solid #eee; font-size: .9rem; }
    .top tr:last-child td { border-bottom: none; }
    .small { font-size: .85rem; color: #777; }
    .badge { display: inline-block; padding: .1rem .5rem; border-radius: 4px; font-size: .8rem; font-weight: 600; text-transform: uppercase; }
    .badge.exact    { background: #c8e6c9; color: #1b5e20; }
    .badge.composed { background: #d1c4e9; color: #311b92; }
    .badge.ok       { background: #bbdefb; color: #0d47a1; }
    .badge.low      { background: #ffe0b2; color: #e65100; }
    .badge.none     { background: #ffcdd2; color: #b71c1c; }
</style>
</head>
<body>

<h1>Compose — Memory QA</h1>

<?php if ($s['facts'] === 0 && $s['learned'] === 0): ?>
    <p>No memory yet. Run in terminal:</p>
    <pre>php compose_train.php</pre>
<?php else: ?>
    <p class="small">
        Facts: <strong><?= $s['facts'] ?></strong> ·
        Learned pairs: <strong><?= $s['learned'] ?></strong> ·
        Fact shards: <strong><?= $s['factsShards'] ?></strong> ·
        Learned shards: <strong><?= $s['learnedShards'] ?></strong>
    </p>

    <form method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($query) ?>"
               placeholder="Ask something..." autofocus>
        <button type="submit">Ask</button>
    </form>

    <?php if ($response): ?>
        <?php $st = $response['status']; ?>
        <div class="answer <?= htmlspecialchars($st) ?>">
            <span class="badge <?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></span>
            &nbsp; <strong><?= htmlspecialchars($response['answer']) ?></strong>
            <div class="meta">
                Score <?= number_format($response['score'], 3) ?>
                <?php if (!empty($response['layer'])): ?>
                    · via <?= htmlspecialchars($response['layer']) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($response['layers'])): ?>
                <div class="layers">
                    <?php foreach ($response['layers'] as $l): ?>
                        <?php
                            $cls = 'layer-tag';
                            if (strpos($l, 'intent') === 0) $cls .= ' intent';
                            elseif (strpos($l, 'word') === 0) $cls .= ' word';
                            elseif (strpos($l, 'char') === 0) $cls .= ' char';
                            elseif (strpos($l, 'exact') === 0) $cls .= ' exact';
                        ?>
                        <span class="<?= $cls ?>"><?= htmlspecialchars($l) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($response['parts']) && count($response['parts']) > 1): ?>
            <div class="parts">
                <strong>Composed from <?= count($response['parts']) ?> parts:</strong>
                <ol>
                    <?php foreach ($response['parts'] as $p): ?>
                        <li><?= htmlspecialchars($p) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if (!empty($response['top'])): ?>
            <div class="top">
                <strong>Top facts:</strong>
                <table>
                    <?php foreach ($response['top'] as $t): ?>
                    <tr>
                        <td style="width:70px;color:#1976d2;font-weight:600"><?= number_format($t['score'], 3) ?></td>
                        <td>
                            <?= htmlspecialchars($t['text']) ?>
                            <?php if (!empty($t['layers'])): ?>
                                <div class="layers" style="margin-top:.2rem">
                                    <?php foreach ($t['layers'] as $l): ?>
                                        <?php
                                            $cls = 'layer-tag';
                                            if (strpos($l, 'intent') === 0) $cls .= ' intent';
                                            elseif (strpos($l, 'word') === 0) $cls .= ' word';
                                            elseif (strpos($l, 'char') === 0) $cls .= ' char';
                                            elseif (strpos($l, 'exact') === 0) $cls .= ' exact';
                                        ?>
                                        <span class="<?= $cls ?>"><?= htmlspecialchars($l) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="small">
        Try:
        <a href="?q=what+is+water">what is water</a> ·
        <a href="?q=describe+the+sun">describe the sun</a> ·
        <a href="?q=tell+me+about+the+sun">tell me about the sun</a> ·
        <a href="?q=explain+beer">explain beer</a> ·
        <a href="?q=who+wrote+hamlet">who wrote hamlet</a> ·
        <a href="?q=how+do+i+make+tea">how do i make tea</a>
    </p>

    <p class="small">
        Multi-sentence:
        <a href="?q=what+is+water%3F+what+is+love%3F">what is water? what is love?</a> ·
        <a href="?q=describe+beer.+what+is+wine%3F">describe beer. what is wine?</a> ·
        <a href="?q=capital+of+france+and+japan">capital of france and japan</a>
    </p>

    <p class="small">
        JSON API:
        <a href="?action=json&q=describe+the+sun"><code>?action=json&q=describe+the+sun</code></a>
    </p>
<?php endif; ?>

</body>
</html>
