# JAH Agent Responsibilities

## MemoryActionScript

The active product agent classifies input, retrieves relevant memory, bounds context for Qwen, stores reusable knowledge or durable user facts, forgets records and migrates tiers. Generated summaries are stored only after a successful Qwen response and retain their source query.

## DataCore agents

| Agent | Real responsibility |
|---|---|
| `StorageAgent` | Binary append/read operations |
| `IndexAgent` | Persistent record indexes |
| `LockAgent` | Filesystem concurrency control |
| `IntegrityAgent` | Stored-data integrity checks |
| `CompactionAgent` | Rebuild and compaction work |
| `SnapshotAgent` | Local snapshots |
| `ReplicationAgent` | Signed, append-only local replicas; no outbound HTTP |
| `WorkerPool` / `SwooleWorkerPool` | Bounded PHP task execution |

External HTTP collectors and enrichers are deliberately unavailable in Qwen-only mode. Calling them raises an explicit exception; they never return simulated success.

## ActionScript utilities

- `JasNativeCompiler` validates syntax with PHP's parser and atomically writes PHP artifacts.
- `JasBinaryCompiler` emits checksummed JAS bytecode and interprets its supported operations.
- `JasAsyncActions` uses bounded PHP child processes when PCNTL exists and reports its sequential fallback otherwise.

See [`ACTIONSCRIPT_ARCHITECTURE.md`](ACTIONSCRIPT_ARCHITECTURE.md) for the complete product pipeline.
