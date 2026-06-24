<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Scene extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-scene');
    }

    protected function tag(): string
    {
        return 'section';
    }
}
