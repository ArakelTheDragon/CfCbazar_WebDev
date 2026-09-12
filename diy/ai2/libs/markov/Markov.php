class MarkovSkill implements AISkillInterface {
    public function getKeywords(): array {
        return ['story', 'novel', 'continue', 'write'];
    }

    public function getPriority(): int {
        return 12;
    }

    public function execute(string $input, array &$memory): string {
        require_once __DIR__ . '/libs/markov/Markov.php';

        $markov = new Markov();

        foreach ($memory['history'] as $entry) {
            $markov->feed($entry['user']);
        }

        return "Markov Story Generator:\n" . $markov->generate(40);
    }
}

