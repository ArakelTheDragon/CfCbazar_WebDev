<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/FactSkill.php';
require_once __DIR__ . '/../core/ConversationStore.php';

/**
 * Central knowledge system — local-first, multi-part and constraint-aware.
 */
class KnowledgeSkill
{
    private int $minimumUsefulFacts = 2;
    private int $maximumMemoryAgeDays = 90;
    private float $minimumVectorScore = 0.40;
    private float $minimumKeywordScore = 0.16;
    private float $minimumCombinedScore = 0.26;
    private int $vectorLimit = 24;
    private int $maxAnswerFacts = 10;

    public function respond(array $analysis, MemoryStore $memory): string
    {
        $prompt = trim((string)($analysis['raw_prompt'] ?? ''));
        $coreQuery = trim((string)($analysis['core_query'] ?? ''));
        if ($coreQuery === '') {
            $coreQuery = $prompt;
        }

        $topic = trim((string)($analysis['topic'] ?? 'general_topic')) ?: 'general_topic';
        $intent = (string)($analysis['intent'] ?? 'ask_information');
        $entities = is_array($analysis['entities'] ?? null) ? $analysis['entities'] : [];
        $questionType = (string)($analysis['question_type'] ?? 'statement');
        $queryEmbedding = is_array($analysis['query_embedding'] ?? null) ? $analysis['query_embedding'] : [];
        $keyPhrases = is_array($analysis['key_phrases'] ?? null) ? $analysis['key_phrases'] : [];
        $constraints = is_array($analysis['constraints'] ?? null) ? $analysis['constraints'] : [];
        $secondaryIntents = is_array($analysis['secondary_intents'] ?? null) ? $analysis['secondary_intents'] : [];
        $subtopics = is_array($analysis['subtopics'] ?? null) ? $analysis['subtopics'] : [];
        $isMultiPart = !empty($analysis['is_multi_part']);

        if ($prompt === '') {
            return 'Please provide a question or topic.';
        }

        $features = $this->loadFeatures();
        $openRouterEnabled = !empty($features['openrouter_enabled']);

        $conversationContext = '';
        if (!empty($features['conversation_enabled'])) {
            $conv = new ConversationStore(
                ConversationStore::resolveSessionId(),
                (int)($features['conversation_max_turns'] ?? 10)
            );
            $relevant = $conv->relevantTurns($queryEmbedding, 3, 0.22);
            if ($relevant !== []) {
                $bits = [];
                foreach ($relevant as $turn) {
                    $ans = (string)($turn['answer'] ?? '');
                    $ans = function_exists('mb_substr') ? mb_substr($ans, 0, 300) : substr($ans, 0, 300);
                    $bits[] = 'User: ' . $turn['prompt'] . "\nAssistant: " . $ans;
                }
                $conversationContext = implode("\n\n", $bits);
            }
        }

        // Search text prioritises core query + key phrases
        $searchText = trim($coreQuery . ' ' . implode(' ', array_slice($keyPhrases, 0, 8)));

        $ranked = $this->retrieveRankedFacts(
            $memory,
            $searchText !== '' ? $searchText : $prompt,
            $topic,
            $queryEmbedding,
            $entities,
            $subtopics,
            $keyPhrases
        );

        $ranked = $this->substantiveFacts($ranked, $prompt);
        $ranked = $this->filterByAge($ranked);
        $ranked = $this->boostByKeyPhrases($ranked, $keyPhrases);
        $ranked = $this->sortByScore($ranked);

        if ($this->memoryIsSufficient($ranked, $intent, $isMultiPart)) {
            $top = array_slice($ranked, 0, $this->maxAnswerFacts);
            return $this->synthesizeAnswer(
                $topic,
                $intent,
                $entities,
                $top,
                $questionType,
                $prompt,
                $constraints,
                $secondaryIntents,
                $isMultiPart,
                $subtopics
            );
        }

        if (empty($openRouterEnabled)) {
            if ($ranked !== []) {
                return $this->synthesizeAnswer(
                    $topic,
                    $intent,
                    $entities,
                    array_slice($ranked, 0, $this->maxAnswerFacts),
                    $questionType,
                    $prompt,
                    $constraints,
                    $secondaryIntents,
                    $isMultiPart,
                    $subtopics
                );
            }
            $msg = 'No solid local knowledge found for this topic yet.';
            if ($conversationContext !== '') {
                $msg .= "\n\n**From recent conversation**\n" . $conversationContext;
            }
            $msg .= "\n\n_(OpenRouter is disabled — set openrouter_enabled to true in config/features.php to fetch new knowledge.)_";
            return $msg;
        }

        $response = $this->requestOpenRouter(
            $prompt,
            $coreQuery,
            $topic,
            $intent,
            $questionType,
            $ranked,
            $conversationContext,
            $constraints,
            $isMultiPart,
            $subtopics
        );

        if (isset($response['error'])) {
            if ($ranked !== []) {
                return $this->synthesizeAnswer(
                    $topic,
                    $intent,
                    $entities,
                    array_slice($ranked, 0, $this->maxAnswerFacts),
                    $questionType,
                    $prompt,
                    $constraints,
                    $secondaryIntents,
                    $isMultiPart,
                    $subtopics
                );
            }
            return 'I could not retrieve knowledge for this topic right now. '
                . (string)($response['message'] ?? '');
        }

        $text = trim((string)($response['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            return 'OpenRouter returned an empty response.';
        }

        $responseFacts = FactSkill::extractFactsFromText($text, 'openrouter', 0.85);
        $responseFacts = $this->substantiveFacts($responseFacts, $prompt);

        $toStore = [];
        foreach ($responseFacts as $f) {
            if (($f['type'] ?? '') !== 'prompt_intent') {
                $toStore[] = $f;
            }
        }
        if ($toStore !== []) {
            $memory->mergeTopicFacts($topic, $toStore);
            // Also store under subtopics when multi-part
            foreach ($subtopics as $sub) {
                $sub = trim((string)$sub);
                if ($sub !== '' && $sub !== $topic) {
                    $memory->mergeTopicFacts($sub, $toStore);
                }
            }
        }

        $final = $this->sortByScore(
            $this->boostByKeyPhrases(
                $this->substantiveFacts(
                    $this->mergeUniqueFacts($ranked, $responseFacts),
                    $prompt
                ),
                $keyPhrases
            )
        );

        if ($final !== []) {
            return $this->synthesizeAnswer(
                $topic,
                $intent,
                $entities,
                array_slice($final, 0, $this->maxAnswerFacts),
                $questionType,
                $prompt,
                $constraints,
                $secondaryIntents,
                $isMultiPart,
                $subtopics
            );
        }

        return $text;
    }

    /**
     * @param list<string> $subtopics
     * @param list<string> $keyPhrases
     * @return list<array>
     */
    private function retrieveRankedFacts(
        MemoryStore $memory,
        string $prompt,
        string $topic,
        array $queryEmbedding,
        array $entities,
        array $subtopics = [],
        array $keyPhrases = []
    ): array {
        $pool = [];
        $topicsToScan = array_values(array_unique(array_filter(
            array_merge([$topic], $subtopics),
            static fn($t) => is_string($t) && $t !== ''
        )));

        if ($queryEmbedding !== []) {
            foreach ($topicsToScan as $t) {
                foreach ($memory->searchByVector(
                    $queryEmbedding,
                    $this->minimumVectorScore,
                    $this->vectorLimit,
                    $t
                ) as $fact) {
                    $pool[] = $this->withScores($fact, $prompt, $t, (float)($fact['_score'] ?? 0.0), $keyPhrases);
                }
            }
            foreach ($memory->searchByVector(
                $queryEmbedding,
                $this->minimumVectorScore,
                $this->vectorLimit,
                null
            ) as $fact) {
                $pool[] = $this->withScores($fact, $prompt, $topic, (float)($fact['_score'] ?? 0.0), $keyPhrases);
            }
        }

        foreach ($topicsToScan as $t) {
            foreach ($this->selectUsefulMemoryFacts($memory->getTopicFacts($t), $prompt, $t) as $fact) {
                $pool[] = $this->withScores($fact, $prompt, $t, 0.0, $keyPhrases);
            }
        }

        foreach ($entities as $entity) {
            $entityTopic = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$entity) ?? '');
            $entityTopic = trim($entityTopic, '_');
            if ($entityTopic === '' || in_array($entityTopic, $topicsToScan, true)) {
                continue;
            }
            foreach ($memory->getTopicFacts($entityTopic) as $fact) {
                $pool[] = $this->withScores($fact, $prompt, $entityTopic, 0.0, $keyPhrases);
            }
        }

        return $this->dedupeKeepBestScore($pool);
    }

    private function withScores(
        array $fact,
        string $prompt,
        string $topic,
        float $vectorScore,
        array $keyPhrases = []
    ): array {
        $value = trim((string)($fact['value'] ?? $fact['content'] ?? $fact['fact'] ?? ''));
        $keyword = $this->relevanceScore($prompt, $topic, $value);

        $phraseBoost = 0.0;
        if ($keyPhrases !== [] && $value !== '') {
            $lv = strtolower($value);
            $hits = 0;
            foreach ($keyPhrases as $ph) {
                $ph = strtolower(trim((string)$ph));
                if ($ph !== '' && str_contains($lv, $ph)) {
                    $hits++;
                }
            }
            $phraseBoost = min(0.20, $hits * 0.04);
        }

        $combined = ($vectorScore > 0.0)
            ? (0.60 * $vectorScore + 0.30 * $keyword + $phraseBoost)
            : min(1.0, $keyword + $phraseBoost);

        $fact['_vector_score'] = $vectorScore;
        $fact['_keyword_score'] = $keyword;
        $fact['_score'] = $combined;

        return $fact;
    }

    private function boostByKeyPhrases(array $facts, array $keyPhrases): array
    {
        if ($keyPhrases === []) {
            return $facts;
        }
        foreach ($facts as &$fact) {
            $value = strtolower(trim((string)($fact['value'] ?? $fact['content'] ?? '')));
            $hits = 0;
            foreach ($keyPhrases as $ph) {
                $ph = strtolower(trim((string)$ph));
                if ($ph !== '' && str_contains($value, $ph)) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $fact['_score'] = min(1.0, (float)($fact['_score'] ?? 0) + min(0.15, $hits * 0.03));
            }
        }
        unset($fact);
        return $facts;
    }

    private function memoryIsSufficient(array $ranked, string $intent, bool $isMultiPart): bool
    {
        if ($ranked === []) {
            return false;
        }

        $strong = array_filter(
            $ranked,
            fn(array $f): bool => (float)($f['_score'] ?? 0) >= $this->minimumCombinedScore
        );

        $need = $this->minimumUsefulFacts;
        if (in_array($intent, ['define_concept', 'explain_process', 'compare_things'], true)) {
            $need = max(2, $this->minimumUsefulFacts);
        }
        if ($isMultiPart) {
            $need = max($need, 3);
        }

        if (count($strong) < $need) {
            return false;
        }

        return (float)($ranked[0]['_score'] ?? 0) >= $this->minimumCombinedScore;
    }

    private function sortByScore(array $facts): array
    {
        usort($facts, static function (array $a, array $b): int {
            return ((float)($b['_score'] ?? 0) <=> (float)($a['_score'] ?? 0));
        });
        return $facts;
    }

    private function dedupeKeepBestScore(array $facts): array
    {
        $best = [];
        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $key = strtolower(trim((string)($fact['content'] ?? $fact['value'] ?? '')));
            if ($key === '') {
                continue;
            }
            if (!isset($best[$key]) || (float)($fact['_score'] ?? 0) > (float)($best[$key]['_score'] ?? 0)) {
                $best[$key] = $fact;
            }
        }
        return array_values($best);
    }

    private function loadFeatures(): array
    {
        $path = __DIR__ . '/../config/features.php';
        if (!is_file($path)) {
            return [
                'openrouter_enabled' => false,
                'conversation_enabled' => true,
                'conversation_max_turns' => 10,
            ];
        }
        $features = require $path;
        return is_array($features) ? $features : [];
    }

    private function requestOpenRouter(
        string $prompt,
        string $coreQuery,
        string $topic,
        string $intent,
        string $questionType,
        array $partialFacts,
        string $conversationContext = '',
        array $constraints = [],
        bool $isMultiPart = false,
        array $subtopics = []
    ): array {
        $config = require __DIR__ . '/../config/openrouter.php';

        $context = '';
        if ($conversationContext !== '') {
            $context .= "Recent conversation:\n" . $conversationContext . "\n\n";
        }
        if ($partialFacts !== []) {
            $bits = [];
            foreach (array_slice($partialFacts, 0, 5) as $f) {
                $v = trim((string)($f['value'] ?? $f['content'] ?? ''));
                if ($v !== '') {
                    $bits[] = '- ' . $v;
                }
            }
            if ($bits !== []) {
                $context .= "Known local facts (use if helpful):\n" . implode("\n", $bits) . "\n\n";
            }
        }

        $style = [];
        if (in_array('simple', $constraints, true) || in_array('beginner', $constraints, true)) {
            $style[] = 'Use simple language for beginners.';
        }
        if (in_array('step_by_step', $constraints, true)) {
            $style[] = 'Use clear numbered steps.';
        }
        if (in_array('brief', $constraints, true)) {
            $style[] = 'Keep the answer brief.';
        }
        if (in_array('detailed', $constraints, true)) {
            $style[] = 'Be detailed and thorough.';
        }
        if (in_array('with_examples', $constraints, true)) {
            $style[] = 'Include concrete examples.';
        }
        if ($isMultiPart) {
            $style[] = 'The user asked multiple things — answer each part under its own short heading.';
        }
        if ($subtopics !== []) {
            $style[] = 'Cover these related areas: ' . implode(', ', $subtopics) . '.';
        }

        $styleText = $style !== [] ? implode(' ', $style) . "\n\n" : '';

        $instruction = $context
            . $styleText
            . "Answer the user's English question accurately and directly. "
            . "Topic focus: {$topic}. Question type: {$questionType}. Intent: {$intent}. "
            . "Write clear factual statements a knowledge base can store. "
            . "Do not use meta phrases like 'it seems you are asking'. "
            . "Prefer concrete definitions, steps, and examples.\n\n"
            . "IMPORTANT: When providing code examples, always wrap the complete code in a single fenced code block using ```language syntax. "
            . "Do not split code into multiple blocks or separate lines with explanations between them.\n\n"
            . "Main ask: {$coreQuery}\n"
            . "Full user message: {$prompt}";

        $payload = [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $instruction],
            ],
            'temperature' => 0.35,
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
            if ($score >= $this->minimumKeywordScore) {
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
        return $this->dedupeKeepBestScore(array_merge($a, $b));
    }

    private function relevanceScore(string $prompt, string $topic, string $fact): float
    {
        $promptWords = $this->keywords($prompt);
        $factWords = $this->keywords($fact);
        $topicWords = $this->keywords(str_replace('_', ' ', $topic));

        if ($promptWords === [] || $factWords === []) {
            return 0.0;
        }

        $overlap = count(array_intersect($promptWords, $factWords));
        $promptCoverage = $overlap / max(1, count($promptWords));
        $topicMatch = count(array_intersect($topicWords, $factWords)) > 0 ? 0.15 : 0.0;

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
            'of', 'in', 'on', 'about', 'me', 'please', 'can', 'you', 'tell',
        ];
        $words = array_filter($words, static function (string $word) use ($stopWords): bool {
            return strlen($word) >= 3 && !in_array($word, $stopWords, true);
        });
        return array_values(array_unique($words));
    }

    private function substantiveFacts(array $facts, string $prompt): array
    {
        $promptNorm = $this->normalizeComparable($prompt);
        $kept = [];

        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            if (($fact['type'] ?? '') === 'prompt_intent') {
                continue;
            }
            $value = trim((string)($fact['value'] ?? $fact['content'] ?? $fact['fact'] ?? ''));
            if ($value === '') {
                continue;
            }
            $len = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($len < 40 || str_word_count($value) < 8) {
                continue;
            }
            if (preg_match(
                '/\b(it seems you are asking|you are asking about|as an ai|i do not have|i don\'t have|cannot provide|no information|not sure what you mean|no stored facts)\b/i',
                $value
            )) {
                continue;
            }
            $factNorm = $this->normalizeComparable($value);
            if ($promptNorm !== '' && $factNorm !== '') {
                if ($factNorm === $promptNorm) {
                    continue;
                }
                similar_text($promptNorm, $factNorm, $pct);
                if ($pct >= 75.0) {
                    continue;
                }
            }
            $kept[] = $fact;
        }
        return $kept;
    }

    private function normalizeComparable(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower(trim($text)) : strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function synthesizeAnswer(
        string $topic,
        string $intent,
        array $entities,
        array $facts,
        string $questionType,
        string $prompt,
        array $constraints = [],
        array $secondaryIntents = [],
        bool $isMultiPart = false,
        array $subtopics = []
    ): string {
        $lines = [];
        foreach ($facts as $fact) {
            $value = trim((string)($fact['value'] ?? $fact['content'] ?? ''));
            if ($value === '' || ($fact['type'] ?? '') === 'prompt_intent') {
                continue;
            }
            if (!preg_match('/[.!?]$/', $value)) {
                $value .= '.';
            }
            $lines[] = $value;
        }

        if ($lines === []) {
            return 'No solid local knowledge found for this question yet.';
        }

        // Brief constraint: fewer facts
        if (in_array('brief', $constraints, true)) {
            $lines = array_slice($lines, 0, 4);
        }

        $label = match ($intent) {
            'define_concept' => 'Definition',
            'explain_process' => 'How to',
            'explain_reason' => 'Why',
            'compare_things' => 'Comparison',
            'list_information' => 'Key points',
            default => 'Answer',
        };

        $readableTopic = str_replace('_', ' ', $topic);
        $header = "### {$label}: {$readableTopic}";

        if ($intent === 'compare_things' && $entities !== []) {
            $header .= "\n\nComparing: " . implode(' vs ', array_slice($entities, 0, 6));
        }

        $stepByStep = in_array('step_by_step', $constraints, true)
            || $intent === 'explain_process';

        // Multi-part: try to group lines under subtopic-ish headings when possible
        if ($isMultiPart && count($lines) >= 3) {
            $mid = (int)ceil(count($lines) / 2);
            $part1 = array_slice($lines, 0, $mid);
            $part2 = array_slice($lines, $mid);
            $body = "**Part 1**\n" . $this->formatLines($part1, $stepByStep);
            if ($part2 !== []) {
                $body .= "\n\n**Part 2**\n" . $this->formatLines($part2, $stepByStep);
            }
            if (in_array('with_examples', $constraints, true)
                || in_array('list_information', $secondaryIntents, true)) {
                // already in fact text usually
            }
            return $header . "\n\n" . trim($body);
        }

        if ($intent === 'define_concept' && count($lines) > 0) {
            $lead = array_shift($lines);
            $body = $lead;
            if ($lines !== []) {
                $body .= "\n\n**Related facts**\n" . $this->formatLines($lines, false);
            }
            return $header . "\n\n" . trim($body);
        }

        return $header . "\n\n" . $this->formatLines($lines, $stepByStep);
    }

    /**
     * @param list<string> $lines
     */
    private function formatLines(array $lines, bool $numbered): string
    {
        $out = '';
        $i = 1;
        
        // Detect if this is code content
        $isCode = false;
        $codeLanguage = '';
        
        foreach ($lines as $line) {
            // Check for code patterns
            if (preg_match('/^```/', $line)) {
                $isCode = true;
                $codeLanguage = trim(str_replace('```', '', $line));
                $out .= $line . "\n";
                continue;
            }
            
            if ($isCode && preg_match('/^```/', $line)) {
                $isCode = false;
                $out .= $line . "\n";
                continue;
            }
            
            if ($isCode) {
                // Keep code lines together without formatting
                $out .= $line . "\n";
            } else {
                // Regular text formatting
                if ($numbered) {
                    $out .= $i . '. ' . $line . "\n";
                    $i++;
                } else {
                    $out .= '- ' . $line . "\n";
                }
            }
        }
        
        return trim($out);
    }
}
