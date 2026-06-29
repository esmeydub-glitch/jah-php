<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * Durable write-ahead log implemented entirely in PHP.
 */
final class WALTransactionCore
{
    private string $basePath;
    private ?string $activeTx = null;
    private array $txBuffer = [];
    private int $sequence = 0;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->init();
    }

    private function init(): void
    {
        foreach (['wal', 'tx/committed', 'storage'] as $dir) {
            $path = "{$this->basePath}/{$dir}";
            if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
                throw new RuntimeException("Cannot create WAL directory: {$path}");
            }
        }
    }

    public function begin(): string
    {
        if ($this->activeTx !== null) {
            throw new RuntimeException('A transaction is already active');
        }

        $this->activeTx = bin2hex(random_bytes(16));
        $this->txBuffer = [];
        $this->sequence = 0;
        $this->appendWAL([
            'tx_id' => $this->activeTx,
            'op' => 'begin',
            'ts' => microtime(true),
        ]);

        return $this->activeTx;
    }

    public function write(string $collection, array $doc, string $op = 'insert'): void
    {
        if ($this->activeTx === null) {
            throw new RuntimeException('No active transaction');
        }
        if (!in_array($op, ['insert', 'update', 'delete'], true)) {
            throw new RuntimeException("Unsupported WAL operation: {$op}");
        }

        $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) ?: 'default';

        $doc['id'] ??= bin2hex(random_bytes(16));
        $doc['id'] = (string)$doc['id'];
        if ($doc['id'] === '' || strlen($doc['id']) > 255 || preg_match('/[\x00-\x1F\x7F]/', $doc['id']) === 1) {
            throw new RuntimeException('Invalid WAL document id');
        }
        if ($op === 'delete') {
            $doc['_deleted'] = true;
        }

        $entry = [
            'tx_id' => $this->activeTx,
            'entry_id' => $this->activeTx . ':' . $this->sequence++,
            'op' => $op,
            'collection' => $collection,
            'doc' => $doc,
            'ts' => microtime(true),
        ];
        $entry['checksum'] = $this->checksum($entry);

        $this->txBuffer[] = $entry;
        $this->appendWAL($entry);
    }

    public function commit(): bool
    {
        if ($this->activeTx === null) {
            return false;
        }

        $txId = $this->activeTx;
        $this->appendWAL([
            'tx_id' => $txId,
            'op' => 'commit',
            'entries' => count($this->txBuffer),
            'ts' => microtime(true),
        ]);

        foreach ($this->txBuffer as $entry) {
            $this->applyToStorage($entry);
        }
        $this->markApplied($txId, count($this->txBuffer));

        $this->activeTx = null;
        $this->txBuffer = [];
        $this->sequence = 0;
        return true;
    }

    public function rollback(): void
    {
        if ($this->activeTx === null) {
            return;
        }

        $this->appendWAL([
            'tx_id' => $this->activeTx,
            'op' => 'rollback',
            'ts' => microtime(true),
        ]);

        $this->activeTx = null;
        $this->txBuffer = [];
        $this->sequence = 0;
    }

    private function appendWAL(array $entry): void
    {
        try {
            $line = PhpSerializer::encode($entry) . "\n";
        } catch (RuntimeException $e) {
            throw new RuntimeException('Cannot serialize WAL entry', 0, $e);
        }

        $this->appendDurable("{$this->basePath}/wal/active.wal", $line);
    }

    private function appendDurable(string $path, string $data): void
    {
        $fp = fopen($path, 'ab');
        if ($fp === false) {
            throw new RuntimeException("Cannot open durable file: {$path}");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException("Cannot lock durable file: {$path}");
            }

            $offset = 0;
            $length = strlen($data);
            while ($offset < $length) {
                $written = fwrite($fp, substr($data, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException("Cannot write durable file: {$path}");
                }
                $offset += $written;
            }

            if (!fflush($fp) || !fsync($fp)) {
                throw new RuntimeException("Cannot sync durable file: {$path}");
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function applyToStorage(array $entry): bool
    {
        $storage = "{$this->basePath}/storage/{$entry['collection']}.jahl";
        if ($this->storageContains($storage, $entry['entry_id'])) {
            return false;
        }

        $record = $entry['doc'];
        $record['_jah_wal'] = [
            'tx_id' => $entry['tx_id'],
            'entry_id' => $entry['entry_id'],
            'op' => $entry['op'],
        ];

        try {
            $line = PhpSerializer::encode($record) . "\n";
        } catch (RuntimeException $e) {
            throw new RuntimeException('Cannot serialize storage record', 0, $e);
        }
        $this->appendDurable($storage, $line);
        return true;
    }

    private function storageContains(string $path, string $entryId): bool
    {
        if (!is_file($path)) {
            return false;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = PhpSerializer::decode($line, true);
            if (($record['_jah_wal']['entry_id'] ?? null) === $entryId) {
                return true;
            }
        }
        return false;
    }

    private function markApplied(string $txId, int $entries): void
    {
        $path = "{$this->basePath}/tx/committed/{$txId}.jahp";
        $payload = PhpSerializer::encode([
            'tx_id' => $txId,
            'entries' => $entries,
            'applied_at' => microtime(true),
        ]) . "\n";
        $this->appendDurable($path, $payload);
    }

    public function recover(): array
    {
        $file = "{$this->basePath}/wal/active.wal";
        if (!is_file($file)) {
            return ['recovered' => 0, 'transactions' => 0, 'invalid_entries' => 0, 'last_committed' => null];
        }

        $transactions = [];
        $invalid = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = PhpSerializer::decode($line, true);
            if (!is_array($entry) || !isset($entry['tx_id'], $entry['op'])) {
                $invalid++;
                continue;
            }

            $txId = (string) $entry['tx_id'];
            $transactions[$txId] ??= ['entries' => [], 'committed' => false, 'rolled_back' => false];
            if (isset($entry['entry_id'])) {
                if (($entry['checksum'] ?? '') !== $this->checksum($entry)) {
                    $invalid++;
                    continue;
                }
                $transactions[$txId]['entries'][] = $entry;
            } elseif ($entry['op'] === 'commit') {
                $transactions[$txId]['committed'] = true;
            } elseif ($entry['op'] === 'rollback') {
                $transactions[$txId]['rolled_back'] = true;
            }
        }

        $recovered = 0;
        $recoveredTransactions = 0;
        $lastCommitted = null;
        foreach ($transactions as $txId => $transaction) {
            if (!$transaction['committed'] || $transaction['rolled_back']) {
                continue;
            }

            $lastCommitted = $txId;
            $applied = 0;
            foreach ($transaction['entries'] as $entry) {
                if ($this->applyToStorage($entry)) {
                    $applied++;
                }
            }
            if ($applied > 0) {
                $recovered += $applied;
                $recoveredTransactions++;
            }
            $this->markApplied($txId, count($transaction['entries']));
        }

        return [
            'recovered' => $recovered,
            'transactions' => $recoveredTransactions,
            'invalid_entries' => $invalid,
            'last_committed' => $lastCommitted,
        ];
    }

    private function checksum(array $entry): string
    {
        unset($entry['checksum']);
        return hash('sha256', PhpSerializer::encode($entry));
    }
}
