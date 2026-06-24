<?php

require_once __DIR__ . '/../Core/JahComponent.php';
require_once __DIR__ . '/JahCard.php';
require_once __DIR__ . '/JahPanel.php';

class JahDashboard extends JahComponent
{
    private array $cards = [];
    private array $panels = [];

    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-dashboard');
    }

    public function addCard(string $title, string|int|float $value, string $subtitle = ''): JahCard
    {
        $card = new JahCard($title, $value);
        if ($subtitle !== '') {
            $card->subtitle($subtitle);
        }
        $this->cards[] = $card;
        return $card;
    }

    public function addPanel(JahPanel $panel): self
    {
        $this->panels[] = $panel;
        return $this;
    }

    public function render(): string
    {
        $cards = '';
        if ($this->cards) {
            $cards = '<div class="jah-dashboard-cards">';
            foreach ($this->cards as $card) {
                $cards .= $card->render();
            }
            $cards .= '</div>';
        }

        $panels = '';
        foreach ($this->panels as $panel) {
            $panels .= $panel->render();
        }

        return '<section' . $this->renderAttributes() . '>' .
            $cards .
            $panels .
            $this->renderChildren() .
            '</section>';
    }
}
