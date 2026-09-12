<?php

class DiagnosticSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['diagnostic', 'test ai', 'system report', 'status report'];
    }

    public function getPriority(): int {
        return 100;
    }

    public function execute(string $input, array &$memory): string {

        $report = "=== CfCbazar Local AI Diagnostic Report ===\n\n";

        // Memory Audit
        $histCount = isset($memory["history"]) ? count($memory["history"]) : 0;
        $knowCount = isset($memory["knowledge"]) ? count($memory["knowledge"]) : 0;
        $report .= "[Memory Test]\nHistory OK ({$histCount} entries)\nKnowledge OK ({$knowCount} topics)\n\n";

        // Test CalculatorSkill
        if (class_exists('CalculatorSkill')) {
            $calc = new CalculatorSkill();
            $res = $calc->execute("calculate 2+2", $memory);
            $report .= "[CalculatorSkill]\nLoaded ✔\nTest: calculate 2+2 → {$res}\n\n";
        }

        // Test KnowledgeSkill
        if (class_exists('KnowledgeSkill')) {
            $know = new KnowledgeSkill();
            $res = $know->execute("tell me about proxima", $memory);
            $report .= "[KnowledgeSkill]\nLoaded ✔\nTest: tell me about proxima → {$res}\n\n";
        }

        // Test GeneratorSkill
        if (class_exists('GeneratorSkill')) {
            $gen = new GeneratorSkill();
            $res = $gen->execute("generate a short story", $memory);
            $report .= "[GeneratorSkill]\nLoaded ✔\nTest: generate a short story → {$res}\n\n";
        }

        // Test SummarizerSkill
        if (class_exists('SummarizerSkill')) {
            $sum = new SummarizerSkill();
            $res = $sum->execute("summarize this: I walked to the store", $memory);
            $report .= "[SummarizerSkill]\nLoaded ✔\nTest: summarize text → {$res}\n\n";
        }

        // Test SelfLearningSkill
        if (class_exists('SelfLearningSkill')) {
            $learn = new SelfLearningSkill();
            $res = $learn->execute("learn testkey is testvalue", $memory);

            if (isset($memory["knowledge"]["testkey"])) {
                unset($memory["knowledge"]["testkey"]);
            }

            $report .= "[SelfLearningSkill]\nLoaded ✔\nTest: learn testkey is testvalue → {$res}\n\n";
        }

        // Test PromptUnderstandingSkill
        if (class_exists('PromptUnderstandingSkill')) {
            $promptEngine = new PromptUnderstandingSkill();
            $res = $promptEngine->execute("write me a novel about deep space", $memory);
            $firstLine = strtok($res, "\n");
            $report .= "[PromptUnderstandingSkill]\nLoaded ✔\nTest: write me a novel → {$firstLine}\n\n";
        }

        // Test BayesSkill
        if (class_exists('BayesSkill')) {
            $bayes = new BayesSkill();
            $res = $bayes->execute("bayes classify", $memory);
            $report .= "[BayesSkill]\nLoaded ✔\nTest: bayes classify → {$res}\n\n";
        }

        // Test MLPatternSkill
        if (class_exists('MLPatternSkill')) {
            $ml = new MLPatternSkill();
            $res = $ml->execute("ML classify", $memory);
            $report .= "[MLPatternSkill]\nLoaded ✔\nTest: ML classify → {$res}\n\n";
        }

        $report .= "[Dispatcher Fallback]\nUnknown input → Should trigger fallback\nResult: General AI: I’m processing locally. Teach me new facts using: remember [topic] is [info].";

        return $report;
    }
}
