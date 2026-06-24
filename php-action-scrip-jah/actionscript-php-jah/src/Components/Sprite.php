<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Animacion\JasTween;
use Jah\ActionScript\Core\Element;

final class Sprite extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-sprite');
    }

    public function at(int|float $x, int|float $y): self
    {
        return $this->style([
            'position' => 'absolute',
            'left' => $x . 'px',
            'top' => $y . 'px',
        ]);
    }

    public function size(int|float $width, int|float $height): self
    {
        return $this->style([
            'width' => $width . 'px',
            'height' => $height . 'px',
        ]);
    }

    public function image(string $src): self
    {
        return $this->style([
            'background-image' => 'url(' . $src . ')',
            'background-size' => 'cover',
            'background-position' => 'center',
        ]);
    }

    public function opacity(float $opacity): self
    {
        return $this->style(['opacity' => (string) $opacity]);
    }

    public function rotate(float $degrees): self
    {
        return $this->style(['transform' => 'rotate(' . $degrees . 'deg)']);
    }

    public function scale(float $scale): self
    {
        return $this->style(['transform' => 'scale(' . $scale . ')']);
    }

    public function text(string $text): self
    {
        return $this->child($text);
    }

    public function moveTo(int|float $x, int|float $y): JasTween
    {
        $tween = (new JasTween($this->id))->to(['x' => $x, 'y' => $y]);
        $this->animation($tween);
        return $tween;
    }

    protected function tag(): string
    {
        return 'div';
    }
}
