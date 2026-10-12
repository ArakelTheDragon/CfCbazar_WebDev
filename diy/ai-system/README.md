# CfCbazar AI System (pure PHP)

Local-first knowledge assistant for shared hosting: no Composer, JSON memory, optional OpenRouter gap-fill.

## Pipeline

```text
index.php
 └── Router
        ├── SpellCorrector (optional)
        ├── SkillData (unified envelope)
        ├── PromptUnderstandingSkill  → fills SkillData (intent, topic, entities, embedding)
        ├── KnowledgeSkill            → orchestrates skill plan, memory, OpenRouter
        │     ├── memory_retrieve (matchFactTopics + vector search)
        │     ├── computeAnswerConfidence
        │     ├── openrouter          (OpenRouterSkill, hybrid only)
        │     ├── memory_store
        │     └── synthesize_local
        └── ResponseUnderstandingSkill → final user-facing text
```

**SkillData** is the bus: every stage reads/writes the same structure (prompt, intent, topic, facts, memory_hits, answer_confidence, draft_answer, final_answer, …).

## Folder structure

```text
ai-system/
├── index.php                 # UI + status (pipeline + SkillData snapshot)
├── eval.php                  # Local regression tests
├── tools/
│   ├── seed.php              # Seed initial knowledge
│   ├── consolidate.php        # Merge and deduplicate facts
│   └── memory_hygiene.php    # Clean up old/low-quality facts
├── config/
│   ├── features.php          # Feature flags and settings
│   ├── openrouter.php        # API key / model (use .local.php for secrets)
│   └── serpapi.php          # SerpAPI web search config
├── core/
│   ├── Router.php            # Thin pipeline: PU → Knowledge → Response
│   ├── SkillData.php         # Unified request envelope
│   ├── MemoryStore.php       # Fact Topics + facts + matchFactTopics
│   ├── LocalEmbedder.php     # Pure-PHP 1536-dim embeddings
│   ├── SpellCorrector.php
│   ├── ConversationStore.php # Per-session short history
│   ├── SkillManager.php      # Loads skills/ classes
│   └── Helpers.php           # HTTP/JSON helpers
├── skills/
│   ├── PromptUnderstandingSkill.php
│   ├── KnowledgeSkill.php    # Capability gate + skill plan + synthesis
│   ├── FactSkill.php         # Facts, keywords, scoring, embeddings, types
│   ├── OpenRouterSkill.php   # External knowledge only
│   ├── SearchSkill.php       # Google via SerpAPI (web search)
│   └── ResponseUnderstandingSkill.php
└── memory/
    ├── memory.json           # Flat fact index
    ├── topics/*.json         # Fact Topic envelopes + facts
    ├── conversation/         # Session turns
    └── training/            # Training data for fact extraction
```

## Fact Topic model

- **Fact Topic** = package (title, tags, aliases, possible_prompts, related_topics, facts[])
- **Retrieval** = matchFactTopics → rank **facts** inside them (not dump whole topic)
- **Answer** = only facts above relevance thresholds (stricter for `define_concept`)
- **Confidence** = blended score + confidence + specificity coverage

## Configuration

|| Flag (`config/features.php`) | Role |
||------------------------------|------|
|| `openrouter_enabled` | Allow gap-fill skill |
|| `knowledge_mode` | `local` \| `hybrid` |
|| `spell_correct_enabled` | Optional spell pass |
|| `search_enabled` | SerpAPI web search skill |
|| `adaptive_thresholds_enabled` | Use adaptive thresholds based on topic quality/size |
|| `weighted_sufficiency_enabled` | Use weighted quality check (score × confidence × source) |
|| `type_diversity_enabled` | Enforce type diversity in fact selection |
|| `intent_synthesis_priority_enabled` | Prioritize facts by intent during synthesis |

## Production Checklist

- [x] Error display disabled in `index.php`
- [x] .gitignore protects secrets and sensitive data
- [x] Feature flags configurable in `config/features.php`
- [x] Memory conversation data protected by .gitignore
- [x] Backup and dev directories protected by .gitignore
- [x] OpenRouter API key in separate config file (not committed)

## Design rules

1. Local memory first; **SearchSkill** (SerpAPI) or OpenRouter only when confidence is low.
   Short "what is …" prompts prefer search when `search_enabled` is true.
2. Definition questions (`what is…`) must not use `*_page` topics or code templates.
3. Code generation with specific requirements ("says 'Beta AI system'") must match those specifics in stored facts.
4. Unsupported capabilities (location, image gen, file upload, …) return a clear "not yet" message.
5. Router stays thin; Knowledge owns skill order and decision logic.
