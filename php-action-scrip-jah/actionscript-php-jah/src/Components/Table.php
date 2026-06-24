<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Table extends Element
{
    private array $headers = [];
    private array $rows = [];

    public function __construct(string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-table');
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

    public function rows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->row($row);
        }

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

    protected function tag(): string
    {
        return 'table';
    }

    private function renderCell(mixed $cell): string
    {
        if ($cell instanceof Element) {
            return $cell->render();
        }

        return self::escape((string) $cell);
    }
}
