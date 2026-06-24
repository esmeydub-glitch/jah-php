<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahSprite extends JahComponent
{
    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-sprite');
    }

    public function render(): string
    {
        return '<div' . $this->renderAttributes() . '>' . $this->renderChildren() . '</div>';
    }
}
