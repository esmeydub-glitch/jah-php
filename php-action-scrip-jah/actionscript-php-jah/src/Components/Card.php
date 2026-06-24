<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Card extends Element
{
    private string $title;
    private string $value;
    private string $subtitle = '';

    public function __construct(string $title, string|int|float $value = '', string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->title = $title;
        $this->value = (string) $value;
        $this->subtitle = (string) ($props['subtitle'] ?? '');
        $this->class('asjah-card');
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function status(string $status): self
    {
        return $this->class('asjah-status-' . (preg_replace('/[^a-z0-9_-]/i', '', $status) ?? ''));
    }

    public function render(): string
    {
        $subtitle = $this->subtitle !== '' ? '<p>' . self::escape($this->subtitle) . '</p>' : '';

        return '<article' . $this->renderAttributes() . '>' .
            '<h3>' . self::escape($this->title) . '</h3>' .
            '<strong>' . self::escape($this->value) . '</strong>' .
            $subtitle .
            $this->renderChildren() .
            '</article>';
    }

    protected function tag(): string
    {
        return 'article';
    }
}
