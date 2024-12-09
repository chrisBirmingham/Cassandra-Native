<?php

namespace CassandraNative\Type;

class UDTType implements \ArrayAccess, \Iterator
{
    protected string $name;

    protected array $fields;

    protected array $values;

    /**
     * @param string $name
     * @param array $fields
     * @param array $values
     */
    public function __construct(string $name, array $fields, array $values)
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->values = $values;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
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
    public function next(): void
    {
        next($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return key($this->values);
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
    public function rewind(): void
    {
        reset($this->values);
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
