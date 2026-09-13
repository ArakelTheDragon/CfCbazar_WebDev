<?php
// ============================================================================
// CfCbazar Local AI System & Skill Dispatcher Engine
// File: /includes/agent_local_system.php
// ============================================================================

if (!function_exists('call_local_system')) {
    function call_local_system(array $messages) {
        $lastMessage = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                $lastMessage = strtolower($messages[$i]['content']);
                break;
            }
        }

        $toolCalls = null;
        $replyContent = "[Local System Mode] CfCbazar Offline Skill Engine active.\n\n";

        // Intent matching across your local skill modules
        if (strpos($lastMessage, 'calculate') !== false || strpos($lastMessage, 'math') !== false) {
            $replyContent .= "Executing **CalculatorSkill** locally...";
            $toolCalls = [[
                'id' => 'local_calc_' . uniqid(),
                'type' => 'function',
                'function' => [
                    'name'     => 'calculator_skill_execute',
                    'arguments' => json_encode(['expression' => $lastMessage])
                ]
            ]];
        } elseif (strpos($lastMessage, 'summarize') !== false || strpos($lastMessage, 'summary') !== false) {
            $replyContent .= "Executing **SummarizerSkill** on available context...";
            $toolCalls = [[
                'id' => 'local_sum_' . uniqid(),
                'type' => 'function',
                'function' => [
                    'name'     => 'summarizer_skill_execute',
                    'arguments' => json_encode(['text' => $lastMessage])
                ]
            ]];
        } elseif (strpos($lastMessage, 'image') !== false || strpos($lastMessage, 'banner') !== false) {
            $replyContent .= "Triggering **ImageGenerationSkill**...";
            $toolCalls = [[
                'id' => 'local_img_' . uniqid(),
                'type' => 'function',
                'function' => [
                    'name'     => 'agent_generate_image',
                    'arguments' => json_encode(['text' => 'CfCbazar Local', 'width' => 400, 'height' => 120])
                ]
            ]];
        } elseif (strpos($lastMessage, 'diagnostic') !== false || strpos($lastMessage, 'check system') !== false) {
            $replyContent .= "Running **DiagnosticSkill** checks...";
            $toolCalls = [[
                'id' => 'local_diag_' . uniqid(),
                'type' => 'function',
                'function' => [
                    'name'     => 'diagnostic_skill_execute',
                    'arguments' => json_encode([])
                ]
            ]];
        } else {
            $replyContent .= "Processed message via **PromptUnderstandingSkill**: \"" . ucfirst($lastMessage) . "\"\n\n"
                           . "### Available Local Skills[cite: 4]:\n"
                           . "* **CalculatorSkill**: Ask to calculate math expressions.\n"
                           . "* **SummarizerSkill**: Ask to summarize texts or uploaded files.\n"
                           . "* **ImageGenerationSkill**: Ask to generate image banners.\n"
                           . "* **DiagnosticSkill**: Ask to run system diagnostics.";
        }

        $messageBlock = [
            'role' => 'assistant',
            'content' => trim($replyContent)
        ];

        if ($toolCalls !== null) {
            $messageBlock['tool_calls'] = $toolCalls;
        }

        return [
            'success' => true,
            'data' => [
                'choices' => [
                    [
                        'message' => $messageBlock
                    ]
                ]
            ]
        ];
    }
}
