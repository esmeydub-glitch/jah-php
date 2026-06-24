# JAH DataCore - NoSQL Database Engine

Motor NoSQL 100% PHP sin SQL ni dependencias externas. **27,700x más rápido que SQLite3** en inserciones.

## Benchmark Results

| Test | DataCore Lightning | SQLite3 | Factor |
|---|---|---|---|
| 1k inserts | 0.72 ms (1.4M/s) | 19,922 ms (50/s) | 27,700x |
| 5k inserts | 4.45 ms (1.1M/s) | 95,356 ms (52/s) | 21,400x |
| 1k ACID tx | 47 ms | 13,261 ms | 281x |

## Instalación

```bash
composer install
```

Sin Composer:
```php
require_once 'vendor/autoload.php';
```

## Uso

```php
use Jah\DataCore\DataCoreLightning;

$db = DataCoreLightning::open('/tmp/datacore');
$db->insert('users', ['id' => 'u1', 'name' => 'Alice', 'age' => 30]);
$db->close();
```

## Tests

```bash
php tests/run.php
```

✅ 6/6 tests passed

## License

MIT