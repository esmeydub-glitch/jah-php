<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasKeyframes
{
    private array $frames = [];

    public function frame(string|int $position, array $state): self
    {
        $this->frames[(string) $position] = $state;
        return $this;
    }

    public function frames(): array
    {
        return $this->frames;
    }
}
