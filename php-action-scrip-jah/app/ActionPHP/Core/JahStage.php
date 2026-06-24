<?php

require_once __DIR__ . '/JahComponent.php';

class JahStage extends JahComponent
{
    public function __construct(string $id = 'main')
    {
        parent::__construct($id);
        $this->class('jah-stage');
    }

    public function render(): string
    {
        return '<section' . $this->renderAttributes() . '>' . $this->renderChildren() . '</section>';
    }
}
