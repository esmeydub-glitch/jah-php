#!/usr/bin/env python3
"""
Demo script: Jah-Qwen Bridge integration example.

Shows how to:
1. Connect to Jah-PHP memory
2. Store memories with tags
3. Search memories
4. Run the full agent loop with Qwen
"""

import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from jah_bridge import JahMemoryBridge, JahQwenAgent, KeywordExtractor


def demo_bridge():
    """Demonstrate bridge functionality."""
    api_url = os.environ.get("JAH_API_URL", "http://localhost/jah-php/bridge.php")
    bridge = JahMemoryBridge(api_url)

    print("=" * 60)
    print("JAH-QWEN BRIDGE DEMO")
    print("=" * 60)

    # 1. Store memories
    print("\n[1] Storing memories...")

    bridge.save_memory(
        "hot",
        "user_pref_language",
        {"preference": "PHP", "type": "programming_language", "importance": "high"},
        tags=["user", "preference", "php"],
    )
    print("  ✓ Stored: user_pref_language (hot)")

    bridge.save_memory(
        "hot",
        "project_jah_description",
        {
            "name": "JAH",
            "description": "Event-driven PHP AI agent framework with tiered memory",
            "status": "active",
        },
        tags=["project", "jah", "php", "ai"],
    )
    print("  ✓ Stored: project_jah_description (hot)")

    bridge.save_memory(
        "warm",
        "php_async_patterns",
        {
            "patterns": ["Swoole", "ReactPHP", "Fibers", "AMP"],
            "use_case": "async programming",
        },
        tags=["php", "async", "patterns"],
    )
    print("  ✓ Stored: php_async_patterns (warm)")

    # 2. Search memories
    print("\n[2] Searching memories...")

    results = bridge.search_memory("PHP async programming", tiers=["hot", "warm"])
    print(f"  Found {len(results)} results for 'PHP async programming':")
    for r in results:
        print(f"    [{r.tier}] {r.key} (score: {r.score})")

    # 3. Search by tags
    print("\n[3] Searching by tags...")

    tag_results = bridge.search_by_tags(["php", "ai"])
    print(f"  Found {len(tag_results)} results for tags ['php', 'ai']:")
    for r in tag_results:
        print(f"    [{r.tier}] {r.key}")

    # 4. Get stats
    print("\n[4] Tier statistics:")

    stats = bridge.get_stats()
    for tier, info in stats.items():
        print(f"  {tier.upper()}: {info['count']} files, {round(info['total_size_bytes']/1024, 1)} KB")

    # 5. Keyword extraction
    print("\n[5] Keyword extraction:")

    extractor = KeywordExtractor()
    test_queries = [
        "¿Cómo implementar colas async en PHP con Swoole?",
        "What is the best approach for memory management in AI agents?",
        "Configurar sistema de memoria escalonada en producción",
    ]
    for q in test_queries:
        keywords = extractor.extract(q)
        print(f"  '{q[:50]}...' → {keywords}")

    print("\n" + "=" * 60)
    print("Demo complete!")
    print("=" * 60)


def demo_agent():
    """Demonstrate full agent loop (mock LLM)."""
    api_url = os.environ.get("JAH_API_URL", "http://localhost/jah-php/bridge.php")
    bridge = JahMemoryBridge(api_url)

    def mock_qwen(prompt: str) -> str:
        """Mock Qwen response for demo."""
        return f"[Qwen would respond to: {prompt[:80]}...]"

    agent = JahQwenAgent(bridge, llm_callable=mock_qwen)

    print("\n" + "=" * 60)
    print("AGENT LOOP DEMO")
    print("=" * 60)

    queries = [
        "¿Qué sabes sobre PHP async?",
        "Cuéntame sobre el proyecto JAH",
        "¿Cómo funciona la memoria escalonada?",
    ]

    for q in queries:
        result = agent.process(q)
        print(f"\n  User: {q}")
        print(f"  Tags: {result['tags']}")
        print(f"  Context: {result['context_found']} memories found")
        print(f"  Response: {result['response']}")


if __name__ == "__main__":
    demo_bridge()
    demo_agent()
