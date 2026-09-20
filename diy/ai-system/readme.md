# ARCHITECTURAL WHITE PAPER & SYSTEM SPECIFICATION

**Project:** `ai-system` (CfCbazar AI Engine)

**Host Runtime:** `cfcbazar.42web.io/diy/ai-system/`

**Version:** 2.0 (Modular Hybrid Engine)

**Author / Metadata:** CfCbazar Group

---

## 1. Architectural Philosophy & Guardrails

The `ai-system` framework is built around four non-negotiable engineering principles designed to maintain execution stability across all deployment environments:

1. **Zero External Dependencies (PHP Core Only):** Built natively in PHP 8.0+ using standard extensions (`cURL`, `json`, `mbstring`). No Composer packages, heavy ORMs, or SQL database servers are permitted.
2. **Strict Interface Contracts:** All functionality modules must implement `AISkillInterface` and rely on a standardized array-based `$context` bus.
3. **Flat-File JSON Persistence:** Runtime state, knowledge graphs, and execution records are stored exclusively in `memory/memory.json` using atomic read/write locks.
4. **Decoupled Presentation & Execution:** Interface logic (`index.php`, `cli.php`) is isolated from core processing (`Router`, `SkillManager`), enabling identical engine behavior in browser, terminal, or API environments.

---

## 2. Directory Structure & File Map

The codebase maintains a predictable, component-isolated file hierarchy:

```
ai-system/
├── config/
│   └── openrouter.php              # API cURL transport & endpoint keys
├── core/
│   ├── Helpers.php                 # String normalization & entity utilities
│   ├── MemoryStore.php             # JSON read/write file persistence engine
│   ├── Router.php                  # Primary pipeline orchestrator
│   └── SkillManager.php            # Skill registry & priority execution chain
├── memory/
│   └── memory.json                 # Primary offline state & knowledge graph
├── skills/
│   ├── FactSkill.php               # Triples extraction (Subject - Relation - Object)
│   ├── KnowledgeSkill.php          # 2-Tier memory search & OpenRouter fallback
│   └── PromptUnderstandingSkill.php# Intent analysis & entity extraction
├── cli.php                         # Terminal execution entry point
├── folder-structure.txt            # System file manifest
├── index.php                       # Web GUI with client-side Markdown parser
└── index_backup_working.php        # System snapshot recovery file

```

---

## 3. Strict Interface Contracts & Data Schemas

To prevent breaking changes across module files during development, all newly authored classes must adhere to these rigid contract specifications.

### 3.1 The Skill Interface Contract (`AISkillInterface`)

Every file placed in `/skills/` must implement `AISkillInterface` and include standard reusable functions.

```php
<?php

require_once __DIR__ . '/../includes/reusable.php';

interface AISkillInterface {
    /**
     * Unique identifier for the skill module.
     */
    public function getName(): string;

    /**
     * Numerical execution order (lower numbers execute first).
     * Priority Scale:
     * 0  - 10 : Parsers & Intent Analyzers (PromptUnderstandingSkill)
     * 11 - 20 : Fact Extractors & Tool Actions (FactSkill, ToolSkill)
     * 21 - 30 : Knowledge Lookups & LLM Fallbacks (KnowledgeSkill)
     */
    public function getPriority(): int;

    /**
     * Determines if the skill should run based on current context.
     */
    public function canHandle(array $context): bool;

    /**
     * Main execution pipeline. Modifies $context by reference and returns status array.
     */
    public function execute(array &$context): array;
}

```

### 3.2 The Global Pipeline `$context` Schema

Data passed through `Router.php` and `SkillManager.php` relies on a unified `$context` array. All skills must read from and write to these exact keys:

```php
$context = [
    // Inputs
    'prompt'            => (string) "User input text",
    'start_time'        => (float)  microtime(true),

    // Classification (Populated by PromptUnderstandingSkill)
    'intent'            => (string) "QUERY | FACT_STORE | EXECUTE_TOOL | GENERAL",
    'question_type'     => (string) "WHAT_IS | HOW_TO | YES_NO | STATEMENT",
    'parsed_entities'   => (array)  ['subject' => '', 'predicate' => '', 'object' => ''],
    'topic'             => (string) "Primary subject matter",

    // Execution State
    'resolved'          => (bool)   false, // Set to true when answer is found
    'response'          => (string) "",    // Final Markdown or text response
    'source'            => (string) "LOCAL_MEMORY | OPENROUTER | TOOL_EXECUTION",

    // System Telemetry
    'errors'            => (array)  [],
    'execution_trace'   => (array)  []
];

```

### 3.3 Core Component API Contracts

#### `core/MemoryStore.php`

```php
class MemoryStore {
    public function __construct(string $filePath = __DIR__ . '/../memory/memory.json');
    public function read(): array;
    public function write(array $data): bool;
    public function getTopic(string $topic): ?array;
    public function setTopic(string $topic, array $data): bool;
}

```

#### `core/Router.php`

```php
class Router {
    public string $lastTopic = "";
    public array $lastEntities = [];
    public string $lastIntent = "";
    public string $lastQuestionType = "";

    public function __construct();
    public function handle(string $prompt): string;
}

```

#### `core/SkillManager.php`

```php
class SkillManager {
    public function registerSkill(AISkillInterface $skill): void;
    public function sortSkills(): void;
    public function executeChain(array &$context): string;
}

```

#### `config/openrouter.php`

```php
/**
 * Global fallback function for external intelligence calls.
 */
function queryOpenRouter(string $prompt, string $systemPrompt = ""): ?string;

```

---

## 4. Execution Lifecycle & Data Flow Sequence

1. **Request Ingestion (`index.php` / `cli.php`)**:
* Accepts user text input, initializes start timestamp, and instantiates `Router`.


2. **Pipeline Initialization (`core/Router.php`)**:
* Constructs `$context` payload with raw prompt.
* Instantiates `SkillManager` and triggers auto-discovery of all scripts under `/skills/`.


3. **Priority Order Execution (`core/SkillManager.php`)**:
* **Stage 1: Intent & Entity Parsing (`skills/PromptUnderstandingSkill.php`)**
* Extracts subject, predicate, object, topic, and intent type into `$context`.


* **Stage 2: Fact Extraction (`skills/FactSkill.php`)**
* If input is an explicit factual statement, stores triple directly via `MemoryStore` and sets `$context['resolved'] = true`.


* **Stage 3: Knowledge Lookup & Fallback (`skills/KnowledgeSkill.php`)**
* If `$context['resolved']` is `false`, performs exact and fuzzy searches against `memory/memory.json`.
* If local lookup fails, routes request to `queryOpenRouter()` via `config/openrouter.php`.
* Automatically saves newly acquired external responses into `memory/memory.json` for future offline reuse.




4. **Response Formatting & Telemetry Output**:
* `Router.php` updates public properties (`lastTopic`, `lastEntities`, `lastIntent`, `lastQuestionType`).
* `index.php` receives output string and renders parsed Markdown dynamically via `marked.min.js`.



---

## 5. Development Continuity Protocol

To eliminate file incompatibility and context loss across development cycles:

* **No Unsolicited Structural Overhauls:** Every new feature must be added either as a new class in `skills/` implementing `AISkillInterface` or as an extended helper in `core/Helpers.php`.
* **Explicit Context Signatures:** No skill module may modify global variables directly; all inter-module communication must occur through the `$context` array.
* **Strict Class & Method Declarations:** All PHP functions generated in subsequent updates will include complete parameter types, return types, and strict docblocks.

The system specification and inter-file dependency contracts are mapped and locked. We can now safely begin implementing the next developmental steps without risking compatibility drift across files.
