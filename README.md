# JAH-Qwen Bridge

Agente de IA con memoria estratificada en PHP nativo, conectado a Qwen Cloud vía DashScope International API.

AI agent with stratified memory in native PHP, connected to Qwen Cloud via DashScope International API.

---

## Arquitectura / Architecture

```
public/          → HTTP entry points (agent.php, api.php) / Puntos de entrada HTTP
app/             → PHP code (core, agents, config, QwenConnector, bootstrap)
src/DataCore/    → Binary storage engine (DataCoreTurbo, MemoryPyramid)
runtime/         → Persistent data (gitignored) / Datos persistentes (ignorados)
.env             → API key (gitignored) / Llave API (ignorada)
```

---

## Inicio Rápido / Quick Start

```bash
# 1. Clonar / Clone
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php

# 2. Configurar API key / Configure API key
cp .env.example .env
# Edita .env con tu API key de DashScope / Edit .env with your DashScope API key

# 3. Iniciar servidor / Start server
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
  -d '{"message":"¿Qué información tienes guardada? / What info do you have?"}'
```

---

## API Key / Llave API

La API key se configura en el archivo `.env` / The API key is configured in `.env`:

```
QWEN_API_KEY=sk-your-key-here
QWEN_MODEL=qwen-max
```

**Nunca subas el `.env` a git.** El `.gitignore` ya lo excluye.
**Never commit `.env` to git.** `.gitignore` already excludes it.

---

## Variables de Entorno / Environment Variables

| Variable | Descripción / Description | Default |
|----------|---------------------------|---------|
| `QWEN_API_KEY` | API key de DashScope / DashScope API key | — |
| `QWEN_MODEL` | Modelo de Qwen / Qwen model | `qwen-max` |
| `JAH_TIMEZONE` | Zona horaria / Timezone | `America/Mexico_City` |

---

## Despliegue en Alibaba Cloud / Alibaba Cloud Deployment

### Function Compute
1. Crear servicio en Function Compute / Create Function Compute service
2. Subir proyecto como zip / Upload project as zip
3. Handler: `public/fc_handler.php`
4. Configurar variable `QWEN_API_KEY` / Set `QWEN_API_KEY` environment variable

### ECS
```bash
apt install -y php-cli php-curl php-mbstring php-pdo php-mysql
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php && cp .env.example .env
# Edita .env / Edit .env
php -S 0.0.0.0:8000 -t public
```

---

## Licencia / License

MIT
