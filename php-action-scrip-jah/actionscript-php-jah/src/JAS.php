<?php

declare(strict_types=1);

namespace Jah\ActionScript;

use Jah\ActionScript\Animacion\JasAnimationCompiler;
use Jah\ActionScript\Animacion\JasCircle;
use Jah\ActionScript\Animacion\JasCompiledAnimation;
use Jah\ActionScript\Animacion\JasGame32;
use Jah\ActionScript\Animacion\JasLine;
use Jah\ActionScript\Animacion\JasMovieClip;
use Jah\ActionScript\Animacion\JasPixel;
use Jah\ActionScript\Animacion\JasRectangle;
use Jah\ActionScript\Animacion\JasTimeline;
use Jah\ActionScript\Animacion\JasTween;
use Jah\ActionScript\Components\Button;
use Jah\ActionScript\Components\Card;
use Jah\ActionScript\Components\Form;
use Jah\ActionScript\Components\Grid;
use Jah\ActionScript\Components\Input;
use Jah\ActionScript\Components\Layout;
use Jah\ActionScript\Components\Modal;
use Jah\ActionScript\Components\Panel;
use Jah\ActionScript\Components\Scene;
use Jah\ActionScript\Components\Select;
use Jah\ActionScript\Components\Sprite;
use Jah\ActionScript\Components\Stage;
use Jah\ActionScript\Components\Table;
use Jah\ActionScript\Components\Text;
use Jah\ActionScript\Components\Video;
use Jah\ActionScript\Core\Element;

final class JAS
{
    public static function stage(string $id = 'stage', array $props = []): Stage
    {
        return new Stage($id, $props);
    }

    public static function stageBox(string $id, int $width, int $height): Stage
    {
        return new Stage($id, ['width' => $width, 'height' => $height]);
    }

    public static function scene(string $id = '', array $props = []): Scene
    {
        return new Scene($id, $props);
    }

    public static function sprite(string $id = '', array $props = []): Sprite
    {
        return new Sprite($id, $props);
    }

    public static function text(string $text, string $id = '', array $props = []): Text
    {
        return new Text($text, $id, $props);
    }

    public static function button(string $label, string $id = '', array $props = []): Button
    {
        return new Button($label, $id, $props);
    }

    public static function video(string $src, string $id = '', array $props = []): Video
    {
        return new Video($src, $id, $props);
    }

    public static function panel(string $id = '', array $props = []): Panel
    {
        return new Panel($id, $props);
    }

    public static function card(string $title, string|int|float $value = '', string $id = '', array $props = []): Card
    {
        return new Card($title, $value, $id, $props);
    }

    public static function grid(string $id = '', array $props = []): Grid
    {
        return new Grid($id, $props);
    }

    public static function table(string $id = '', array $props = []): Table
    {
        return new Table($id, $props);
    }

    public static function form(string $id = '', array $props = []): Form
    {
        return new Form($id, $props);
    }

    public static function input(string $name, string $label = '', string $id = '', array $props = []): Input
    {
        return new Input($name, $label, $id, $props);
    }

    public static function select(string $name, array $options, string $label = '', string $id = '', array $props = []): Select
    {
        return new Select($name, $options, $label, $id, $props);
    }

    public static function modal(string $id = '', array $props = []): Modal
    {
        return new Modal($id, $props);
    }

    public static function layout(string $id = '', array $props = []): Layout
    {
        return new Layout($id, $props);
    }

    public static function tween(string $target): JasTween
    {
        return new JasTween($target);
    }

    public static function animate(string $target): JasTween
    {
        return new JasTween($target);
    }

    public static function timeline(string $id = ''): JasTimeline
    {
        return new JasTimeline($id);
    }

    public static function movieClip(string $id = '', array $props = []): JasMovieClip
    {
        return new JasMovieClip($id, $props);
    }

    public static function pixel(string $id = '', array $props = []): JasPixel
    {
        return new JasPixel($id, $props);
    }

    public static function circle(string $id = '', array $props = []): JasCircle
    {
        return new JasCircle($id, $props);
    }

    public static function rectangle(string $id = '', array $props = []): JasRectangle
    {
        return new JasRectangle($id, $props);
    }

    public static function line(string $id = '', array $props = []): JasLine
    {
        return new JasLine($id, $props);
    }

    public static function compile(Element $root): JasCompiledAnimation
    {
        return (new JasAnimationCompiler())->compile($root);
    }

    public static function render(Element $root): string
    {
        return self::compile($root)->render();
    }

    public static function game32(string $id): JasGame32
    {
        return new JasGame32($id);
    }
}
