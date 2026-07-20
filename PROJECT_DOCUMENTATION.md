# JAH MemoryAgent — Project Map

JAH is a persistent-memory agent for Qwen Cloud implemented in PHP 8.1+. The official request path is:

```text
PHP HTTP endpoint
  → RequestGuard and SALK
  → MemoryActionScript
  → DataCoreTurbo / MemoryPyramid
  → bounded recalled context
  → QwenConnector
  → selected durable memory
```

## Authoritative documentation

| Document | Scope |
|---|---|
| [`README.md`](README.md) | Product, setup, architecture, API and submission status |
| [`ACTIONSCRIPT_ARCHITECTURE.md`](ACTIONSCRIPT_ARCHITECTURE.md) | Agent actions and orchestration |
| [`DATACORE_ARCHITECTURE.md`](DATACORE_ARCHITECTURE.md) | Persistent records, indexes and retrieval |
| [`SALK_SECURITY.md`](SALK_SECURITY.md) | Security boundary and audit controls |
| [`TEST_RESULTS.md`](TEST_RESULTS.md) | Reproducible automated evidence |
| [Public Alibaba Cloud proof](https://raw.githubusercontent.com/esmeydub/jah-php/main/ALIBABA_CLOUD_PROOF.md) | Public cloud proof and verification checklist |

## Runtime boundaries

- No framework, Composer package, Node.js or Java runtime.
- Internal records use PHP serialization through `PhpSerializer`.
- Public responses use the plain-text `JAH_RESPONSE` envelope.
- Qwen's required HTTP payload encoding exists only at `app/QwenConnector.php`.
- Secrets are supplied through environment configuration and are not memory content.

This file is an index, not a duplicate of the README.
