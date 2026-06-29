# Automated Test Evidence

This report is reproducible; it does not claim browser or cloud deployment checks.

## Commands

```bash
php php_actionscript_php_doc/test_compiler.php
php php_actionscript_php_doc/tests/run.php
php tests/run.php
find app public src php_actionscript_php_doc tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Covered behavior

- real PHP syntax acceptance and rejection;
- checksummed JAS bytecode execution and corruption rejection;
- ActionScript actions, promises, streams, events, types, policy and task execution;
- generated-summary classification, persistence and retrieval;
- collection isolation, tier deduplication, migration and forgetting;
- DataCore pointer/index correctness, rebuilding and metrics;
- access-key, CSRF and sensitive-field protections;
- signed local replication, tamper detection and replica recovery.

The latest observed totals and date are recorded only after executing these commands. Cloud evidence and the single public demo-video link remain separate in [`ALIBABA_CLOUD_PROOF.md`](ALIBABA_CLOUD_PROOF.md).

## Latest local run

Run on 2026-06-28:

```text
JAS compiler and bytecode: 7/7 PASS
ActionScript runtime: 7/7 PASS
MemoryAgent product: 17/17 PASS
PHP lint: PASS for every PHP file under app, public, src, php_actionscript_php_doc and tests
```
