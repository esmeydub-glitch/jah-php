<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahForm extends JahComponent
{
    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-form');
    }

    public function action(string $action): self
    {
        return $this->attr('action', $action);
    }

    public function method(string $method): self
    {
        return $this->attr('method', strtoupper($method));
    }

    public function render(): string
    {
        return '<form' . $this->renderAttributes() . '>' . $this->renderChildren() . '</form>';
    }
}
