<?php

declare(strict_types=1);

/**
 * Feature flags for CfCbazar AI System.
 * Toggle OpenRouter without code changes.
 */
return [
    // false = local memory only (no OpenRouter chat calls)
    'openrouter_enabled' => true,

    // Conversation short-term memory
    'conversation_enabled' => true,
    'conversation_max_turns' => 10,
];
