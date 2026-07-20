# JAH MemoryAgent

**Global AI Hackathon Series with Qwen Cloud — Track 1: MemoryAgent**

[![License: MIT](https://img.shields.io/badge/License-MIT-00a86b.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)
![Qwen Cloud](https://img.shields.io/badge/AI-Qwen%20Cloud-6f42c1.svg)

JAH MemoryAgent is a persistent-memory AI agent built in pure PHP. It learns durable user preferences and project facts across sessions, retrieves only relevant memories for a limited Qwen context window, and forgets information through persistent tombstones. Its internal ActionScript PHP runtime coordinates security, classification, retrieval, Qwen inference, storage, migration, and audit actions.

The project uses no Node.js, npm, Java, or browser JavaScript. Internal configuration and storage use PHP arrays and JAH/PHP serialization. JSON is isolated inside `app/QwenConnector.php` only because the Qwen Cloud compatible API requires it at the external boundary.

## Why this is a MemoryAgent

The Track 1 requirements are implemented directly:

| MemoryAgent requirement | JAH implementation |
|---|---|
| Persistent cross-session memory | Append-only `DataCoreTurbo` storage and persistent JAH indexes |
| Immediate conversational context | Hot keeps active dialogue; older sections of long conversations remain in Warm for seven days |
| Accumulated experience | Every meaningful interaction is classified and may become a durable memory |
| User preference memory | Preference, workflow, identity, and project signals receive explicit classifications |
| Efficient retrieval | SHA-256 direct pointers, persistent inverted term/prefix index, ranked candidates, deduplication, and bounded results |
| Timely forgetting | Tombstones override canonical and archived copies so deleted memories cannot reappear |
| Limited context windows | At most 10 relevant memories are reduced to 280-character excerpts before Qwen inference |
| Cross-session improvement | Stored facts are recalled on later requests and incorporated into the next Qwen decision |

## Core capabilities

- Pure PHP ActionScript registry with required parameters, execution budgets, result envelopes, and traces.
- Persistent Hot / Warm / Cold memory lifecycle.
- Human conversational memory: Hot keeps the active thread, Warm keeps temporary history for seven days, and Cold stores permanent explicitly requested or highly important knowledge.
- Collection isolation for independent users, agents, or workspaces.
- Meaningful-memory classifier that rejects greetings, noise, transient questions, secrets, and forget commands.
- Reusable-knowledge classifier that persists Qwen-generated book summaries and their source query instead of saving only the prompt; repeated requests update one deterministic memory instead of creating duplicates.
- Secret detection, output masking, security preflight, audit journal, API access control, and CSRF protection.
- Atomic append-only writes, O(1)-style direct ID pointers, persistent inverted postings, rare-first query planning, automatic index rebuilding, concurrent locking, and compressed cold records.
- Qwen Cloud connection through native PHP cURL with explicit connect and request timeouts.
- Plain-text `JAH_RESPONSE` API plus a server-rendered PHP interface with no JavaScript.

Technical deep dives:

- [`DATACORE_ARCHITECTURE.md`](DATACORE_ARCHITECTURE.md) — record format, direct pointers, inverted postings, consistency, rebuilding, and complexity.
- [`ACTIONSCRIPT_ARCHITECTURE.md`](ACTIONSCRIPT_ARCHITECTURE.md) — action DSL, execution envelopes, official pipeline, security gates, and traces.

## Architecture diagram

```text
┌──────────────────────────── Clients ─────────────────────────────┐
│  Server-rendered PHP UI       CLI / curl       Agent consumers   │
└───────────────┬──────────────────┬──────────────────┬─────────────┘
                │ HTTP GET/POST    │                  │
                ▼                  ▼                  ▼
┌──────────────────────── Public PHP boundary ─────────────────────┐
│ public/index.php       public/api.php       public/agent.php      │
│ RequestGuard → JahTransport → SALK masking and access checks     │
└───────────────────────────────┬───────────────────────────────────┘
                                │ PHP arrays
                                ▼
┌──────────────────── ActionScript PHP runtime ─────────────────────┐
│ 1. salk.preflight                                                │
│ 2. memory.classify_input                                         │
│ 3. memory.load_conversation + memory.search_context              │
│ 4. memory.build_context (recent turns + durable knowledge)       │
│ 5. qwen.ask                                                      │
│ 6. memory.store_conversation (Hot + long-dialogue Warm)          │
│ 7. memory.store_interaction (important knowledge → Cold)         │
│ 8. salk.audit_event                                              │
└───────────────┬───────────────────────────────┬───────────────────┘
                │                               │
                ▼                               ▼
┌──────────────────────────────┐   ┌───────────────────────────────┐
│ TieredMemory                 │   │ QwenConnector                 │
│                              │   │ Native PHP cURL               │
│ DataCoreTurbo canonical log  │   │ Authorization header only     │
│  ├─ segmented binary data    │   │ External JSON boundary only   │
│  ├─ direct SHA-256 pointers  │   └──────────────┬────────────────┘
│  ├─ inverted term postings   │                  │ HTTPS
│  └─ persistent tombstones    │                  │
│                              │                  ▼
│ MemoryPyramid                │   ┌───────────────────────────────┐
│  ├─ Hot: active dialogue     │   │ Qwen Cloud                   │
│  ├─ Warm: temporary, 7 days  │   │ qwen-max / configured model  │
│  └─ Cold: permanent memory   │   └───────────────────────────────┘
└───────────────┬──────────────┘
                ▼
┌──────────────────────────────────────────────────────────────────┐
│ runtime/                                                         │
│ Persistent memory, indexes, compressed archives, and SALK audit  │
└──────────────────────────────────────────────────────────────────┘
```

## Agent decision flow

```text
Request
  ↓
SALK security preflight ── fail ──→ block, mask, and audit
  ↓ pass
Load Hot/Warm conversation + classify durable importance
  ↓
Retrieve relevant Warm/Cold memories from the selected collection
  ↓
Deduplicate → remove tombstoned records → sort by recency → limit
  ↓
Build a compact memory context
  ↓
Ask Qwen Cloud
  ↓
Append dialogue to Hot; retain long overflow in Warm for 7 days; store permanent knowledge in Cold
  ↓
Return a masked ActionScript trace and persist the audit event
```

## Memory lifecycle

```text
HOT                         WARM                        COLD
active conversation         temporary history          permanent important knowledge
natural working context     retained for 7 days        "recuerda" / "guarda" / high importance
          │                         │                         ▲
          └─ conversation grows ───►│                         │
classified important interaction ─────────────────────────────┘

Hot and unexpired Warm turns are loaded together to preserve conversational
coherence. Warm expires after seven days and never becomes Cold automatically.
Cold has no time expiration. The Qwen prompt is size-bounded independently.

DELETE / FORGET
        ↓
persistent tombstone in canonical storage and memory index
        ↓
all older Hot, Warm, and Cold copies are suppressed during retrieval
```

## Project structure

```text
.
├── app/
│   ├── actions/
│   │   ├── MemoryActionScript.php       # Official agent workflow
│   │   └── SalkSecurityActionScript.php # Security actions
│   ├── config/                           # Pure PHP configuration arrays
│   ├── http/
│   │   ├── JahTransport.php              # JAH request/response transport
│   │   └── RequestGuard.php              # API auth, origin and CSRF checks
│   ├── memory/TieredMemory.php           # Memory lifecycle and retrieval
│   ├── security/SalkGuard.php            # Secret protection and audit
│   ├── QwenConnector.php                 # Only Qwen Cloud boundary
│   └── bootstrap.php                     # Manual autoload and environment boot
├── public/
│   ├── index.php                         # Server-rendered PHP interface
│   ├── api.php                           # Plain-text JAH API
│   └── agent.php                         # Dedicated chat endpoint
├── src/DataCore/
│   ├── DataCoreTurbo.php                 # Segments, pointers and inverted index
│   ├── MemoryPyramid.php                 # Hot / Warm / Cold persistence
│   ├── PhpSerializer.php                 # JAHPS1 PHP serialization
│   ├── StorageAgent.php                  # Locked append-only worker storage
│   ├── WorkerPool.php                    # Verified bounded PHP processes
│   └── ReplicationAgent.php              # Signed local replication
├── php_actionscript_php_doc/
│   ├── ActionScriptEngine.php            # Pure PHP action runtime
│   ├── JahEngineJas.php                   # JAS policy evaluator
│   └── tests/run.php                      # ActionScript tests
├── tests/
│   ├── run.php                            # MemoryAgent product tests
│   └── benchmark.php                      # Reproducible DataCore benchmark
├── DATACORE_ARCHITECTURE.md                # DataCore v3 technical design
├── ACTIONSCRIPT_ARCHITECTURE.md            # ActionScript PHP technical design
├── ALIBABA_CLOUD_PROOF.md                 # Cloud deployment evidence guide
├── HACKATHON_TEST_REPORT.md               # Reproducible test report
├── LICENSE                                # MIT open-source license
└── .env.example                           # Safe configuration template
```

## Requirements

- PHP 8.1 or newer.
- PHP cURL extension for Qwen Cloud.
- PHP zlib extension for Cold memory compression.
- A Qwen Cloud API key.
- Write access to the configured runtime and session paths.
- Optional: PCNTL for parallel worker execution; it is not required by the official request path.

No Composer, Node.js, npm, Java, database server, or frontend build process is required.

## Installation

### Automated Alibaba Cloud ECS installation (`deploy_alibaba_ecs.sh`)

The recommended deployment path is the included executable installer:

- [`deploy_alibaba_ecs.sh`](deploy_alibaba_ecs.sh)

It is designed for a clean **Alibaba Cloud Linux 3 ECS** instance and must be run as `root`. No Docker, Composer, Node.js, npm, Java, or external database is required.

Install Git, clone the deployment branch, and run the script:

```bash
dnf install -y git
cd /root
git clone -b agent/alibaba-ecs-installer https://github.com/esmeydub/jah-php.git
cd /root/jah-php
chmod 755 deploy_alibaba_ecs.sh
./deploy_alibaba_ecs.sh
```

The script performs the complete deployment sequence:

1. Verifies `root`, Alibaba Cloud Linux 3, `dnf`, and the x86-64 environment.
2. Installs Git, cURL, unzip, PHP 8.2, and the required PHP extensions.
3. Requests `QWEN_API_KEY` and `JAH_API_KEY` using hidden terminal input.
4. Writes both keys only to the ignored `.env` with mode `0600`; neither key is embedded in the script or committed to Git.
5. Prepares persistent DataCoreTurbo, MemoryPyramid, session, audit, and deployment directories.
6. Validates the PHP source and runs the complete `18/18` product suite and `7/7` ActionScript suite.
7. Installs and enables `jah-memoryagent.service` with `systemd` on port 8000.
8. Executes live status, Qwen, cross-session memory, retrieval, search, and SALK checks.
9. Creates a secret-free report at `runtime/deployment/alibaba-ecs-proof.txt`.
10. Prints the service, logs, local URL, and SSH tunnel commands needed after installation.

The credential prompts look like this; typed values are not displayed:

```text
Pega tu QWEN_API_KEY (entrada oculta):
Crea una JAH_API_KEY de al menos 16 caracteres (entrada oculta):
```

Verify the completed deployment:

```bash
systemctl status jah-memoryagent --no-pager -l
ss -ltnp | grep ':8000'
cat /root/jah-php/runtime/deployment/alibaba-ecs-proof.txt
journalctl -u jah-memoryagent -n 100 --no-pager -l
```

Expected summaries in the generated proof:

```text
SUMMARY 18/18
SUMMARY 7/7
```

#### Secure access through an SSH tunnel

Keep inbound TCP 8000 closed in the ECS Security Group and run this on the authorized workstation:

```bash
ssh -N -L 8000:127.0.0.1:8000 root@<ECS_PUBLIC_IP>
```

Then open `http://127.0.0.1:8000/index.php`. The local address is forwarded through encrypted SSH to the real ECS service. No SSH password, private key, Qwen key, or JAH key belongs in the repository.

#### Temporary public judging access

For hackathon judging only, TCP 8000 can be temporarily authorized in the ECS Security Group and the authenticated interface can be opened at:

```text
http://<ECS_PUBLIC_IP>:8000/index.php
```

Keep `JAH_API_KEY` enabled, share only a temporary judge key through Devpost's private testing instructions, and never share `QWEN_API_KEY`. Remove the public rule and rotate the judge key after judging.

For complete deployment evidence and the detailed tunnel request path, see [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md).

### Manual local installation

Clone the public repository:

```bash
git clone -b agent/alibaba-ecs-installer https://github.com/esmeydub/jah-php.git
cd jah-php
cp .env.example .env
```

Configure at least:

```dotenv
QWEN_API_KEY=
QWEN_MODEL=qwen-max
QWEN_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1

# Required for remote access. Use a long random value.
JAH_API_KEY=
```

Start the pure PHP server:

```bash
php -S 0.0.0.0:8000 -t public
```

Open `http://localhost:8000/index.php`. When `JAH_API_KEY` is configured, the PHP interface presents a server-side login form. Without it, access is restricted to loopback clients.

## API examples

All mutation actions use POST. Remote requests include `X-JAH-API-Key` or an `Authorization: Bearer` header.

Status:

```bash
curl -H "X-JAH-API-Key: $JAH_API_KEY" \
  "http://localhost:8000/api.php?action=status"
```

Save a durable preference:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=save" \
  -d "collection=demo-user" \
  -d "id=preferred-language" \
  -d "content=The user prefers concise answers in Spanish" \
  -d "tier=hot" \
  "http://localhost:8000/api.php"
```

Search relevant memory:

```bash
curl -H "X-JAH-API-Key: $JAH_API_KEY" \
  "http://localhost:8000/api.php?action=search&collection=demo-user&query=Spanish"
```

Run the full MemoryAgent:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=chat" \
  -d "collection=demo-user" \
  -d "conversation_id=demo-thread" \
  -d "message=How should you answer me?" \
  "http://localhost:8000/api.php"
```

Send a follow-up with the same `conversation_id`; JAH loads recent Hot turns even when the new words do not overlap the previous question:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=chat" \
  -d "collection=demo-user" \
  -d "conversation_id=demo-thread" \
  -d "message=And who wrote it?" \
  "http://localhost:8000/api.php"
```

Forget a memory:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=forget" \
  -d "collection=demo-user" \
  -d "id=preferred-language" \
  "http://localhost:8000/api.php"
```

Rebuild and compact a collection's DataCore indexes:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=reindex" \
  -d "collection=demo-user" \
  "http://localhost:8000/api.php"
```

## Security model

- `QWEN_API_KEY` is sent only in Qwen's `Authorization` header.
- `JAH_API_KEY` protects remote API access and the PHP interface.
- The interface uses server-side sessions, SameSite cookies, and CSRF tokens.
- SALK blocks known keys, bearer tokens, passwords, credentials, and secret-bearing memory payloads.
- Public responses and ActionScript traces are recursively masked.
- Serialized input disables PHP object reconstruction with `allowed_classes=false`.
- Mutations require POST; request sizes, action names, model names, IDs, and collections are bounded or sanitized.
- DataCore uses file locks, atomic offsets, encoded indexes, and persistent tombstones.
- Runtime memory and `.env` are excluded from version control.

## Tests and reproducibility

Run the MemoryAgent product suite:

```bash
php tests/run.php
```

Expected result:

```text
SUMMARY 18/18
```

Run the ActionScript PHP suite:

```bash
php php_actionscript_php_doc/tests/run.php
```

Expected result:

```text
SUMMARY 7/7
```

The product tests cover API access keys, CSRF, generated book-summary memory, Hot conversational continuity, Hot→Warm overflow, seven-day Warm expiration, permanent Cold memory, bounded Qwen context, collection isolation, forgetting, sensitive-field rejection, indexes, search metrics, replication and worker correctness.

## DataCore performance

DataCore v3 moves retrieval work from full segment scans to two persistent pure-PHP structures:

1. SHA-256 selects one of 256 compact pointer journals that maps each collection and document ID to its latest binary segment and byte offset.
2. An inverted index maps normalized exact terms and prefixes to recent document IDs. Stale postings are safe because the current pointer and tombstone are always verified before returning a result.

Indexes rebuild automatically when existing v1/v2 data is opened. They can also be compacted explicitly through the `memory.reindex` ActionScript action or the POST API action `reindex`.

Reproducible benchmark:

```bash
php tests/benchmark.php 10000
```

Measured locally with PHP 8.4 and 10,000 synthetic memories:

| Operation | Scan-based baseline | Indexed DataCore v3 |
|---|---:|---:|
| Missing-term search | 29.28 ms | 0.033 ms |
| Rare-term search | 29.28 ms worst-case scan | 0.120 ms |
| Common-term search, 20 results | 29.28 ms | 7.021 ms |
| Retrieve latest document by ID | 1.488 ms | 0.073 ms |
| Indexed write cost | 0.024 ms/document baseline | 0.160 ms/document |

The index intentionally trades about 0.14 ms of additional write work per document for large read-latency gains. At 10,000 documents, sharding keeps the complete index near 9.7 MB and 523 files instead of creating one filesystem entry per ID and numeric term. MemoryAgent writes one durable memory at a time but searches on every agent turn, so this read-optimized tradeoff matches the product workload. Results vary by filesystem and hardware.

## Alibaba Cloud and Qwen Cloud deployment

- Qwen Cloud integration code: [`app/QwenConnector.php`](app/QwenConnector.php)
- Public Alibaba Cloud proof: [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md)
- Automated ECS installer: [`deploy_alibaba_ecs.sh`](deploy_alibaba_ecs.sh)
- Architecture diagram: [`docs/submission/jah-memoryagent-architecture-en.png`](docs/submission/jah-memoryagent-architecture-en.png)
- Deployment screenshot: [`docs/submission/alibaba-ecs-deployment-proof.png`](docs/submission/alibaba-ecs-deployment-proof.png)
- Backend deployment target: Alibaba Cloud compute with Qwen Cloud inference.

The public Alibaba Cloud proof is [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md), with the service/API implementation in [`app/QwenConnector.php`](app/QwenConnector.php). Do not commit or expose cloud credentials.

## Hackathon submission

| Deliverable | Status |
|---|---|
| Track identified as **Track 1: MemoryAgent** | Complete |
| Public source repository | Complete — [github.com/esmeydub/jah-php](https://github.com/esmeydub/jah-php) |
| Detectable open-source license | Complete — [MIT License](LICENSE) |
| Architecture diagram | Complete — [English PNG](docs/submission/jah-memoryagent-architecture-en.png) and diagram above |
| Text description and feature explanation | Complete — included above |
| Alibaba Cloud integration code | Complete — [`QwenConnector.php`](app/QwenConnector.php) |
| Public Alibaba Cloud proof | Complete — [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md) |
| Alibaba Cloud service/API code | Complete — [`app/QwenConnector.php`](app/QwenConnector.php) |
| Automated Alibaba ECS installer | Complete — [`deploy_alibaba_ecs.sh`](deploy_alibaba_ecs.sh) |
| Alibaba ECS deployment screenshot | Complete — [public PNG](docs/submission/alibaba-ecs-deployment-proof.png) |
| Public judging endpoint | Complete — [JAH MemoryAgent on Alibaba ECS](http://47.77.201.239:8000/index.php) |
| Approximately three-minute public demo video | Complete — [YouTube](https://youtu.be/3H8MfxC-SFY) |
| Optional public build journey post | Optional Blog Post Award entry |

### Demo video

[JAH MemoryAgent — Qwen Cloud and Alibaba ECS demo (2:58)](https://youtu.be/3H8MfxC-SFY)

## Suggested three-minute demo

1. **0:00–0:25 — Problem and architecture:** show the diagram and explain cross-session memory.
2. **0:25–1:00 — Preference learning:** tell the agent a durable preference and show the classification trace.
3. **1:00–1:35 — Cross-session recall:** start a new session and show the preference affecting Qwen's answer.
4. **1:35–2:05 — Limited-context retrieval:** show relevant memory selection, deduplication, and context count.
5. **2:05–2:30 — Forgetting:** delete the preference and prove it no longer appears from Hot, Warm, or Cold storage.
6. **2:30–2:50 — Engineering:** show ActionScript PHP, DataCore, SALK, tests, and Qwen Cloud integration.
7. **2:50–3:00 — Impact:** position JAH as an embeddable memory layer for PHP applications.

## Product value

Most memory-agent examples depend on a large framework stack or external vector database. JAH offers a small, auditable, open-source alternative for existing PHP products: one runtime, no package manager, no database service, persistent forgetting, collection isolation, and a direct Qwen Cloud boundary. The design can be embedded in customer-support systems, personal assistants, learning platforms, internal knowledge tools, and long-running workflow agents.

## License

Released under the [MIT License](LICENSE).
