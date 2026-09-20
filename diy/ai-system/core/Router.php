<?php

require_once __DIR__ . '/SkillManager.php';
require_once __DIR__ . '/MemoryStore.php';
require_once __DIR__ . '/Helpers.php';

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
        // 1. Analyze prompt (including prompt facts)
        $promptSkill = $this->skillManager->get('PromptUnderstandingSkill');
        $analysis = $promptSkill->analyze($prompt);

        $this->lastTopic        = $analysis['topic'];
        $this->lastEntities     = $analysis['entities'];
        $this->lastIntent       = $analysis['intent'];
        $this->lastQuestionType = $analysis['question_type'];

        // 2. KnowledgeSkill drives OpenRouter + FactSkill + Memory
        $knowledge = $this->skillManager->get('KnowledgeSkill');
        return $knowledge->respond($analysis, $this->memory);
    }
}