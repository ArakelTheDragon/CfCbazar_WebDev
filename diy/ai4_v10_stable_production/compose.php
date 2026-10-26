<?php
// compose.php — Sharded memory with inverted index + streaming ingest + ensemble scoring
declare(strict_types=1);

require_once __DIR__ . '/compose_helpers.php';

const FACT_SHARD_SIZE  = 1000;
const LEARNED_SHARDS   = 64;
const INDEX_PREFIX_LEN = 4;

class CompositionMemory {
    private string $dir;
    private int $factsShards = 0;
    private int $factsTotal = 0;
    private int $learnedTotal = 0;
    private int $factShardSize = FACT_SHARD_SIZE;
    private int $learnedShards = LEARNED_SHARDS;

    private array $factIndex = [];
    private array $learnedCache = [];
    private array $intentTable = [];
    private array $intentPhrases = [];

    // Ensemble weights
    private const W_INTENT  = 0.50;
    private const W_WORD    = 0.15;
    private const W_CHAR    = 0.05;
    private const W_AGREE_BONUS = 1.2;

    public function __construct(string $dir) {
        $this->dir = $dir;
        foreach (['', '/facts', '/learned'] as $sub) {
            if (!is_dir($dir . $sub)) mkdir($dir . $sub, 0755, true);
        }
        $this->loadIntentTable(__DIR__ . '/small_table.json');
        $this->loadIndex();
    }

    // ---------- Index ----------
    private function loadIndex(): void {
        $path = $this->dir . '/index.json';
        if (!file_exists($path)) return;
        $idx = json_decode(file_get_contents($path), true);
        if (!is_array($idx)) return;
        $this->factsShards   = (int)($idx['factsShards'] ?? 0);
        $this->factsTotal    = (int)($idx['factsTotal'] ?? 0);
        $this->learnedTotal  = (int)($idx['learnedTotal'] ?? 0);
        $this->factShardSize = (int)($idx['factShardSize'] ?? FACT_SHARD_SIZE);
        $this->learnedShards = (int)($idx['learnedShards'] ?? LEARNED_SHARDS);
        $this->factIndex = [];
        foreach (($idx['factIndex'] ?? []) as $k => $shards) {
            $this->factIndex[(string)$k] = $shards;
        }
    }

    private function saveIndex(): void {
        $idx = [
            'version'       => 4,
            'factsShards'   => $this->factsShards,
            'factsTotal'    => $this->factsTotal,
            'learnedTotal'  => $this->learnedTotal,
            'factShardSize' => $this->factShardSize,
            'learnedShards' => $this->learnedShards,
            'factIndex'     => $this->factIndex,
        ];
        file_put_contents($this->dir . '/index.json', json_encode($idx));
    }

    // ---------- Intent table ----------
    private function loadIntentTable(string $path): void {
        if (!file_exists($path)) return;
        $raw = json_decode(file_get_contents($path), true);
        if (!is_array($raw)) return;
        $this->intentTable = $raw;
        $this->intentPhrases = array_keys($raw);
        usort($this->intentPhrases, fn($a, $b) => strlen($b) <=> strlen($a));
    }

    // ---------- Encoding ----------
    public function encode(string $s): string {
        $s = strtolower(trim(preg_replace('/\s+/', ' ', $s)));
        if ($s === '') return '0';
        $n = '0';
        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $n = bcadd(bcmul($n, '256'), (string)ord($s[$i]));
        }
        return $n;
    }

    public function decode(string $n): string {
        if ($n === '0' || $n === '') return '';
        $bytes = [];
        while (bccomp($n, '0') > 0) {
            $bytes[] = (int)bcmod($n, '256');
            $n = bcdiv($n, '256', 0);
        }
        return implode('', array_map('chr', array_reverse($bytes)));
    }

    // ---------- Prefix & routing ----------
    private function prefixKey(string $text): string {
        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($text));
        if ($clean === '') return 'k';
        return 'k' . substr($clean, 0, INDEX_PREFIX_LEN);
    }

    private function learnedShardOf(string $nq): int {
        $prefix = substr($nq, 0, 9);
        if ($prefix === '') return 0;
        return (int)$prefix % $this->learnedShards;
    }

    // ---------- Streaming ingest ----------
    public function ingestFile(string $file, ?callable $progress = null): void {
        if (!file_exists($file)) throw new RuntimeException("File not found: $file");

        $fh = fopen($file, 'r');
        if (!$fh) throw new RuntimeException("Cannot open $file");

        $pendingFacts = [];
        $pendingLearned = [];
        $lineNo = 0;
        $factCount = 0;
        $learnedCount = 0;

        while (($line = fgets($fh)) !== false) {
            $lineNo++;
            $line = trim($line);
            if ($line === '') continue;

            if (strpos($line, '||') !== false) {
                [$in, $ans] = array_map('trim', explode('||', $line, 2));
                if ($in === '' || $ans === '') continue;

                $nq = $this->encode($in);
                $na = $this->encode($ans);
                $shard = $this->learnedShardOf($nq);
                if (!isset($pendingLearned[$shard])) $pendingLearned[$shard] = [];
                $pendingLearned[$shard][$nq] = $na;
                $learnedCount++;

                $pendingFacts[] = ['n' => $na, 't' => strtolower($ans)];
                $factCount++;
                if (count($pendingFacts) >= $this->factShardSize) {
                    $this->flushFactsShard($pendingFacts);
                    $pendingFacts = [];
                }
            } else {
                $n = $this->encode($line);
                $pendingFacts[] = ['n' => $n, 't' => strtolower($line)];
                $factCount++;
                if (count($pendingFacts) >= $this->factShardSize) {
                    $this->flushFactsShard($pendingFacts);
                    $pendingFacts = [];
                }
            }

            foreach ($pendingLearned as $shard => $entries) {
                if (count($entries) >= 200) {
                    $this->flushLearnedShard($shard, $entries);
                    unset($pendingLearned[$shard]);
                }
            }

            if ($progress && $lineNo % 5000 === 0) {
                $progress($lineNo, $factCount, $learnedCount);
            }
        }

        fclose($fh);

        if (!empty($pendingFacts)) $this->flushFactsShard($pendingFacts);
        foreach ($pendingLearned as $shard => $entries) {
            $this->flushLearnedShard($shard, $entries);
        }

        $this->saveIndex();
    }

    private function flushFactsShard(array $facts): void {
        if (empty($facts)) return;
        $shardIdx = $this->factsShards;
        $path = $this->dir . '/facts/' . sprintf('%05d', $shardIdx) . '.json';
        file_put_contents($path, json_encode($facts));

        foreach ($facts as $f) {
            $key = $this->prefixKey($f['t']);
            if (!isset($this->factIndex[$key])) $this->factIndex[$key] = [];
            if (!in_array($shardIdx, $this->factIndex[$key], true)) {
                $this->factIndex[$key][] = $shardIdx;
            }
        }

        $this->factsShards++;
        $this->factsTotal += count($facts);
    }

    private function flushLearnedShard(int $shard, array $entries): void {
        if (empty($entries)) return;
        $path = $this->dir . '/learned/' . sprintf('%02d', $shard) . '.json';
        $existing = [];
        if (file_exists($path)) {
            $existing = json_decode(file_get_contents($path), true) ?? [];
        }
        $merged = array_merge($existing, $entries);
        file_put_contents($path, json_encode($merged));
        $this->learnedTotal += count($entries);
        unset($this->learnedCache[$shard]);
    }

    // ---------- Direct add ----------
    public function addFact(string $text): void {
        $text = strtolower(trim($text));
        if ($text === '') return;
        $n = $this->encode($text);
        $this->ingestLine(['n' => $n, 't' => $text]);
    }

    public function learn(string $input, string $answer): void {
        $input  = strtolower(trim($input));
        $answer = trim($answer);
        if ($input === '' || $answer === '') return;

        $nq = $this->encode($input);
        $na = $this->encode($answer);
        $shard = $this->learnedShardOf($nq);

        $path = $this->dir . '/learned/' . sprintf('%02d', $shard) . '.json';
        $entries = file_exists($path) ? (json_decode(file_get_contents($path), true) ?? []) : [];
        $entries[$nq] = $na;
        file_put_contents($path, json_encode($entries));
        unset($this->learnedCache[$shard]);
        $this->learnedTotal++;
    }

    private function ingestLine(array $fact): void {
        $shardIdx = $this->factsShards;
        $path = $this->dir . '/facts/' . sprintf('%05d', $shardIdx) . '.json';
        if (!file_exists($path)) {
            file_put_contents($path, json_encode([$fact]));
            $key = $this->prefixKey($fact['t']);
            if (!isset($this->factIndex[$key])) $this->factIndex[$key] = [];
            $this->factIndex[$key][] = $shardIdx;
            $this->factsShards++;
        } else {
            $existing = json_decode(file_get_contents($path), true) ?? [];
            $existing[] = $fact;
            file_put_contents($path, json_encode($existing));
        }
        $this->factsTotal++;
    }

    public function saveNow(): void {
        $this->saveIndex();
    }

    // ---------- Learned lookup ----------
    private function loadLearnedShard(int $shard): array {
        if (isset($this->learnedCache[$shard])) return $this->learnedCache[$shard];
        $path = $this->dir . '/learned/' . sprintf('%02d', $shard) . '.json';
        if (!file_exists($path)) {
            $this->learnedCache[$shard] = [];
            return [];
        }
        $data = json_decode(file_get_contents($path), true) ?? [];
        $this->learnedCache[$shard] = $data;
        return $data;
    }

    private function lookupLearned(string $nq): ?string {
        $shard = $this->learnedShardOf($nq);
        $entries = $this->loadLearnedShard($shard);
        return $entries[$nq] ?? null;
    }

    private function hasLearned(string $input): bool {
        return $this->lookupLearned($this->encode($input)) !== null;
    }

    private function isSelfReferential(string $input, string $answer): bool {
        return strtolower(trim($input)) === strtolower(trim($answer));
    }

    // ============================================================
    // TRANSITIONS (Markov) — learn intent_prev → intent_next
    // ============================================================
    public function extractTransitions(): void {
        $transitions = [];

        // Scan all learned inputs
        for ($i = 0; $i < $this->learnedShards; $i++) {
            $path = $this->dir . '/learned/' . sprintf('%02d', $i) . '.json';
            if (!file_exists($path)) continue;
            $entries = json_decode(file_get_contents($path), true);
            if (!is_array($entries)) continue;

            foreach (array_keys($entries) as $nq) {
                $input = $this->decode((string)$nq);
                if ($input === '') continue;

                $regions = ComposeHelpers::splitByIntentRegions(
                    $input,
                    $this->intentPhrases,
                    $this->intentTable
                );

                for ($k = 0; $k < count($regions) - 1; $k++) {
                    $from = $regions[$k]['intent'] ?? 'unknown';
                    $to = $regions[$k + 1]['intent'] ?? 'unknown';
                    if ($from === 'unknown' || $to === 'unknown') continue;

                    if (!isset($transitions[$from])) $transitions[$from] = [];
                    $transitions[$from][$to] = ($transitions[$from][$to] ?? 0) + 1;
                }
            }
        }

        // Normalize rows to probabilities
        foreach ($transitions as $from => $row) {
            $total = array_sum($row);
            foreach ($row as $to => $count) {
                $transitions[$from][$to] = $count / $total;
            }
            arsort($transitions[$from]);
        }

        file_put_contents(
            $this->dir . '/transitions.json',
            json_encode($transitions, JSON_PRETTY_PRINT)
        );
    }

    private function predictNextIntent(string $prevIntent): array {
        $path = $this->dir . '/transitions.json';
        if (!file_exists($path)) return [];
        $transitions = json_decode(file_get_contents($path), true);
        if (!is_array($transitions) || !isset($transitions[$prevIntent])) return [];
        return array_keys($transitions[$prevIntent]);
    }

    private function nextIntentProbability(string $prevIntent, string $nextIntent): float {
        $path = $this->dir . '/transitions.json';
        if (!file_exists($path)) return 0.0;
        $transitions = json_decode(file_get_contents($path), true);
        return (float)($transitions[$prevIntent][$nextIntent] ?? 0.0);
    }

    // ============================================================
    // SUBJECT EXTRACTION — for pronoun resolution
    // ============================================================
    private function extractSubject(string $sentence): ?string {
        // Take the first content word (non-stopword) from the answer
        $tokens = ComposeHelpers::tokens($sentence);
        if (empty($tokens)) return null;
        return $tokens[0];
    }

    // ============================================================
    // ASK — layered flow with ensemble scoring at the end
    // ============================================================
    public function ask(string $input, int $topK = 3): array {
        $input = strtolower(trim($input));
        if ($input === '') return ['answer' => '', 'status' => 'empty', 'score' => 0.0];

        // 1. Exact full-sentence match
        $nq = $this->encode($input);
        $na = $this->lookupLearned($nq);
        if ($na !== null) {
            $decoded = $this->decode($na);
            if (!$this->isSelfReferential($input, $decoded)) {
                return [
                    'answer' => $decoded,
                    'status' => 'exact',
                    'score' => 1.0,
                    'parts' => [$decoded],
                    'layer' => 'exact-learned',
                ];
            }
        }

        // 2. Split into regions by intent bytes
        $regions = ComposeHelpers::splitByIntentRegions(
            $input,
            $this->intentPhrases,
            $this->intentTable
        );

        if (empty($regions)) {
            return $this->askSingle($input, $topK);
        }

        // 3. Single region — direct lookup
        if (count($regions) === 1) {
            $r = $regions[0];
            if ($r['intent'] !== null && $r['data'] !== '') {
                $matches = $this->collectIntentMatches($r['intent'], $r['data'], $topK);
                if (!empty($matches)) {
                    return [
                        'answer' => $matches[0]['text'],
                        'status' => 'ok',
                        'score' => $matches[0]['score'],
                        'top' => $matches,
                        'layer' => 'intent-region',
                        'intent' => $r['intent'],
                        'data' => $r['data'],
                    ];
                }
            }
            return $this->askSingle($input, $topK);
        }

        // 4. Multiple regions — resolve each with Markov prediction + pronouns
        $parts = [];
        $allOk = true;
        $prevIntent = null;
        $prevEntity = null;
        $debug = [];

        foreach ($regions as $i => $region) {
            $intent = $region['intent'];
            $data = $region['data'];

            // Predict expected next intent from Markov
            $predictedBoost = 0.0;
            if ($prevIntent !== null) {
                $prob = $this->nextIntentProbability($prevIntent, $intent ?? 'unknown');
                if ($prob >= 0.30) $predictedBoost = 0.10;
                elseif ($prob >= 0.10) $predictedBoost = 0.05;
            }

            // Pronoun substitution
            if ($prevEntity !== null) {
                $data = preg_replace(
                    '/\b(he|she|it|they|them|its)\b/',
                    $prevEntity,
                    $data
                );
                $data = trim(preg_replace('/\s+/', ' ', $data));
            }

            // Lookup
            $r = null;
            if ($intent !== null && $data !== '') {
                $matches = $this->collectIntentMatches($intent, $data, $topK);
                if (!empty($matches)) {
                    $r = [
                        'answer' => $matches[0]['text'],
                        'status' => 'ok',
                        'score' => $matches[0]['score'] + $predictedBoost,
                        'top' => $matches,
                    ];
                }
            }

            if ($r === null || $r['status'] === 'none' || $r['status'] === 'empty') {
                // Fallback: fuzzy on the region's data
                $r = $this->askSingle($data !== '' ? $data : $region['data'], $topK);
                if ($r['status'] === 'none' || $r['status'] === 'empty') {
                    $allOk = false;
                    break;
                }
            }

            $parts[] = $r['answer'];
            $debug[] = [
                'intent' => $intent,
                'data' => $data,
                'predicted_boost' => $predictedBoost,
                'score' => $r['score'],
            ];

            $prevIntent = $intent;
            $prevEntity = $this->extractSubject($r['answer']);
        }

        if ($allOk && !empty($parts)) {
            $joined = implode('. ', array_map(fn($p) => rtrim($p, '. '), $parts)) . '.';
            return [
                'answer' => $joined,
                'status' => 'composed',
                'score' => 0.85,
                'parts' => $parts,
                'layer' => 'multi-region-markov',
                'regions' => $debug,
            ];
        }

        // 5. Fallback — ensemble fuzzy
        return $this->askSingle($input, $topK);
    }

    /**
     * Ensemble scorer: all layers vote, weights combine, agreement stacks.
     */
    private function askSingle(string $input, int $topK): array {
        $input = strtolower(trim($input));

        // Layer 1 — exact learned
        $nq = $this->encode($input);
        $na = $this->lookupLearned($nq);
        if ($na !== null) {
            $decoded = $this->decode($na);
            if (!$this->isSelfReferential($input, $decoded)) {
                return ['answer' => $decoded, 'status' => 'exact', 'score' => 1.0, 'layer' => 'exact-learned'];
            }
        }

        if ($this->factsTotal === 0) {
            return ['answer' => '', 'status' => 'no_facts', 'score' => 0.0];
        }

        // Layer 2 — intent matches (header + data)
        $intentMatches = [];
        $split = ComposeHelpers::splitHeader($input, $this->intentPhrases, $this->intentTable);
        if ($split['intent'] !== null && $split['data'] !== '') {
            $intentMatches = $this->collectIntentMatches($split['intent'], $split['data'], 20);
        }

        // Layers 3+4 — word + char similarity
        $fuzzyMatches = $this->collectFuzzyMatches($input, 50);

        // ============ MERGE ============
        $merged = [];

        foreach ($intentMatches as $m) {
            $key = $m['text'];
            if (!isset($merged[$key])) {
                $merged[$key] = ['text' => $key, 'number' => $m['number'], 'score' => 0.0, 'layers' => []];
            }
            $merged[$key]['score'] += self::W_INTENT * $m['score'];
            $merged[$key]['layers'][] = 'intent:' . round($m['score'], 2);
        }

        foreach ($fuzzyMatches as $m) {
            $key = $m['text'];
            if (!isset($merged[$key])) {
                $merged[$key] = ['text' => $key, 'number' => $m['number'], 'score' => 0.0, 'layers' => []];
            }
            $merged[$key]['score'] += self::W_WORD * $m['word'] + self::W_CHAR * $m['char'];
            $merged[$key]['layers'][] = 'word:' . round($m['word'], 2) . ',char:' . round($m['char'], 2);
        }

        if (empty($merged)) {
            return ['answer' => '', 'status' => 'none', 'score' => 0.0];
        }

        // Agreement bonus — multiple layers agree on same fact
        foreach ($merged as &$m) {
            if (count($m['layers']) >= 2) {
                $m['score'] *= self::W_AGREE_BONUS;
            }
        }
        unset($m);

        $sorted = array_values($merged);
        usort($sorted, fn($a, $b) => $b['score'] <=> $a['score']);

        if (getenv('DEBUG_ASK')) {
            fwrite(STDERR, "Query: $input\n");
            fwrite(STDERR, "Intent matches (" . count($intentMatches) . "):\n");
            foreach (array_slice($intentMatches, 0, 5) as $m) {
                fwrite(STDERR, "  " . round($m['score'], 3) . "  " . substr($m['text'], 0, 70) . "\n");
            }
            fwrite(STDERR, "Fuzzy top 3:\n");
            foreach (array_slice($fuzzyMatches, 0, 3) as $m) {
                fwrite(STDERR, "  w=" . round($m['word'], 2) . " c=" . round($m['char'], 2) . "  " . substr($m['text'], 0, 60) . "\n");
            }
            fwrite(STDERR, "Merged top 3:\n");
            foreach (array_slice($sorted, 0, 3) as $m) {
                fwrite(STDERR, "  " . round($m['score'], 3) . "  " . substr($m['text'], 0, 60) . "  [" . implode('|', $m['layers']) . "]\n");
            }
        }

        $best = $sorted[0];
        $status = $best['score'] >= 0.45 ? 'ok'
                : ($best['score'] >= 0.25 ? 'low' : 'none');

        return [
            'answer' => $best['text'],
            'status' => $status,
            'score'  => $best['score'],
            'top'    => array_slice($sorted, 0, $topK),
            'layer'  => 'ensemble',
            'layers' => $best['layers'],
        ];
    }

    /**
     * Layer 2 — collect facts matching intent + data.
     */
    private function collectIntentMatches(string $intent, string $data, int $limit): array {
        $found = [];

        // Synonym set for this intent
        $synonyms = [];
        foreach ($this->intentTable as $phrase => $intentName) {
            if ($intentName === $intent) $synonyms[] = $phrase;
        }

        // Try exact learned pairs: synonyms + data
        $candidates = [];
        foreach ($synonyms as $syn) {
            $candidates[] = $syn . ' ' . $data;
            $candidates[] = $syn . ' the ' . $data;
        }
        $candidates[] = $data;
        $candidates[] = 'the ' . $data;

        foreach ($candidates as $c) {
            $nq = $this->encode($c);
            $na = $this->lookupLearned($nq);
            if ($na !== null) {
                $decoded = $this->decode($na);
                if (!$this->isSelfReferential($c, $decoded)) {
                    $found[$decoded] = [
                        'text'   => $decoded,
                        'number' => $na,
                        'score'  => 1.0,
                    ];
                }
            }
        }

        // Facts starting with data / "the" + data
        $probes = [$data, 'the ' . $data, preg_replace('/^(the|a|an)\s+/', '', $data)];
        $probes = array_unique(array_filter($probes, fn($p) => $p !== ''));
        foreach ($probes as $probe) {
            $facts = $this->searchFactsStartingWith($probe, $limit);
            foreach ($facts as $f) {
                $key = $f['text'];
                if (!isset($found[$key]) || $found[$key]['score'] < $f['score']) {
                    $found[$key] = $f;
                }
            }
        }

        // Facts CONTAINING data (for "the capital of france is..." when data="france")
        $containing = $this->searchFactsContaining($data, $limit);
        foreach ($containing as $f) {
            $key = $f['text'];
            if (!isset($found[$key]) || $found[$key]['score'] < $f['score']) {
                $found[$key] = $f;
            }
        }

        // Intent-shape filter (penalty, not removal)
        $patterns = [
            'definition' => '/\b(is|are|was|were|refers to|means)\b/i',
            'person'     => '/\b(is|was|born|died|lived|known for)\b/i',
            'author'     => '/\b(wrote|invented|created|discovered|composed|painted|built)\b/i',
            'procedure'  => '/\b(to |by |first |then |boil|bake|mix|add|steep|brew|cook)\b/i',
            'location'   => '/\b(is in|is located|is at|lies|near)\b/i',
            'time'       => '/\b(in \d|on \d|during|around|year|century)\b/i',
            'reason'     => '/\b(because|since|due to|causes|leads to)\b/i',
            'capital'    => '/\b(capital|is the capital|capital of)\b/i',
        ];
        $pattern = $patterns[$intent] ?? null;

        if ($pattern !== null) {
            foreach ($found as $key => $f) {
                if (!preg_match($pattern, $f['text'])) {
                    $found[$key]['score'] *= 0.6;
                }
            }
        }

        return array_values($found);
    }

    /**
     * Layers 3+4 — word overlap + char similarity across candidate shards.
     */
    private function collectFuzzyMatches(string $input, int $limit): array {
        $candidates = $this->candidateShardsFor($input);
        if (empty($candidates)) {
            $candidates = range(0, $this->factsShards - 1);
        }

        $all = [];
        foreach ($candidates as $shardIdx) {
            $path = $this->dir . '/facts/' . sprintf('%05d', $shardIdx) . '.json';
            if (!file_exists($path)) continue;
            $facts = json_decode(file_get_contents($path), true);
            if (!is_array($facts)) continue;

            foreach ($facts as $f) {
                $word = ComposeHelpers::wordOverlap($input, $f['t']);
                $char = ComposeHelpers::charSimilarity($input, $f['t']);
                $score = 0.7 * $word + 0.3 * $char;
                if ($score > 0.15) {
                    $all[] = [
                        'text'   => $f['t'],
                        'number' => $f['n'],
                        'word'   => $word,
                        'char'   => $char,
                    ];
                }
            }
            unset($facts);
            if (count($all) > $limit * 3) {
                usort($all, fn($a, $b) => (0.7*$b['word']+0.3*$b['char']) <=> (0.7*$a['word']+0.3*$a['char']));
                $all = array_slice($all, 0, $limit * 2);
            }
        }

        usort($all, fn($a, $b) => (0.7*$b['word']+0.3*$b['char']) <=> (0.7*$a['word']+0.3*$a['char']));
        return array_slice($all, 0, $limit);
    }

    private function candidateShardsFor(string $input): array {
        $prefix = $this->prefixKey($input);
        $candidates = $this->factIndex[$prefix] ?? [];
        if (empty($candidates) && strlen($prefix) >= 3) {
            $short = substr($prefix, 0, 3);
            $merged = [];
            foreach ($this->factIndex as $k => $shards) {
                $k = (string)$k;
                if (substr($k, 0, 3) === $short) $merged = array_merge($merged, $shards);
            }
            $candidates = array_values(array_unique($merged));
        }
        return $candidates;
    }

    private function searchFactsStartingWith(string $content, int $topK): array {
        $content = strtolower(trim($content));
        if ($content === '') return [];
        $results = [];

        $candidates = $this->candidateShardsFor($content);

        foreach ($candidates as $shardIdx) {
            $path = $this->dir . '/facts/' . sprintf('%05d', $shardIdx) . '.json';
            if (!file_exists($path)) continue;
            $facts = json_decode(file_get_contents($path), true);
            if (!is_array($facts)) continue;
            foreach ($facts as $f) {
                $score = ComposeHelpers::startsWithScore($content, $f['t']);
                if ($score > 0) {
                    if (strtolower(trim($f['t'])) === $content) continue;
                    $results[] = [
                        'score'  => $score,
                        'text'   => $f['t'],
                        'number' => $f['n'],
                    ];
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $topK);
    }

    private function searchFactsContaining(string $content, int $topK): array {
        $content = strtolower(trim($content));
        if ($content === '') return [];
        $results = [];

        $candidates = $this->candidateShardsFor($content);
        if (empty($candidates)) {
            $candidates = range(0, $this->factsShards - 1);
        }

        foreach ($candidates as $shardIdx) {
            $path = $this->dir . '/facts/' . sprintf('%05d', $shardIdx) . '.json';
            if (!file_exists($path)) continue;
            $facts = json_decode(file_get_contents($path), true);
            if (!is_array($facts)) continue;
            foreach ($facts as $f) {
                if (strpos(strtolower($f['t']), $content) !== false) {
                    $results[] = [
                        'score' => 0.6 * ComposeHelpers::lengthBonus($f['t']),
                        'text' => $f['t'],
                        'number' => $f['n'],
                    ];
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $topK);
    }

    public function stats(): array {
        return [
            'facts'         => $this->factsTotal,
            'learned'       => $this->learnedTotal,
            'factsShards'   => $this->factsShards,
            'learnedShards' => $this->learnedShards,
            'indexKeys'     => count($this->factIndex),
            'intentPhrases' => count($this->intentPhrases),
        ];
    }
}
