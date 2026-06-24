<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Panel extends Element
{
    private string $title = '';

    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-panel');
        $this->title = (string) ($props['title'] ?? '');
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function render(): string
    {
        $header = '';
        if ($this->title !== '') {
            $header = '<header class="asjah-panel-header"><h2>' . self::escape($this->title) . '</h2></header>';
        }

        return '<section' . $this->renderAttributes() . '>' .
            $header .
            '<div class="asjah-panel-body">' . $this->renderChildren() . '</div>' .
            '</section>';
    }

    protected function tag(): string
    {
        return 'section';
    }
}
