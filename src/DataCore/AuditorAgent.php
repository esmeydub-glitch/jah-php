<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class AuditorAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function audit(string $collection, StorageAgent $storage, IntegrityAgent $integrity): array
    {
        $issues = [];

        // Verificar integridad
        $corrupt = $integrity->verify($collection);
        if ($corrupt) {
            $issues = array_merge($issues, $corrupt);
        }

        // Verificar eventos huérfanos
        $eventsFile = $this->basePath . "/../events/{$collection}.events";
        if (file_exists($eventsFile)) {
            $idsInData = [];
            foreach ($storage->query($collection, fn($d) => true) as $doc) {
                $idsInData[$doc['id']] = true;
            }

            foreach (file($eventsFile) as $line) {
                $event = PhpSerializer::decode($line, true);
                if ($event && !isset($idsInData[$event['payload']['id'] ?? ''])) {
                    $issues[] = "orphan_event:{$event['id']}";
                }
            }
        }

        return $issues;
    }

    public function repair(string $collection, StorageAgent $storage): int
    {
        // Eliminar marcados como deleted
        $fixed = 0;
        $data = [];
        foreach ($storage->query($collection, fn($d) => true) as $doc) {
            if (!isset($doc['_deleted'])) {
                $data[$doc['id']] = $doc;
            } else {
                $fixed++;
            }
        }
        return $fixed;
    }
}