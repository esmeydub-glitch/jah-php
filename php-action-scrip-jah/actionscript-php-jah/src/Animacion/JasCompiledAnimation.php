<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

final class JasCompiledAnimation
{
    public function __construct(
        private string $html,
        private string $css,
        private array $manifest,
        private array $events = []
    ) {
    }

    public function html(): string
    {
        return $this->html;
    }

    public function css(): string
    {
        return $this->css;
    }

    public function styleTag(): string
    {
        return '<style>' . $this->css . '</style>';
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function render(): string
    {
        return $this->styleTag() . $this->html;
    }
}
