class SummarizerSkill implements AISkillInterface {
    public function getKeywords(): array {
        return ['summarize', 'summary', 'explain'];
    }

    public function getPriority(): int {
        return 15;
    }

    public function execute(string $input, array &$memory): string {
        require_once __DIR__ . '/libs/textrank/TextRank.php';

        $text = "";
        foreach ($memory['history'] as $entry) {
            $text .= $entry['user'] . ". ";
        }

        $ranker = new TextRank();
        $summary = $ranker->summarizeText($text);

        return "Summary of your interactions:\n" . $summary;
    }
}

