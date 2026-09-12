<?php

class GeneratorSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['novel','story','recipe','cook','cv','resume','budget','finance'];
    }

    public function getPriority(): int { return 5; }

    public function execute(string $input, array &$memory): string {
        $t = strtolower($input);

        if (str_contains($t,'recipe') || str_contains($t,'cook'))
            return "Cooking Blueprint: Sauté garlic in olive oil, add tomatoes, basil, simmer 10 mins, mix with pasta.";

        if (str_contains($t,'budget') || str_contains($t,'finance'))
            return "Finance Blueprint: Use the 50/30/20 rule — Needs / Wants / Savings.";

        if (str_contains($t,'cv') || str_contains($t,'resume'))
            return "CV Blueprint: Summary → Skills → Experience → Education.";

        if (str_contains($t,'novel') || str_contains($t,'story'))
            return "Story Blueprint: 3 Acts — Setup, Conflict, Resolution.";

        return "Generator skill active.";
    }
}

