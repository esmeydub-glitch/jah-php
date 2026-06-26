# JAH DataCore - Pure PHP Memory Engine

Motor NoSQL 100% PHP sin SQL ni dependencias externas. Incluye benchmarks locales reproducibles para comparar escritura binaria append-only.

## Runtime

No usa Composer para la demo del hackathon. La carga se hace con `require_once` y el autoloader manual de JAH.

## Formato

```text
[4 bytes length][JSON payload][newline]
```

## Uso

```php
require_once __DIR__ . '/../src/DataCore/DataCoreTurbo.php';

use Jah\DataCore\DataCoreTurbo;

$db = new DataCoreTurbo('/tmp/datacore');
$db->insert('memories', ['id' => 'm1', 'content' => 'Jah-PHP memory']);
$memory = $db->find('memories', 'm1');
$db->close();
```

## Tests

Los tests del proyecto se ejecutan con PHP CLI puro.

```bash
php tests/run.php
```
