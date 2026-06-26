# Pruebas del Sistema JAH-Qwen Bridge

## Resultados de Pruebas (2026-06-25)

### Prueba 1: Guardar Memoria
```bash
curl -s -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"save","collection":"memories","tier":"hot","data":{"id":"color_pref","content":"Al usuario le gusta programar en color oscuro, tema Dracula","tags":["preference","color","dark"]}}'
```
**Resultado:** `{"status":"success","message":"Memory stored in hot","id":"color_pref","collection":"memories"}`

### Prueba 2: Buscar Memoria
```bash
curl -s -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"search","collection":"memories","query":"oscuro"}'
```
**Resultado:** `{"status":"success","query":"oscuro","count":1,"data":[{"id":"color_pref","content":"Al usuario le gusta programar en color oscuro, tema Dracula","tags":["preference","color","dark"],"_ts":1782448000,"_tier":"hot"}]}`

### Prueba 3: Agente con Qwen (Contexto Recuperado)
```bash
curl -s -X POST http://localhost:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿De qué color me gusta programar?"}'
```
**Resultado:**
```json
{
  "status": "success",
  "response": "Te gusta programar con un tema de color oscuro, específicamente el tema Dracula. Este es popular por su estética distintiva y por ser fácil para los ojos, especialmente en entornos con poca luz.",
  "context_used": 2,
  "model": "qwen-max",
  "memory_ids": {
    "user": "user_24e777d5a9ca",
    "assistant": "assistant_1b8d9a466f7d"
  }
}
```

### Prueba 4: Agente con Qwen (Sin Contexto Previo)
```bash
curl -s -X POST http://localhost:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿Qué opinas del hackathon?"}'
```
**Resultado:**
```json
{
  "status": "success",
  "response": "Los hackathons son eventos muy interesantes y beneficiosos por varias razones: Fomentan la creatividad e innovación...",
  "context_used": 0,
  "model": "qwen-max"
}
```

## Resumen de Resultados

| Prueba | Estado | Detalle |
|--------|--------|---------|
| Guardar memoria | ✅ OK | Almacenado en DataCore (formato binario) |
| Buscar memoria | ✅ OK | Búsqueda full-text funciona |
| Agente con contexto | ✅ OK | Qwen responde usando memoria recuperada |
| Agente sin contexto | ✅ OK | Qwen responde con conocimiento general |

## Problemas Encontrados y Solucionados

### Problema 1: API Key no cargaba vía HTTP
**Síntoma:** `QWEN_API_KEY not configured` aunque el archivo existía.
**Causa:** `getenv()` retorna `false` (booleano) cuando la variable no existe, y `false !== ''` es `true`, causando que el operador ternario seleccionara `false`.
**Solución:** Agregar verificación `if ($envApiKey === false) { $envApiKey = ''; }`

### Problema 2: Error 500 en agent.php
**Síntoma:** Internal Server Error al llamar agent.php
**Causa:** `JahEngine::getInstance()->boot()` intentaba cargar agentes que no estaban incluidos.
**Solución:** Eliminar dependencia de JahEngine, usar DataCoreTurbo directamente.

### Problema 3: Conexión a Qwen Cloud
**Síntomo:** No conexión con api.qwenlm.ai
**Causa:** El endpoint correcto es `dashscope-intl.aliyuncs.com/compatible-mode/v1`
**Solución:** Actualizar QwenConnector.php con el endpoint correcto

## Configuración de Producción

```bash
# 1. Clonar repositorio
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php

# 2. Configurar API Key
export QWEN_API_KEY="tu-key-real"

# 3. Iniciar servidor
php -S localhost:8000 -t jah-php

# 4. Probar
curl -X POST http://localhost:8000/agent.php \
  -d '{"message":"Hola"}'
```
