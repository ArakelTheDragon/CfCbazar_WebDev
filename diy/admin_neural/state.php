<?php
// /diy/byte-embed/state.php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require __DIR__ . '/model.php';

$statePath = __DIR__ . '/state.json';

$action = $_POST['action'] ?? $_GET['action'] ?? 'tick';

$model  = model_load();
$state  = file_exists($statePath)
    ? (json_decode(file_get_contents($statePath), true) ?: null)
    : null;

// Default state
if (!is_array($state)) {
    $state = [
        'input'         => array_fill(0, 16, 0),
        'proposal'      => array_fill(0, 16, 0),
        'user_output'   => array_fill(0, 16, 0),
        'created_at'    => time(),
        'corrected'     => false,
        'version'       => 1,
    ];
}

if ($action === 'tick' && (time() - ($state['created_at'] ?? 0)) >= 30) {
    // New input round
    $input = [];
    for ($i = 0; $i < 16; $i++) {
        $input[] = random_int(-128, 127);
    }
    $proposal = model_predict($model, $input);

    $state = [
        'input'         => $input,
        'proposal'      => $proposal,
        'user_output'   => $proposal,
        'created_at'    => time(),
        'corrected'     => false,
        'version'       => 1,
    ];

    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

if ($action === 'force_new') {
    $input = [];
    for ($i = 0; $i < 16; $i++) {
        $input[] = random_int(-128, 127);
    }
    $proposal = model_predict($model, $input);

    $state = [
        'input'         => $input,
        'proposal'      => $proposal,
        'user_output'   => $proposal,
        'created_at'    => time(),
        'corrected'     => false,
        'version'       => 1,
    ];

    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

if ($action === 'correct') {
    $userOutput = [];
    for ($i = 0; $i < 16; $i++) {
        $raw = $_POST["out$i"] ?? ($state['user_output'][$i] ?? 0);
        $v = (int)$raw;
        if ($v < -128) $v = -128;
        if ($v > 127)  $v = 127;
        $userOutput[] = $v;
    }

    $state['user_output'] = $userOutput;
    $state['corrected']   = true;

    // Append to training data
    $dataPath = __DIR__ . '/training_data.jsonl';
    $row = json_encode([
        'input'  => $state['input'],
        'output' => $userOutput,
        'at'     => date('c'),
    ]);
    file_put_contents($dataPath, $row . "\n", FILE_APPEND | LOCK_EX);

    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Train synchronously (small model, fast)
    $trained = model_train_from_log(__DIR__ . '/training_data.jsonl', 10);

    echo json_encode([
        'ok'          => true,
        'state'       => $state,
        'train_stats' => $trained,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['ok' => true, 'state' => $state], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
