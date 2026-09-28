<?php

require_once __DIR__ . '/SkillManager.php';
require_once __DIR__ . '/MemoryStore.php';
require_once __DIR__ . '/Helpers.php';

if (!class_exists('Router', false)) {

    class Router
    {
        private SkillManager $skillManager;
        private MemoryStore $memory;

        public $lastTopic = null;
        public $lastEntities = [];
        public $lastIntent = null;
        public $lastQuestionType = null;

        public function __construct()
        {
            $this->skillManager = new SkillManager();
            $this->memory = new MemoryStore(__DIR__ . '/../memory/memory.json');
        }

        public function handle(string $prompt): string
        {
            // 1. Analyze prompt intent and metadata
            $promptSkill = $this->skillManager->get('PromptUnderstandingSkill');
            $analysis = $promptSkill->analyze($prompt);

            $this->lastTopic        = $analysis['topic'] ?? null;
            $this->lastEntities     = $analysis['entities'] ?? [];
            $this->lastIntent       = $analysis['intent'] ?? null;
            $this->lastQuestionType = $analysis['question_type'] ?? null;

            // 2. Fetch knowledge response
            $knowledge = $this->skillManager->get('KnowledgeSkill');
            $rawResponse = $knowledge->respond($analysis, $this->memory);

            // 3. Format final response through ResponseUnderstandingSkill
            if (SkillManager::exists('ResponseUnderstandingSkill')) {
                try {
                    $formatter = $this->skillManager->get('ResponseUnderstandingSkill');
                    if (method_exists($formatter, 'execute')) {
                        return $formatter->execute($rawResponse);
                    }
                } catch (Exception $e) {
                    // Fallback to unformatted string if skill fails
                }
            }

            return is_array($rawResponse) ? json_encode($rawResponse, JSON_PRETTY_PRINT) : (string)$rawResponse;
        }
    }

}
