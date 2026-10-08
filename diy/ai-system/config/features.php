<?php
declare(strict_types=1);
return [
    "openrouter_enabled" => true,
    "knowledge_mode" => "hybrid",
    "conversation_enabled" => true,
    "conversation_max_turns" => 10,
    "spell_correct_enabled" => true, // set false to disable prompt auto-correct

    // KnowledgeSkill enhancements
    "adaptive_thresholds_enabled" => true,  // Use adaptive thresholds based on topic quality/size
    "weighted_sufficiency_enabled" => true,  // Use weighted quality check (score × confidence × source)
    "type_diversity_enabled" => true,        // Enforce type diversity in fact selection
    "intent_synthesis_priority_enabled" => true,  // Prioritize facts by intent during synthesis
];
