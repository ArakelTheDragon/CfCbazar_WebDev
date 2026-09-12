<?php

class PromptUnderstandingSkill implements AISkillInterface {

    public function getKeywords(): array {
        return [
            'write', 'create', 'draft', 'explain', 'generate', 
            'novel', 'essay', 'code', 'script', 'how to', 'guide'
        ];
    }

    public function getPriority(): int {
        return 85; // High priority to intercept complex generator prompts
    }

    public function execute(string $input, array &$memory): string {
        $inputLower = strtolower($input);
        
        // 1. Detect Intent Category
        $intent = $this->detectIntent($inputLower);
        
        // 2. Extract Core Topic
        $topic = $this->extractTopic($inputLower);

        // 3. Route to Intent Handler
        switch ($intent) {
            case 'creative_writing':
                return $this->generateNovelBlueprint($topic, $inputLower);
                
            case 'explanation':
                return $this->generateStructuredExplanation($topic);

            case 'code_generation':
                return $this->generateCodeTemplate($topic);

            case 'guide':
                return $this->generateStepByStepGuide($topic);
                
            default:
                return "Prompt Analysis: Intent detected as [{$intent}] for topic '{$topic}'.";
        }
    }

    private function detectIntent(string $text): string {
        if (preg_match('/(novel|story|book|chapter|fiction|poem|tale)/i', $text)) {
            return 'creative_writing';
        }
        if (preg_match('/(code|script|php|python|function|html|css|javascript|sql)/i', $text)) {
            return 'code_generation';
        }
        if (preg_match('/(how to|guide|tutorial|steps|instructions)/i', $text)) {
            return 'guide';
        }
        if (preg_match('/(explain|what is|tell me about|describe|define)/i', $text)) {
            return 'explanation';
        }
        return 'general_prompt';
    }

    private function extractTopic(string $text): string {
        $clean = preg_replace('/^(write|create|draft|explain|generate|give me|how to|tell me about|a|an|the|me)\s+/i', '', $text);
        $clean = preg_replace('/^(novel|story|essay|guide|script|code|about|on)\s+/i', '', $clean);
        $clean = trim($clean);
        return $clean !== '' ? $clean : 'the requested subject';
    }

    private function generateNovelBlueprint(string $topic, string $fullInput): string {
        $title = ucwords($topic);
        return "=== NOVEL DRAFT GENERATOR ===\n" .
               "Title: The Chronicles of " . $title . "\n" .
               "Genre: Speculative Narrative / Fiction\n\n" .
               "[TABLE OF CONTENTS]\n" .
               "- Chapter 1: The Awakening of " . $title . "\n" .
               "- Chapter 2: Echoes in the Shadow\n" .
               "- Chapter 3: The Convergence\n" .
               "- Chapter 4: Beyond " . $title . "\n\n" .
               "--- CHAPTER 1: THE AWAKENING ---\n" .
               "The horizon was silent before " . $topic . " began to transform everything. Shadows stretched across the expanse, whispering secrets of an old era long forgotten.\n\n" .
               "The protagonist stepped forward, holding the central artifact. \"If we do not master " . $topic . ", it will control us,\" they remarked into the cold atmospheric wind.\n\n" .
               "--- CHAPTER 2: CONFRONTATION ---\n" .
               "Deep within the core, the true nature of " . $topic . " became clear. Every decision had led to this singular point of non-return...\n\n" .
               "[Draft Status: Outline & Initial Chapters Generated locally]";
    }

    private function generateStructuredExplanation(string $topic): string {
        $title = ucwords($topic);
        return "=== TECHNICAL EXPLANATION: " . $title . " ===\n\n" .
               "1. Core Definition:\n" .
               $title . " represents a fundamental concept structured around systemic operations and procedural rules.\n\n" .
               "2. Primary Principles:\n" .
               "- Foundation: Operational mechanics governing " . $topic . ".\n" .
               "- Dynamics: Interaction between primary components and external inputs.\n" .
               "- Impact: Practical output achieved when " . $topic . " is fully deployed.\n\n" .
               "3. Key Takeaway:\n" .
               "Understanding " . $topic . " requires analyzing both component structure and execution context.";
    }

    private function generateCodeTemplate(string $topic): string {
        $className = ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', $topic));
        if (empty($className)) {
            $className = 'GeneratedModule';
        }

        return "=== AUTO-GENERATED CODE MODULE ===\n" .
               "Target Topic: " . $topic . "\n\n" .
               "```php\n" .
               "<?php\n\n" .
               "class " . $className . " {\n" .
               "    private \$context;\n\n" .
               "    public function __construct(\$context = null) {\n" .
               "        \$this->context = \$context;\n" .
               "    }\n\n" .
               "    public function execute() {\n" .
               "        // Core implementation for: " . $topic . "\n" .
               "        return \"Executing module logic for " . $topic . "...\";\n" .
               "    }\n" .
               "}\n" .
               "```";
    }

    private function generateStepByStepGuide(string $topic): string {
        $title = ucwords($topic);
        return "=== STEP-BY-STEP GUIDE: HOW TO " . strtoupper($topic) . " ===\n\n" .
               "Step 1: Preparation\n" .
               "- Gather all prerequisites and outline target goals for " . $topic . ".\n\n" .
               "Step 2: Execution & Setup\n" .
               "- Initialize the primary workspace and establish core parameters.\n\n" .
               "Step 3: Implementation\n" .
               "- Apply step-by-step actions dedicated to completing " . $topic . ".\n\n" .
               "Step 4: Verification\n" .
               "- Inspect the final output to confirm expected performance and stability.";
    }
}
