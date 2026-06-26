# JAH-PHP — Motor de Agentes PHP con DataCore Binario

**JAH-PHP** es un framework de agentes de IA en PHP nativo con un motor de almacenamiento binario de alto rendimiento (**DataCore**) y una API REST para integración con LLMs como Qwen.

---

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│  Qwen / LLM Cloud                                           │
│  (JahQwenAgent — Python)                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP REST (JSON)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  JahMemoryBridge (Python)                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ KeywordExtractor │ TierManager │ ContextBuilder      │    │
│  └─────────────────────────────────────────────────────┘    │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP REST
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  api.php (API Endpoint)                                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  DataCoreTurbo (PHP — Formato Binario)                      │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  [4 bytes length][JSON payload][newline] per record  │   │
│  │  Segmentado por hash (1000 segmentos)                │   │
│  │  Índices .idx para búsqueda O(1)                      │   │
│  └──────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│  MemoryPyramid (PHP — 3 Niveles)                            │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │  hot/    │→ │  warm/   │→ │  cold/   │                  │
│  │  (LRU)   │  │  (ndjson)│  │  (gzip)  │                  │
│  └──────────┘  └──────────┘  └──────────┘                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Estructura del Proyecto

```
.
├── jah-php/                       # Núcleo PHP
│   ├── index.php                  # Punto de entrada (CLI/HTTP)
│   ├── api.php                    # API REST para agentes externos
│   ├── bridge.php                 # Alias hacia api.php
│   ├── migrate_tiers.php          # CLI: migración de tiers
│   ├── cron_tier_migration.php    # Cron: migración automática
│   ├── config/
│   │   ├── config.php             # Configuración global
│   │   ├── database.php           # Configuración MySQL
│   │   └── environment.php        # Helpers de variables de entorno
│   ├── core/
│   │   ├── JahEngine.php          # Motor central (Singleton)
│   │   ├── EventBus.php           # Bus pub/sub
│   │   ├── EventRouter.php        # Enrutador de eventos
│   │   └── Autoloader.php         # PSR-4 autoloader
│   ├── agents/
│   │   ├── BaseAgent.php          # Clase base abstracta
│   │   ├── GatewayAgent.php       # Validación de requests
│   │   ├── MemoryAgent.php        # Persistencia + tiered memory
│   │   ├── ObserverAgent.php      # Monitoreo del sistema
│   │   ├── PredictorAgent.php     # Predicción de carga
│   │   ├── OrchestratorAgent.php  # División de tareas
│   │   ├── NetworkAgent.php       # Cliente HTTP
│   │   ├── WorkerAgent.php        # Workers dinámicos
│   │   ├── CacheAgent.php         # Caché rápido
│   │   ├── ExecutorAgent.php      # Ejecución de comandos
│   │   ├── AnalystAgent.php       # Auditoría
│   │   ├── OptimizerAgent.php     # Optimización
│   │   └── CleanerAgent.php       # Limpieza
│   ├── memory/
│   │   ├── Database.php           # PDO wrapper (MySQL)
│   │   ├── TieredMemory.php       # Sistema de memoria escalonada
│   │   ├── schema.sql             # Esquema MySQL
│   │   ├── datacore/             # Almacenamiento binario DataCore
│   │   │   ├── data/
│   │   │   ├── index/
│   │   │   └── wal/
│   │   ├── pyramid/               # MemoryPyramid (hot/warm/cold)
│   │   │   ├── hot/
│   │   │   ├── warm/
│   │   │   └── cold/
│   │   └── tiers/                 # TieredMemory filesystem
│   │       ├── hot/
│   │       ├── warm/
│   │       └── cold/
│   ├── network/                   # Cliente HTTP (cURL)
│   ├── cache/                     # Caché en archivos
│   ├── logs/                      # Logs del sistema
│   └── tmp/                       # Archivos temporales
├── jah-datacore/                  # Motor NoSQL PHP (DataCore)
│   ├── src/
│   │   ├── DataCoreTurbo.php      # Almacenamiento binario + batch
│   │   ├── DataCoreLightning.php  # Escritura ultra-rápida
│   │   ├── MemoryPyramid.php      # Cache LRU + warm + cold
│   │   ├── StorageAgent.php       # Append-only con índices
│   │   ├── CacheAgent.php         # LRU en RAM (10k entries)
│   │   ├── BufferQueue.php        # Escritura en bloque
│   │   ├── WALTransactionCore.php # Write-Ahead Log ACID
│   │   ├── Compressor.php         # LZ4/ZSTD/GZIP
│   │   ├── IndexAgent.php         # Índices por campo
│   │   ├── Agents.php             # Lock, Event, Transaction, Schema, Integrity
│   │   └── ...
│   ├── tests/
│   └── benchmarks/
├── jah-qwen-bridge/               # Bridge Python
│   ├── jah_bridge/
│   │   └── __init__.py            # JahMemoryBridge + JahQwenAgent
│   ├── seeder.py                  # Inyección masiva de memorias
│   ├── demo.py                    # Demo interactiva
│   ├── setup.py                   # Package setup
│   └── README.md                  # Documentación del bridge
└── README.md                      # Este archivo
```

---

## DataCore — Motor de Almacenamiento Binario

DataCore es un motor NoSQL 100% PHP sin SQL ni dependencias externas.

### Formato Binario

```
Archivo: {collection}_{segment}.bin

Formato de registro:
┌─────────────────┬──────────────────────┬──────────┐
│ 4 bytes (uint32)│ JSON payload         │ \n       │
│ longitud        │                      │ newline  │
└─────────────────┴──────────────────────┴──────────┘
```

### Rendimiento

| Operación | DataCore Lightning | SQLite3 | Factor |
|-----------|-------------------|---------|--------|
| 1k inserts | 0.72 ms (1.4M/s) | 19,922 ms (50/s) | **27,700x** |
| 5k inserts | 4.45 ms (1.1M/s) | 95,356 ms (52/s) | **21,400x** |
| 1k ACID tx | 47 ms | 13,261 ms | **281x** |

### Clases principales

| Clase | Función |
|-------|---------|
| `DataCoreTurbo` | Almacenamiento binario segmentado + batch + mmap |
| `DataCoreLightning` | Escritura máxima velocidad con buffer |
| `MemoryPyramid` | Cache LRU (hot) + ndjson (warm) + gzip (cold) |
| `StorageAgent` | Append-only con índices .idx |
| `WALTransactionCore` | Write-Ahead Log ACID con hash chain |
| `BufferQueue` | Escritura en bloque con flush inteligente |
| `Compressor` | Compresión LZ4/ZSTD/GZIP |

---

## Inicio Rápido

### Requisitos

- PHP 8.0+ con extensiones: `curl`, `pdo_mysql`, `json`
- MySQL/MariaDB (opcional, para persistencia histórica)
- Python 3.10+ (para el bridge)

### 1. Levantar servidor PHP

```bash
cd jah-php
php -S localhost:8000
```

### 2. Verificar estado

```bash
curl http://localhost:8000/api.php
```

### 3. Probar el API

```bash
# Guardar memoria
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "collection": "memories",
    "tier": "hot",
    "data": {
      "id": "test_001",
      "content": "Primera memoria de prueba",
      "tags": ["test", "demo"]
    }
  }'

# Buscar
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "search",
    "collection": "memories",
    "query": "prueba"
  }'

# Estadísticas
curl http://localhost:8000/api.php?action=stats
```

### 4. Python Bridge

```bash
cd jah-qwen-bridge
pip install -e .
python demo.py
```

### 5. Inyección masiva (para demos)

```bash
cd jah-qwen-bridge
python seeder.py inject 1000    # 1,000 memorias
python seeder.py benchmark     # Benchmark de búsqueda
```

---

## API REST (api.php)

### Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api.php?action=status` | Estado del servicio |
| `GET` | `/api.php?action=list&collection=memories` | Listar memorias |
| `GET` | `/api.php?action=stats` | Estadísticas de DataCore |
| `POST` | `/api.php` | Guardar/buscar/eliminar |

### Acciones POST

#### Guardar memoria

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "collection": "memories",
    "tier": "hot",
    "data": {"id": "key_001", "content": "...", "tags": ["a","b"]}
  }'
```

#### Buscar

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "search", "collection": "memories", "query": "PHP async"}'
```

#### Batch insert

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "batch", "collection": "memories", "docs": [{...}, {...}]}'
```

#### Recuperar por ID

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "retrieve", "collection": "memories", "id": "key_001"}'
```

#### Eliminar (soft-delete)

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "delete", "collection": "memories", "id": "key_001"}'
```

#### MemoryPyramid

```bash
# Set en cache LRU
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "pyramid_set", "key": "k1", "value": {...}, "ttl": 3600}'

# Get desde cache
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "pyramid_get", "key": "k1"}'
```

---

## Python Bridge

### Instalación

```bash
cd jah-qwen-bridge
pip install -e .
```

### Uso básico

```python
from jah_bridge import JahMemoryBridge, JahQwenAgent

# Conectar al API PHP
bridge = JahMemoryBridge("http://localhost:8000/api.php")

# Guardar memoria (se almacena en formato binario internamente)
bridge.save_memory("hot", "user_123", {
    "query": "¿Cómo usar PHP Fibers?",
    "response": "Las Fibers permiten concurrencia cooperativa..."
}, tags=["php", "async", "fibers"])

# Buscar
results = bridge.search_memory("PHP Fibers")
for r in results:
    print(f"[{r.tier}] {r.id}: {r.data}")

# Batch insert (alto rendimiento)
docs = [{"id": f"doc_{i}", "content": f"Memory #{i}"} for i in range(1000)]
bridge.batch_save(docs)
```

### Agente completo con Qwen

```python
def call_qwen(prompt: str) -> str:
    import openai
    response = openai.chat.completions.create(
        model="qwen-7b",
        messages=[{"role": "user", "content": prompt}]
    )
    return response.choices[0].message.content

bridge = JahMemoryBridge("http://localhost:8000/api.php")
agent = JahQwenAgent(bridge, llm_callable=call_qwen)

# El agente automáticamente:
# 1. Busca contexto en memoria (DataCore binario)
# 2. Construye el prompt con contexto
# 3. Llama a Qwen
# 4. Guarda la interacción en hot/
result = agent.process("¿Qué sabes sobre PHP async?")
print(result["response"])
```

---

## Agentes del Motor JAH

El motor opera en 12 fases con 11 agentes especializados:

| Fase | Agente | Responsabilidad |
|------|--------|-----------------|
| 1 | GatewayAgent | Validación de requests entrantes |
| 2 | MemoryAgent | Persistencia en MySQL + DataCore + tiered |
| 3 | ObserverAgent | Monitoreo de CPU/RAM/Disco |
| 4 | PredictorAgent | Predicción de carga y estrategias |
| 5 | OrchestratorAgent | División y delegación de tareas |
| 6 | NetworkAgent | Cliente HTTP para APIs externas |
| 7 | WorkerAgent | Workers dinámicos (código, red, archivo) |
| 8 | CacheAgent | Caché rápido con TTL |
| 9 | ExecutorAgent | Ejecución segura de comandos |
| 10 | AnalystAgent | Auditoría de resultados |
| 11 | OptimizerAgent | Recomendaciones de optimización |
| 12 | CleanerAgent | Purga de temporales y caché expirada |

---

## Configuración

Variables de entorno principales:

| Variable | Default | Descripción |
|----------|---------|-------------|
| `JAH_ENV` | `production` | Entorno |
| `JAH_DEBUG` | `false` | Modo debug |
| `JAH_DB_HOST` | `127.0.0.1` | Host MySQL |
| `JAH_DB_NAME` | `jah_motor` | Base de datos |
| `JAH_DB_PASS` | — | Contraseña MySQL |
| `JAH_DATACORE_STORAGE` | `memory/datacore` | Directorio DataCore |
| `JAH_HOT_STORAGE` | `memory/pyramid` | Directorio MemoryPyramid |
| `JAH_LOG_LEVEL` | `warning` | Nivel de log |

---

## Demo para Hackathon

### Mostrar velocidad de recuperación

```bash
# 1. Inyectar 1,000 memorias en formato binario
cd jah-qwen-bridge
python seeder.py inject 1000

# 2. Mostrar estadísticas de DataCore
curl http://localhost:8000/api.php?action=stats

# 3. Benchmark de búsqueda
python seeder.py benchmark
```

### Mostrar ciclo de vida de la memoria

```bash
# Ver datos binarios en DataCore
ls jah-php/memory/datacore/data/

# Forzar migración (hot → warm → cold)
php jah-php/migrate_tiers.php migrate

# Ver estadísticas
php jah-php/migrate_tiers.php stats
```

### Argumento de venta

> "Hemos eliminado el cuello de botella de la base de datos. Nuestro almacenamiento es binario, segmentado por hash, con índices en memoria y compresión LZ4/ZSTD. 27,700x más rápido que SQLite3."

---

## Licencia

MIT
