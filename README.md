# JAH-PHP — Motor de Agentes PHP con Memoria Escalonada

**JAH-PHP** es un framework de agentes de IA basado en PHP con un sistema de memoria tiered (caliente/tibia/fría) y una API REST para integración con LLMs como Qwen.

---

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│  Qwen / LLM Cloud                                           │
│  (JahQwenAgent — Python)                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP REST
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
│  bridge.php (API Endpoint)                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  TieredMemory (PHP Filesystem)                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │  hot/    │→ │  warm/   │→ │  cold/   │                  │
│  │  (1h)    │  │  (24h)   │  │  (7d)    │                  │
│  └──────────┘  └──────────┘  └──────────┘                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Estructura del Proyecto

```
.
├── jah-php/                       # Núcleo PHP
│   ├── index.php                  # Punto de entrada (CLI/HTTP)
│   ├── bridge.php                 # API REST para agentes externos
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
│   │   └── tiers/                 # Almacenamiento físico
│   │       ├── hot/
│   │       ├── warm/
│   │       └── cold/
│   ├── network/                   # Cliente HTTP (cURL)
│   ├── cache/                     # Caché en archivos
│   ├── logs/                      # Logs del sistema
│   └── tmp/                       # Archivos temporales
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

## Inicio Rápido

### Requisitos

- PHP 8.0+
- Extensiones: `curl`, `pdo_mysql`, `json`
- MySQL/MariaDB (opcional, para persistencia histórica)
- Python 3.10+ (para el bridge)

### 1. Configurar entorno

```bash
cd jah-php

# Configurar credenciales (opcional)
export JAH_DB_HOST=localhost
export JAH_DB_NAME=jah_motor
export JAH_DB_USER=root
export JAH_DB_PASS=tu_password
```

### 2. Inicializar base de datos (opcional)

```bash
mysql -u root -p < jah-php/memory/schema.sql
```

### 3. Levantar servidor PHP

```bash
cd jah-php
php -S localhost:8000
```

### 4. Verificar estado

```bash
curl http://localhost:8000/
```

### 5. Probar el bridge (Python)

```bash
cd jah-qwen-bridge
pip install -e .
python demo.py
```

---

## Sistema de Memoria Escalonada

El sistema de memoria de JAH organiza los datos en tres tiers según su "temperatura" (frecuencia de acceso y antigüedad):

| Tier | TTL | Capacidad | Uso |
|------|-----|-----------|-----|
| **hot** | 1 hora | 1,000 archivos | Acceso inmediato, datos de sesión |
| **warm** | 24 horas | 5,000 archivos | Datos recientes, contexto de trabajo |
| **cold** | 7 días | 50,000 archivos | Histórico, archivo a largo plazo |

### Migración automática

Los datos migran automáticamente entre tiers según su TTL:

```
Nuevo dato → hot/ (0-1h) → warm/ (1-24h) → cold/ (24h-7d) → eliminado
```

Configurar TTL en `config/config.php`:

```php
'tiered_memory_config' => [
    'hot' => ['ttl' => 3600, 'max_files' => 1000],
    'warm' => ['ttl' => 86400, 'max_files' => 5000],
    'cold' => ['ttl' => 604800, 'max_files' => 50000],
],
```

### Ejecutar migración manualmente

```bash
# CLI
php jah-php/migrate_tiers.php migrate

# Cron (cada 5 minutos)
*/5 * * * * php /path/to/jah-php/cron_tier_migration.php
```

---

## API REST (bridge.php)

### Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/bridge.php?action=status` | Estado del servicio |
| `GET` | `/bridge.php?action=list&tier=hot` | Listar memorias |
| `GET` | `/bridge.php?action=stats` | Estadísticas de tiers |
| `POST` | `/bridge.php` | Guardar/buscar/eliminar |

### Acciones POST

#### Guardar memoria

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "save",
    "tier": "hot",
    "key": "user_123_preference",
    "data": {"language": "PHP", "level": "expert"},
    "tags": ["user", "preference", "php"]
  }'
```

#### Buscar memoria

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "search",
    "query": "PHP async patterns",
    "tiers": ["hot", "warm"],
    "limit": 10
  }'
```

#### Buscar por tags

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "tags",
    "tags": ["php", "async"],
    "tiers": ["hot", "warm", "cold"]
  }'
```

#### Recuperar por clave

```bash
curl "http://localhost:8000/bridge.php?action=retrieve&tier=hot&key=user_123_preference"
```

#### Eliminar

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{"action": "delete", "key": "user_123_preference"}'
```

#### Mover entre tiers

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{"action": "move", "key": "user_123_preference", "to_tier": "warm"}'
```

#### Forzar migración

```bash
curl -X POST http://localhost:8000/bridge.php \
  -H "Content-Type: application/json" \
  -d '{"action": "migrate"}'
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
bridge = JahMemoryBridge("http://localhost:8000/bridge.php")

# Guardar memoria
bridge.save_memory("hot", "session_001", {
    "query": "¿Cómo usar PHP Fibers?",
    "response": "Las Fibers permiten concurrencia cooperativa..."
}, tags=["php", "async", "fibers"])

# Buscar memoria
results = bridge.search_memory("PHP Fibers", tiers=["hot", "warm"])
for r in results:
    print(f"[{r.tier}] {r.key} (score: {r.score}): {r.data}")
```

### Agente completo con Qwen

```python
from jah_bridge import JahMemoryBridge, JahQwenAgent

def call_qwen(prompt: str) -> str:
    # Tu implementación de llamada a Qwen
    import openai
    response = openai.chat.completions.create(
        model="qwen-7b",
        messages=[{"role": "user", "content": prompt}]
    )
    return response.choices[0].message.content

bridge = JahMemoryBridge("http://localhost:8000/bridge.php")
agent = JahQwenAgent(bridge, llm_callable=call_qwen)

# El agente automáticamente:
# 1. Busca contexto en memoria
# 2. Construye el prompt con contexto
# 3. Llama a Qwen
# 4. Guarda la interacción en hot/
result = agent.process("¿Qué sabes sobre PHP async?")
print(result["response"])
```

### Inyección masiva (para demos)

```bash
# Inyectar 1000 memorias de prueba
python seeder.py inject 1000

# Benchmark de búsqueda
python seeder.py benchmark

# Ver estadísticas
python seeder.py stats
```

---

## Agentes del Motor JAH

El motor opera en 12 fases con 11 agentes especializados:

| Fase | Agente | Responsabilidad |
|------|--------|-----------------|
| 1 | GatewayAgent | Validación de requests entrantes |
| 2 | MemoryAgent | Persistencia en MySQL + tiered memory |
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

### Flujo de ejecución

```
Evento (HTTP/CLI)
    │
    ▼
[GatewayAgent] → Valida estructura
    │
    ▼
[ObserverAgent] → Evalúa recursos del sistema
    │
    ▼
[PredictorAgent] → Determina estrategia óptima
    │
    ▼
[OrchestratorAgent] → Divide en subtareas
    │
    ▼
[WorkerAgent] → Ejecuta tareas en paralelo
    │
    ▼
[AnalystAgent] → Audita resultados
    │
    ▼
[MemoryAgent] → Persiste en MySQL + tiered
    │
    ▼
[OptimizerAgent] → Genera recomendaciones
    │
    ▼
[CleanerAgent] → Limpia temporales
```

---

## Configuración

Variables de entorno principales:

| Variable | Default | Descripción |
|----------|---------|-------------|
| `JAH_ENV` | `production` | Entorno (production/development) |
| `JAH_DEBUG` | `false` | Modo debug |
| `JAH_DB_HOST` | `127.0.0.1` | Host MySQL |
| `JAH_DB_NAME` | `jah_motor` | Nombre de la base de datos |
| `JAH_DB_USER` | `root` | Usuario MySQL |
| `JAH_DB_PASS` | — | Contraseña MySQL (requerida) |
| `JAH_LOG_LEVEL` | `warning` | Nivel de log |
| `JAH_TIERED_MEMORY_DIR` | `memory/tiers` | Directorio de memoria |
| `JAH_MAX_WORKERS` | `4` | Workers concurrentes |

---

## Demo para Hackathon

### Mostrar velocidad de recuperación

```bash
# 1. Inyectar 1,000 memorias
cd jah-qwen-bridge
python seeder.py inject 1000

# 2. Mostrar estadísticas
python seeder.py stats

# 3. Benchmark de búsqueda (comparar con SQL)
python seeder.py benchmark
```

### Mostrar ciclo de vida de la memoria

```bash
# Ver datos en hot/
ls jah-php/memory/tiers/hot/

# Forzar migración (hot → warm → cold)
php jah-php/migrate_tiers.php migrate

# Ver datos migrados
ls jah-php/memory/tiers/warm/
```

### Argumento de venta

> "Hemos eliminado el cuello de botella de la base de datos. Nuestra memoria es el sistema de archivos, gestionado por PHP, orquestado por Qwen."

---

## Licencia

MIT
