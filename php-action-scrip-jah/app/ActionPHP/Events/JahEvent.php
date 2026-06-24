<?php

class JahEvent
{
    public function __construct(
        public readonly string $name,
        public readonly array $payload = []
    ) {
    }
}
