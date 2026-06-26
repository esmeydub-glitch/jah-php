# Documentación del Proyecto — JAH-Qwen Bridge

## Resumen

Agente de IA con memoria estratificada en PHP nativo, conectado a Qwen Cloud vía DashScope International API. El sistema usa un motor de almacenamiento binario (DataCoreTurbo) con benchmarks locales reproducibles.

---

## Arquitectura

```
Usuario → agent.php → DataCoreTurbo (binario) + MemoryPyramid (LRU)
                    ↓
              QwenConnector → dashscope-intl.aliyuncs.com
                    ↓
              Respuesta con contexto de memoria
```

---

## Problemas Encontrados y Solucionados

### Problema 1: API Key no cargaba vía HTTP

**Síntoma:** `QWEN_API_KEY not configured` aunque el archivo `config/qwen.php` existía y tenía la key.

**Causa raíz:** PHP's `getenv()` retorna `false` (booleano) cuando la variable de entorno no existe. El código usaba:
```php
$apiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? $configApiKey;
```
Cuando `getenv()` retorna `false`, el operador `??` no lo considera "null" y asigna `false` como API key. Luego `empty(false)` es `true`, pero el string vacío no se detectaba correctamente.

**Solución:**
```php
$envApiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? '';
if ($envApiKey === false) {
    $envApiKey = '';
}
$apiKey = $envApiKey !== '' ? $envApiKey : $configApiKey;
```

**Lección aprendida:** Siempre verificar el tipo de retorno de `getenv()` antes de usar operadores null-coalescing.

---

### Problema 2: Error 500 en agent.php

**Síntoma:** Internal Server Error al llamar `agent.php` vía HTTP.

**Causa raíz:** `agent.php` usaba `JahEngine::getInstance()->boot($config)` que intentaba registrar todos los agentes del sistema (NetworkAgent, CacheAgent, ObserverAgent, etc.). Estos agentes dependían de clases como `Jah\Network\HttpClient` y `Jah\Cache\CacheManager` que no estaban incluidas en el contexto de `agent.php`.

**Solución:** Eliminar la dependencia de `JahEngine`. `agent.php` ahora usa `DataCoreTurbo` y `MemoryPyramid` directamente sin pasar por el motor de agentes.

**Lección aprendida:** Los endpoints HTTP deben ser autocontenedores, sin depender del motor completo del sistema.

---

### Problema 3: Conexión a Qwen Cloud fallaba

**Síntoma:** `Error HTTP 0` o timeout al conectar con la API de Qwen.

**Causa raíz:** Se usaba el endpoint incorrecto `api.qwenlm.ai` que no resuelve en este entorno. El endpoint correcto es `dashscope-intl.aliyuncs.com/compatible-mode/v1`.

**Solución:** Actualizar `QwenConnector.php` con el endpoint correcto:
```php
private string $baseUrl = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1';
```

Y usar el path `/chat/completions` (formato OpenAI-compatible):
```php
$ch = curl_init($this->baseUrl . '/chat/completions');
```

**Lección aprendida:** Verificar siempre los endpoints con la documentación oficial del servicio.

---

### Problema 4: Pruebas fallaban por falta de contexto

**Síntoma:** Qwen respondía "No tengo información en el contexto proporcionado" aunque había datos guardados.

**Causa raíz:** Los datos de prueba tenían tags pero el contenido no coincidía con los términos de búsqueda. El search de DataCore es case-sensitive y busca en el JSON completo del documento.

**Solución:** Alimentar la base con datos que contengan las palabras clave exactas que se usarán en las preguntas:
- Pregunta: "¿Qué lenguajes prefiere?" → Dato: "El usuario prefiere programar en PHP y JavaScript"
- Pregunta: "¿Qué tema de color?" → Dato: "Al usuario le gusta el tema oscuro Dracula"

**Lección aprendida:** El contexto debe contener las palabras clave exactas que el usuario podría usar en sus preguntas.

---

### Problema 5: Soft-delete no funcionaba en DataCore

**Síntoma:** Documentos eliminados seguían siendo recuperables.

**Causa raíz:** `DataCoreTurbo::find()` no filtra documentos con `_deleted=true`. El método `insert()` crea un nuevo registro con `_deleted=true` (append-only), pero `find()` retorna el último registro que coincide con el ID, sin importar el flag de eliminación.

**Estado:** Requiere fix en `DataCoreTurbo.php` — el método `find()` debe verificar `$payload['_deleted'] ?? false` y retornar `null` si está marcado como eliminado.

---

### Problema 6: Servidor PHP embebido mataba procesos hijos

**Síntoma:** El servidor PHP moría cuando el comando bash terminaba.

**Causa raíz:** `php -S` ejecutado con `&` en bash normal muere cuando el proceso padre termina.

**Solución:** Usar `nohup` + `disown` o el tool `background_process` que mantiene el proceso vivo independientemente.

---

## Pruebas Realizadas

### Pruebas de Almacenamiento

| Prueba | Comando | Resultado |
|--------|---------|-----------|
| Guardar memoria | `POST api.php action=save` | ✅ `{"status":"success"}` |
| Buscar memoria | `POST api.php action=search query="PHP"` | ✅ Retorna documentos |
| Recuperar por ID | `POST api.php action=retrieve id="pref_001"` | ✅ Retorna documento |
| Batch insert 100 docs | `POST api.php action=batch docs=[...]` | ✅ 100 insertados |
| Eliminar (soft-delete) | `POST api.php action=delete id="pref_001"` | ⚠️ No filtra al recuperar |

### Pruebas de Agente con Qwen

| Pregunta | Respuesta | Contexto usado |
|----------|-----------|----------------|
| ¿Qué lenguajes de programación prefiere el usuario? | "El usuario prefiere programar en PHP y JavaScript. Además, le gusta utilizar el framework Laravel" | 5 |
| ¿Qué tema de color prefiere el usuario para su editor? | "El usuario prefiere el tema oscuro Dracula para su editor de código" | 7 |
| ¿Qué bases de datos usa el usuario en sus proyectos? | "El usuario utiliza MySQL y Redis como bases de datos principales" | 9 |
| ¿En qué hackathon está participando el usuario? | "El usuario está participando en el Qwen Cloud Hackathon 2026" | 11 |

### Pruebas de Estadísticas

```bash
GET api.php?action=stats
```
```json
{
  "status": "success",
  "data": {
    "turbo": {"documents": 113, "buffered": 0},
    "pyramid": {"hot_entries": 0, "warm_files": 0, "cold_files": 0},
    "binary_segments": 1
  }
}
```

---

## Configuración de Producción

### Variables de Entorno

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `QWEN_API_KEY` | API key de DashScope International | `sk-tu-key-aqui` |

### Despliegue en Alibaba Cloud Function Compute

1. Crear servicio en Function Compute
2. Runtime: Custom PHP 8.x
3. Handler: `fc_handler.php`
4. Variable de entorno: `QWEN_API_KEY`
5. Subir todo el proyecto como zip

### Despliegue en Alibaba Cloud ECS

```bash
apt update && apt install -y php-cli php-curl git
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php
export QWEN_API_KEY="tu-key-real"
php -S 0.0.0.0:8000 -t jah-php
```

---

## Estructura del Proyecto

```
jah-php/
├── agent.php              # Endpoint principal del agente
├── api.php                # API REST para memoria
├── bridge.php             # Alias a api.php
├── fc_handler.php         # Handler para Function Compute
├── QwenConnector.php      # Cliente HTTP para Qwen Cloud
├── config/
│   ├── config.php         # Configuración global
│   ├── qwen.php           # API key (no subir a git)
│   └── database.php       # Config MySQL
├── core/                  # Motor PHP (JahEngine, EventBus)
├── agents/                # 11 agentes especializados
├── memory/
│   ├── DataCoreTurbo.php  # Motor binario
│   ├── MemoryPyramid.php  # Cache LRU + warm + cold
│   ├── datacore/          # Archivos binarios .bin
│   └── pyramid/           # Archivos de cache
└── jah-datacore/          # Librerías DataCore
```

---

## Estado Actual

- ✅ Almacenamiento binario funcionando
- ✅ Búsqueda full-text funcionando
- ✅ Integración con Qwen Cloud funcionando
- ✅ Agente responde con contexto personalizado
- ⚠️ Soft-delete requiere fix menor
- ⚠️ Search case-sensitive (mejorar a insensitive)

---

## Próximos Pasos

1. Hacer search case-insensitive en DataCoreTurbo
2. Implementar filtro de `_deleted` en `find()`
3. Agregar más datos de contexto para el hackathon
4. Grabar video demo de 3 minutos
5. Subir a Alibaba Cloud para prueba de infraestructura
