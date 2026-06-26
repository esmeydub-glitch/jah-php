<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * JAH DataCore - Base de datos nativa PHP sin SQL
 * 15 agentes trabajando en arquitectura event-sourcing
 */
final class DataCore
{
    private DataMaster $dataMaster;
    private array $collections = [];

    public function __construct(string $basePath)
    {
        $this->dataMaster = new DataMaster($basePath);
    }

    public static function init(string $basePath): self
    {
        return new self($basePath);
    }

    public function collection(string $name): SchemaAgent
    {
        return $this->dataMaster->schema($name);
    }

    public function insert(string $collection, array $doc): string
    {
        return $this->dataMaster->insert($collection, $doc);
    }

    public function find(string $collection, string $id): ?array
    {
        return $this->dataMaster->find($collection, $id);
    }

    public function query(string $collection, callable $filter): array
    {
        return $this->dataMaster->query($collection, $filter);
    }

    public function update(string $collection, string $id, array $patch): bool
    {
        return $this->dataMaster->update($collection, $id, $patch);
    }

    public function delete(string $collection, string $id): bool
    {
        return $this->dataMaster->delete($collection, $id);
    }

    public function getStats(): array
    {
        return $this->dataMaster->getStats();
    }

    public function close(): void
    {
        $this->dataMaster->close();
    }
}