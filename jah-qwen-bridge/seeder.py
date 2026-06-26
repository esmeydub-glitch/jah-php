#!/usr/bin/env python3
"""
Jah Memory Seeder — Inject bulk memories into Jah-PHP for benchmarking.
Useful for hackathon demo showing 1000+ instant retrievals.
"""

import json
import os
import sys
import time
import random
import string
import requests

API_URL = os.environ.get("JAH_API_URL", "http://localhost/jah-php/bridge.php")


def random_string(length: int = 8) -> str:
    return "".join(random.choices(string.ascii_lowercase + string.digits, k=length))


def generate_memories(count: int = 1000, batch_size: int = 50) -> dict:
    """Generate and inject test memories."""
    tiers = ["hot", "warm", "cold"]
    topics = [
        ("php", ["async", "fibers", "swoole", "react", "worker", "process"]),
        ("ai", ["agent", "memory", "context", "retrieval", "embedding", "llm"]),
        ("infra", ["cache", "redis", "queue", "database", "api", "microservice"]),
        ("dev", ["testing", "deploy", "ci", "docker", "kubernetes", "monitor"]),
    ]

    stats = {"success": 0, "failed": 0, "time_seconds": 0}
    start = time.time()

    print(f"Injecting {count} memories into Jah-PHP...")

    for i in range(count):
        tier = random.choice(tops)
        topic_category, keywords = random.choice(topics)
        keyword = random.choice(keywords)
        key = f"{topic_category}_{keyword}_{random_string(6)}"

        data = {
            "id": i,
            "category": topic_category,
            "topic": keyword,
            "content": f"Memory #{i}: {keyword} in {topic_category} — generated at {time.time()}",
            "metadata": {
                "batch": i // batch_size,
                "index": i,
            },
        }

        tags = [topic_category, keyword, tier]

        try:
            response = requests.post(
                API_URL,
                json={
                    "action": "save",
                    "tier": tier,
                    "key": key,
                    "data": data,
                    "tags": tags,
                },
                timeout=5,
            )
            if response.status_code == 200:
                stats["success"] += 1
            else:
                stats["failed"] += 1
        except Exception:
            stats["failed"] += 1

        if (i + 1) % 100 == 0:
            elapsed = time.time() - start
            rate = (i + 1) / elapsed if elapsed > 0 else 0
            print(f"  Progress: {i+1}/{count} ({rate:.0f} ops/sec, {elapsed:.1f}s)")

    stats["time_seconds"] = round(time.time() - start, 2)
    return stats


def benchmark_search(iterations: int = 100) -> dict:
    """Benchmark memory search performance."""
    import random

    terms = ["php", "ai", "cache", "agent", "memory", "async", "fiber", "queue"]
    search_times = []

    print(f"Running {iterations} search queries...")

    for i in range(iterations):
        query = random.choice(terms)
        start = time.time()

        try:
            requests.post(
                f"{API_URL}",
                json={"action": "search", "query": query, "limit": 10},
                timeout=5,
            )
            search_times.append(time.time() - start)
        except Exception:
            pass

    if search_times:
        return {
            "queries": len(search_times),
            "avg_ms": round(sum(search_times) / len(search_times) * 1000, 2),
            "p50_ms": round(sorted(search_times)[len(search_times) // 2] * 1000, 2),
            "p95_ms": round(sorted(search_times)[int(len(search_times) * 0.95)] * 1000, 2),
            "max_ms": round(max(search_times) * 1000, 2),
        }
    return {}


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "stats"

    if action == "inject":
        count = int(sys.argv[2]) if len(sys.argv) > 2 else 1000
        stats = generate_memories(count)
        print(f"\nInjection complete:")
        print(json.dumps(stats, indent=2))

    elif action == "benchmark":
        results = benchmark_search()
        print(f"\nSearch benchmark:")
        print(json.dumps(results, indent=2))

    elif action == "stats":
        response = requests.get(API_URL, params={"action": "stats"}, timeout=10)
        print(json.dumps(response.json(), indent=2))

    else:
        print("Usage: python seeder.py [inject|benchmark|stats] [count]")


if __name__ == "__main__":
    main()
