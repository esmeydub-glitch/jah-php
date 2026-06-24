<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasGame32Player
{
    private array $config = [
        'id' => 'player',
        'x' => 80,
        'y' => 260,
        'energy' => 100,
        'speed' => 6,
        'weapon' => 'pulse',
    ];

    public function __construct(string $id)
    {
        $this->config['id'] = $id;
    }

    public function at(int $x, int $y): self
    {
        $this->config['x'] = $x;
        $this->config['y'] = $y;
        return $this;
    }

    public function energy(int $energy): self
    {
        $this->config['energy'] = max(1, $energy);
        return $this;
    }

    public function speed(int $speed): self
    {
        $this->config['speed'] = max(1, $speed);
        return $this;
    }

    public function weapon(string $weapon): self
    {
        $this->config['weapon'] = $weapon;
        return $this;
    }

    public function config(): array
    {
        return $this->config;
    }
}
