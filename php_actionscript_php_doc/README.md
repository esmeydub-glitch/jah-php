# JAH ActionScript PHP

Motor de acciones y politicas JAS escrito solamente en PHP.

## Componentes

- `ActionScriptEngine.php`: registro y ejecucion de acciones.
- `JahEngineJas.php`: carga y evaluacion de politicas `.jas`.
- `JasAsyncActions.php`: procesos PHP concurrentes acotados y fallback declarado.
- `JasBinaryCompiler.php`: bytecode JAS verificable e interpretable en PHP.
- `JasNativeCompiler.php`: validacion real y escritura atomica de artefactos PHP.
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
