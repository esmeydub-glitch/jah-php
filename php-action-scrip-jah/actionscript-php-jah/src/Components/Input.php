<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Input extends Element
{
    private string $name;
    private string $label;

    public function __construct(string $name, string $label = '', string $id = '', array $props = [])
    {
        parent::__construct($id !== '' ? $id : 'input_' . $name, $props);
        $this->name = $name;
        $this->label = $label !== '' ? $label : $name;
        $this->class('asjah-field');
        $this->attr('type', $props['type'] ?? 'text');
        $this->attr('value', $props['value'] ?? '');
        if (isset($props['required'])) {
            $this->attr('required', (bool) $props['required']);
        }
    }

    public function render(): string
    {
        return '<label' . $this->renderAttributes() . '>' .
            '<span>' . self::escape($this->label) . '</span>' .
            '<input id="' . self::escape($this->id) . '_control" name="' . self::escape($this->name) . '"' .
            $this->renderControlAttributes() .
            '>' .
            '</label>';
    }

    protected function tag(): string
    {
        return 'label';
    }

    private function renderControlAttributes(): string
    {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            if (str_starts_with((string) $key, 'data-')) {
                continue;
            }
            if ($value === false) {
                continue;
            }
            if ($value === true) {
                $attrs .= ' ' . self::escape((string) $key);
                continue;
            }
            $attrs .= ' ' . self::escape((string) $key) . '="' . self::escape((string) $value) . '"';
        }

        return $attrs;
    }
}
