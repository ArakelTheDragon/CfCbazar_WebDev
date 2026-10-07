# AI System Architecture

## Core Concept

Three-stage pipeline with a **single shared data envelope** (`SkillData`):

**Router** → **PromptUnderstanding** → **Knowledge** → **ResponseUnderstanding** → user

One fixed structure is created at the start of each request. Skills **fill fields in place**; they do not change the shape of the data mid-pipeline.

```
User prompt
    ↓
Router: create SkillData (defaults)
    ↓
PromptUnderstandingSkill (FactSkill): fill understanding + prompt facts + query_embedding
    ↓
KnowledgeSkill (MemoryStore + FactSkill + optional OpenRouter): fill memory_hits, facts, draft_answer
    ↓
ResponseUnderstandingSkill (FactSkill for filter/format only): fill final_answer
    ↓
Final response to user
```

---

## Unified SkillData Envelope

Same keys on every hop. Empty defaults at bootstrap; each stage only writes its fields.

### Top-level fields

| Field | Type | Filled by |
|--------|------|-----------|
| `version` | int | Router |
| `skill` | string | current stage name (optional debug) |
| `prompt` | string | Router / spell-correct |
| `prompt_original` | string | Router |
| `core_query` | string | PromptUnderstanding |
| `intent` | string | PromptUnderstanding |
| `secondary_intents` | string[] | PromptUnderstanding |
| `topic` | string | PromptUnderstanding (+ MemoryStore resolve) |
| `subtopics` | string[] | PromptUnderstanding |
| `entities` | string[] | PromptUnderstanding |
| `question_type` | string | PromptUnderstanding |
| `constraints` | string[] | PromptUnderstanding |
| `key_phrases` | string[] | PromptUnderstanding |
| `is_multi_part` | bool | PromptUnderstanding |
| `facts` | Fact[] | Prompt + Knowledge (merged) |
| `query_embedding` | float[] | PromptUnderstanding (FactSkill / LocalEmbedder) |
| `memory_hits` | Fact[] | Knowledge |
| `memory_sufficient` | bool | Knowledge |
| `openrouter` | object | Knowledge |
| `draft_answer` | string | Knowledge |
| `final_answer` | string | ResponseUnderstanding |
| `session_id` | string | Router / ConversationStore |
| `conversation` | array | Knowledge (optional context) |

### Fact record (always the same shape)

```text
content      string
type         string    (statement | code_html | prompt_intent | ...)
source       string    (prompt | memory | openrouter | gui_seed)
confidence   float
embedding    float[]   (optional, 1536-dim local)
created_at   string    (ISO-8601)
```

### `openrouter` object

```text
enabled    bool
mode       "hybrid" | "local"
gap_fill   bool
raw        string|null
```

### Stage write permissions

| Stage | May write |
|--------|-----------|
| Router | prompt, prompt_original, session_id, version, defaults |
| PromptUnderstanding | core_query, intent, secondary_intents, topic, subtopics, entities, question_type, constraints, key_phrases, is_multi_part, query_embedding, facts (prompt-derived) |
| Knowledge | memory_hits, memory_sufficient, openrouter, facts (merge), draft_answer, conversation |
| ResponseUnderstanding | final_answer only (may read facts/draft for formatting; must not write MemoryStore) |

---

## Skill Components

### PromptUnderstandingSkill
- FactSkill: extract prompt facts + query embedding
- Intent, topic, entities, constraints, multi-part detection
- Output: filled understanding fields on SkillData

### KnowledgeSkill (main processing unit)
- MemoryStore: vector + keyword retrieval into `memory_hits`
- Hybrid mode: local first; OpenRouter gap-fill when enabled and memory insufficient
- FactSkill: extract facts from OpenRouter text; merge + persist
- Produces `draft_answer`

### ResponseUnderstandingSkill
- Formats `draft_answer` → `final_answer`
- May use FactSkill to filter/normalize statements (no memory writes)
- Protects code/HTML as single fenced blocks

### FactSkill (shared utility)
- extractFactsFromPrompt / extractFactsFromText
- embed / cosineSimilarity (via LocalEmbedder)
- mergeFacts
- Used by PromptUnderstanding, Knowledge, and optionally ResponseUnderstanding

---

## Memory System

- Index: `memory/memory.json`
- Topics: `memory/topics/{topic}.json` (facts + embeddings)
- Conversation: `memory/conversation/{sessionId}.json`
- Pure PHP vector search (LocalEmbedder, 1536-dim)
- seed.php / consolidate.php for bulk ingest and topic merge

---

## Configuration

- `config/features.php` — `openrouter_enabled`, `knowledge_mode` (hybrid|local), conversation flags
- `config/openrouter.php` — loads `$API_openrouter` from site `/config.php`
- Site `/config.php` + `/includes/secrets.php` — credentials

---

## Data Flow (SkillData)

```
SkillData{} 
  → PromptUnderstanding.fill understanding + prompt facts
  → Knowledge fills memory_hits + draft_answer (+ stored facts)
  → ResponseUnderstanding fills final_answer
  → User sees final_answer
```

OpenRouter and MemoryStore both consume/produce the **same Fact shape** and read topic/prompt/constraints from the **same SkillData** keys.
