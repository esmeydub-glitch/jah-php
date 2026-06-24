<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Form extends Element
{
    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-form');
        if (isset($props['action'])) {
            $this->action((string) $props['action']);
        }
        if (isset($props['method'])) {
            $this->method((string) $props['method']);
        }
    }

    public function action(string $action): self
    {
        return $this->attr('action', $action);
    }

    public function method(string $method): self
    {
        return $this->attr('method', strtoupper($method));
    }

    protected function tag(): string
    {
        return 'form';
    }
}
