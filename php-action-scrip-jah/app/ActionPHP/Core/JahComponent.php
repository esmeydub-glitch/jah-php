<?php

require_once __DIR__ . '/../../../jah php/security/JahSalkToken.php';

use Jah\Security\JahSalkToken;

abstract class JahComponent
{
    protected string $id;
    protected array $classes = [];
    protected array $attributes = [];
    protected array $children = [];

    public function __construct(string $id = '')
    {
        $this->id = $id !== '' ? $id : uniqid('jah_', true);
    }

    public function add(JahComponent $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    public function class(string $class): self
    {
        $this->classes[] = $class;
        return $this;
    }

    public function attr(string $key, string $value): self
    {
        $this->attributes[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    public function salkProtect(array $context = []): self
    {
        $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
        $context['purpose'] = 'component_event';
        $context['component_id'] = $this->id;
        $context['component_class'] = static::class;
        $context['event'] = $context['event'] ?? ($this->attributes['data-jah-event'] ?? '');
        $context['payload_hash'] = JahSalkToken::payloadHash($payload);

        $token = JahSalkToken::make($context);

        $this->attr('data-jah-component', $this->id);
        $this->attr('data-salk-token', $token);

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    protected function renderAttributes(): string
    {
        $attrs = ' id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '"';
        if ($this->classes) {
            $attrs .= ' class="' . htmlspecialchars(implode(' ', $this->classes), ENT_QUOTES, 'UTF-8') . '"';
        }
        foreach ($this->attributes as $key => $value) {
            $safe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $attrs .= " {$safe}=\"{$value}\"";
        }
        return $attrs;
    }

    protected function renderChildren(): string
    {
        return implode('', array_map(static fn($child) => $child->render(), $this->children));
    }

    abstract public function render(): string;
}
