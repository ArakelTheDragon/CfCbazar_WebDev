<?php
// ============================================================================
// CfCbazar Group — KnowledgeSkill.php
// File: /includes/skills/KnowledgeSkill.php
// ============================================================================

if (!class_exists('KnowledgeSkill')) {
    class KnowledgeSkill implements AISkillInterface {

        private string $topicsDir;

        public function __construct(string $topicsDir = __DIR__ . '/../memory_topics') {
            $this->topicsDir = rtrim($topicsDir, '/\\');
        }

        public function getKeywords(): array {
            return [
                'remember', 'learn', 'what is', 'who is', 'tell me about', 'fact',
                'topics', 'what do you know', 'what topics', 'known topics', 'list knowledge', 'your knowledge', 'memory list'
            ];
        }

        public function getPriority(): int { 
            return 9; 
        }

        public function execute(string $input, array &$memory): string {

            if (!is_dir($this->topicsDir)) {
                @mkdir($this->topicsDir, 0755, true);
            }

            $now = date('Y-m-d H:i:s');
            $inputLower = strtolower(trim($input));

            // 1. Memory Introspection: Return list of all stored topics when asked
            if (preg_match('/what (topics|facts)|what do you know|known topics|list (knowledge|topics|memory)|your knowledge/i', $inputLower)) {
                if (empty($memory["knowledge"]) || !is_array($memory["knowledge"])) {
                    return "I don't have any specific topics saved in my local memory index yet.";
                }

                $output = "### Currently Stored Knowledge Topics\n\n";
                foreach ($memory["knowledge"] as $topic => $data) {
                    $summary = is_array($data) ? ($data['summary'] ?? 'No summary available.') : $data;
                    $output .= "* **" . ucwords($topic) . "**: " . $summary . "\n";
                }
                return $output;
            }

            // 2. Handle explicit learning ("remember [topic] is [fact]")
            if (preg_match('/remember\s+(.+)\s+is\s+(.+)/i', $input, $m)) {
                $topic = strtolower(trim($m[1]));
                $info  = trim($m[2]);

                $slug = preg_replace('/[^a-z0-9_]+/', '_', $topic);
                $slug = trim($slug, '_');
                $filename = "{$slug}.json";
                $relativeFilePath = "memory_topics/{$filename}";
                $fullFilePath = $this->topicsDir . '/' . $filename;

                // Save index in local_memory.json ($memory)
                $memory["knowledge"][$topic] = [
                    "file"    => $relativeFilePath,
                    "summary" => substr($info, 0, 120)
                ];

                // Create or update topic-specific JSON file
                if (file_exists($fullFilePath)) {
                    $topicData = json_decode(file_get_contents($fullFilePath), true) ?: [];
                    $topicData['last_updated'] = $now;
                    $topicData['content']      = $info;
                    $topicData['detail']       = $info;
                    $topicData['updates'][]    = [
                        'timestamp' => $now,
                        'source'    => 'Local User',
                        'note'      => "Updated fact: {$info}"
                    ];
                } else {
                    $topicData = [
                        'topic'        => $topic,
                        'last_updated' => $now,
                        'summary'      => substr($info, 0, 120),
                        'content'      => $info,
                        'detail'       => $info,
                        'updates'      => [
                            [
                                'timestamp' => $now,
                                'source'    => 'Local User',
                                'note'      => 'Initial knowledge entry created.'
                            ]
                        ]
                    ];
                }

                file_put_contents($fullFilePath, json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                return "Saved new knowledge about '{$topic}'.";
            }

            // 3. Handle reading specific existing facts from local memory
            if (isset($memory["knowledge"]) && is_array($memory["knowledge"])) {
                foreach ($memory["knowledge"] as $topic => $data) {
                    if (stripos($input, $topic) !== false) {
                        
                        if (is_array($data)) {
                            $filename = basename($data['file'] ?? "{$topic}.json");
                            $fullFilePath = $this->topicsDir . '/' . $filename;

                            if (file_exists($fullFilePath)) {
                                $topicJson = json_decode(file_get_contents($fullFilePath), true);
                                $content   = $topicJson['content'] ?? $topicJson['detail'] ?? $data['summary'] ?? 'No detail content found.';
                                return "Knowledge [{$topic}]: " . $content;
                            }

                            return "Knowledge [{$topic}]: " . ($data['summary'] ?? 'Topic file missing.');
                        }

                        return "Knowledge [{$topic}]: " . $data;
                    }
                }
            }

            // 4. Fallback to OpenRouter if topic is missing locally
            if (function_exists('queryOpenRouter')) {
                $openRouterReply = queryOpenRouter($input);

                if ($openRouterReply && !str_contains($openRouterReply, 'OpenRouter Error')) {
                    $extractedTopic = preg_replace('/^(what is|who is|tell me about|fact)\s+/i', '', trim($input));
                    $extractedTopic = strtolower(trim($extractedTopic));

                    $slug = preg_replace('/[^a-z0-9_]+/', '_', $extractedTopic);
                    $slug = trim($slug, '_');
                    $filename = "{$slug}.json";
                    $relativeFilePath = "memory_topics/{$filename}";
                    $fullFilePath = $this->topicsDir . '/' . $filename;

                    $lines   = explode("\n", trim($openRouterReply));
                    $summary = substr(trim($lines[0]), 0, 120);

                    $memory["knowledge"][$extractedTopic] = [
                        "file"    => $relativeFilePath,
                        "summary" => $summary
                    ];

                    $topicData = [
                        'topic'        => $extractedTopic,
                        'last_updated' => $now,
                        'summary'      => $summary,
                        'content'      => $openRouterReply,
                        'detail'       => $openRouterReply,
                        'updates'      => [
                            [
                                'timestamp' => $now,
                                'source'    => 'OpenRouter AI',
                                'note'      => 'Ingested new topic answer from OpenRouter.'
                            ]
                        ]
                    ];

                    file_put_contents($fullFilePath, json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    return "OpenRouter AI (Saved to Knowledge Folder):\n\n" . $openRouterReply;
                }
            }

            return "I don't know that yet. Teach me using: remember [topic] is [fact].";
        }
    }
}
