# JAH ActionScript PHP

PHP puro con una forma de uso mas cercana a ActionScript/TypeScript:

```php
require_once __DIR__ . '/src/bootstrap.php';

use Jah\ActionScript\JAS;

$app = JAS::stage('jah_app')->children([
    JAS::scene('intro')->children([
        JAS::video('/public/media/jah-demo.webm', 'intro_video'),
        JAS::button('Reproducir', 'play_btn')
            ->attr('data-video-target', 'intro_video')
            ->on('click', 'jah.video.play', [
                'file' => 'public/media/jah-demo.webm',
            ]),
    ]),
]);

echo $app->render();
```

## Filosofia

```text
PHP piensa como codigo logico
HTML5 muestra
SALK firma eventos
JAH coordina
```

No usa Node, React, Vue, TypeScript real ni build step. La sintaxis es PHP, pero el estilo de uso es declarativo:

```text
Stage
  Scene
    Sprite
      Video
      Button.on(click, jah.video.play)
```

## Componentes

```text
Panel
Card
Grid
Table
Form
Input
Select
Modal
Layout
```

Ejemplo:

```php
$app = JAS::stage('language_stage')->children([
    JAS::layout('main_layout', ['mode' => 'dashboard'])->children([
        JAS::panel('runtime_panel', ['title' => 'Runtime'])->children([
            JAS::grid('stats_grid')->children([
                JAS::card('Eventos SALK', 3),
            ]),
            JAS::table('events')->headers(['ID', 'Evento'])->row(['1', 'jah.video.play']),
        ]),
        JAS::form('process_form')->children([
            JAS::input('process', 'Proceso'),
            JAS::select('mode', ['execute' => 'Ejecutar'], 'Modo'),
        ]),
    ]),
]);
```

## Ejecutar ejemplo

Desde la raiz del proyecto principal:

```bash
php -S 127.0.0.1:8004 -t .
```

Abrir:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/video_runtime.php
```

Dashboard de componentes:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/ui_language.php
```

## Validar

```bash
php actionscript-php-jah/tests/smoke.php
php actionscript-php-jah/tests/jas_animation_smoke.php
php actionscript-php-jah/tests/jas_animation_scale.php --sprites=1000 --tweens=1000
```

## Animacion

Carpeta:

```text
src/Animacion/
```

Ejemplo:

```php
$logo = JAS::sprite('logo')
    ->text('JAH')
    ->at(50, 120)
    ->size(140, 70);

$timeline = JAS::timeline('intro')
    ->add(
        JAS::tween('logo')
            ->from(['x' => 50, 'opacity' => 0, 'scale' => 0.5])
            ->to(['x' => 350, 'opacity' => 1, 'scale' => 1])
            ->duration(1200)
            ->ease('ease-out')
            ->onFinish('animation.logo.finished')
    );

$compiled = JAS::compile(
    JAS::stageBox('demo', 800, 400)->children([
        JAS::scene('main')->children([$logo, $timeline]),
    ])
);

echo $compiled->styleTag();
echo $compiled->html();
```

Abrir:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/intro.php
```

Demo 16-bit:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/bit16.php
```

Videojuego 16-bit:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/bit16_game.php
```

Controles:

```text
Flechas o WASD para moverte
Shift para acelerar
```

Videojuego 32-bit:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/bit32_game.php
```

Controles:

```text
Flechas o WASD = mover
Espacio o Control = disparar
Shift = acelerar
```

JAH SkyCore 32:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/jah_skycore32.php
```

Escala:

```bash
php actionscript-php-jah/tests/jas_game32_scale.php --level=1 --enemies=10 --shots=20 --particles=50
php actionscript-php-jah/tests/jas_game32_scale.php --level=5 --enemies=120 --shots=300 --particles=800
```

JAH SkyCore 64:

```text
http://127.0.0.1:8004/actionscript-php-jah/examples/animacion/jah_skycore64.php
```

Controles:

```text
WASD/Flechas = mover
Espacio/Control = disparar
Q = laser cargado
E = dash
Shift = acelerar
```
