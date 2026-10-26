<?php
// /diy/byte-embed/model.php
declare(strict_types=1);

const MODEL_PATH   = __DIR__ . '/model.json';
const VOCAB_SIZE   = 256;
const EMB_DIM      = 32;
const SEQ_IN       = 16;
const SEQ_OUT      = 16;
const HIDDEN_DIM   = 32;
const LR           = 0.05;

// ---------------------------------------------------------------- init

function model_init(): array {
    mt_srand(42);

    // Byte embedding table: 256 x 32
    $emb = [];
    for ($b = 0; $b < VOCAB_SIZE; $b++) {
        $row = [];
        for ($k = 0; $k < EMB_DIM; $k++) {
            $row[] = (mt_rand() / mt_getrandmax() - 0.5) * 0.2;
        }
        $emb[] = $row;
    }

    // Hidden layer: (SEQ_IN * EMB_DIM) -> HIDDEN_DIM
    $inDim  = SEQ_IN * EMB_DIM; // 512
    $w1 = [];
    $b1 = [];
    for ($h = 0; $h < HIDDEN_DIM; $h++) {
        $row = [];
        for ($d = 0; $d < $inDim; $d++) {
            $row[] = (mt_rand() / mt_getrandmax() - 0.5) * (2.0 / sqrt($inDim));
        }
        $w1[] = $row;
        $b1[] = 0.0;
    }

    // Output layer: HIDDEN_DIM -> SEQ_OUT
    $w2 = [];
    $b2 = [];
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $row = [];
        for ($h = 0; $h < HIDDEN_DIM; $h++) {
            $row[] = (mt_rand() / mt_getrandmax() - 0.5) * (2.0 / sqrt(HIDDEN_DIM));
        }
        $w2[] = $row;
        $b2[] = 0.0;
    }

    return [
        'version'     => 1,
        'created_at'  => date('c'),
        'epochs'      => 0,
        'emb'         => $emb,
        'w1'          => $w1,
        'b1'          => $b1,
        'w2'          => $w2,
        'b2'          => $b2,
    ];
}

// ---------------------------------------------------------------- io

function model_load(): array {
    if (!is_file(MODEL_PATH)) {
        $m = model_init();
        model_save($m);
        return $m;
    }
    $raw = json_decode((string)file_get_contents(MODEL_PATH), true);
    if (!is_array($raw) || !isset($raw['emb'], $raw['w1'], $raw['w2'])) {
        $m = model_init();
        model_save($m);
        return $m;
    }
    return $raw;
}

function model_save(array $m): void {
    $tmp = MODEL_PATH . '.tmp';
    file_put_contents($tmp, json_encode($m, JSON_UNESCAPED_SLASHES));
    rename($tmp, MODEL_PATH);
}

// ---------------------------------------------------------------- forward

function model_encode_input(array $input, array $emb): array {
    // Returns flat 512-dim vector (16 bytes x 32 dims)
    $vec = [];
    for ($i = 0; $i < SEQ_IN; $i++) {
        $b = ((int)$input[$i]) & 0xFF;
        for ($k = 0; $k < EMB_DIM; $k++) {
            $vec[] = $emb[$b][$k];
        }
    }
    return $vec;
}

function model_forward(array $m, array $input): array {
    // Returns [
    //   'x'      => encoded input (512),
    //   'h_pre'  => hidden pre-activation (32),
    //   'h'      => hidden activation (32),
    //   'y_pre'  => output pre-activation (16),
    // ]
    $x = model_encode_input($input, $m['emb']);

    $hPre = [];
    $h    = [];
    for ($j = 0; $j < HIDDEN_DIM; $j++) {
        $sum = $m['b1'][$j];
        $wj  = $m['w1'][$j];
        for ($d = 0; $d < count($x); $d++) {
            $sum += $wj[$d] * $x[$d];
        }
        $hPre[] = $sum;
        $h[]    = $sum > 0 ? $sum : 0.0;
    }

    $yPre = [];
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $sum = $m['b2'][$o];
        $wo  = $m['w2'][$o];
        for ($j = 0; $j < HIDDEN_DIM; $j++) {
            $sum += $wo[$j] * $h[$j];
        }
        $yPre[] = $sum;
    }

    return ['x' => $x, 'h_pre' => $hPre, 'h' => $h, 'y_pre' => $yPre];
}

function model_predict(array $m, array $input): array {
    $f   = model_forward($m, $input);
    $out = [];
    foreach ($f['y_pre'] as $v) {
        $r = (int)round($v);
        if ($r < -128) $r = -128;
        if ($r > 127)  $r = 127;
        $out[] = $r;
    }
    return $out;
}

// ---------------------------------------------------------------- backward + train

function model_train_pair(array $m, array $input, array $target, float $lr): array {
    $f = model_forward($m, $input);

    // Output delta: dL/dy_pre = 2 * (y_pred - target) / SEQ_OUT
    $dY = [];
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $dY[] = 2.0 * ($f['y_pre'][$o] - (float)$target[$o]) / SEQ_OUT;
    }

    // Gradients for w2, b2
    $gW2 = [];
    $gB2 = [];
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $row = [];
        for ($j = 0; $j < HIDDEN_DIM; $j++) {
            $row[] = $dY[$o] * $f['h'][$j];
        }
        $gW2[] = $row;
        $gB2[] = $dY[$o];
    }

    // Hidden delta: dL/dh = sum_o dY[o] * w2[o][j], then ReLU mask
    $dH = [];
    for ($j = 0; $j < HIDDEN_DIM; $j++) {
        $sum = 0.0;
        for ($o = 0; $o < SEQ_OUT; $o++) {
            $sum += $dY[$o] * $m['w2'][$o][$j];
        }
        $dH[] = $f['h_pre'][$j] > 0 ? $sum : 0.0;
    }

    // Gradients for w1, b1
    $gW1 = [];
    $gB1 = [];
    $xLen = count($f['x']);
    for ($j = 0; $j < HIDDEN_DIM; $j++) {
        $row = [];
        for ($d = 0; $d < $xLen; $d++) {
            $row[] = $dH[$j] * $f['x'][$d];
        }
        $gW1[] = $row;
        $gB1[] = $dH[$j];
    }

    // Gradient for embedding table
    // For each input position i and each dim k:
    //   dL/d(emb[b][k]) += dH_dot_w1_at(i*EMB_DIM + k)
    $gEmb = [];
    for ($i = 0; $i < SEQ_IN; $i++) {
        $b = ((int)$input[$i]) & 0xFF;
        if (!isset($gEmb[$b])) {
            $gEmb[$b] = array_fill(0, EMB_DIM, 0.0);
        }
        for ($k = 0; $k < EMB_DIM; $k++) {
            $d = $i * EMB_DIM + $k;
            $sum = 0.0;
            for ($j = 0; $j < HIDDEN_DIM; $j++) {
                $sum += $dH[$j] * $m['w1'][$j][$d];
            }
            $gEmb[$b][$k] += $sum;
        }
    }

    // SGD update
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $m['b2'][$o] -= $lr * $gB2[$o];
        for ($j = 0; $j < HIDDEN_DIM; $j++) {
            $m['w2'][$o][$j] -= $lr * $gW2[$o][$j];
        }
    }
    for ($j = 0; $j < HIDDEN_DIM; $j++) {
        $m['b1'][$j] -= $lr * $gB1[$j];
        for ($d = 0; $d < $xLen; $d++) {
            $m['w1'][$j][$d] -= $lr * $gW1[$j][$d];
        }
    }
    foreach ($gEmb as $b => $row) {
        for ($k = 0; $k < EMB_DIM; $k++) {
            $m['emb'][$b][$k] -= $lr * $row[$k];
        }
    }

    // Loss
    $loss = 0.0;
    for ($o = 0; $o < SEQ_OUT; $o++) {
        $diff = $f['y_pre'][$o] - (float)$target[$o];
        $loss += $diff * $diff;
    }
    $loss /= SEQ_OUT;

    return [$m, $loss];
}

function model_train_from_log(string $logPath, int $epochs = 10, ?float $lr = null): array {
    if (!is_file($logPath)) {
        return ['pairs' => 0, 'epochs' => 0, 'loss' => null];
    }
    $lr = $lr ?? LR;

    $pairs = [];
    foreach (file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (is_array($row) && isset($row['input'], $row['output'])
            && count($row['input']) === SEQ_IN && count($row['output']) === SEQ_OUT) {
            $pairs[] = $row;
        }
    }

    if (empty($pairs)) {
        return ['pairs' => 0, 'epochs' => 0, 'loss' => null];
    }

    $m = model_load();
    $totalLoss = 0.0;
    $steps = 0;

    for ($e = 0; $e < $epochs; $e++) {
        shuffle($pairs);
        foreach ($pairs as $p) {
            [$m, $loss] = model_train_pair($m, $p['input'], $p['output'], $lr);
            $totalLoss += $loss;
            $steps++;
        }
    }

    $m['epochs']    = ($m['epochs'] ?? 0) + $epochs;
    $m['trained_at'] = date('c');
    model_save($m);

    return [
        'pairs'  => count($pairs),
        'epochs' => $epochs,
        'loss'   => $steps > 0 ? round($totalLoss / $steps, 4) : null,
        'steps'  => $steps,
    ];
}
