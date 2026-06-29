# JAH MemoryAgent — Verification Report

Verified locally on 2026-06-28:

```text
JAS compiler and bytecode: 7/7 PASS
ActionScript runtime: 7/7 PASS
MemoryAgent product: 17/17 PASS
PHP lint: PASS
```

These results come from automated PHP tests. This report does not label browser pages, public APIs, Qwen Cloud or Alibaba Cloud deployment as passing without a live test and external evidence.

## Reproduce

```bash
php php_actionscript_php_doc/test_compiler.php
php php_actionscript_php_doc/tests/run.php
php tests/run.php
find app public src php_actionscript_php_doc tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Automated coverage

- HOT, WARM and COLD storage with deduplicated retrieval.
- Collection isolation, durable forgetting and tier migration.
- Summary-request classification and generated Qwen-response persistence.
- DataCore direct pointers, inverted postings, stale-update filtering and reindexing.
- CSRF, API access-key and sensitive-field enforcement.
- JAS parsing, bytecode integrity and ActionScript task execution.
- Signed local replication, tamper detection and recovery.

## Manual checks still required for release

- Start `php -S localhost:8000 -t public` and exercise the HTML forms.
- Exercise the protected API from another process or machine.
- Perform a live Qwen Cloud chat request.
- Verify the Alibaba Cloud resource and code proof described in [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md).

Public endpoints use the plain-text `JAH_RESPONSE` envelope. Qwen's required external wire encoding remains isolated in `app/QwenConnector.php`.
