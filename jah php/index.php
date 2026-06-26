<?php

declare(strict_types=1);

/**
 * Motor PHP JAH — Punto de Entrada Principal.
 * Inicializa el motor, registra namespaces, levanta agentes base
 * y procesa peticiones HTTP o CLI.
 */

// 1. Cargar Autoloader base
require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\',    __DIR__ . '/core');
Autoloader::addNamespace('Jah\\Agents\\',  __DIR__ . '/agents');
Autoloader::addNamespace('Jah\\Memory\\',  __DIR__ . '/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/network');
Autoloader::addNamespace('Jah\\Cache\\',   __DIR__ . '/cache');

use Jah\Core\JahEngine;

// 2. Cargar configuración global
$configFile = __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    die("Error crítico: Archivo de configuración global no encontrado en {$configFile}\n");
}
$config = require $configFile;

// 3. Obtener e iniciar el motor central
$engine = JahEngine::getInstance();
$engine->boot($config);

if (!($config['debug'] ?? false)) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// 4. Determinar entorno de ejecución (CLI o HTTP)
$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    // Si se ejecuta por consola (ej: php index.php <action> key=value)
    global $argv;
    if (count($argv) > 1) {
        $action = $argv[1];
        
        $engine->log("Inyección de comando desde CLI: {$action}", 'info');
        
        // El GatewayAgent (ya registrado en boot) procesará la petición
        $gateway = $engine->getAgent('GatewayAgent');
        if ($gateway instanceof \Jah\Agents\GatewayAgent) {
            $gateway->handleCliRequest($argv);
        } else {
            echo "Error: GatewayAgent no está activo.\n";
        }
    } else {
        // Ejecución CLI interactiva / interactuar con prueba por defecto
        echo "Motor JAH listo (Entorno CLI).\n";
        echo "Uso sugerido: php index.php <accion> [parametro=valor]\n\n";
        echo "Ejecutando simulación de flujo completo por defecto...\n";
        
        // Inyectar un flujo de prueba completo
        $gateway = $engine->getAgent('GatewayAgent');
        if ($gateway instanceof \Jah\Agents\GatewayAgent) {
            // Registrar dinámicamente un suscriptor temporal para ver la salida del flujo completo en consola
            $engine->getEventBus()->subscribe('orchestrator.job_finished', function(array $event) {
                echo "\n=== PROCESAMIENTO COMPLETADO POR EL MOTOR ===\n";
                echo "Job: " . $event['payload']['job_id'] . "\n";
                echo "Acción: " . $event['payload']['action'] . "\n";
                echo "Éxito: " . ($event['payload']['success'] ? 'Sí' : 'No') . "\n";
                echo "Detalles: " . json_encode($event['payload']['results'], JSON_PRETTY_PRINT) . "\n";
            });

            // Enviar un trigger de observer check
            $engine->getEventBus()->publish('observer.check');
            
            // Enviar una tarea de prueba
            $gateway->handleCliRequest(['index.php', 'test', 'param1=valor1', 'param2=valor2']);
        }
    }
} else {
    header('Content-Type: application/json; charset=utf-8');

    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $allowedMethods = $config['security']['allowed_methods'] ?? ['GET', 'POST'];

    if (!in_array($requestMethod, $allowedMethods, true)) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.'], JSON_THROW_ON_ERROR);
        $engine->shutdown();
        exit;
    }

    $data = [];
    if ($requestMethod === 'POST') {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > ($config['security']['max_payload_bytes'] ?? 1_048_576)) {
            http_response_code(413);
            echo json_encode(['error' => 'Payload too large.'], JSON_THROW_ON_ERROR);
            $engine->shutdown();
            exit;
        }

        $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload.'], JSON_THROW_ON_ERROR);
            $engine->shutdown();
            exit;
        }

        $action = is_string($input['action'] ?? null) ? $input['action'] : null;
        $data = is_array($input['data'] ?? null) ? $input['data'] : [];
    } else {
        $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : null;
        $data = $_GET;
        unset($data['action']);
    }

    if (!$action) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Motor PHP JAH activo y escuchando.',
            'version' => $config['version'],
        ], JSON_THROW_ON_ERROR);
        $engine->shutdown();
        exit;
    }

    $gateway = $engine->getAgent('GatewayAgent');
    if ($gateway instanceof \Jah\Agents\GatewayAgent) {
        $engine->getEventBus()->subscribe('orchestrator.job_finished', function (array $event) use ($action): void {
            http_response_code(200);
            echo json_encode([
                'status'  => 'success',
                'job_id'  => (string) ($event['payload']['job_id'] ?? ''),
                'action'  => (string) ($event['payload']['action'] ?? $action),
                'success' => (bool) ($event['payload']['success'] ?? false),
                'results' => $event['payload']['results'] ?? [],
            ], JSON_THROW_ON_ERROR);
        });

        $engine->getEventBus()->publish('observer.check');

        $gateway->handleHttpRequest([
            'action' => $action,
            'data'   => $data,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'GatewayAgent no configurado o inactivo.'], JSON_THROW_ON_ERROR);
    }
}

// 5. Apagar agentes al terminar la ejecución actual
$engine->shutdown();
