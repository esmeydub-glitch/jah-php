<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Modal extends Element
{
    private string $title = '';

    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-modal');
        $this->title = (string) ($props['title'] ?? '');
        $this->attr('role', 'dialog');
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function open(bool $open = true): self
    {
        return $this->attr('data-open', $open ? 'true' : 'false');
    }

    public function render(): string
    {
        $title = $this->title !== '' ? '<header class="asjah-modal-header"><h2>' . self::escape($this->title) . '</h2></header>' : '';

        return '<section' . $this->renderAttributes() . '>' .
            '<div class="asjah-modal-content">' .
            $title .
            '<div class="asjah-modal-body">' . $this->renderChildren() . '</div>' .
            '</div>' .
            '</section>';
    }

    protected function tag(): string
    {
        return 'section';
    }
}
