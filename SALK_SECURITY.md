# SALK Security Model

SALK is JAH's PHP security gate and append-only audit layer.

## Controls

| Control | Behavior |
|---|---|
| Environment check | Rejects a public `.env` and warns about unsafe permissions |
| Key protection | Reports only presence, source and a one-way fingerprint |
| Storage paths | Keeps memory and audit data outside `public/` |
| Secret scan | Detects likely hardcoded credentials in runtime source |
| Runtime boundary | Detects package ecosystems forbidden by pure-PHP mode |
| Public payload validation | Blocks sensitive fields before output or persistence |
| Audit | Appends masked `JAHPS1` events to `.jahl` storage |
| Request guard | Enforces access keys remotely and CSRF tokens for forms |

## ActionScript security actions

`SalkSecurityActionScript` registers preflight, environment, key, path, package-vector, permission, payload, masking and audit actions. A failed security preflight stops the chat workflow before Qwen is called.

## Trust boundary

Qwen credentials are read from the environment and added only to the outbound authorization header inside `QwenConnector`. They must never appear in memory documents, audit metadata, public responses, screenshots or recordings.

Run `php tests/run.php` to exercise request authorization, CSRF enforcement and sensitive-field rejection alongside the memory tests.
