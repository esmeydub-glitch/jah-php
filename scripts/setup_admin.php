<?php

declare(strict_types=1);

require_once __DIR__ . '/../jah-datacore/src/autoload.php';
require_once __DIR__ . '/../jah-datacore/src/SecurityAgent.php';

use Jah\DataCore\SecurityAgent;

if ($argc < 3) {
    echo "Usage: php scripts/setup_admin.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];
$basePath = __DIR__ . '/../jah-datacore/data';

$security = new SecurityAgent($basePath);

try {
    $user = $security->register($username, $password, ['admin']);
    echo "Admin user created: {$user['username']} (id: {$user['id']})\n";
} catch (InvalidArgumentException $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
