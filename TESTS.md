# JAH MemoryAgent — Test de SALK Security

## Regla importante

Este proyecto es 100% PHP puro. La única conexión que usa JSON es QwenConnector.php porque Qwen Cloud lo exige.

Los endpoints públicos responden en formato `JAH_RESPONSE` (text/plain), no JSON.

## Cómo testear SALK

**Incorrecto (espera JSON):**
```bash
curl "http://localhost:8000/api.php?action=salk_status" | jq
```

**Correcto (valida con grep):**
```bash
# Verificar que SALK responde correctamente
curl -s "http://localhost:8000/api.php?action=salk_status" | grep "salk.ok: true"
curl -s "http://localhost:8000/api.php?action=salk_status" | grep "salk.errors: []"
curl -s "http://localhost:8000/api.php?action=salk_status" | grep "status: success"
```

## Criterios de evaluación SALK

| Check | Comando | Resultado esperado |
|-------|---------|-------------------|
| SALK responde | `curl -s "http://localhost:8000/api.php?action=salk_status"` | `salk.ok: true` |
| Sin errores | `grep "salk.errors"` | `salk.errors: []` |
| API key protegida | `grep "api_key.present"` | `api_key.present: true` |
| API key NO expuesta | `grep "sk-ws"` | Sin resultados (no se imprime) |
| DataCore seguro | `grep "datacore_paths.ok"` | `datacore_paths.ok: true` |
| Sin secretos en código | `grep "secret_scan.matches"` | `secret_scan.matches: []` |

## Ejemplo de respuesta correcta SALK

```text
JAH_RESPONSE
status: success
service: JAH MemoryAgent
salk.ok: true
salk.context: api.salk_status
salk.errors: []
salk.warnings: []
salk.checks.env.ok: true
salk.checks.api_key.ok: true
salk.checks.api_key.present: true
salk.checks.api_key.fingerprint: 400559176326948d
salk.checks.api_key.source: env_loaded
salk.checks.datacore_paths.ok: true
salk.checks.permissions.ok: true
salk.checks.secret_scan.ok: true
```

## Nota para el hackathon

Si el evaluador espera JSON, explicar:

> "Este proyecto usa PHP puro + ActionScript PHP. El formato JSON se usa únicamente en app/QwenConnector.php porque Qwen Cloud lo exige. Los endpoints públicos responden en formato JAH_RESPONSE (text/plain) para mantener la regla de PHP puro."

El fingerprint de la API key se muestra (SHA256) pero NUNCA la key completa.
