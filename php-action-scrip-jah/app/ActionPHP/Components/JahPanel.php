<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahPanel extends JahComponent
{
    private string $title = '';

    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-panel');
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
            $header = '<header class="jah-panel-header"><h2>' .
                htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') .
                '</h2></header>';
        }

        return '<section' . $this->renderAttributes() . '>' .
            $header .
            '<div class="jah-panel-body">' . $this->renderChildren() . '</div>' .
            '</section>';
    }
}
