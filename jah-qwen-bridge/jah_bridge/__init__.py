"""
Jah-Qwen Bridge — Python wrapper for connecting Qwen (or any LLM) to Jah-PHP DataCore.

Jah-PHP uses DataCoreTurbo internally: binary format [4-byte length][JSON payload][newline].
This bridge exposes clean JSON to Qwen while the PHP side handles binary translation.

Usage:
    from jah_bridge import JahMemoryBridge

    bridge = JahMemoryBridge("http://localhost/jah-php/api.php")
    bridge.save_memory("hot", "user_123", {"query": "PHP async"})
    results = bridge.search_memory("PHP async", collection="memories")
"""

import requests
import json
import time
import hashlib
from typing import Any, Optional
from dataclasses import dataclass


@dataclass
class MemoryRecord:
    id: str
    tier: str
    data: dict
    score: float = 0.0


class JahMemoryBridge:
    """Python bridge to Jah-PHP DataCore storage engine."""

    def __init__(self, php_api_url: str = "http://localhost/jah-php/api.php"):
        self.api_url = php_api_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({"Content-Type": "application/json"})

    def save_memory(
        self,
        tier: str,
        key: str,
        content: Any,
        collection: str = "memories",
        tags: list[str] | None = None,
    ) -> dict:
        """Store memory in DataCore (binary format internally)."""
        data = content if isinstance(content, dict) else {"content": content}
        data["id"] = key
        data["_tier"] = tier
        data["tags"] = tags or []

        response = self.session.post(
            self.api_url,
            json={
                "action": "save",
                "collection": collection,
                "tier": tier,
                "data": data,
            },
            timeout=10,
        )
        response.raise_for_status()
        return response.json()

    def retrieve_memory(self, key: str, collection: str = "memories") -> dict | None:
        """Retrieve memory by ID from DataCore binary format."""
        response = self.session.post(
            self.api_url,
            json={
                "action": "retrieve",
                "collection": collection,
                "id": key,
            },
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()
        return result.get("data") if result.get("status") == "success" else None

    def search_memory(
        self,
        query: str,
        collection: str = "memories",
        limit: int = 20,
    ) -> list[MemoryRecord]:
        """Full-text search across DataCore binary storage."""
        response = self.session.post(
            self.api_url,
            json={
                "action": "search",
                "collection": collection,
                "query": query,
                "limit": limit,
            },
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()

        records = []
        if result.get("status") == "success":
            for item in result.get("data", []):
                records.append(
                    MemoryRecord(
                        id=item.get("id", ""),
                        tier=item.get("_tier", ""),
                        data=item,
                        score=1.0,
                    )
                )
        return records

    def batch_save(
        self,
        docs: list[dict],
        collection: str = "memories",
    ) -> int:
        """Batch insert for high-volume operations (uses DataCore batchInsert)."""
        response = self.session.post(
            self.api_url,
            json={
                "action": "batch",
                "collection": collection,
                "docs": docs,
            },
            timeout=30,
        )
        response.raise_for_status()
        return response.json().get("inserted", 0)

    def delete_memory(self, key: str, collection: str = "memories") -> bool:
        """Soft-delete a memory record."""
        response = self.session.post(
            self.api_url,
            json={
                "action": "delete",
                "collection": collection,
                "id": key,
            },
            timeout=10,
        )
        response.raise_for_status()
        return response.json().get("status") == "success"

    def list_memories(
        self,
        collection: str = "memories",
        limit: int = 50,
    ) -> dict:
        """List memories with pagination."""
        response = self.session.get(
            self.api_url,
            params={"action": "list", "collection": collection, "limit": limit},
            timeout=10,
        )
        response.raise_for_status()
        return response.json()

    def get_stats(self) -> dict:
        """Get DataCore storage statistics."""
        response = self.session.get(
            self.api_url, params={"action": "stats"}, timeout=10
        )
        response.raise_for_status()
        return response.json().get("data", {})

    def pyramid_get(self, key: str) -> dict | None:
        """Get from MemoryPyramid (hot LRU cache)."""
        response = self.session.post(
            self.api_url,
            json={"action": "pyramid_get", "key": key},
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()
        return result.get("data") if result.get("status") == "success" else None

    def pyramid_set(self, key: str, value: Any, ttl: int = 0) -> dict:
        """Set in MemoryPyramid (hot LRU cache)."""
        response = self.session.post(
            self.api_url,
            json={"action": "pyramid_set", "key": key, "value": value, "ttl": ttl},
            timeout=10,
        )
        response.raise_for_status()
        return response.json()


class KeywordExtractor:
    """Lightweight keyword extraction for memory tagging."""

    STOP_WORDS = {
        "el", "la", "los", "las", "un", "una", "unos", "unas",
        "de", "del", "al", "y", "o", "en", "es", "son", "está",
        "the", "is", "are", "was", "were", "a", "an", "and", "or",
        "in", "on", "at", "to", "for", "of", "with", "by",
        "que", "como", "para", "pero", "por", "con",
        "how", "what", "why", "when", "where", "which",
        "yo", "tú", "él", "ella", "nosotros", "ellos",
    }

    @staticmethod
    def extract(text: str, max_keywords: int = 5) -> list[str]:
        import re

        words = re.findall(r"[a-záéíóúñü0-9]+", text.lower())
        words = [w for w in words if w not in KeywordExtractor.STOP_WORDS and len(w) > 2]

        freq: dict[str, int] = {}
        for w in words:
            freq[w] = freq.get(w, 0) + 1

        sorted_words = sorted(freq.items(), key=lambda x: (-x[1], x[0]))
        return [w for w, _ in sorted_words[:max_keywords]]


class JahQwenAgent:
    """Full agent loop: Qwen LLM + Jah-PHP DataCore memory."""

    def __init__(
        self,
        bridge: JahMemoryBridge,
        llm_callable=None,
        collection: str = "memories",
    ):
        self.bridge = bridge
        self.llm_callable = llm_callable
        self.collection = collection
        self.session_id = hashlib.md5(str(time.time()).encode()).hexdigest()[:12]
        self.extractor = KeywordExtractor()

    def process(self, user_input: str) -> dict:
        """Main agent loop: search → build context → LLM → save."""

        tags = self.extractor.extract(user_input)

        hot_results = self.bridge.search_memory(
            user_input, collection=self.collection, limit=5
        )

        context_str = ""
        if hot_results:
            context_items = []
            for r in hot_results:
                preview = json.dumps(r.data, ensure_ascii=False)[:200]
                context_items.append(f"[{r.tier}] {r.id}: {preview}")
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
            "context_found": len(hot_results),
            "response": response,
            "tags": tags,
        }

    def _build_prompt(self, user_input: str, context_str: str) -> str:
        if context_str:
            return f"""Based on memory context:
{context_str}

User says: {user_input}

Respond naturally, using memory context:"""
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
            collection=self.collection,
            tags=tags,
        )

    def save_important(self, content: dict, key: str | None = None, tags: list[str] | None = None):
        if key is None:
            key = f"important_{int(time.time())}"
        if tags is None:
            tags = self.extractor.extract(json.dumps(content))

        self.bridge.save_memory("hot", key, content, tags=tags)
        return key

    def recall(self, query: str, limit: int = 10) -> list[MemoryRecord]:
        return self.bridge.search_memory(query, collection=self.collection, limit=limit)


if __name__ == "__main__":
    bridge = JahMemoryBridge("http://localhost/jah-php/api.php")
    agent = JahQwenAgent(bridge)

    print("Jah-Qwen Bridge Demo")
    print("-" * 40)

    stats = bridge.get_stats()
    print(f"Stats: {json.dumps(stats, indent=2)}")

    test_input = "¿Cómo implementar colas async en PHP?"
    result = agent.process(test_input)
    print(f"\nInput: {test_input}")
    print(f"Tags: {result['tags']}")
    print(f"Context: {result['context_found']} memories")

    search = bridge.search_memory("PHP async")
    print(f"\nSearch: {len(search)} results")
