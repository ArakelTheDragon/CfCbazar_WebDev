# ai4 — Byte-Keyed Retrieval QA System

**Version:** 10
**Status:** Stable
**Model size:** ~32 MB
**Training time:** ~52 seconds
**Query time:** ~0.1 seconds

## What it is

A retrieval + intent-based question answering system that uses **ASCII bytes as the coordinate space** instead of 256-dimensional float embeddings.

## Why it exists

Modern LLMs use floats: 256 dims per word × millions of words = gigabytes of parameters.
This system uses the bytes of the text itself plus an intent table to route questions to stored answers.

```
| Aspect         | 256-dim embeddings | This system    |
| -------------- | ------------------ | -------------- |
| Model size     | ~5 GB              | **32 MB**      |
| Training       | Hours (GPU)        | **52 s (CPU)** |
| Query          | 0.5–2 s            | **0.1 s**      |
| Explainability | Black box          | **Full trace** |
| Learning       | Gradient descent   | **Add pairs**  |
```

## How it works

1. Every sentence → base-256 integer (byte identity, fixed)
2. `small_table.json` maps intent phrases → categories
3. Input split into `(intent, data)` regions by scanning for intent phrases
4. Each region looked up in sharded memory
5. Multiple regions composed in order (Markov transitions predict next intent)
6. Pronoun resolution carries subject across regions

Example:
```
"what is tea"
↓ split by header
intent = "definition" (from small_table.json)
data = "tea" (bytes minus intent bytes)
↓ lookup
"tea is a brewed drink made from tea leaves"
```

## Architecture

```
| File                  | Purpose                                       |
| --------------------- | --------------------------------------------- |
| `compose.php`         | Core memory class                             |
| `compose_helpers.php` | Matching functions (pure)                     |
| `compose_train.php`   | Ingest `data.jsonl` → `memory/`               |
| `compose_ask.php`     | CLI query                                     |
| `compose_web.php`     | Web UI                                        |
| `compose_add.php`     | Web add + retrain                             |
| `benchmark.php`       | Accuracy test suite                           |
| `small_table.json`    | Intent table                                  |
| `data.jsonl`          | Training corpus (`input || answer`)           |
| `memory/`             | Sharded state (facts + learned + transitions) |
```

## What it's good for

- FAQ bots
- Documentation search
- Structured QA
- Rule-based assistants
- Any bounded-domain retrieval

## What it's NOT good for

- Open-domain reasoning
- Novel question forms not in corpus
- Semantic similarity beyond stored pairs

## Trade-off

**Byte-space identity + intent routing > float-space semantic similarity** for bounded-domain retrieval.

## Current accuracy

79% on 38-test benchmark (`php benchmark.php`):
- single-exact: 90%
- position: 50%
- compound-exact: 100%
- compound-variant: 100%
- multi: 50%
- unknown: 100%

## How to extend

1. Add `input || answer` lines to `data.jsonl`
2. Run `php compose_train.php --rebuild`
3. Run `php benchmark.php` to verify

## License

Your own.
