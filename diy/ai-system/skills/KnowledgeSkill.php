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
    private int $vectorLimit = 80;
    /** Safety ceiling only — all facts above relevance threshold are kept. */
    private int $maxAnswerFacts = 80;

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

        // Capability gate: refuse or stub skills we cannot run yet
        $cap = $this->detectCapability($prompt, $data);
        $data->set('capability', $cap['capability']);
        $data->set('capability_status', $cap['status']);
        $data->set('capability_message', $cap['message']);
        if ($cap['status'] === 'unsupported' || $cap['status'] === 'planned') {
            $data->set('memory_sufficient', true);
            $data->set('answer_confidence', 1.0);
            $data->set('draft_answer', $cap['message']);
            return $data;
        }

        $features = $this->loadFeatures();
        $openRouterEnabled = !empty($features['openrouter_enabled']);
        $searchEnabled = !empty($features['search_enabled']);
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


        // --- Skill plan orchestration (memory-first) ---
        // Named steps: memory_retrieve → (openrouter → memory_store)* → synthesize_local
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
        $searchCalls = 0;
        $openRouterRaw = null;
        $gapFill = false;
        $draft = '';
        $ranked = [];
        $selected = [];
        $executedPlan = [];

        $ctx = [
            'prompt' => $prompt,
            'coreQuery' => $coreQuery,
            'topic' => $topic,
            'intent' => $intent,
            'entities' => $entities,
            'questionType' => $questionType,
            'queryEmbedding' => $queryEmbedding,
            'keyPhrases' => $keyPhrases,
            'constraints' => $constraints,
            'secondaryIntents' => $secondaryIntents,
            'subtopics' => $subtopics,
            'isMultiPart' => $isMultiPart,
            'specifics' => $specifics,
            'searchText' => $searchText,
            'conversationContext' => $conversationContext,
            'hasSpecifics' => $hasSpecifics,
        ];

        while (true) {
            // Step: memory_retrieve (+ score / confidence)
            $state = $this->runMemoryRetrieveStep($memory, $data, $ctx);
            $ranked = $state['ranked'];
            $selected = $state['selected'];
            $sufficient = $state['sufficient'];
            $confidence = $state['confidence'];

            $needsWeb = $this->needsRealtimeSearch($prompt);
            $explicitSearch = (bool) preg_match(
                '/\b(?:search the web|google|look up|web search|search online|search for)\b/i',
                $prompt
            );
            // Force SearchSkill for live/local web or explicit search phrasing
            $forceSearch = $searchEnabled && ($needsWeb || $explicitSearch);
            if ($forceSearch) {
                $sufficient = false;
                $data->set('memory_sufficient', false);
            }

            $plan = $this->buildSkillPlan(
                $sufficient,
                $openRouterEnabled,
                $mode,
                'supported',
                $searchEnabled,
                $forceSearch
            );
            $data->set('skill_plan', $plan);
            $executedPlan = array_values(array_unique(array_merge($executedPlan, ['memory_retrieve'], $plan)));

            $needsSearch = in_array('search', $plan, true) && $searchEnabled && $searchCalls < 1;
            $needsOpenRouter = in_array('openrouter', $plan, true) && $canCallOpenRouter
                && $openRouterCalls < $maxOpenRouterCalls;

            // Sufficient local knowledge → synthesize only
            if ($sufficient && !$needsSearch && !$needsOpenRouter) {
                $draft = $this->runSynthesizeLocalStep(
                    $selected,
                    $ranked,
                    $ctx,
                    $memory,
                    $openRouterEnabled,
                    $mode,
                    $openRouterCalls,
                    $conversationContext
                );
                $executedPlan[] = 'synthesize_local';
                break;
            }

            // Primary gap-fill: SearchSkill when plan says search
            if ($needsSearch) {
                $query = $prompt;
                if ($coreQuery !== '' && !preg_match('/\b(?:search|google|nearby|weather|bus|opening)\b/i', $prompt)) {
                    $query = $coreQuery;
                }
                $searchOk = $this->runSearchStoreStep(
                    $data,
                    $memory,
                    $query,
                    $topic,
                    $prompt,
                    $subtopics,
                    $keyPhrases,
                    $specifics,
                    $entities
                );
                $searchCalls++;
                $executedPlan[] = 'search';
                if ($searchOk) {
                    $executedPlan[] = 'memory_store';
                    continue; // re-check local memory
                }
                // Search failed → try OpenRouter if available, else synthesize
                if (!$needsOpenRouter && !($canCallOpenRouter && $openRouterCalls < $maxOpenRouterCalls)) {
                    $draft = $selected !== []
                        ? $this->answerFromLocalFacts(
                            $selected, $ranked, $prompt, $topic, $intent, $entities,
                            $questionType, $constraints, $secondaryIntents, $isMultiPart, $subtopics, $memory
                        )
                        : 'Web search did not return usable facts.';
                    $executedPlan[] = 'synthesize_local';
                    break;
                }
                // Fall through to OpenRouter as secondary
                $needsOpenRouter = $canCallOpenRouter && $openRouterCalls < $maxOpenRouterCalls;
            }

            // Secondary gap-fill: OpenRouter (search is fallback on failure)
            if ($needsOpenRouter) {

            $gapFill = $ranked !== [];
            $data->set('openrouter', array_merge(
                is_array($data->get('openrouter')) ? $data->get('openrouter') : [],
                ['gap_fill' => $gapFill, 'enabled' => true, 'mode' => $mode]
            ));
            $openRouterCalls++;
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
            $executedPlan[] = 'openrouter';

            $orFailed = isset($response['error']);
            $text = '';
            if (!$orFailed) {
                $text = trim((string)($response['choices'][0]['message']['content'] ?? ''));
            }
            $orCannot = $text !== '' && $this->openRouterAdmitsInability($text);

            if ($orFailed || $text === '' || $orCannot) {
                // Fallback: SearchSkill when OpenRouter cannot help
                if ($searchEnabled && $searchCalls < 1) {
                    $searchOk = $this->runSearchStoreStep(
                        $data,
                        $memory,
                        $coreQuery !== '' ? $coreQuery : $prompt,
                        $topic,
                        $prompt,
                        $subtopics,
                        $keyPhrases,
                        $specifics,
                        $entities
                    );
                    $searchCalls++;
                    $executedPlan[] = 'search';
                    if ($searchOk) {
                        $executedPlan[] = 'memory_store';
                        continue; // re-check local memory
                    }
                }

                if ($selected !== []) {
                    $draft = $this->answerFromLocalFacts(
                        $selected, $ranked, $prompt, $topic, $intent, $entities,
                        $questionType, $constraints, $secondaryIntents, $isMultiPart, $subtopics, $memory
                    );
                } elseif ($orFailed) {
                    $draft = 'I could not retrieve knowledge for this topic right now. '
                        . (string)($response['message'] ?? '');
                } elseif ($text === '') {
                    $draft = 'OpenRouter returned an empty response.';
                } else {
                    $draft = $text; // model admitted limits; surface message if no search
                }
                $executedPlan[] = 'synthesize_local';
                break;
            }

            $openRouterRaw = $text;

            // Step: memory_store (persist OR facts, then loop to re-retrieve)
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
            $executedPlan[] = 'memory_store';
            continue; // re-check local memory
            }

            // Step: search only when OpenRouter is not in the plan (OR disabled / local mode)
            if ($needsSearch) {
                $searchOk = $this->runSearchStoreStep(
                    $data,
                    $memory,
                    $coreQuery !== '' ? $coreQuery : $prompt,
                    $topic,
                    $prompt,
                    $subtopics,
                    $keyPhrases,
                    $specifics,
                    $entities
                );
                $searchCalls++;
                $executedPlan[] = 'search';
                if ($searchOk) {
                    $executedPlan[] = 'memory_store';
                    continue;
                }
                $draft = $selected !== []
                    ? $this->answerFromLocalFacts(
                        $selected, $ranked, $prompt, $topic, $intent, $entities,
                        $questionType, $constraints, $secondaryIntents, $isMultiPart, $subtopics, $memory
                    )
                    : 'Web search did not return usable facts.';
                $executedPlan[] = 'synthesize_local';
                break;
            }

            $draft = $this->runSynthesizeLocalStep(
                $selected, $ranked, $ctx, $memory, $openRouterEnabled, $mode,
                $openRouterCalls, $conversationContext
            );
            $executedPlan[] = 'synthesize_local';
            break;
        }

        $data->set('skill_plan_executed', array_values(array_unique($executedPlan)));
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
        array $subtopics,
        ?MemoryStore $memory = null
    ): string {
        // Prefer stored code document only for generation requests (not "what is PHP?")
        if ($this->isCodeGenerationRequest($prompt, $intent) && $intent !== 'define_concept') {
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

        $use = $selected !== [] ? $selected : $this->selectFactsForAnswer($ranked, $intent, $prompt, $memory);
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
        $queryKeywords = $keyPhrases !== []
            ? array_map(static fn($p) => strtolower(trim((string)$p)), $keyPhrases)
            : FactSkill::extractKeywords($prompt);

        // Fact Topic matching first (title / tags / aliases / possible_prompts)
        $topicsToScan = [];
        try {
            $matched = $memory->matchFactTopics($prompt, $queryEmbedding, 0.28, 8);
            foreach ($matched as $hit) {
                $t = trim((string)($hit['topic'] ?? ''));
                if ($t !== '' && !in_array($t, $topicsToScan, true)) {
                    $topicsToScan[] = $t;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Always include PU topic + subtopics as soft priors
        foreach (array_merge([$topic], $subtopics) as $t) {
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '' && !in_array($t, $topicsToScan, true)) {
                $topicsToScan[] = $t;
            }
        }

        // Related topics (embedding) + related_topics / subtopics from envelope
        try {
            $related = $memory->findRelatedTopics($queryEmbedding, $prompt, 6, 0.14);
            foreach ($related as $rel) {
                $rel = trim((string)$rel);
                if ($rel !== '' && !in_array($rel, $topicsToScan, true)) {
                    $topicsToScan[] = $rel;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        foreach (array_slice($topicsToScan, 0, 6) as $scanTopic) {
            try {
                $meta = $memory->getTopicMeta($scanTopic);
                foreach (array_merge($meta['subtopics'] ?? [], $meta['related_topics'] ?? []) as $st) {
                    $st = trim((string)$st);
                    if ($st !== '' && !in_array($st, $topicsToScan, true)) {
                        $topicsToScan[] = $st;
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        if ($queryEmbedding !== []) {
            // Use adaptive thresholds for vector search
            $thresholds = $this->getAdaptiveThresholds($topic, $memory);

            foreach ($topicsToScan as $t) {
                foreach ($memory->searchByVector(
                    $queryEmbedding,
                    $thresholds['vector'],
                    $this->vectorLimit,
                    $t,
                    $keyPhrases
                ) as $fact) {
                    $pool[] = $this->withScores($fact, $prompt, $t, (float)($fact['_score'] ?? 0.0), $keyPhrases, $queryEmbedding, $queryKeywords);
                }
            }
            // Global search only as fallback when topic-scoped pool is thin
            if (count($pool) < 5) {
                foreach ($memory->searchByVector(
                    $queryEmbedding,
                    max($thresholds['vector'], 0.50),
                    min(20, $this->vectorLimit),
                    null,
                    $keyPhrases
                ) as $fact) {
                    $pool[] = $this->withScores($fact, $prompt, $topic, (float)($fact['_score'] ?? 0.0), $keyPhrases, $queryEmbedding, $queryKeywords);
                }
            }
        }

        foreach ($topicsToScan as $t) {
            foreach ($this->selectUsefulMemoryFacts($memory->getTopicFacts($t), $prompt, $t) as $fact) {
                $pool[] = $this->withScores($fact, $prompt, $t, 0.0, $keyPhrases, $queryEmbedding, $queryKeywords);
            }
        }

        foreach ($entities as $entity) {
            $entityTopic = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$entity) ?? '');
            $entityTopic = trim($entityTopic, '_');
            if ($entityTopic === '' || in_array($entityTopic, $topicsToScan, true)) {
                continue;
            }
            foreach ($memory->getTopicFacts($entityTopic) as $fact) {
                $pool[] = $this->withScores($fact, $prompt, $entityTopic, 0.0, $keyPhrases, $queryEmbedding, $queryKeywords);
            }
        }

        return $this->dedupeKeepBestScore($pool);
    }

    /**
     * Score a fact: FactSkill owns the base formula; Knowledge only adds
     * phrase-hit and soft recency boosts (not a second independent formula).
     *
     * @param list<float> $queryEmbedding
     * @param list<string> $keyPhrases
     * @param list<string> $queryKeywords Precomputed keywords (hoisted)
     */
    private function withScores(
        array $fact,
        string $prompt,
        string $topic,
        float $vectorScore,
        array $keyPhrases = [],
        array $queryEmbedding = [],
        array $queryKeywords = []
    ): array {
        $value = trim((string)($fact['value'] ?? $fact['content'] ?? $fact['fact'] ?? ''));

        if ($queryKeywords === []) {
            $queryKeywords = $keyPhrases !== []
                ? array_map(static fn($p) => strtolower(trim((string)$p)), $keyPhrases)
                : FactSkill::extractKeywords($prompt);
        }

        $override = $vectorScore > 0.0 ? $vectorScore : -1.0;
        $fs = FactSkill::scoreFactAgainstQuery(
            $fact,
            $prompt,
            $queryEmbedding,
            $queryKeywords,
            $override
        );

        // Explicit add-ons only (not a second scoring system)
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
            $phraseBoost = min(0.12, $hits * 0.03);
        }
        $recencyBoost = $this->getRecencyBoost($fact, $prompt);

        $fact['_vector_score'] = $fs['vector'];
        $fact['_keyword_score'] = $fs['keyword'];
        $fact['_score'] = min(1.0, $fs['combined'] + $phraseBoost + $recencyBoost);

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
     * Main sufficiency check - delegates to weighted or simple based on feature flag.
     *
     * @param list<array<string, mixed>> $ranked
     * @param MemoryStore $memory MemoryStore instance for adaptive thresholds
     */
    /**
     * Single sufficiency gate: unified confidence vs one intent threshold.
     * (No stacked weighted/simple AND gates.)
     *
     * @param list<array<string, mixed>> $ranked
     * @param list<string> $specifics
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

        $threshold = $this->intentConfidenceThreshold($intent, $isMultiPart, $specifics);

        // Soft minimum: at least one strong fact
        $top = (float)($ranked[0]['_score'] ?? 0);
        if ($top < 0.20) {
            return false;
        }

        return $confidence >= $threshold;
    }

    /**
     * One threshold table for sufficiency (aligned with FactSkill-based scores).
     *
     * @param list<string> $specifics
     */
    private function intentConfidenceThreshold(string $intent, bool $isMultiPart, array $specifics = []): float
    {
        $threshold = match ($intent) {
            'define_concept' => 0.34,
            'explain_process' => 0.38,
            'compare_things' => 0.42,
            'generate_code' => 0.48,
            default => 0.32,
        };
        if ($isMultiPart) {
            $threshold = min(0.65, $threshold + 0.04);
        }
        if ($specifics !== []) {
            $threshold = min(0.65, $threshold + 0.03);
        }
        return $threshold;
    }

    /**
     * Unified 0–1 confidence from FactSkill-ranked facts (_score).
     * Specificity fit adjusts coverage when the prompt has unique requirements.
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
        $pool = $selected !== [] ? $selected : array_slice($ranked, 0, 8);
        if ($pool === []) {
            return 0.0;
        }

        $scoreSum = 0.0;
        $n = 0;
        $specificityHits = 0;

        foreach ($pool as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $score = (float)($fact['_score'] ?? 0.0);
            $conf = (float)($fact['confidence'] ?? 0.7);
            // Blend rank score with stored fact confidence (single stream)
            $scoreSum += $score * (0.7 + 0.3 * $conf);
            $n++;

            if ($specifics === []) {
                continue;
            }
            $content = strtolower((string)($fact['content'] ?? $fact['value'] ?? ''));
            $tags = array_map('strval', is_array($fact['tags'] ?? null) ? $fact['tags'] : []);
            foreach ($specifics as $sp) {
                $sp = strtolower(trim((string)$sp));
                if ($sp === '' || (strlen($sp) < 8 && !str_contains($sp, ' '))) {
                    continue;
                }
                if (str_contains($content, $sp) || in_array($sp, $tags, true)) {
                    $specificityHits++;
                    break;
                }
            }
        }

        if ($n === 0) {
            return 0.0;
        }

        $avg = $scoreSum / $n;
        $need = $isMultiPart ? 3 : (($specifics !== []) ? 2 : 1);
        $coverage = min(1.0, $n / max(1, $need));
        if ($specifics !== [] && $specificityHits === 0) {
            $coverage *= 0.55;
            $avg *= 0.85;
        }

        return max(0.0, min(1.0, 0.80 * $avg + 0.20 * $coverage));
    }

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
            $title = str_replace('_', ' ', $topic);
            if ($specifics !== []) {
                $title = $prompt !== '' ? FactSkill::coreQueryFromPrompt($prompt) : $title;
            } elseif ($prompt !== '') {
                $core = FactSkill::coreQueryFromPrompt($prompt);
                if ($core !== '') {
                    $title = $core;
                }
            }
            $memory->mergeTopicMeta($topic, [
                'title' => $title,
                'description' => $description,
                'tags' => $tags,
                'subtopics' => $subs,
                'aliases' => array_values(array_filter([$topic, $title])),
                'possible_prompts' => array_values(array_filter([
                    $prompt !== '' ? FactSkill::coreQueryFromPrompt($prompt) : '',
                ])),
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
     * Keep every fact that clears relevance / vector / confidence thresholds.
     * maxAnswerFacts is only a safety ceiling (not "top 10 only").
     * Soft type caps avoid flooding non-code answers with code_html only.
     *
     * @param list<array<string, mixed>> $ranked
     * @return list<array<string, mixed>>
     */
    /**
     * Quality re-rank: adjust FactSkill scores with topic-name alignment and
     * prompt/specificity overlap (cross-check, pure PHP — no second formula).
     *
     * @param list<array<string, mixed>> $ranked
     * @param list<string> $keyPhrases
     * @param list<string> $specifics
     * @return list<array<string, mixed>>
     */
    private function rerankFacts(
        array $ranked,
        string $prompt,
        string $topic,
        array $keyPhrases = [],
        array $specifics = []
    ): array {
        if ($ranked === [] || count($ranked) < 2) {
            return $ranked;
        }

        $promptLower = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
        $topicTokens = array_values(array_filter(
            preg_split('/[_\s]+/', strtolower(str_replace('_', ' ', $topic))) ?: [],
            static fn($w) => strlen($w) >= 3
        ));
        $phrases = array_values(array_filter(array_map(
            static fn($p) => strtolower(trim((string)$p)),
            $keyPhrases
        )));
        $specs = [];
        foreach ($specifics as $sp) {
            $sp = strtolower(trim((string)$sp));
            if ($sp !== '' && (strlen($sp) >= 8 || str_contains($sp, ' '))) {
                $specs[] = $sp;
            }
        }

        foreach ($ranked as &$fact) {
            if (!is_array($fact)) {
                continue;
            }
            $base = (float)($fact['_score'] ?? 0.0);
            $content = strtolower((string)($fact['content'] ?? $fact['value'] ?? ''));
            $factTopic = strtolower(str_replace('_', ' ', (string)($fact['_topic'] ?? $fact['topic'] ?? $topic)));
            $tags = array_map('strval', is_array($fact['tags'] ?? null) ? $fact['tags'] : []);

            // Topic-name alignment: prefer facts from topics whose tokens appear in the prompt
            $align = 0.0;
            $tokHits = 0;
            foreach ($topicTokens as $tok) {
                if (str_contains($promptLower, $tok) || str_contains($content, $tok)) {
                    $tokHits++;
                }
            }
            if ($topicTokens !== []) {
                $align = $tokHits / count($topicTokens);
            }
            // Fact's own topic name vs prompt
            $ftoks = array_values(array_filter(preg_split('/\s+/', $factTopic) ?: [], static fn($w) => strlen($w) >= 3));
            $fHits = 0;
            foreach ($ftoks as $tok) {
                if (str_contains($promptLower, $tok)) {
                    $fHits++;
                }
            }
            $factAlign = $ftoks !== [] ? $fHits / count($ftoks) : 0.0;
            $align = max($align, $factAlign);

            // Phrase density
            $phraseHits = 0;
            foreach ($phrases as $ph) {
                if ($ph !== '' && str_contains($content, $ph)) {
                    $phraseHits++;
                }
            }
            $phraseFactor = $phrases !== [] ? min(1.0, $phraseHits / min(4, count($phrases))) : 0.0;

            // Specificity: strong boost if long specific appears; penalty if required but missing
            $specFactor = 0.0;
            if ($specs !== []) {
                $specHit = false;
                foreach ($specs as $sp) {
                    if (str_contains($content, $sp) || in_array($sp, $tags, true)) {
                        $specHit = true;
                        break;
                    }
                }
                $specFactor = $specHit ? 0.10 : -0.08;
            }

            // Penalize facts from topics that share almost no tokens with the prompt
            // (e.g. celestial "sky" when prompt is call-center Sky process)
            $mismatchPenalty = 0.0;
            if ($ftoks !== [] && $factAlign < 0.25 && $align < 0.35) {
                $mismatchPenalty = -0.06;
            }

            // Definition questions: demote code_html / page templates heavily
            $typePenalty = 0.0;
            $type = strtolower((string)($fact['type'] ?? ''));
            $isDefPrompt = (bool)preg_match('/\b(?:what is|what are|define|meaning of|explain what)\b/i', $prompt);
            if ($isDefPrompt && in_array($type, ['code_html', 'prompt_intent', 'prompt_core'], true)) {
                $typePenalty = -0.35;
            }

            $adjust = (0.08 * $align) + (0.05 * $phraseFactor) + $specFactor + $mismatchPenalty + $typePenalty;
            $fact['_score'] = max(0.0, min(1.0, $base + $adjust));
            $fact['_rerank_adjust'] = $adjust;
        }
        unset($fact);

        return $this->sortByScore($ranked);
    }

    /**
     * Main subject for "What is X?" / "Define X" style prompts.
     */
    private function extractDefinitionSubject(string $prompt): string
    {
        $p = trim($prompt);
        $p = preg_replace('/^[\'"\s]+|[\'"\s?!.]+$/u', '', $p) ?? $p;
        if (preg_match('/^\s*(?:what\s+(?:is|are)|who\s+(?:is|are)|define|meaning\s+of|explain)\s+(.+)$/iu', $p, $m)) {
            $sub = trim($m[1]);
        } else {
            $sub = $p;
        }
        $sub = preg_replace('/^(?:a|an|the)\s+/iu', '', $sub) ?? $sub;
        $sub = trim($sub, " \t\n\r\0\x0B?!.'\"");
        $parts = preg_split('/\s+/u', $sub) ?: [];
        if (count($parts) > 4) {
            $sub = implode(' ', array_slice($parts, 0, 4));
        }
        return $sub;
    }

    /**
     * Content looks like a definition of $subject (not a side FAQ).
     */
    private function factLooksLikeDefinitionOf(string $content, string $subject): bool
    {
        if ($subject === '' || $content === '') {
            return false;
        }
        $s = preg_quote($subject, '/');
        if (preg_match('/\b' . $s . '\b.{0,80}\b(?:is|are|refers to|means|stands for|was|were)\b/iu', $content)) {
            return true;
        }
        if (preg_match('/\b(?:is|are)\s+(?:a|an|the)\b[^.]{0,100}\b' . $s . '\b/iu', $content)) {
            return true;
        }
        return false;
    }

    /**
     * Fact is about a narrower product/service than the bare subject
     * (e.g. Sky Glass / Sky Stream when subject is only "Sky").
     */
    private function factIsNarrowerProductThanSubject(string $content, string $subject, string $prompt): bool
    {
        if ($subject === '' || strlen($subject) < 2) {
            return false;
        }
        $pl = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
        $sLow = function_exists('mb_strtolower') ? mb_strtolower($subject) : strtolower($subject);
        // User already asked for the narrower name
        if (preg_match('/\b' . preg_quote($sLow, '/') . '\s+\w{2,}/u', $pl)) {
            return false;
        }
        $s = preg_quote($subject, '/');
        if (preg_match('/\b' . $s . '\s+(?:glass|stream|puck|store|id|q\b|mail|go\b|broadband|wifi|hub|remote|app|support|yahoo|smtp)/iu', $content)) {
            return true;
        }
        if (preg_match('/\b' . $s . '\s+[A-Z][a-zA-Z0-9]{1,24}\b/u', $content)) {
            return true;
        }
        if (preg_match('/\b(?:smtp|imap|port\s+\d+|ssl\/tls|mbps|hdmi|factory reset|unplug|reinstall)\b/i', $content)) {
            return true;
        }
        return false;
    }

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

        $topic = (string)($ranked[0]['_topic'] ?? $ranked[0]['topic'] ?? 'general_topic');
        if ($memory === null) {
            $memory = new MemoryStore(__DIR__ . '/../memory/memory.json');
        }
        $thresholds = $this->getAdaptiveThresholds($topic, $memory);

        $minCombined = $this->adaptiveThresholdsEnabled ? $thresholds['combined'] : $this->minimumCombinedScore;
        $minVector = $this->adaptiveThresholdsEnabled ? $thresholds['vector'] : $this->minimumVectorScore;
        $isDefIntent = in_array($intent, ['define_concept', 'explain_reason'], true) && !$isCodeGen;
        if ($isDefIntent) {
            $minCombined = max(0.42, $minCombined);
            $minVector = max(0.45, $minVector);
        } else {
            $minCombined = max(0.18, $minCombined * 0.90);
            $minVector = max(0.28, $minVector * 0.90);
        }

        $promptKeywords = $prompt !== '' ? FactSkill::extractKeywords($prompt, 12) : [];
        $subject = $isDefIntent ? $this->extractDefinitionSubject($prompt) : '';

        foreach ($ranked as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $score = (float)($fact['_score'] ?? 0);
            $vector = (float)($fact['_vector_score'] ?? 0);
            $conf = (float)($fact['confidence'] ?? 0.5);

            if ($conf < 0.25) {
                continue;
            }
            if ($score < $minCombined && $vector < $minVector) {
                continue;
            }

            $type = strtolower((string)($fact['type'] ?? 'statement'));
            $content = (string)($fact['content'] ?? $fact['value'] ?? '');

            $isDef = in_array($intent, ['define_concept', 'explain_reason', 'ask_information'], true) && !$isCodeGen;
            if ($isDef) {
                if ($type === 'code_html' || $type === 'procedure') {
                    continue;
                }
                if (preg_match('/\b(?:how to|step-by-step|press the|unplug|reinstall|factory reset|clear(?:ing)? the cache)\b/i', $content)) {
                    continue;
                }
                if (preg_match('/<!DOCTYPE\s+html|<html\b/i', $content)) {
                    continue;
                }
                if ($subject !== '') {
                    $cLow = strtolower($content);
                    $sLow = strtolower($subject);
                    if (!str_contains($cLow, $sLow) && $score < 0.62) {
                        continue;
                    }
                    // Reject product FAQs when user only asked "what is Sky?"
                    if ($this->factIsNarrowerProductThanSubject($content, $subject, $prompt)) {
                        continue;
                    }
                    $defines = ($type === 'definition') || $this->factLooksLikeDefinitionOf($content, $subject);
                    // Definition answers must be definition-shaped — score alone is not enough
                    if (!$defines) {
                        continue;
                    }
                }
                $len = function_exists('mb_strlen') ? mb_strlen($content) : strlen($content);
                if ($type !== 'definition' && $len > 220 && $score < 0.58) {
                    continue;
                }
            }

            $count = $typeCounts[$type] ?? 0;
            $maxPerType = $this->typeDiversityEnabled
                ? $this->getMaxPerType($type, $isCodeGen, $intent)
                : ($isCodeGen && $type === 'code_html' ? 8 : 40);
            if ($type === 'code_html' && !$isCodeGen) {
                $maxPerType = 0;
            }
            if ($isDefIntent && $type === 'procedure') {
                $maxPerType = 0;
            }

            if ($count >= $maxPerType) {
                continue;
            }

            $typeCounts[$type] = $count + 1;
            $selected[] = $fact;

            $cap = $isDefIntent ? 3 : ($intent === 'ask_information' ? 12 : $this->maxAnswerFacts);
            if (count($selected) >= $cap) {
                break;
            }
        }

        // Keep only defining facts when available
        if ($isDefIntent && $selected !== []) {
            $defs = [];
            foreach ($selected as $f) {
                if (!is_array($f)) {
                    continue;
                }
                $c = (string)($f['content'] ?? $f['value'] ?? '');
                $ty = strtolower((string)($f['type'] ?? ''));
                if ($ty === 'definition' || ($subject !== '' && $this->factLooksLikeDefinitionOf($c, $subject))) {
                    $defs[] = $f;
                }
            }
            if ($defs !== []) {
                $selected = array_slice($defs, 0, 2);
            } else {
                $selected = array_slice($selected, 0, 1);
            }
        }

        // Fallback: only for non-definition, or one best definition-like fact
        if ($selected === []) {
            foreach ($ranked as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $content = (string)($fact['content'] ?? $fact['value'] ?? '');
                $type = strtolower((string)($fact['type'] ?? ''));
                $score = (float)($fact['_score'] ?? 0);
                if ($isDefIntent) {
                    if ($type === 'procedure' || $type === 'code_html') {
                        continue;
                    }
                    if ($subject !== '' && $this->factIsNarrowerProductThanSubject($content, $subject, $prompt)) {
                        continue;
                    }
                    // Only a true definition — never UI/how-to lines
                    if ($type !== 'definition' && !$this->factLooksLikeDefinitionOf($content, $subject)) {
                        continue;
                    }
                    if (preg_match('/\b(?:press the|unplug|remote|menu|smtp|port\s+\d+)\b/i', $content)) {
                        continue;
                    }
                    $selected[] = $fact;
                    break;
                }
                if ($score < ($minCombined * 0.75)) {
                    continue;
                }
                $selected[] = $fact;
                if (count($selected) >= 8) {
                    break;
                }
            }
        }

        return $selected;
    }

    private function getMaxPerType(string $type, bool $isCodeGen, string $intent): int
    {
        // Soft caps — allow many related facts; only restrain code dumps off code intents
        if ($type === 'code_html') {
            return $isCodeGen ? 6 : 2;
        }

        return match ($intent) {
            'define_concept' => match ($type) {
                'definition' => 3,
                'example' => 2,
                'best_practice' => 1,
                'statement' => 3,
                'warning' => 1,
                'procedure' => 0,
                default => 2,
            },
            'explain_process' => match ($type) {
                'procedure' => 25,
                'warning' => 10,
                'best_practice' => 12,
                'example' => 12,
                'statement' => 25,
                default => 15,
            },
            'compare_things' => 20,
            'generate_code' => match ($type) {
                'code_html' => 6,
                'procedure' => 15,
                'best_practice' => 10,
                default => 12,
            },
            default => 25,
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


    /**
     * Detect required capability from the prompt (and PU intent when present).
     * Start small: only catch clearly out-of-scope requests.
     *
     * @return array{capability:string, status:string, message:string}
     */
    private function detectCapability(string $prompt, SkillData $data): array
    {
        $p = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
        $intent = (string)$data->get('intent', '');

        $rules = [
            [
                'capability' => 'location',
                'status' => 'planned',
                'patterns' => [
                    '/\b(?:what(?:\'s| is)|where is|get|share|use)\s+my\s+(?:current\s+)?location\b/i',
                    '/\bmy\s+(?:current\s+)?(?:gps\s+)?(?:location|coordinates|position)\b/i',
                    '/\bwhere\s+am\s+i\b/i',
                    '/\bcurrent\s+(?:city|country|address)\b/i',
                ],
                'message' => 'I cannot access your current location yet. Location-based answers are not available in this version.',
            ],
            [
                'capability' => 'image_generation',
                'status' => 'planned',
                'patterns' => [
                    '/\b(?:generate|create|draw|make|paint)\s+(?:me\s+)?(?:an?\s+)?(?:image|picture|photo|illustration|logo)\b/i',
                    '/\btext\s+to\s+image\b/i',
                    '/\bdall-?e\b/i',
                ],
                'message' => 'I cannot generate images yet. Image generation is planned but not available in this version.',
            ],
            [
                'capability' => 'file_analysis',
                'status' => 'planned',
                'patterns' => [
                    '/\b(?:read|analyze|summarize|open)\s+(?:this\s+|the\s+|my\s+)?(?:uploaded\s+)?(?:file|document|pdf|spreadsheet|csv)\b/i',
                    '/\bupload(?:ed)?\s+(?:a\s+)?(?:file|document|pdf)\b/i',
                    '/\banalyze\s+(?:the\s+)?(?:attachment|upload)\b/i',
                ],
                'message' => 'I cannot read or analyze uploaded files yet. File analysis is planned but not available in this version.',
            ],
            [
                'capability' => 'voice_audio',
                'status' => 'planned',
                'patterns' => [
                    '/\b(?:transcribe|speech\s+to\s+text)\b/i',
                    '/\b(?:generate|make)\s+(?:audio|speech|voiceover|podcast)\b/i',
                    '/\btext\s+to\s+speech\b/i',
                ],
                'message' => 'I cannot process voice or audio yet. Speech features are not available in this version.',
            ],
            [
                'capability' => 'live_web_browse',
                'status' => 'planned',
                'patterns' => [
                    '/\b(?:browse|open|visit)\s+(?:the\s+)?(?:website|url|link)\b/i',
                    '/\bwhat(?:\'s| is)\s+(?:on\s+)?(?:this|that)\s+(?:url|webpage|website)\b/i',
                    '/\bscrape\s+(?:the\s+)?(?:page|site|website)\b/i',
                ],
                'message' => 'I cannot browse live websites yet. Use a question about a topic I can answer from memory or knowledge instead.',
            ],
            [
                'capability' => 'phone_call_sms',
                'status' => 'unsupported',
                'patterns' => [
                    '/\b(?:call|phone|text|sms)\s+(?:me\s+)?(?:this\s+)?(?:number|person)\b/i',
                    '/\bmake\s+a\s+phone\s+call\b/i',
                    '/\bsend\s+(?:an?\s+)?(?:sms|text\s+message)\b/i',
                ],
                'message' => 'I cannot make phone calls or send SMS messages. That is outside what this system can do.',
            ],
            [
                'capability' => 'realtime_control',
                'status' => 'unsupported',
                'patterns' => [
                    '/\b(?:control|turn\s+on|turn\s+off)\s+(?:my\s+)?(?:lights|thermostat|smart\s+home)\b/i',
                    '/\bexecute\s+(?:shell|system)\s+commands?\b/i',
                ],
                'message' => 'I cannot control devices or run system commands. That is outside what this system can do.',
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                if (preg_match($pattern, $prompt) === 1) {
                    return [
                        'capability' => $rule['capability'],
                        'status' => $rule['status'],
                        'message' => $rule['message'],
                    ];
                }
            }
        }

        if ($intent === 'request_location') {
            return [
                'capability' => 'location',
                'status' => 'planned',
                'message' => 'I cannot access your current location yet. Location-based answers are not available in this version.',
            ];
        }

        return [
            'capability' => 'knowledge',
            'status' => 'supported',
            'message' => '',
        ];
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
                'search_enabled' => false,
            ];
        }
        $features = require $path;
        return is_array($features) ? $features : [];
    }


    /**
     * Named step: memory_retrieve — rank facts, confidence, sufficiency.
     *
     * @param array<string, mixed> $ctx
     * @return array{ranked:list, selected:list, sufficient:bool, confidence:float}
     */
    private function runMemoryRetrieveStep(MemoryStore $memory, SkillData $data, array $ctx): array
    {
        $prompt = (string)$ctx['prompt'];
        $topic = (string)$ctx['topic'];
        $intent = (string)$ctx['intent'];
        $entities = is_array($ctx['entities'] ?? null) ? $ctx['entities'] : [];
        $subtopics = is_array($ctx['subtopics'] ?? null) ? $ctx['subtopics'] : [];
        $keyPhrases = is_array($ctx['keyPhrases'] ?? null) ? $ctx['keyPhrases'] : [];
        $specifics = is_array($ctx['specifics'] ?? null) ? $ctx['specifics'] : [];
        $queryEmbedding = is_array($ctx['queryEmbedding'] ?? null) ? $ctx['queryEmbedding'] : [];
        $searchText = (string)($ctx['searchText'] ?? $prompt);
        $isMultiPart = !empty($ctx['isMultiPart']);
        $hasSpecifics = !empty($ctx['hasSpecifics']);
        $constraints = is_array($ctx['constraints'] ?? null) ? $ctx['constraints'] : [];

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
        $ranked = $this->rerankFacts($ranked, $prompt, $topic, $keyPhrases, $specifics);

        // Definition questions: prefer definition facts; avoid page-generation topics
        if (in_array($intent, ['define_concept', 'ask_information'], true)
            && !$this->isCodeGenerationRequest($prompt, $intent)
        ) {
            // Bare "What is Sky?" must not stick to sky_stream_puck / glass product topics
            $subj = $this->extractDefinitionSubject($prompt);
            $subjLow = strtolower($subj);
            $topicLow = strtolower($topic);
            $promptLow = strtolower($prompt);
            if ($subjLow !== '' && !str_contains($promptLow, 'stream') && !str_contains($promptLow, 'puck')
                && !str_contains($promptLow, 'glass') && !str_contains($promptLow, 'mail')
            ) {
                if (preg_match('/(?:stream|puck|glass|smtp|mail|store)/', $topicLow)
                    && !preg_match('/(?:stream|puck|glass|smtp|mail)/', $subjLow)
                ) {
                    // Prefer a simpler topic slug from the subject alone
                    $alt = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $subj) ?? '');
                    $alt = trim($alt, '_');
                    if ($alt !== '' && $alt !== $topic) {
                        $topic = $alt;
                        $ctx['topic'] = $topic;
                        $data->set('topic', $topic);
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
                        $ranked = $this->rerankFacts($ranked, $prompt, $topic, $keyPhrases, $specifics);
                    }
                }
            }
            if (str_ends_with($topic, '_page') || in_array($topic, ['php_page', 'html_page'], true)) {
                if (preg_match('/php/i', $prompt)) {
                    $topic = 'php_programming';
                    $ctx['topic'] = $topic;
                    $data->set('topic', $topic);
                    // Re-retrieve under the correct topic
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
                    $ranked = $this->rerankFacts($ranked, $prompt, $topic, $keyPhrases, $specifics);
                }
            }
            // Demote code_html for non-codegen intents
            foreach ($ranked as &$f) {
                if (!is_array($f)) {
                    continue;
                }
                if (($f['type'] ?? '') === 'code_html') {
                    $f['_score'] = max(0.0, (float)($f['_score'] ?? 0) - 0.35);
                }
                if (($f['type'] ?? '') === 'definition') {
                    $f['_score'] = min(1.0, (float)($f['_score'] ?? 0) + 0.12);
                }
            }
            unset($f);
            $ranked = $this->sortByScore($ranked);
        }

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

        // Code-gen: enough only with a code_html fact that matches specifics
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

        return [
            'ranked' => $ranked,
            'selected' => $selected,
            'sufficient' => $sufficient,
            'confidence' => $confidence,
        ];
    }

    /**
     * Named step: synthesize_local — answer from ranked facts / templates.
     *
     * @param list<array<string, mixed>> $selected
     * @param list<array<string, mixed>> $ranked
     * @param array<string, mixed> $ctx
     */
    private function runSynthesizeLocalStep(
        array $selected,
        array $ranked,
        array $ctx,
        MemoryStore $memory,
        bool $openRouterEnabled,
        string $mode,
        int $openRouterCalls,
        string $conversationContext
    ): string {
        $prompt = (string)$ctx['prompt'];
        $topic = (string)$ctx['topic'];
        $intent = (string)$ctx['intent'];
        $entities = is_array($ctx['entities'] ?? null) ? $ctx['entities'] : [];
        $questionType = (string)($ctx['questionType'] ?? 'statement');
        $constraints = is_array($ctx['constraints'] ?? null) ? $ctx['constraints'] : [];
        $secondaryIntents = is_array($ctx['secondaryIntents'] ?? null) ? $ctx['secondaryIntents'] : [];
        $subtopics = is_array($ctx['subtopics'] ?? null) ? $ctx['subtopics'] : [];
        $isMultiPart = !empty($ctx['isMultiPart']);
        $specifics = is_array($ctx['specifics'] ?? null) ? $ctx['specifics'] : [];
        $hasSpecifics = !empty($ctx['hasSpecifics']);

        if ($this->isCodeGenerationRequest($prompt, $intent)) {
            $localCode = $this->localCodeTemplate($prompt, $topic, $intent, $constraints, $specifics);
            if ($localCode !== null) {
                $factsBlob = '';
                foreach (array_merge($selected, $ranked) as $f) {
                    if (is_array($f)) {
                        $factsBlob .= ' ' . strtolower((string)($f['content'] ?? $f['value'] ?? ''));
                    }
                }
                if ($hasSpecifics && $specifics !== []) {
                    $specInFacts = false;
                    foreach ($specifics as $sp) {
                        $sp = strtolower(trim((string)$sp));
                        if ($sp === '' || (strlen($sp) < 8 && !str_contains($sp, ' '))) {
                            continue;
                        }
                        if (str_contains($factsBlob, $sp)) {
                            $specInFacts = true;
                            break;
                        }
                    }
                    if (!$specInFacts) {
                        return $localCode;
                    }
                } elseif ($selected === []) {
                    return $localCode;
                }
            }
        }

        if ($selected !== []) {
            return $this->answerFromLocalFacts(
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
                $subtopics,
                $memory
            );
        }

        $localCode = $this->localCodeTemplate($prompt, $topic, $intent, $constraints, $specifics);
        if ($localCode !== null) {
            return $localCode;
        }

        $intent = (string)($ctx['intent'] ?? '');
        $subj = $intent === 'define_concept' ? $this->extractDefinitionSubject((string)$ctx['prompt']) : '';
        if ($intent === 'define_concept' && $subj !== '') {
            $draft = 'I do not have a clear definition of "' . $subj . '" in local memory yet. '
                . 'Product or how-to notes about related items are not used for this kind of question.';
        } else {
            $draft = 'No solid local knowledge found for this topic yet.';
        }
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

        return $draft;
    }

    /**
     * Ordered skill plan for this request (Knowledge is the orchestrator).
     *
     * @return list<string> skill class names in execution order
     */
    /**
     * @param bool $forceSearch True when process() detected needs_web or explicit "search for…"
     */
    private function buildSkillPlan(
        bool $memorySufficient,
        bool $openRouterEnabled,
        string $mode,
        string $capabilityStatus = 'supported',
        bool $searchEnabled = false,
        bool $forceSearch = false
    ): array {
        if ($capabilityStatus === 'unsupported' || $capabilityStatus === 'planned') {
            return [];
        }

        // Local-only when memory is enough and not a web-forced prompt
        if ($memorySufficient && !$forceSearch) {
            return ['memory_retrieve', 'synthesize_local'];
        }

        // Primary fallback: SearchSkill (live data, nearby, hours, explicit search, …)
        if ($searchEnabled && $forceSearch) {
            return ['memory_retrieve', 'search', 'memory_store', 'synthesize_local'];
        }

        // Secondary: OpenRouter hybrid
        if ($openRouterEnabled && $mode === 'hybrid') {
            return ['memory_retrieve', 'openrouter', 'memory_store', 'synthesize_local'];
        }

        // Tertiary: search when OR unavailable
        if ($searchEnabled) {
            return ['memory_retrieve', 'search', 'memory_store', 'synthesize_local'];
        }

        return ['memory_retrieve', 'synthesize_local'];
    }


    /**
     * True when the model text says it cannot answer (no live data, no access, …).
     */
    private function openRouterAdmitsInability(string $text): bool
    {
        return (bool) preg_match(
            '/\b(?:'
            . 'i (?:can(?:not|\'t)|am unable to|don\'t|do not) (?:help|provide|access|retrieve|know|give)|'
            . 'unable to (?:provide|access|retrieve)|'
            . '(?:no|without) (?:real[- ]?time|live) (?:data|information|access)|'
            . 'beyond my (?:knowledge|capabilities)|'
            . 'as an ai (?:language )?model'
            . ')\b/i',
            $text
        );
    }

    /**
     * Run SearchSkill and store facts into local memory.
     *
     * @param list<string> $subtopics
     * @param list<string> $keyPhrases
     * @param list<string> $specifics
     * @param list<string> $entities
     */
    private function runSearchStoreStep(
        SkillData $data,
        MemoryStore $memory,
        string $query,
        string $topic,
        string $prompt,
        array $subtopics,
        array $keyPhrases,
        array $specifics,
        array $entities
    ): bool {
        require_once __DIR__ . '/SearchSkill.php';
        $searchSkill = new SearchSkill();
        $data->set('search_query', $query);
        $data = $searchSkill->process($data, $memory);

        $searchBlock = is_array($data->get('search')) ? $data->get('search') : [];
        $toStore = [];
        if (empty($searchBlock['last_error']) && is_array($searchBlock['raw_facts'] ?? null)) {
            $toStore = $searchBlock['raw_facts'];
        }
        if ($toStore === []) {
            return false;
        }

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
        return true;
    }

    /**
     * Prefer SerpAPI search when the web (or live data) is a better source than a frozen LLM.
     *
     * Includes:
     * - explicit "search the web / google"
     * - short definition questions (knowledge graph)
     * - real-time data OpenRouter cannot reliably know (buses, schedules, live scores, …)
     */
    private function prefersWebSearch(string $prompt, string $intent): bool
    {
        if ($this->needsRealtimeSearch($prompt)) {
            return true;
        }
        if (preg_match('/\b(?:search the web|google|look up|web search|search online)\b/i', $prompt)) {
            return true;
        }
        if (in_array($intent, ['define_concept', 'ask_information'], true)
            && preg_match('/^\s*(?:what|who|where|when)\s+(?:is|are|was|were)\b/i', $prompt)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Detect prompts that need live / time-sensitive web data
     * (LLM training cutoff and local memory are not enough).
     */
    private function needsRealtimeSearch(string $prompt): bool
    {
        $p = strtolower($prompt);

        // Transit / schedules
        if (preg_match(
            '/\b(?:bus|train|tram|metro|subway|ferry|flight|gate)\b.*\b(?:arriv|depart|schedule|timetable|when does|next)\b/i',
            $prompt
        ) || preg_match(
            '/\b(?:when does|when is|what time does|next)\b.*\b(?:bus|train|tram|flight)\b/i',
            $prompt
        ) || preg_match(
            '/\b(?:bus|route)\s*\d{1,4}\b/i',
            $prompt
        ) && preg_match('/\b(?:stop|station|arriv|depart|when)\b/i', $prompt)) {
            return true;
        }

        // Live status / "right now"
        if (preg_match(
            '/\b(?:right now|currently|live|real[- ]?time|at the moment|today\'?s? (?:score|price|weather|news))\b/i',
            $prompt
        )) {
            return true;
        }

        // Weather (often better from search than frozen model)
        if (preg_match('/\b(?:weather|forecast|temperature|rain|snow)\b/i', $prompt)
            && preg_match('/\b(?:today|tomorrow|now|this week|tonight|in\s+\w+)\b/i', $prompt)
        ) {
            return true;
        }

        // Markets / scores / recent results
        if (preg_match('/\b(?:stock price|share price|crypto|bitcoin|score|match result)\b/i', $prompt)) {
            return true;
        }
        if (preg_match('/\b(?:won|winner|score|result)\b/i', $prompt)
            && preg_match('/\b(?:yesterday|today|last night|this week)\b/i', $prompt)
        ) {
            return true;
        }

        // Network / utility outages and cutovers (time-sensitive)
        if (preg_match('/\b(?:openreach|outage|cut\s*-?off|cutover|downtime|maintenance window)\b/i', $prompt)
            && preg_match('/\b(?:when|schedule|happen|start|end|today|tonight)\b/i', $prompt)
        ) {
            return true;
        }

        // Opening hours / events today
        if (preg_match('/\b(?:open now|opening hours|closes? at|event today)\b/i', $prompt)) {
            return true;
        }
        // Hours / schedule of a place (university, shop, museum…)
        if (preg_match(
            '/\b(?:opening times?|open(?:ing)? hours|what time (?:does|do|is)|when (?:does|do|is).*(?:open|close))\b/i',
            $prompt
        )) {
            return true;
        }

        // Nearby places / local pack (maps-style — not in LLM training)
        if (preg_match(
            '/\b(?:nearby|near me|closest|around here|in the area)\b/i',
            $prompt
        ) || preg_match(
            '/\b(?:coffee shops?|caf[eé]s?|restaurants?|pharmac(?:y|ies)|hospitals?|hotels?)\b/i',
            $prompt
        ) && preg_match('/\b(?:near|nearby|closest|around|in \w+)\b/i', $prompt)) {
            return true;
        }

        return false;
    }


    /**
     * Delegate external knowledge fetch to OpenRouterSkill (behavior unchanged).
     *
     * @param list<array<string, mixed>> $partialFacts
     * @param list<string> $constraints
     * @param list<string> $subtopics
     * @return array<string, mixed>
     */
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
        $path = __DIR__ . '/OpenRouterSkill.php';
        if (!is_file($path)) {
            return Helpers::error('OpenRouterSkill.php is missing.');
        }
        require_once $path;
        if (!class_exists('OpenRouterSkill', false)) {
            return Helpers::error('OpenRouterSkill class not found.');
        }
        $skill = new OpenRouterSkill();
        return $skill->request(
            $prompt,
            $coreQuery,
            $topic,
            $intent,
            $questionType,
            $partialFacts,
            $conversationContext,
            $constraints,
            $isMultiPart,
            $subtopics,
            $gapFill
        );
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
        // Definitions / conceptual questions must never trigger page templates
        if (in_array($intent, ['define_concept', 'explain_reason', 'compare_things', 'ask_information'], true)
            && !$this->looksLikeCodeRequest($prompt)) {
            return false;
        }
        if (in_array($intent, ['define_concept', 'explain_reason'], true)
            && !preg_match('/\b(?:make|create|write|generate|build)\b/i', $prompt)) {
            return false;
        }
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

        // Brief / definition: keep answers focused
        if ($intent === 'define_concept') {
            $lines = array_slice($lines, 0, 5);
        } elseif (in_array('brief', $constraints, true)) {
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
