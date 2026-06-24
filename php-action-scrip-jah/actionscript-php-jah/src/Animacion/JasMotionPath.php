<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasMotionPath
{
    private array $points = [];

    public function __construct(private string $id)
    {
    }

    public function points(array $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function manifest(): array
    {
        return [
            'id' => $this->id,
            'points' => $this->points,
        ];
    }
}
