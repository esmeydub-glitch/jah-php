# ActionScript PHP Architecture

ActionScript PHP is JAH's internal agent orchestration runtime. It is implemented entirely in PHP and is unrelated to browser JavaScript or Adobe ActionScript.

## Action definition

```php
ActionScript::define('memory.search_context')
    ->requires(['query'])
    ->timeout(3000)
    ->handler(function (array $data): array {
        return ['memories' => [], 'count' => 0];
    });
```

Every action has:

- a validated global name;
- required input parameters;
- an execution budget;
- a PHP callable handler;
- a structured success or failure envelope;
- measured duration and optional budget warning.

## Result envelope

```text
success: true|false
action: memory.search_context
duration_ms: 0.123
result: PHP value
error: present only on failure
budget_exceeded: present when a blocking handler completed late
```

Fibers support cooperative actions. A blocking PHP operation cannot be safely preempted after it has already produced side effects, so a completed late handler returns its result with `budget_exceeded=true`. External operations such as Qwen cURL also enforce their own native connection and request timeouts.

## Official MemoryAgent pipeline

```text
salk.preflight
  ↓ security gate
memory.classify_input
  ↓ memory value decision
memory.load_conversation + memory.search_context
  ↓ recent dialogue + DataCore v3 indexed knowledge
memory.build_context
  ↓ bounded Qwen context
qwen.ask
  ↓ native PHP cURL
memory.store_conversation
  ↓ active dialogue in Hot; long overflow in Warm
memory.store_interaction
  ↓ explicit or classified important knowledge in Cold
salk.audit_event
```

The pipeline stops before Qwen when security preflight fails. It also stops before persistence when Qwen fails or SALK blocks a secret.

## Memory actions

| Action | Responsibility |
|---|---|
| `memory.classify_input` | Decide whether to store user input or reusable generated knowledge |
| `memory.load_conversation` | Merge ordered Hot and Warm turns by conversation ID without semantic matching |
| `memory.search_context` | Ranked DataCore inverted-index retrieval |
| `memory.build_context` | Combine and bound recent dialogue plus durable recalled knowledge |
| `memory.store_conversation` | Append an exchange to Hot and move older sections to seven-day Warm when dialogue becomes long |
| `memory.store_interaction` | Route explicit/high-importance facts to permanent Cold; summaries and lower-importance reusable context to seven-day Warm |
| `memory.save` | Explicit collection-aware memory write |
| `memory.retrieve` | Direct pointer lookup by ID |
| `memory.forget` | Append a durable tombstone |
| `memory.migrate` | Move generic aged Hot records to Warm and expire Warm after seven days; never expire or auto-create Cold |
| `memory.stats` | Return memory and index statistics |
| `memory.reindex` | Rebuild and compact DataCore v3 indexes |

## SALK actions

| Action | Responsibility |
|---|---|
| `salk.preflight` | Environment, path, permission and secret checks |
| `salk.protect_api_key` | Verify Qwen key presence without exposing it |
| `salk.scan_package_vectors` | Enforce the pure-PHP runtime boundary |
| `salk.validate_public_payload` | Detect sensitive public fields |
| `salk.mask_secrets` | Recursively mask output |
| `salk.audit_event` | Append a JAH-serialized audit event |

## Traceability

Each agent response can expose a masked action trace containing the action name, success state, duration, budget state, warning, safe decision details, and error. This makes the agent workflow inspectable during the hackathon demo without revealing credentials.

## Tests

```bash
php php_actionscript_php_doc/tests/run.php
php tests/run.php
```

The first suite validates the generic ActionScript runtime. The product suite validates its integration with DataCore, SALK, cross-request conversation context, collections, migration, forgetting, metrics, and reindexing.
