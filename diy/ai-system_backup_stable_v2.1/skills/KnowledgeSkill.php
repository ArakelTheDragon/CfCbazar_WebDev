<?php

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/FactSkill.php';

class KnowledgeSkill
{
    public function respond(array $analysis, MemoryStore $memory): string
    {
        $config = require __DIR__ . '/../config/openrouter.php';

        $prompt       = $analysis['raw_prompt'];
        $topic        = $analysis['topic'];
        $intent       = $analysis['intent'];
        $entities     = $analysis['entities'];
        $questionType = $analysis['question_type'];
        $promptFacts  = $analysis['prompt_facts'] ?? [];

        // 1. Call OpenRouter with exact user prompt
        $payload = [
            "model" => $config['model'],
            "messages" => [
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "temperature" => 0.4
        ];

        $headers = [
            "Authorization: Bearer " . $config['api_key']
        ];

        $response = Helpers::httpPostJson($config['base_url'], $headers, $payload);

        if (isset($response['error'])) {
            $facts = FactSkill::mergeFacts($promptFacts, []);
            $memoryFacts = $memory->mergeTopicFacts($topic, $facts);
            return $this->formatAnswer($topic, $intent, $entities, $memoryFacts, "OpenRouter error: " . $response['error']);
        }

        $text = $response['choices'][0]['message']['content'] ?? "";
        $responseFacts = FactSkill::extractFactsFromText($text);

        // 2. Merge prompt facts + response facts + existing memory
        $mergedFacts = FactSkill::mergeFacts($promptFacts, $responseFacts);
        $memoryFacts = $memory->mergeTopicFacts($topic, $mergedFacts);

        // 3. Build final answer from memory facts
        return $this->formatAnswer($topic, $intent, $entities, $memoryFacts, $text);
    }

    private function formatAnswer(string $topic, string $intent, array $entities, array $facts, string $rawText): string
    {
        $factSummary = $this->formatFacts($facts);

        switch ($intent) {
            case "define_concept":
                return "### Definition of {$topic}\n\n{$factSummary}";
            case "explain_process":
                return "### How {$topic} works\n\n{$factSummary}";
            case "explain_reason":
                return "### Why {$topic} happens\n\n{$factSummary}";
            case "compare_things":
                $entityText = empty($entities) ? "" : "Comparing: " . implode(" vs ", $entities) . "\n\n";
                return "### Comparison related to {$topic}\n\n{$entityText}{$factSummary}";
            case "list_information":
                return "### List of information about {$topic}\n\n{$factSummary}";
            default:
                return "### Information about {$topic}\n\n{$factSummary}";
        }
    }

    private function formatFacts(array $facts): string
    {
        if (empty($facts)) {
            return "No stored facts yet.";
        }

        $sections = [];
        foreach ($facts as $fact) {
            $type       = $fact['type'] ?? 'unknown';
            $value      = $fact['value'] ?? 'unknown';
            $confidence = $fact['confidence'] ?? 0;
            $source     = $fact['source'] ?? 'unknown';

            $sections[] = "- **{$type}**: {$value}  
  (confidence: {$confidence}, source: {$source})";
        }

        return implode("\n", $sections);
    }
}