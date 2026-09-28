<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/FactSkill.php';

/**
 * Central knowledge system.
 *
 * Workflow:
 * 1. Vector search local memory (using query_embedding from PromptUnderstanding).
 * 2. Fallback to keyword relevance if vector results insufficient.
 * 3. Call OpenRouter only when local memory is inadequate.
 * 4. Extract facts + embeddings via FactSkill and persist them.
 * 5. Build response from the resulting facts.
 */
class KnowledgeSkill
{
    private int $minimumUsefulFacts = 1;
    private int $maximumMemoryAgeDays = 30;
    private float $minimumVectorScore = 0.55;
    private float $minimumKeywordScore = 0.20;
    private int $vectorLimit = 12;

    public function respond(array $analysis, MemoryStore $memory): string
    {
        $prompt = trim((string)($analysis['raw_prompt'] ?? ''));
        $topic = trim((string)($analysis['topic'] ?? 'general_topic')) ?: 'general_topic';
        $intent = (string)($analysis['intent'] ?? 'ask_information');
        $entities = is_array($analysis['entities'] ?? null) ? $analysis['entities'] : [];
        $questionType = (string)($analysis['question_type'] ?? 'statement');
        $promptFacts = is_array($analysis['prompt_facts'] ?? null) ? $analysis['prompt_facts'] : [];
        $queryEmbedding = is_array($analysis['query_embedding'] ?? null) ? $analysis['query_embedding'] : [];

        if ($prompt === '') {
            return 'Please provide a question or topic.';
        }

        // 1. Vector search first (topic-scoped, then global if needed)
        $usefulFacts = [];

        if ($queryEmbedding !== []) {
            $usefulFacts = $memory->searchByVector(
                $queryEmbedding,
                $this->minimumVectorScore,
                $this->vectorLimit,
                $topic
            );

            // If topic search yields too little, try global
            if (count($usefulFacts) < $this->minimumUsefulFacts) {
                $global = $memory->searchByVector(
                    $queryEmbedding,
                    $this->minimumVectorScore,
                    $this->vectorLimit,
                    null
                );
                $usefulFacts = $this->mergeUniqueFacts($usefulFacts, $global);
            }
        }

        // 2. Keyword fallback on topic facts
        if (count($usefulFacts) < $this->minimumUsefulFacts) {
            $storedFacts = $memory->getTopicFacts($topic);
            $keywordFacts = $this->selectUsefulMemoryFacts($storedFacts, $prompt, $topic);
            $usefulFacts = $this->mergeUniqueFacts($usefulFacts, $keywordFacts);
        }

        // Age filter
        $usefulFacts = $this->filterByAge($usefulFacts);

        if (count($usefulFacts) >= $this->minimumUsefulFacts) {
            return $this->formatAnswer($topic, $intent, $entities, $usefulFacts, $questionType);
        }

        // 3. Local memory inadequate → OpenRouter
        $response = $this->requestOpenRouter($prompt, $topic, $intent, $questionType);

        if (isset($response['error'])) {
            if (!empty($usefulFacts)) {
                return $this->formatAnswer($topic, $intent, $entities, $usefulFacts, $questionType);
            }
            return 'I could not retrieve knowledge for this topic right now. ' . ($response['message'] ?? '');
        }

        $text = trim((string)($response['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            return 'OpenRouter returned an empty response.';
        }

        // 4. Extract facts + embeddings and persist
        $responseFacts = FactSkill::extractFactsFromText($text);
        $factsToStore = FactSkill::mergeFacts($promptFacts, $responseFacts);

        if (!empty($factsToStore)) {
            $memory->mergeTopicFacts($topic, $factsToStore);
        }

        // Prefer newly stored facts for the answer
        $finalFacts = $this->filterByAge(
            $this->mergeUniqueFacts($usefulFacts, $responseFacts)
        );

        if (!empty($finalFacts)) {
            return $this->formatAnswer($topic, $intent, $entities, $finalFacts, $questionType);
        }

        return $text;
    }

    private function requestOpenRouter(
        string $prompt,
        string $topic,
        string $intent,
        string $questionType
    ): array {
        $config = require __DIR__ . '/../config/openrouter.php';

        $instruction = "Answer the user's English question accurately and directly. "
            . "Focus only on the requested topic: {$topic}. "
            . "Question type: {$questionType}. Intent: {$intent}. "
            . "Do not answer about a different subject. Provide useful factual statements.\n\n"
            . "User question: {$prompt}";

        $payload = [
            'model' => $config['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $instruction,
                ],
            ],
            'temperature' => 0.4,
        ];

        return Helpers::httpPostJson(
            $config['base_url'],
            ['Authorization: Bearer ' . $config['api_key']],
            $payload
        ) ?? Helpers::error('No valid response received from OpenRouter.');
    }

    private function selectUsefulMemoryFacts(array $facts, string $prompt, string $topic): array
    {
        $selected = [];

        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }

            $value = trim((string)($fact['value'] ?? $fact['content'] ?? $fact['fact'] ?? ''));
            if ($value === '' || ($fact['type'] ?? '') === 'prompt_intent') {
                continue;
            }

            $score = $this->relevanceScore($prompt, $topic, $value);
            if ($score >= $this->minimumKeywordScore || $topic === 'general_topic') {
                $selected[] = $fact;
            }
        }

        return $selected;
    }

    private function filterByAge(array $facts): array
    {
        $now = time();
        $kept = [];

        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }

            $createdAt = (string)($fact['created_at'] ?? $fact['added_at'] ?? '');
            if ($createdAt !== '') {
                $timestamp = strtotime($createdAt);
                if ($timestamp !== false) {
                    $ageDays = ($now - $timestamp) / 86400;
                    if ($ageDays > $this->maximumMemoryAgeDays) {
                        continue;
                    }
                }
            }

            $kept[] = $fact;
        }

        return $kept;
    }

    private function mergeUniqueFacts(array $a, array $b): array
    {
        $merged = $a;
        $seen = [];

        foreach ($a as $fact) {
            $key = strtolower(trim((string)($fact['content'] ?? $fact['value'] ?? '')));
            if ($key !== '') {
                $seen[$key] = true;
            }
        }

        foreach ($b as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $key = strtolower(trim((string)($fact['content'] ?? $fact['value'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $fact;
        }

        return $merged;
    }

    private function relevanceScore(string $prompt, string $topic, string $fact): float
    {
        $promptWords = $this->keywords($prompt);
        $factWords = $this->keywords($fact);
        $topicWords = $this->keywords(str_replace('_', ' ', $topic));

        if (empty($promptWords) || empty($factWords)) {
            return 0.0;
        }

        $overlap = count(array_intersect($promptWords, $factWords));
        $promptCoverage = $overlap / max(1, count($promptWords));
        $topicMatch = count(array_intersect($topicWords, $factWords)) > 0 ? 0.20 : 0.0;

        return min(1.0, $promptCoverage + $topicMatch);
    }

    private function keywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $words = preg_split('/\s+/', $text) ?: [];

        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'what', 'how', 'why',
            'who', 'when', 'where', 'does', 'are', 'is', 'a', 'an', 'to',
            'of', 'in', 'on', 'about', 'me', 'please', 'can', 'you', 'tell'
        ];

        $words = array_filter($words, static function (string $word) use ($stopWords): bool {
            return strlen($word) >= 3 && !in_array($word, $stopWords, true);
        });

        return array_values(array_unique($words));
    }

    private function formatAnswer(
        string $topic,
        string $intent,
        array $entities,
        array $facts,
        string $questionType
    ): string {
        $factSummary = $this->formatFacts($facts);

        return match ($intent) {
            'define_concept' => "### Definition of {$topic}\n\n{$factSummary}",
            'explain_process' => "### How {$topic} works\n\n{$factSummary}",
            'explain_reason' => "### Why {$topic} happens\n\n{$factSummary}",
            'compare_things' => "### Comparison related to {$topic}\n\n"
                . (empty($entities) ? '' : 'Comparing: ' . implode(' vs ', $entities) . "\n\n")
                . $factSummary,
            'list_information' => "### List of information about {$topic}\n\n{$factSummary}",
            default => "### Information about {$topic}\n\n{$factSummary}",
        };
    }

    private function formatFacts(array $facts): string
    {
        if (empty($facts)) {
            return 'No stored facts yet.';
        }

        $lines = [];
        foreach ($facts as $fact) {
            $value = trim((string)($fact['value'] ?? $fact['content'] ?? $fact['fact'] ?? ''));
            if ($value === '' || ($fact['type'] ?? '') === 'prompt_intent') {
                continue;
            }
            $lines[] = '- ' . $value;
        }

        return empty($lines) ? 'No stored facts yet.' : implode("\n", $lines);
    }
}
