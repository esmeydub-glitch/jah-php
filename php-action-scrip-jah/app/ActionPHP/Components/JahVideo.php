<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahVideo extends JahComponent
{
    private string $src;
    private bool $controls = true;
    private bool $autoplay = false;
    private bool $loop = false;
    private bool $muted = false;
    private string $poster = '';
    private string $preload = 'metadata';

    public function __construct(string $src, string $id = '')
    {
        parent::__construct($id);
        $this->src = $src;
        $this->class('jah-video');
    }

    public function controls(bool $controls = true): self
    {
        $this->controls = $controls;
        return $this;
    }

    public function autoplay(bool $autoplay = true): self
    {
        $this->autoplay = $autoplay;
        return $this;
    }

    public function loop(bool $loop = true): self
    {
        $this->loop = $loop;
        return $this;
    }

    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;
        return $this;
    }

    public function poster(string $poster): self
    {
        $this->poster = $poster;
        return $this;
    }

    public function preload(string $preload): self
    {
        $this->preload = $preload;
        return $this;
    }

    public function render(): string
    {
        $src = htmlspecialchars($this->src, ENT_QUOTES, 'UTF-8');
        $attrs = $this->renderAttributes() .
            ' src="' . $src . '"' .
            ' preload="' . htmlspecialchars($this->preload, ENT_QUOTES, 'UTF-8') . '"';

        if ($this->controls) {
            $attrs .= ' controls';
        }
        if ($this->autoplay) {
            $attrs .= ' autoplay';
        }
        if ($this->loop) {
            $attrs .= ' loop';
        }
        if ($this->muted) {
            $attrs .= ' muted';
        }
        if ($this->poster !== '') {
            $attrs .= ' poster="' . htmlspecialchars($this->poster, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<video' . $attrs . '></video>';
    }
}
