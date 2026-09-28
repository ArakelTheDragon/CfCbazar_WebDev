<?php

require_once __DIR__ . '/../core/Helpers.php';

class FactSkill
{
    public static function extractFactsFromText(string $text): array
    {
        $facts = [];

        $sentences = preg_split('/[.?!]\s*/', $text);
        foreach ($sentences as $s) {
            $s = trim($s);
            if ($s === '') continue;

            $facts[] = [
                "type"       => "statement",
                "value"      => $s,
                "confidence" => 0.80,
                "source"     => "openrouter",
                "created_at" => date('c')
            ];
        }

        return $facts;
    }

    public static function extractFactsFromPrompt(string $prompt): array
    {
        $facts = [];

        if (strlen(trim($prompt)) > 0) {
            $facts[] = [
                "type"       => "prompt_intent",
                "value"      => $prompt,
                "confidence" => 0.70,
                "source"     => "prompt",
                "created_at" => date('c')
            ];
        }

        return $facts;
    }

    public static function mergeFacts(array $a, array $b): array
    {
        return array_values(array_merge($a, $b));
    }
}