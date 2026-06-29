# Pure PHP Runtime Contract

JAH runs on PHP 8.1+ without Composer, Node.js, npm, Java, a SQL server or a vector database.

## Allowed runtime mechanisms

- PHP arrays, callables, generators and optional Fibers.
- Files, locks, hashes, compression and PHP serialization.
- Native PHP cURL only for the Qwen Cloud boundary.
- Optional PCNTL for PHP worker execution.
- HTML forms rendered and processed by PHP; no client-side script is required.

## Data formats

DataCore records and SALK audit entries use the `JAHPS1:` envelope produced by `PhpSerializer`. Public endpoints emit `text/plain` in the `JAH_RESPONSE` format. QwenConnector encodes and decodes the provider's required wire payload at the external boundary; that format is not used for internal storage or actions.

## Enforcement

`SalkGuard::checkPackageVectors()` reports Node package artifacts, Composer manifests and executable script files that violate the runtime boundary. Automated PHP lint and product tests are listed in [`TEST_RESULTS.md`](TEST_RESULTS.md).
