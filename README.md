# JAH-Qwen Bridge

**ES:** MemoryAgent en PHP puro con ActionScript PHP, DataCoreTurbo, MemoryPyramid y Qwen Cloud vía cURL nativo. Diseñado para el track **MemoryAgent** del Global AI Hackathon Series with Qwen Cloud.

**EN:** Pure PHP MemoryAgent with ActionScript PHP, DataCoreTurbo, MemoryPyramid, and Qwen Cloud through native PHP cURL. Built for the **MemoryAgent** track of the Global AI Hackathon Series with Qwen Cloud.

---

## Arquitectura / Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Usuario / User                                             │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP POST / HTML form
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  public/index.php + public/agent.php                        │
│  Interfaz oficial / Official MemoryAgent interface          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  ActionScript PHP Runtime                                   │
│  memory.search_context → memory.build_context               │
│  qwen.ask → memory.store_interaction                        │
│  Controla eventos, estado y ciclo de memoria                │
└──────────────────────┬──────────────────────────────────────┘
                       │
       ┌───────────────┴────────────────┐
       ▼                                ▼
┌─────────────────────────────┐   ┌───────────────────────────┐
│ DataCoreTurbo               │   │ MemoryPyramid             │
│ PHP binary storage          │   │ Hot / Warm / Cold         │
│ [4 bytes][JSON][newline]    │   │ hot → warm → cold         │
│ hash segments + .idx offset │   │ timely forgetting         │
└──────────────┬──────────────┘   └──────────────┬────────────┘
               │                                 │
               └───────────────┬─────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  Qwen Cloud / DashScope Intl Compatible API                 │
│  app/QwenConnector.php — PHP cURL nativo                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Estructura del Proyecto / Project Structure

```
.
├── public/                  # HTTP endpoints / Puntos de entrada HTTP
│   ├── index.php            # Interfaz oficial / Official interface
│   ├── agent.php            # Agente principal / Main MemoryAgent endpoint
│   └── api.php              # API REST de memoria / Memory REST API
├── app/                     # Runtime PHP puro / Pure PHP runtime
│   ├── bootstrap.php        # Carga .env, autoloader / .env loader, autoloader
│   ├── QwenConnector.php    # Cliente Qwen Cloud por cURL / Qwen cURL client
│   ├── actions/             # ActionScript PHP runtime actions
│   ├── config/              # Configuración por env / Env-based configuration
│   ├── core/                # Motor central / Core engine
│   ├── agents/              # Agentes internos / Internal agents
│   ├── memory/              # TieredMemory / Memoria estratificada
│   ├── network/             # Cliente HTTP PHP / PHP HTTP client
│   └── cache/               # Caché PHP / PHP cache
├── src/DataCore/            # Motor binario / Binary engine
│   ├── DataCoreTurbo.php    # Almacenamiento binario / Binary storage
│   ├── MemoryPyramid.php    # Hot/Warm/Cold tiers
│   ├── CacheAgent.php       # LRU cache
│   ├── BufferQueue.php      # Escritura en bloque / Batch writes
│   ├── Compressor.php       # GZIP fallback compression
│   └── ...
├── php_actionscript_php_doc/ # Núcleo ActionScript PHP y pruebas / ActionScript PHP core
├── jah-datacore/            # Paquete original DataCore / Original DataCore package
├── runtime/                 # Datos persistentes / Persistent data (gitignored)
├── .env.example             # Plantilla / Template
└── README.md
```

---

## Inicio Rápido / Quick Start

### Requisitos / Requirements
- PHP 8.1+
- Extensión PHP `curl` para Qwen Cloud / PHP `curl` extension for Qwen Cloud
- Extensión PHP `json` estándar / standard PHP `json` extension
- Sin Python / No Python runtime
- Sin Node.js / No Node.js
- Sin base de datos externa para el núcleo MemoryAgent / No external database required for the MemoryAgent core

### Instalación / Installation

```bash
# Clonar / Clone
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php

# Configurar API key / Configure API key
cp .env.example .env
# Edita .env con tu QWEN_API_KEY / Edit .env with your QWEN_API_KEY

# Iniciar servidor / Start server
php -S localhost:8000 -t public
```

---

## Uso / Usage

### Guardar memoria / Save memory
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"save","collection":"memories","data":{"id":"dato1","content":"Mi proyecto se llama Jah-PHP","tags":["project"]},"tier":"hot"}'
```

### Buscar memoria / Search memory
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"search","collection":"memories","query":"Jah-PHP"}'
```

### Recuperar por ID / Retrieve by ID
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"retrieve","collection":"memories","id":"dato1"}'
```

### Eliminar / Forget
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"forget","collection":"memories","id":"dato1"}'
```

### Preguntar al agente / Ask the agent
```bash
curl -X POST http://localhost:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿Qué recuerdas de mi proyecto?","collection":"memories"}'
```

---

## API REST / REST API

| Método/Method | Endpoint | Descripción/Description |
|---------------|----------|------------------------|
| `GET` | `/api.php?action=status` | Estado del servicio / Service status |
| `GET` | `/api.php?action=stats` | Estadísticas / Statistics |
| `POST` | `/api.php` | Guardar, buscar, recuperar, olvidar, migrar / Save, search, retrieve, forget, migrate |
| `POST` | `/agent.php` | Ejecutar MemoryAgent con Qwen Cloud / Run MemoryAgent with Qwen Cloud |
| `GET/POST` | `/index.php` | Interfaz web PHP puro / Pure PHP web interface |

---

## DataCore — Motor Binario / Binary Engine

### Formato de registro / Record Format
```
┌─────────────────┬──────────────────────┬──────────┐
│ 4 bytes (uint32)│ JSON payload          │ \n       │
│ length          │                      │ newline  │
└─────────────────┴──────────────────────┴──────────┘
```

### Índice / Index

```
id:segment:offset:timestamp
```

DataCoreTurbo usa segmentos por hash e índices `.idx` con offset para recuperación directa por ID. Las operaciones de búsqueda contextual recorren la colección para rankear memorias relevantes, mientras que la recuperación por ID usa el índice.

DataCoreTurbo uses hash segments and `.idx` files with offsets for direct ID retrieval. Context search scans the collection to rank relevant memories, while ID retrieval uses the index.

### Rendimiento / Performance

| Operación/Operation | Enfoque / Approach |
|---------------------|--------------------|
| Escritura / Write | Append-only binary records |
| Recuperación por ID / Retrieve by ID | Hash segment + `.idx` offset |
| Búsqueda contextual / Context search | Collection scan + memory filtering |
| Olvido / Forgetting | Tombstone record + latest-state resolution |

Los benchmarks deben ejecutarse en el hardware de despliegue antes de declarar números finales.

Benchmarks should be run on the deployment hardware before publishing final numbers.

---

## Memoria Estratificada / Stratified Memory

| Tier | Tipo/Type | Persistencia/Persistence | Uso/Use |
|------|-----------|--------------------------|---------|
| **Hot** | Cache LRU + write-through | Activa por request + persistencia binaria | Memoria reciente / Recent memory |
| **Warm** | Archivo ndjson | Persistente/Persistent | Datos recientes de medio plazo / Mid-term memory |
| **Cold** | Archivo gzip | Persistente/Persistent | Histórico comprimido / Compressed history |

MemoryPyramid implementa movimiento Hot → Warm → Cold para apoyar el “olvido oportuno” requerido por MemoryAgent.

MemoryPyramid implements Hot → Warm → Cold movement to support the timely forgetting required by MemoryAgent.

---

## MemoryAgent Track / Track MemoryAgent

JAH-Qwen Bridge está enfocado en el track **MemoryAgent**:

- Memoria persistente entre sesiones / Persistent cross-session memory
- Preferencias recordadas por DataCoreTurbo / User preferences stored by DataCoreTurbo
- Recuperación selectiva antes de llamar a Qwen / Selective retrieval before calling Qwen
- Contexto limitado enviado a Qwen / Limited context sent to Qwen
- Olvido por tombstones y migración Hot/Warm/Cold / Forgetting through tombstones and Hot/Warm/Cold migration
- ActionScript PHP como orquestador de eventos y estado / ActionScript PHP as event and state orchestrator

---

## Despliegue en Alibaba Cloud / Alibaba Cloud Deployment

### Function Compute (Serverless)
1. Crear servicio / Create service
2. Subir proyecto como zip / Upload project as zip
3. Configurar variable de entorno: `QWEN_API_KEY`
4. Usar `public/agent.php` o `public/api.php` como entrada HTTP

### ECS (Tradicional/Traditional)
```bash
apt update
apt install -y php-cli php-curl
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php && cp .env.example .env
# Edita .env / Edit .env
php -S 0.0.0.0:8000 -t public
```

---

## Seguridad / Security

- **API key solo en `.env` o variable de entorno** — Nunca hardcodeada / Never hardcoded
- **`.env` excluido de git** — `.gitignore` lo ignora / `.gitignore` excludes it
- **Sin Python, Node.js ni DB externa en el runtime principal** / No Python, Node.js, or external DB in the main runtime
- **Configuración vía variables de entorno** / Configuration through environment variables

---

## Licencia / License

MIT
