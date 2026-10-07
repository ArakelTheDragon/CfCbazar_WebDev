# Core Components

This directory contains the core infrastructure for the AI system.

## Components

### Router.php
Main orchestrator that controls the AI pipeline:
- PromptUnderstandingSkill → KnowledgeSkill → ResponseUnderstandingSkill
- Manages conversation tracking
- Handles skill loading and execution
- Preserves metadata for GUI status panel

### SkillManager.php
Dynamic skill loader and cache:
- Auto-loads skills from `skills/` directory
- Caches skill instances for performance
- Provides skill existence checking

### MemoryStore.php
JSON-based knowledge storage system:
- Topic-based fact storage
- Vector similarity search (pure PHP)
- Fact normalization and deduplication
- Memory pruning and hygiene
- Topic resolution with fuzzy matching

### ConversationStore.php
Browser/session conversation persistence:
- Cookie-based session management
- Conversation turn storage
- Relevance-based conversation retrieval
- Automatic turn limit enforcement

### Helpers.php
Shared utility functions:
- HTTP POST requests (cURL)
- Safe JSON encoding/decoding
- OpenRouter response validation
- String cleaning utilities
- Consistent error handling

### SkillData.php
Unified request envelope:
- Fixed data structure passed through pipeline
- Fact merging and normalization
- Draft/final answer separation
- Session ID and conversation tracking
- Memory hits and sufficiency metadata

### SpellCorrector.php
Prompt correction system:
- Lexicon-based correction
- Memory topic word enhancement
- Query normalization

### LocalEmbedder.php
Pure PHP embedding generation:
- Hash-based embedding creation
- Fixed dimension vectors
- No external dependencies

## Usage

These components are automatically loaded by the Router. Manual usage is typically not required unless extending the system.

## Extending

When adding new core components:
1. Follow existing naming conventions
2. Use type hints and return types
3. Include comprehensive docblocks
4. Handle errors gracefully
5. Maintain backward compatibility
