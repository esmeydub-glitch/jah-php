<?php

require_once __DIR__ . '/../Core/JahComponent.php';

class JahInput extends JahComponent
{
    private string $name;
    private string $label;
    private string $type = 'text';
    private string $value = '';
    private string $placeholder = '';
    private bool $required = false;

    public function __construct(string $name, string $label = '', string $id = '')
    {
        parent::__construct($id !== '' ? $id : 'jah_input_' . $name);
        $this->name = $name;
        $this->label = $label !== '' ? $label : $name;
        $this->class('jah-field');
        $this->attr('data-field', $name);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function value(string|int|float $value): self
    {
        $this->value = (string) $value;
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    public function render(): string
    {
        $fieldId = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($this->type, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($this->value, ENT_QUOTES, 'UTF-8');
        $placeholder = '';
        if ($this->placeholder !== '') {
            $placeholder = ' placeholder="' . htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8') . '"';
        }
        $required = $this->required ? ' required' : '';

        return '<label' . $this->renderAttributes() . '>' .
            '<span>' . htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8') . '</span>' .
            '<input id="' . $fieldId . '_control" name="' . $name . '" type="' . $type .
            '" value="' . $value . '"' . $placeholder . $required . '>' .
            '</label>';
    }
}
