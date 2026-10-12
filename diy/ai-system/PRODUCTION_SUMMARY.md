# AI System Production Summary

## Overview
Production-ready pure-PHP AI system for shared hosting with local-first knowledge storage and optional OpenRouter gap-fill.

## Key Enhancements (Latest)

### KnowledgeSkill
- **Adaptive thresholds**: Adjusts based on topic quality/size (low-fact topics get lenient thresholds)
- **Weighted sufficiency**: Uses score × confidence × source instead of simple count
- **Answer confidence computation**: Blends rank score + confidence + specificity coverage
- **Specific requirements handling**: Ensures code answers match prompt specifics
- **Type diversity**: Enforces intent-specific type limits (definitions, procedures, warnings, etc.)
- **Intent-aware synthesis**: Prioritizes facts by intent during answer synthesis
- **Enhanced topic matching**: Uses `matchFactTopics()` for fact-based topic resolution
- **Soft recency boost**: Small boost for recent facts on time-sensitive queries

### FactSkill
- **Expanded fact types**: example, warning, best_practice (in addition to definition, procedure, code_html, statement)
- **Request-scoped embed cache**: Performance optimization for repeated embeddings
- **Enhanced confidence scoring**: Type-aware confidence adjustments
- **Vector override support**: Allows external vector score injection
- **Empty embedding handling**: Returns [] instead of zero-filled array (prevents false matches)

### MemoryStore
- **matchFactTopics()**: Matches topics by title, tags, aliases, possible_prompts
- **searchByVector($queryKeywords)**: Keyword-prefiltered global search for performance
- **Enhanced metadata**: Supports title, possible_prompts, related_topics in topic meta

## Production Settings

### Security
- ✅ Error display disabled in `index.php`
- ✅ .gitignore protects:
  - includes/secrets.php
  - config_* files (except config/)
  - Memory conversation data
  - Backup and dev directories
  - Upload directories

### Configuration (config/features.php)
```php
"openrouter_enabled" => true,
"knowledge_mode" => "hybrid",
"conversation_enabled" => true,
"spell_correct_enabled" => true,
"search_enabled" => true,
"adaptive_thresholds_enabled" => true,
"weighted_sufficiency_enabled" => true,
"type_diversity_enabled" => true,
"intent_synthesis_priority_enabled" => true,
```

### Limits
- vectorLimit: 80 (increased from 24 for comprehensive retrieval)
- maxAnswerFacts: 80 (safety ceiling, only relevant facts kept)
- maximumMemoryAgeDays: 90
- minimumVectorScore: 0.40 (adaptive)
- minimumCombinedScore: 0.26 (adaptive)

## Architecture

### Pipeline
```
User Prompt
  → SpellCorrector (optional)
  → PromptUnderstandingSkill (intent, topic, entities, embedding)
  → KnowledgeSkill
    → matchFactTopics (fact-based topic resolution)
    → searchByVector (keyword-prefiltered vector search)
    → computeAnswerConfidence (score × confidence × specificity)
    → OpenRouter (if confidence insufficient, max 2 calls)
    → storeFactsWithMeta (enriched metadata)
  → ResponseUnderstandingSkill
  → Final Answer
```

### Fact Types
- `code_html` - Complete HTML/PHP documents
- `definition` - Concept definitions
- `procedure` - Sequential steps
- `example` - Illustrative examples
- `warning` - Warnings and cautions
- `best_practice` - Recommended practices
- `statement` - General statements
- `prompt_intent` - User intent (not stored as knowledge)
- `prompt_core` - Core query without fluff

## Performance Optimizations

1. **Embed caching**: Request-scoped cache for repeated embeddings
2. **Keyword prefiltering**: Global search filters by keywords before vector computation
3. **Adaptive thresholds**: Low-fact topics use lenient thresholds to avoid unnecessary OpenRouter calls
4. **MemoryStore indexing**: Flat fact index for fast retrieval

## Testing Recommendations

1. Test code generation with specific requirements:
   - "Make me a PHP page that says 'Beta AI system'"
   - Should match specifics in stored code_html facts
   - Should use local template if facts don't contain specifics

2. Test fact type diversity:
   - "How to create a user account" (should use procedures + warnings)
   - "What is PHP?" (should use definitions + examples)

3. Test confidence computation:
   - Queries with low-confidence memory should trigger OpenRouter
   - High-confidence queries should answer from local memory

4. Test topic matching:
   - Ambiguous prompts should use matchFactTopics for best topic selection
   - Related topics should be discovered via vector similarity

## Deployment Checklist

- [x] Error display disabled
- [x] .gitignore protects sensitive files
- [x] Feature flags configurable
- [x] All PHP files pass syntax validation
- [x] Memory conversation data protected
- [x] OpenRouter API key in separate config (not committed)
- [x] Backup and dev directories protected
- [x] README files in all directories

## Known Limitations

1. **No avg_score in topic meta**: Adaptive thresholds fall back to 0.50 (compute during index update or remove branch)
2. **filterByAge (90 days)**: May drop useful seed facts (exempt seeds or raise cap)
3. **Duplicate MemoryStore.php**: Skills/MemoryStore.php is a duplicate of core/MemoryStore.php (can be deleted)

## Future Enhancements (Optional)

1. Compute and store avg_score in topic meta during index updates
2. Exempt seed facts from age filtering
3. Add access frequency tracking for fact ranking
4. Implement user feedback loop for quality improvement
5. Add metrics JSONL for performance tracking

## Files Modified in This Release

- diy/ai-system/skills/KnowledgeSkill.php
- diy/ai-system/skills/FactSkill.php
- diy/ai-system/core/MemoryStore.php
- diy/ai-system/config/features.php
- diy/ai-system/index.php
- diy/ai-system/README.md
- diy/ai-system/tools/README.md
- .gitignore

## Production Notes

- System is pure-PHP, no Composer required
- Suitable for shared hosting environments
- Local-first with optional OpenRouter gap-fill
- JSON-based memory storage (no database required)
- Modular skill architecture for easy extension
