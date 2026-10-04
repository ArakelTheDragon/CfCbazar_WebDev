<?php

declare(strict_types=1);

require_once __DIR__ . '/SkillManager.php';
require_once __DIR__ . '/MemoryStore.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/ConversationStore.php';
require_once __DIR__ . '/SkillData.php';

if (!class_exists('Router', false)) {

    /**
     * Main AI workflow orchestrator.
     *
     * The Router does not perform knowledge processing itself. It controls
     * the order in which the main skills are executed:
     *
     * PromptUnderstandingSkill
     *          ↓
     * KnowledgeSkill
     *          ↓
     * ResponseUnderstandingSkill
     *
     * KnowledgeSkill remains the central AI/knowledge component and is
     * responsible for working with MemoryStore, FactSkill and OpenRouter.
     */
    class Router
    {
        private SkillManager $skillManager;
        private MemoryStore $memory;

        public ?string $lastTopic = null;
        public array $lastEntities = [];
        public ?string $lastIntent = null;
        public ?string $lastQuestionType = null;

        public function __construct()
        {
            $this->skillManager = new SkillManager();
            $this->memory = new MemoryStore(__DIR__ . '/../memory/memory.json');
        }

        /**
         * Execute the normal AI pipeline for one user prompt.
         */
        public function handle(string $prompt): string
        {
            $prompt = trim($prompt);

            if ($prompt === '') {
                return 'Please provide a question or topic.';
            }

            // -------------------------------------------------------------
            // 0. Unified SkillData envelope (filled in place by each stage)
            // -------------------------------------------------------------
            $sessionId = '';
            try {
                if (class_exists('ConversationStore', false) || is_file(__DIR__ . '/ConversationStore.php')) {
                    $sessionId = ConversationStore::resolveSessionId();
                }
            } catch (Throwable $e) {
                $sessionId = '';
            }

            $data = SkillData::create($prompt, $prompt, $sessionId);

            // -------------------------------------------------------------
            // 1. Understand the user's prompt.
            // -------------------------------------------------------------
            $promptSkill = $this->skillManager->get('PromptUnderstandingSkill');

            if (!method_exists($promptSkill, 'analyze')) {
                throw new RuntimeException(
                    'PromptUnderstandingSkill does not provide analyze().'
                );
            }

            // Prefer SkillData process(); fall back to legacy analyze()
            if (method_exists($promptSkill, 'process')) {
                $data = $promptSkill->process($data, $this->memory);
            } else {
                $analysisLegacy = $promptSkill->analyze($prompt);
                if (!is_array($analysisLegacy)) {
                    throw new RuntimeException(
                        'PromptUnderstandingSkill returned an invalid analysis.'
                    );
                }
                foreach ([
                    'core_query', 'intent', 'secondary_intents', 'topic', 'subtopics',
                    'entities', 'question_type', 'constraints', 'key_phrases',
                    'is_multi_part', 'query_embedding',
                ] as $key) {
                    if (array_key_exists($key, $analysisLegacy)) {
                        $data->set($key, $analysisLegacy[$key]);
                    }
                }
                if (isset($analysisLegacy['prompt_facts']) && is_array($analysisLegacy['prompt_facts'])) {
                    $data->mergeFacts($analysisLegacy['prompt_facts']);
                }
            }

            // Fuzzy-resolve topic against existing memory topics.
            $resolvedTopic = $this->memory->resolveTopic($data->topic());
            $data->set('topic', $resolvedTopic);

            // Legacy analysis array for KnowledgeSkill until Phase 3
            $analysis = [
                'raw_prompt'        => $data->prompt(),
                'core_query'        => (string)$data->get('core_query', ''),
                'intent'            => (string)$data->get('intent', 'ask_information'),
                'secondary_intents' => is_array($data->get('secondary_intents')) ? $data->get('secondary_intents') : [],
                'topic'             => $data->topic(),
                'subtopics'         => is_array($data->get('subtopics')) ? $data->get('subtopics') : [],
                'entities'          => is_array($data->get('entities')) ? $data->get('entities') : [],
                'question_type'     => (string)$data->get('question_type', 'statement'),
                'constraints'       => is_array($data->get('constraints')) ? $data->get('constraints') : [],
                'key_phrases'       => is_array($data->get('key_phrases')) ? $data->get('key_phrases') : [],
                'is_multi_part'     => (bool)$data->get('is_multi_part', false),
                'prompt_facts'      => is_array($data->get('facts')) ? $data->get('facts') : [],
                'query_embedding'   => is_array($data->get('query_embedding')) ? $data->get('query_embedding') : [],
            ];

            // Preserve the information used by the GUI/status panel.
            $this->lastTopic = $data->topic();
            $this->lastEntities = is_array($data->get('entities')) ? $data->get('entities') : [];
            $this->lastIntent = (string)$data->get('intent', '');
            $this->lastQuestionType = (string)$data->get('question_type', '');

            // -------------------------------------------------------------
            // 2. KnowledgeSkill is the central AI/knowledge system.
            // -------------------------------------------------------------
            $knowledge = $this->skillManager->get('KnowledgeSkill');

            if (!method_exists($knowledge, 'respond')) {
                throw new RuntimeException(
                    'KnowledgeSkill does not provide respond().'
                );
            }

            $data->setSkill('knowledge');
            if (method_exists($knowledge, 'process')) {
                $data = $knowledge->process($data, $this->memory);
                $draft = $data->draftAnswer();
            } else {
                $rawResponse = $knowledge->respond($analysis, $this->memory);
                $draft = is_string($rawResponse) ? $rawResponse : $this->fallbackResponse($rawResponse);
                $data->set('draft_answer', $draft);
            }

            // Store conversation turn (local short-term memory)
            $this->storeConversationTurn(
                $data->prompt(),
                $draft,
                $data->topic()
            );

            // -------------------------------------------------------------
            // 3. Convert the knowledge result into the final user response.
            // -------------------------------------------------------------
            $final = $draft;
            if (SkillManager::exists('ResponseUnderstandingSkill')) {
                try {
                    $formatter = $this->skillManager->get('ResponseUnderstandingSkill');

                    if (method_exists($formatter, 'process')) {
                        $data = $formatter->process($data);
                        $final = $data->finalAnswer();
                    } elseif (method_exists($formatter, 'execute')) {
                        $data->setSkill('response_understanding');
                        $final = $formatter->execute($draft);
                        $data->set('final_answer', is_string($final) ? $final : $draft);
                    }
                } catch (Throwable $e) {
                    // Presentation layer failure must not drop the answer.
                    $final = $draft;
                    $data->set('final_answer', $draft);
                }
            } else {
                $data->set('final_answer', is_string($final) ? $final : $draft);
            }

            return $data->finalAnswer();
        }

        /**
         * Convert a response to a safe string when the final formatter is
         * unavailable or fails.
         */
        private function fallbackResponse(mixed $response): string
        {
            if (is_string($response)) {
                return $response;
            }

            if (is_scalar($response)) {
                return (string)$response;
            }

            $json = json_encode(
                $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            return $json !== false ? $json : 'No response generated.';
        }

        private function storeConversationTurn(string $prompt, string $answer, string $topic): void
        {
            $featuresPath = __DIR__ . '/../config/features.php';
            $features = is_file($featuresPath) ? (require $featuresPath) : [];
            if (empty($features['conversation_enabled'])) {
                return;
            }
            try {
                $sessionId = ConversationStore::resolveSessionId();
                $conv = new ConversationStore(
                    $sessionId,
                    (int)($features['conversation_max_turns'] ?? 10)
                );
                $conv->addTurn($prompt, $answer, $topic);
            } catch (Throwable $e) {
                // Conversation memory must not break the main response.
            }
        }

    }
}
