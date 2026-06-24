<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasGame32Level
{
    private array $config = [
        'id' => '',
        'background' => 'grid_blue',
        'enemies' => 10,
        'shots' => 20,
        'particles' => 50,
        'boss' => '',
        'goal' => '',
        'hazards' => '',
        'objective' => '',
    ];

    public function __construct(string $id)
    {
        $this->config['id'] = $id;
    }

    public function background(string $background): self
    {
        $this->config['background'] = $background;
        return $this;
    }

    public function enemies(int $count): self
    {
        $this->config['enemies'] = max(0, $count);
        return $this;
    }

    public function shots(int $count): self
    {
        $this->config['shots'] = max(0, $count);
        return $this;
    }

    public function particles(int $count): self
    {
        $this->config['particles'] = max(0, $count);
        return $this;
    }

    public function boss(string $boss): self
    {
        $this->config['boss'] = $boss;
        return $this;
    }

    public function goal(string $goal): self
    {
        $this->config['goal'] = $goal;
        return $this;
    }

    public function hazards(string $hazards): self
    {
        $this->config['hazards'] = $hazards;
        return $this;
    }

    public function objective(string $objective): self
    {
        $this->config['objective'] = $objective;
        return $this;
    }

    public function config(): array
    {
        return $this->config;
    }
}
