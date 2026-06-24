<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahTable extends JahComponent
{
    private array $headers = [];
    private array $rows = [];

    public function __construct(string $id = '')
    {
        parent::__construct($id);
        $this->class('jah-table');
    }

    public function headers(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function row(array $cells): self
    {
        $this->rows[] = $cells;
        return $this;
    }

    public function render(): string
    {
        $thead = '';
        if ($this->headers) {
            $thead = '<thead><tr>';
            foreach ($this->headers as $header) {
                $thead .= '<th>' . $this->renderCell($header) . '</th>';
            }
            $thead .= '</tr></thead>';
        }

        $tbody = '<tbody>';
        foreach ($this->rows as $row) {
            $tbody .= '<tr>';
            foreach ($row as $cell) {
                $tbody .= '<td>' . $this->renderCell($cell) . '</td>';
            }
            $tbody .= '</tr>';
        }
        $tbody .= '</tbody>';

        return '<table' . $this->renderAttributes() . '>' . $thead . $tbody . '</table>';
    }

    private function renderCell(mixed $cell): string
    {
        if ($cell instanceof JahComponent) {
            return $cell->render();
        }

        return htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8');
    }
}
