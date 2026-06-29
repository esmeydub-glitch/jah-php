<?php

declare(strict_types=1);

/**
 * Compatibility loader for the original aggregate file.
 * Each agent now has a single canonical class definition.
 */

require_once __DIR__ . '/LockAgent.php';
require_once __DIR__ . '/IndexAgent.php';
require_once __DIR__ . '/EventAgent.php';
require_once __DIR__ . '/TransactionAgent.php';
require_once __DIR__ . '/SchemaAgent.php';
require_once __DIR__ . '/IntegrityAgent.php';
