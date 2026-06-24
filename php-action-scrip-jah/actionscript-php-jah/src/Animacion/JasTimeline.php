<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasTimeline extends Element
{
    private array $items = [];

    public function at(int $ms, callable|object|string $item): self
    {
        $this->items[] = ['time' => max(0, $ms), 'item' => $item];
        return $this;
    }

    public function add(JasTween $tween): self
    {
        return $this->animation($tween);
    }

    public function onFinish(string $event): self
    {
        $this->attr('data-jah-event', $event);
        return $this->on('animationend', $event, ['timeline' => $this->id]);
    }

    public function collectAnimations(): array
    {
        $animations = parent::collectAnimations();
        foreach ($this->items as $item) {
            $value = $item['item'];
            if (is_callable($value)) {
                $value = $value();
            }
            if ($value instanceof JasTween) {
                $animations[] = $value->delay((int) $item['time']);
            }
            if ($value instanceof Element) {
                array_push($animations, ...$value->collectAnimations());
            }
        }

        return $animations;
    }

    public function render(): string
    {
        $html = '';
        foreach ($this->items as $item) {
            $value = $item['item'];
            if (is_callable($value)) {
                $value = $value();
            }
            if ($value instanceof Element) {
                $html .= $value->render();
            }
        }

        return $html;
    }

    protected function tag(): string
    {
        return 'div';
    }
}
