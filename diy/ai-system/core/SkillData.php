<?php

declare(strict_types=1);

/**
 * Unified request envelope passed through:
 * PromptUnderstanding → Knowledge → ResponseUnderstanding
 *
 * One fixed shape; stages fill fields in place.
 *
 * @see ARCHITECTURE.md
 */
if (!class_exists('SkillData', false)) {

class SkillData
{
    public const VERSION = 1;

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Bootstrap a new envelope for a user prompt.
     */
    public static function create(string $prompt, string $promptOriginal = '', string $sessionId = ''): self
    {
        $prompt = trim($prompt);
        $promptOriginal = trim($promptOriginal) !== '' ? trim($promptOriginal) : $prompt;

        return new self([
            'version'            => self::VERSION,
            'skill'              => 'router',

            'prompt'             => $prompt,
            'prompt_original'    => $promptOriginal,
            'core_query'         => '',

            'intent'             => 'ask_information',
            'secondary_intents'  => [],
            'topic'              => 'general_topic',
            'subtopics'          => [],
            'entities'           => [],
            'question_type'      => 'statement',
            'constraints'        => [],
            'key_phrases'        => [],
            'specific_requirements' => [],
            'has_specifics'      => false,
            'is_multi_part'      => false,

            'facts'              => [],
            'query_embedding'    => [],

            'memory_hits'        => [],
            'memory_sufficient'  => false,
            'answer_confidence'  => 0.0,

            'openrouter'         => [
                'enabled'  => false,
                'mode'     => 'hybrid',
                'gap_fill' => false,
                'raw'      => null,
            ],

            'draft_answer'       => '',
            'final_answer'       => '',

            'session_id'         => $sessionId,
            'conversation'       => [],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $base = self::create(
            (string)($data['prompt'] ?? ''),
            (string)($data['prompt_original'] ?? ''),
            (string)($data['session_id'] ?? '')
        );
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $base->data)) {
                $base->data[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): self
    {
        if (array_key_exists($key, $this->data)) {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function setSkill(string $skill): self
    {
        $this->data['skill'] = $skill;
        return $this;
    }

    /**
     * Merge fact records into data['facts'] (dedupe by normalized content).
     *
     * @param list<array<string, mixed>> $facts
     */
    public function mergeFacts(array $facts): self
    {
        $existing = is_array($this->data['facts']) ? $this->data['facts'] : [];
        $seen = [];
        foreach ($existing as $f) {
            if (!is_array($f)) {
                continue;
            }
            $k = strtolower(trim((string)($f['content'] ?? $f['value'] ?? '')));
            if ($k !== '') {
                $seen[$k] = true;
            }
        }
        foreach ($facts as $f) {
            if (!is_array($f)) {
                continue;
            }
            $k = strtolower(trim((string)($f['content'] ?? $f['value'] ?? '')));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $existing[] = self::normalizeFact($f);
        }
        $this->data['facts'] = $existing;
        return $this;
    }

    /**
     * Normalize a fact to the architecture Fact shape.
     *
     * @param array<string, mixed> $fact
     * @return array<string, mixed>
     */
    /**
     * Canonical Fact shape (memory unit — parallel to SkillData for requests).
     *
     * Every fact has its own content, type, vector, keywords/tags.
     * Topics only group facts; answers are built from selected facts only.
     *
     * @param array<string, mixed> $fact
     * @return array<string, mixed>
     */
    public static function normalizeFact(array $fact): array
    {
        $content = trim((string)($fact['content'] ?? $fact['value'] ?? $fact['fact'] ?? ''));
        $now = gmdate('c');

        $keywords = [];
        if (isset($fact['keywords']) && is_array($fact['keywords'])) {
            $keywords = array_values(array_unique(array_filter(array_map('strval', $fact['keywords']))));
        }
        $tags = [];
        if (isset($fact['tags']) && is_array($fact['tags'])) {
            $tags = array_values(array_unique(array_filter(array_map('strval', $fact['tags']))));
        }

        // Prefer FactSkill helpers when available
        if ($content !== '' && class_exists('FactSkill', false)) {
            if ($keywords === []) {
                $keywords = FactSkill::extractKeywords($content);
            }
            if ($tags === [] && $keywords !== []) {
                $tags = array_slice($keywords, 0, 6);
            }
        }

        $embedding = is_array($fact['embedding'] ?? null) ? $fact['embedding'] : [];
        if ($content !== '' && class_exists('FactSkill', false)) {
            if ($embedding === [] || (defined('LocalEmbedder::DIMENSIONS') && count($embedding) !== LocalEmbedder::DIMENSIONS)) {
                $embedSrc = $content;
                if (function_exists('mb_substr')) {
                    $embedSrc = mb_substr($content, 0, 800);
                } else {
                    $embedSrc = substr($content, 0, 800);
                }
                $embedding = FactSkill::embed($embedSrc);
            }
        }

        $id = trim((string)($fact['id'] ?? ''));
        if ($id === '') {
            $id = 'fact_' . substr(sha1($content . '|' . ($fact['type'] ?? 'statement')), 0, 12);
        }

        return [
            'id'         => $id,
            'content'    => $content,
            'value'      => $content, // backward compat
            'type'       => (string)($fact['type'] ?? 'statement'),
            'source'     => (string)($fact['source'] ?? 'unknown'),
            'confidence' => (float)($fact['confidence'] ?? 0.8),
            'embedding'  => $embedding,
            'keywords'   => $keywords,
            'tags'       => $tags,
            'created_at' => (string)($fact['created_at'] ?? $fact['added_at'] ?? $now),
            'updated_at' => (string)($fact['updated_at'] ?? $now),
        ];
    }

    public function prompt(): string
    {
        return (string)$this->data['prompt'];
    }

    public function topic(): string
    {
        $t = trim((string)$this->data['topic']);
        return $t !== '' ? $t : 'general_topic';
    }

    public function draftAnswer(): string
    {
        return (string)$this->data['draft_answer'];
    }

    public function finalAnswer(): string
    {
        $final = trim((string)$this->data['final_answer']);
        if ($final !== '') {
            return $final;
        }
        return trim((string)$this->data['draft_answer']);
    }
}

} // class_exists
