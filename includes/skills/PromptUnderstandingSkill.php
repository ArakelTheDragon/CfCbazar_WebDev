<?php
// ============================================================================
// CfCbazar Group — PromptUnderstandingSkill.php
// File: /includes/skills/PromptUnderstandingSkill.php
// ============================================================================

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

if (!class_exists('PromptUnderstandingSkill')) {
    class PromptUnderstandingSkill implements AISkillInterface {

        /**
         * Priority triggers for skill registry loader.
         */
        public function getKeywords(): array {
            return ['intent', 'understand', 'analyze prompt', 'classify', 'meaning', 'prompt', 'query', 'route'];
        }

        /**
         * High priority score to ensure intent parsing runs early in pipeline execution.
         */
        public function getPriority(): int {
            return 5;
        }

        /**
         * Analyzes prompt intent, evaluates internal skills, and establishes target execution routes.
         */
        public function execute(string $input, array &$memory): string {
            $rawInput = trim($input);
            $inputLower = strtolower($rawInput);

            // Initialize state memory container for downstream skill pipeline
            $memory['parsed_intent'] = [
                'type'               => 'General Query',
                'target_skill'       => 'GeneratorSkill',
                'confidence'         => 0.50,
                'question_type'      => $this->detectQuestionType($inputLower),
                'matched_topic'      => null,
                'matched_content'    => '',
                'extracted_entities' => $this->extractEntities($rawInput),
                'raw_input'          => $rawInput
            ];

            // 1. Evaluate Skill: CalculatorSkill (Math & Equations)
            if ($this->isMathExpression($inputLower)) {
                $memory['parsed_intent']['type'] = 'Calculation Request';
                $memory['parsed_intent']['target_skill'] = 'CalculatorSkill';
                $memory['parsed_intent']['confidence'] = 0.98;
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 2. Evaluate Skill: ImageGenerationSkill (Visual Synthesis)
            if (preg_match('/\b(generate|draw|create|make|render)\s+(image|picture|photo|graphic|logo|banner)\b/i', $rawInput)) {
                $memory['parsed_intent']['type'] = 'Image Generation Request';
                $memory['parsed_intent']['target_skill'] = 'ImageGenerationSkill';
                $memory['parsed_intent']['confidence'] = 0.95;
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 3. Evaluate Skill: SelfLearningSkill (Knowledge Ingestion)
            if (preg_match('/^(learn|remember|note|fact|teach)\b/i', $rawInput) || str_contains($inputLower, 'openrouter')) {
                $memory['parsed_intent']['type'] = 'Learning Command';
                $memory['parsed_intent']['target_skill'] = 'SelfLearningSkill';
                $memory['parsed_intent']['confidence'] = 0.95;
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 4. Evaluate Skill: SummarizerSkill (Text Rank & Reduction)
            if (preg_match('/\b(summarize|summary|tl;dr|shorten|condense|key points|bullet points)\b/i', $rawInput)) {
                $memory['parsed_intent']['type'] = 'Summarization Request';
                $memory['parsed_intent']['target_skill'] = 'SummarizerSkill';
                $memory['parsed_intent']['confidence'] = 0.90;
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 5. Evaluate Skill: DiagnosticSkill (System Health & Logs)
            if (preg_match('/\b(diagnose|diagnostic|system status|health check|log analysis|server test)\b/i', $rawInput)) {
                $memory['parsed_intent']['type'] = 'System Diagnostics';
                $memory['parsed_intent']['target_skill'] = 'DiagnosticSkill';
                $memory['parsed_intent']['confidence'] = 0.95;
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 6. Evaluate Skill: KnowledgeSkill & Local File Topics
            $match = $this->findKnowledgeMatch($inputLower, $memory);
            if ($match !== null) {
                $memory['parsed_intent']['type'] = 'Knowledge Base Lookup';
                $memory['parsed_intent']['target_skill'] = 'KnowledgeSkill';
                $memory['parsed_intent']['confidence'] = $match['confidence'];
                $memory['parsed_intent']['matched_topic'] = $match['topic'];
                $memory['parsed_intent']['matched_content'] = $match['content'];
                return $this->formatAnalysisResponse($memory['parsed_intent']);
            }

            // 7. Evaluate Skill: MLPatternSkill / BayesSkill Fallback (Probabilistic Patterning)
            if (class_exists('SimpleBayes') || class_exists('MLPatternSkill')) {
                $probabilisticTarget = $this->evaluateBayesTarget($inputLower);
                if ($probabilisticTarget !== null) {
                    $memory['parsed_intent']['type'] = 'Pattern Classified Task';
                    $memory['parsed_intent']['target_skill'] = $probabilisticTarget['skill'];
                    $memory['parsed_intent']['confidence'] = $probabilisticTarget['confidence'];
                    return $this->formatAnalysisResponse($memory['parsed_intent']);
                }
            }

            // Default Generator Skill Fallback
            return $this->formatAnalysisResponse($memory['parsed_intent']);
        }

        /**
         * Detects arithmetic, algebraic expressions, and numerical logic.
         */
        private function isMathExpression(string $inputLower): bool {
            if (preg_match('/^\s*[\d\.\(\)\+\-\*\/\^\%\s]+\s*$/', $inputLower) && preg_match('/\d/', $inputLower)) {
                return true;
            }
            return (bool)preg_match('/\b(calculate|compute|math|solve|add|subtract|multiply|divide|square root|sqrt)\b/i', $inputLower);
        }

        /**
         * Cross-references input against active memory topics and storage models.
         */
        private function findKnowledgeMatch(string $inputLower, array $memory): ?array {
            if (!isset($memory['knowledge']) || !is_array($memory['knowledge'])) {
                return null;
            }

            $bestMatch = null;
            $highestScore = 0.0;

            foreach ($memory['knowledge'] as $topicKey => $data) {
                $topicStr = strtolower((string)$topicKey);
                $topicClean = str_replace(['_', '-'], ' ', $topicStr);

                if (strlen($topicStr) < 2) {
                    continue;
                }

                $score = 0.0;
                if ($topicStr === $inputLower || $topicClean === $inputLower) {
                    $score = 1.00;
                } elseif (preg_match('/\b' . preg_quote($topicClean, '/') . '\b/i', $inputLower)) {
                    $score = 0.90;
                } elseif (str_contains($inputLower, $topicClean) || str_contains($inputLower, $topicStr)) {
                    $score = 0.75;
                }

                if ($score > $highestScore && $score >= 0.70) {
                    $highestScore = $score;
                    $content = '';
                    if (is_array($data)) {
                        $content = $data['info'] 
                            ?? ($data['summary'] 
                            ?? ($data['response'] 
                            ?? ($data['content'] ?? '')));
                    } else {
                        $content = (string)$data;
                    }

                    $bestMatch = [
                        'topic'      => $topicStr,
                        'content'    => $content,
                        'confidence' => $highestScore
                    ];
                }
            }

            return $bestMatch;
        }

        /**
         * Evaluates probabilistic classification when SimpleBayes or MLPattern library features are available.
         */
        private function evaluateBayesTarget(string $inputLower): ?array {
            $skillMap = [
                'MarkovSkill' => ['sequence', 'predict next', 'chain', 'markov'],
                'SummarizerSkill' => ['article', 'text', 'excerpt', 'paragraph', 'document']
            ];

            foreach ($skillMap as $skill => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($inputLower, $kw)) {
                        return ['skill' => $skill, 'confidence' => 0.80];
                    }
                }
            }

            return null;
        }

        /**
         * Classifies prompt question syntax.
         */
        private function detectQuestionType(string $inputLower): string {
            if (preg_match('/^(what|which)\b/', $inputLower)) return 'definition / identity';
            if (preg_match('/^(how)\b/', $inputLower)) return 'procedure / instruction';
            if (preg_match('/^(why)\b/', $inputLower)) return 'reasoning / explanation';
            if (preg_match('/^(where|when|who)\b/', $inputLower)) return 'factual query';
            if (preg_match('/^(can|is|are|does|do|will|should)\b/', $inputLower)) return 'boolean confirmation';
            return 'general instruction';
        }

        /**
         * Extracts distinct key entities filtering common stop words.
         */
        private function extractEntities(string $input): array {
            $words = preg_split('/\s+/', preg_replace('/[^\w\s-]/', '', strtolower($input)));
            $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'it', 'to', 'for', 'of', 'in', 'on', 'at', 'by', 'with', 'and', 'or', 'what', 'how', 'why', 'can', 'you', 'me', 'my', 'this', 'that'];

            $filtered = array_filter($words, function($w) use ($stopwords) {
                return strlen($w) > 2 && !in_array($w, $stopwords);
            });

            return array_values(array_unique($filtered));
        }

        /**
         * Generates markdown summary response.
         */
        private function formatAnalysisResponse(array $intent): string {
            $output = "### Prompt Analysis & Skill Routing\n\n";
            $output .= "Identified Intent: **" . $intent['type'] . "** (Confidence: " . round($intent['confidence'] * 100) . "%)\n";
            $output .= "Target Execution Skill: **" . $intent['target_skill'] . "**\n";
            $output .= "Question Category: **" . ucfirst($intent['question_type']) . "**\n";

            if (!empty($intent['extracted_entities'])) {
                $output .= "Extracted Entities: `" . implode('`, `', array_slice($intent['extracted_entities'], 0, 5)) . "`\n";
            }

            if (!empty($intent['matched_topic'])) {
                $output .= "Matched Topic: **" . ucfirst(str_replace(['_', '-'], ' ', $intent['matched_topic'])) . "**\n";
                $output .= "Retrieved Knowledge: " . $intent['matched_content'] . "\n";
            } else {
                $output .= "Parsed Input: \"" . htmlspecialchars($intent['raw_input']) . "\"\n";
            }

            return trim($output);
        }
    }
}
