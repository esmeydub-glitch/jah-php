# Alibaba Cloud Deployment Proof

This document identifies the repository code link used as Alibaba Cloud proof and provides a deployment verification checklist.

## Repository evidence

The official runtime connects to Qwen Cloud through:

- [`app/QwenConnector.php`](app/QwenConnector.php): native PHP cURL request to the Qwen Cloud compatible API.
- [`app/actions/MemoryActionScript.php`](app/actions/MemoryActionScript.php): `qwen.ask` ActionScript action and memory workflow.
- [`app/config/qwen.php`](app/config/qwen.php): environment-driven Qwen model and endpoint configuration.
- [`app/security/SalkGuard.php`](app/security/SalkGuard.php): key protection, masking, preflight, and audit.

The Qwen API key is loaded from the environment and is sent only in the HTTP `Authorization` header. It is never included in the request body, memory records, public output, or repository.

## Deployment evidence status

| Evidence | Status |
|---|---|
| Qwen Cloud integration code | Complete |
| Alibaba Cloud backend resource | Pending deployment confirmation |
| Public source repository | Complete — [github.com/esmeydub-glitch/jah-php](https://github.com/esmeydub-glitch/jah-php) |
| Public Alibaba Cloud proof document | Complete — [raw ALIBABA_CLOUD_PROOF.md](https://raw.githubusercontent.com/esmeydub-glitch/jah-php/main/ALIBABA_CLOUD_PROOF.md) |
| Required Alibaba service/API code link | Complete — [`app/QwenConnector.php`](app/QwenConnector.php) |

Do not mark the backend deployment complete until the resource and live request have been verified.

## Deployment verification checklist

Verify all of the following without exposing credentials:

1. The Alibaba Cloud console and the backend compute resource used by JAH MemoryAgent.
2. The resource name, region, running state, and public endpoint or domain.
3. The deployed repository revision or application directory.
4. The running PHP service or process.
5. A request to `api.php?action=status` returning `JAH_RESPONSE`.
6. A live POST request to the MemoryAgent chat action.
7. The corresponding backend log or ActionScript trace showing Qwen inference completed.
8. The Qwen API key remains fully hidden.

## Suggested verification commands

Run these on the Alibaba Cloud backend:

```bash
php -v
php -m | grep -E 'curl|zlib'
php tests/run.php
php php_actionscript_php_doc/tests/run.php
```

Verify the public backend from a separate machine:

```bash
curl -H "X-JAH-API-Key: $JAH_API_KEY" \
  "$PUBLIC_BACKEND_URL/api.php?action=status"
```

Verify Qwen and persistent memory:

```bash
curl -X POST \
  -H "X-JAH-API-Key: $JAH_API_KEY" \
  -d "action=chat" \
  -d "collection=deployment-proof" \
  -d "message=Remember that this backend is deployed on Alibaba Cloud" \
  "$PUBLIC_BACKEND_URL/api.php"
```

## Submission links

- Public source repository: [https://github.com/esmeydub-glitch/jah-php](https://github.com/esmeydub-glitch/jah-php)
- Public Alibaba Cloud proof: [https://raw.githubusercontent.com/esmeydub-glitch/jah-php/main/ALIBABA_CLOUD_PROOF.md](https://raw.githubusercontent.com/esmeydub-glitch/jah-php/main/ALIBABA_CLOUD_PROOF.md)
- Alibaba Cloud service/API code: [public QwenConnector.php](https://github.com/esmeydub-glitch/jah-php/blob/main/app/QwenConnector.php)

The required public video link is the approximately three-minute demo listed in [`README.md`](README.md).
