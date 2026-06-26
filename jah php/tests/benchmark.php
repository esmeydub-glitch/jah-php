<?php

declare(strict_types=1);

/**
 * Benchmark & Prueba de Movimiento de Datos — Motor JAH
 * Mide tiempos de ejecución, consumo de memoria y rendimiento de eventos.
 */

// Registrar autoloaders
require_once __DIR__ . '/../core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\',    dirname(__DIR__) . '/core');
Autoloader::addNamespace('Jah\\Agents\\',  dirname(__DIR__) . '/agents');
Autoloader::addNamespace('Jah\\Memory\\',  dirname(__DIR__) . '/memory');
Autoloader::addNamespace('Jah\\Network\\', dirname(__DIR__) . '/network');
Autoloader::addNamespace('Jah\\Cache\\',   dirname(__DIR__) . '/cache');

use Jah\Core\JahEngine;

// Desactivar temporalmente logs para el benchmark para no saturar disco
$config = require __DIR__ . '/../config/config.php';
$config['log']['enabled'] = false; // Desactivar log para test de velocidad pura

echo "==================================================\n";
echo "       BENCHMARK DE MOVIMIENTO DE DATOS - JAH     \n";
echo "==================================================\n\n";

// --- TEST 1: Medir Arranque (Bootstrap) ---
$memStart = memory_get_usage();
$timeStart = microtime(true);

$engine = JahEngine::getInstance();
$engine->boot($config);

$timeEnd = microtime(true);
$memEnd = memory_get_usage();

$bootTimeMs = ($timeEnd - $timeStart) * 1000;
$bootMemoryKb = ($memEnd - $memStart) / 1024;

echo "1. RENDIMIENTO DE ARRANQUE (BOOTSTRAP)\n";
echo "--------------------------------------------------\n";
echo "Tiempo de arranque (Bootstrap): " . number_format($bootTimeMs, 4) . " ms\n";
echo "Memoria consumida al arrancar: " . number_format($bootMemoryKb, 2) . " KB\n\n";


// --- TEST 2: Envío Masivo de Eventos ---
echo "2. VELOCIDAD DE ENRUTAMIENTO DE EVENTOS (EVENT BUS)\n";
echo "--------------------------------------------------\n";

$eventBus = $engine->getEventBus();
$totalEvents = 10000;

// Registrar un suscriptor ligero para medir recepción
$receivedCount = 0;
$eventBus->subscribe('benchmark.test', function(array $event) use (&$receivedCount) {
    $receivedCount++;
});

$timeStart = microtime(true);
for ($i = 0; $i < $totalEvents; $i++) {
    $eventBus->publish('benchmark.test', [
        'index' => $i,
        'data'  => 'data_payload_' . $i
    ]);
}
$timeEnd = microtime(true);
$duration = $timeEnd - $timeStart;
$eps = $totalEvents / $duration;

echo "Eventos enrutados: " . number_format($totalEvents) . "\n";
echo "Tiempo total: " . number_format($duration * 1000, 2) . " ms\n";
echo "Velocidad: " . number_format($eps, 2) . " eventos/segundo\n\n";


// --- TEST 3: Operaciones de Caché ---
echo "3. LECTURA/ESCRITURA DE DATOS EN CACHÉ\n";
echo "--------------------------------------------------\n";

$cacheManager = new \Jah\Cache\CacheManager(dirname(__DIR__) . '/cache/store');
$cacheManager->clear(); // Limpiar previo

$totalCacheOps = 1000;

// Escrituras
$timeStart = microtime(true);
for ($i = 0; $i < $totalCacheOps; $i++) {
    $cacheManager->set("key_$i", [
        'id' => $i,
        'payload' => str_repeat("A", 100) // 100 bytes
    ], 60);
}
$timeEnd = microtime(true);
$writeDuration = $timeEnd - $timeStart;

// Lecturas
$timeStart = microtime(true);
for ($i = 0; $i < $totalCacheOps; $i++) {
    $val = $cacheManager->get("key_$i");
}
$timeEnd = microtime(true);
$readDuration = $timeEnd - $timeStart;

echo "Operaciones escritas: " . number_format($totalCacheOps) . " archivos JSON con TTL\n";
echo "Tiempo de escritura total: " . number_format($writeDuration * 1000, 2) . " ms (" . number_format($totalCacheOps / $writeDuration, 2) . " escrituras/seg)\n";
echo "Tiempo de lectura total: " . number_format($readDuration * 1000, 2) . " ms (" . number_format($totalCacheOps / $readDuration, 2) . " lecturas/seg)\n\n";


// --- TEST 4: Lectura/Escritura en Base de Datos Real ---
echo "4. RENDIMIENTO DE BASE DE DATOS (MARIADB REAL)\n";
echo "--------------------------------------------------\n";

try {
    $db = \Jah\Memory\Database::getInstance();
    $totalDbOps = 500;
    
    // Medir inserciones directas (con Transacción para optimizar velocidad de bloque)
    $timeStart = microtime(true);
    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO jah_events (event_id, event_type, payload, source, created_at) 
            VALUES (:id, :type, :payload, :source, NOW())";
    
    for ($i = 0; $i < $totalDbOps; $i++) {
        $db->query($sql, [
            'id'      => 'bench_' . uniqid('', true),
            'type'    => 'benchmark.db.write',
            'payload' => json_encode(['db_index' => $i]),
            'source'  => 'benchmark_script',
        ]);
    }
    $pdo->commit();
    $timeEnd = microtime(true);
    $dbWriteDuration = $timeEnd - $timeStart;

    // Medir lecturas directas
    $timeStart = microtime(true);
    $results = $db->fetchAll("SELECT * FROM jah_events WHERE event_type = 'benchmark.db.write' LIMIT :limit", [
        'limit' => $totalDbOps
    ]);
    $timeEnd = microtime(true);
    $dbReadDuration = $timeEnd - $timeStart;

    // Limpieza de datos del benchmark en la DB
    $db->query("DELETE FROM jah_events WHERE event_type = 'benchmark.db.write'");

    echo "Conexión a MariaDB: Exitosa\n";
    echo "Operaciones realizadas: " . number_format($totalDbOps) . " filas insertadas y leídas\n";
    echo "Tiempo de escritura (transacción): " . number_format($dbWriteDuration * 1000, 2) . " ms (" . number_format($totalDbOps / $dbWriteDuration, 2) . " inserts/seg)\n";
    echo "Tiempo de lectura: " . number_format($dbReadDuration * 1000, 2) . " ms (" . number_format(count($results) / $dbReadDuration, 2) . " selects/seg)\n\n";

} catch (\Throwable $e) {
    echo "Conexión a MariaDB: Fallida (" . $e->getMessage() . ")\n\n";
}


// --- TEST 5: Simulación de Carga vs Mocks de Frameworks ---
echo "5. COMPARACIÓN DE ARRANQUE Y HUELLA DE MEMORIA\n";
echo "--------------------------------------------------\n";
// Valores estimados promedio de Frameworks basados en PHP 8.1+ en producción vacíos
$laravelBootTime = 35.0; // ms
$laravelMemory = 18432; // KB (18MB)

$symfonyBootTime = 25.0; // ms
$symfonyMemory = 12288; // KB (12MB)

echo "Comparación de Carga inicial (Bootstrap):\n";
echo sprintf("  %-15s | %-12s | %-12s\n", "Sistema", "Tiempo Boot", "Memoria RAM");
echo "  " . str_repeat("-", 47) . "\n";
echo sprintf("  %-15s | %-10.4f ms | %-10.2f KB\n", "JAH Motor", $bootTimeMs, $bootMemoryKb);
echo sprintf("  %-15s | %-10.4f ms | %-10.2f KB (estimado)\n", "Laravel", $laravelBootTime, $laravelMemory);
echo sprintf("  %-15s | %-10.4f ms | %-10.2f KB (estimado)\n", "Symfony", $symfonyBootTime, $symfonyMemory);

echo "\n--------------------------------------------------\n";
echo "Simulación finalizada.\n";

$engine->shutdown();
