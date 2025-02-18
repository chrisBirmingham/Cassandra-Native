<?php

namespace CassandraNative\Type;

class TupleType implements \ArrayAccess, \Countable, \Iterator
{
    protected array $types;

    protected array $values;

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
     * @return array
     */ 
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * {@inheritDoc}
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
        reset($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        return current($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function key(): int
    {
        return key($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        next($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return key($this->values) !== null;
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
}
