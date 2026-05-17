#!/usr/bin/env python3
"""Topic-based recommender (stdlib-only).

Reads attempt history (topic + correctness) from stdin JSON and returns the
weakest topics by accuracy.

You can later replace this with a more advanced ML model.
"""

import json
import sys
from collections import defaultdict


def safe_int(x, default=0):
    try:
        return int(x)
    except Exception:
        return default


def main() -> int:
    try:
        raw = sys.stdin.read()
        payload = json.loads(raw) if raw.strip() else {}
    except Exception:
        print(json.dumps({"ok": False, "error": "invalid_json"}))
        return 1

    attempts = payload.get("attempts") or []
    min_attempts = safe_int(payload.get("min_attempts_per_topic"), 3)
    max_topics = safe_int(payload.get("max_topics"), 3)

    total = defaultdict(int)
    correct = defaultdict(int)

    for a in attempts:
        topic = (a.get("topic") or "").strip()
        if not topic:
            continue
        total[topic] += 1
        if safe_int(a.get("is_correct"), 0) == 1:
            correct[topic] += 1

    stats = []
    for topic, t in total.items():
        if t < min_attempts:
            continue
        acc = (correct[topic] / t) if t else 0.0
        stats.append({"topic": topic, "acc": round(acc, 4), "total": t})

    stats.sort(key=lambda d: (d["acc"], -d["total"], d["topic"].lower()))
    weak = stats[:max_topics]

    print(json.dumps({"ok": True, "weak_topics": weak}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
