<?php

declare(strict_types=1);

namespace Jah\ActionScript\Core;

use Jah\Security\JahSalkToken;

abstract class Element
{
    protected string $id;
    protected array $props = [];
    protected array $classes = [];
    protected array $attributes = [];
    protected array $styles = [];
    protected array $children = [];
    protected array $animations = [];

    public function __construct(string $id = '', array $props = [])
    {
        $this->id = $id !== '' ? $id : uniqid('asjah_', true);
        $this->props($props);
    }

    public function props(array $props): static
    {
        foreach ($props as $key => $value) {
            $this->props[$key] = $value;
        }

        return $this;
    }

    public function class(string $class): static
    {
        $this->classes[] = $class;
        return $this;
    }

    public function attr(string $key, string|int|float|bool $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function style(array $styles): static
    {
        foreach ($styles as $key => $value) {
            $this->styles[$key] = $value;
        }

        return $this;
    }

    public function child(Element|string|int|float $child): static
    {
        $this->children[] = $child;
        return $this;
    }

    public function children(array $children): static
    {
        foreach ($children as $child) {
            $this->child($child);
        }

        return $this;
    }

    public function on(string $domEvent, string $jahEvent, array $payload = []): static
    {
        $this->attr('data-as-event', $domEvent);
        $this->attr('data-jah-event', $jahEvent);
        $this->attr('data-jah-component', $this->id);

        if (class_exists(JahSalkToken::class)) {
            $context = [
                'purpose' => 'component_event',
                'component_id' => $this->id,
                'component_class' => static::class,
                'event' => $jahEvent,
                'dom_event' => $domEvent,
                'payload' => $payload,
                'payload_hash' => JahSalkToken::payloadHash($payload),
            ];
            $this->attr('data-salk-token', JahSalkToken::make($context));
        }

        return $this;
    }

    public function animation(object $animation): static
    {
        $this->animations[] = $animation;
        return $this;
    }

    public function collectAnimations(): array
    {
        $animations = $this->animations;
        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                array_push($animations, ...$child->collectAnimations());
            }
        }

        return $animations;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function render(): string
    {
        return '<' . $this->tag() . $this->renderAttributes() . '>' .
            $this->renderChildren() .
            '</' . $this->tag() . '>';
    }

    abstract protected function tag(): string;

    protected function renderAttributes(): string
    {
        $attrs = ' id="' . self::escape($this->id) . '"';
        if ($this->classes) {
            $attrs .= ' class="' . self::escape(implode(' ', array_unique($this->classes))) . '"';
        }

        if ($this->styles) {
            $attrs .= ' style="' . self::escape($this->renderStyle()) . '"';
        }

        foreach ($this->attributes as $key => $value) {
            if ($value === false) {
                continue;
            }

            $safeKey = self::escape((string) $key);
            if ($value === true) {
                $attrs .= ' ' . $safeKey;
                continue;
            }

            $attrs .= ' ' . $safeKey . '="' . self::escape((string) $value) . '"';
        }

        return $attrs;
    }

    protected function renderChildren(): string
    {
        $html = '';
        foreach ($this->children as $child) {
            $html .= $child instanceof Element ? $child->render() : self::escape((string) $child);
        }

        return $html;
    }

    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function renderStyle(): string
    {
        $style = '';
        foreach ($this->styles as $key => $value) {
            $style .= (string) $key . ':' . (string) $value . ';';
        }

        return $style;
    }
}
