<?php
// /diy/admin_neural/api.php
declare(strict_types=1);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/model.php';

// ---------------------------------------------------------------- auth
// No authentication. The API is open.
// Protect it via nginx IP allowlist or leave it behind the VPS firewall.
// ----------------------------------------------------------------

// ---------------------------------------------------------------- parse body

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $ctype = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($ctype === 'application/json') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw === false ? '' : $raw, true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body.']);
            exit;
        }
    } else {
        $body = $_POST;
    }
} else {
    $body = [];
}

// ---------------------------------------------------------------- helpers

function api_validate_bytes(array $arr, int $len): bool {
    if (count($arr) !== $len) return false;
    foreach ($arr as $v) {
        if (!is_int($v) && !is_float($v)) return false;
        if ($v < -128 || $v > 127) return false;
    }
    return true;
}

function api_append_training(array $pairs): int {
    $path  = __DIR__ . '/training_data.jsonl';
    $lines = '';
    $count = 0;
    foreach ($pairs as $p) {
        $lines .= json_encode([
            'input'  => array_map('intval', $p['input']),
            'output' => array_map('intval', $p['output']),
            'at'     => $p['at'] ?? date('c'),
            'source' => $p['source'] ?? 'api',
        ], JSON_UNESCAPED_SLASHES) . "\n";
        $count++;
    }
    file_put_contents($path, $lines, FILE_APPEND | LOCK_EX);
    return $count;
}

// ---------------------------------------------------------------- routes

// GET ?action=ping — health check
if ($action === 'ping') {
    echo json_encode([
        'ok'      => true,
        'model'   => is_file(MODEL_PATH),
        'trained' => (function () {
            if (!is_file(MODEL_PATH)) return false;
            $m = json_decode((string)file_get_contents(MODEL_PATH), true);
            return is_array($m) && !empty($m['trained_at']);
        })(),
        'pairs'   => is_file(__DIR__ . '/training_data.jsonl')
            ? count(file(__DIR__ . '/training_data.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            : 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST ?action=predict — run the model on given input bytes
if ($action === 'predict' && $method === 'POST') {
    $input = $body['input'] ?? null;
    if (!is_array($input) || !api_validate_bytes($input, SEQ_IN)) {
        http_response_code(400);
        echo json_encode(['error' => 'input must be 16 integers in -128..127']);
        exit;
    }
    $m    = model_load();
    $pred = model_predict($m, array_map('intval', $input));
    echo json_encode([
        'ok'        => true,
        'input'     => array_map('intval', $input),
        'predicted' => $pred,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST ?action=learn — add one or more (input, output) pairs and train
if ($action === 'learn' && $method === 'POST') {
    $pairs = $body['pairs'] ?? null;

    if ($pairs === null && isset($body['input'], $body['output'])) {
        $pairs = [['input' => $body['input'], 'output' => $body['output']]];
    }

    if (!is_array($pairs) || empty($pairs)) {
        http_response_code(400);
        echo json_encode(['error' => 'Provide "pairs": [{input, output}, ...] or single input/output']);
        exit;
    }

    $valid = [];
    foreach ($pairs as $p) {
        if (!is_array($p) || !isset($p['input'], $p['output'])) continue;
        if (!api_validate_bytes($p['input'],  SEQ_IN))  continue;
        if (!api_validate_bytes($p['output'], SEQ_OUT)) continue;
        $valid[] = $p;
    }

    if (empty($valid)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid pairs. Each must have input:16 and output:16 in -128..127.']);
        exit;
    }

    $added = api_append_training($valid);

    $epochs = isset($body['epochs']) ? max(1, min(100, (int)$body['epochs'])) : 10;
    $lr     = isset($body['lr'])     ? max(0.001, min(0.5, (float)$body['lr'])) : null;

    $stats = model_train_from_log(__DIR__ . '/training_data.jsonl', $epochs, $lr);

    echo json_encode([
        'ok'    => true,
        'added' => $added,
        'train' => $stats,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST ?action=retrain — retrain on the entire existing log (no new pairs)
if ($action === 'retrain' && $method === 'POST') {
    $epochs = isset($body['epochs']) ? max(1, min(100, (int)$body['epochs'])) : 20;
    $lr     = isset($body['lr'])     ? max(0.001, min(0.5, (float)$body['lr'])) : null;

    $stats = model_train_from_log(__DIR__ . '/training_data.jsonl', $epochs, $lr);

    echo json_encode([
        'ok'    => true,
        'train' => $stats,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST ?action=reset — wipe the model (and optionally the training data)
if ($action === 'reset' && $method === 'POST') {
    @unlink(MODEL_PATH);
    $alsoData = !empty($body['wipe_data']);
    if ($alsoData) {
        @unlink(__DIR__ . '/training_data.jsonl');
    }
    echo json_encode([
        'ok'         => true,
        'model_gone' => !is_file(MODEL_PATH),
        'data_gone'  => $alsoData ? !is_file(__DIR__ . '/training_data.jsonl') : false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// GET ?action=stats — current state of the system
if ($action === 'stats') {
    $dataCount = is_file(__DIR__ . '/training_data.jsonl')
        ? count(file(__DIR__ . '/training_data.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        : 0;

    $model = is_file(MODEL_PATH)
        ? json_decode((string)file_get_contents(MODEL_PATH), true)
        : null;

    echo json_encode([
        'ok'            => true,
        'training_rows' => $dataCount,
        'epochs_total'  => is_array($model) ? ($model['epochs'] ?? 0) : 0,
        'trained_at'    => is_array($model) ? ($model['trained_at'] ?? null) : null,
        'model_exists'  => is_file(MODEL_PATH),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Fallback
http_response_code(404);
echo json_encode(['error' => 'Unknown action. Use: ping, predict, learn, retrain, reset, stats']);
