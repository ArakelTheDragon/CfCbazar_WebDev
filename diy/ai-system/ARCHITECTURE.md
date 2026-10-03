# AI System Architecture

## Core Concept

The AI system follows a three-stage pipeline architecture:

**Prompt Understanding** → **Knowledge Skill (Main Processing Unit)** → **Response Understanding**

## Skill Components

### Prompt Understanding Skill
- Uses FactSkill to extract meaning from user input
- Analyzes user intent and context
- Identifies key entities and topics

### Knowledge Skill (Main Processing Unit)
- Central processing component that uses MemoryStore
- Retrieves relevant knowledge from JSON-based memory
- Decides on appropriate response strategy
- Produces the final answer
- Integrates with OpenRouter API when needed

### Response Understanding Skill
- Uses FactSkill to extract knowledge from responses
- Validates response quality
- Ensures response coherence

## FactSkill Integration
All three main skills can use FactSkill to:
- Extract meaning from text
- Extract facts from content
- Extract knowledge from data

## Memory System
- JSON-based storage in /memory/ directory
- Organized by topics (separate JSON files per topic)
- Vector similarity search for knowledge retrieval
- Pure PHP implementation (no external databases)

## Data Flow
User Input → Prompt Understanding (FactSkill) → Knowledge Skill (MemoryStore + OpenRouter) → Response Understanding (FactSkill) → Final Response