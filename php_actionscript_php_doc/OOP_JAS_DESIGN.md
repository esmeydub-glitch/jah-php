# OOP Design JAS

## Principios

1. Inmutabilidad: Configuración después de definición no modifica instancia
2. Fibers: Para timeout sin forks
3. Streams: Reactivo a eventos similares a Node.js
4. Event loop: Simple scheduler integrado

## Clases principales

- `Jah\Actions\ActionScript` - Registry de acciones
- `Jah\JasEventEmitter` - Pub/Sub genérico
- `Jah\JasPromise` - Promesas con then/catch
- `Jah\JasStream` - Streams con pipe/map/filter/reduce