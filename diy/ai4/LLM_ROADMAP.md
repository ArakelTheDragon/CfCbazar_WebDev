# LLM Evolution Roadmap

## Current State: Retrieval-Based QA System

### What We Have
- **Byte-number encoding**: Converts text to base-256 numbers instead of 256-dim float vectors
- **Sharded memory**: 96K+ facts in JSON shards with inverted index
- **Intent table**: Fixed mapping for query chunking ("what is" → "definition")
- **Ensemble scoring**: Intent (50%) + word (15%) + char (5%) with agreement bonus
- **Markov transitions**: Learns intent transitions for compound queries
- **Multi-sentence composition**: Splits queries into regions, resolves each
- **Pronoun resolution**: Basic entity tracking across regions

### What We Don't Have (vs. Full LLM)
- ❌ Generative capability (only retrieval, no novel text creation)
- ❌ Neural architecture (rule-based matching, not attention mechanisms)
- ❌ Context window beyond pronoun resolution
- ❌ Training on raw text (requires structured Q/A pairs)
- ❌ Emergent reasoning (pattern matching only)
- ❌ Continual learning (fixed intent table, rules)

---

## Phase 1: Hybrid Architecture (Near-Term)

**Goal**: Add a small neural layer while keeping retrieval backbone

### 1.1 Neural Intent Classification
**Why**: Current intent table is manual and fixed. Neural classifier can generalize.

**Implementation**:
```php
// Train a lightweight classifier (e.g., TinyBERT or DistilBERT)
// Input: query text
// Output: intent probability distribution
// Model size: ~50MB (vs. 5GB for full embeddings)
```

**Benefits**:
- Handles unseen intent phrases ("explain what X is" → "definition")
- Can be fine-tuned with user corrections
- Still uses byte encoding for memory lookup

**Timeline**: 2-3 weeks

### 1.2 Neural Re-ranking
**Why**: Current scoring is rule-based. Neural re-ranking improves accuracy.

**Implementation**:
- Keep fast retrieval (current system)
- Add cross-encoder to re-rank top 10 candidates
- Model: lightweight sequence-pair classifier (~100MB)

**Benefits**:
- Better semantic matching beyond word overlap
- Still fast (re-rank only top candidates)
- Explainable (show original scores vs. re-ranked)

**Timeline**: 2-3 weeks

### 1.3 Generation Layer (Template-Based)
**Why**: Move from exact answers to fluent generation.

**Implementation**:
```php
// Generate from retrieved facts using templates
// "The capital of {country} is {city}."
// "{person} invented {invention} in {year}."
// Extract entities from retrieved facts, fill templates
```

**Benefits**:
- More natural answers than verbatim retrieval
- Still controllable and explainable
- No hallucination (grounded in facts)

**Timeline**: 1-2 weeks

**Phase 1 Total**: 5-8 weeks

---

## Phase 2: Neural Memory (Mid-Term)

**Goal**: Replace byte encoding with learned embeddings but keep efficiency

### 2.1 Learned Byte-Level Embeddings
**Why**: Current byte encoding is positional, not semantic. Learned embeddings capture meaning.

**Implementation**:
- Train a small transformer on your corpus
- Use byte-level tokenization (keep memory efficiency)
- Output: 64-dim vectors (vs. 256-dim standard)
- Quantize to int8 (8x smaller than float32)

**Architecture**:
```
Input: bytes → 4-layer transformer → 64-dim embedding → int8 quantization
```

**Memory savings**:
- 64-dim int8 = 64 bytes per embedding
- 256-dim float32 = 1024 bytes per embedding
- **16x reduction** vs. standard embeddings

**Timeline**: 4-6 weeks

### 2.2 Differentiable Index
**Why**: Current index is heuristic. Learned index can optimize for query patterns.

**Implementation**:
- Train a small neural network to predict shard for a query
- Loss: minimize shard lookups while maintaining recall
- Can be fine-tuned with query logs

**Benefits**:
- Faster queries (fewer shard loads)
- Adaptive to query distribution
- Still explainable (can inspect learned routing)

**Timeline**: 3-4 weeks

### 2.3 End-to-End Retrieval
**Why**: Current system has separate encoding, index, retrieval, scoring. End-to-end learns jointly.

**Implementation**:
- Single model: query → top-k candidates
- Trained with contrastive loss (positive/negative pairs)
- Uses your existing data.jsonl as training data

**Benefits**:
- Better overall accuracy
- Fewer hyperparameters to tune
- Still efficient (can prune at inference)

**Timeline**: 6-8 weeks

**Phase 2 Total**: 13-18 weeks

---

## Phase 3: Generative LLM (Long-Term)

**Goal**: Full generative capability with retrieval grounding

### 3.1 Causal Language Model
**Why**: Need token-by-token generation for novel text.

**Implementation**:
- Train a small decoder-only transformer (e.g., 100M parameters)
- Use byte-level tokenization (keep memory efficiency)
- Train on your corpus + additional text data

**Model size**:
- 100M parameters ≈ 400MB (quantized to int8)
- Compare: GPT-3 (175B) = 700GB, Llama-2-7B = 14GB

**Timeline**: 8-12 weeks

### 3.2 Retrieval-Augmented Generation (RAG)
**Why**: Pure generation hallucinates. Retrieval provides grounding.

**Implementation**:
```
Query → Intent classifier → Retrieve facts → LLM generates → Output
                      ↑
                 Byte-encoded memory
```

**Benefits**:
- Reduces hallucination
- Can cite sources
- Still efficient (retrieval is fast)

**Timeline**: 4-6 weeks

### 3.3 Training Pipeline
**Why**: Need systematic way to train and update models.

**Implementation**:
```bash
# 1. Collect data
scrape_wikipedia.py > raw_text.txt
extract_qa_pairs.py > data.jsonl

# 2. Train intent classifier
train_intent_classifier.py --data data.jsonl --output models/intent.bin

# 3. Train embeddings
train_embeddings.py --corpus raw_text.txt --output models/embeddings.bin

# 4. Train LLM
train_llm.py --corpus raw_text.txt --qa data.jsonl --output models/llm.bin

# 5. Fine-tune with retrieval
train_rag.py --llm models/llm.bin --memory memory/ --output models/rag.bin
```

**Timeline**: 6-8 weeks

### 3.4 Context Window
**Why**: Need to maintain conversation history.

**Implementation**:
- Add conversation context to retrieval query
- Use attention mechanisms to weight recent vs. distant context
- Prune context to token limit (e.g., 4096 tokens)

**Timeline**: 4-6 weeks

**Phase 3 Total**: 22-32 weeks

---

## Phase 4: Advanced Capabilities (Optional)

### 4.1 Multi-Modal
- Add image inputs (vision transformer)
- Combine with text retrieval

### 4.2 Tool Use
- Allow model to call external APIs (calculator, search)
- Chain-of-thought reasoning

### 4.3 Reinforcement Learning
- RLHF for alignment
- Reward modeling for helpfulness

### 4.4 Scaling
- Increase model size (100M → 1B → 7B)
- Distributed training
- Mixture of Experts

---

## Technical Considerations

### Memory Efficiency Strategies

| Approach | Size per embedding | 100K embeddings | Notes |
|----------|-------------------|-----------------|-------|
| Current byte encoding | 0 bytes (computational) | 0 | No storage, but no semantics |
| 256-dim float32 | 1024 bytes | 100 MB | Standard, but large |
| 64-dim int8 | 64 bytes | 6.4 MB | **Recommended** |
| 32-dim int8 | 32 bytes | 3.2 MB | May hurt accuracy |
| Product quantization | 8 bytes | 0.8 MB | Best compression, complex |

### Architecture Comparison

| System | Parameters | Memory | Speed | Accuracy | Generation |
|--------|-----------|--------|-------|----------|------------|
| Current | 0 (rules) | ~50MB | Fast | 70-85% | ❌ |
| Phase 1 | ~150MB | ~200MB | Medium | 85-90% | ⚠️ Template |
| Phase 2 | ~500MB | ~500MB | Medium | 90-95% | ❌ |
| Phase 3 | ~400MB | ~1GB | Slow | 95%+ | ✅ Full |

### Training Data Requirements

| Phase | Data type | Quantity | Quality |
|-------|-----------|----------|---------|
| Current | Q/A pairs | 100K+ | Structured |
| Phase 1 | Q/A pairs | 100K+ | Structured |
| Phase 2 | Raw text | 1M+ tokens | Diverse |
| Phase 3 | Raw text | 100M+ tokens | High-quality |

---

## Quick Wins (Do These First)

### 1. Expand Corpus (1-2 days)
```bash
# Generate more Q/A pairs
php gen_pairs.php --count 500000 --output data.jsonl
php compose_train.php --rebuild
```

### 2. Improve Intent Table (1 day)
```json
// Add more synonyms and patterns
{
  "give me a definition of": "definition",
  "can you define": "definition",
  "what's the meaning of": "definition",
  "what would you say is": "definition",
  // ... add 50+ more
}
```

### 3. Add Context Tracking (2-3 days)
```php
// Store last 3 questions/answers
class ConversationContext {
    private array $history = [];
    private int $maxHistory = 3;

    public function add(string $question, string $answer): void {
        $this->history[] = ['q' => $question, 'a' => $answer];
        if (count($this->history) > $this->maxHistory) {
            array_shift($this->history);
        }
    }

    public function resolvePronouns(string $query): string {
        // Replace "it", "they", etc. with entities from history
    }
}
```

### 4. Benchmark Before Changes (1 day)
```bash
php benchmark.php > baseline.txt
# Save this for comparison
```

---

## Alternative: Skip to RAG

If you want generation **now** without building from scratch:

### Option A: Use Existing Small LLM
```python
# Use Llama-2-7B or Mistral-7B (quantized to 4-bit)
# Model size: ~4GB
# Wrap with your retrieval system
```

**Pros**:
- Get generation immediately
- Already trained on massive data
- Can fine-tune on your domain

**Cons**:
- Larger memory footprint
- Less control over architecture
- May hallucinate without good retrieval

### Option B: Use API + Your Retrieval
```python
# Use OpenAI/Claude API for generation
# Use your system for retrieval and grounding
# Hybrid: your system provides facts, API generates answer
```

**Pros**:
- Best of both worlds
- Low local compute
- Can switch models easily

**Cons**:
- API costs
- Privacy concerns
- Dependency on external service

---

## Recommended Path

Given your current system and constraints:

### If you want to stay in PHP + keep efficiency:
**Phase 1 → Phase 2** (Hybrid architecture)
- Add neural intent classifier
- Add neural re-ranking
- Add template-based generation
- Timeline: 5-8 weeks

### If you want full generation now:
**Skip to Option A** (Use existing small LLM)
- Integrate Llama-2-7B-quantized
- Use your retrieval for grounding
- Timeline: 1-2 weeks

### If you want to learn and build from scratch:
**Phase 1 → Phase 2 → Phase 3** (Full build)
- Build incrementally
- Learn each component
- Full control
- Timeline: 40-58 weeks

---

## Next Steps

1. **Decide on path**: Hybrid, existing LLM, or full build?
2. **Run benchmark**: Establish baseline
3. **Expand corpus**: More data helps all paths
4. **Choose first phase**: Start with quick wins

**Which path interests you most?**
