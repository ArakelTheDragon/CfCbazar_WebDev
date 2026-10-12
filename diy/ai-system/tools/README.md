# AI System Tools

Utility scripts for AI system maintenance and development.

## Files

### seed.php
Seed initial knowledge into the AI system memory from prepared content.

### consolidate.php
Merge and deduplicate facts across topics to improve memory efficiency.

### memory_hygiene.php
Clean up old or low-quality facts from memory to maintain system performance.

## Usage

These tools are typically run from the command line:

```bash
php seed.php
php consolidate.php
php memory_hygiene.php
```

## Notes

- Always backup memory before running maintenance tools
- Tools should be run when the AI system is not under active use
- Changes made by tools are immediately reflected in memory files
