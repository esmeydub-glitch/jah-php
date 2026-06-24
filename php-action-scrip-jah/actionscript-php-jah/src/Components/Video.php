<?php

declare(strict_types=1);

namespace Jah\ActionScript\Components;

use Jah\ActionScript\Core\Element;

final class Video extends Element
{
    public function __construct(string $src, string $id = '', array $props = [])
    {
        parent::__construct($id, $props);
        $this->class('asjah-video');
        $this->attr('src', $src);
        $this->attr('preload', $props['preload'] ?? 'metadata');
        $this->attr('controls', $props['controls'] ?? true);
    }

    protected function tag(): string
    {
        return 'video';
    }
}
