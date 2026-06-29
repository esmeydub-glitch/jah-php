# SALK Security ActionScript — JAH MemoryAgent

Esta capa agrega acciones SALK en PHP puro para proteger el MemoryAgent durante la demo y el despliegue.

## Objetivo

- Proteger `QWEN_API_KEY`.
- Evitar que secretos se guarden en DataCore.
- Enmascarar secretos en respuestas, contexto, trazas y auditoría.
- Validar que DataCore/runtime no estén expuestos dentro de `public/`.
- Registrar eventos de seguridad en `runtime/security/salk_audit.ndjson`.

## Archivos agregados

| Archivo | Función |
|---|---|
| `app/security/SalkGuard.php` | Núcleo de validación, masking y auditoría SALK userland |
| `app/actions/SalkSecurityActionScript.php` | Acciones ActionScript PHP de seguridad |
| `app/config/salk.php` | Configuración SALK |
| `SALK_SECURITY.md` | Documentación técnica |

## Acciones ActionScript PHP

| Acción | Función |
|---|---|
| `salk.preflight` | Ejecuta validaciones antes del agente |
| `salk.check_env` | Revisa `.env` y evita exposición en `public/` |
| `salk.protect_api_key` | Confirma presencia de key sin mostrarla |
| `salk.check_datacore_path` | Verifica rutas seguras de DataCore/runtime |
| `salk.verify_runtime_permissions` | Revisa permisos de carpetas runtime |
| `salk.mask_secrets` | Enmascara API keys, bearer tokens y secretos |
| `salk.audit_event` | Registra auditoría NDJSON |

## Flujo integrado

```text
Usuario
→ salk.preflight
→ memory.classify_input
→ memory.search_context
→ memory.build_context
→ qwen.ask
→ memory.store_interaction
→ salk.audit_event
→ respuesta al usuario
```

## Protección de API key

La key se carga desde entorno o `.env`, pero no se muestra. SALK solo expone un fingerprint SHA-256 corto:

```json
{
  "present": true,
  "fingerprint": "0a5ec82c41e605dd",
  "source": "process_env"
}
```

## Bloqueo de secretos en memoria

Si el usuario intenta guardar una API key, bearer token o secreto, SALK bloquea el almacenamiento:

```json
{
  "stored": false,
  "reason": "secret_detected_not_stored",
  "type": "secret_blocked",
  "tier": null
}
```

## Endpoint de estado

```bash
curl "http://localhost:8000/api.php?action=salk_status" | jq
```

## Notas

Esta es una capa SALK userland para el hackathon. No depende de Python, Node.js, Composer ni base de datos externa.


---

## Modo PHP puro y vectores package.json

Esta versión incluye una revisión SALK adicional contra vectores de paquetes JSON/Node.

Regla:

```text
JSON permitido: solo transporte API/Qwen.
JSON prohibido: paquetes, acciones internas, API keys o configuración principal.
```

Nueva acción ActionScript PHP:

```text
salk.scan_package_vectors
```

Nuevo endpoint:

```bash
curl "http://localhost:8000/api.php?action=salk_package_vectors" | jq
```

SALK alerta si detecta:

```text
package.json
package-lock.json
npm-shrinkwrap.json
yarn.lock
pnpm-lock.yaml
node_modules/
composer.json con scripts peligrosos
composer.json/composer.lock expuestos en public/
```

La API key de Qwen no viaja en JSON. Se usa exclusivamente como header HTTP:

```http
Authorization: Bearer {QWEN_API_KEY}
```

---

## Aislamiento estricto de JSON

Esta versión concentra el JSON público en una sola capa:

```text
app/http/JsonTransport.php
```

Regla:

```text
API key: solo header Authorization
JSON público: solo API/Qwen
Acciones internas: ActionScript PHP
Configuración interna: PHP arrays
```

`JsonTransport::encodeQwenPayload()` bloquea payloads que intenten incluir:

```text
api_key
authorization
bearer
token
secret
password
```

Esto evita que la API key viaje dentro del JSON.
