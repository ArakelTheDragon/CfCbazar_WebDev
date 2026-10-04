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
            'is_multi_part'      => false,

            'facts'              => [],
            'query_embedding'    => [],

            'memory_hits'        => [],
            'memory_sufficient'  => false,

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
    public static function normalizeFact(array $fact): array
    {
        $content = trim((string)($fact['content'] ?? $fact['value'] ?? $fact['fact'] ?? ''));
        return [
            'content'    => $content,
            'value'      => $content, // backward compat for older readers
            'type'       => (string)($fact['type'] ?? 'statement'),
            'source'     => (string)($fact['source'] ?? 'unknown'),
            'confidence' => (float)($fact['confidence'] ?? 0.8),
            'embedding'  => is_array($fact['embedding'] ?? null) ? $fact['embedding'] : [],
            'created_at' => (string)($fact['created_at'] ?? $fact['added_at'] ?? gmdate('c')),
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
