<?php

declare(strict_types=1);

require_once __DIR__ . '/../skills/FactSkill.php';

/**
 * Short-term conversation memory (local JSON only), per session id.
 *
 * Path: memory/conversation/{sessionId}.json
 * Keeps up to N turns. When full, drops the outlier least similar to the
 * cluster of (other turns + the new turn), then appends the new turn.
 */
if (!class_exists('ConversationStore', false)) {

class ConversationStore
{
    private string $path;
    private int $maxTurns;
    private string $sessionId;

    public function __construct(
        string $sessionId = '',
        int $maxTurns = 10,
        string $baseDir = ''
    ) {
        $this->sessionId = self::sanitizeSessionId($sessionId);
        $this->maxTurns = max(2, $maxTurns);
        $dir = $baseDir !== ''
            ? rtrim($baseDir, '/\\')
            : (__DIR__ . '/../memory/conversation');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->path = $dir . DIRECTORY_SEPARATOR . $this->sessionId . '.json';
    }

    public static function sanitizeSessionId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        if ($id === '' || strlen($id) < 8) {
            $id = bin2hex(random_bytes(16));
        }
        if (strlen($id) > 64) {
            $id = substr($id, 0, 64);
        }
        return $id;
    }

    /**
     * Resolve or create a session id from cookie / POST / GET.
     * Sets cookie when appropriate.
     */
    public static function resolveSessionId(): string
    {
        $fromRequest = '';
        if (isset($_POST['conversation_session']) && is_string($_POST['conversation_session'])) {
            $fromRequest = $_POST['conversation_session'];
        } elseif (isset($_GET['conversation_session']) && is_string($_GET['conversation_session'])) {
            $fromRequest = $_GET['conversation_session'];
        } elseif (isset($_COOKIE['cfc_ai_session']) && is_string($_COOKIE['cfc_ai_session'])) {
            $fromRequest = $_COOKIE['cfc_ai_session'];
        }

        $id = self::sanitizeSessionId($fromRequest);

        // Refresh cookie (30 days)
        if (!headers_sent()) {
            setcookie('cfc_ai_session', $id, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['cfc_ai_session'] = $id;

        return $id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return list<array{prompt:string,answer:string,topic:string,embedding:list<float>,created_at:string}>
     */
    public function getTurns(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($this->path), true);
        if (!is_array($data)) {
            return [];
        }
        $turns = isset($data['turns']) && is_array($data['turns']) ? $data['turns'] : $data;
        if (!is_array($turns)) {
            return [];
        }
        $out = [];
        foreach ($turns as $t) {
            if (!is_array($t)) {
                continue;
            }
            $prompt = trim((string)($t['prompt'] ?? ''));
            $answer = trim((string)($t['answer'] ?? ''));
            if ($prompt === '' && $answer === '') {
                continue;
            }
            $out[] = [
                'prompt'     => $prompt,
                'answer'     => $answer,
                'topic'      => (string)($t['topic'] ?? ''),
                'embedding'  => is_array($t['embedding'] ?? null) ? array_map('floatval', $t['embedding']) : [],
                'created_at' => (string)($t['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    public function addTurn(string $prompt, string $answer, string $topic = ''): void
    {
        $prompt = trim($prompt);
        $answer = trim($answer);
        if ($prompt === '') {
            return;
        }

        $snippet = function_exists('mb_substr') ? mb_substr($answer, 0, 400) : substr($answer, 0, 400);
        $embedding = FactSkill::embed($prompt . ' ' . $snippet);
        $new = [
            'prompt'     => $prompt,
            'answer'     => $answer,
            'topic'      => $topic,
            'embedding'  => $embedding,
            'created_at' => gmdate('c'),
        ];

        $turns = $this->getTurns();

        if (count($turns) >= $this->maxTurns && $embedding !== []) {
            $dropIndex = $this->findOutlierIndex($turns, $embedding);
            if ($dropIndex !== null) {
                array_splice($turns, $dropIndex, 1);
            } else {
                array_shift($turns);
            }
        } elseif (count($turns) >= $this->maxTurns) {
            array_shift($turns);
        }

        $turns[] = $new;
        $this->save($turns);
    }

    /**
     * @return list<array>
     */
    public function relevantTurns(array $queryEmbedding, int $limit = 3, float $minScore = 0.25): array
    {
        if ($queryEmbedding === []) {
            return array_slice($this->getTurns(), -$limit);
        }

        $scored = [];
        foreach ($this->getTurns() as $i => $turn) {
            $emb = $turn['embedding'] ?? [];
            if (!is_array($emb) || $emb === []) {
                continue;
            }
            $score = FactSkill::cosineSimilarity($queryEmbedding, $emb);
            if ($score >= $minScore) {
                $turn['_score'] = $score;
                $turn['_index'] = $i;
                $scored[] = $turn;
            }
        }

        usort($scored, static fn(array $a, array $b): int =>
            ((float)$b['_score'] <=> (float)$a['_score'])
        );

        $top = array_slice($scored, 0, max(1, $limit));
        usort($top, static fn(array $a, array $b): int =>
            ((int)($a['_index'] ?? 0) <=> (int)($b['_index'] ?? 0))
        );

        return $top;
    }

    private function findOutlierIndex(array $turns, array $newEmbedding): ?int
    {
        $n = count($turns);
        if ($n === 0) {
            return null;
        }

        $bestIndex = 0;
        $bestScore = PHP_FLOAT_MAX;

        for ($i = 0; $i < $n; $i++) {
            $vectors = [];
            for ($j = 0; $j < $n; $j++) {
                if ($j === $i) {
                    continue;
                }
                $emb = $turns[$j]['embedding'] ?? [];
                if (is_array($emb) && $emb !== []) {
                    $vectors[] = $emb;
                }
            }
            $vectors[] = $newEmbedding;
            $centroid = $this->meanVector($vectors);
            $candidate = $turns[$i]['embedding'] ?? [];
            if (!is_array($candidate) || $candidate === [] || $centroid === []) {
                return $i;
            }
            $sim = FactSkill::cosineSimilarity($candidate, $centroid);
            if ($sim < $bestScore) {
                $bestScore = $sim;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    /**
     * @param list<list<float>> $vectors
     * @return list<float>
     */
    private function meanVector(array $vectors): array
    {
        if ($vectors === []) {
            return [];
        }
        $dim = count($vectors[0]);
        $sum = array_fill(0, $dim, 0.0);
        $count = 0;
        foreach ($vectors as $v) {
            if (count($v) !== $dim) {
                continue;
            }
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] += (float)$v[$i];
            }
            $count++;
        }
        if ($count === 0) {
            return [];
        }
        for ($i = 0; $i < $dim; $i++) {
            $sum[$i] /= $count;
        }
        return $sum;
    }

    private function save(array $turns): void
    {
        $payload = [
            'session_id' => $this->sessionId,
            'max_turns'  => $this->maxTurns,
            'updated_at' => gmdate('c'),
            'turns'      => array_values($turns),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->path . '.tmp.' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $json) === false) {
            return;
        }
        @rename($tmp, $this->path);
    }
}

} // class_exists
