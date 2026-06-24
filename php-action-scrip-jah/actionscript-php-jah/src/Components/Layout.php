<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Layout extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-layout');
        if (isset($props['mode'])) {
            $this->class('asjah-layout-' . (preg_replace('/[^a-z0-9_-]/i', '', (string) $props['mode']) ?? ''));
        }
    }

    protected function tag(): string
    {
        return 'div';
    }
}
