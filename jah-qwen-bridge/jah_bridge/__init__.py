"""
Jah-Qwen Bridge — Python wrapper for connecting Qwen (or any LLM) to Jah-PHP's tiered memory.

Usage:
    from jah_bridge import JahMemoryBridge

    bridge = JahMemoryBridge("http://localhost/jah-php/bridge.php")
    bridge.save_memory("hot", "user_123_last_query", {"query": "PHP async patterns"})
    results = bridge.search_memory("PHP patterns", tiers=["hot", "warm"])
"""

import requests
import json
import time
import hashlib
from typing import Any, Optional
from dataclasses import dataclass


@dataclass
class MemoryRecord:
    key: str
    tier: str
    score: float
    data: dict
    metadata: dict


class JahMemoryBridge:
    def __init__(self, php_api_url: str = "http://localhost/jah-php/bridge.php"):
        self.api_url = php_api_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({"Content-Type": "application/json"})

    def save_memory(self, tier: str, key: str, content: Any, tags: list[str] | None = None) -> dict:
        """Store a memory record in the specified tier."""
        payload = {
            "action": "save",
            "tier": tier,
            "key": key,
            "data": content if isinstance(content, dict) else {"content": content},
        }
        if tags:
            if isinstance(content, dict):
                content["tags"] = tags
                payload["data"] = content
            else:
                payload["data"] = {"content": content, "tags": tags}

        response = self.session.post(self.api_url, json=payload, timeout=10)
        response.raise_for_status()
        return response.json()

    def retrieve_memory(self, tier: str, key: str) -> dict | None:
        """Retrieve a specific memory by tier and key."""
        response = self.session.get(
            self.api_url,
            params={"action": "retrieve", "tier": tier, "key": key},
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()
        return result.get("data") if result.get("status") == "success" else None

    def search_memory(
        self,
        query: str,
        tiers: list[str] | None = None,
        limit: int = 20,
    ) -> list[MemoryRecord]:
        """Full-text search across memory tiers."""
        if tiers is None:
            tiers = ["hot", "warm", "cold"]

        payload = {
            "action": "search",
            "query": query,
            "tiers": tiers,
            "limit": limit,
        }

        response = self.session.post(self.api_url, json=payload, timeout=10)
        response.raise_for_status()
        result = response.json()

        records = []
        if result.get("status") == "success":
            for item in result.get("data", []):
                records.append(
                    MemoryRecord(
                        key=item["key"],
                        tier=item["tier"],
                        score=item["score"],
                        data=item["data"],
                        metadata=item.get("metadata", {}),
                    )
                )
        return records

    def search_by_tags(
        self,
        tags: list[str],
        tiers: list[str] | None = None,
        limit: int = 20,
    ) -> list[MemoryRecord]:
        """Search memories by tags."""
        if tiers is None:
            tiers = ["hot", "warm", "cold"]

        payload = {
            "action": "tags",
            "tags": tags,
            "tiers": tiers,
            "limit": limit,
        }

        response = self.session.post(self.api_url, json=payload, timeout=10)
        response.raise_for_status()
        result = response.json()

        records = []
        if result.get("status") == "success":
            for item in result.get("data", []):
                records.append(
                    MemoryRecord(
                        key=item["key"],
                        tier=item["tier"],
                        score=item["score"],
                        data=item["data"],
                        metadata=item.get("metadata", {}),
                    )
                )
        return records

    def delete_memory(self, key: str) -> bool:
        """Delete a memory record by key."""
        payload = {"action": "delete", "key": key}
        response = self.session.post(self.api_url, json=payload, timeout=10)
        response.raise_for_status()
        result = response.json()
        return result.get("status") == "success"

    def move_memory(self, key: str, to_tier: str) -> bool:
        """Move a memory to a different tier."""
        payload = {"action": "move", "key": key, "to_tier": to_tier}
        response = self.session.post(self.api_url, json=payload, timeout=10)
        response.raise_for_status()
        result = response.json()
        return result.get("status") == "success"

    def list_memories(self, tier: str = "", limit: int = 50, offset: int = 0) -> dict:
        """List memories with pagination."""
        params = {"action": "list", "limit": limit, "offset": offset}
        if tier:
            params["tier"] = tier

        response = self.session.get(self.api_url, params=params, timeout=10)
        response.raise_for_status()
        return response.json()

    def get_stats(self) -> dict:
        """Get tier storage statistics."""
        response = self.session.get(
            self.api_url, params={"action": "stats"}, timeout=10
        )
        response.raise_for_status()
        return response.json().get("data", {})

    def trigger_migration(self) -> list[dict]:
        """Trigger tier migration (hot→warm→cold based on TTL)."""
        response = self.session.post(
            self.api_url, json={"action": "migrate"}, timeout=30
        )
        response.raise_for_status()
        return response.json().get("migrated", [])


class KeywordExtractor:
    """Lightweight keyword extraction for memory tagging (no LLM needed)."""

    STOP_WORDS = {
        "el", "la", "los", "las", "un", "una", "unos", "unas",
        "de", "del", "al", "y", "o", "en", "es", "son", "está",
        "the", "is", "are", "was", "were", "a", "an", "and", "or",
        "in", "on", "at", "to", "for", "of", "with", "by",
        "que", "como", "para", "pero", "por", "con", "que",
        "how", "what", "why", "when", "where", "which",
        "yo", "tú", "él", "ella", "nosotros", "ellos",
        "me", "te", "se", "nos", "le", "lo", "la",
        "mi", "tu", "su", "mis", "tus", "sus",
        "este", "esta", "estos", "estas", "ese", "esa",
    }

    @staticmethod
    def extract(text: str, max_keywords: int = 5) -> list[str]:
        """Extract meaningful keywords from text."""
        import re

        words = re.findall(r"[a-záéíóúñü0-9]+", text.lower())
        words = [w for w in words if w not in KeywordExtractor.STOP_WORDS and len(w) > 2]

        freq: dict[str, int] = {}
        for w in words:
            freq[w] = freq.get(w, 0) + 1

        sorted_words = sorted(freq.items(), key=lambda x: (-x[1], x[0]))
        return [w for w, _ in sorted_words[:max_keywords]]


class JahQwenAgent:
    """Full agent loop integrating Qwen LLM with Jah-PHP memory."""

    def __init__(
        self,
        bridge: JahMemoryBridge,
        llm_callable=None,
    ):
        self.bridge = bridge
        self.llm_callable = llm_callable
        self.session_id = hashlib.md5(str(time.time()).encode()).hexdigest()[:12]
        self.extractor = KeywordExtractor()

    def process(self, user_input: str) -> dict:
        """Main agent loop: search memory → build context → call LLM → save memory."""

        tags = self.extractor.extract(user_input)

        hot_context = self.bridge.search_memory(user_input, tiers=["hot"], limit=5)
        warm_context = self.bridge.search_memory(user_input, tiers=["warm"], limit=5)

        all_context = hot_context + warm_context

        context_str = ""
        if all_context:
            context_items = []
            for r in all_context:
                content_preview = json.dumps(r.data, ensure_ascii=False)[:200]
                context_items.append(f"[{r.tier}] {r.key}: {content_preview}")
            context_str = "\n".join(context_items)

        full_prompt = self._build_prompt(user_input, context_str)

        if self.llm_callable:
            response = self.llm_callable(full_prompt)
        else:
            response = "[No LLM configured]"

        self._save_interaction(user_input, response, tags)

        return {
            "session_id": self.session_id,
            "user_input": user_input,
            "context_found": len(all_context),
            "response": response,
            "tags": tags,
        }

    def _build_prompt(self, user_input: str, context_str: str) -> str:
        if context_str:
            return f"""Based on the user's memory context:
{context_str}

User says: {user_input}

Respond naturally, using the memory context to provide personalized answers."""
        else:
            return f"User says: {user_input}\n\nRespond naturally:"

    def _save_interaction(self, user_input: str, response: str, tags: list[str]):
        timestamp = int(time.time())
        key = f"session_{self.session_id}_{timestamp}"

        self.bridge.save_memory(
            "hot",
            key,
            {
                "query": user_input,
                "response": response,
                "timestamp": timestamp,
            },
            tags=tags,
        )

    def save_important(self, content: dict, key: str | None = None, tags: list[str] | None = None):
        """Explicitly store an important memory in hot tier."""
        if key is None:
            key = f"important_{int(time.time())}"
        if tags is None:
            tags = self.extractor.extract(json.dumps(content))

        self.bridge.save_memory("hot", key, content, tags=tags)
        return key

    def recall(self, query: str, limit: int = 10) -> list[MemoryRecord]:
        """Recall relevant memories."""
        return self.bridge.search_memory(query, limit=limit)


if __name__ == "__main__":
    bridge = JahMemoryBridge("http://localhost/jah-php/bridge.php")

    agent = JahQwenAgent(bridge)

    print("Jah-Qwen Bridge Demo")
    print("-" * 40)

    stats = bridge.get_stats()
    print(f"Memory stats: {json.dumps(stats, indent=2)}")

    test_input = "¿Cómo implementar colas async en PHP?"
    result = agent.process(test_input)
    print(f"\nInput: {test_input}")
    print(f"Tags: {result['tags']}")
    print(f"Context found: {result['context_found']}")

    search = bridge.search_memory("PHP async")
    print(f"\nSearch 'PHP async': {len(search)} results")
