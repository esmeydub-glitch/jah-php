<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * QueryPlanner - Consultas complejas sin SQL
 */
final class QueryPlanner
{
    private string $collection;
    private array $filters = [];
    private array $sorts = [];
    private int $limit = 0;
    private int $offset = 0;
    private array $aggregations = [];
    private ?string $groupByField = null;
    private DataCoreLightning $db;

    public function __construct(DataCoreLightning $db, string $collection)
    {
        $this->db = $db;
        $this->collection = $collection;
    }

    public static function from(DataCoreLightning $db, string $collection): self
    {
        return new self($db, $collection);
    }

    public function where(string $field, string $op, mixed $value): self
    {
        $this->filters[] = ['field' => $field, 'op' => $op, 'value' => $value];
        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sorts[] = ['field' => $field, 'direction' => $direction];
        return $this;
    }

    public function limit(int $count): self
    {
        $this->limit = $count;
        return $this;
    }

    public function offset(int $count): self
    {
        $this->offset = $count;
        return $this;
    }

    public function sum(string $field): self
    {
        $this->aggregations[] = ['type' => 'sum', 'field' => $field];
        return $this;
    }

    public function count(string $field = '*'): self
    {
        $this->aggregations[] = ['type' => 'count', 'field' => $field];
        return $this;
    }

    public function groupBy(string $field): self
    {
        $this->groupByField = $field;
        return $this;
    }

    public function execute(): array
    {
        $results = $this->buildFilter();

        // Apply sorts
        foreach ($this->sorts as $sort) {
            usort($results, fn($a, $b) => $sort['direction'] === 'asc'
                ? ($a[$sort['field']] <=> $b[$sort['field']])
                : ($b[$sort['field']] <=> $a[$sort['field']]));
        }

        // Apply offset/limit
        if ($this->offset > 0) {
            $results = array_slice($results, $this->offset);
        }
        if ($this->limit > 0) {
            $results = array_slice($results, 0, $this->limit);
        }

        // Apply aggregations
        if (!empty($this->aggregations)) {
            return $this->executeAggregations($results);
        }

        return $results;
    }

    private function buildFilter(): array
    {
        // Simple filter compilation
        return $this->db->query($this->collection, function ($doc) {
            foreach ($this->filters as $filter) {
                $val = $doc[$filter['field']] ?? null;
                $pass = match ($filter['op']) {
                    '=' => $val == $filter['value'],
                    '==' => $val === $filter['value'],
                    '!=' => $val != $filter['value'],
                    '>' => $val > $filter['value'],
                    '>=' => $val >= $filter['value'],
                    '<' => $val < $filter['value'],
                    '<=' => $val <= $filter['value'],
                    default => true,
                };
                if (!$pass) {
                    return false;
                }
            }
            return true;
        });
    }

    private function executeAggregations(array $data): array
    {
        $results = [];
        foreach ($this->aggregations as $agg) {
            $results[$agg['type']] = match ($agg['type']) {
                'sum' => array_sum(array_column($data, $agg['field'])),
                'count' => count($data),
                default => 0,
            };
        }
        return $results;
    }
}
