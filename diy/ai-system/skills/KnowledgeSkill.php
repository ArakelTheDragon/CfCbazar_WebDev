<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/FactSkill.php';
require_once __DIR__ . '/../core/ConversationStore.php';
require_once __DIR__ . '/../core/SkillData.php';

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


    /**
     * Preferred entry (Phase 3): fill SkillData in place.
     * Writes memory_hits, memory_sufficient, openrouter, facts merge, draft_answer.
     */
    public function process(SkillData $data, MemoryStore $memory): SkillData
    {
        $data->setSkill('knowledge');

        $prompt = $data->prompt();
        if ($prompt === '') {
            $data->set('draft_answer', 'Please provide a question or topic.');
            return $data;
        }

        $features = $this->loadFeatures();
        $openRouterEnabled = !empty($features['openrouter_enabled']);
        $mode = strtolower((string)($features['knowledge_mode'] ?? 'hybrid'));
        if ($mode !== 'local' && $mode !== 'hybrid') {
            $mode = 'hybrid';
        }

        $coreQuery = trim((string)$data->get('core_query', ''));
        if ($coreQuery === '') {
            $coreQuery = $prompt;
        }
        $topic = $data->topic();
        $intent = (string)$data->get('intent', 'ask_information');
        $entities = is_array($data->get('entities')) ? $data->get('entities') : [];
        $questionType = (string)$data->get('question_type', 'statement');
        $queryEmbedding = is_array($data->get('query_embedding')) ? $data->get('query_embedding') : [];
        $keyPhrases = is_array($data->get('key_phrases')) ? $data->get('key_phrases') : [];
        $constraints = is_array($data->get('constraints')) ? $data->get('constraints') : [];
        $secondaryIntents = is_array($data->get('secondary_intents')) ? $data->get('secondary_intents') : [];
        $subtopics = is_array($data->get('subtopics')) ? $data->get('subtopics') : [];
        $isMultiPart = (bool)$data->get('is_multi_part', false);

        // Conversation context
        $conversation = [];
        $conversationContext = '';
        if (!empty($features['conversation_enabled'])) {
            $sessionId = (string)$data->get('session_id', '');
            if ($sessionId === '') {
                $sessionId = ConversationStore::resolveSessionId();
                $data->set('session_id', $sessionId);
            }
            $conv = new ConversationStore(
                $sessionId,
                (int)($features['conversation_max_turns'] ?? 10)
            );
            $relevant = $conv->relevantTurns($queryEmbedding, 3, 0.22);
            $conversation = $relevant;
            if ($relevant !== []) {
                $bits = [];
                foreach ($relevant as $turn) {
                    $ans = (string)($turn['answer'] ?? '');
                    $ans = function_exists('mb_substr') ? mb_substr($ans, 0, 300) : substr($ans, 0, 300);
                    $bits[] = 'User: ' . ($turn['prompt'] ?? '') . "\nAssistant: " . $ans;
                }
                $conversationContext = implode("\n\n", $bits);
            }
        }
        $data->set('conversation', $conversation);


        // --- Simple local page templates only (not advanced) ---
        $localCode = $this->localCodeTemplate($prompt, $topic, $intent, $constraints);
        if ($localCode !== null) {
            $data->set('openrouter', [
                'enabled'  => $openRouterEnabled,
                'mode'     => $mode,
                'gap_fill' => false,
                'raw'      => null,
            ]);
            $data->set('memory_sufficient', true);
            $data->set('draft_answer', $localCode);
            return $data;
        }

        // --- Main decision loop ---
        // 1) Score local memory
        // 2) If enough → answer from local facts
        // 3) Else OpenRouter → store facts into memory → goto 1 (max 2 OR calls)
        $searchText = trim($coreQuery . ' ' . implode(' ', array_slice($keyPhrases, 0, 8)));
        if ($searchText === '') {
            $searchText = $prompt;
        }

        $canCallOpenRouter = $openRouterEnabled && $mode === 'hybrid';
        $maxOpenRouterCalls = 2;
        $openRouterCalls = 0;
        $openRouterRaw = null;
        $gapFill = false;
        $draft = '';
        $ranked = [];
        $selected = [];

        while (true) {
            $ranked = $this->retrieveRankedFacts(
                $memory,
                $searchText,
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
            $selected = $this->selectFactsForAnswer($ranked);

            $hits = [];
            foreach ($selected as $f) {
                if (is_array($f)) {
                    $hits[] = SkillData::normalizeFact($f);
                }
            }
            $data->set('memory_hits', $hits);

            $sufficient = $this->memoryIsSufficient($ranked, $intent, $isMultiPart);

            // Code-gen (non-simple): only "enough" when we have a stored code document
            if ($this->isCodeGenerationRequest($prompt, $intent)
                && !$this->isSimpleCodeRequest($prompt, $constraints)
            ) {
                $hasCode = false;
                foreach ($ranked as $f) {
                    if (is_array($f) && ($f['type'] ?? '') === 'code_html') {
                        $hasCode = true;
                        break;
                    }
                }
                $sufficient = $hasCode;
            }

            $data->set('memory_sufficient', $sufficient);

            if ($sufficient) {
                $draft = $this->answerFromLocalFacts(
                    $selected,
                    $ranked,
                    $prompt,
                    $topic,
                    $intent,
                    $entities,
                    $questionType,
                    $constraints,
                    $secondaryIntents,
                    $isMultiPart,
                    $subtopics
                );
                break;
            }

            // Not enough local knowledge — try OpenRouter (limited retries)
            if (!$canCallOpenRouter || $openRouterCalls >= $maxOpenRouterCalls) {
                if ($selected !== []) {
                    $draft = $this->answerFromLocalFacts(
                        $selected,
                        $ranked,
                        $prompt,
                        $topic,
                        $intent,
                        $entities,
                        $questionType,
                        $constraints,
                        $secondaryIntents,
                        $isMultiPart,
                        $subtopics
                    );
                } else {
                    $draft = 'No solid local knowledge found for this topic yet.';
                    if ($conversationContext !== '') {
                        $draft .= "\n\n**From recent conversation**\n" . $conversationContext;
                    }
                    if (!$openRouterEnabled) {
                        $draft .= "\n\n_(OpenRouter is disabled — set openrouter_enabled to true in config/features.php.)_";
                    } elseif ($mode === 'local') {
                        $draft .= "\n\n_(knowledge_mode is local — set knowledge_mode to hybrid to fill gaps via OpenRouter.)_";
                    } elseif ($openRouterCalls > 0) {
                        $draft .= "\n\n_(OpenRouter was called but local memory still lacks solid facts.)_";
                    }
                }
                break;
            }

            $openRouterCalls++;
            $gapFill = $ranked !== [];
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
                $subtopics,
                $gapFill
            );

            if (isset($response['error'])) {
                if ($selected !== []) {
                    $draft = $this->answerFromLocalFacts(
                        $selected,
                        $ranked,
                        $prompt,
                        $topic,
                        $intent,
                        $entities,
                        $questionType,
                        $constraints,
                        $secondaryIntents,
                        $isMultiPart,
                        $subtopics
                    );
                } else {
                    $draft = 'I could not retrieve knowledge for this topic right now. '
                        . (string)($response['message'] ?? '');
                }
                break;
            }

            $text = trim((string)($response['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                if ($selected !== []) {
                    $draft = $this->answerFromLocalFacts(
                        $selected,
                        $ranked,
                        $prompt,
                        $topic,
                        $intent,
                        $entities,
                        $questionType,
                        $constraints,
                        $secondaryIntents,
                        $isMultiPart,
                        $subtopics
                    );
                } else {
                    $draft = 'OpenRouter returned an empty response.';
                }
                break;
            }

            $openRouterRaw = $text;

            // Write OpenRouter output into local memory, then loop and re-check
            $toStore = [];
            if ($this->isCodeGenerationRequest($prompt, $intent)) {
                $htmlDoc = $this->extractHtmlDocument($text);
                if ($htmlDoc !== null) {
                    $toStore[] = SkillData::normalizeFact([
                        'content' => $htmlDoc,
                        'type' => 'code_html',
                        'source' => 'openrouter',
                        'confidence' => 0.92,
                        'embedding' => FactSkill::embed(
                            function_exists('mb_substr') ? mb_substr($htmlDoc, 0, 500) : substr($htmlDoc, 0, 500)
                        ),
                        'created_at' => gmdate('c'),
                    ]);
                }
            }

            $responseFacts = FactSkill::extractFactsFromText($text, 'openrouter', 0.85);
            $responseFacts = $this->substantiveFacts($responseFacts, $prompt);
            foreach ($responseFacts as $f) {
                if (!is_array($f) || ($f['type'] ?? '') === 'prompt_intent') {
                    continue;
                }
                $toStore[] = SkillData::normalizeFact($f);
            }

            if ($toStore !== []) {
                $memory->mergeTopicFacts($topic, $toStore);
                foreach ($subtopics as $sub) {
                    $sub = trim((string)$sub);
                    if ($sub !== '' && $sub !== $topic) {
                        $memory->mergeTopicFacts($sub, $toStore);
                    }
                }
                $data->mergeFacts($toStore);
            }

            // Loop: score local memory again (now richer), maybe second OR call
        }

        $data->set('openrouter', [
            'enabled'  => $openRouterEnabled,
            'mode'     => $mode,
            'gap_fill' => $gapFill,
            'raw'      => $openRouterRaw,
            'calls'    => $openRouterCalls,
        ]);
        $data->set('draft_answer', $draft);

        return $data;
    }

    /**
     * Build user-facing answer from ranked local facts only.
     *
     * @param list<array<string, mixed>> $selected
     * @param list<array<string, mixed>> $ranked
     * @param list<string> $constraints
     * @param list<string> $secondaryIntents
     * @param list<string> $subtopics
     */
    private function answerFromLocalFacts(
        array $selected,
        array $ranked,
        string $prompt,
        string $topic,
        string $intent,
        array $entities,
        string $questionType,
        array $constraints,
        array $secondaryIntents,
        bool $isMultiPart,
        array $subtopics
    ): string {
        // Prefer stored code document for generation requests
        if ($this->isCodeGenerationRequest($prompt, $intent)) {
            foreach (array_merge($selected, $ranked) as $f) {
                if (!is_array($f) || ($f['type'] ?? '') !== 'code_html') {
                    continue;
                }
                $doc = trim((string)($f['content'] ?? $f['value'] ?? ''));
                if ($doc === '' || !preg_match('/<!DOCTYPE\s+html|<html\b/i', $doc)) {
                    continue;
                }
                $label = $this->isSimpleCodeRequest($prompt, $constraints) ? 'simple' : 'advanced';
                $ext = (preg_match('/\bphp\b/i', $prompt) || $topic === 'php_page') ? 'php' : 'html';
                $fence = $ext === 'php' ? 'php' : 'html';
                $filename = $ext === 'php' ? 'index.php' : 'index.html';
                return "Here is an {$label} {$ext} page you can save as `{$filename}`.\n\n```{$fence}\n{$doc}\n```";
            }
        }

        $use = $selected !== [] ? $selected : $this->selectFactsForAnswer($ranked);
        if ($use === []) {
            return 'No solid local knowledge found for this topic yet.';
        }

        return $this->synthesizeAnswer(
            $topic,
            $intent,
            $entities,
            $use,
            $questionType,
            $prompt,
            $constraints,
            $secondaryIntents,
            $isMultiPart,
            $subtopics
        );
    }


    /**
     * Legacy array API — delegates to process(SkillData).
     */
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

        $mode = strtolower((string)($features['knowledge_mode'] ?? 'hybrid'));
        if ($mode !== 'local' && $mode !== 'hybrid') {
            $mode = 'hybrid';
        }

        // Fully covered by local memory → never call OpenRouter
        if ($this->memoryIsSufficient($ranked, $intent, $isMultiPart)) {
            $top = $this->selectFactsForAnswer($ranked);
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

        $canUseOpenRouter = $openRouterEnabled && $mode === 'hybrid';

        if (!$canUseOpenRouter) {
            if ($ranked !== []) {
                return $this->synthesizeAnswer(
                    $topic,
                    $intent,
                    $entities,
                    $this->selectFactsForAnswer($ranked),
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
                $msg .= "

**From recent conversation**
" . $conversationContext;
            }
            if (!$openRouterEnabled) {
                $msg .= "

_(OpenRouter is disabled — set openrouter_enabled to true in config/features.php.)_";
            } elseif ($mode === 'local') {
                $msg .= "

_(knowledge_mode is local — set knowledge_mode to hybrid to fill gaps via OpenRouter.)_";
            }
            return $msg;
        }

        // Hybrid: local facts are the base; OpenRouter fills gaps only
        $gapFill = $ranked !== [];
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
            $subtopics,
            $gapFill
        );

        if (isset($response['error'])) {
            if ($ranked !== []) {
                return $this->synthesizeAnswer(
                    $topic,
                    $intent,
                    $entities,
                    $this->selectFactsForAnswer($ranked),
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
            if ($ranked !== []) {
                return $this->synthesizeAnswer(
                    $topic,
                    $intent,
                    $entities,
                    $this->selectFactsForAnswer($ranked),
                    $questionType,
                    $prompt,
                    $constraints,
                    $secondaryIntents,
                    $isMultiPart,
                    $subtopics
                );
            }
            return 'OpenRouter returned an empty response.';
        }

        // Code/HTML generation: prefer one complete fenced document for the user
        if ($this->looksLikeCodeRequest($prompt) && $this->extractHtmlDocument($text) !== null) {
            $doc = $this->extractHtmlDocument($text);
            $responseFacts = FactSkill::extractFactsFromText($text, 'openrouter', 0.85);
            $responseFacts = $this->substantiveFacts($responseFacts, $prompt);
            $toStore = [];
            foreach ($responseFacts as $f) {
                if (($f['type'] ?? '') !== 'prompt_intent') {
                    $toStore[] = $f;
                }
            }
            // Also store the full page as one fact for reuse
            $toStore[] = [
                'content' => $doc,
                'value' => $doc,
                'type' => 'code_html',
                'source' => 'openrouter',
                'confidence' => 0.9,
                'embedding' => FactSkill::embed((function_exists('mb_substr') ? mb_substr($doc, 0, 500) : substr($doc, 0, 500))),
                'created_at' => gmdate('c'),
            ];
            if ($toStore !== []) {
                $memory->mergeTopicFacts($topic, $toStore);
            }
            return "Here is a simple HTML page you can save as index.html.

```html
" . $doc . "
```";
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
                $this->selectFactsForAnswer($final),
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

        // FactSkill scoring (type + keywords + vector when available)
        $queryKeywords = $keyPhrases !== []
            ? array_map(static fn($p) => strtolower(trim((string)$p)), $keyPhrases)
            : FactSkill::extractKeywords($prompt);
        $fs = FactSkill::scoreFactAgainstQuery($fact, $prompt, [], $queryKeywords);
        if ($vectorScore <= 0.0 && $fs['vector'] > 0.0) {
            $vectorScore = $fs['vector'];
        }
        $keyword = max($keyword, $fs['keyword']);

        $combined = ($vectorScore > 0.0)
            ? (0.55 * $vectorScore + 0.28 * $keyword + $phraseBoost + 0.12 * $fs['combined'])
            : min(1.0, $keyword + $phraseBoost + 0.15 * $fs['combined']);

        $fact['_vector_score'] = $vectorScore;
        $fact['_keyword_score'] = $keyword;
        $fact['_score'] = min(1.0, $combined);

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
        usort($facts, function (array $a, array $b): int {
            $scoreCmp = ((float)($b['_score'] ?? 0) <=> (float)($a['_score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            // Tie-break: confidence, source preference, recency
            $confCmp = ((float)($b['confidence'] ?? 0) <=> (float)($a['confidence'] ?? 0));
            if ($confCmp !== 0) {
                return $confCmp;
            }
            $srcCmp = $this->sourceRank((string)($b['source'] ?? '')) <=> $this->sourceRank((string)($a['source'] ?? ''));
            if ($srcCmp !== 0) {
                return $srcCmp;
            }
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        return $facts;
    }

    /**
     * Prefer curated seeds over noisy chat extracts when scores tie.
     */
    private function sourceRank(string $source): int
    {
        $source = strtolower(trim($source));
        return match (true) {
            in_array($source, ['hygiene_seed', 'gui_seed', 'seed'], true) => 5,
            $source === 'memory' => 4,
            $source === 'openrouter' => 3,
            $source === 'prompt' => 1,
            default => 2,
        };
    }

    /**
     * Decision flow: keep facts at/above score threshold, then cap at max N.
     * N is a ceiling, not a target — fewer strong facts is fine.
     *
     * @param list<array<string, mixed>> $ranked
     * @return list<array<string, mixed>>
     */
    private function selectFactsForAnswer(array $ranked): array
    {
        if ($ranked === []) {
            return [];
        }

        $ranked = $this->sortByScore($ranked);
        $selected = [];

        foreach ($ranked as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $score = (float)($fact['_score'] ?? 0);
            $vector = (float)($fact['_vector_score'] ?? 0);

            // Pass if combined score clears bar OR strong pure vector hit
            if ($score < $this->minimumCombinedScore && $vector < $this->minimumVectorScore) {
                continue;
            }

            $selected[] = $fact;
            if (count($selected) >= $this->maxAnswerFacts) {
                break;
            }
        }

        return $selected;
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
                'knowledge_mode' => 'hybrid',
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
        array $subtopics = [],
        bool $gapFill = false
    ): array {
        try {
            $config = require __DIR__ . '/../config/openrouter.php';
        } catch (Throwable $e) {
            return Helpers::error('OpenRouter config error: ' . $e->getMessage());
        }

        if (!is_array($config) || empty($config['api_key'])) {
            return Helpers::error('OpenRouter API key is missing or invalid.');
        }

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
                $context .= "Trusted local facts (keep these; do not contradict):\n" . implode("\n", $bits) . "\n\n";
            }
        }

        $style = [];
        if ($gapFill) {
            $style[] = 'Fill only gaps missing from the local facts. Do not rewrite what is already covered. Add new storeable factual statements.';
        } else {
            $style[] = 'Provide a complete factual answer using concrete storeable statements.';
        }
        if (preg_match('/\b(html\s*page|simple\s+html|make\s+me\s+a\s+.*html|webpage|web\s*page)\b/i', $prompt)) {
            $style[] = 'Return ONE complete HTML5 document inside a single markdown fenced block marked html. Do not explain tags line by line. Put a one-sentence intro before the fence only.';
        }
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
            . "Main ask: {$coreQuery}\n"
            . "Full user message: {$prompt}";

        $payload = [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $instruction],
            ],
            'temperature' => 0.35,
        ];

        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return Helpers::error('OpenRouter API key is empty. Check /config.php ($API_openrouter) or config/openrouter.local.php.');
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
        if (!empty($config['referer'])) {
            $headers[] = 'HTTP-Referer: ' . $config['referer'];
            $headers[] = 'Referer: ' . $config['referer'];
        }
        if (!empty($config['title'])) {
            $headers[] = 'X-Title: ' . $config['title'];
        }

        return Helpers::httpPostJson(
            $config['base_url'],
            $headers,
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

    private function looksLikeCodeRequest(string $prompt): bool
    {
        $p = strtolower($prompt);
        return (bool)preg_match(
            '/\b(?:make|create|write|generate|build)\b[\s\S]{0,40}\b(?:simple\s+)?(?:php|html|css)\b|\b(?:php|html)\s+page\b|\bsimple\s+(?:php|html)\b|<!doctype/i',
            $p
        );
    }

    /**
     * Local templates when user asks to generate a page (no OpenRouter required).
     */


    private function isCodeGenerationRequest(string $prompt, string $intent): bool
    {
        return $intent === 'generate_code' || $this->looksLikeCodeRequest($prompt);
    }

    /**
     * Local templates only for clearly simple requests.
     * "Advanced", "complex", "responsive app", etc. must not use the stub page.
     */
    private function isSimpleCodeRequest(string $prompt, array $constraints = []): bool
    {
        $p = strtolower($prompt);
        foreach ($constraints as $c) {
            $c = strtolower(trim((string)$c));
            if (in_array($c, ['advanced', 'complex', 'detailed', 'full', 'professional', 'responsive'], true)) {
                return false;
            }
        }
        if (preg_match('/\b(?:advanced|complex|detailed|elaborate|professional|responsive|animated|dashboard|multi-?page|full-?featured|with\s+(?:js|javascript|css\s+grid|flexbox|nav(?:igation)?|menu|form|login))\b/i', $p)) {
            return false;
        }
        // Explicit simple, or bare "html page" / "php page" without advanced markers
        if (preg_match('/\bsimple\b/i', $p)) {
            return true;
        }
        // Minimal: "make me an html page" without extra requirements → simple OK
        if (preg_match('/\b(?:make|create|write|generate|build)\b[\s\S]{0,40}\b(?:a|an|the)?\s*(?:html|php)\s+page\b/i', $p)
            && !preg_match('/\b(?:advanced|complex|detailed)\b/i', $p)) {
            return true;
        }
        return false;
    }

    /**
     * Local templates when user asks for a *simple* page (no OpenRouter required).
     *
     * @param list<string> $constraints
     */
    private function localCodeTemplate(string $prompt, string $topic, string $intent, array $constraints = []): ?string
    {
        if (!$this->isCodeGenerationRequest($prompt, $intent)) {
            return null;
        }
        // Advanced / non-simple → let hybrid OpenRouter handle it
        if (!$this->isSimpleCodeRequest($prompt, $constraints)) {
            return null;
        }

        $p = strtolower($prompt);
        $wantPhp = (bool)preg_match('/\bphp\b/', $p) || $topic === 'php_page';
        $wantHtml = (bool)preg_match('/\bhtml\b|\bweb\s*page\b|\bwebpage\b/', $p) || $topic === 'html_page';

        if ($wantPhp) {
            $code = <<<'PHP'
<?php
declare(strict_types=1);
$title = 'My PHP Page';
$message = 'Hello from PHP!';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
        h1 { color: #222; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
PHP;
            return "Here is a simple PHP page you can save as `index.php`.\n\n```php\n" . trim($code) . "\n```";
        }

        if ($wantHtml || $this->looksLikeCodeRequest($prompt)) {
            $code = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simple Page</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
        h1 { color: #222; }
    </style>
</head>
<body>
    <h1>Hello</h1>
    <p>This is a simple HTML page.</p>
</body>
</html>
HTML;
            return "Here is a simple HTML page you can save as `index.html`.\n\n```html\n" . trim($code) . "\n```";
        }

        return null;
    }


    private function extractHtmlDocument(string $text): ?string
    {
        if (preg_match('/(<!DOCTYPE\s+html[\s\S]*?<\/html>)/i', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(<html[\s\S]*?<\/html>)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
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
        // Prefer a stored full HTML document when user asked for a page
        if ($this->looksLikeCodeRequest($prompt)) {
            foreach ($facts as $fact) {
                $value = trim((string)($fact['value'] ?? $fact['content'] ?? ''));
                if (($fact['type'] ?? '') === 'code_html' || $this->extractHtmlDocument($value) !== null) {
                    $doc = $this->extractHtmlDocument($value) ?? $value;
                    return "Here is a simple HTML page you can save as index.html.

```html
" . $doc . "
```";
                }
            }
        }

        $lines = [];
        foreach ($facts as $fact) {
            $value = trim((string)($fact['value'] ?? $fact['content'] ?? ''));
            if ($value === '' || ($fact['type'] ?? '') === 'prompt_intent') {
                continue;
            }
            if (($fact['type'] ?? '') === 'code_html') {
                return "Here is a simple HTML page you can save as index.html.

```html
" . $value . "
```";
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
        foreach ($lines as $line) {
            if ($numbered) {
                $out .= $i . '. ' . $line . "\n";
                $i++;
            } else {
                $out .= '- ' . $line . "\n";
            }
        }
        return trim($out);
    }
}
