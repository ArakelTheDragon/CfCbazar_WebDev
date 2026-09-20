AI‑System Architectural White Paper

A Modular, Self‑Learning, Zero‑Dependency PHP Artificial Intelligence Engine

---

1. Executive Summary

The ai-system is a fully modular, zero‑dependency PHP artificial intelligence engine designed around three core principles:

- Local-first intelligence through a flat‑file JSON memory store  
- Modular skill pipeline enabling incremental capability expansion  
- Hybrid reasoning combining local memory with OpenRouter LLM responses  

The system is intentionally lightweight, portable, and deployable on low-resource shared hosting environments. It provides a clean separation between:

- Understanding (PromptUnderstandingSkill)  
- Fact extraction (FactSkill)  
- Knowledge synthesis (KnowledgeSkill)  
- Memory persistence (MemoryStore)  
- Pipeline orchestration (Router)  
- Presentation layer (index.php / cli.php)

This white paper documents the architecture in detail to ensure future development remains consistent, compatible, and maintainable.

---

2. System Overview

The ai-system operates as a pipeline controller that processes user input through a series of modular skills. Each skill performs a specific function and communicates through structured arrays.

Pipeline Flow

1. PromptUnderstandingSkill  
   Extracts topic, intent, entities, question type, and facts from the prompt using FactSkill.

2. KnowledgeSkill  
   Sends the exact user prompt to OpenRouter.  
   Receives natural-language output.  
   Uses FactSkill to extract new facts from the response.

3. MemoryStore  
   Merges:
   - prompt facts  
   - OpenRouter facts  
   - existing memory facts  

4. KnowledgeSkill  
   Synthesizes a final answer from local memory, not from OpenRouter directly.

5. Router  
   Orchestrates the entire process and exposes diagnostic metadata to the GUI.

---

3. File Structure

The system’s file structure is intentionally simple:

`
ai-system/
│
├── core/
│   ├── Router.php
│   ├── SkillManager.php
│   ├── MemoryStore.php
│   ├── Helpers.php
│
├── skills/
│   ├── PromptUnderstandingSkill.php
│   ├── FactSkill.php
│   ├── KnowledgeSkill.php
│
├── config/
│   ├── openrouter.php
│
├── memory/
│   ├── memory.json
│
├── index.php
├── cli.php
`

This structure ensures:

- No cross-file ambiguity  
- No hidden dependencies  
- No accidental coupling  
- Clear boundaries between layers

---

4. Core Components

4.1 Router.php

Router.php is the pipeline controller.  
It does not perform any AI logic.  
It simply orchestrates:

- PromptUnderstandingSkill  
- KnowledgeSkill  
- MemoryStore  

It also exposes:

- lastTopic  
- lastEntities  
- lastIntent  
- lastQuestionType  

These are used by the GUI for diagnostics.

---

4.2 SkillManager.php

SkillManager dynamically loads skills from /skills/ and caches them.  
It ensures:

- Skills are modular  
- Skills are replaceable  
- Skills do not depend on each other directly  

This prevents cross-file incompatibility — a common failure mode in AI-assisted development.

---

4.3 MemoryStore.php

MemoryStore is the local knowledge base.

It stores facts in:

`
memory/topics/<topic>
`

MemoryStore supports:

- topic-based fact retrieval  
- merging new facts  
- atomic saving  
- JSON persistence  

This is the foundation of the system’s self-learning behavior.

---

4.4 PromptUnderstandingSkill.php

This skill performs:

- intent detection  
- topic extraction  
- entity extraction  
- question type classification  
- fact extraction from the prompt (via FactSkill)

It does not call OpenRouter.

It is lightweight, fast, and rule-based.

---

4.5 FactSkill.php

FactSkill is the single source of truth for fact extraction.

It extracts facts from:

- the user prompt  
- OpenRouter responses  

It normalizes facts into a consistent structure:

`
[
  "type" => "...",
  "value" => "...",
  "confidence" => 0.80,
  "source" => "prompt" or "openrouter",
  "created_at" => "ISO timestamp"
]
`

All skills rely on FactSkill for fact extraction, ensuring consistency across the system.

---

4.6 KnowledgeSkill.php

KnowledgeSkill is the reasoning engine.

It:

1. Sends the exact user prompt to OpenRouter  
2. Extracts facts from the response using FactSkill  
3. Merges:
   - prompt facts  
   - OpenRouter facts  
   - memory facts  
4. Produces the final answer from memory, not from OpenRouter directly  
5. Updates memory.json with new facts  

This ensures:

- consistency  
- self-learning  
- offline capability  
- stable answers  

---

5. Architectural Strengths

5.1 Modularity
Every skill is isolated.  
No skill depends on another skill’s internal logic.  
This prevents cross-file incompatibility.

5.2 Zero Dependencies
No database.  
No external libraries.  
No frameworks.  
Only pure PHP.

5.3 Self-Learning
MemoryStore grows over time.  
Facts accumulate.  
Answers become richer.

5.4 Hybrid Reasoning
Local memory + OpenRouter fallback.

5.5 Multi-Interface Support
- Web GUI  
- CLI runtime  

5.6 Diagnostics
Router exposes metadata for debugging and GUI display.

---

6. Risks & Bottlenecks

6.1 Flat-File Concurrency
Multiple writes to memory.json can cause corruption.

6.2 Memory Growth
Large memory.json files slow down parsing.

6.3 Rule-Based Intent Detection
PromptUnderstandingSkill may misclassify complex prompts.

6.4 External API Reliability
OpenRouter timeouts or rate limits require robust fallback logic.

---

7. Development Roadmap

Phase 1 — Storage Optimization
- Atomic writes  
- Memory pruning  
- Topic-based sharding  
- LRU eviction  

Phase 2 — Enhanced Local Reasoning
- TF-IDF indexing  
- Semantic similarity scoring  
- Confidence-weighted fact retrieval  

Phase 3 — Autonomous Skills
- Web scraping  
- Code execution sandbox  
- System diagnostics  
- ToolSkill for actionable intents  

---

8. Conclusion

The ai-system is a clean, modular, self-learning PHP AI engine with a robust architectural foundation.  
Your design choices — especially the separation of PromptUnderstandingSkill, FactSkill, KnowledgeSkill, and MemoryStore — are correct and scalable.

This white paper ensures we maintain architectural coherence as we continue development.

---
