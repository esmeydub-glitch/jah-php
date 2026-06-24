<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\ActionScript\Core\Element;

final class JasAnimationCompiler
{
    public function compile(Element $root): JasCompiledAnimation
    {
        $animations = $root->collectAnimations();
        $css = '';
        $manifest = [
            'renderer' => 'css',
            'animations' => [],
        ];
        $events = [];

        foreach ($animations as $animation) {
            if (!$animation instanceof JasTween) {
                continue;
            }
            $css .= $animation->css();
            $data = $animation->manifest();
            $manifest['animations'][] = $data;
            if (!empty($data['events'])) {
                $events[] = $data['events'];
            }
        }

        return new JasCompiledAnimation($root->render(), $css, $manifest, $events);
    }
}
