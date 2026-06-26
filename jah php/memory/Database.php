<?php

declare(strict_types=1);

namespace Jah\Memory;

use PDO;
use PDOException;

/**
 * Database — Conexión PDO singleton y ejecutor de consultas para MySQL/MariaDB.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establece la conexión PDO leyendo los parámetros del archivo de configuración.
     */
    private function connect(): void
    {
        $configFile = dirname(__DIR__) . '/config/database.php';
        
        if (!is_file($configFile)) {
            throw new PDOException("Archivo de configuración de base de datos no encontrado: {$configFile}");
        }

        $config = require $configFile;

        $dsn = sprintf(
            "%s:host=%s;port=%d;dbname=%s;charset=%s",
            $config['driver'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? 'jah_motor',
            $config['charset'] ?? 'utf8mb4'
        );

        $password = $config['password'] ?? null;
        if ($password === null || $password === '') {
            throw new PDOException('JAH_DB_PASS is required for database connections.');
        }

        $this->pdo = new PDO(
            $dsn,
            $config['username'] ?? 'root',
            $password,
            $config['options'] ?? []
        );
    }

    /**
     * Retorna la conexión interna de PDO.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Ejecuta una consulta SQL preparada de lectura/escritura.
     *
     * @param string $sql Sentencia SQL (ej. "SELECT * FROM users WHERE id = :id")
     * @param array $params Parámetros para enlazar en la consulta preparada
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        
        foreach ($params as $key => $value) {
            $type = match (gettype($value)) {
                'integer' => PDO::PARAM_INT,
                'boolean' => PDO::PARAM_BOOL,
                'NULL'    => PDO::PARAM_NULL,
                default   => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Ejecuta una consulta y retorna una sola fila.
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Ejecuta una consulta y retorna todas las filas resultantes.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Retorna el último ID autoincremental insertado.
     */
    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }
}
