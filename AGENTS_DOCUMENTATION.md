# Documentación de Agentes — JAH MemoryAgent

## Flujo del Agente Principal

```
Usuario → public/index.php → MemoryActionScript::runAgent()
                                    ↓
                        1. Clasificar input (classifyInput)
                                    ↓
                        2. Buscar contexto (search_context)
                                    ↓
                        3. Construir contexto (build_context)
                                    ↓
                        4. Preguntar a Qwen (qwen.ask)
                                    ↓
                        5. Guardar interacción (store_interaction)
                                    ↓
                        Respuesta al usuario
```

---

## 1. Clasificación de Input (`classifyInput`)

**Archivo:** `app/actions/MemoryActionScript.php:269-351`

Determina si el mensaje del usuario debe guardarse en memoria o es ruido.

### Tipos de clasificación:

| Tipo | Importancia | Descripción | Ejemplo |
|------|-------------|-------------|---------|
| `empty` | 0 | Mensaje vacío | "" |
| `noise` | 1 | Saludos, preguntas básicas | "hola", "que eres", "gracias" |
| `forget_request` | 8 | Comandos para olvidar | "olvida esto", "borra mi memoria" |
| `explicit_memory` | 9 | Instrucciones explícitas de guardar | "recuerda que...", "guarda en memoria" |
| `project_fact` | 8 | Hechos del proyecto/stack | "mi proyecto se llama JAH", "uso DataCoreTurbo" |
| `user_preference` | 7 | Preferencias del usuario | "prefiero PHP", "me gusta el tema oscuro" |
| `long_context` | 5 | Mensajes largos con posible valor | >= 80 caracteres |
| `transient_message` | 2 | Mensajes sin valor persistente | Preguntas generales |

### Proceso:
1. Normaliza texto (lowercase, sin acentos, sin puntuación)
2. Verifica si es ruido (lista de frases comunes)
3. Busca palabras clave de cada categoría
4. Retorna array con: `store`, `type`, `importance`, `reason`

---

## 2. Búsqueda de Contexto (`search_context`)

**Archivo:** `app/memory/TieredMemory.php:54-90`

Busca en las 3 capas de memoria usando full-text search.

### Las 3 capas:

| Tier | Almacenamiento | TTL | Ubicación |
|------|----------------|-----|-----------|
| **Hot** | DataCoreTurbo (archivos .bin) | 1 hora | `runtime/memory/datacore/data/` |
| **Warm** | Archivos ndjson | 24 horas | `runtime/memory/warm/` |
| **Cold** | Archivos comprimidos .gz | 7 días | `runtime/memory/cold/` |

### Proceso:
1. Busca en Hot (DataCoreTurbo) con query completo y por términos individuales
2. Busca en Warm (archivos ndjson)
3. Busca en Cold (descomprime .gz y busca)
4. Filtra documentos `assistant` (respuestas previas de Qwen)
5. Ordena por timestamp (más reciente primero)
6. Limita resultados

---

## 3. Construcción de Contexto (`build_context`)

**Archivo:** `app/actions/MemoryActionScript.php:134-165`

Construye el prompt que se envía a Qwen.

### Estructura del contexto:
```
Fecha actual: 2026-06-28 16:30:00 CST
Decision de memoria: guardar | tipo=project_fact | razon=project_or_stack_fact
Memorias recuperadas:
- [memory|hot] Contenido de memoria 1...
- [user|warm] Contenido de memoria 2...
- [memory|cold] Contenido de memoria 3...
```

---

## 4. Pregunta a Qwen (`qwen.ask`)

**Archivo:** `app/QwenConnector.php:15-77`

Envía la pregunta a Qwen Cloud vía cURL.

### Endpoint:
```
POST https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions
```

### Headers:
```
Authorization: Bearer {QWEN_API_KEY}
Content-Type: application/json
```

### Body:
```json
{
  "model": "qwen-max",
  "messages": [
    {"role": "system", "content": "Instrucciones del sistema..."},
    {"role": "user", "content": "Pregunta del usuario"}
  ]
}
```

### Manejo de errores:
- Verifica que `curl_init()` existe
- Timeout de 45 segundos
- Retorna error descriptivo si falla

---

## 5. Almacenamiento de Interacción (`store_interaction`)

**Archivo:** `app/actions/MemoryActionScript.php:179-215`

Guarda la interacción si la clasificación lo indica.

### Proceso:
1. Revisa clasificación del input
2. Si `store = false`, no guarda (ruido, saludos, etc.)
3. Si `store = true`, guarda en tier **hot** con:
   - ID único: `memory_xxxxxxxx`
   - Role: `user`
   - Tags: `[memory, tipo_clasificado, classified]`
   - Importance: según clasificación
   - Timestamp actual

---

## Migración de Tiers

**Archivo:** `app/memory/TieredMemory.php:144-174`

Mueve datos entre tiers basado en TTL:

```
Hot (1h) → Warm (24h) → Cold (7d) → Eliminado
```

### Comando para migrar:
```bash
curl "http://localhost:8000/?action=migrate"
```

---

## ActionScript PHP Engine

**Archivo:** `php_actionscript_php_doc/ActionScriptEngine.php`

Sistema de registro de acciones con:
- `ActionScript::define('nombre')` — Registra una acción
- `->requires(['param1', 'param2'])` — Define parámetros requeridos
- `->timeout(3000)` — Timeout en milisegundos
- `->handler(function($data) { ... })` — Función ejecutora
- `ActionScript::run('nombre', $data)` — Ejecuta la acción

---

## Archivos del Sistema

| Archivo | Función |
|---------|---------|
| `public/index.php` | Interfaz web principal |
| `public/agent.php` | Endpoint API para agente |
| `app/bootstrap.php` | Carga .env, autoloader, config |
| `app/QwenConnector.php` | Cliente HTTP para Qwen Cloud |
| `app/actions/MemoryActionScript.php` | Runtime principal del agente |
| `app/memory/TieredMemory.php` | Sistema de memoria 3 tiers |
| `src/DataCore/DataCoreTurbo.php` | Motor de almacenamiento binario |
| `src/DataCore/MemoryPyramid.php` | Cache LRU + warm + cold |
| `src/DataCore/Compressor.php` | Compresión LZ4/ZSTD/GZIP |
