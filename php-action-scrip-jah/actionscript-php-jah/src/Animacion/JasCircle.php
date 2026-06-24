<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasCircle extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-circle');
        $this->style([
            'position' => 'absolute',
            'border-radius' => '999px',
            'background' => $props['fill'] ?? '#00ffcc',
        ]);
    }

    public function at(int|float $x, int|float $y): self
    {
        return $this->style(['left' => $x . 'px', 'top' => $y . 'px']);
    }

    public function radius(int|float $radius): self
    {
        $size = $radius * 2;
        return $this->style(['width' => $size . 'px', 'height' => $size . 'px']);
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
