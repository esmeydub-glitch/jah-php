# JAH Agent Responsibilities

## MemoryActionScript

The active product agent classifies input, retrieves relevant memory, bounds context for Qwen, stores reusable knowledge or durable user facts, forgets records and migrates tiers. Generated summaries are stored only after a successful Qwen response and retain their source query.

## DataCore agents

| Agent | Real responsibility |
|---|---|
| `StorageAgent` | Binary append/read operations |
| `DataCoreTurbo` | Canonical segments, direct pointers, inverted postings, tombstones and index rebuilding |
| `MemoryPyramid` | Hot conversation, seven-day Warm history and permanent Cold records |
| `ReplicationAgent` | Signed, append-only local replicas; no outbound HTTP |
| `WorkerPool` | Bounded PCNTL task execution with a verified sequential fallback |
| `CacheAgent` | In-request Hot cache used by MemoryPyramid |
| `Compressor` | Cold-memory compression and decompression |

Unused collector, enricher, exporter, cleaner, scheduler, alternate storage and relational-database modules were removed from the production surface. QwenConnector remains the only external HTTP boundary.

## ActionScript utilities

- `JasNativeCompiler` validates syntax with PHP's parser and atomically writes PHP artifacts.
- `JasBinaryCompiler` emits checksummed JAS bytecode and interprets its supported operations.
- `JasAsyncActions` uses bounded PHP child processes when PCNTL exists and reports its sequential fallback otherwise.

See [`ACTIONSCRIPT_ARCHITECTURE.md`](ACTIONSCRIPT_ARCHITECTURE.md) for the complete product pipeline.
