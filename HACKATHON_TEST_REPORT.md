# JAH MemoryAgent — Hackathon Test Report

Estado final del demo:

```text
Status: PASS
Guardar HOT: PASS
Guardar WARM: PASS
Guardar COLD: PASS
Buscar HTML: PASS
Buscar API: PASS
Stats HTML: PASS
Stats API: PASS
SALK API: PASS
SALK HTML: PASS
ActionScript tests: 7/7 PASS
PHP lint: PASS
```

## Comandos correctos

Iniciar servidor:

```bash
php -S localhost:8000 -t public
```

Status:

```bash
curl "http://localhost:8000/api.php?action=status"
```

Guardar HOT:

```bash
curl "http://localhost:8000/api.php?action=save&id=nombre&content=Te%20llamas%20Juan&tier=hot"
```

Guardar WARM:

```bash
curl "http://localhost:8000/api.php?action=save&id=proyecto&content=Proyecto%20JAH%20MemoryAgent&tier=warm"
```

Guardar COLD:

```bash
curl "http://localhost:8000/api.php?action=save&id=hackathon&content=Hackathon%20PHP%20puro&tier=cold"
```

Buscar:

```bash
curl "http://localhost:8000/api.php?action=search&query=Juan"
```

Stats:

```bash
curl "http://localhost:8000/api.php?action=stats"
```

SALK:

```bash
curl "http://localhost:8000/api.php?action=salk_status"
```

Interfaz:

```text
http://localhost:8000/index.php
http://localhost:8000/index.php?action=search&query=Juan
http://localhost:8000/index.php?action=stats
http://localhost:8000/index.php?action=salk_status
```

## Nota

La salida pública del endpoint es `text/plain` en formato JAH_RESPONSE.
La conexión especial con Qwen queda concentrada en `app/QwenConnector.php`.
