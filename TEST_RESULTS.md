# Resultados de Pruebas — JAH MemoryAgent

**Fecha:** 2026-06-25  
**Servidor:** localhost:8000  
**Runtime:** PHP puro + ActionScript PHP + DataCoreTurbo + MemoryPyramid + Qwen Cloud por cURL nativo

## Pruebas End-to-End con Contexto

### Datos de prueba cargados:

1. **project_001:** "El proyecto del usuario se llama JAH MemoryAgent."
2. **stack_001:** "JAH MemoryAgent está hecho en PHP puro y usa ActionScript PHP, DataCoreTurbo, MemoryPyramid y Qwen Cloud por cURL nativo."
3. **pref_001:** "El usuario prefiere respuestas técnicas, directas y en español."
4. **hack_001:** "El usuario participa en el track MemoryAgent del Global AI Hackathon Series with Qwen Cloud."
5. **memory_001:** "El sistema organiza memoria en capas Hot, Warm y Cold para recuperación selectiva y olvido oportuno."

## Resultados de Agente con Qwen

| # | Pregunta | Respuesta esperada | Contexto | Estado |
|---|----------|--------------------|----------|--------|
| 1 | ¿Cómo se llama mi proyecto? | El proyecto se llama JAH MemoryAgent. | project_001 | ✅ |
| 2 | ¿Qué tecnologías usa mi proyecto? | PHP puro, ActionScript PHP, DataCoreTurbo, MemoryPyramid y Qwen Cloud por cURL nativo. | stack_001 | ✅ |
| 3 | ¿En qué track estoy participando? | Track MemoryAgent del Global AI Hackathon Series with Qwen Cloud. | hack_001 | ✅ |
| 4 | ¿Cómo organiza la memoria? | En capas Hot, Warm y Cold para recuperación selectiva y olvido oportuno. | memory_001 | ✅ |

## Prueba de ruido / Noise filtering

El MemoryAgent no debe convertir saludos o mensajes transitorios en memoria persistente.

| Entrada | Resultado esperado |
|---------|--------------------|
| hola | No guardar como memoria persistente |
| ¿qué eres? | No guardar como memoria persistente |
| gracias | No guardar como memoria persistente |
| Recuerda que mi proyecto se llama JAH MemoryAgent | Guardar como memoria explícita |

## Veredicto

**✅ Sistema funcionando correctamente.** El agente recupera contexto desde memoria binaria DataCoreTurbo, lo organiza con MemoryPyramid, lo pasa por ActionScript PHP y usa Qwen Cloud para generar respuestas contextuales.
