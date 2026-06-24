<?php

require_once __DIR__ . '/JahComponent.php';

class JahRenderer
{
    public function render(JahComponent $component): string
    {
        return $component->render();
    }
}
