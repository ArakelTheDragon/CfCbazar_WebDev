<?php

class MarkovSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['continue','write','generate','markov','story','novel'];
    }

    public function getPriority(): int {
        return 12;
    }

    public function execute(string $input, array &$memory): string {

        // Load library
        $lib = __DIR__ . '/../libs/markov/markov.php';
        if (!file_exists($lib)) {
            return "Markov library not installed. Upload /libs/markov/markov.php to enable this skill.";
        }
        require_once $lib;

        // Build Markov model from memory history
        $markov = new Markov();

        foreach ($memory["history"] as $entry) {
            $markov->feed($entry["user"]);
        }

        // Generate text
        $output = $markov->generate(40);

        return "Markov Generator:\n" . $output;
    }
}

