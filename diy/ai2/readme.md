```markdown
# CfCbazar Local Modular PHP AI Engine

A lightweight, folder-based PHP AI engine built for execution on standard shared web hosting environments. The engine combines local rule-based skills, structured JSON file memory, and dynamic OpenRouter API integration to provide intelligent query routing, knowledge base ingestion, and client-side Markdown rendering.

---

## Key Features

* **Modular Skill System**: Extensible architecture powered by `AISkillInterface` for intent scoring, calculation, summarization, and memory management.
* **Hybrid Memory Architecture**: Dual-tier storage using a lightweight catalog index (`local_memory.json`) and detailed topic files (`memory_topics/*.json`).
* **OpenRouter Integration**: Fallback integration for open-access models (`meta-llama/llama-3.1-8b-instruct:free`) with error handling and formatted error reporting.
* **Automated Knowledge Ingestion**: Automatically ingests missing topics from OpenRouter responses directly into local JSON memory storage.
* **Plugin Architecture**: InfinityFree-compatible dynamic plugin loader in `/plugins`.
* **Interactive UI**: Dark-mode frontend featuring client-side Markdown parsing via Marked.js and built-in code block copy controls.

---

## Directory Structure

```text
diy/ai2/
├── index.php                 # Core AI Engine dispatcher, UI, & cURL OpenRouter handler
├── local_admin.php           # Local engine management interface
├── local_memory.json         # Catalog index mapping topics to memory files
├── memory_topics/            # Folder containing individual topic JSON payloads
│   └── *.json
├── skills/                   # Modular AI skill modules
│   ├── AISkillInterface.php  # Base skill contract
│   ├── KnowledgeSkill.php    # Local memory retrieval & ingestion handler
│   ├── CalculatorSkill.php   # Math parsing skill
│   ├── SummarizerSkill.php   # Content summarization skill
│   └── skill_list.json       # Active skill registry
├── plugins/                  # Extensible engine plugins
│   └── PluginLoader.php
└── libs/                     # Third-party utilities & stemmers

```

---

## Installation & Setup

1. **Clone/Upload Repository**: Upload the project directory to your web server (e.g., `/diy/ai2/`).
2. **Environment & Configuration**: Ensure your server has PHP 8.x with `cURL` enabled.
3. **Configure API Key**: Define your OpenRouter key in your central `config.php`:
```php
$API_openrouter = "your_openrouter_api_key_here";
define('CFCBAZAR_AI_MODEL', 'meta-llama/llama-3.1-8b-instruct:free');

```


4. **Folder Permissions**: Set `0755` write permissions on `diy/ai2/` and `memory_topics/`.

---

## Memory Architecture & Ingestion Flow

1. **Local Lookup**: When a user queries a topic, `KnowledgeSkill.php` checks `local_memory.json` for existing topic keys.
2. **File Payload**: If matched, the engine loads details from `memory_topics/{topic_slug}.json`.
3. **OpenRouter Fallback**: If missing locally, the query routes to OpenRouter.
4. **Auto-Save**: Successful OpenRouter responses are parsed, saved as new JSON files in `memory_topics/`, and indexed into `local_memory.json`.

---

## Production Improvements & Security Roadmap

The following tasks are scheduled for production hardening:

### 1. Security & Access Control

* [ ] **Folder Access Guard**: Add an `.htaccess` file inside `memory_topics/` (`Require all denied`) to prevent direct browser URL dumping of memory files.
* [ ] **Admin Authentication**: Enforce session verification or token key checks (`HTTP_X_ADMIN_KEY`) at the top of `local_admin.php`.

### 2. Concurrency & Data Integrity

* [ ] **Atomic File Writes**: Apply `LOCK_EX` flags across all `file_put_contents()` calls in `index.php` and `KnowledgeSkill.php` to prevent JSON corruption during concurrent requests:
```php
file_put_contents($filePath,$jsonData, LOCK_EX);

```



### 3. Engine & Memory Refinements

* [ ] **Catalog Introspection**: Enhance `KnowledgeSkill.php` to directly parse and return stored memory summaries for prompts like *"What topics do you know?"*.
* [ ] **Path Sanitization**: Ensure all file operations pass relative paths through `basename()` prior to path concatenation.
* [ ] **Codebase Cleanup**: Remove unreferenced legacy files (`PromptUnderstandingSkill2.php`, `download2.php`, `index2.php`).
* [ ] **Execution Limits**: Set standard cURL timeout limits (`CURLOPT_TIMEOUT => 15`) to prevent shared hosting maximum execution time crashes.

---

## License

Distributed under the MIT License. Built for CfCbazar Group projects.

```

```
