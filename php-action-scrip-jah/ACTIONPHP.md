# JAH ActionPHP

V1 minima real para construir interfaces estilo ActionScript desde PHP y renderizarlas como HTML5.

```php
require_once __DIR__ . '/app/ActionPHP/bootstrap.php';

$stage = new JahStage('main');
$scene = new JahScene('dashboard');
$button = new JahButton('Ejecutar flujo JAH');
$button->onClick('jah.flow.execute');
$scene->add($button);
$stage->add($scene);

echo $stage->render();
```

## Componentes empresariales

La capa de componentes agrega objetos visuales reutilizables para escenas, paneles, tarjetas, tablas, formularios y video HTML5.

```php
$stage = new JahStage('jah_flashless_player');
$scene = new JahScene('video_scene');

$player = new JahSprite('jah_player_shell');
$player->class('jah-player-shell');

$video = new JahVideo('/media/jah-demo.webm', 'jah_intro_video');
$video->controls(true)->preload('metadata');

$controls = new JahSprite('player_controls');
$controls->add(
    (new JahButton('Reproducir video'))
        ->onClick('jah.video.play')
        ->salkProtect([
            'event' => 'jah.video.play',
            'process' => 'video.runtime',
            'payload' => ['file' => 'jah-demo.webm'],
        ])
);
$controls->add((new JahButton('Pausar'))->onClick('jah.video.pause'));

$player->add($video);
$player->add($controls);
$scene->add($player);
$stage->add($scene);

echo $stage->render();
```

Demo:

```bash
php -S 127.0.0.1:8000 -t public
```

Abrir `http://127.0.0.1:8000/demo_dashboard.php`.

Validacion:

```bash
php -l app/ActionPHP/Core/JahComponent.php
php tests/actionphp_smoke.php
php tests/actionphp_metrics.php
php tests/actionphp_scale.php --panels=100 --buttons=1000 --tables=100 --rows=10 --events=10000
```

Metricas actuales:

```bash
cat METRICS.md
```

## SALK

Los botones con evento se firman automaticamente con SALK. Para firmar contexto de proceso:

```php
$button = (new JahButton('Ejecutar'))
    ->onClick('jah.video.play')
    ->salkProtect([
        'event' => 'jah.video.play',
        'process' => 'video.runtime',
        'payload' => ['file' => 'jah-demo.webm'],
    ]);
```

El motor valida con `dispatchSignedEvent()` antes de publicar el evento al `EventBus`.

## JAH ActionScript PHP

Nueva capa experimental en `actionscript-php-jah/`:

```php
use Jah\ActionScript\JAS;

$app = JAS::stage('jah_app')->children([
    JAS::scene('intro')->children([
        JAS::video('/public/media/jah-demo.webm', 'intro_video'),
        JAS::button('Reproducir', 'play_btn')->on('click', 'jah.video.play', [
            'file' => 'public/media/jah-demo.webm',
        ]),
    ]),
]);
```

Validacion:

```bash
php actionscript-php-jah/tests/smoke.php
```

Animacion:

```bash
php actionscript-php-jah/tests/jas_animation_smoke.php
php actionscript-php-jah/tests/jas_animation_scale.php --sprites=1000 --tweens=1000
```

Ejemplo:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/intro.php
```
