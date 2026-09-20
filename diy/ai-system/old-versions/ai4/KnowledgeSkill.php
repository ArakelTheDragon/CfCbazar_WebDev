<?php
// ============================================================================
// CfCbazar Group — KnowledgeSkill.php
// File: /includes/skills/KnowledgeSkill.php
//
// ROLE
// ----------------------------------------------------------------------------
// Central knowledge broker for the CfCbazar AI pipeline.
//
// Responsibilities:
//
//   - Local memory introspection
//   - Explicit user learning
//   - Search local_memory.json
//   - Search memory_topics/*.json
//   - Determine whether local knowledge is relevant
//   - Query OpenRouter when additional knowledge is required
//   - Compare local knowledge with new OpenRouter information
//   - Update/create topic files when useful
//   - Provide knowledge/context to specialist skills
//
// IMPORTANT
// ----------------------------------------------------------------------------
// KnowledgeSkill does NOT have to generate the final answer when another
// specialist has been selected by PromptUnderstandingSkill.
//
// In that situation it places relevant information into:
//
//     $memory['knowledge_context']
//
// and returns an empty string.
//
// The dispatcher can then execute:
//
//     CalculatorSkill
//     SummarizerSkill
//     BayesSkill
//     MLPatternSkill
//     DiagnosticSkill
//     SelfLearningSkill
//     etc.
//
// Explicit knowledge requests targeting KnowledgeSkill may return a final
// answer directly.
//
// File: /includes/skills/KnowledgeSkill.php
// ============================================================================

if (!class_exists('KnowledgeSkill')) {

    class KnowledgeSkill implements AISkillInterface
    {
        /**
         * Directory containing individual topic JSON files.
         */
        private string $topicsDir;


        /**
         * Constructor.
         *
         * The existing constructor signature is retained for compatibility
         * with ai/index.php.
         */
        public function __construct(
            string $topicsDir = __DIR__ . '/../memory_topics'
        ) {
            $this->topicsDir = rtrim(
                $topicsDir,
                '/\\'
            );
        }


        /**
         * Keywords used by the general skill system.
         */
        public function getKeywords(): array
        {
            return [
                'remember',
                'learn',
                'what is',
                'who is',
                'tell me about',
                'fact',
                'facts',
                'topics',
                'what do you know',
                'what topics',
                'known topics',
                'list knowledge',
                'list topics',
                'your knowledge',
                'memory list',
                'memory',
                'knowledge'
            ];
        }


        /**
         * Knowledge priority.
         */
        public function getPriority(): int
        {
            return 9;
        }


        // ====================================================================
        // MAIN EXECUTION
        // ====================================================================

        public function execute(
            string $input,
            array &$memory
        ): string {

            $input = trim($input);

            if ($input === '') {
                return '';
            }


            // ---------------------------------------------------------------
            // Ensure required memory structures exist.
            // ---------------------------------------------------------------

            if (
                !isset($memory['knowledge']) ||
                !is_array($memory['knowledge'])
            ) {
                $memory['knowledge'] = [];
            }

            if (
                !isset($memory['history']) ||
                !is_array($memory['history'])
            ) {
                $memory['history'] = [];
            }

            if (
                !isset($memory['knowledge_context']) ||
                !is_array($memory['knowledge_context'])
            ) {
                $memory['knowledge_context'] = [];
            }


            // ---------------------------------------------------------------
            // Ensure topic directory exists.
            // ---------------------------------------------------------------

            if (!is_dir($this->topicsDir)) {

                @mkdir(
                    $this->topicsDir,
                    0755,
                    true
                );
            }


            $now = date('Y-m-d H:i:s');

            $inputLower = strtolower($input);


            // =================================================================
            // 1. EXPLICIT MEMORY INTROSPECTION
            // =================================================================
            //
            // These requests are handled directly because the user is asking
            // about the AI's stored memory rather than asking a specialist
            // skill to process something.
            // =================================================================

            if (
                preg_match(
                    '/what (topics|facts)|what do you know|known topics|list (knowledge|topics|memory)|your knowledge|memory list/i',
                    $inputLower
                )
            ) {

                return $this->buildKnowledgeList(
                    $memory
                );
            }


            // =================================================================
            // 2. EXPLICIT USER LEARNING
            // =================================================================
            //
            // Example:
            //
            //   remember cfcbazar is a website
            //
            // User-provided information has priority over automatically
            // retrieved information.
            // =================================================================

            if (
                preg_match(
                    '/^\s*remember\s+(.+?)\s+is\s+(.+?)\s*$/is',
                    $input,
                    $matches
                )
            ) {

                $topic = strtolower(
                    trim($matches[1])
                );

                $info = trim(
                    $matches[2]
                );


                if (
                    $topic !== '' &&
                    $info !== ''
                ) {

                    $this->saveUserKnowledge(
                        $topic,
                        $info,
                        $memory,
                        $now
                    );

                    // User explicitly taught the system something.
                    // Make it immediately available to the rest of the
                    // current pipeline.
                    $memory['knowledge_context'] = [
                        [
                            'topic'  => $topic,
                            'source' => 'Local User',
                            'content' => $info,
                            'status' => 'user_provided'
                        ]
                    ];

                    return "Saved new knowledge about '{$topic}'.";
                }
            }


            // =================================================================
            // 3. DETERMINE TARGET SKILL
            // =================================================================
            //
            // PromptUnderstandingSkill normally writes the target skill here.
            //
            // If it is unavailable, KnowledgeSkill treats the request as a
            // knowledge request.
            // =================================================================

            $targetSkill = '';

            if (
                isset($memory['parsed_intent']) &&
                is_array($memory['parsed_intent'])
            ) {

                $targetSkill = trim(
                    (string)(
                        $memory['parsed_intent']['target_skill']
                        ?? ''
                    )
                );
            }


            // =================================================================
            // 4. SEARCH LOCAL MEMORY
            // =================================================================

            $localMatches = $this->findLocalKnowledge(
                $input,
                $memory
            );


            // =================================================================
            // 5. SPECIALIST ROUTING
            // =================================================================
            //
            // Some skills do not require external factual knowledge.
            //
            // Calculator:
            //     Calculate directly.
            //
            // Summarizer:
            //     Summarize supplied material.
            //
            // Diagnostic:
            //     Run diagnostics.
            //
            // Bayes / MLPattern:
            //     Process their supplied data.
            //
            // Knowledge-oriented requests, however, can use local memory and
            // OpenRouter.
            // =================================================================

            $requiresExternalKnowledge =
                $this->requiresExternalKnowledge(
                    $targetSkill,
                    $input
                );


            // =================================================================
            // 6. LOCAL KNOWLEDGE FOUND
            // =================================================================

            if (!empty($localMatches)) {

                $context = $this->buildKnowledgeContext(
                    $localMatches
                );

                $memory['knowledge_context'] =
                    $context;


                // -------------------------------------------------------------
                // If another specialist was selected, do NOT terminate the
                // pipeline. The specialist receives the context through the
                // shared memory array.
                // -------------------------------------------------------------

                if (
                    $targetSkill !== '' &&
                    $targetSkill !== 'KnowledgeSkill'
                ) {

                    // For specialist tasks, local knowledge is sufficient
                    // unless the specialist explicitly needs outside factual
                    // information.
                    if (!$requiresExternalKnowledge) {
                        return '';
                    }
                }


                // -------------------------------------------------------------
                // For a direct KnowledgeSkill request, local knowledge is the
                // first answer.
                //
                // We do not automatically overwrite it with OpenRouter data.
                // -------------------------------------------------------------

                if (
                    $targetSkill === '' ||
                    $targetSkill === 'KnowledgeSkill'
                ) {

                    $answer =
                        $this->formatLocalKnowledgeAnswer(
                            $localMatches
                        );

                    return $answer;
                }
            }


            // =================================================================
            // 7. NO LOCAL KNOWLEDGE
            // =================================================================
            //
            // If a specialist does not require outside knowledge, let the
            // specialist handle the prompt.
            // =================================================================

            if (
                !$requiresExternalKnowledge &&
                $targetSkill !== '' &&
                $targetSkill !== 'KnowledgeSkill'
            ) {

                $memory['knowledge_context'] = [];

                return '';
            }


            // =================================================================
            // 8. OPENROUTER KNOWLEDGE LOOKUP
            // =================================================================
            //
            // KnowledgeSkill is now the only component responsible for this
            // stage.
            // =================================================================

            if (!function_exists('queryOpenRouter')) {

                // If this is a specialist request, let the specialist run
                // without external knowledge.
                if (
                    $targetSkill !== '' &&
                    $targetSkill !== 'KnowledgeSkill'
                ) {

                    return '';
                }

                return "I don't know that yet. Teach me using: remember [topic] is [fact].";
            }


            $openRouterReply =
                queryOpenRouter($input);


            if (
                !is_string($openRouterReply) ||
                trim($openRouterReply) === '' ||
                $this->isOpenRouterError(
                    $openRouterReply
                )
            ) {

                if (
                    $targetSkill !== '' &&
                    $targetSkill !== 'KnowledgeSkill'
                ) {

                    return '';
                }

                return "I don't know that yet. Teach me using: remember [topic] is [fact].";
            }


            $openRouterReply =
                trim($openRouterReply);


            // =================================================================
            // 9. EXTRACT TOPIC
            // =================================================================

            $extractedTopic =
                $this->extractTopic(
                    $input
                );


            if ($extractedTopic === '') {

                // Do not create a meaningless memory file.
                $memory['knowledge_context'] = [
                    [
                        'topic'   => 'current_query',
                        'source'  => 'OpenRouter AI',
                        'content' => $openRouterReply,
                        'status'  => 'external'
                    ]
                ];

                if (
                    $targetSkill !== '' &&
                    $targetSkill !== 'KnowledgeSkill'
                ) {
                    return '';
                }

                return $openRouterReply;
            }


            // =================================================================
            // 10. COMPARE NEW KNOWLEDGE WITH LOCAL MEMORY
            // =================================================================
            //
            // This prevents blindly replacing existing information.
            // =================================================================

            $existingMatch =
                $this->findExactTopic(
                    $extractedTopic,
                    $memory
                );


            if ($existingMatch !== null) {

                $existingContent =
                    (string)(
                        $existingMatch['content']
                        ?? ''
                    );


                $comparison =
                    $this->compareKnowledge(
                        $existingContent,
                        $openRouterReply
                    );


                // -------------------------------------------------------------
                // Identical / essentially identical information.
                // -------------------------------------------------------------

                if (
                    $comparison === 'same'
                ) {

                    $memory['knowledge_context'] =
                        [
                            [
                                'topic'   => $extractedTopic,
                                'source'  => 'Local Memory',
                                'content' => $existingContent,
                                'status'  => 'existing'
                            ]
                        ];


                    // Update only the timestamp/history if desired.
                    $this->recordKnowledgeCheck(
                        $extractedTopic,
                        $openRouterReply,
                        $now
                    );


                    if (
                        $targetSkill !== '' &&
                        $targetSkill !== 'KnowledgeSkill'
                    ) {
                        return '';
                    }


                    return $this->formatKnowledgeAnswer(
                        $extractedTopic,
                        $existingContent
                    );
                }


                // -------------------------------------------------------------
                // New/better information.
                // -------------------------------------------------------------

                if (
                    $comparison === 'update'
                ) {

                    $this->updateTopicFromOpenRouter(
                        $extractedTopic,
                        $openRouterReply,
                        $memory,
                        $now
                    );


                    $memory['knowledge_context'] =
                        [
                            [
                                'topic'   => $extractedTopic,
                                'source'  => 'OpenRouter AI',
                                'content' => $openRouterReply,
                                'status'  => 'updated'
                            ]
                        ];


                    if (
                        $targetSkill !== '' &&
                        $targetSkill !== 'KnowledgeSkill'
                    ) {
                        return '';
                    }


                    return $this->formatKnowledgeAnswer(
                        $extractedTopic,
                        $openRouterReply
                    );
                }


                // -------------------------------------------------------------
                // Conflict.
                //
                // Do not silently destroy existing information.
                // Preserve the existing entry and record the new source.
                // -------------------------------------------------------------

                if (
                    $comparison === 'conflict'
                ) {

                    $this->recordConflict(
                        $extractedTopic,
                        $openRouterReply,
                        $memory,
                        $now
                    );


                    $memory['knowledge_context'] =
                        [
                            [
                                'topic'   => $extractedTopic,
                                'source'  => 'Local Memory + OpenRouter AI',
                                'content' =>
                                    "Existing local knowledge:\n" .
                                    $existingContent .
                                    "\n\nNew external information:\n" .
                                    $openRouterReply,
                                'status'  => 'conflict'
                            ]
                        ];


                    if (
                        $targetSkill !== '' &&
                        $targetSkill !== 'KnowledgeSkill'
                    ) {
                        return '';
                    }


                    return
                        "I found conflicting information in local memory and the external knowledge source.\n\n" .
                        "### Existing Local Knowledge\n" .
                        $existingContent .
                        "\n\n### New Information\n" .
                        $openRouterReply .
                        "\n\nThe existing knowledge was preserved and the new information was recorded for review.";
                }
            }


            // =================================================================
            // 11. CREATE NEW TOPIC
            // =================================================================

            $this->createTopicFromOpenRouter(
                $extractedTopic,
                $openRouterReply,
                $memory,
                $now
            );


            $memory['knowledge_context'] =
                [
                    [
                        'topic'   => $extractedTopic,
                        'source'  => 'OpenRouter AI',
                        'content' => $openRouterReply,
                        'status'  => 'new'
                    ]
                ];


            // =================================================================
            // 12. SPECIALIST CONTINUES
            // =================================================================

            if (
                $targetSkill !== '' &&
                $targetSkill !== 'KnowledgeSkill'
            ) {

                return '';
            }


            // =================================================================
            // 13. DIRECT KNOWLEDGE RESPONSE
            // =================================================================

            return $this->formatKnowledgeAnswer(
                $extractedTopic,
                $openRouterReply
            );
        }


        // ====================================================================
        // MEMORY INTROSPECTION
        // ====================================================================

        private function buildKnowledgeList(
            array $memory
        ): string {

            if (
                empty($memory['knowledge']) ||
                !is_array($memory['knowledge'])
            ) {

                return
                    "I don't have any specific topics saved in my local memory index yet.";
            }


            $output =
                "### Currently Stored Knowledge Topics\n\n";


            foreach (
                $memory['knowledge']
                as $topic => $data
            ) {

                if (is_array($data)) {

                    $summary =
                        trim(
                            (string)(
                                $data['summary']
                                ?? 'No summary available.'
                            )
                        );

                } else {

                    $summary =
                        trim(
                            (string)$data
                        );
                }


                $output .=
                    "* **" .
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            (string)$topic
                        )
                    ) .
                    "**: " .
                    $summary .
                    "\n";
            }


            return trim($output);
        }


        // ====================================================================
        // DETERMINE WHETHER EXTERNAL KNOWLEDGE IS REQUIRED
        // ====================================================================

        private function requiresExternalKnowledge(
            string $targetSkill,
            string $input
        ): bool {

            if ($targetSkill === '') {
                return true;
            }


            if (
                in_array(
                    $targetSkill,
                    [
                        'CalculatorSkill',
                        'SummarizerSkill',
                        'BayesSkill',
                        'MLPatternSkill',
                        'DiagnosticSkill'
                    ],
                    true
                )
            ) {

                return false;
            }


            if (
                $targetSkill === 'SelfLearningSkill'
            ) {

                return false;
            }


            if (
                $targetSkill === 'KnowledgeSkill'
            ) {

                return true;
            }


            return true;
        }


        // ====================================================================
        // FIND LOCAL KNOWLEDGE
        // ====================================================================

        private function findLocalKnowledge(
            string $input,
            array $memory
        ): array {

            $matches = [];

            if (
                empty($memory['knowledge']) ||
                !is_array($memory['knowledge'])
            ) {

                return $matches;
            }


            $inputLower =
                strtolower($input);


            foreach (
                $memory['knowledge']
                as $topic => $data
            ) {

                $topicName =
                    strtolower(
                        trim(
                            (string)$topic
                        )
                    );


                if ($topicName === '') {
                    continue;
                }


                $topicWords =
                    preg_split(
                        '/[\s_\-]+/',
                        $topicName,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );


                $topicMatched =
                    stripos(
                        $inputLower,
                        $topicName
                    ) !== false;


                // Also allow multi-word topics to match when all meaningful
                // words occur in the prompt.
                if (
                    !$topicMatched &&
                    is_array($topicWords) &&
                    count($topicWords) > 0
                ) {

                    $topicMatched = true;

                    foreach (
                        $topicWords
                        as $word
                    ) {

                        if (
                            strlen($word) >= 3 &&
                            stripos(
                                $inputLower,
                                $word
                            ) === false
                        ) {

                            $topicMatched = false;
                            break;
                        }
                    }
                }


                if (!$topicMatched) {
                    continue;
                }


                $content = '';
                $file = '';


                if (is_array($data)) {

                    $file =
                        basename(
                            (string)(
                                $data['file']
                                ?? ''
                            )
                        );
                }


                if ($file !== '') {

                    $fullFilePath =
                        $this->topicsDir .
                        DIRECTORY_SEPARATOR .
                        $file;


                    if (
                        is_file($fullFilePath)
                    ) {

                        $json =
                            @file_get_contents(
                                $fullFilePath
                            );


                        if (
                            $json !== false
                        ) {

                            $topicJson =
                                json_decode(
                                    $json,
                                    true
                                );


                            if (
                                is_array($topicJson)
                            ) {

                                $content =
                                    trim(
                                        (string)(
                                            $topicJson['content']
                                            ??
                                            $topicJson['detail']
                                            ??
                                            ''
                                        )
                                    );
                            }
                        }
                    }
                }


                if (
                    $content === '' &&
                    is_array($data)
                ) {

                    $content =
                        trim(
                            (string)(
                                $data['summary']
                                ?? ''
                            )
                        );
                }


                if (
                    $content === '' &&
                    !is_array($data)
                ) {

                    $content =
                        trim(
                            (string)$data
                        );
                }


                if ($content !== '') {

                    $matches[] = [
                        'topic'   => (string)$topic,
                        'content' => $content,
                        'source'  => 'Local Memory'
                    ];
                }
            }


            return $matches;
        }


        // ====================================================================
        // BUILD KNOWLEDGE CONTEXT
        // ====================================================================

        private function buildKnowledgeContext(
            array $matches
        ): array {

            $context = [];


            foreach (
                $matches
                as $match
            ) {

                $context[] = [
                    'topic' =>
                        (string)(
                            $match['topic']
                            ?? ''
                        ),

                    'source' =>
                        (string)(
                            $match['source']
                            ?? 'Local Memory'
                        ),

                    'content' =>
                        (string)(
                            $match['content']
                            ?? ''
                        ),

                    'status' =>
                        'existing'
                ];
            }


            return $context;
        }


        // ====================================================================
        // FORMAT LOCAL KNOWLEDGE
        // ====================================================================

        private function formatLocalKnowledgeAnswer(
            array $matches
        ): string {

            $output = '';


            foreach (
                $matches
                as $match
            ) {

                $topic =
                    (string)(
                        $match['topic']
                        ?? 'Knowledge'
                    );

                $content =
                    (string)(
                        $match['content']
                        ?? ''
                    );


                if ($output !== '') {
                    $output .= "\n\n";
                }


                $output .=
                    "Knowledge [" .
                    $topic .
                    "]: " .
                    $content;
            }


            return $output !== ''
                ? $output
                : "I don't have that information in local memory.";
        }


        // ====================================================================
        // FORMAT KNOWLEDGE ANSWER
        // ====================================================================

        private function formatKnowledgeAnswer(
            string $topic,
            string $content
        ): string {

            return
                "Knowledge [" .
                $topic .
                "]:\n\n" .
                $content;
        }


        // ====================================================================
        // EXTRACT TOPIC
        // ====================================================================

        private function extractTopic(
            string $input
        ): string {

            $topic = trim($input);


            $topic = preg_replace(
                '/^\s*(what\s+is|what\s+are|who\s+is|who\s+are|tell\s+me\s+about|explain|describe|define|fact\s+about)\s+/i',
                '',
                $topic
            );


            $topic = preg_replace(
                '/\?\s*$/',
                '',
                $topic
            );


            $topic = trim(
                (string)$topic
            );


            // Avoid creating excessively large filenames/topics.
            if (
                strlen($topic) > 150
            ) {

                $topic =
                    substr(
                        $topic,
                        0,
                        150
                    );
            }


            return strtolower($topic);
        }


        // ====================================================================
        // CREATE SLUG
        // ====================================================================

        private function createSlug(
            string $topic
        ): string {

            $slug =
                strtolower(
                    trim($topic)
                );


            $slug =
                preg_replace(
                    '/[^a-z0-9]+/',
                    '_',
                    $slug
                );


            $slug =
                trim(
                    (string)$slug,
                    '_'
                );


            if ($slug === '') {
                $slug = 'general_knowledge';
            }


            return $slug;
        }


        // ====================================================================
        // SAVE USER KNOWLEDGE
        // ====================================================================

        private function saveUserKnowledge(
            string $topic,
            string $info,
            array &$memory,
            string $now
        ): void {

            $slug =
                $this->createSlug(
                    $topic
                );


            $filename =
                $slug . '.json';


            $relativeFilePath =
                'memory_topics/' .
                $filename;


            $fullFilePath =
                $this->topicsDir .
                DIRECTORY_SEPARATOR .
                $filename;


            $existingData = [];


            if (
                is_file($fullFilePath)
            ) {

                $existingJson =
                    @file_get_contents(
                        $fullFilePath
                    );


                if (
                    $existingJson !== false
                ) {

                    $decoded =
                        json_decode(
                            $existingJson,
                            true
                        );


                    if (
                        is_array($decoded)
                    ) {
                        $existingData =
                            $decoded;
                    }
                }
            }


            if (
                !isset(
                    $existingData['updates']
                ) ||
                !is_array(
                    $existingData['updates']
                )
            ) {

                $existingData['updates'] = [];
            }


            $existingData['topic'] =
                $topic;

            $existingData['last_updated'] =
                $now;

            $existingData['summary'] =
                substr(
                    $info,
                    0,
                    120
                );

            $existingData['content'] =
                $info;

            $existingData['detail'] =
                $info;


            $existingData['updates'][] = [

                'timestamp' =>
                    $now,

                'source' =>
                    'Local User',

                'note' =>
                    'User-provided knowledge saved or updated.',

                'content' =>
                    $info
            ];


            $this->writeTopicFile(
                $fullFilePath,
                $existingData
            );


            $memory['knowledge'][$topic] = [

                'file' =>
                    $relativeFilePath,

                'summary' =>
                    substr(
                        $info,
                        0,
                        120
                    )
            ];
        }


        // ====================================================================
        // FIND EXACT TOPIC
        // ====================================================================

        private function findExactTopic(
            string $topic,
            array $memory
        ): ?array {

            if (
                empty($memory['knowledge']) ||
                !is_array($memory['knowledge'])
            ) {
                return null;
            }


            $topicLower =
                strtolower(
                    trim($topic)
                );


            foreach (
                $memory['knowledge']
                as $storedTopic => $data
            ) {

                if (
                    strtolower(
                        trim(
                            (string)$storedTopic
                        )
                    ) !== $topicLower
                ) {
                    continue;
                }


                $content = '';


                if (is_array($data)) {

                    $filename =
                        basename(
                            (string)(
                                $data['file']
                                ?? ''
                            )
                        );


                    if (
                        $filename !== ''
                    ) {

                        $fullFilePath =
                            $this->topicsDir .
                            DIRECTORY_SEPARATOR .
                            $filename;


                        if (
                            is_file(
                                $fullFilePath
                            )
                        ) {

                            $json =
                                @file_get_contents(
                                    $fullFilePath
                                );


                            if (
                                $json !== false
                            ) {

                                $topicJson =
                                    json_decode(
                                        $json,
                                        true
                                    );


                                if (
                                    is_array($topicJson)
                                ) {

                                    $content =
                                        trim(
                                            (string)(
                                                $topicJson['content']
                                                ??
                                                $topicJson['detail']
                                                ??
                                                ''
                                            )
                                        );
                                }
                            }
                        }
                    }


                    if (
                        $content === ''
                    ) {

                        $content =
                            trim(
                                (string)(
                                    $data['summary']
                                    ?? ''
                                )
                            );
                    }

                } else {

                    $content =
                        trim(
                            (string)$data
                        );
                }


                return [
                    'topic' =>
                        (string)$storedTopic,

                    'content' =>
                        $content
                ];
            }


            return null;
        }


        // ====================================================================
        // COMPARE KNOWLEDGE
        // ====================================================================
        //
        // Returns:
        //
        //   same
        //   update
        //   conflict
        //
        // The comparison is deliberately conservative.
        //
        // Exact/near-identical information:
        //     keep local information.
        //
        // Clearly different information:
        //     use OpenRouter as a potential update.
        //
        // Potential contradiction:
        //     preserve both and record conflict.
        // ====================================================================

        private function compareKnowledge(
            string $localContent,
            string $newContent
        ): string {

            $localNormalized =
                $this->normalizeKnowledgeText(
                    $localContent
                );


            $newNormalized =
                $this->normalizeKnowledgeText(
                    $newContent
                );


            if (
                $localNormalized === '' ||
                $newNormalized === ''
            ) {

                return 'update';
            }


            if (
                $localNormalized ===
                $newNormalized
            ) {

                return 'same';
            }


            // ---------------------------------------------------------------
            // Calculate a basic word overlap.
            //
            // This is intentionally local and deterministic so identical or
            // near-identical OpenRouter responses do not generate unnecessary
            // memory updates.
            // ---------------------------------------------------------------

            $localWords =
                array_unique(
                    preg_split(
                        '/\s+/',
                        $localNormalized,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    )
                );


            $newWords =
                array_unique(
                    preg_split(
                        '/\s+/',
                        $newNormalized,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    )
                );


            if (
                empty($localWords) ||
                empty($newWords)
            ) {

                return 'update';
            }


            $intersection =
                array_intersect(
                    $localWords,
                    $newWords
                );


            $union =
                array_unique(
                    array_merge(
                        $localWords,
                        $newWords
                    )
                );


            $similarity =
                count($intersection) /
                max(
                    1,
                    count($union)
                );


            if (
                $similarity >= 0.75
            ) {

                return 'same';
            }


            // ---------------------------------------------------------------
            // Look for obvious contradiction indicators.
            //
            // This does not claim to perform full semantic fact checking.
            // It simply avoids silently replacing information where there is
            // a strong textual indication of disagreement.
            // ---------------------------------------------------------------

            $conflictIndicators = [

                'however',

                'but',

                'incorrect',

                'wrong',

                'not',

                'instead',

                'contrary',

                'conflicts',

                'different',

                'disputed',

                'according to newer information'
            ];


            foreach (
                $conflictIndicators
                as $indicator
            ) {

                if (
                    stripos(
                        $newContent,
                        $indicator
                    ) !== false
                ) {

                    return 'conflict';
                }
            }


            // Different information is treated as a potential update.
            return 'update';
        }


        // ====================================================================
        // NORMALIZE KNOWLEDGE TEXT
        // ====================================================================

        private function normalizeKnowledgeText(
            string $text
        ): string {

            $text =
                strtolower(
                    trim($text)
                );


            $text =
                preg_replace(
                    '/\s+/',
                    ' ',
                    $text
                );


            $text =
                preg_replace(
                    '/[^\p{L}\p{N}\s]/u',
                    '',
                    (string)$text
                );


            return trim(
                (string)$text
            );
        }


        // ====================================================================
        // CREATE NEW OPENROUTER TOPIC
        // ====================================================================

        private function createTopicFromOpenRouter(
            string $topic,
            string $content,
            array &$memory,
            string $now
        ): void {

            $slug =
                $this->createSlug(
                    $topic
                );


            $filename =
                $slug . '.json';


            $relativeFilePath =
                'memory_topics/' .
                $filename;


            $fullFilePath =
                $this->topicsDir .
                DIRECTORY_SEPARATOR .
                $filename;


            $summary =
                $this->makeSummary(
                    $content
                );


            $topicData = [

                'topic' =>
                    $topic,

                'last_updated' =>
                    $now,

                'summary' =>
                    $summary,

                'content' =>
                    $content,

                'detail' =>
                    $content,

                'updates' => [

                    [
                        'timestamp' =>
                            $now,

                        'source' =>
                            'OpenRouter AI',

                        'note' =>
                            'Created new knowledge topic from external knowledge lookup.',

                        'content' =>
                            $content
                    ]
                ]
            ];


            $this->writeTopicFile(
                $fullFilePath,
                $topicData
            );


            $memory['knowledge'][$topic] = [

                'file' =>
                    $relativeFilePath,

                'summary' =>
                    $summary
            ];
        }


        // ====================================================================
        // UPDATE EXISTING TOPIC
        // ====================================================================

        private function updateTopicFromOpenRouter(
            string $topic,
            string $content,
            array &$memory,
            string $now
        ): void {

            $slug =
                $this->createSlug(
                    $topic
                );


            $filename =
                $slug . '.json';


            $relativeFilePath =
                'memory_topics/' .
                $filename;


            $fullFilePath =
                $this->topicsDir .
                DIRECTORY_SEPARATOR .
                $filename;


            $topicData = [];


            if (
                is_file($fullFilePath)
            ) {

                $json =
                    @file_get_contents(
                        $fullFilePath
                    );


                if (
                    $json !== false
                ) {

                    $decoded =
                        json_decode(
                            $json,
                            true
                        );


                    if (
                        is_array($decoded)
                    ) {

                        $topicData =
                            $decoded;
                    }
                }
            }


            if (
                !isset($topicData['updates']) ||
                !is_array($topicData['updates'])
            ) {

                $topicData['updates'] = [];
            }


            $previousContent =
                (string)(
                    $topicData['content']
                    ?? ''
                );


            $topicData['topic'] =
                $topic;

            $topicData['last_updated'] =
                $now;

            $topicData['summary'] =
                $this->makeSummary(
                    $content
                );

            $topicData['content'] =
                $content;

            $topicData['detail'] =
                $content;


            $topicData['updates'][] = [

                'timestamp' =>
                    $now,

                'source' =>
                    'OpenRouter AI',

                'note' =>
                    'Existing topic updated after comparison with new external knowledge.',

                'previous_content' =>
                    $previousContent,

                'content' =>
                    $content
            ];


            $this->writeTopicFile(
                $fullFilePath,
                $topicData
            );


            $memory['knowledge'][$topic] = [

                'file' =>
                    $relativeFilePath,

                'summary' =>
                    $topicData['summary']
            ];
        }


        // ====================================================================
        // RECORD SAME-KNOWLEDGE CHECK
        // ====================================================================

        private function recordKnowledgeCheck(
            string $topic,
            string $externalContent,
            string $now
        ): void {

            $slug =
                $this->createSlug(
                    $topic
                );


            $filename =
                $slug . '.json';


            $fullFilePath =
                $this->topicsDir .
                DIRECTORY_SEPARATOR .
                $filename;


            if (
                !is_file($fullFilePath)
            ) {
                return;
            }


            $json =
                @file_get_contents(
                    $fullFilePath
                );


            if (
                $json === false
            ) {
                return;
            }


            $topicData =
                json_decode(
                    $json,
                    true
                );


            if (
                !is_array($topicData)
            ) {
                return;
            }


            if (
                !isset($topicData['updates']) ||
                !is_array($topicData['updates'])
            ) {

                $topicData['updates'] = [];
            }


            $topicData['updates'][] = [

                'timestamp' =>
                    $now,

                'source' =>
                    'OpenRouter AI',

                'note' =>
                    'External information checked and found essentially identical to local knowledge.'
            ];


            $this->writeTopicFile(
                $fullFilePath,
                $topicData
            );
        }


        // ====================================================================
        // RECORD CONFLICT
        // ====================================================================

        private function recordConflict(
            string $topic,
            string $externalContent,
            array &$memory,
            string $now
        ): void {

            $slug =
                $this->createSlug(
                    $topic
                );


            $filename =
                $slug . '.json';


            $fullFilePath =
                $this->topicsDir .
                DIRECTORY_SEPARATOR .
                $filename;


            $topicData = [];


            if (
                is_file($fullFilePath)
            ) {

                $json =
                    @file_get_contents(
                        $fullFilePath
                    );


                if (
                    $json !== false
                ) {

                    $decoded =
                        json_decode(
                            $json,
                            true
                        );


                    if (
                        is_array($decoded)
                    ) {

                        $topicData =
                            $decoded;
                    }
                }
            }


            if (
                !isset($topicData['updates']) ||
                !is_array($topicData['updates'])
            ) {

                $topicData['updates'] = [];
            }


            $topicData['updates'][] = [

                'timestamp' =>
                    $now,

                'source' =>
                    'OpenRouter AI',

                'type' =>
                    'conflict',

                'note' =>
                    'Potential conflict detected. Existing local knowledge was preserved.',

                'new_information' =>
                    $externalContent
            ];


            $topicData['last_external_check'] =
                $now;


            $this->writeTopicFile(
                $fullFilePath,
                $topicData
            );


            // Keep the local index unchanged because the local knowledge was
            // deliberately preserved.
            if (
                !isset(
                    $memory['knowledge'][$topic]
                )
            ) {

                $memory['knowledge'][$topic] = [

                    'file' =>
                        'memory_topics/' .
                        $filename,

                    'summary' =>
                        $this->makeSummary(
                            (string)(
                                $topicData['content']
                                ?? ''
                            )
                        )
                ];
            }
        }


        // ====================================================================
        // MAKE SHORT SUMMARY
        // ====================================================================

        private function makeSummary(
            string $content
        ): string {

            $content =
                trim($content);


            $lines =
                preg_split(
                    '/\R+/',
                    $content
                );


            foreach (
                $lines
                as $line
            ) {

                $line =
                    trim(
                        (string)$line
                    );


                if (
                    $line !== ''
                ) {

                    return substr(
                        $line,
                        0,
                        120
                    );
                }
            }


            return substr(
                $content,
                0,
                120
            );
        }


        // ====================================================================
        // OPENROUTER ERROR DETECTION
        // ====================================================================

        private function isOpenRouterError(
            string $reply
        ): bool {

            $replyLower =
                strtolower(
                    trim($reply)
                );


            return
                str_contains(
                    $replyLower,
                    'openrouter error'
                ) ||
                str_contains(
                    $replyLower,
                    'provider returned error'
                ) ||
                str_contains(
                    $replyLower,
                    'api error'
                );
        }


        // ====================================================================
        // WRITE TOPIC FILE
        // ====================================================================

        private function writeTopicFile(
            string $path,
            array $data
        ): bool {

            $json =
                json_encode(
                    $data,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );


            if (
                $json === false
            ) {
                return false;
            }


            return
                @file_put_contents(
                    $path,
                    $json,
                    LOCK_EX
                ) !== false;
        }
    }
}