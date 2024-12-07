<?php

namespace CassandraNative\Type;

class Tuple implements \ArrayAccess, \Iterator
{
    protected array $types;

    protected array $values;

    protected int $position = 0;

    /**
     * @param array $types
     * @param array $values
     */
    public function __construct(array $types, array $values)
    {
        $this->types = $types;
        $this->values = $values;
    }

	/**
     * @return int
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        return $this->values[$this->position];
    }

    /**
     * {@inheritDoc}
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return isset($this->values[$this->position]);
    }

        /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->values[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }

    /**
     * Yields the tuple values as a pair. The first item is the type of
     * the variable, the second is the value
     * 
     * @return Generator<array>
     */
    public function fetchAssoc(): \Generator
    {
        foreach ($this->values as $k => $v) {
            yield $this->types[$k] => $v;
        }
    }
}
