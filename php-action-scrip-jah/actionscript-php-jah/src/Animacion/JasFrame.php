<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasFrame
{
    public function __construct(
        public readonly int $frame,
        public readonly array $state
    ) {
    }
}
