<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasRectangle extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-rectangle');
        $this->style([
            'position' => 'absolute',
            'background' => $props['fill'] ?? '#7dd3fc',
        ]);
    }

    public function at(int|float $x, int|float $y): self
    {
        return $this->style(['left' => $x . 'px', 'top' => $y . 'px']);
    }

    public function size(int|float $width, int|float $height): self
    {
        return $this->style(['width' => $width . 'px', 'height' => $height . 'px']);
    }

    public function fill(string $color): self
    {
        return $this->style(['background' => $color]);
    }

    protected function tag(): string
    {
        return 'div';
    }
}
