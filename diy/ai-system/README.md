# CfCbazar AI System

A PHP-only conversational AI system with local-first knowledge storage, designed for shared hosting environments without external AI infrastructure dependencies.

## Overview

The AI system provides conversational AI capabilities through a three-stage pipeline:

**Prompt Understanding → Knowledge Skill → Response Understanding**

### Key Features

- **Local-First**: JSON-based memory storage without external databases
- **Pure PHP**: No external AI infrastructure required
- **Modular Skills**: Pluggable skill architecture
- **Vector Search**: Pure PHP implementation for similarity matching
- **Conversation Tracking**: Session-based conversation memory
- **OpenRouter Integration**: Optional external AI for knowledge gaps
- **Code Generation**: HTML/PHP page generation with document storage

## Architecture

### Core Components

- **Router** (`core/Router.php`) - Main orchestrator controlling the AI pipeline
- **SkillManager** (`core/SkillManager.php`) - Dynamic skill loader and cache
- **MemoryStore** (`core/MemoryStore.php`) - JSON-based knowledge storage with vector search
- **ConversationStore** (`core/ConversationStore.php`) - Browser/session conversation persistence
- **SpellCorrector** (`core/SpellCorrector.php`) - Prompt correction
- **Helpers** (`core/Helpers.php`) - Shared AI utilities (HTTP, JSON, validation)
- **SkillData** (`core/SkillData.php`) - Unified request envelope passed through pipeline
- **LocalEmbedder** (`core/LocalEmbedder.php`) - Pure PHP embedding generation

### Skills

- **PromptUnderstandingSkill** (`skills/PromptUnderstandingSkill.php`) - Prompt analysis and intent detection
- **KnowledgeSkill** (`skills/KnowledgeSkill.php`) - Central knowledge processing unit
- **ResponseUnderstandingSkill** (`skills/ResponseUnderstandingSkill.php`) - Response interpretation and processing
- **FactSkill** (`skills/FactSkill.php`) - Fact extraction, classification, and scoring

### Data Flow

```
User Input → PromptUnderstandingSkill → KnowledgeSkill → ResponseUnderstandingSkill → Final Response
                      ↓                      ↓                      ↓
                 FactSkill              MemoryStore              FactSkill
```

## Configuration

### Features Configuration

**File**: `config/features.php`

```php
<?php
return [
    'openrouter_enabled' => false,  // Enable OpenRouter API
    'knowledge_mode' => 'hybrid',    // 'local' or 'hybrid'
    'conversation_enabled' => true,  // Enable conversation tracking
    'conversation_max_turns' => 10,  // Max conversation turns to remember
];
```

### OpenRouter Configuration

**File**: `config/openrouter.php`

```php
<?php
return [
    'api_key' => getenv('OPENROUTER_API_KEY') ?: 'your-api-key',
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
    'model' => 'openrouter/free',
    'referer' => 'https://your-site.com',
    'title' => 'Your Site Name',
];
```

Create `config/openrouter.local.php` for local overrides (not committed to Git).

## Memory System

### Storage Structure

```
memory/
├── memory.json              # Main index
└── topics/                  # Topic-specific fact storage
    ├── general_topic.json
    ├── php_programming.json
    └── web_development.json
```

### Fact Schema

Each fact contains:
- `type` - Fact type (statement, definition, procedure, code_html, etc.)
- `content` - Fact content
- `confidence` - Confidence score (0.0-1.0)
- `source` - Source (openrouter, user, seed, etc.)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp
- `access_count` - Access frequency
- `decay_rate` - Decay rate for aging
- `embedding` - Vector embedding for similarity search
- `keywords` - Extracted keywords for matching
- `metadata` - Additional metadata

## Usage

### Web Interface

Access the AI system at:
```
https://your-site.com/diy/ai-system/
```

### CLI Interface

```bash
php cli.php "Your question here"
```

### Programmatic Usage

```php
require_once 'core/Router.php';
require_once 'core/MemoryStore.php';

$router = new Router();
$response = $router->handle("What is PHP?");
echo $response;
```

## Development

### Adding New Skills

1. Create skill file in `skills/` directory
2. Implement required methods
3. SkillManager will auto-load it

### Seeding Memory

```bash
php seed.php
```

### Memory Hygiene

```bash
php memory_hygiene.php
```

### Evaluation

```bash
php eval.php
```

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - Detailed architecture description
- [DEVELOPMENT_SUMMARY.md](DEVELOPMENT_SUMMARY.md) - KnowledgeSkill enhancements
- [FACTSKILL_FINAL_SUMMARY.md](FACTSKILL_FINAL_SUMMARY.md) - FactSkill enhancements

## Security

- **API Keys**: Store in environment variables or `config/openrouter.local.php` (not committed)
- **Memory Files**: Protected by `.gitignore` in parent directory
- **SQL Injection**: Uses prepared statements in database operations
- **XSS**: User input is escaped in web interface

## Performance

- **Embedding**: Pure PHP, no external dependencies
- **Vector Search**: Cosine similarity in pure PHP
- **Memory**: JSON-based, no database required
- **Scalability**: Topic-based partitioning for large knowledge bases

## Requirements

- PHP 8.0 or higher
- JSON extension
- Multibyte string extension (mbstring)
- cURL extension (for OpenRouter integration)

## License

Part of the CfCbazar project. See parent directory for license information.

## Support

For issues or questions, refer to the main CfCbazar documentation or contact the development team.
