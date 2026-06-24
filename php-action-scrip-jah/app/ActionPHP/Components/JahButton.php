<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahButton extends JahComponent
{
    private string $label;

    public function __construct(string $label, string $id = '')
    {
        parent::__construct($id);
        $this->label = $label;
        $this->class('jah-button');
    }

    public function onClick(string $event): self
    {
        $this->attr('data-jah-event', $event);
        return $this->salkProtect(['event' => $event, 'payload' => []]);
    }

    public function render(): string
    {
        return '<button' . $this->renderAttributes() . '>' .
            htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8') .
            '</button>';
    }
}
