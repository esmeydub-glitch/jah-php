# DataCore v3 Architecture

DataCore v3 is JAH MemoryAgent's pure-PHP persistent memory engine. It is not a wrapper around SQL, a vector database, or an external package. Its design is optimized for the MemoryAgent workload: one durable write after selected conversations, followed by memory retrieval on every agent turn.

Dialogue uses two deterministic documents per collection and conversation ID. `conversation_state` holds the active Hot section. When that section exceeds the working character budget, complete oldest exchanges move to `conversation_warm`. Warm turns remain available for seven days and then expire; they never become Cold automatically. Reads merge unexpired Warm history and Hot dialogue chronologically, so retrieval does not depend on the wording of the next question. Explicit “recuerda/guarda” instructions and high-importance classifications are stored directly in permanent Cold, which has no time expiration. Only the Qwen prompt is size-bounded.

## Storage model

Each canonical record is append-only:

```text
[4-byte little-endian payload length]
[JAHPS1 serialized PHP payload]
[newline]
```

Documents are deterministically assigned to one of 1,000 binary segments using `crc32(id)`. Updates and deletions append a new record; existing bytes are never edited in place. A deletion is a durable `_deleted` tombstone.

## Persistent index layers

```text
DataCore collection
├── data/{collection}_{segment}.bin       append-only canonical records
├── index/{collection}.idx                chronological recovery index
├── index/lookup/{collection}/
│   └── {00..ff}.ptrlog                   sharded latest-pointer journal
└── index/terms/{collection}/
    ├── .ready                            versioned rebuild marker
    ├── {shard}/sha256(term-key).post     textual term postings
    └── numeric/{00..ff}.post             shared numeric postings
```

### Direct pointers

The first byte of the document ID's SHA-256 hash selects one of 256 append-only pointer shards. Each JAH pointer retains the original ID, segment, and offset, so collisions or corrupted pointers are rejected before reading data. A request loads only the selected shard and caches its latest pointers in memory.

Lookup becomes:

```text
collection + ID
  → SHA-256 pointer shard
  → segment and byte offset
  → one length-prefixed record
  → verify ID and tombstone
```

The chronological `.idx` remains as a recovery path for old data and incomplete pointer states.

### Inverted postings

Searchable scalar memory fields are normalized, tokenized, stripped of common Spanish and English stop words, and indexed as:

- `e:term` for exact matches;
- `p:prefix` for prefix and simple morphological recall.

Each posting appends a term-key hash, timestamp, and encoded document ID. Numeric keys share 256 shards to avoid one tiny filesystem entry per unique number. The query planner discards absent keys, orders existing posting files from rarest to most common, and bounds the number of keys and candidates. Search then reads recent posting tails, deduplicates IDs, ranks exact hits above prefix hits, resolves every candidate through its current pointer, verifies tombstones, and applies the final semantic substring filter.

Stale postings cannot resurrect an old value: the latest canonical pointer always wins. Reindexing compacts stale postings without rewriting canonical memory.

## Write path

```text
normalize collection and ID
  ↓
serialize PHP payload with JAHPS1
  ↓
lock deterministic binary segment
  ↓
append complete length-prefixed record
  ↓
append chronological recovery index
  ↓
replace direct pointer
  ↓
append exact and prefix postings
  ↓
release lock
```

Offsets are calculated only after the segment lock is acquired. Partial writes are detected, IDs are bounded, delimiters are encoded, and readers use shared locks.

## Search path

```text
query
  ↓ normalize and tokenize
exact and prefix posting files
  ↓ recent tail reads
weighted candidate IDs
  ↓ direct pointer lookup
latest canonical documents
  ↓ tombstone + collection + semantic verification
ranked and bounded memories
```

Search metrics are returned by the `memory.search_context` ActionScript action:

```text
strategy: datacore_inverted_index_v3
collection: ...
candidate_count: ...
result_count: ...
duration_ms: ...
```

## Automatic compatibility

When a v1/v2 collection has no v3 marker, DataCore acquires a rebuild lock, scans existing binary records once, resolves the latest record per ID, writes sharded pointers and postings, and publishes the versioned marker. Concurrent requests wait for the same rebuild lock.

Manual compaction is available through:

```php
$runtime->reindex('collection');
```

or the POST API action `reindex`. Both execute the `memory.reindex` ActionScript action.

## Complexity

| Operation | Previous scan path | DataCore v3 path |
|---|---|---|
| Retrieve by ID | O(number of index lines) | O(1)-style pointer + record read |
| Missing term | O(all canonical and archive records) | O(number of query index keys) |
| Rare term | O(all records) | O(recent postings + matched documents) |
| Common term | O(all records) | O(bounded recent postings and candidates) |
| Delete verification | Full merge | Direct latest tombstone verification |

## Reproducible benchmark

```bash
php tests/benchmark.php 10000
```

The benchmark creates an isolated dataset under the system temporary directory and measures fresh PHP request-style object construction for searches and retrievals. See the performance table in [`README.md`](README.md).

## Pure-PHP guarantees

- No SQL database.
- No vector database.
- No Composer dependency.
- No Node.js or Java runtime.
- No JSON storage.
- PHP arrays are serialized through `PhpSerializer` using `allowed_classes=false` when decoded.
