<?php

namespace CassandraNative\Result;

class Rows implements \ArrayAccess, \Countable, \Iterator
{
    protected array $results;

    /**
     * @param array[] $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * {@inheritDoc}
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
        reset($this->results);
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        return current($this->results);
    }

    /**
     * {@inheritDoc}
     */
    public function key(): int
    {
        return key($this->results);
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        next($this->results);
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return key($this->results) !== null;
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
