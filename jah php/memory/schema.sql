-- Esquema base de la base de datos de Memoria del Motor PHP JAH.
-- Este archivo define las tablas necesarias para guardar la traza de eventos, decisiones, errores y logs.

CREATE DATABASE IF NOT EXISTS `jah_motor` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jah_motor`;

-- 1. Registro histórico de todos los eventos
CREATE TABLE IF NOT EXISTS `jah_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(50) NOT NULL UNIQUE,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` LONGTEXT NULL,
    `source` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Registro de decisiones tomadas (Predictor, Optimizer, Orchestrator)
CREATE TABLE IF NOT EXISTS `jah_decisions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `context` VARCHAR(100) NOT NULL,
    `decision_type` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `output_strategy` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_context` (`context`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Registro detallado de errores y excepciones
CREATE TABLE IF NOT EXISTS `jah_errors` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `message` TEXT NOT NULL,
    `trace` LONGTEXT NULL,
    `source` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Registro de resultados consolidados de Jobs ejecutados
CREATE TABLE IF NOT EXISTS `jah_results` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` VARCHAR(50) NOT NULL UNIQUE,
    `action` VARCHAR(100) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `total_tasks` INT UNSIGNED NOT NULL DEFAULT 0,
    `completed_tasks` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_tasks` INT UNSIGNED NOT NULL DEFAULT 0,
    `results_payload` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Logs generales en base de datos (opcional)
CREATE TABLE IF NOT EXISTS `jah_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `level` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `source` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Monitoreo de agentes activos
CREATE TABLE IF NOT EXISTS `jah_agents` (
    `id` VARCHAR(100) PRIMARY KEY,
    `agent_class` VARCHAR(100) NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `last_seen` DATETIME NOT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
