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
