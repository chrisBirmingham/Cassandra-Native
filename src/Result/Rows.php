<?php

namespace CassandraNative\Result;

class Rows implements \ArrayAccess, \Iterator
{
    protected int $position = 0;

    protected array $results;

    /**
     * @param array[] $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->results);
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
        return $this->results[$this->position];
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
        return isset($this->results[$this->position]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->results[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): ?array
    {
        return $this->results[$offset] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->results[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->results[$offset]);
    }
}
