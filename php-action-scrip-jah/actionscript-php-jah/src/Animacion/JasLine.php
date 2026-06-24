<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasLine extends Element
{
    private array $from = [0, 0];
    private array $to = [100, 0];
    private string $stroke = '#00ffcc';
    private int $width = 2;

    public function from(int|float $x, int|float $y): self
    {
        $this->from = [$x, $y];
        return $this;
    }

    public function to(int|float $x, int|float $y): self
    {
        $this->to = [$x, $y];
        return $this;
    }

    public function stroke(string $stroke): self
    {
        $this->stroke = $stroke;
        return $this;
    }

    public function width(int $width): self
    {
        $this->width = max(1, $width);
        return $this;
    }

    public function render(): string
    {
        $maxX = max($this->from[0], $this->to[0]) + $this->width;
        $maxY = max($this->from[1], $this->to[1]) + $this->width;

        return '<svg' . $this->renderAttributes() . ' width="' . self::escape((string) $maxX) . '" height="' . self::escape((string) $maxY) . '">' .
            '<line x1="' . self::escape((string) $this->from[0]) . '" y1="' . self::escape((string) $this->from[1]) .
            '" x2="' . self::escape((string) $this->to[0]) . '" y2="' . self::escape((string) $this->to[1]) .
            '" stroke="' . self::escape($this->stroke) . '" stroke-width="' . self::escape((string) $this->width) . '"/>' .
            '</svg>';
    }

    protected function tag(): string
    {
        return 'svg';
    }
}
