<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Components\Sprite;

final class JasMovieClip extends Sprite
{
    private int $fps = 60;

    public function fps(int $fps): self
    {
        $this->fps = max(1, $fps);
        return $this;
    }

    public function frame(int $frame, array $state): self
    {
        $ms = (int) round(($frame / $this->fps) * 1000);
        $this->animation((new JasTween($this->getId()))->to($state)->duration($ms));
        return $this;
    }
}
