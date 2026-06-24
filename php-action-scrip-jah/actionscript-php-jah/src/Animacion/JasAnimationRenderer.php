<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasAnimationRenderer
{
    public function render(JasCompiledAnimation $compiled): string
    {
        return $compiled->render();
    }
}
