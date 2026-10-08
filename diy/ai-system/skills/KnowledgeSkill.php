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

    // Feature flags (loaded from config)
    private bool $adaptiveThresholdsEnabled = false;
    private bool $weightedSufficiencyEnabled = false;
    private bool $typeDiversityEnabled = false;
    private bool $intentSynthesisPriorityEnabled = false;


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

        // Load feature flags for new enhancements
        $this->adaptiveThresholdsEnabled = !empty($features['adaptive_thresholds_enabled']);
        $this->weightedSufficiencyEnabled = !empty($features['weighted_sufficiency_enabled']);
        $this->typeDiversityEnabled = !empty($features['type_diversity_enabled']);
        $this->intentSynthesisPriorityEnabled = !empty($features['intent_synthesis_priority_enabled']);

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
        $specifics = is_array($data->get('specific_requirements')) ? $data->get('specific_requirements') : [];
        $hasSpecifics = (bool)$data->get('has_specifics', false) || $specifics !== [];

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


        // --- Main decision loop (memory-first) ---
        // 1) Search local memory with this prompt embedding + specifics
        // 2) If confidence enough → answer from matched facts
        // 3) Else OpenRouter → store → re-check local memory
        // 4) Local simple templates only as last-resort fallback
        // --- Main decision loop ---

        // 1) Score local memory
        // 2) If enough → answer from local facts
        // 3) Else OpenRouter → store facts into memory → goto 1 (max 2 OR calls)
        if ($specifics !== []) {
            $keyPhrases = array_values(array_unique(array_merge($keyPhrases, $specifics)));
        }
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
            $selected = $this->selectFactsForAnswer($ranked, $intent, $prompt, $memory);

            $hits = [];
            foreach ($selected as $f) {
                if (is_array($f)) {
                    $hits[] = SkillData::normalizeFact($f);
                }
            }
            $data->set('memory_hits', $hits);

            $confidence = $this->computeAnswerConfidence(
                $ranked,
                $selected,
                $prompt,
                $intent,
                $specifics,
                $isMultiPart
            );
            $data->set('answer_confidence', $confidence);
            $sufficient = $this->memoryIsSufficient(
                $ranked,
                $intent,
                $isMultiPart,
                $memory,
                $specifics,
                $confidence
            );

            // Code-gen: enough only with a code_html fact that matches this prompt's specifics
            if ($this->isCodeGenerationRequest($prompt, $intent)) {
                $hasCode = false;
                foreach ($ranked as $f) {
                    if (!is_array($f) || ($f['type'] ?? '') !== 'code_html') {
                        continue;
                    }
                    $content = (string)($f['content'] ?? $f['value'] ?? '');
                    if ($content === '') {
                        continue;
                    }
                    if ($hasSpecifics && $specifics !== []) {
                        $blob = strtolower($content);
                        $ok = false;
                        foreach ($specifics as $sp) {
                            $sp = strtolower(trim((string)$sp));
                            if (strlen($sp) >= 3 && str_contains($blob, $sp)) {
                                $ok = true;
                                break;
                            }
                        }
                        if (!$ok) {
                            continue;
                        }
                    }
                    $hasCode = true;
                    break;
                }
                if ($hasSpecifics || !$this->isSimpleCodeRequest($prompt, $constraints, $specifics)) {
                    $sufficient = $hasCode;
                } elseif ($hasCode) {
                    $sufficient = true;
                }
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
                    $localCode = $this->localCodeTemplate($prompt, $topic, $intent, $constraints, $specifics);
                    if ($localCode !== null) {
                        $draft = $localCode;
                    } else {
                        $draft = 'No solid local knowledge found for this topic yet.';
                        if ($conversationContext !== '') {
                            $draft .= "

**From recent conversation**\n" . $conversationContext;
                        }
                        if (!$openRouterEnabled) {
                            $draft .= "

_(OpenRouter is disabled — set openrouter_enabled to true in config/features.php.)_";
                        } elseif ($mode === 'local') {
                            $draft .= "

_(knowledge_mode is local — set knowledge_mode to hybrid to fill gaps via OpenRouter.)_";
                        } elseif ($openRouterCalls > 0) {
                            $draft .= "

_(OpenRouter was called but local memory still lacks solid facts.)_";
                        }
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

            // Write OpenRouter output into local memory (full Fact shape + topic meta), then re-check
            $toStore = $this->buildFactsFromOpenRouterText(
                $text,
                $prompt,
                $intent,
                $keyPhrases,
                $specifics
            );
            if ($toStore !== []) {
                $this->storeFactsWithMeta(
                    $memory,
                    $topic,
                    $toStore,
                    $prompt,
                    $subtopics,
                    $keyPhrases,
                    $specifics,
                    $entities
                );
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

        $use = $selected !== [] ? $selected : $this->selectFactsForAnswer($ranked, $intent, $prompt, null);
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
        if ($this->memoryIsSufficient($ranked, $intent, $isMultiPart, $memory)) {
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

        // Related topics via description / aliases / tags embeddings
        try {
            $related = $memory->findRelatedTopics($queryEmbedding, $prompt, 4, 0.16);
            foreach ($related as $rel) {
                $rel = trim((string)$rel);
                if ($rel !== '' && !in_array($rel, $topicsToScan, true)) {
                    $topicsToScan[] = $rel;
                }
            }
        } catch (Throwable $e) {
            // ignore — fall back to primary topic only
        }

        // Topic meta subtopics (from memory envelope)
        try {
            $meta = $memory->getTopicMeta($topic);
            foreach (($meta['subtopics'] ?? []) as $st) {
                $st = trim((string)$st);
                if ($st !== '' && !in_array($st, $topicsToScan, true)) {
                    $topicsToScan[] = $st;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        if ($queryEmbedding !== []) {
            // Use adaptive thresholds for vector search
            $thresholds = $this->getAdaptiveThresholds($topic, $memory);

            foreach ($topicsToScan as $t) {
                foreach ($memory->searchByVector(
                    $queryEmbedding,
                    $thresholds['vector'],
                    $this->vectorLimit,
                    $t
                ) as $fact) {
                    $pool[] = $this->withScores($fact, $prompt, $t, (float)($fact['_score'] ?? 0.0), $keyPhrases);
                }
            }
            foreach ($memory->searchByVector(
                $queryEmbedding,
                $thresholds['vector'],
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

        // Soft recency boost for time-sensitive queries
        $recencyBoost = $this->getRecencyBoost($fact, $prompt);

        $combined = ($vectorScore > 0.0)
            ? (0.55 * $vectorScore + 0.28 * $keyword + $phraseBoost + 0.12 * $fs['combined'] + $recencyBoost)
            : min(1.0, $keyword + $phraseBoost + 0.15 * $fs['combined'] + $recencyBoost);

        $fact['_vector_score'] = $vectorScore;
        $fact['_keyword_score'] = $keyword;
        $fact['_score'] = min(1.0, $combined);

        return $fact;
    }

    /**
     * Get soft recency boost for time-sensitive queries.
     * Only applies when query contains time-sensitive keywords.
     *
     * @param array<string, mixed> $fact
     * @return float
     */
    private function getRecencyBoost(array $fact, string $prompt): float
    {
        $createdAt = (string)($fact['created_at'] ?? $fact['added_at'] ?? '');
        if ($createdAt === '') {
            return 0.0;
        }

        // Check if query is time-sensitive
        $p = strtolower($prompt);
        $timeSensitive = (bool)preg_match(
            '/\b(?:current|latest|recent|new|version|today|now|this year|this month)\b/i',
            $p
        );

        if (!$timeSensitive) {
            return 0.0;
        }

        $ageDays = (time() - strtotime($createdAt)) / 86400;

        // Boost for recent facts (last 7 days)
        if ($ageDays < 7) {
            return 0.05;
        }

        // Small boost for facts under 30 days
        if ($ageDays < 30) {
            return 0.02;
        }

        return 0.0;
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

    /**
     * Get adaptive thresholds based on topic quality and size.
     * Low-fact or low-quality topics get more lenient thresholds.
     *
     * @return array{vector: float, combined: float}
     */
    private function getAdaptiveThresholds(string $topic, MemoryStore $memory): array
    {
        // Feature flag: if disabled, return defaults
        if (!$this->adaptiveThresholdsEnabled) {
            return [
                'vector' => $this->minimumVectorScore,
                'combined' => $this->minimumCombinedScore,
            ];
        }

        try {
            $meta = $memory->getTopicMeta($topic);
            $avgScore = (float)($meta['avg_score'] ?? 0.50);
            $factCount = (int)($meta['fact_count'] ?? 0);
        } catch (Throwable $e) {
            // Fall back to defaults if meta unavailable
            return [
                'vector' => $this->minimumVectorScore,
                'combined' => $this->minimumCombinedScore,
            ];
        }

        // Higher quality topics = stricter thresholds
        $vectorMin = $avgScore > 0.60 ? 0.45 : 0.35;
        $combinedMin = $avgScore > 0.60 ? 0.30 : 0.22;

        // Low-fact topics = more lenient (need more permissive thresholds to avoid OpenRouter overuse)
        if ($factCount < 5) {
            $vectorMin *= 0.85;
            $combinedMin *= 0.85;
        }

        return [
            'vector' => max(0.25, $vectorMin),
            'combined' => max(0.18, $combinedMin),
        ];
    }

    /**
     * Weighted sufficiency check using score × confidence × source weights.
     * More accurate than simple count-based checks.
     *
     * @param list<array<string, mixed>> $ranked
     * @param MemoryStore|null $memory MemoryStore instance for adaptive thresholds
     */
    private function memoryIsSufficientWeighted(array $ranked, string $intent, bool $isMultiPart, ?MemoryStore $memory = null): bool
    {
        if ($ranked === []) {
            return false;
        }

        // Feature flag: if disabled, use simple count-based check
        if (!$this->weightedSufficiencyEnabled) {
            return $this->memoryIsSufficientSimple($ranked, $intent, $isMultiPart, $memory);
        }

        // Calculate weighted quality score from top 5 facts
        $qualitySum = 0.0;
        $weightSum = 0.0;

        foreach (array_slice($ranked, 0, 5) as $fact) {
            $score = (float)($fact['_score'] ?? 0);
            $confidence = (float)($fact['confidence'] ?? 0.5);
            $sourceWeight = $this->sourceRank((string)($fact['source'] ?? '')) / 5.0;

            $qualitySum += $score * $confidence * $sourceWeight;
            $weightSum += $sourceWeight;
        }

        $avgQuality = $weightSum > 0 ? $qualitySum / $weightSum : 0;

        // Intent-specific thresholds
        $threshold = match($intent) {
            'define_concept' => 0.35,
            'explain_process' => 0.40,
            'compare_things' => 0.45,
            'generate_code' => 0.50,
            default => 0.30
        };

        // Multi-part queries need higher quality
        if ($isMultiPart) {
            $threshold *= 1.1;
        }

        return $avgQuality >= $threshold;
    }

    /**
     * Simple count-based sufficiency check (fallback when weighted sufficiency disabled).
     *
     * @param list<array<string, mixed>> $ranked
     * @param MemoryStore|null $memory MemoryStore instance for adaptive thresholds
     */
    private function memoryIsSufficientSimple(array $ranked, string $intent, bool $isMultiPart, ?MemoryStore $memory = null): bool
    {
        if ($ranked === []) {
            return false;
        }

        $topic = $ranked[0]['_topic'] ?? 'general_topic';
        $thresholds = $this->getAdaptiveThresholds($topic, $memory ?? new MemoryStore(__DIR__ . '/../memory/memory.json'));

        $strong = array_filter(
            $ranked,
            fn(array $f): bool => (float)($f['_score'] ?? 0) >= $thresholds['combined']
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

        return (float)($ranked[0]['_score'] ?? 0) >= $thresholds['combined'];
    }

    /**
     * Main sufficiency check - delegates to weighted or simple based on feature flag.
     *
     * @param list<array<string, mixed>> $ranked
     * @param MemoryStore $memory MemoryStore instance for adaptive thresholds
     */
    private function memoryIsSufficient(
        array $ranked,
        string $intent,
        bool $isMultiPart,
        ?MemoryStore $memory = null,
        array $specifics = [],
        ?float $precomputedConfidence = null
    ): bool {
        if ($ranked === []) {
            return false;
        }

        $confidence = $precomputedConfidence;
        if ($confidence === null) {
            $confidence = $this->computeAnswerConfidence(
                $ranked,
                array_slice($ranked, 0, $this->maxAnswerFacts),
                '',
                $intent,
                $specifics,
                $isMultiPart
            );
        }

        // Intent thresholds on unified confidence
        $threshold = match ($intent) {
            'define_concept' => 0.34,
            'explain_process' => 0.38,
            'compare_things' => 0.42,
            'generate_code' => 0.48,
            default => 0.32,
        };
        if ($isMultiPart) {
            $threshold *= 1.08;
        }
        if ($specifics !== []) {
            $threshold = min(0.62, $threshold + 0.04);
        }

        if ($this->weightedSufficiencyEnabled) {
            return $confidence >= $threshold
                && $this->memoryIsSufficientWeighted($ranked, $intent, $isMultiPart, $memory);
        }

        return $confidence >= $threshold
            && $this->memoryIsSufficientSimple($ranked, $intent, $isMultiPart, $memory);
    }

    /**
     * Unified 0–1 confidence for current local answer candidates.
     * Combines rank scores, fact confidence, source quality, and specificity fit.
     *
     * @param list<array<string, mixed>> $ranked
     * @param list<array<string, mixed>> $selected
     * @param list<string> $specifics
     */
    private function computeAnswerConfidence(
        array $ranked,
        array $selected,
        string $prompt,
        string $intent,
        array $specifics = [],
        bool $isMultiPart = false
    ): float {
        $pool = $selected !== [] ? $selected : array_slice($ranked, 0, 5);
        if ($pool === []) {
            return 0.0;
        }

        $qualitySum = 0.0;
        $weightSum = 0.0;
        $specificityHits = 0;

        foreach (array_slice($pool, 0, 5) as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $score = (float)($fact['_score'] ?? 0.0);
            $conf = (float)($fact['confidence'] ?? 0.5);
            $srcW = max(0.2, $this->sourceRank((string)($fact['source'] ?? '')) / 5.0);
            $type = strtolower((string)($fact['type'] ?? 'statement'));
            $typeW = match ($type) {
                'definition' => 1.05,
                'procedure' => 1.05,
                'code_html' => $intent === 'generate_code' ? 1.15 : 0.85,
                'best_practice' => 1.05,
                'example' => 0.95,
                'warning' => 1.0,
                default => 1.0,
            };

            $content = strtolower((string)($fact['content'] ?? $fact['value'] ?? ''));
            $tags = array_map('strval', is_array($fact['tags'] ?? null) ? $fact['tags'] : []);
            $keywords = array_map('strval', is_array($fact['keywords'] ?? null) ? $fact['keywords'] : []);

            $specFit = 1.0;
            if ($specifics !== []) {
                $hit = false;
                foreach ($specifics as $sp) {
                    $sp = strtolower(trim((string)$sp));
                    if (strlen($sp) < 3) {
                        continue;
                    }
                    if (str_contains($content, $sp)
                        || in_array($sp, $tags, true)
                        || in_array($sp, $keywords, true)
                    ) {
                        $hit = true;
                        $specificityHits++;
                        break;
                    }
                }
                $specFit = $hit ? 1.12 : 0.72;
            }

            $q = $score * $conf * $srcW * $typeW * $specFit;
            $qualitySum += $q;
            $weightSum += $srcW;
        }

        $avg = $weightSum > 0 ? $qualitySum / $weightSum : 0.0;

        // Coverage: multi-part / specifics need more supporting facts
        $need = $isMultiPart ? 3 : (($specifics !== []) ? 2 : 1);
        $coverage = min(1.0, count($pool) / max(1, $need));
        if ($specifics !== [] && $specificityHits === 0) {
            $coverage *= 0.55;
        }

        return max(0.0, min(1.0, 0.75 * $avg + 0.25 * $coverage));
    }

    /**
     * Build storeable facts from an OpenRouter response (code + prose).
     *
     * @param list<string> $keyPhrases
     * @param list<string> $specifics
     * @return list<array<string, mixed>>
     */
    private function buildFactsFromOpenRouterText(
        string $text,
        string $prompt,
        string $intent,
        array $keyPhrases = [],
        array $specifics = []
    ): array {
        $toStore = [];
        $tags = array_values(array_unique(array_filter(array_map(
            static fn($x) => strtolower(trim((string)$x)),
            array_merge($keyPhrases, $specifics)
        ))));

        if ($this->isCodeGenerationRequest($prompt, $intent)) {
            $htmlDoc = $this->extractHtmlDocument($text);
            if ($htmlDoc !== null) {
                $toStore[] = SkillData::normalizeFact([
                    'content' => $htmlDoc,
                    'type' => 'code_html',
                    'source' => 'openrouter',
                    'confidence' => 0.92,
                    'tags' => $tags,
                    'keywords' => array_slice($tags, 0, 12),
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
            $f = SkillData::normalizeFact($f);
            $f['tags'] = array_values(array_unique(array_merge(
                is_array($f['tags'] ?? null) ? $f['tags'] : [],
                $tags
            )));
            $toStore[] = $f;
        }

        return $toStore;
    }

    /**
     * Persist facts and enrich topic meta (description, tags, subtopics).
     *
     * @param list<array<string, mixed>> $facts
     * @param list<string> $subtopics
     * @param list<string> $keyPhrases
     * @param list<string> $specifics
     * @param list<string> $entities
     */
    private function storeFactsWithMeta(
        MemoryStore $memory,
        string $topic,
        array $facts,
        string $prompt,
        array $subtopics = [],
        array $keyPhrases = [],
        array $specifics = [],
        array $entities = []
    ): void {
        if ($facts === []) {
            return;
        }

        $memory->mergeTopicFacts($topic, $facts);

        $tags = array_values(array_unique(array_filter(array_map(
            static fn($x) => strtolower(trim((string)$x)),
            array_merge($keyPhrases, $specifics, $entities, [$topic])
        ))));
        $subs = array_values(array_unique(array_filter(array_map(
            static fn($x) => trim((string)$x),
            $subtopics
        ))));

        $description = trim(str_replace('_', ' ', $topic));
        if ($specifics !== []) {
            $description .= ' — ' . implode(', ', array_slice($specifics, 0, 4));
        } elseif ($prompt !== '') {
            $core = FactSkill::coreQueryFromPrompt($prompt);
            if ($core !== '') {
                $description .= ' — ' . (function_exists('mb_substr') ? mb_substr($core, 0, 120) : substr($core, 0, 120));
            }
        }

        try {
            $memory->mergeTopicMeta($topic, [
                'description' => $description,
                'tags' => $tags,
                'subtopics' => $subs,
                'aliases' => array_values(array_filter([$topic])),
            ]);
        } catch (Throwable $e) {
            // non-fatal
        }

        foreach ($subs as $sub) {
            if ($sub !== '' && $sub !== $topic) {
                $memory->mergeTopicFacts($sub, $facts);
            }
        }
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
     * Enhanced with type diversity limits.
     *
     * @param list<array<string, mixed>> $ranked
     * @return list<array<string, mixed>>
     */
    private function selectFactsForAnswer(array $ranked, string $intent = '', string $prompt = '', ?MemoryStore $memory = null): array
    {
        if ($ranked === []) {
            return [];
        }

        $ranked = $this->sortByScore($ranked);
        $selected = [];
        $typeCounts = [];
        $isCodeGen = ($intent === 'generate_code')
            || ($prompt !== '' && $this->looksLikeCodeRequest($prompt));

        // Get adaptive thresholds if enabled (reuse caller MemoryStore when provided)
        $topic = (string)($ranked[0]['_topic'] ?? $ranked[0]['topic'] ?? 'general_topic');
        if ($memory === null) {
            $memory = new MemoryStore(__DIR__ . '/../memory/memory.json');
        }
        $thresholds = $this->getAdaptiveThresholds($topic, $memory);

        foreach ($ranked as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $score = (float)($fact['_score'] ?? 0);
            $vector = (float)($fact['_vector_score'] ?? 0);

            // Use adaptive thresholds if enabled, otherwise use defaults
            $minCombined = $this->adaptiveThresholdsEnabled ? $thresholds['combined'] : $this->minimumCombinedScore;
            $minVector = $this->adaptiveThresholdsEnabled ? $thresholds['vector'] : $this->minimumVectorScore;

            // Pass if combined score clears bar OR strong pure vector hit
            if ($score < $minCombined && $vector < $minVector) {
                continue;
            }

            $type = strtolower((string)($fact['type'] ?? 'statement'));
            $count = $typeCounts[$type] ?? 0;

            // Diversity: do not let one type (e.g. code_html) fill the whole answer
            // unless this is a code-generation request.
            // Enhanced with type diversity feature flag
            if ($this->typeDiversityEnabled) {
                $maxPerType = $this->getMaxPerType($type, $isCodeGen, $intent);
            } else {
                // Original logic
                $maxPerType = $isCodeGen && $type === 'code_html' ? 2 : 3;
                if ($type === 'code_html' && !$isCodeGen) {
                    $maxPerType = 1;
                }
            }

            if ($count >= $maxPerType) {
                continue;
            }

            $typeCounts[$type] = $count + 1;
            $selected[] = $fact;
            if (count($selected) >= $this->maxAnswerFacts) {
                break;
            }
        }

        // If diversity filtered everything out, fall back to top score-only
        if ($selected === []) {
            foreach ($ranked as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $score = (float)($fact['_score'] ?? 0);
                $vector = (float)($fact['_vector_score'] ?? 0);

                $minCombined = $this->adaptiveThresholdsEnabled ? $thresholds['combined'] : $this->minimumCombinedScore;
                $minVector = $this->adaptiveThresholdsEnabled ? $thresholds['vector'] : $this->minimumVectorScore;

                if ($score < $minCombined && $vector < $minVector) {
                    continue;
                }
                $selected[] = $fact;
                if (count($selected) >= min(3, $this->maxAnswerFacts)) {
                    break;
                }
            }
        }

        return $selected;
    }

    /**
     * Get maximum facts per type based on intent and type.
     * Used when type diversity is enabled.
     */
    private function getMaxPerType(string $type, bool $isCodeGen, string $intent): int
    {
        // Code generation: allow more code facts
        if ($isCodeGen && $type === 'code_html') {
            return 2;
        }

        // Non-code generation: limit code facts strictly
        if ($type === 'code_html' && !$isCodeGen) {
            return 1;
        }

        // Intent-specific type limits (with new fact types)
        return match($intent) {
            'define_concept' => match($type) {
                'definition' => 2,
                'example' => 2,
                'best_practice' => 2,
                'statement' => 3,
                default => 1
            },
            'explain_process' => match($type) {
                'procedure' => 4,
                'warning' => 2,
                'best_practice' => 2,
                'example' => 2,
                'statement' => 2,
                default => 1
            },
            'compare_things' => match($type) {
                'statement' => 2,
                'example' => 2,
                default => 1
            },
            'generate_code' => match($type) {
                'code_html' => 2,
                'procedure' => 2,
                'best_practice' => 1,
                default => 1
            },
            default => 3 // Generic limit
        };
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
    private function isSimpleCodeRequest(string $prompt, array $constraints = [], array $specifics = []): bool
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
    /**
     * Pull title/message text from prompts like:
     *   Make me a PHP page that says "Beta AI system"
     *   HTML page titled Welcome
     *
     * @return array{title: string, message: string}
     */
    private function extractPageCopy(string $prompt, array $specifics = []): array
    {
        $title = 'My Page';
        $message = 'Hello!';

        if ($specifics !== []) {
            $primary = trim((string)$specifics[0]);
            if ($primary !== '') {
                $title = $primary;
                $message = $primary;
            }
        }

        // Quoted phrase(s)
        if (preg_match('/["\']([^"\']{1,80})["\']/', $prompt, $m)) {
            $title = trim($m[1]);
            $message = $title;
        } elseif (preg_match('/\b(?:that\s+says|saying|with\s+text|showing|display(?:ing)?)\s+(.+?)\s*$/i', $prompt, $m)) {
            $title = trim($m[1], " \t.\"'");
            $message = $title;
        } elseif (preg_match('/\b(?:titled|title|named|called)\s+["\']?([^"\']{1,80?}?)["\']?\s*$/i', $prompt, $m)) {
            $title = trim($m[1], " \t.\"'");
            $message = $title;
        }

        // Safety for PHP single-quoted string injection
        $title = str_replace(["\\", "'"], ["\\\\", "\\'"], $title);
        $message = str_replace(["\\", "'"], ["\\\\", "\\'"], $message);

        if ($title === '') {
            $title = 'My Page';
        }
        if ($message === '') {
            $message = $title;
        }

        return ['title' => $title, 'message' => $message];
    }

    /**
     * Local templates when user asks for a *simple* page (no OpenRouter required).
     * Custom title/message from the prompt are applied when present.
     *
     * @param list<string> $constraints
     */
    private function localCodeTemplate(string $prompt, string $topic, string $intent, array $constraints = [], array $specifics = []): ?string
    {
        if (!$this->isCodeGenerationRequest($prompt, $intent)) {
            return null;
        }
        // Advanced / non-simple → let hybrid OpenRouter handle it
        if (!$this->isSimpleCodeRequest($prompt, $constraints, $specifics)) {
            return null;
        }

        $p = strtolower($prompt);
        $wantPhp = (bool)preg_match('/\bphp\b/', $p) || $topic === 'php_page';
        $wantHtml = (bool)preg_match('/\bhtml\b|\bweb\s*page\b|\bwebpage\b/', $p) || $topic === 'html_page';

        $copy = $this->extractPageCopy($prompt, $specifics);
        $title = $copy['title'];
        $message = $copy['message'];

        if ($wantPhp) {
            $code = "<?php\n"
                . "declare(strict_types=1);\n"
                . "\$title = '{$title}';\n"
                . "\$message = '{$message}';\n"
                . "?><!DOCTYPE html>\n"
                . "<html lang=\"en\">\n"
                . "<head>\n"
                . "    <meta charset=\"utf-8\">\n"
                . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
                . "    <title><?= htmlspecialchars(\$title, ENT_QUOTES, 'UTF-8') ?></title>\n"
                . "    <style>\n"
                . "        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }\n"
                . "        h1 { color: #222; }\n"
                . "    </style>\n"
                . "</head>\n"
                . "<body>\n"
                . "    <h1><?= htmlspecialchars(\$title, ENT_QUOTES, 'UTF-8') ?></h1>\n"
                . "    <p><?= htmlspecialchars(\$message, ENT_QUOTES, 'UTF-8') ?></p>\n"
                . "</body>\n"
                . "</html>";
            return "Here is a simple PHP page you can save as `index.php`.\n\n```php\n" . $code . "\n```";
        }

        if ($wantHtml || $this->looksLikeCodeRequest($prompt)) {
            // For HTML, unescape only for display in tags (title was escaped for PHP quotes)
            $htmlTitle = str_replace(["\\'", "\\\\"], ["'", "\\"], $title);
            $htmlMessage = str_replace(["\\'", "\\\\"], ["'", "\\"], $message);
            $htmlTitle = htmlspecialchars($htmlTitle, ENT_QUOTES, 'UTF-8');
            $htmlMessage = htmlspecialchars($htmlMessage, ENT_QUOTES, 'UTF-8');
            $code = "<!DOCTYPE html>\n"
                . "<html lang=\"en\">\n"
                . "<head>\n"
                . "    <meta charset=\"utf-8\">\n"
                . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
                . "    <title>{$htmlTitle}</title>\n"
                . "    <style>\n"
                . "        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }\n"
                . "        h1 { color: #222; }\n"
                . "    </style>\n"
                . "</head>\n"
                . "<body>\n"
                . "    <h1>{$htmlTitle}</h1>\n"
                . "    <p>{$htmlMessage}</p>\n"
                . "</body>\n"
                . "</html>";
            return "Here is a simple HTML page you can save as `index.html`.\n\n```html\n" . $code . "\n```";
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
        // Intent-aware ordering when feature enabled
        if ($this->intentSynthesisPriorityEnabled && $facts !== []) {
            $facts = $this->prioritizeFactsForSynthesis($facts, $intent);
        }

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
     * Prioritize facts for synthesis based on intent and fact type.
     * Used when intent-aware synthesis priority is enabled.
     *
     * @param list<array<string, mixed>> $facts
     * @return list<array<string, mixed>>
     */
    private function prioritizeFactsForSynthesis(array $facts, string $intent): array
    {
        if ($facts === []) {
            return [];
        }

        // Define priority weights for each intent/type combination
        $priorityMap = [
            'define_concept' => [
                'definition' => 1.0,
                'example' => 0.8,
                'best_practice' => 0.7,
                'statement' => 0.6,
                'warning' => 0.5,
                'procedure' => 0.4,
            ],
            'explain_process' => [
                'procedure' => 1.0,
                'warning' => 0.9,
                'best_practice' => 0.8,
                'example' => 0.7,
                'statement' => 0.5,
                'definition' => 0.3,
            ],
            'compare_things' => [
                'statement' => 1.0,
                'example' => 0.8,
                'definition' => 0.6,
                'best_practice' => 0.5,
            ],
            'generate_code' => [
                'code_html' => 1.0,
                'procedure' => 0.6,
                'best_practice' => 0.5,
                'statement' => 0.3,
            ],
            'list_information' => [
                'statement' => 1.0,
                'example' => 0.9,
                'definition' => 0.7,
            ],
        ];

        // Default priority if intent not in map
        $defaultPriorities = [
            'statement' => 1.0,
            'definition' => 0.8,
            'example' => 0.7,
            'procedure' => 0.6,
            'best_practice' => 0.5,
            'warning' => 0.5,
        ];

        $weights = $priorityMap[$intent] ?? $defaultPriorities;

        // Assign priority to each fact
        foreach ($facts as &$fact) {
            $type = strtolower((string)($fact['type'] ?? 'statement'));
            $fact['_synthesis_priority'] = $weights[$type] ?? 0.5;
        }
        unset($fact);

        // Sort by priority (higher first), then by score
        usort($facts, function (array $a, array $b): int {
            $priorityCmp = ($b['_synthesis_priority'] ?? 0) <=> ($a['_synthesis_priority'] ?? 0);
            if ($priorityCmp !== 0) {
                return $priorityCmp;
            }
            return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
        });

        return $facts;
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
