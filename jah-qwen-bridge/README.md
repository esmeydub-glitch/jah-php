# Jah-Qwen Bridge

Architecture: Qwen (LLM) ↔ JahMemoryBridge (Python) ↔ bridge.php (REST API) ↔ TieredMemory (PHP filesystem)

## Quick Start

### 1. Start Jah-PHP

```bash
cd jah-php
php -S localhost:8000
```

### 2. Run the Python Bridge

```bash
cd jah-qwen-bridge
pip install -e .
python demo.py
```

### 3. Bulk Inject Memories (Demo)

```bash
cd jah-qwen-bridge
python seeder.py inject 1000   # Inject 1000 test memories
python seeder.py benchmark     # Run search benchmark
```

### 4. Automatic Tier Migration

```bash
# Every 5 minutes via cron
*/5 * * * * php /path/to/jah-php/cron_tier_migration.php

# Or manually
php migrate_tiers.php migrate
```

## API Endpoints (bridge.php)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/bridge.php?action=status` | Service status |
| `GET` | `/bridge.php?action=list&tier=hot` | List memories |
| `GET` | `/bridge.php?action=stats` | Tier statistics |
| `POST` | `/bridge.php` | Store/search/delete |

### POST Actions

```json
// Store
{"action": "save", "tier": "hot", "key": "my_key", "data": {...}, "tags": ["tag1"]}

// Search
{"action": "search", "query": "PHP async", "tiers": ["hot", "warm"]}

// Retrieve by key
{"action": "retrieve", "tier": "hot", "key": "my_key"}

// Delete
{"action": "delete", "key": "my_key"}

// Move tier
{"action": "move", "key": "my_key", "to_tier": "warm"}

// Trigger migration
{"action": "migrate"}
```

## Memory Lifecycle

```
hot/  (0-1h)   → warm/ (1-24h) → cold/ (24h+) → deleted
  ↑                                          
  new memories                              
```

## Python API

```python
from jah_bridge import JahMemoryBridge, JahQwenAgent

bridge = JahMemoryBridge("http://localhost:8000/bridge.php")
agent = JahQwenAgent(bridge, llm_callable=my_qwen_call)

# Full agent loop
result = agent.process("¿Cómo implementar colas async en PHP?")
print(result["response"])
```
