# Actions Documentation

## Acción Asíncrona

```php
use Jah\Actions\AsyncAction;

$action = new AsyncAction('my.task');
$action->requires(['input']);
$action->timeout(5000);
$action->handler(fn($data) => $data['input'] * 2);
$result = $action->execute(['input' => 10]);
```

## Promise

```php
use Jah\JasPromise;

$promise = JasPromise::resolve($value);
$result = $promise->await();
```

## Stream

```php
use Jah\JasStream;

$stream = JasStream::from([1, 2, 3]);
$stream->on('data', fn($x) => print($x));
$stream->pipe(fn($x) => $x * 2)->on('data', fn($x) => print($x));
```