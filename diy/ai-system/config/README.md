# Configuration

This directory contains configuration files for the AI system.

## Files

### features.php
Feature flags and settings:
- `openrouter_enabled` - Enable/disable OpenRouter API integration
- `knowledge_mode` - Knowledge mode: 'local' or 'hybrid'
- `conversation_enabled` - Enable conversation tracking
- `conversation_max_turns` - Maximum conversation turns to remember

### openrouter.php
OpenRouter API configuration:
- `api_key` - API key (prefer environment variable)
- `base_url` - OpenRouter API endpoint
- `model` - Model to use (e.g., 'openrouter/free')
- `referer` - Site referer for OpenRouter
- `title` - Site title for OpenRouter

### openrouter.local.php.example
Example configuration file for local overrides.
Copy this to `openrouter.local.php` and fill in your actual API key.
This file should NOT be committed to Git.

## Security

- **Never commit** API keys to Git
- Use environment variables when possible
- Use `openrouter.local.php` for local development
- Ensure `openrouter.local.php` is in `.gitignore`

## Configuration Priority

1. Environment variable `OPENROUTER_API_KEY`
2. `config/openrouter.local.php`
3. `config/openrouter.php`
4. Hardcoded fallback (not recommended)

## Setting Up OpenRouter

1. Get API key from https://openrouter.ai/
2. Set environment variable:
   ```bash
   export OPENROUTER_API_KEY="your-api-key"
   ```
3. Or create `config/openrouter.local.php`:
   ```php
   <?php
   return [
       'api_key' => 'your-api-key',
       // ... other settings
   ];
   ```
