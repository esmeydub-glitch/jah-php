<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Stage extends Element
{
    public function __construct(string $id = 'stage', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-stage');
        if (isset($props['width'], $props['height'])) {
            $this->style([
                'width' => (int) $props['width'] . 'px',
                'height' => (int) $props['height'] . 'px',
                'position' => 'relative',
                'overflow' => 'hidden',
            ]);
        }
    }

    protected function tag(): string
    {
        return 'main';
    }
}
