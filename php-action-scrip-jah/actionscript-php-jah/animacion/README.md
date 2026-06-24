# Animacion JAS

Esta carpeta documenta la capa de animacion del lenguaje `actionscript-php-jah`.

Codigo fuente:

```text
../src/Animacion/
```

Ejemplo visual:

```text
../examples/animacion/intro.php
```

Pruebas:

```bash
php actionscript-php-jah/tests/jas_animation_smoke.php
php actionscript-php-jah/tests/jas_animation_scale.php --sprites=1000 --tweens=1000
```

Modelo:

```text
PHP describe
JAS compila
CSS keyframes ejecuta
SALK firma eventos de animacion
JAH recibe eventos validos
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
            ->from(['x' => 50, 'opacity' => 0])
            ->to(['x' => 350, 'opacity' => 1])
            ->duration(1200)
            ->ease('ease-out')
            ->onFinish('animation.logo.finished')
    );

$compiled = JAS::compile(
    JAS::stageBox('demo', 800, 400)->children([
        JAS::scene('main')->children([$logo, $timeline]),
    ])
);
```
