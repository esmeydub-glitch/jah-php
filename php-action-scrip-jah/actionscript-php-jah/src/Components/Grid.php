<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Grid extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-grid');
        if (isset($props['columns'])) {
            $this->attr('style', '--asjah-grid-columns: ' . (string) $props['columns']);
        }
    }

    protected function tag(): string
    {
        return 'div';
    }
}
