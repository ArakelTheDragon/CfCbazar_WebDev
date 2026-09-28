<?php

declare(strict_types=1);

require_once __DIR__ . '/SkillManager.php';
require_once __DIR__ . '/MemoryStore.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/ConversationStore.php';

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
            // 1. Understand the user's prompt.
            // -------------------------------------------------------------
            $promptSkill = $this->skillManager->get('PromptUnderstandingSkill');

            if (!method_exists($promptSkill, 'analyze')) {
                throw new RuntimeException(
                    'PromptUnderstandingSkill does not provide analyze().'
                );
            }

            $analysis = $promptSkill->analyze($prompt);

            if (!is_array($analysis)) {
                throw new RuntimeException(
                    'PromptUnderstandingSkill returned an invalid analysis.'
                );
            }

            // Fuzzy-resolve topic against existing memory topics.
            if (isset($analysis['topic']) && is_string($analysis['topic']) && $analysis['topic'] !== '') {
                $analysis['topic'] = $this->memory->resolveTopic((string)$analysis['topic']);
            }

            // Preserve the information used by the GUI/status panel.
            $this->lastTopic = isset($analysis['topic'])
                ? (string)$analysis['topic']
                : null;

            $this->lastEntities = is_array($analysis['entities'] ?? null)
                ? $analysis['entities']
                : [];

            $this->lastIntent = isset($analysis['intent'])
                ? (string)$analysis['intent']
                : null;

            $this->lastQuestionType = isset($analysis['question_type'])
                ? (string)$analysis['question_type']
                : null;

            // -------------------------------------------------------------
            // 2. KnowledgeSkill is the central AI/knowledge system.
            // -------------------------------------------------------------
            $knowledge = $this->skillManager->get('KnowledgeSkill');

            if (!method_exists($knowledge, 'respond')) {
                throw new RuntimeException(
                    'KnowledgeSkill does not provide respond().'
                );
            }

            $rawResponse = $knowledge->respond($analysis, $this->memory);

            // Store conversation turn (local short-term memory)
            $this->storeConversationTurn($prompt, is_string($rawResponse) ? $rawResponse : '', (string)($this->lastTopic ?? ''));

            // -------------------------------------------------------------
            // 3. Convert the knowledge result into the final user response.
            // -------------------------------------------------------------
            if (SkillManager::exists('ResponseUnderstandingSkill')) {
                try {
                    $formatter = $this->skillManager->get('ResponseUnderstandingSkill');

                    if (method_exists($formatter, 'execute')) {
                        return $formatter->execute($rawResponse);
                    }
                } catch (Throwable $e) {
                    // The response formatter is a presentation layer. If it
                    // fails, preserve the KnowledgeSkill result instead of
                    // losing the answer entirely.
                }
            }

            return $this->fallbackResponse($rawResponse);
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
