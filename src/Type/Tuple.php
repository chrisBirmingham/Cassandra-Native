<?php

namespace CassandraNative\Type;

class Tuple implements \ArrayAccess, \Iterator
{
	protected array $values = [];

	protected int $position = 0;

    /**
     * @param array $values List of pairs where the first item is the value 
     *                      to insert and the second is the type of the 
     *                      value for cassandra 
     */
    public function __construct(array $values = [])
    {
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
    public function current(): array
    {
        return $this->values[$this->position][0];
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
        foreach ($this->values as $v) {
            yield $v[0] => $v[1];
        }
    }
}
