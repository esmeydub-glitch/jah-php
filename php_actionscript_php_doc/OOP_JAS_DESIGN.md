# OOP Design JAS

## Principios

1. Registro declarativo: nombre, parámetros requeridos, presupuesto y handler PHP
2. Fibers: cooperación para acciones que suspenden voluntariamente
3. Presupuesto medido: una operación bloqueante tardía conserva su resultado y reporta `budget_exceeded`
4. Streams: procesamiento reactivo con iteradores PHP puros
5. Envolvente uniforme: éxito, resultado, duración, warning o error

## Clases principales

- `Jah\Actions\ActionScript` - Registry de acciones
- `Jah\JasEventEmitter` - Pub/Sub genérico
- `Jah\JasPromise` - Promesas con then/catch
- `Jah\JasStream` - Streams con pipe/map/filter/reduce

La arquitectura usada por MemoryAgent está documentada en
[`../ACTIONSCRIPT_ARCHITECTURE.md`](../ACTIONSCRIPT_ARCHITECTURE.md).
