<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class SchemaAgent
{
    private string $basePath;
    private string $collection = '';

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function define(string $name): self
    {
        $this->collection = $name;
        $this->ensureCollection($name);
        return $this;
    }

    private function ensureCollection(string $name): void
    {
        $schemaFile = $this->basePath . "/{$name}.jahp";
        if (!file_exists($schemaFile)) {
            file_put_contents($schemaFile, PhpSerializer::encode([
                'name' => $name,
                'fields' => [],
                'created_at' => time(),
            ]));
        }
    }

    public function addField(string $field, string $type): self
    {
        $schema = PhpSerializer::decode(file_get_contents($this->basePath . "/{$this->collection}.jahp"), true) ?: [];
        $schema['fields'][$field] = $type;
        file_put_contents($this->basePath . "/{$this->collection}.jahp", PhpSerializer::encode($schema));
        return $this;
    }
}