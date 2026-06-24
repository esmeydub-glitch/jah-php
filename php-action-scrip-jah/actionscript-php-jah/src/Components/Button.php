<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Button extends Element
{
    private string $label;

    public function __construct(string $label, string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->label = $label;
        $this->class('asjah-button');
    }

    public function render(): string
    {
        return '<button' . $this->renderAttributes() . '>' .
            self::escape($this->label) .
            $this->renderChildren() .
            '</button>';
    }

    protected function tag(): string
    {
        return 'button';
    }
}
