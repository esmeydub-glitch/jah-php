<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Select extends Element
{
    private string $name;
    private string $label;
    private array $options;
    private string $selected = '';

    public function __construct(string $name, array $options, string $label = '', string $id = '', array $props = [])
    {
        parent::__construct($id !== '' ? $id : 'select_' . $name, $props);
        $this->name = $name;
        $this->label = $label !== '' ? $label : $name;
        $this->options = $options;
        $this->selected = (string) ($props['selected'] ?? '');
        $this->class('asjah-field');
    }

    public function selected(string $value): self
    {
        $this->selected = $value;
        return $this;
    }

    public function render(): string
    {
        $options = '';
        foreach ($this->options as $value => $label) {
            $safeValue = self::escape((string) $value);
            $isSelected = (string) $value === $this->selected ? ' selected' : '';
            $options .= '<option value="' . $safeValue . '"' . $isSelected . '>' . self::escape((string) $label) . '</option>';
        }

        return '<label' . $this->renderAttributes() . '>' .
            '<span>' . self::escape($this->label) . '</span>' .
            '<select id="' . self::escape($this->id) . '_control" name="' . self::escape($this->name) . '">' .
            $options .
            '</select>' .
            '</label>';
    }

    protected function tag(): string
    {
        return 'label';
    }
}
