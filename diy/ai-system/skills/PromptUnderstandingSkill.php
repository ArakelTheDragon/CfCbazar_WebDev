<?php

require_once __DIR__ . '/FactSkill.php';

class PromptUnderstandingSkill
{
    public function analyze(string $prompt): array
    {
        $clean = trim($prompt);

        $intent        = $this->detectIntent($clean);
        $topic         = $this->extractTopic($clean);
        $entities      = $this->extractEntities($clean);
        $questionType  = $this->detectQuestionType($clean);

        $promptFacts   = FactSkill::extractFactsFromPrompt($clean);

        return [
            "raw_prompt"    => $clean,
            "intent"        => $intent,
            "topic"         => $topic,
            "entities"      => $entities,
            "question_type" => $questionType,
            "prompt_facts"  => $promptFacts,
        ];
    }

    private function detectIntent(string $prompt): string
    {
        $p = strtolower($prompt);

        $map = [
            "how"       => "explain_process",
            "why"       => "explain_reason",
            "what is"   => "define_concept",
            "define"    => "define_concept",
            "compare"   => "compare_things",
            "vs"        => "compare_things",
            "difference" => "compare_things",
            "list"      => "list_information",
            "show"      => "ask_information",
            "explain"   => "explain_process",
            "tell me"   => "ask_information",
            "who"       => "ask_information",
            "what"      => "ask_information"
        ];

        foreach ($map as $keyword => $intent) {
            if (str_contains($p, $keyword)) {
                return $intent;
            }
        }

        return "ask_information";
    }

    private function extractTopic(string $prompt): string
    {
        $p = strtolower($prompt);

        $topics = [
            "php" => "php_programming",
            "ai" => "artificial_intelligence",
            "json" => "json_data",
            "array" => "php_arrays",
            "router" => "ai_router_system",
            "memory" => "ai_memory_system",
        ];

        foreach ($topics as $keyword => $topicName) {
            if (str_contains($p, $keyword)) {
                return $topicName;
            }
        }

        preg_match('/\b([a-zA-Z]{3,})\b/', $prompt, $match);
        return $match[1] ?? "general_topic";
    }

    private function extractEntities(string $prompt): array
    {
        $entities = [];

        preg_match_all('/"([^"]+)"/', $prompt, $quoted);
        foreach ($quoted[1] as $q) {
            $entities[] = $q;
        }

        preg_match_all('/[a-zA-Z0-9_\-]+\.(php|json|txt)/', $prompt, $files);
        foreach ($files[0] as $f) {
            $entities[] = $f;
        }

        $keywords = ["php", "ai", "json", "array", "router", "memory"];
        foreach ($keywords as $k) {
            if (stripos($prompt, $k) !== false) {
                $entities[] = $k;
            }
        }

        return array_values(array_unique($entities));
    }

    private function detectQuestionType(string $prompt): string
    {
        $p = strtolower($prompt);

        if (str_starts_with($p, "who")) return "who";
        if (str_starts_with($p, "what")) return "what";
        if (str_starts_with($p, "when")) return "when";
        if (str_starts_with($p, "where")) return "where";
        if (str_starts_with($p, "why")) return "why";
        if (str_starts_with($p, "how")) return "how";

        return "statement";
    }
}