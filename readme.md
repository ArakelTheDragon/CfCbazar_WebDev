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

| Aspect | 256-dim embeddings | This system |
|---|---|---|
| Model size | ~5 GB | **32 MB** |
| Training | Hours (GPU) | **52 s (CPU)** |
| Query | 0.5–2 s | **0.1 s** |
| Explainability | Black box | **Full trace** |
| Learning | Gradient descent | **Add pairs** |

## How it works

1. Every sentence → base-256 integer (byte identity, fixed)
2. `small_table.json` maps intent phrases → categories
3. Input split into `(intent, data)` regions by scanning for intent phrases
4. Each region looked up in sharded memory
5. Multiple regions composed in order (Markov transitions predict next intent)
6. Pronoun resolution carries subject across regions

Example:
