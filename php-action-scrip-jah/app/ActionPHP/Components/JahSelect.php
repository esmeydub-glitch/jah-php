<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahSelect extends JahComponent
{
    private string $name;
    private string $label;
    private array $options;
    private string $selected = '';

    public function __construct(string $name, array $options, string $label = '', string $id = '')
    {
        parent::__construct($id !== '' ? $id : 'jah_select_' . $name);
        $this->name = $name;
        $this->label = $label !== '' ? $label : $name;
        $this->options = $options;
        $this->class('jah-field');
    }

    public function selected(string $value): self
    {
        $this->selected = $value;
        return $this;
    }

    public function render(): string
    {
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $fieldId = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $options = '';

        foreach ($this->options as $value => $label) {
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $isSelected = (string) $value === $this->selected ? ' selected' : '';
            $options .= '<option value="' . $safeValue . '"' . $isSelected . '>' .
                htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') .
                '</option>';
        }

        return '<label' . $this->renderAttributes() . '>' .
            '<span>' . htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8') . '</span>' .
            '<select id="' . $fieldId . '_control" name="' . $name . '">' . $options . '</select>' .
            '</label>';
    }
}
