<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahCard extends JahComponent
{
    private string $title;
    private string $value;
    private string $subtitle = '';

    public function __construct(string $title, string|int|float $value = '', string $id = '')
    {
        parent::__construct($id);
        $this->title = $title;
        $this->value = (string) $value;
        $this->class('jah-card');
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function status(string $status): self
    {
        $safeStatus = preg_replace('/[^a-z0-9_-]/i', '', $status) ?? '';
        return $this->class('jah-status-' . $safeStatus);
    }

    public function render(): string
    {
        $subtitle = '';
        if ($this->subtitle !== '') {
            $subtitle = '<p>' . htmlspecialchars($this->subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return '<article' . $this->renderAttributes() . '>' .
            '<h3>' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</h3>' .
            '<strong>' . htmlspecialchars($this->value, ENT_QUOTES, 'UTF-8') . '</strong>' .
            $subtitle .
            $this->renderChildren() .
            '</article>';
    }
}
