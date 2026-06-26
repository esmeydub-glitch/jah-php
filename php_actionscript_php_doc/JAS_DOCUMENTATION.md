# JAS Documentation

Política mínima en `.jas`:

```jas
policy("balanced")
observe(30s)
stability_windows(3)
cooldown(300s)
rollback_loss(5%)
custom_cap(90)
workers(2, 8)
require("status_ok", "==", true)
```

## Acción mínima

```php
use Jah\Actions\ActionScript;

ActionScript::define('math.double')
    ->requires(['value'])
    ->timeout(100)
    ->handler(static fn(array $data): int => (int) $data['value'] * 2);

$result = ActionScript::run('math.double', ['value' => 21]);
```