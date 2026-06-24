# Motor PHP JAH 🧠🤖

El **Motor PHP JAH** es un cerebro web y enrutador de eventos distribuido y desacoplado, estructurado en **12 Fases** de operación asistidas por **11 Agentes Especializados**. 

PHP se desempeña aquí como la puerta de enlace, control y enrutamiento rápido, mientras que la base de datos MySQL provee memoria persistente e histórico de decisiones para optimizar el rendimiento.

---

## 📂 Estructura del Proyecto

El proyecto está organizado siguiendo el estándar PSR-4:

```text
jah php/
├── index.php                    # Punto de entrada principal (CLI / HTTP)
├── config/
│   ├── config.php               # Configuración global del motor
│   └── database.php             # Configuración de base de datos MySQL/MariaDB
├── core/
│   ├── JahEngine.php            # Motor central (Bootstrap, Singleton, Logs)
│   ├── EventBus.php             # Bus interno de publicación/suscripción
│   ├── EventRouter.php          # Enrutador de eventos por patrón a agentes
│   └── Autoloader.php           # Autocargador manual PSR-4
├── agents/
│   ├── BaseAgent.php            # Interfaz base y ciclo de vida de los agentes
│   ├── GatewayAgent.php         # FASE 1 — Validación de datos externos
│   ├── MemoryAgent.php          # FASE 2 — Persistencia y consulta en MySQL
│   ├── ObserverAgent.php        # FASE 3 — Monitoreo de CPU/RAM/Disco
│   ├── PredictorAgent.php       # FASE 4 — Predicción de carga y estrategias
│   ├── OrchestratorAgent.php    # FASE 5 — Fraccionador y delegador de tareas
│   ├── NetworkAgent.php         # FASE 6 — HTTP Client y APIs
│   ├── WorkerAgent.php          # FASE 7 — Workers dinámicos (Código, Red, Archivo)
│   ├── CacheAgent.php           # FASE 8 — Persistencia rápida/temporal
│   ├── ExecutorAgent.php        # FASE 9 — Ejecución segura de comandos de sistema
│   ├── AnalystAgent.php         # FASE 10 — Auditoría de resultados
│   ├── OptimizerAgent.php       # FASE 11 — Recomendaciones de optimización
│   └── CleanerAgent.php         # FASE 12 — Purga de basura, zombis y temporales
├── memory/
│   ├── Database.php             # PDO Wrapper Singleton
│   └── schema.sql               # Esquema de base de datos MySQL
├── network/
│   └── HttpClient.php           # Envoltorio cURL HTTP para llamadas de API
├── cache/
│   └── CacheManager.php         # Gestor de caché local basado en archivos PHP con TTL
├── logs/                        # Logs de eventos del sistema (.log)
└── tmp/                         # Espacio temporal del sistema
```

---

## ⚡ Flujo de Ejecución Completo

Cuando un evento ingresa al motor, sigue la siguiente ruta:

```text
       Evento Entrante (HTTP / CLI)
                   │
                   ▼
  [FASE 1] GatewayAgent (Valida datos)
                   │
                   ▼
  [FASE 3] ObserverAgent (Evalúa estado del sistema)
                   │
                   ▼
  [FASE 4] PredictorAgent (Determina estrategia)
                   │
                   ▼
  [FASE 5] OrchestratorAgent (Divide el trabajo)
                   │
                   ▼
  [FASE 7] WorkerAgent (Procesa sub-tareas específicas)
                   │
                   ▼
  [FASE 10] AnalystAgent (Reporta rendimiento del Job)
                   │
                   ▼
  [FASE 2] MemoryAgent (Persiste trazas en MySQL)
                   │
                   ▼
  [FASE 11] OptimizerAgent (Analiza fallos y recomienda)
                   │
                   ▼
  [FASE 12] CleanerAgent (Elimina temporales y caché expirada)
```

---

## 🚀 Cómo Empezar

### Requisitos
* PHP 8.0 o superior con extensión `curl` y `pdo_mysql`.
* MySQL o MariaDB (opcional para simulación inicial, obligatorio para persistencia).

### 1. Inicializar Base de Datos (Opcional)
Importa el archivo [schema.sql](file:///home/salkesme/Documentos/jah/jah%20php/memory/schema.sql) en tu servidor local de base de datos MySQL:
```bash
mysql -u tu_usuario -p < memory/schema.sql
```
Ajusta las credenciales de conexión en [config/database.php](file:///home/salkesme/Documentos/jah/jah%20php/config/database.php).

### 2. Simular Flujo Completo por Consola (CLI)
Para correr una simulación predefinida del flujo completo del motor (Observer -> Gateway -> Predictor -> Orchestrator -> Workers -> Finalización):
```bash
php index.php
```

Para inyectar una acción específica:
```bash
php index.php test param1=mi_valor param2=otro_valor
```

### 3. Ejecutar por Servidor Web (HTTP)
Puedes levantar el servidor embebido de PHP en la carpeta raíz:
```bash
php -S localhost:8000
```
Y consumir el servicio enviando solicitudes GET o POST:
* Verificar estado: `http://localhost:8000/`
* Disparar acción: `http://localhost:8000/?action=test&data_clave=data_valor`
