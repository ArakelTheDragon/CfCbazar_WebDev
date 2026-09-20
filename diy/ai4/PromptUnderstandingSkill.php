<?php
// ============================================================================
// CfCbazar Group — PromptUnderstandingSkill.php
// File: /includes/skills/PromptUnderstandingSkill.php
//
// ROLE:
//   First-stage intent router for the normal AI pipeline.
//
// PIPELINE:
//   User Prompt
//       ↓
//   PromptUnderstandingSkill
//       ↓
//   KnowledgeSkill (knowledge/context broker)
//       ↓
//   Relevant Specialist Skill
//       ↓
//   Final Answer
//
// IMPORTANT:
//   This class determines WHAT the user wants.
//   It does not directly answer knowledge questions.
//
//   KnowledgeSkill is responsible for:
//     - local memory
//     - memory_topics/
//     - knowledge sufficiency
//     - OpenRouter lookup
//     - comparison/update of knowledge
//
//   DiagnosticSkill may still be explicitly forced by /ai/index.php.
// ============================================================================

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

if (!class_exists('PromptUnderstandingSkill')) {

    class PromptUnderstandingSkill implements AISkillInterface
    {
        /**
         * Priority triggers for the skill registry.
         */
        public function getKeywords(): array
        {
            return [
                'intent',
                'understand',
                'analyze prompt',
                'classify',
                'meaning',
                'prompt',
                'query',
                'route'
            ];
        }

        /**
         * Lower than explicit specialist routing priorities, but the
         * dispatcher intentionally executes this first for normal prompts.
         */
        public function getPriority(): int
        {
            return 5;
        }

        /**
         * Analyze the user's prompt and establish routing state.
         *
         * This method intentionally does NOT perform the actual knowledge
         * lookup. KnowledgeSkill handles that later in the pipeline.
         */
        public function execute(string $input, array &$memory): string
        {
            $rawInput = trim($input);
            $inputLower = strtolower($rawInput);

            // ----------------------------------------------------------------
            // Initialize parsed intent state.
            // ----------------------------------------------------------------

            $memory['parsed_intent'] = [
                'type'               => 'General Knowledge Query',
                'target_skill'       => 'KnowledgeSkill',
                'confidence'         => 0.70,
                'question_type'      => $this->detectQuestionType($inputLower),
                'matched_topic'      => null,
                'matched_content'    => '',
                'knowledge_required' => true,
                'extracted_entities' => $this->extractEntities($rawInput),
                'raw_input'          => $rawInput
            ];

            // =================================================================
            // 1. CALCULATOR
            // =================================================================
            //
            // Mathematical expressions should go directly to CalculatorSkill.
            // KnowledgeSkill does not need to mediate a deterministic
            // calculation.
            // =================================================================

            if ($this->isMathExpression($inputLower)) {

                $memory['parsed_intent']['type'] =
                    'Calculation Request';

                $memory['parsed_intent']['target_skill'] =
                    'CalculatorSkill';

                $memory['parsed_intent']['confidence'] =
                    0.98;

                $memory['parsed_intent']['knowledge_required'] =
                    false;

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 2. SUMMARIZER
            // =================================================================
            //
            // Summarization is a specialist operation.
            //
            // KnowledgeSkill may still be used later if the summarizer needs
            // contextual knowledge, but it should not replace the requested
            // specialist.
            // =================================================================

            if (preg_match(
                '/\b(summarize|summary|tl;dr|shorten|condense|key points|bullet points)\b/i',
                $rawInput
            )) {

                $memory['parsed_intent']['type'] =
                    'Summarization Request';

                $memory['parsed_intent']['target_skill'] =
                    'SummarizerSkill';

                $memory['parsed_intent']['confidence'] =
                    0.95;

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 3. DIAGNOSTICS
            // =================================================================
            //
            // Normal diagnostic requests are routed here.
            //
            // The explicit "diagnostic ai 0f" override is still handled by
            // /ai/index.php before this skill is executed.
            // =================================================================

            if (preg_match(
                '/\b(diagnose|diagnostic|system status|health check|log analysis|server test|debug system)\b/i',
                $rawInput
            )) {

                $memory['parsed_intent']['type'] =
                    'System Diagnostics';

                $memory['parsed_intent']['target_skill'] =
                    'DiagnosticSkill';

                $memory['parsed_intent']['confidence'] =
                    0.95;

                $memory['parsed_intent']['knowledge_required'] =
                    false;

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 4. EXPLICIT LEARNING / MEMORY COMMANDS
            // =================================================================
            //
            // KnowledgeSkill is now the central knowledge broker.
            //
            // We do NOT route these directly to SelfLearningSkill because
            // KnowledgeSkill owns the actual memory_topics/local_memory
            // persistence layer.
            //
            // SelfLearningSkill remains available as a specialist for
            // learning-pattern tasks when explicitly appropriate.
            // =================================================================

            if ($this->isExplicitLearningCommand($rawInput)) {

                $memory['parsed_intent']['type'] =
                    'Knowledge Learning Command';

                $memory['parsed_intent']['target_skill'] =
                    'KnowledgeSkill';

                $memory['parsed_intent']['confidence'] =
                    0.99;

                $memory['parsed_intent']['knowledge_required'] =
                    false;

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 5. BAYES / ML PATTERN TASKS
            // =================================================================
            //
            // Only route here when the prompt clearly represents a
            // probabilistic/pattern-processing task.
            // =================================================================

            $probabilisticTarget =
                $this->evaluateBayesTarget($inputLower);

            if ($probabilisticTarget !== null) {

                $memory['parsed_intent']['type'] =
                    'Pattern Classified Task';

                $memory['parsed_intent']['target_skill'] =
                    $probabilisticTarget['skill'];

                $memory['parsed_intent']['confidence'] =
                    $probabilisticTarget['confidence'];

                $memory['parsed_intent']['knowledge_required'] =
                    false;

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 6. KNOWLEDGE ROUTING
            // =================================================================
            //
            // This is the important architectural change.
            //
            // KnowledgeSkill is now the default broker for:
            //
            //   - what is...
            //   - who is...
            //   - explain...
            //   - tell me about...
            //   - factual questions
            //   - general information
            //   - local-memory lookups
            //   - questions requiring OpenRouter
            //
            // We DO NOT use findKnowledgeMatch() to decide whether
            // KnowledgeSkill should execute.
            //
            // KnowledgeSkill itself makes that decision.
            // =================================================================

            $knowledgeIntent =
                $this->isKnowledgeQuery($inputLower);

            if ($knowledgeIntent) {

                $memory['parsed_intent']['type'] =
                    'Knowledge Query';

                $memory['parsed_intent']['target_skill'] =
                    'KnowledgeSkill';

                $memory['parsed_intent']['confidence'] =
                    $this->knowledgeConfidence($inputLower);

                $memory['parsed_intent']['knowledge_required'] =
                    true;

                // Record an existing local topic as context if one exists.
                //
                // This is informational only. It must NOT cause the router
                // to bypass KnowledgeSkill.
                $match =
                    $this->findKnowledgeMatch(
                        $inputLower,
                        $memory
                    );

                if ($match !== null) {

                    $memory['parsed_intent']['matched_topic'] =
                        $match['topic'];

                    $memory['parsed_intent']['matched_content'] =
                        $match['content'];

                    $memory['parsed_intent']['confidence'] =
                        max(
                            $memory['parsed_intent']['confidence'],
                            $match['confidence']
                        );
                }

                return $this->formatAnalysisResponse(
                    $memory['parsed_intent']
                );
            }

            // =================================================================
            // 7. DEFAULT
            // =================================================================
            //
            // Unknown/general prompts still go through KnowledgeSkill.
            //
            // This allows KnowledgeSkill to determine whether:
            //   - local knowledge exists
            //   - OpenRouter is required
            //   - the request can be answered from context
            //
            // The dispatcher can subsequently route specialist work if a
            // future version adds more advanced intent classification.
            // =================================================================

            $memory['parsed_intent']['type'] =
                'General Query';

            $memory['parsed_intent']['target_skill'] =
                'KnowledgeSkill';

            $memory['parsed_intent']['confidence'] =
                0.60;

            $memory['parsed_intent']['knowledge_required'] =
                true;

            return $this->formatAnalysisResponse(
                $memory['parsed_intent']
            );
        }

        // =====================================================================
        // INTENT DETECTION HELPERS
        // =====================================================================

        /**
         * Detect arithmetic, equations and explicit calculation requests.
         */
        private function isMathExpression(string $inputLower): bool
        {
            // Pure numerical expression.
            if (
                preg_match(
                    '/^\s*[\d\.\+\-\*\/\^\%\s]+\s*$/',
                    $inputLower
                ) &&
                preg_match('/\d/', $inputLower)
            ) {
                return true;
            }

            // Explicit mathematical language.
            return (bool)preg_match(
                '/\b(calculate|compute|math|solve|add|subtract|multiply|divide|square root|sqrt|percentage|percent)\b/i',
                $inputLower
            );
        }

        /**
         * Detect explicit user instructions to save knowledge.
         */
        private function isExplicitLearningCommand(string $input): bool
        {
            return (bool)preg_match(
                '/^(remember|learn|note|teach me|save this|store this)\b/i',
                trim($input)
            );
        }

        /**
         * Determine whether a prompt should be handled by KnowledgeSkill.
         *
         * KnowledgeSkill is intentionally broad because it is the central
         * knowledge/memory broker.
         */
        private function isKnowledgeQuery(string $inputLower): bool
        {
            $patterns = [
                // Direct questions.
                '/^(what|who|where|when|why|which|how)\b/i',

                // Yes/no factual questions.
                '/^(is|are|does|do|did|can|could|will|would|should)\b/i',

                // Knowledge requests.
                '/\b(what is|who is|tell me about|explain|define|definition)\b/i',

                // Memory introspection.
                '/\b(what do you know|what topics|known topics|list knowledge|list topics|memory list|your knowledge)\b/i',

                // General factual/informational language.
                '/\b(information about|facts about|details about|knowledge about|learn about)\b/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $inputLower)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Estimate confidence for a knowledge route.
         */
        private function knowledgeConfidence(string $inputLower): float
        {
            if (preg_match(
                '/^(what is|who is|where is|when is|why is|how does|how do|tell me about|explain|define)\b/i',
                $inputLower
            )) {
                return 0.92;
            }

            if (preg_match(
                '/\b(what do you know|what topics|known topics|list knowledge|memory list)\b/i',
                $inputLower
            )) {
                return 0.99;
            }

            if (preg_match(
                '/^(is|are|does|do|did|can|could|will|would|should)\b/i',
                $inputLower
            )) {
                return 0.85;
            }

            return 0.75;
        }

        // =====================================================================
        // LOCAL KNOWLEDGE MATCH
        // =====================================================================

        /**
         * Find an existing topic in local memory.
         *
         * IMPORTANT:
         * This is context discovery only.
         *
         * It does NOT determine whether KnowledgeSkill executes.
         */
        private function findKnowledgeMatch(
            string $inputLower,
            array $memory
        ): ?array {

            if (
                !isset($memory['knowledge']) ||
                !is_array($memory['knowledge'])
            ) {
                return null;
            }

            $bestMatch = null;
            $highestScore = 0.0;

            foreach ($memory['knowledge'] as $topicKey => $data) {

                $topicStr =
                    strtolower((string)$topicKey);

                $topicClean =
                    str_replace(
                        ['_', '-'],
                        ' ',
                        $topicStr
                    );

                if (strlen($topicStr) < 2) {
                    continue;
                }

                $score = 0.0;

                // Exact topic.
                if (
                    $topicStr === $inputLower ||
                    $topicClean === $inputLower
                ) {
                    $score = 1.00;

                // Complete topic phrase.
                } elseif (
                    preg_match(
                        '/\b' .
                        preg_quote($topicClean, '/') .
                        '\b/i',
                        $inputLower
                    )
                ) {
                    $score = 0.90;

                // Topic appears somewhere in prompt.
                } elseif (
                    str_contains(
                        $inputLower,
                        $topicClean
                    ) ||
                    str_contains(
                        $inputLower,
                        $topicStr
                    )
                ) {
                    $score = 0.75;
                }

                if (
                    $score > $highestScore &&
                    $score >= 0.70
                ) {

                    $highestScore = $score;

                    $content = '';

                    if (is_array($data)) {

                        $content =
                            $data['info']
                            ?? $data['summary']
                            ?? $data['response']
                            ?? $data['content']
                            ?? '';

                    } else {

                        $content =
                            (string)$data;
                    }

                    $bestMatch = [
                        'topic'      => $topicStr,
                        'content'    => (string)$content,
                        'confidence' => $highestScore
                    ];
                }
            }

            return $bestMatch;
        }

        // =====================================================================
        // PROBABILISTIC ROUTING
        // =====================================================================

        /**
         * Evaluate deterministic/probabilistic pattern tasks.
         *
         * These routes are deliberately conservative so normal factual
         * questions continue through KnowledgeSkill.
         */
        private function evaluateBayesTarget(
            string $inputLower
        ): ?array {

            $skillMap = [

                'MLPatternSkill' => [
                    'pattern recognition',
                    'find a pattern',
                    'identify the pattern',
                    'pattern analysis',
                    'machine learning pattern'
                ],

                'BayesSkill' => [
                    'bayes',
                    'bayesian',
                    'conditional probability',
                    'posterior probability',
                    'prior probability'
                ]
            ];

            foreach ($skillMap as $skill => $keywords) {

                foreach ($keywords as $keyword) {

                    if (str_contains(
                        $inputLower,
                        $keyword
                    )) {
                        return [
                            'skill'       => $skill,
                            'confidence'  => 0.90
                        ];
                    }
                }
            }

            return null;
        }

        // =====================================================================
        // QUESTION CLASSIFICATION
        // =====================================================================

        /**
         * Classify basic question syntax.
         */
        private function detectQuestionType(
            string $inputLower
        ): string {

            if (preg_match(
                '/^(what|which)\b/',
                $inputLower
            )) {
                return 'definition / identity';
            }

            if (preg_match(
                '/^(how)\b/',
                $inputLower
            )) {
                return 'procedure / instruction';
            }

            if (preg_match(
                '/^(why)\b/',
                $inputLower
            )) {
                return 'reasoning / explanation';
            }

            if (preg_match(
                '/^(where|when|who)\b/',
                $inputLower
            )) {
                return 'factual query';
            }

            if (preg_match(
                '/^(can|is|are|does|do|will|should|could|would)\b/',
                $inputLower
            )) {
                return 'boolean confirmation';
            }

            return 'general instruction';
        }

        // =====================================================================
        // ENTITY EXTRACTION
        // =====================================================================

        /**
         * Extract meaningful entities from the prompt.
         */
        private function extractEntities(
            string $input
        ): array {

            $cleanInput = preg_replace(
                '/[^\p{L}\p{N}_\s-]/u',
                '',
                strtolower($input)
            );

            if ($cleanInput === null) {
                return [];
            }

            $words = preg_split(
                '/\s+/u',
                $cleanInput,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if (!is_array($words)) {
                return [];
            }

            $stopwords = [
                'a',
                'an',
                'the',
                'is',
                'are',
                'was',
                'were',
                'it',
                'to',
                'for',
                'of',
                'in',
                'on',
                'at',
                'by',
                'with',
                'and',
                'or',
                'what',
                'how',
                'why',
                'who',
                'where',
                'when',
                'which',
                'can',
                'could',
                'would',
                'will',
                'should',
                'you',
                'me',
                'my',
                'this',
                'that',
                'about',
                'tell'
            ];

            $filtered = array_filter(
                $words,
                function ($word) use ($stopwords) {
                    return mb_strlen($word) > 2 &&
                           !in_array(
                               $word,
                               $stopwords,
                               true
                           );
                }
            );

            return array_values(
                array_unique($filtered)
            );
        }

        // =====================================================================
        // ANALYSIS OUTPUT
        // =====================================================================

        /**
         * Generate a compact routing analysis.
         *
         * This is useful for the AI Hub interface and debugging the pipeline.
         */
        private function formatAnalysisResponse(
            array $intent
        ): string {

            $output =
                "### Prompt Analysis & Skill Routing\n\n";

            $output .=
                "Identified Intent: **" .
                ($intent['type'] ?? 'Unknown') .
                "** (Confidence: " .
                round(
                    ((float)($intent['confidence'] ?? 0)) * 100
                ) .
                "%)\n";

            $output .=
                "Target Execution Skill: **" .
                ($intent['target_skill'] ?? 'KnowledgeSkill') .
                "**\n";

            $output .=
                "Question Category: **" .
                ucfirst(
                    (string)(
                        $intent['question_type']
                        ?? 'general'
                    )
                ) .
                "**\n";

            $output .=
                "Knowledge Broker Required: **" .
                (
                    !empty($intent['knowledge_required'])
                        ? 'Yes'
                        : 'No'
                ) .
                "**\n";

            if (
                !empty($intent['extracted_entities']) &&
                is_array($intent['extracted_entities'])
            ) {

                $entities =
                    array_slice(
                        $intent['extracted_entities'],
                        0,
                        5
                    );

                $output .=
                    "Extracted Entities: `" .
                    implode(
                        '`, `',
                        $entities
                    ) .
                    "`\n";
            }

            if (
                !empty($intent['matched_topic'])
            ) {

                $output .=
                    "Existing Local Topic: **" .
                    ucfirst(
                        str_replace(
                            ['_', '-'],
                            ' ',
                            (string)$intent['matched_topic']
                        )
                    ) .
                    "**\n";

                // Do not dump the entire local knowledge content into the
                // router response. KnowledgeSkill owns the actual retrieval.
                $output .=
                    "Local Knowledge Match: **Available**\n";

            } else {

                $output .=
                    "Existing Local Topic: **Not identified**\n";
            }

            $output .=
                "Parsed Input: \"" .
                htmlspecialchars(
                    (string)($intent['raw_input'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                "\"";

            return trim($output);
        }
    }
}
?>