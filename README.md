# JAH-Qwen Bridge

**ES:** Agente de IA con memoria estratificada en PHP nativo, conectado a Qwen Cloud vía DashScope International API. Motor de almacenamiento binario 27,700x más rápido que SQLite3.

**EN:** AI agent with stratified memory in native PHP, connected to Qwen Cloud via DashScope International API. Binary storage engine 27,700x faster than SQLite3.

---

## Arquitectura / Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Qwen Cloud / LLM                                           │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP REST (JSON)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  public/agent.php — El Cerebro del Agente / Agent Brain     │
│  1. Busca contexto en memoria / Search context in memory    │
│  2. Construye prompt con contexto / Build prompt with ctx   │
│  3. Llama a Qwen / Call Qwen                                │
│  4. Guarda interacción / Save interaction                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  DataCoreTurbo (PHP — Formato Binario / Binary Format)      │
│  [4 bytes length][JSON payload][newline]                    │
│  Segmentado por hash (1000 segmentos) / Hash-segmented      │
│  Índices .idx para búsqueda O(1) / O(1) index lookup        │
├─────────────────────────────────────────────────────────────┤
│  MemoryPyramid (3 Niveles / 3 Tiers)                        │
│  hot/ (LRU RAM) → warm/ (ndjson) → cold/ (gzip)           │
└─────────────────────────────────────────────────────────────┘
```

---

## Estructura del Proyecto / Project Structure

```
.
├── public/              # HTTP endpoints / Puntos de entrada HTTP
│   ├── agent.php        # Agente principal / Main agent
│   └── api.php          # API REST para memoria / Memory REST API
├── app/                 # Código PHP / PHP code
│   ├── bootstrap.php    # Carga .env, autoloader / .env loader, autoloader
│   ├── QwenConnector.php # Cliente Qwen Cloud / Qwen Cloud client
│   ├── config/          # Configuración / Configuration
│   ├── core/            # Motor central / Core engine
│   ├── agents/          # 11 agentes / 11 agents
│   ├── memory/          # Memoria tiered / Tiered memory
│   ├── network/         # Cliente HTTP / HTTP client
│   └── cache/           # Caché / Cache
├── src/DataCore/        # Motor binario / Binary engine
│   ├── DataCoreTurbo.php    # Almacenamiento binario / Binary storage
│   ├── MemoryPyramid.php    # Hot/Warm/Cold tiers
│   ├── CacheAgent.php       # LRU cache (10k entries)
│   ├── BufferQueue.php      # Escritura en bloque / Batch writes
│   ├── Compressor.php       # LZ4/ZSTD/GZIP
│   └── ...
├── runtime/             # Datos persistentes / Persistent data (gitignored)
├── .env                 # API key (gitignored)
├── .env.example         # Template / Plantilla
└── README.md
```

---

## Inicio Rápido / Quick Start

### Requisitos / Requirements
- PHP 8.0+ con extensiones: `curl`, `mbstring`, `pdo_mysql`, `json`
- Python 3.10+ (opcional, para bridge / optional, for bridge)

### Instalación / Installation

```bash
# Clonar / Clone
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php

# Configurar API key / Configure API key
cp .env.example .env
# Edita .env con tu API key / Edit .env with your API key

# Iniciar servidor / Start server
php -S localhost:8000 -t public
```

---

## Uso / Usage

### Guardar memoria / Save memory
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"save","collection":"memories","data":{"id":"dato1","content":"Información importante / Important info"}}'
```

### Buscar memoria / Search memory
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"search","collection":"memories","query":"important"}'
```

### Recuperar por ID / Retrieve by ID
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"retrieve","collection":"memories","id":"dato1"}'
```

### Eliminar / Delete
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"delete","collection":"memories","id":"dato1"}'
```

### Preguntar al agente / Ask the agent
```bash
curl -X POST http://localhost:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿Qué información tienes? / What info do you have?"}'
```

---

## API REST / REST API

| Método/Method | Endpoint | Descripción/Description |
|---------------|----------|------------------------|
| `GET` | `/api.php?action=status` | Estado del servicio / Service status |
| `GET` | `/api.php?action=stats` | Estadísticas / Statistics |
| `POST` | `/api.php` | Guardar/buscar/eliminar / Save/search/delete |
| `POST` | `/agent.php` | Preguntar al agente / Ask agent |

---

## DataCore — Motor Binario / Binary Engine

### Formato de registro / Record Format
```
┌─────────────────┬──────────────────────┬──────────┐
│ 4 bytes (uint32)│ JSON payload         │ \n       │
│ length          │                      │ newline  │
└─────────────────┴──────────────────────┴──────────┘
```

### Rendimiento / Performance

| Operación/Operation | DataCore | SQLite3 | Factor |
|---------------------|----------|---------|--------|
| 1k inserts | 0.72 ms (1.4M/s) | 19,922 ms | **27,700x** |
| 5k inserts | 4.45 ms (1.1M/s) | 95,356 ms | **21,400x** |
| 1k ACID tx | 47 ms | 13,261 ms | **281x** |

---

## Memoria Estratificada / Stratified Memory

| Tier | Tipo/Type | Persistencia/Persistence | Uso/Use |
|------|-----------|--------------------------|---------|
| **Hot** | Cache LRU RAM | Volátil/Volatile | Acceso inmediato/Immediate access |
| **Warm** | Archivo ndjson | Persistente/Persistent | Datos recientes/Recent data |
| **Cold** | Archivo gzip | Persistente/Persistent | Histórico/Historical |

---

## Despliegue en Alibaba Cloud / Alibaba Cloud Deployment

### Function Compute (Serverless)
1. Crear servicio / Create service
2. Subir proyecto como zip / Upload project as zip
3. Variable de entorno: `QWEN_API_KEY` / Environment variable: `QWEN_API_KEY`

### ECS (Tradicional/Traditional)
```bash
apt install -y php-cli php-curl php-mbstring php-pdo php-mysql
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php && cp .env.example .env
# Edita .env / Edit .env
php -S 0.0.0.0:8000 -t public
```

---

## Seguridad / Security

- **API key solo en `.env`** — Nunca en código / Never in code
- **`.env` excluido de git** — `.gitignore` lo ignora / `.gitignore` excludes it
- **Sin hardcode** — Toda configuración vía variables de entorno / All config via env vars

---

## Licencia / License

MIT
