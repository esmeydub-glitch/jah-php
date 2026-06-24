<?php

require_once __DIR__ . '/JahComponent.php';

class JahScene extends JahComponent
{
    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-scene');
    }

    public function render(): string
    {
        return '<div' . $this->renderAttributes() . '>' . $this->renderChildren() . '</div>';
    }
}
