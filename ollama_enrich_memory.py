#!/usr/bin/env python3
"""
Enrich Fact Topic JSON files with a local Ollama model.

Reads memory/topics/*.json (downloaded from the server), improves:
  - title, subtitle, description
  - aliases, tags, possible_prompts, related_topics
  - optional per-fact tags / type cleanup

Does NOT require the live site. After upload, run on the server:
  php memory_reviewer.php --rebuild

Embeddings: by default this script does NOT replace vectors (your PHP
LocalEmbedder owns those). Pass --embed-ollama only if you intentionally
want Ollama vectors (dimension will differ from LocalEmbedder).

Usage:
  python3 ollama_enrich_memory.py --memory-dir ./memory
  python3 ollama_enrich_memory.py --memory-dir ./memory --model llama3.2 --limit 5
  python3 ollama_enrich_memory.py --memory-dir ./memory --dry-run
  python3 ollama_enrich_memory.py --memory-dir ./memory --only sky_stream_puck
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any


def ollama_generate(base: str, model: str, prompt: str, timeout: int = 180) -> str:
    url = base.rstrip("/") + "/api/generate"
    body = json.dumps(
        {
            "model": model,
            "prompt": prompt,
            "stream": False,
            "options": {"temperature": 0.2},
        }
    ).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        data = json.loads(resp.read().decode("utf-8"))
    return (data.get("response") or "").strip()


def extract_json_object(text: str) -> dict[str, Any] | None:
    text = text.strip()
    if not text:
        return None
    m = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", text, re.S)
    if m:
        text = m.group(1)
    try:
        obj = json.loads(text)
        if isinstance(obj, dict):
            return obj
    except json.JSONDecodeError:
        pass
    m = re.search(r"\{.*\}", text, re.S)
    if m:
        try:
            obj = json.loads(m.group(0))
            if isinstance(obj, dict):
                return obj
        except json.JSONDecodeError:
            return None
    return None


def str_list(value: Any, limit: int = 24) -> list[str]:
    if not isinstance(value, list):
        return []
    out: list[str] = []
    for item in value:
        s = str(item).strip()
        if s and s not in out:
            out.append(s)
        if len(out) >= limit:
            break
    return out


def sample_facts(doc: dict[str, Any], n: int = 10) -> list[str]:
    facts = doc.get("facts") or []
    samples: list[str] = []
    if not isinstance(facts, list):
        return samples
    for fact in facts:
        if not isinstance(fact, dict):
            continue
        content = str(fact.get("content") or fact.get("value") or "").strip()
        if not content:
            continue
        samples.append(content[:280])
        if len(samples) >= n:
            break
    return samples


def build_prompt(topic: str, doc: dict[str, Any]) -> str:
    samples = sample_facts(doc)
    sample_block = "\n".join(f"- {s}" for s in samples) if samples else "- (no facts)"
    existing = {
        "title": doc.get("title") or "",
        "subtitle": doc.get("subtitle") or "",
        "description": (doc.get("description") or "")[:400],
        "aliases": str_list(doc.get("aliases"), 8),
        "tags": str_list(doc.get("tags"), 12),
        "possible_prompts": str_list(doc.get("possible_prompts"), 8),
    }
    return f"""You maintain a knowledge-base Fact Topic used by a local AI system.
Multiple facts are stored under ONE topic. Improve the TOPIC metadata only.

Topic key: {topic}
Existing metadata: {json.dumps(existing, ensure_ascii=False)}

Sample facts under this topic:
{sample_block}

Return ONLY valid JSON (no markdown, no commentary) with exactly these keys:
{{
  "title": "short human title",
  "subtitle": "one short clarifying phrase",
  "description": "1-3 sentence plain description of the topic",
  "aliases": ["alternate names users might say"],
  "tags": ["short", "keywords", "max 12"],
  "possible_prompts": ["natural questions this topic answers", "max 8"],
  "related_topics": ["optional_related_topic_keys"]
}}

Rules:
- Prefer precise tags over long phrases.
- possible_prompts must be real user questions, not definitions.
- Do not invent unrelated products; stay faithful to the sample facts.
- Keep language English.
"""


def merge_meta(doc: dict[str, Any], meta: dict[str, Any]) -> dict[str, Any]:
    out = dict(doc)
    for key in ("title", "subtitle", "description"):
        val = str(meta.get(key) or "").strip()
        if val:
            out[key] = val
    for key, limit in (
        ("aliases", 20),
        ("tags", 16),
        ("possible_prompts", 10),
        ("related_topics", 12),
    ):
        incoming = str_list(meta.get(key), limit)
        if not incoming:
            continue
        current = str_list(out.get(key), limit)
        merged: list[str] = []
        for item in incoming + current:
            if item not in merged:
                merged.append(item)
        out[key] = merged[:limit]
    return out


def load_topic(path: Path) -> dict[str, Any]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise ValueError(f"Topic file is not an object: {path}")
    return data


def save_topic(path: Path, doc: dict[str, Any]) -> None:
    path.write_text(
        json.dumps(doc, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def check_ollama(base: str) -> None:
    url = base.rstrip("/") + "/api/tags"
    try:
        with urllib.request.urlopen(url, timeout=5) as resp:
            resp.read()
    except Exception as exc:  # noqa: BLE001
        raise SystemExit(
            f"Cannot reach Ollama at {base} ({exc}).\n"
            "Start it with: ollama serve\n"
            "And pull a model: ollama pull llama3.2"
        ) from exc


def main() -> int:
    parser = argparse.ArgumentParser(description="Enrich memory topic JSON via local Ollama")
    parser.add_argument(
        "--memory-dir",
        default="./memory",
        help="Path to memory folder containing topics/ (default: ./memory)",
    )
    parser.add_argument("--model", default="llama3.2", help="Ollama model name")
    parser.add_argument("--base-url", default="http://127.0.0.1:11434", help="Ollama base URL")
    parser.add_argument("--limit", type=int, default=0, help="Max topics to process (0=all)")
    parser.add_argument("--only", default="", help="Only this topic filename stem")
    parser.add_argument("--sleep", type=float, default=0.5, help="Seconds between calls")
    parser.add_argument("--dry-run", action="store_true", help="Print planned work, do not write")
    parser.add_argument(
        "--force",
        action="store_true",
        help="Enrich even if title/tags/prompts already look complete",
    )
    args = parser.parse_args()

    memory_dir = Path(args.memory_dir).expanduser().resolve()
    topics_dir = memory_dir / "topics"
    if not topics_dir.is_dir():
        raise SystemExit(f"Topics directory not found: {topics_dir}")

    check_ollama(args.base_url)

    files = sorted(topics_dir.glob("*.json"))
    if args.only:
        files = [p for p in files if p.stem == args.only]
        if not files:
            raise SystemExit(f"Topic not found: {args.only}")

    processed = 0
    skipped = 0
    failed = 0

    print(f"Memory dir : {memory_dir}")
    print(f"Topics     : {len(files)}")
    print(f"Model      : {args.model}")
    print(f"Ollama     : {args.base_url}")
    print()

    for path in files:
        if args.limit and processed >= args.limit:
            break

        topic = path.stem
        try:
            doc = load_topic(path)
        except Exception as exc:  # noqa: BLE001
            print(f"[skip] {topic}: cannot read ({exc})")
            failed += 1
            continue

        facts = doc.get("facts") if isinstance(doc.get("facts"), list) else []
        tags = str_list(doc.get("tags"))
        prompts = str_list(doc.get("possible_prompts"))
        title = str(doc.get("title") or "").strip()

        needs = (
            args.force
            or not title
            or len(tags) < 3
            or len(prompts) < 2
            or not str(doc.get("description") or "").strip()
            or not str(doc.get("subtitle") or "").strip()
        )
        if not needs:
            print(f"[ok]   {topic} ({len(facts)} facts) — meta looks complete, skip")
            skipped += 1
            continue

        print(f"[work] {topic} ({len(facts)} facts) …")
        prompt = build_prompt(topic, doc)

        if args.dry_run:
            print(f"       dry-run: would call {args.model}")
            processed += 1
            continue

        try:
            raw = ollama_generate(args.base_url, args.model, prompt)
            meta = extract_json_object(raw)
            if not meta:
                print(f"[fail] {topic}: model did not return JSON")
                failed += 1
                continue
            merged = merge_meta(doc, meta)
            # backup once
            bak = path.with_suffix(".json.bak")
            if not bak.exists():
                bak.write_text(path.read_text(encoding="utf-8"), encoding="utf-8")
            save_topic(path, merged)
            print(
                f"[done] {topic}: title={merged.get('title')!r} "
                f"tags={len(str_list(merged.get('tags')))} "
                f"prompts={len(str_list(merged.get('possible_prompts')))}"
            )
            processed += 1
        except urllib.error.URLError as exc:
            print(f"[fail] {topic}: Ollama request failed ({exc})")
            failed += 1
        except Exception as exc:  # noqa: BLE001
            print(f"[fail] {topic}: {exc}")
            failed += 1

        if args.sleep > 0:
            time.sleep(args.sleep)

    print()
    print(f"Processed={processed} skipped={skipped} failed={failed}")
    print("Next: upload memory/topics/*.json to the server, then:")
    print("  php memory_reviewer.php --rebuild")
    print("Optional: re-embed with PHP LocalEmbedder after upload (keeps dims consistent).")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
