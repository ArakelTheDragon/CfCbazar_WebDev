<?php

class PromptUnderstandingSkill implements AISkillInterface {

    public function getKeywords(): array {
        return [
            'write', 'create', 'draft', 'explain', 'generate', 
            'novel', 'story', 'essay', 'code', 'script', 'how to', 
            'guide', 'remember', 'what is', 'can i'
        ];
    }

    public function getPriority(): int {
        return 85; // High priority to intercept complex generator prompts
    }

    public function execute(string $input, array &$memory): string {
        $inputLower = strtolower(trim($input));

        // 1. RUN 3-METHOD ANALYSIS ON THE INCOMING PROMPT
        $m1Result = $this->runMethod1Heuristics($inputLower);
        $m2Result = $this->runMethod2SemanticClusters($inputLower);
        $m3Result = $this->runMethod3LLMClassifier($input);

        // Ensemble voting to determine intent & canonical topic
        $finalIntent = $this->resolveEnsembleIntent($m1Result['intent'], $m2Result['intent'], $m3Result['intent']);
        $userTopic   = !empty($m3Result['topic']) ? $m3Result['topic'] : $m2Result['topic'];
        if (empty($userTopic)) {
            $userTopic = $m1Result['topic'];
        }

        // 2. MATCH USER TOPIC AGAINST EXISTING KEYS IN LOCAL MEMORY
        $knowledgeBase = $memory["knowledge"] ?? [];
        $bestMemoryKey = $this->findBestMemoryMatch($userTopic, $knowledgeBase);

        // If a valid semantic match exists in local_memory.json, serve it directly
        if ($bestMemoryKey !== null && isset($knowledgeBase[$bestMemoryKey])) {
            return "Knowledge Base Match [Matched: '{$bestMemoryKey}']:\n\n" . $knowledgeBase[$bestMemoryKey];
        }

        // 3. ROUTE TO INTENT HANDLER IF NO LOCAL MEMORY MATCH EXISTS
        switch ($finalIntent) {
            case 'creative_writing':
                return $this->generateNovelBlueprint($userTopic, $inputLower);

            case 'explanation':
            case 'qna':
                return $this->generateStructuredExplanation($userTopic);

            case 'code_generation':
                return $this->generateCodeTemplate($userTopic);

            case 'guide':
                return $this->generateStepByStepGuide($userTopic);

            default:
                // Fallback to external call via OpenRouter and index answer into memory under canonical topic
                $llmResponse = queryOpenRouter($input);
                if ($llmResponse && !str_contains($llmResponse, 'OpenRouter Error')) {
                    $memory["knowledge"][$userTopic] = $llmResponse;
                }
                return $llmResponse ?? "Unable to resolve request locally or via OpenRouter.";
        }
    }

    /* ============================================================
       METHOD 1: Regex & Pattern Heuristics
       ============================================================ */
    private function runMethod1Heuristics(string $text): array {
        $intent = 'general_prompt';

        if (preg_match('/(novel|story|book|chapter|fiction|poem|tale)/i', $text)) {
            $intent = 'creative_writing';
        } elseif (preg_match('/(code|script|php|python|function|html|css|javascript|sql)/i', $text)) {
            $intent = 'code_generation';
        } elseif (preg_match('/(how to|guide|tutorial|steps|instructions)/i', $text)) {
            $intent = 'guide';
        } elseif (preg_match('/(explain|what is|tell me about|describe|define|can i)/i', $text)) {
            $intent = 'explanation';
        }

        $clean = preg_replace('/^(write|create|draft|explain|generate|give me|how to|tell me about|can i|run|a|an|the|me)\s+/i', '', $text);
        $clean = preg_replace('/^(novel|story|essay|guide|script|code|about|on)\s+/i', '', $clean);
        $clean = trim($clean);

        return [
            'intent' => $intent,
            'topic'  => $clean !== '' ? $clean : 'general subject'
        ];
    }

    /* ============================================================
       METHOD 2: Semantic Cluster Extraction & Normalization
       ============================================================ */
    private function runMethod2SemanticClusters(string $text): array {
        $stopWords = ['can', 'i', 'run', 'only', 'on', 'the', 'a', 'an', 'is', 'it', 'locally', 'in', 'to', 'for', 'with', 'how', 'do'];
        $words = explode(' ', preg_replace('/[^a-z0-9\s]/', '', $text));
        $filtered = array_diff($words, $stopWords);
        
        $normalizedKey = implode('_', array_filter($filtered));

        $clusters = [
            'local_php_ai'   => ['php', 'ai', 'local', 'offline', 'standalone', 'selfhosted'],
            'stock_market'   => ['stock', 'market', 'trading', 'shares', 'equity', 'finance'],
            'circuit_design' => ['555', 'timer', 'transistor', 'bjt', 'oscillator', 'circuit']
        ];

        $detectedIntent = 'general_prompt';
        $matchedTopic = $normalizedKey;

        foreach ($clusters as $clusterName => $keywords) {
            $matches = array_intersect($keywords, $words);
            if (count($matches) >= 2) {
                $matchedTopic = $clusterName;
                if (in_array('ai', $keywords) || in_array('php', $keywords)) {
                    $detectedIntent = 'explanation';
                }
                break;
            }
        }

        return [
            'intent' => $detectedIntent,
            'topic'  => $matchedTopic
        ];
    }

    /* ============================================================
       METHOD 3: LLM Intent Classifier
       ============================================================ */
    private function runMethod3LLMClassifier(string $text): array {
        $prompt = "Analyze this prompt: \"{$text}\"\n" .
                  "1. Classify intent as one of: [creative_writing, code_generation, guide, explanation, qna, general_prompt]\n" .
                  "2. Extract a canonical topic key in 1-3 lowercase words (e.g. 'local_php_ai', 'stock_market').\n" .
                  "Return output in strict JSON format: {\"intent\": \"...\", \"topic\": \"...\"}";

        $rawResponse = queryOpenRouter($prompt);
        
        if ($rawResponse && !str_contains($rawResponse, 'OpenRouter Error')) {
            $jsonString = preg_replace('/^```json\s*|\s*```$/i', '', trim($rawResponse));
            $decoded = json_decode($jsonString, true);

            if (is_array($decoded) && isset($decoded['intent'], $decoded['topic'])) {
                return [
                    'intent' => strtolower(trim($decoded['intent'])),
                    'topic'  => strtolower(trim($decoded['topic']))
                ];
            }
        }

        return ['intent' => 'general_prompt', 'topic' => ''];
    }

    /* ============================================================
       ENSEMBLE VOTING RESOLVER
       ============================================================ */
    private function resolveEnsembleIntent(string $m1, string $m2, string $m3): string {
        $votes = [
            $m1 => 1.0,
            $m2 => 1.5,
            $m3 => 2.5
        ];

        $scores = [];
        foreach ($votes as $intent => $weight) {
            if ($intent === 'general_prompt') continue;
            $scores[$intent] = ($scores[$intent] ?? 0) + $weight;
        }

        if (empty($scores)) {
            return 'general_prompt';
        }

        arsort($scores);
        return array_key_first($scores);
    }

    /* ============================================================
       MEMORY MATCHING ENGINE
       ============================================================ */
    private function findBestMemoryMatch(string $userTopic, array $knowledgeBase): ?string {
        if (empty($knowledgeBase)) return null;

        if (isset($knowledgeBase[$userTopic])) {
            return $userTopic;
        }

        $userWords = explode('_', str_replace([' ', '-'], '_', strtolower($userTopic)));
        $bestKey = null;
        $highestOverlap = 0;

        foreach (array_keys($knowledgeBase) as $memoryKey) {
            $memoryWords = explode('_', str_replace([' ', '-'], '_', strtolower($memoryKey)));
            $commonWords = array_intersect($userWords, $memoryWords);
            $overlapScore = count($commonWords);

            if ($overlapScore > $highestOverlap && $overlapScore >= 2) {
                $highestOverlap = $overlapScore;
                $bestKey = $memoryKey;
            }
        }

        return $bestKey;
    }

    /* ============================================================
       INTENT BLUEPRINTS
       ============================================================ */
    private function generateNovelBlueprint(string $topic, string $fullInput): string {
        $title = ucwords(str_replace('_', ' ', $topic));
        return "PROJECT CONFIGURATION\n" .
               "1. Writing Mode: Novel\n" .
               "2. Title: The Chronicles of " . $title . "\n" .
               "3. Genre: Speculative Narrative / Fiction\n" .
               "4. Author: The Writer\n\n" .
               "EXECUTION PARAMETERS\n" .
               "- Number of Chapters: 5\n" .
               "- Target Words per Unit: 1000\n" .
               "- Embedding Model: llama3.1:8b\n" .
               "- Theme & Visual Style: Modern Corporate\n\n" .
               "CORE CONTENT & CONSTRAINTS\n" .
               "- Premise / Seed Context: A narrative detailing the rise and convergence of " . $title . ".\n" .
               "- House Style Notes: Omniscient third-person POV. Includes a complete Table of Contents.\n" .
               "- Knowledge Base / Grounding Data: Derived from local engine analysis.\n\n" .
               "AGENT ROSTER SETUP\n" .
               "- Architect Agent(s): llama3.1:8b\n" .
               "- Writer Agent(s): llama3.1:8b\n" .
               "- Reviewer / Continuity Agent(s): llama3.1:8b\n\n" .
               "[TABLE OF CONTENTS]\n" .
               "- Chapter 1: The Awakening of " . $title . "\n" .
               "- Chapter 2: Echoes in the Shadow\n" .
               "- Chapter 3: The Convergence\n" .
               "- Chapter 4: Beyond " . $title . "\n\n" .
               "--- CHAPTER 1: THE AWAKENING ---\n" .
               "The horizon was silent before " . $title . " began to transform everything...";
    }

    private function generateStructuredExplanation(string $topic): string {
        $title = ucwords(str_replace('_', ' ', $topic));
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
        $title = ucwords(str_replace('_', ' ', $topic));
        return "=== STEP-BY-STEP GUIDE: HOW TO " . strtoupper($title) . " ===\n\n" .
               "Step 1: Preparation\n" .
               "- Gather all prerequisites and outline target goals for " . $title . ".\n\n" .
               "Step 2: Execution & Setup\n" .
               "- Initialize the primary workspace and establish core parameters.\n\n" .
               "Step 3: Implementation\n" .
               "- Apply step-by-step actions dedicated to completing " . $title . ".\n\n" .
               "Step 4: Verification\n" .
               "- Inspect the final output to confirm expected performance and stability.";
    }
}
