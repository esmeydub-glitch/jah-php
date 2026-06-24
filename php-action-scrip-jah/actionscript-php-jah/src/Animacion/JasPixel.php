<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasPixel extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-pixel');
        $this->style([
            'position' => 'absolute',
            'width' => '1px',
            'height' => '1px',
            'background' => $props['color'] ?? '#00ffcc',
        ]);
    }

    public function at(int $x, int $y): self
    {
        return $this->style(['left' => $x . 'px', 'top' => $y . 'px']);
    }

    public function color(string $color): self
    {
        return $this->style(['background' => $color]);
    }

    protected function tag(): string
    {
        return 'div';
    }
}
