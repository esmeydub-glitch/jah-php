# JAH MemoryAgent — Qwen Only External Connection

Modo final para hackathon:

```text
PHP puro
ActionScript PHP JAH
DataCore con serialización PHP/JAH
SALK audit en .jahl
Salida pública text/plain con var_export()
QwenConnector como única conexión externa especial
```

## Flujo activo

```text
public/index.php / public/agent.php / public/api.php
        ↓
app/actions/MemoryActionScript.php
        ↓
src/DataCore/
        ↓
app/QwenConnector.php
        ↓
Qwen Cloud
```

## Reglas

```text
No Node
No npm
No package runtime
No acciones internas en formatos externos
No configuración interna en formatos externos
No secretos en respuestas públicas
QWEN_API_KEY solo en header Authorization dentro de QwenConnector
```

## Archivos clave

```text
app/actions/MemoryActionScript.php
app/actions/SalkSecurityActionScript.php
app/security/SalkGuard.php
app/http/JahTransport.php
app/QwenConnector.php
src/DataCore/PhpSerializer.php
public/index.php
public/agent.php
public/api.php
```
