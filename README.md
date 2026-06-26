# JAH-Qwen Bridge

Agente de IA con memoria estratificada en PHP nativo, conectado a Qwen Cloud vía DashScope International API.

## Arquitectura

```
public/          → Punto de entrada HTTP (agent.php, api.php)
app/             → Código PHP (core, agents, config, QwenConnector, bootstrap)
src/DataCore/    → Motor de almacenamiento binario (DataCoreTurbo, MemoryPyramid)
runtime/         → Datos persistentes (ignorados por git)
.env             → API key (ignorado por git)
```

## Inicio Rápido

```bash
# 1. Clonar
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php

# 2. Configurar API key
cp .env.example .env
# Edita .env con tu API key de DashScope

# 3. Iniciar servidor
php -S localhost:8000 -t public
```

## Uso

### Guardar memoria
```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"save","collection":"memories","data":{"id":"dato1","content":"Información importante"}}'
```

### Preguntar al agente
```bash
curl -X POST http://localhost:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿Qué información tienes guardada?"}'
```

## API Key

La API key se configura en el archivo `.env`:

```
QWEN_API_KEY=sk-tu-key-real
QWEN_MODEL=qwen-max
```

**Nunca subas el `.env` a git.** El `.gitignore` ya lo excluye.

## Despliegue en Alibaba Cloud

### Function Compute
1. Crear servicio en Function Compute
2. Subir todo el proyecto como zip
3. Handler: `public/fc_handler.php`

### ECS
```bash
apt install -y php-cli php-curl php-mbstring php-pdo php-mysql
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php && cp .env.example .env
php -S 0.0.0.0:8000 -t public
```

## Licencia

MIT
