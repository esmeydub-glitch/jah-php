<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Text extends Element
{
    private string $text;

    public function __construct(string $text, string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->text = $text;
        $this->class('asjah-text');
    }

    public function render(): string
    {
        return '<' . $this->tag() . $this->renderAttributes() . '>' .
            self::escape($this->text) .
            $this->renderChildren() .
            '</' . $this->tag() . '>';
    }

    protected function tag(): string
    {
        return 'p';
    }
}
