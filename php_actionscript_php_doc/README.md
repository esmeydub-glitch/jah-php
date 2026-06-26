# JAH ActionScript PHP

Motor experimental de acciones y politicas JAS escrito solamente en PHP.

## Componentes

- `ActionScriptEngine.php`: registro y ejecucion de acciones.
- `JahEngineJas.php`: carga y evaluacion de politicas `.jas`.
- `JasAsyncActions.php`: coordinacion de tareas y streams.
- `JasBinaryCompiler.php`: generacion binaria experimental.
- `JasNativeCompiler.php`: interfaz de compilacion nativa.
- `JasTypeScript.php`: declaraciones de tipos JAS.

## Ejemplo

```php
require_once 'ActionScriptEngine.php';

use Jah\Actions\ActionScript;

$action = new ActionScript('math.double');
$action
    ->requires(['value'])
    ->timeout(100)
    ->handler(static fn(array $data): int => (int) $data['value'] * 2);

$result = ActionScript::run('math.double', ['value' => 21]);
```
