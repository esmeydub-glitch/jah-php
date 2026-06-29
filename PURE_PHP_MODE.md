# JAH MemoryAgent — Modo PHP Puro

## Regla principal

JAH MemoryAgent se mantiene como proyecto de **PHP puro + ActionScript PHP**.

```text
PHP puro = runtime, configuración y lógica interna
ActionScript PHP = acciones internas del agente
JSON = solo transporte externo API/Qwen, sin secretos
```

## Qué JSON está permitido

JSON público se permite únicamente como formato de transporte para:

1. Entrada/salida HTTP de `public/api.php` y `public/agent.php`.
2. Payload enviado a Qwen Cloud.
3. Respuesta recibida desde Qwen Cloud.

Ese JSON queda aislado en:

```text
app/http/JsonTransport.php
app/QwenConnector.php
public/api.php
public/agent.php
```

También existe serialización interna controlada para auditoría SALK en `.ndjson` y para capas de almacenamiento de DataCore/TieredMemory. Eso no se usa como acciones, paquetes ni configuración.

Esto no significa usar JavaScript ni Node. Es solo texto estructurado para comunicación externa o serialización controlada.

## Qué JSON no se usa

No se usa JSON para:

- Registrar acciones internas.
- Definir módulos internos.
- Ejecutar paquetes.
- Configurar el runtime principal.
- Guardar API keys.
- Ejecutar scripts tipo `npm`.
- Reemplazar ActionScript PHP.


## Capa única de transporte JSON

La versión estricta agrega:

```text
app/http/JsonTransport.php
```

Funciones principales:

```text
decodeRequest()
respond()
encodePublic()
encodeQwenPayload()
decodeQwenResponse()
```

Esa capa valida que el payload público no contenga secretos y que el JSON de Qwen no lleve `api_key`, `Authorization`, `token`, `secret` o `password`.


## API key

La API key **nunca debe viajar dentro del JSON**.

Correcto:

```http
Authorization: Bearer {QWEN_API_KEY}
Content-Type: application/json
```

Incorrecto:

```json
{
  "api_key": "sk-..."
}
```

## ActionScript PHP

Las acciones internas se registran con PHP:

```php
ActionScript::define('memory.classify_input')
    ->requires(['message'])
    ->timeout(1000)
    ->handler(function (array $data): array {
        return classifyInput($data['message']);
    });
```

## Configuración interna

La configuración interna usa PHP arrays:

```php
<?php

return [
    'runtime' => 'php_puro',
    'actions' => 'ActionScript PHP',
];
```

## SALK Security

SALK revisa:

- Que `.env` no esté dentro de `public/`.
- Que `QWEN_API_KEY` exista sin exponerse.
- Que DataCore/runtime estén fuera de `public/`.
- Que no aparezcan secretos en respuestas JSON públicas.
- Que no existan vectores Node/npm:
  - `package.json`
  - `package-lock.json`
  - `node_modules/`
  - `yarn.lock`
  - `pnpm-lock.yaml`
- Que `composer.json`, si existe, no tenga scripts peligrosos.

## Endpoints útiles

```bash
curl "http://localhost:8000/api.php?action=salk_status" | jq
curl "http://localhost:8000/api.php?action=salk_package_vectors" | jq
```

## Verificación esperada

```json
{
  "status": "success",
  "package_vectors": {
    "ok": true,
    "mode": "php_puro_actionscript_php",
    "node_detected": false
  }
}
```
