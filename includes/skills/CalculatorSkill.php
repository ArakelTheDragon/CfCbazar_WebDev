<?php
// ============================================================================
// CfCbazar - Local AI CalculatorSkill Module
// File: /includes/skills/CalculatorSkill.php
// ============================================================================

if (!class_exists('CalculatorSkill')) {
    class CalculatorSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['calc', 'calculate', 'math', 'add', 'subtract', 'multiply', 'divide', '+', '-', '*', '/'];
        }

        public function getPriority(): int {
            return 10;
        }

        public function execute(string $input, array &$memory): string {
            // Look for mathematical expressions in the input
            if (preg_match('/([\d\.\s\+\-\*\/\(\)]+)/', $input, $m)) {
                $expr = trim($m[1]);

                // Strictly validate characters to prevent unwanted code execution
                if (!preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/', $expr)) {
                    return "Invalid math expression.";
                }

                try {
                    $result = @eval("return ($expr);");
                    if ($result !== false && $result !== null) {
                        return "Math Engine: {$expr} = {$result}";
                    }
                } catch (Throwable $e) {
                    return "Math error: " . $e->getMessage();
                }
            }

            return "I detected math, but couldn't compute it.";
        }
    }
}
