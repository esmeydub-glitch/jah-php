<?php

declare(strict_types=1);

namespace Jah;

use InvalidArgumentException;

/**
 * Runtime type contracts for JAS. It validates PHP values without TypeScript.
 */
final class JasTypeScript
{
    private array $aliases = [];
    private array $shapes = [];

    public function declare(string $type, string $alias): void
    {
        $this->aliases[$alias] = $type;
    }

    public function getAlias(string $alias): ?string
    {
        return $this->aliases[$alias] ?? null;
    }

    public function define(string $name, array $shape): self
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid type name: {$name}");
        }
        foreach ($shape as $field => $type) {
            if (!is_string($field) || !is_string($type)) {
                throw new InvalidArgumentException('A shape must map field names to type expressions');
            }
        }
        $this->shapes[$name] = $shape;
        return $this;
    }

    public function validate(string $type, mixed $value): bool
    {
        foreach (explode('|', $type) as $candidate) {
            if ($this->matches(trim($candidate), $value)) {
                return true;
            }
        }
        return false;
    }

    public function assert(string $type, mixed $value, string $label = 'value'): mixed
    {
        if (!$this->validate($type, $value)) {
            throw new InvalidArgumentException("{$label} does not match {$type}");
        }
        return $value;
    }

    public function compile(string $type, mixed $value): mixed
    {
        return $this->assert($type, $value);
    }

    private function matches(string $type, mixed $value): bool
    {
        if (isset($this->aliases[$type])) {
            return $this->validate($this->aliases[$type], $value);
        }
        if (str_ends_with($type, '[]')) {
            if (!is_array($value)) {
                return false;
            }
            $itemType = substr($type, 0, -2);
            foreach ($value as $item) {
                if (!$this->validate($itemType, $item)) {
                    return false;
                }
            }
            return true;
        }
        if (isset($this->shapes[$type])) {
            if (!is_array($value)) {
                return false;
            }
            foreach ($this->shapes[$type] as $field => $fieldType) {
                $optional = str_ends_with($field, '?');
                $fieldName = $optional ? substr($field, 0, -1) : $field;
                if (!array_key_exists($fieldName, $value)) {
                    if ($optional) {
                        continue;
                    }
                    return false;
                }
                if (!$this->validate($fieldType, $value[$fieldName])) {
                    return false;
                }
            }
            return true;
        }

        return match ($type) {
            'mixed', 'any' => true,
            'null' => $value === null,
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float' => is_float($value),
            'number' => is_int($value) || is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            default => $value instanceof $type,
        };
    }
}
