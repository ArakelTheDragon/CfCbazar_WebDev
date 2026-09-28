<?php

declare(strict_types=1);

require_once __DIR__ . '/FactSkill.php';

/**
 * Analyse an English-language user prompt.
 *
 * Produces routing/context info + prompt facts + query embedding.
 * Does not retrieve memory, call OpenRouter for chat, or generate final response.
 */
class PromptUnderstandingSkill
{
    public function analyze(string $prompt): array
    {
        $clean = $this->normalizePrompt($prompt);

        $intent       = $this->detectIntent($clean);
        $topic        = $this->extractTopic($clean);
        $entities     = $this->extractEntities($clean);
        $questionType = $this->detectQuestionType($clean);

        // FactSkill extracts prompt facts and generates the query embedding
        $promptFacts  = FactSkill::extractFactsFromPrompt($clean);
        $queryEmbedding = FactSkill::embed($clean);

        return [
            'raw_prompt'      => $clean,
            'intent'          => $intent,
            'topic'           => $topic,
            'entities'        => $entities,
            'question_type'   => $questionType,
            'prompt_facts'    => $promptFacts,
            'query_embedding' => $queryEmbedding,
        ];
    }

    private function normalizePrompt(string $prompt): string
    {
        $prompt = trim($prompt);
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $prompt) ?? $prompt;

        return preg_replace('/\s+/u', ' ', $prompt) ?? $prompt;
    }

    private function detectIntent(string $prompt): string
    {
        $p = strtolower($prompt);

        $patterns = [
            'explain how'      => 'explain_process',
            'how do i'         => 'explain_process',
            'how can i'        => 'explain_process',
            'how does'         => 'explain_process',
            'how is'           => 'explain_process',
            'why does'         => 'explain_reason',
            'why is'           => 'explain_reason',
            'why are'          => 'explain_reason',
            'what is the difference' => 'compare_things',
            'what is different'     => 'compare_things',
            'difference between'     => 'compare_things',
            'compare'          => 'compare_things',
            ' vs '             => 'compare_things',
            'versus'           => 'compare_things',
            'what is'          => 'define_concept',
            'what are'         => 'define_concept',
            'define '          => 'define_concept',
            'list '            => 'list_information',
            'give me a list'   => 'list_information',
            'show me'          => 'ask_information',
            'tell me'          => 'ask_information',
        ];

        foreach ($patterns as $pattern => $intent) {
            if ($this->containsPhrase($p, $pattern)) {
                return $intent;
            }
        }

        if ($this->containsWord($p, 'how')) {
            return 'explain_process';
        }

        if ($this->containsWord($p, 'why')) {
            return 'explain_reason';
        }

        if ($this->containsWord($p, 'who') || $this->containsWord($p, 'what')) {
            return 'ask_information';
        }

        return 'ask_information';
    }

    private function extractTopic(string $prompt): string
    {
        $p = strtolower($prompt);

        $topics = [
            'prompt understanding' => 'prompt_understanding',
            'response understanding' => 'response_understanding',
            'knowledge skill' => 'knowledge_skill',
            'fact skill' => 'fact_skill',
            'memory store' => 'ai_memory_system',
            'ai memory' => 'ai_memory_system',
            'artificial intelligence' => 'artificial_intelligence',
            'php programming' => 'php_programming',
            'php' => 'php_programming',
            'json' => 'json_data',
            'router' => 'ai_router_system',
            'memory' => 'ai_memory_system',
        ];

        foreach ($topics as $keyword => $topicName) {
            if ($this->containsPhrase($p, $keyword)) {
                return $topicName;
            }
        }

        $subject = preg_replace(
            '/^(what|who|when|where|why|how)\b(?:\s+(?:is|are|does|do|can|could|would|was|were))?\s+/i',
            '',
            trim($prompt)
        );

        $subject = trim((string)$subject, " \t\n\r\0\x0B?.!,;:");

        if (preg_match('/"([^"]{2,120})"/', $prompt, $match)) {
            return $this->slugTopic($match[1]);
        }

        if (preg_match('/\b([a-zA-Z0-9_-]+\.(?:php|json|txt))\b/i', $prompt, $match)) {
            return $this->slugTopic(pathinfo($match[1], PATHINFO_FILENAME));
        }

        if ($subject !== '') {
            $words = preg_split('/\s+/u', $subject, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $words = array_slice($words, 0, 6);
            $candidate = implode(' ', $words);

            if ($candidate !== '') {
                return $this->slugTopic($candidate);
            }
        }

        return 'general_topic';
    }

    private function extractEntities(string $prompt): array
    {
        $entities = [];

        if (preg_match_all('/"([^"]+)"/', $prompt, $quoted)) {
            foreach ($quoted[1] as $q) {
                $q = trim($q);
                if ($q !== '') {
                    $entities[] = $q;
                }
            }
        }

        if (preg_match_all('/\b[a-zA-Z0-9_-]+\.(?:php|json|txt)\b/i', $prompt, $files)) {
            foreach ($files[0] as $file) {
                $entities[] = $file;
            }
        }

        foreach ([
            'php',
            'ai',
            'json',
            'array',
            'router',
            'memory',
            'openrouter',
            'knowledge skill',
            'fact skill',
            'prompt understanding skill',
            'response understanding skill',
        ] as $entity) {
            if ($this->containsPhrase(strtolower($prompt), $entity)) {
                $entities[] = $entity;
            }
        }

        return array_values(array_unique($entities));
    }

    private function detectQuestionType(string $prompt): string
    {
        $p = strtolower(ltrim($prompt));

        foreach (['who', 'what', 'when', 'where', 'why', 'how'] as $type) {
            if ($this->containsWordAtStart($p, $type)) {
                return $type;
            }
        }

        return 'statement';
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        $phrase = trim(strtolower($phrase));
        if ($phrase === '') {
            return false;
        }

        if (preg_match('/^[a-z0-9]+(?:[ _-][a-z0-9]+)*$/i', $phrase)) {
            return preg_match('/\b' . preg_quote($phrase, '/') . '\b/i', $text) === 1;
        }

        return str_contains($text, $phrase);
    }

    private function containsWord(string $text, string $word): bool
    {
        return preg_match('/\b' . preg_quote(strtolower($word), '/') . '\b/i', $text) === 1;
    }

    private function containsWordAtStart(string $text, string $word): bool
    {
        return preg_match('/^' . preg_quote(strtolower($word), '/') . '\b/i', $text) === 1;
    }

    private function slugTopic(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'general_topic';
    }
}
