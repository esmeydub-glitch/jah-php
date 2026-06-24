<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\Security\JahSalkToken;

final class JasTween
{
    private array $from = [];
    private array $to = [];
    private int $duration = 1000;
    private int $delay = 0;
    private string $ease = 'ease';
    private int|string $iterations = 1;
    private bool $alternate = false;
    private string $fillMode = 'forwards';
    private array $events = [];

    public function __construct(private string $target)
    {
    }

    public function from(array $state): self
    {
        $this->from = $state;
        return $this;
    }

    public function to(array $state): self
    {
        $this->to = $state;
        return $this;
    }

    public function duration(int $ms): self
    {
        $this->duration = max(0, $ms);
        return $this;
    }

    public function delay(int $ms): self
    {
        $this->delay = max(0, $ms);
        return $this;
    }

    public function ease(string $ease): self
    {
        $this->ease = $ease;
        return $this;
    }

    public function loop(int|string $iterations = 'infinite'): self
    {
        $this->iterations = $iterations;
        return $this;
    }

    public function alternate(bool $alternate = true): self
    {
        $this->alternate = $alternate;
        return $this;
    }

    public function forwards(bool $forwards = true): self
    {
        $this->fillMode = $forwards ? 'forwards' : 'none';
        return $this;
    }

    public function onStart(string $event): self
    {
        $this->events['start'] = $event;
        return $this;
    }

    public function onFinish(string $event): self
    {
        $this->events['finish'] = $event;
        return $this;
    }

    public function target(): string
    {
        return $this->target;
    }

    public function cssName(): string
    {
        return 'jas_anim_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $this->target) . '_' . substr(hash('sha1', json_encode($this->manifest())), 0, 8);
    }

    public function css(): string
    {
        $name = $this->cssName();
        $direction = $this->alternate ? ' alternate' : '';
        $css = '@keyframes ' . $name . '{from{' . $this->stateCss($this->from) . '}to{' . $this->stateCss($this->to) . '}}';
        $css .= '#' . $this->safeTarget() . '{animation:' . $name . ' ' . $this->duration . 'ms ' . $this->ease . ' ' . $this->delay . 'ms ' . $this->iterations . $direction . ' ' . $this->fillMode . ';}';

        return $css;
    }

    public function manifest(): array
    {
        return [
            'type' => 'tween',
            'target' => $this->target,
            'from' => $this->from,
            'to' => $this->to,
            'duration' => $this->duration,
            'delay' => $this->delay,
            'ease' => $this->ease,
            'iterations' => $this->iterations,
            'alternate' => $this->alternate,
            'fillMode' => $this->fillMode,
            'events' => $this->signedEvents(),
        ];
    }

    private function signedEvents(): array
    {
        $signed = [];
        foreach ($this->events as $phase => $event) {
            $payload = [
                'target' => $this->target,
                'phase' => $phase,
                'event' => $event,
            ];
            $signed[$phase] = [
                'event' => $event,
                'token' => class_exists(JahSalkToken::class) ? JahSalkToken::make([
                    'purpose' => 'animation_event',
                    'event' => $event,
                    'target' => $this->target,
                    'phase' => $phase,
                    'payload_hash' => JahSalkToken::payloadHash($payload),
                    'payload' => $payload,
                ]) : '',
            ];
        }

        return $signed;
    }

    private function stateCss(array $state): string
    {
        $css = '';
        $transform = [];

        if (isset($state['x']) || isset($state['y'])) {
            $transform[] = 'translate(' . (int) ($state['x'] ?? 0) . 'px,' . (int) ($state['y'] ?? 0) . 'px)';
        }
        if (isset($state['scale'])) {
            $transform[] = 'scale(' . (float) $state['scale'] . ')';
        }
        if (isset($state['rotate'])) {
            $transform[] = 'rotate(' . (float) $state['rotate'] . 'deg)';
        }
        if ($transform) {
            $css .= 'transform:' . implode(' ', $transform) . ';';
        }

        $map = [
            'width' => 'width',
            'height' => 'height',
            'opacity' => 'opacity',
            'background' => 'background',
            'color' => 'color',
            'borderRadius' => 'border-radius',
            'blur' => 'filter',
        ];
        foreach ($map as $key => $property) {
            if (!array_key_exists($key, $state)) {
                continue;
            }
            $value = $key === 'blur' ? 'blur(' . (int) $state[$key] . 'px)' : (string) $state[$key];
            if (in_array($key, ['width', 'height', 'borderRadius'], true) && is_numeric($value)) {
                $value .= 'px';
            }
            $css .= $property . ':' . $value . ';';
        }

        return $css;
    }

    private function safeTarget(): string
    {
        return str_replace(['.', ':'], ['\\.', '\\:'], $this->target);
    }
}
