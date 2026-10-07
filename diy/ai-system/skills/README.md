# AI Skills

This directory contains the modular skills that make up the AI pipeline.

## Skills

### PromptUnderstandingSkill.php
Analyzes user prompts and extracts:
- Intent (what the user wants to do)
- Core query (the essential question)
- Topic (subject matter)
- Entities (named entities mentioned)
- Question type (statement, question, etc.)
- Key phrases (important terms)
- Constraints (simple, detailed, step-by-step, etc.)
- Secondary intents
- Subtopics

### KnowledgeSkill.php
Central knowledge processing unit:
- Retrieves relevant facts from MemoryStore
- Scores and ranks facts by relevance
- Decides when to use local vs external knowledge
- Generates answers from local facts
- Calls OpenRouter API when local knowledge is insufficient
- Stores new facts from OpenRouter responses
- Handles code generation with document storage
- Implements gap-fill mode for hybrid knowledge

### ResponseUnderstandingSkill.php
Processes and refines responses:
- Validates response quality
- Ensures response coherence
- Formats final output
- Applies response styling

### FactSkill.php
Fact extraction and classification:
- Extracts factual statements from text
- Classifies fact types (definition, procedure, code_html, etc.)
- Generates vector embeddings
- Scores facts against queries
- Extracts keywords for matching
- Adjusts confidence based on type and length
- Merges fact collections intelligently

## Skill Execution Order

1. **PromptUnderstandingSkill** - Analyze user input
2. **KnowledgeSkill** - Retrieve and synthesize knowledge
3. **ResponseUnderstandingSkill** - Format final response

## Adding New Skills

1. Create new skill file in this directory
2. Implement required methods
3. Register in Router if needed
4. SkillManager will auto-load it

## Skill Interface

Skills should follow these conventions:
- Static methods for stateless operations
- Clear method names (analyze, process, execute, etc.)
- Type hints for parameters and return values
- Comprehensive docblocks
- Error handling for edge cases
